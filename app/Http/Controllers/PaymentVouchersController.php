<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\ErrorDispatcher;
use App\Models\PaymentVoucherHeader;
use App\Models\PaymentVoucherLine;
use App\Models\ChartAccount;
use App\Models\InvoiceableItem;
use App\Models\CompanyBankAccount;
use App\Models\ReturnSummary;
use App\Models\Client;
use App\Services\ApprovalService;

class PaymentVouchersController extends Controller
{
    /**
     * Display the payment vouchers listing page.
     */
    public function index()
    {
        try {
            $currentYear  = (int) date('Y');
            $currentMonth = (int) date('m');
            $years        = range($currentYear, $currentYear - 4);

            return view('finance.vouchers.index', compact('currentYear', 'currentMonth', 'years'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading vouchers page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load payment vouchers page');
        }
    }

    /**
     * Show the create payment voucher form.
     */
    public function create()
    {
        try {
            $currentYear  = (int) date('Y');
            $currentMonth = (int) date('m');
            $years        = range($currentYear, $currentYear - 4);

            $invoiceableItems = InvoiceableItem::orderBy('item_name')
                ->get(['id', 'item_name', 'billing_code']);

            $bankAccounts = CompanyBankAccount::with('bank')
                ->where('current_status_id', 1)
                ->orderBy('account_name')
                ->get(['id', 'account_name', 'account_number', 'bank_id']);

            return view('finance.vouchers.create', compact(
                'currentYear', 'currentMonth', 'years',
                'invoiceableItems', 'bankAccounts'
            ));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading create voucher page: ' . $e->getMessage());
            return redirect()->route('payment.vouchers.index')->with('error', 'Failed to load create voucher page');
        }
    }

    /**
     * Get vouchers data for DataTable (AJAX).
     */
    public function getVouchersData(Request $request)
    {
        try {
            $year  = $request->input('year', date('Y'));
            $month = $request->input('month', date('m'));

            $vouchers = PaymentVoucherHeader::where('year', $year)
                ->where('month', $month)
                ->where('is_deleted', 0)
                ->orderByDesc('voucher_sequence')
                ->get();

            return response()->json([
                'success' => true,
                'count'   => $vouchers->count(),
                'data'    => $vouchers->map(function ($v) {
                    return [
                        'id'             => $v->id,
                        'encrypted_id'   => encrypt($v->id),
                        'voucher_number' => $v->voucher_number,
                        'voucher_date'   => $v->voucher_date?->format('d/m/Y') ?? '-',
                        'description'    => $v->description ?? '-',
                        'vendor_id'      => $v->vendor_id ?? '-',
                        'vendor_name'    => $v->vendor_name ?? '-',
                        'debit_amount'   => number_format($v->debit_amount ?? 0, 2),
                        'entry_count'    => $v->entry_count ?? 0,
                        'voucher_status' => $v->voucher_status ?? 'Pending',
                        'prepared_by'    => $v->prepared_by ?? '-',
                    ];
                }),
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching vouchers data: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch vouchers data'], 500);
        }
    }

    /**
     * Show voucher details page.
     */
    public function show($encryptedId)
    {
        try {
            $id      = decrypt($encryptedId);
            $voucher = PaymentVoucherHeader::findOrFail($id);

            return view('finance.vouchers.details', compact('voucher'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading voucher details: ' . $e->getMessage());
            return redirect()->route('payment.vouchers.index')->with('error', 'Failed to load voucher details');
        }
    }

    /**
     * Create a full voucher (header + all lines) in one transaction (AJAX, JSON body).
     */
    public function createFull(Request $request)
    {
        try {
            $validated = $request->validate([
                'vendor_type'               => ['required', 'string', Rule::in(['Landlord', 'Other'])],
                'vendor_id'                 => ['nullable', 'string', 'max:50'],
                'vendor_name'               => ['required', 'string', 'max:255'],
                'voucher_date'              => ['required', 'date'],
                'year'                      => ['required', 'integer', 'min:2000', 'max:2099'],
                'month'                     => ['required', 'integer', 'min:1', 'max:12'],
                'cheque_number'             => ['nullable', 'string', 'max:50'],
                'lines'                     => ['required', 'array', 'min:1'],
                'lines.*.entry_description' => ['required', 'string', 'max:500'],
                'lines.*.entry_number'      => ['nullable', 'string', 'max:100'],
                'lines.*.amount'            => ['required', 'numeric', 'min:0.01'],
                'lines.*.account_number'    => ['nullable', 'string', 'max:100'],
                'lines.*.account_bank'      => ['nullable', 'string', 'max:255'],
            ], [
                'lines.required' => 'Add at least one entry before creating the voucher.',
                'lines.min'      => 'Add at least one entry before creating the voucher.',
            ]);

            $maxSeq      = PaymentVoucherHeader::max('voucher_sequence') ?? 0;
            $nextSeq     = $maxSeq + 1;
            $voucherNum  = 'PV' . $validated['year'] . str_pad($validated['month'], 2, '0', STR_PAD_LEFT) . $nextSeq;
            $totalAmount = collect($validated['lines'])->sum('amount');

            $header = PaymentVoucherHeader::create([
                'voucher_sequence' => $nextSeq,
                'voucher_number'   => $voucherNum,
                'year'             => $validated['year'],
                'month'            => $validated['month'],
                'voucher_date'     => $validated['voucher_date'],
                'description'      => $validated['lines'][0]['entry_description'],
                'vendor_id'        => $validated['vendor_id'] ?: null,
                'vendor_name'      => $validated['vendor_name'],
                'vendor_type'      => $validated['vendor_type'],
                'debit_amount'     => $totalAmount,
                'credit_amount'    => 0,
                'entry_count'      => count($validated['lines']),
                'voucher_status'   => 'Pending',
                'cheque_number'    => $validated['cheque_number'] ?? null,
                'is_deleted'       => 0,
                'prepared_by'      => Auth::user()->name,
                'is_printed'       => 0,
            ]);

            foreach ($validated['lines'] as $line) {
                PaymentVoucherLine::create([
                    'entry_id'          => $header->id,
                    'voucher_number'    => $voucherNum,
                    'entry_number'      => $line['entry_number'] ?? null,
                    'entry_year'        => $validated['year'],
                    'entry_month'       => $validated['month'],
                    'account_number'    => $line['account_number'] ?? null,
                    'account_bank'      => $line['account_bank'] ?? null,
                    'entry_description' => $line['entry_description'],
                    'payee_name'        => $validated['vendor_name'],
                    'amount'            => $line['amount'],
                    'dr_cr'             => 'DR',
                    'is_approved'       => 'No',
                ]);
            }

            $approvalRequest = null;
            try {
                $approvalService = app(ApprovalService::class);
                $approvalRequest = $approvalService->initiateApproval(
                    'voucher',
                    $header->id,
                    'voucher_approval',
                    Auth::id()
                );
            } catch (Exception $e) {
                Log::warning('Could not initiate approval workflow for voucher: ' . $e->getMessage());
            }

            return response()->json([
                'success'        => true,
                'message'        => 'Voucher ' . $voucherNum . ' created successfully' . ($approvalRequest ? ' and submitted for approval' : ''),
                'voucher_number' => $voucherNum,
                'encrypted_id'   => encrypt($header->id),
                'approval_initiated' => $approvalRequest !== null,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating full voucher: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create voucher'], 500);
        }
    }

    /**
     * Store a new payment voucher header (AJAX).
     */
    public function storeHeader(Request $request)
    {
        try {
            $validated = $request->validate([
                'vendor_type'  => ['required', 'string', Rule::in(['Landlord', 'Other'])],
                'vendor_id'    => ['nullable', 'string', 'max:50'],
                'vendor_name'  => ['required', 'string', 'max:255'],
                'description'  => ['required', 'string', 'max:500'],
                'debit_amount' => ['required', 'numeric', 'min:0'],
                'voucher_date' => ['required', 'date'],
                'year'         => ['required', 'integer', 'min:2000', 'max:2099'],
                'month'        => ['required', 'integer', 'min:1', 'max:12'],
            ]);

            $maxSeq       = PaymentVoucherHeader::max('voucher_sequence') ?? 0;
            $nextSeq      = $maxSeq + 1;
            $voucherNumber = 'PV' . $validated['year'] . str_pad($validated['month'], 2, '0', STR_PAD_LEFT) . $nextSeq;

            $header = PaymentVoucherHeader::create([
                'voucher_sequence' => $nextSeq,
                'voucher_number'   => $voucherNumber,
                'year'             => $validated['year'],
                'month'            => $validated['month'],
                'voucher_date'     => $validated['voucher_date'],
                'description'      => $validated['description'],
                'vendor_id'        => $validated['vendor_id'] ?: null,
                'vendor_name'      => $validated['vendor_name'],
                'vendor_type'      => $validated['vendor_type'],
                'debit_amount'     => $validated['debit_amount'],
                'credit_amount'    => 0,
                'entry_count'      => 0,
                'voucher_status'   => 'Pending',
                'is_deleted'       => 0,
                'prepared_by'      => Auth::user()->name,
                'is_printed'       => 0,
            ]);

            return response()->json([
                'success'        => true,
                'message'        => 'Voucher header created successfully',
                'voucher_number' => $voucherNumber,
                'encrypted_id'   => encrypt($header->id),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating voucher header: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create voucher header'], 500);
        }
    }

    /**
     * Add a line to a voucher (AJAX).
     */
    public function addLine(Request $request, $encryptedId)
    {
        try {
            $id      = decrypt($encryptedId);
            $voucher = PaymentVoucherHeader::findOrFail($id);

            $validated = $request->validate([
                'entry_description' => ['required', 'string', 'max:500'],
                'account_number'    => ['nullable', 'string', 'max:100'],
                'cheque_number'     => ['nullable', 'string', 'max:50'],
                'amount'            => ['required', 'numeric', 'min:0.01'],
                'gl_account'        => ['nullable', 'string', 'max:50'],
                'credit_account'    => ['nullable', 'string', 'max:50'],
            ]);

            $maxEntry  = PaymentVoucherLine::where('voucher_number', $voucher->voucher_number)->max('entry_number') ?? 0;
            $nextEntry = $maxEntry + 1;

            $line = PaymentVoucherLine::create([
                'entry_id'          => $voucher->id,
                'voucher_number'    => $voucher->voucher_number,
                'entry_number'      => $nextEntry,
                'entry_year'        => $voucher->year,
                'entry_month'       => $voucher->month,
                'account_number'    => $validated['account_number'] ?? null,
                'cheque_number'     => $validated['cheque_number'] ?? null,
                'entry_description' => $validated['entry_description'],
                'amount'            => $validated['amount'],
                'gl_account'        => $validated['gl_account'] ?? null,
                'credit_account'    => $validated['credit_account'] ?? null,
                'dr_cr'             => 'DR',
                'is_approved'       => 'No',
            ]);

            $count = PaymentVoucherLine::where('voucher_number', $voucher->voucher_number)->count();
            $voucher->update(['entry_count' => $count]);

            return response()->json([
                'success' => true,
                'message' => 'Line added successfully',
                'line'    => [
                    'id'                => $line->id,
                    'entry_number'      => $line->entry_number,
                    'entry_description' => $line->entry_description,
                    'account_number'    => $line->account_number ?? '-',
                    'cheque_number'     => $line->cheque_number ?? '-',
                    'amount'            => number_format($line->amount, 2),
                    'gl_account'        => $line->gl_account ?? '-',
                    'credit_account'    => $line->credit_account ?? '-',
                    'voucher_number'    => $line->voucher_number,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error adding voucher line: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to add line'], 500);
        }
    }

    /**
     * Remove a voucher line (AJAX).
     */
    public function removeLine($lineId)
    {
        try {
            $line          = PaymentVoucherLine::findOrFail($lineId);
            $voucherNumber = $line->voucher_number;
            $line->delete();

            $count = PaymentVoucherLine::where('voucher_number', $voucherNumber)->count();
            PaymentVoucherHeader::where('voucher_number', $voucherNumber)->update(['entry_count' => $count]);

            return response()->json(['success' => true, 'message' => 'Line removed successfully']);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error removing voucher line: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to remove line'], 500);
        }
    }

    /**
     * Search GL accounts for DR/CR selects (AJAX).
     */
    public function searchGlAccounts(Request $request)
    {
        try {
            $search = trim($request->input('search', ''));
            if (strlen($search) < 2) {
                return response()->json(['accounts' => []]);
            }

            $accounts = ChartAccount::where(function ($q) use ($search) {
                $q->where('account_code', 'ILIKE', "%{$search}%")
                  ->orWhere('account_name', 'ILIKE', "%{$search}%");
            })
            ->orderBy('account_code')
            ->limit(40)
            ->get(['account_code', 'account_name']);

            return response()->json([
                'accounts' => $accounts->map(fn($a) => [
                    'account_code' => $a->account_code,
                    'account_name' => $a->account_name,
                ]),
            ]);
        } catch (Exception $e) {
            Log::error('Error searching GL accounts: ' . $e->getMessage());
            return response()->json(['accounts' => []], 500);
        }
    }

    /**
     * Get voucher lines data (AJAX).
     */
    public function getVoucherLines($encryptedId)
    {
        try {
            $id      = decrypt($encryptedId);
            $voucher = PaymentVoucherHeader::findOrFail($id);

            $lines = PaymentVoucherLine::where('voucher_number', $voucher->voucher_number)
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'count'   => $lines->count(),
                'header'  => [
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date'   => $voucher->voucher_date?->format('d/m/Y') ?? '-',
                    'description'    => $voucher->description,
                    'vendor_id'      => $voucher->vendor_id,
                    'vendor_name'    => $voucher->vendor_name,
                    'vendor_type'    => $voucher->vendor_type,
                    'debit_amount'   => number_format($voucher->debit_amount ?? 0, 2),
                    'credit_amount'  => number_format($voucher->credit_amount ?? 0, 2),
                    'voucher_status' => $voucher->voucher_status,
                    'prepared_by'    => $voucher->prepared_by,
                    'checked_by'     => $voucher->checked_by,
                    'authorized_by'  => $voucher->authorized_by,
                ],
                'data'    => $lines->map(function ($l) {
                    return [
                        'billing_code'     => $l->entry_number ?? '-',
                        'entry_description' => $l->entry_description ?? '-',
                        'payee_name'        => $l->payee_name ?? '-',
                        'account_number'    => $l->account_number ?? '-',
                        'account_bank'      => $l->account_bank ?? '-',
                        'cheque_number'     => $l->cheque_number ?? '-',
                        'amount'            => number_format($l->amount ?? 0, 2),
                        'gl_account'        => $l->gl_account ?? '-',
                        'is_approved'       => $l->is_approved ?? '-',
                    ];
                }),
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching voucher lines: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch voucher lines'], 500);
        }
    }

    /**
     * Get approved unpaid returns for returns vouchers tab (AJAX).
     */
    public function getApprovedReturns(Request $request)
    {
        try {
            $year = $request->input('year', date('Y'));
            $month = $request->input('month', date('m'));

            $returns = ReturnSummary::select([
                    'cb_returns_summary.id',
                    'cb_returns_summary.return_code',
                    'cb_returns_summary.landlord_code',
                    'cb_returns_summary.property_code',
                    'cb_returns_summary.year',
                    'cb_returns_summary.month',
                    'cb_returns_summary.net_balance',
                    'cb_returns_summary.return_description',
                    'cb_returns_summary.approved_date',
                    'sys_pm_clients.names as landlord_name',
                    'sys_pm_clients.reference_number',
                ])
                ->leftJoin('sys_pm_clients', 'cb_returns_summary.landlord_code', '=', 'sys_pm_clients.reference_number')
                ->where('cb_returns_summary.approved_status', 1)
                ->where('cb_returns_summary.paid_status', 0)
                ->where('cb_returns_summary.year', $year)
                ->where('cb_returns_summary.month', $month)
                ->orderBy('cb_returns_summary.landlord_code')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $returns->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'encrypted_id' => encrypt($r->id),
                        'return_code' => $r->return_code,
                        'landlord_code' => $r->landlord_code,
                        'landlord_name' => $r->landlord_name ?? '-',
                        'property_code' => $r->property_code ?? '-',
                        'net_balance' => number_format($r->net_balance ?? 0, 2),
                        'net_balance_raw' => $r->net_balance ?? 0,
                        'return_description' => $r->return_description ?? '-',
                        'period' => $r->year . '/' . str_pad($r->month, 2, '0', STR_PAD_LEFT),
                        'approved_date' => $r->approved_date ? $r->approved_date->format('d M Y') : '-',
                    ];
                }),
                'summary' => [
                    'total_count' => $returns->count(),
                    'total_amount' => number_format($returns->sum('net_balance'), 2),
                ],
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching approved returns: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch returns'], 500);
        }
    }
}
