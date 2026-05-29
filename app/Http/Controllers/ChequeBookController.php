<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\ChequeBook;
use App\Models\ChequeLeaf;
use App\Models\CompanyBankAccount;
use App\Services\ChequeBookService;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class ChequeBookController extends Controller
{
    public function __construct(private ChequeBookService $service) {}

    public function index()
    {
        try {
            return view('finance.cheque-books.index');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return redirect()->back()->with('error', 'Failed to load cheque books page.');
        }
    }

    public function getData()
    {
        try {
            $books = ChequeBook::with(['bankAccount.bank', 'createdBy'])
                ->orderByDesc('id')
                ->get()
                ->map(function ($b) {
                    return [
                        'id'                  => $b->id,
                        'encrypted_id'        => Crypt::encrypt($b->id),
                        'reference_number'    => $b->reference_number,
                        'first_cheque_number' => $b->first_cheque_number,
                        'leafs'               => $b->leafs,
                        'available_count'     => $b->available_count,
                        'used_count'          => $b->used_count,
                        'cancelled_count'     => $b->cancelled_count,
                        'bank_account_name'   => $b->bankAccount ? $b->bankAccount->account_name : '—',
                        'bank_name'           => ($b->bankAccount && $b->bankAccount->bank) ? $b->bankAccount->bank->bank_name : '—',
                        'status_label'        => $b->status_label,
                        'status_badge'        => $b->status_badge,
                        'created_by_name'     => $b->createdBy ? $b->createdBy->name : '—',
                        'created_at'          => $b->date_time ? $b->date_time->format('d M Y') : '—',
                        'notes'               => $b->notes,
                    ];
                });

            return response()->json(['data' => $books]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to load cheque books.'], 500);
        }
    }

    public function getBankAccounts()
    {
        try {
            $accounts = CompanyBankAccount::where('current_status_id', 1)
                ->with('bank')
                ->orderBy('account_name')
                ->get()
                ->map(fn($a) => [
                    'id'    => $a->id,
                    'label' => $a->account_name . ' — ' . ($a->bank ? $a->bank->bank_name : '') . ' (' . $a->account_number . ')',
                ]);

            return response()->json(['accounts' => $accounts]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to load bank accounts.'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'bank_account_id'     => 'required|integer|exists:sys_set_bank_accounts,id',
                'first_cheque_number' => 'required|string|max:50',
                'leafs'               => 'required|integer|min:1|max:500',
                'reference_number'    => 'nullable|string|max:100',
                'notes'               => 'nullable|string|max:2000',
                'cheque_type'         => 'required|string|in:open,closed',
            ]);

            if(ChequeBook::where('reference_number', $validated['reference_number'])->exists()) {
                return response()->json(['success' => false, 'errors' => ['reference_number' => ['The reference number must be unique.']]], 422);
            }

            $book = DB::transaction(function () use ($validated) {
                $book = new ChequeBook();
                $book->bank_account_id     = $validated['bank_account_id'];
                $book->first_cheque_number = $validated['first_cheque_number'];
                $book->leafs               = $validated['leafs'];
                $book->reference_number    = $validated['reference_number'] ?? null;
                $book->notes               = $validated['notes'] ?? null;
                $book->current_status_id   = 1;
                $book->created_by_id       = Auth::id();
                $book->date_time           = now();
                $book->cheque_type         = $validated['cheque_type'];
                $book->save();

                $this->service->generateLeaves($book);

                return $book;
            });

            return response()->json(['success' => true, 'id' => $book->id, 'encrypted_id' => Crypt::encrypt($book->id)]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to create cheque book.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $id = Crypt::decrypt($id);
            $book = ChequeBook::with([
                'bankAccount.bank',
                'bankAccount.branch',
                'createdBy',
            ])->findOrFail($id);

            return view('finance.cheque-books.show', compact('book'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return redirect()->route('cheque-books.index')->with('error', 'Cheque book not found.');
        }
    }

    public function getLeavesData(Request $request, $id)
    {
        // try {
        Log::info("Fetching leaves for cheque book ID: $id with status filter: " . $request->status);
            $query = ChequeLeaf::where('parent_id', $id)
                ->with(['lockedBy', 'usedBy', 'cancelledBy']);

            if ($request->filled('status')) {
                $query->where('current_status_id', (int) $request->status);
            }

            $leaves = $query->orderBy('cheque_number')->get()->map(function ($l) {
                return [
                    'id'             => $l->id,
                    'cheque_number'  => $l->cheque_number,
                    'status_label'   => $l->status_label,
                    'status_badge'   => $l->status_badge,
                    'current_status_id' => $l->current_status_id,
                    'locked_by'      => $l->lockedBy ? $l->lockedBy->name : null,
                    'locked_at'      => $l->locked_at ? $l->locked_at->format('d M Y H:i') : null,
                    'used_by'        => $l->usedBy ? $l->usedBy->name : null,
                    'used_at'        => $l->used_at ? $l->used_at->format('d M Y H:i') : null,
                    'used_for'       => $l->used_for,
                    'cancelled_by'   => $l->cancelledBy ? $l->cancelledBy->name : null,
                    'cancelled_at'   => $l->cancelled_at ? $l->cancelled_at->format('d M Y H:i') : null,
                    'cancel_reason'  => $l->cancel_reason,
                ];
            });

            return response()->json(['data' => $leaves]);
        // } catch (Exception $e) {
        //     ErrorDispatcher::dispatch($e);
        //     return response()->json(['error' => 'Failed to load cheque leaves.'], 500);
        // }
    }

    /**
     * Find and lock the next available cheque leaf for a given bank account (called from voucher creation).
     */
    public function lockNextAvailable(Request $request)
    {
        try {
            $request->validate(['bank_account_id' => 'required|integer']);

            $leaf = ChequeLeaf::where('bank_account_id', $request->bank_account_id)
                ->where('current_status_id', ChequeLeaf::STATUS_AVAILABLE)
                ->orderBy('cheque_number')
                ->first();

            if (!$leaf) {
                return response()->json([
                    'success' => false,
                    'message' => 'No available cheques for this bank account.',
                ], 404);
            }

            $locked = $this->service->lockCheque($leaf->id, Auth::id());

            return response()->json([
                'success'        => true,
                'leaf_id'        => $locked->id,
                'cheque_number'  => $locked->cheque_number,
                'lock_token'     => $locked->lock_token,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'message' => 'Failed to lock cheque.'], 500);
        }
    }

    /**
     * Release a cheque lock that was acquired during voucher creation.
     */
    public function releaseVoucherCheque(Request $request)
    {
        try {
            $request->validate([
                'leaf_id'    => 'required|integer',
                'lock_token' => 'required|string',
            ]);

            $this->service->releaseCheque((int) $request->leaf_id, $request->lock_token);

            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'message' => 'Failed to release cheque lock.'], 500);
        }
    }

    public function releaseLock($leafId)
    {
        try {
            $leaf = ChequeLeaf::findOrFail($leafId);

            if ($leaf->current_status_id !== ChequeLeaf::STATUS_LOCKED) {
                return response()->json(['success' => false, 'error' => 'This cheque is not locked.'], 422);
            }

            $this->service->forceReleaseCheque($leafId);

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to release lock.'], 500);
        }
    }

    public function cancelLeaf(Request $request, $leafId)
    {
        try {
            $request->validate([
                'cancel_reason' => 'required|string|min:5|max:1000',
            ]);

            $leaf = ChequeLeaf::findOrFail($leafId);

            if ($leaf->current_status_id === ChequeLeaf::STATUS_USED) {
                return response()->json(['success' => false, 'error' => 'A used cheque cannot be cancelled.'], 422);
            }

            if ($leaf->current_status_id === ChequeLeaf::STATUS_CANCELLED) {
                return response()->json(['success' => false, 'error' => 'This cheque is already cancelled.'], 422);
            }

            DB::table('sys_fin_cheque_books_261_repeat')
                ->where('id', $leafId)
                ->update([
                    'current_status_id' => ChequeLeaf::STATUS_CANCELLED,
                    'cancelled_by_id'   => Auth::id(),
                    'cancelled_at'      => now(),
                    'cancel_reason'     => $request->cancel_reason,
                    'lock_token'        => null,
                    'locked_by_id'      => null,
                    'locked_at'         => null,
                ]);

            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to cancel cheque.'], 500);
        }
    }
}
