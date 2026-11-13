<?php

namespace App\Http\Controllers;

use App\Exceptions\BankStatementException;
use App\Services\BankStatement\BankStatementBanks;
use App\Services\BankStatement\BankStatementConfirmationService;
use App\Services\BankStatement\BankStatementManualMatchService;
use App\Services\BankStatement\BankStatementUploadService;
use App\Services\ErrorDispatcher;
use App\Traits\ChecksMenuAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The Bank Statements page: anyone with the menu entry uploads a bank's statement file, and a user in a confirming role
 * (never the one who uploaded it) confirms it, which starts the matching and the payments in the background. The page only sends and shows; the work is in the
 * services under App\Services\BankStatement.
 */
class BankStatementController extends Controller
{
    use ChecksMenuAccess;

    const MENU_URL = '/tenant/bank-statements';

    protected BankStatementUploadService $uploads;
    protected BankStatementConfirmationService $confirmation;
    protected BankStatementManualMatchService $matching;

    /**
     * @param BankStatementUploadService $uploads
     * @param BankStatementConfirmationService $confirmation
     * @param BankStatementManualMatchService $matching
     */
    public function __construct(BankStatementUploadService $uploads, BankStatementConfirmationService $confirmation, BankStatementManualMatchService $matching) {
        $this->uploads = $uploads;
        $this->confirmation = $confirmation;
        $this->matching = $matching;
    }

    /**
     * The Bank Statements page.
     */
    public function index() {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        $banks = collect(BankStatementBanks::all())->map(fn ($bank) => ['label' => $bank['label'], 'columns' => $bank['columns']])->all();

        return view('property.payments.bank-statements', compact('banks'));
    }

    /**
     * The latest 100 uploads of each bank with their line counts, for the page's tables (AJAX). Each row says whether
     * this user may confirm it and whether it can still be deleted.
     */
    public function data() {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        try {
            // One query per bank, so a busy bank cannot push another bank's statements off its own table
            $uploads = collect();
            foreach (array_keys(BankStatementBanks::all()) as $bankKey) {
                $uploads = $uploads->concat(DB::table('cb_bank_statement_uploads as s')
                    ->leftJoin('josya_users as u', 'u.id', '=', 's.uploaded_by')
                    ->leftJoin('josya_users as c', 'c.id', '=', 's.confirmed_by')
                    ->where('s.bank', $bankKey)
                    ->orderByDesc('s.id')
                    ->limit(100)
                    ->get(['s.*', 'u.name as uploader', 'c.name as confirmer']));
            }
            $uploads = $uploads->sortByDesc('id')->values();

            $statuses = [];
            foreach (BankStatementBanks::all() as $bank => $definition) {
                $ids = $uploads->where('bank', $bank)->pluck('id')->all();

                if ($ids) {
                    foreach (DB::table($definition['table'])->whereIn('upload_id', $ids)->selectRaw('upload_id, match_status, COUNT(*) as total')->groupBy('upload_id', 'match_status')->get() as $row) {
                        $statuses[$row->upload_id][$row->match_status] = (int) $row->total;
                    }
                }
            }

            $canConfirm = $this->confirmation->canConfirm((int) Auth::id());
            $banks = BankStatementBanks::all();

            return response()->json([
                'success' => true,
                'data' => $uploads->map(fn ($upload) => [
                    'id' => $upload->id,
                    'bank_key' => $upload->bank,
                    'bank' => $banks[$upload->bank]['label'] ?? $upload->bank,
                    'file_name' => $upload->file_name,
                    'status' => $upload->status,
                    'line_count' => $upload->line_count,
                    'total_amount' => number_format((float) $upload->total_amount, 2),
                    'paid_count' => (int) $upload->paid_count,
                    'paid_amount' => number_format((float) $upload->paid_amount, 2),
                    'lines' => $statuses[$upload->id] ?? [],
                    'uploader' => $upload->uploader,
                    'uploaded_at' => substr((string) $upload->created_at, 0, 16),
                    'confirmer' => $upload->confirmer,
                    'confirmed_at' => $upload->confirmed_at ? substr((string) $upload->confirmed_at, 0, 16) : null,
                    'error' => $upload->error,
                    'can_confirm' => $canConfirm && $upload->status === 'Uploaded' && (int) $upload->uploaded_by !== (int) Auth::id(),
                    'needs_other_confirmer' => $canConfirm && $upload->status === 'Uploaded' && (int) $upload->uploaded_by === (int) Auth::id(),
                    'can_retry' => $canConfirm && $upload->status === 'Failed',
                    'can_delete' => $upload->status === 'Uploaded',
                ])->values(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading bank statement uploads: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to load the statements'], 500);
        }
    }

    /**
     * Upload one statement file for one bank and stage its lines (AJAX).
     */
    public function upload(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        try {
            $validated = $request->validate([
                'bank' => 'required|string',
                'statement' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120',
            ], [
                'statement.mimes' => 'Upload the statement as an xlsx, xls or csv file.',
                'statement.max' => 'The file is larger than 5 MB.',
                'statement.required' => 'Choose the statement file.',
            ]);

            $id = $this->uploads->upload($validated['bank'], $request->file('statement'), (int) Auth::id());

            return response()->json(['success' => true, 'id' => $id, 'message' => 'Statement uploaded. Check the lines, then someone else in a confirming role has to confirm it.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (BankStatementException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error uploading a bank statement: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to upload the statement'], 500);
        }
    }

    /**
     * The lines of one statement, 100 a page, optionally only one match status (AJAX). Each line says whether this user
     * can match it by hand (an Unmatched or Ambiguous line, once the statement has finished its run, for a confirmer who
     * did not capture it), or why not, and who matched it by hand when someone did.
     *
     * @param int $id the upload
     */
    public function lines(Request $request, $id) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        try {
            $upload = DB::table('cb_bank_statement_uploads')->where('id', $id)->first();

            if (!$upload) {
                return response()->json(['success' => false, 'message' => 'That statement does not exist.'], 404);
            }

            $definition = BankStatementBanks::get($upload->bank);
            $matchRefusal = $this->matching->refusal($upload->status, $upload->uploaded_by !== null ? (int) $upload->uploaded_by : null, (int) Auth::id());
            $query = DB::table($definition['table'] . ' as l')
                ->leftJoin('sys_pm_clients as t', 't.id', '=', 'l.tenant_id')
                ->leftJoin('sys_pm_tenants_payments as p', 'p.id', '=', 'l.payment_id')
                ->leftJoin('josya_users as mb', 'mb.id', '=', 'l.matched_by')
                ->where('l.upload_id', $id)
                ->when($request->filled('status'), fn ($q) => $q->where('l.match_status', $request->input('status')));

            $total = (clone $query)->count();
            $page = max(1, (int) $request->input('page', 1));
            $rows = $query->orderBy('l.row_no')->forPage($page, 100)->get([
                'l.id', 'l.row_no', 'l.matched_at', 'mb.name as matched_by_name', 'l.' . $definition['date'] . ' as date', 'l.' . $definition['ref'] . ' as reference', 'l.' . $definition['amount'] . ' as amount',
                'l.' . $definition['text'] . ' as description', 'l.unit_code', 'l.payer_name', 'l.match_status', 'l.match_note', 't.names as tenant',
                'p.receipt_number', 'l.receipt_status',
            ]);

            return response()->json([
                'success' => true,
                'total' => $total,
                'page' => $page,
                'pages' => (int) ceil($total / 100),
                'data' => $rows->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'can_match' => !$matchRefusal && in_array($row->match_status, BankStatementManualMatchService::MATCHABLE, true),
                    'match_hint' => $matchRefusal && in_array($row->match_status, BankStatementManualMatchService::MATCHABLE, true) ? $matchRefusal : null,
                    'matched_by' => $row->matched_by_name,
                    'matched_at' => $row->matched_at ? substr((string) $row->matched_at, 0, 16) : null,
                    'row_no' => $row->row_no,
                    'date' => $row->date,
                    'reference' => $row->reference,
                    'amount' => number_format((float) $row->amount, 2),
                    'description' => $row->description,
                    'unit_code' => $row->unit_code,
                    'payer_name' => $row->payer_name,
                    'tenant' => $row->tenant,
                    'receipt_number' => $row->receipt_number,
                    'receipt_status' => $row->receipt_status,
                    'status' => $row->match_status,
                    'note' => $row->match_note,
                ])->values(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading bank statement lines: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to load the lines'], 500);
        }
    }

    /**
     * Confirm a statement. The answer comes back at once; the matching and the payments run on the queue (AJAX).
     *
     * @param int $id the upload
     */
    public function confirm($id) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        try {
            $this->confirmation->confirm((int) $id, (int) Auth::id());

            return response()->json(['success' => true, 'message' => 'Confirmed. Matching and payment have started in the background; the page updates as it goes.']);
        } catch (BankStatementException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error confirming a bank statement: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to confirm the statement'], 500);
        }
    }

    /**
     * Start a statement's run again after it failed (AJAX).
     *
     * @param int $id the upload
     */
    public function retry($id) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        try {
            $this->confirmation->retry((int) $id, (int) Auth::id());

            return response()->json(['success' => true, 'message' => 'The run started again in the background.']);
        } catch (BankStatementException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error retrying bank statement matching: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to start matching'], 500);
        }
    }

    /**
     * Delete a statement that has not been confirmed (AJAX).
     *
     * @param int $id the upload
     */
    public function destroy($id) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        try {
            $this->uploads->delete((int) $id);

            return response()->json(['success' => true, 'message' => 'Statement deleted.']);
        } catch (BankStatementException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting a bank statement: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to delete the statement'], 500);
        }
    }

    /**
     * Who confirms now, the roles that can be chosen, and whether this user may change it (AJAX).
     */
    public function confirmers() {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        return response()->json([
            'label' => $this->confirmation->label(),
            'role_ids' => $this->confirmation->seats()['roles'],
            'has_named_users' => !empty($this->confirmation->seats()['users']),
            'roles' => $this->confirmation->roleOptions(),
            'can_change' => (bool) Auth::user()->isAbleTo('update_bank_statements'),
        ]);
    }

    /**
     * Choose the roles that confirm bank statements (AJAX). Needs update_bank_statements.
     */
    public function saveConfirmers(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }
        if (!Auth::user()->isAbleTo('update_bank_statements')) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to change who confirms.'], 403);
        }

        try {
            $validated = $request->validate(['role_ids' => 'required|array|min:1', 'role_ids.*' => 'integer']);
            $this->confirmation->setConfirmerRoles($validated['role_ids'], (int) Auth::id());

            return response()->json(['success' => true, 'message' => 'Saved. Bank statements are now confirmed by ' . $this->confirmation->label() . '.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Choose at least one role.'], 422);
        } catch (BankStatementException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error saving the bank statement confirmers: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to save the setting'], 500);
        }
    }
}
