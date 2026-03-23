<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\ClientReturnsBatch;
use App\Models\ReturnSummary;
use App\Models\ReturnClient;
use App\Jobs\UpdateAbstractThresholdsJob;

class ClientReturnsController extends Controller
{
    /**
     * Display the unpaid returns listing page
     */
    public function index()
    {
        try {
            $currentYear = (int) date('Y');
            $currentMonth = (int) date('m');
            $years = range($currentYear, $currentYear - 4);

            return view('property.returns.list', compact('currentYear', 'currentMonth', 'years'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading returns list page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load returns list page');
        }
    }

    /**
     * Get unpaid returns data for DataTable (AJAX)
     */
    public function getBatchesData(Request $request)
    {
        try {
            $year = $request->input('year', date('Y'));
            $month = $request->input('month', date('m'));
            $userId = auth()->id();

            // Check if user is top management
            $isTopManagement = DB::table('cb_role_user as ru')
                ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                ->where('ru.user_id', $userId)
                ->where('r.name', 'top_management')
                ->exists();

            $query = ReturnSummary::unpaid()
                ->where('year', $year)
                ->where('month', $month);

            // Role-based filtering: non-top-management only see their properties
            if (!$isTopManagement) {
                $query->whereIn('property_code', function ($sub) use ($userId) {
                    $sub->select('reference_number')
                        ->from('sys_pm_properties')
                        ->where(function ($q) use ($userId) {
                            $q->where('field_officer_id', $userId)
                                ->orWhere('zone_manager_id', $userId)
                                ->orWhere('zone_supervisor_id', $userId);
                        });
                });
            }

            $returns = $query->orderBy('property_code')
                ->orderBy('landlord_code')
                ->get();

            return response()->json([
                'success' => true,
                'count' => $returns->count(),
                'data' => $returns->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'encrypted_id' => encrypt($r->id),
                        'return_code' => $r->return_code,
                        'landlord_code' => $r->landlord_code,
                        'property_code' => $r->property_code ?? '-',
                        'return_description' => $r->return_description ?? '-',
                        'return_type' => $r->return_type ?? '-',
                        'period' => str_pad($r->month, 2, '0', STR_PAD_LEFT) . '/' . $r->year,
                        'commission_rate' => number_format($r->commission_rate ?? 0, 2),
                        'total_credit' => number_format($r->total_credit ?? 0, 2),
                        'total_debit' => number_format($r->total_debit ?? 0, 2),
                        'commission_amount' => number_format($r->commission_amount ?? 0, 2),
                        'net_amount' => number_format($r->net_amount ?? 0, 2),
                        'payable_units' => ($r->payable_units ?? 0) . '/' . ($r->total_units ?? 0),
                        'prepared_by' => $r->prepared_by ?? '-',
                        'approved_status' => $r->approved_status,
                        'prepared_status' => $r->prepared_status,
                    ];
                })
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching returns list data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch returns list data'
            ], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // UNPROCESSED RETURNS — separate from processed returns
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Show unprocessed return details page
     */
    public function showUnprocessedReturnDetails($id)
    {
        try {
            $id = decrypt($id);
            $return = ReturnSummary::findOrFail($id);

            // Verify it's unpaid
            if ($return->paid_status == 1) {
                return redirect()->route('processed.returns.details', encrypt($return->id));
            }

            $allReturns = ReturnSummary::unpaid()
                ->where('landlord_code', $return->landlord_code)
                ->where('property_code', $return->property_code)
                ->where('year', $return->year)
                ->where('month', $return->month)
                ->orderByRaw("CASE WHEN return_type = 'MAINRETURN' THEN 0 ELSE 1 END")
                ->orderBy('return_type')
                ->get(['id', 'return_code', 'return_type']);

            $lineTotals = ReturnClient::where('return_code', $return->return_code)
                ->selectRaw("
                    SUM(CASE WHEN dr_cr = 'CR' THEN net_amount ELSE 0 END) as total_cr,
                    SUM(CASE WHEN dr_cr = 'DR' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(commission_amount, 0) + COALESCE(vat_amount, 0) ELSE 0 END) as total_dr,
                    SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN net_amount ELSE 0 END) as rent_total,
                    SUM(CASE WHEN entry_type = 'Commission' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(commission_amount, 0) ELSE 0 END) as commission,
                    SUM(CASE WHEN entry_type = 'VAT' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(vat_amount, 0) ELSE 0 END) as vat,
                    SUM(CASE WHEN entry_type = 'Deductions Before Commission' THEN net_amount ELSE 0 END) as deductions,
                    SUM(CASE WHEN entry_type = 'Add Back Deductions' THEN net_amount ELSE 0 END) as add_back
                ")
                ->first();

            return view('property.returns.unprocessed-details', compact('return', 'lineTotals', 'allReturns'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading unprocessed return details: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load return details');
        }
    }

    /**
     * Get unprocessed return summary data via AJAX (for tab switching)
     */
    public function getUnprocessedReturnSummaryData($id)
    {
        try {
            $id = decrypt($id);
            $return = ReturnSummary::findOrFail($id);

            $lineTotals = ReturnClient::where('return_code', $return->return_code)
                ->selectRaw("
                    SUM(CASE WHEN dr_cr = 'CR' THEN net_amount ELSE 0 END) as total_cr,
                    SUM(CASE WHEN dr_cr = 'DR' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(commission_amount, 0) + COALESCE(vat_amount, 0) ELSE 0 END) as total_dr,
                    SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN net_amount ELSE 0 END) as rent_total,
                    SUM(CASE WHEN entry_type = 'Commission' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(commission_amount, 0) ELSE 0 END) as commission,
                    SUM(CASE WHEN entry_type = 'VAT' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(vat_amount, 0) ELSE 0 END) as vat,
                    SUM(CASE WHEN entry_type = 'Deductions Before Commission' THEN net_amount ELSE 0 END) as deductions,
                    SUM(CASE WHEN entry_type = 'Add Back Deductions' THEN net_amount ELSE 0 END) as add_back
                ")
                ->first();

            return response()->json([
                'success' => true,
                'return' => [
                    'id' => encrypt($return->id),
                    'return_code' => $return->return_code,
                    'property_code' => $return->property_code ?? '-',
                    'landlord_code' => $return->landlord_code,
                    'period' => str_pad($return->month, 2, '0', STR_PAD_LEFT) . '/' . $return->year,
                    'return_type' => $return->return_type ?? '-',
                    'commission_rate' => number_format($return->commission_rate ?? 0, 2),
                    'commission_total' => number_format($return->commission_total ?? 0, 2),
                    'approved_status' => $return->approved_status,
                    'prepared_by' => $return->prepared_by ?? '-',
                    'remarks' => trim(($return->remarks ?? '') . ' ' . ($return->remarks_2 ?? '')),
                    'payable_units' => $return->payable_units ?? 0,
                    'total_units' => $return->total_units ?? 0,
                    'payable_amount' => number_format($return->payable_amount ?? 0, 2),
                    'non_payable_amount' => number_format($return->non_payable_amount ?? 0, 2),
                ],
                'lineTotals' => [
                    'rent_total' => number_format($lineTotals->rent_total ?? 0, 2),
                    'add_back' => number_format($lineTotals->add_back ?? 0, 2),
                    'add_back_raw' => (float) ($lineTotals->add_back ?? 0),
                    'total_cr' => number_format($lineTotals->total_cr ?? 0, 2),
                    'deductions' => number_format($lineTotals->deductions ?? 0, 2),
                    'deductions_raw' => (float) ($lineTotals->deductions ?? 0),
                    'commission' => number_format($lineTotals->commission ?? 0, 2),
                    'vat' => number_format($lineTotals->vat ?? 0, 2),
                    'total_dr' => number_format($lineTotals->total_dr ?? 0, 2),
                    'net_amount' => number_format(($lineTotals->total_cr ?? 0) - ($lineTotals->total_dr ?? 0), 2),
                ],
                'unitsUrl' => route('ajax.returns.units', encrypt($return->id)),
                'excelUrl' => route('returns.excel', encrypt($return->id)),
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching unprocessed return summary: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load return data'], 500);
        }
    }

    /**
     * Get unit details for an unprocessed return (AJAX)
     */
    public function getUnprocessedReturnUnitsData($id)
    {
        try {
            $id = decrypt($id);
            $return = ReturnSummary::findOrFail($id);

            $details = ReturnClient::where('return_code', $return->return_code)
                ->orderBy('entry_type')
                ->orderBy('unit_code')
                ->orderBy('tenant_name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $details->map(function ($d) {
                    return [
                        'entry_type' => $d->entry_type ?? '-',
                        'tenant_name' => $d->tenant_name ?? '-',
                        'unit_code' => $d->unit_code ?? '-',
                        'entry_description' => $d->entry_description ?? '-',
                        'dr_cr' => $d->dr_cr ?? '-',
                        'amount_payable_landlord' => number_format($d->amount_payable_landlord ?? 0, 2),
                        'net_amount' => number_format($d->net_amount ?? 0, 2),
                        'vat_amount' => number_format($d->vat_amount ?? 0, 2),
                        'pay_percentage' => $d->pay_percentage ?? '',
                        'return_period' => $d->return_period ?? '-',
                    ];
                })
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching unprocessed return units: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load detail lines'], 500);
        }
    }

    /**
     * Download Excel for an unprocessed return
     */
    public function downloadUnprocessedExcel($id)
    {
        try {
            $id = decrypt($id);
            $return = ReturnSummary::findOrFail($id);

            $lines = ReturnClient::where('return_code', $return->return_code)
                ->orderBy('entry_type')
                ->orderBy('unit_code')
                ->orderBy('tenant_name')
                ->get();

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Return Details');

            // Header info
            $sheet->setCellValue('A1', 'Return Code');
            $sheet->setCellValue('B1', $return->return_code);
            $sheet->setCellValue('A2', 'Property');
            $sheet->setCellValue('B2', $return->property_code);
            $sheet->setCellValue('A3', 'Landlord');
            $sheet->setCellValue('B3', $return->landlord_code);
            $sheet->setCellValue('A4', 'Period');
            $sheet->setCellValue('B4', str_pad($return->month, 2, '0', STR_PAD_LEFT) . '/' . $return->year);
            $sheet->setCellValue('A5', 'Type');
            $sheet->setCellValue('B5', $return->return_type);
            $sheet->setCellValue('A6', 'Status');
            $sheet->setCellValue('B6', 'UNPAID');

            // Column headers
            $row = 8;
            $headers = ['#', 'Entry Type', 'Tenant', 'Unit', 'Description', 'Dr/Cr', 'Landlord Amt', 'Net', 'Commission', 'VAT', 'Pay %', 'Payable', 'Ref'];
            foreach ($headers as $col => $header) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $header);
            }
            $sheet->getStyle('A8:M8')->getFont()->setBold(true);

            // Data rows
            $row = 9;
            $num = 1;
            foreach ($lines as $line) {
                $sheet->setCellValueByColumnAndRow(1, $row, $num++);
                $sheet->setCellValueByColumnAndRow(2, $row, $line->entry_type);
                $sheet->setCellValueByColumnAndRow(3, $row, $line->tenant_name);
                $sheet->setCellValueByColumnAndRow(4, $row, $line->unit_code);
                $sheet->setCellValueByColumnAndRow(5, $row, $line->entry_description);
                $sheet->setCellValueByColumnAndRow(6, $row, $line->dr_cr);
                $sheet->setCellValueByColumnAndRow(7, $row, $line->amount_payable_landlord);
                $sheet->setCellValueByColumnAndRow(8, $row, $line->net_amount);
                $sheet->setCellValueByColumnAndRow(9, $row, $line->commission_amount);
                $sheet->setCellValueByColumnAndRow(10, $row, $line->vat_amount);
                $sheet->setCellValueByColumnAndRow(11, $row, $line->pay_percentage ? $line->pay_percentage . '%' : '');
                $sheet->setCellValueByColumnAndRow(12, $row, $line->payable_status ? 'Yes' : 'No');
                $sheet->setCellValueByColumnAndRow(13, $row, $line->transaction_ref);
                $row++;
            }

            foreach (range('A', 'M') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $filename = 'Unprocessed_Return_' . $return->return_code . '.xlsx';
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (Exception $e) {
            Log::error('Error downloading unprocessed return excel: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download excel');
        }
    }

    /**
     * Get batch details (AJAX)
     */
    public function getBatchDetails($id)
    {
        try {
            $batch = ClientReturnsBatch::with(['property', 'generatedBy', 'approvedBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'batch' => [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'batch_id' => $batch->batch_id,
                    'property_name' => $batch->property->property_name ?? 'N/A',
                    'property_ref' => $batch->property->reference_number ?? 'N/A',
                    'returns_count' => $batch->returns_count,
                    'total_trans_amount' => number_format($batch->total_trans_amount, 2),
                    'total_comm_amount' => number_format($batch->total_comm_amount, 2),
                    'total_payable_amount' => number_format($batch->total_payable_amount, 2),
                    'approval_status' => $batch->approval_status,
                    'approval_status_name' => $batch->approval_status_name,
                    'generated_by_name' => $batch->generatedBy->name ?? 'N/A',
                    'generated_at' => $batch->generated_at ? $batch->generated_at->format('d M Y, H:i') : 'N/A',
                    'approved_by_name' => $batch->approvedBy->name ?? '-',
                    'approved_at' => $batch->approved_at ? $batch->approved_at->format('d M Y, H:i') : '-',
                    'remarks' => $batch->remarks ?? '-'
                ]
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching batch details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch batch details'
            ], 500);
        }
    }

    /**
     * Show batch details page
     */
    public function showBatchDetails($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $batch = ClientReturnsBatch::with(['property', 'generatedBy'])->findOrFail($id);
            return view('property.returns.details', compact('batch'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading batch details page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load batch details page');
        }
    }

    /**
     * Get batch returns data for DataTable (AJAX)
     */
    public function getBatchReturnsData($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $batch = ClientReturnsBatch::findOrFail($id);

            $returns = DB::table('sys_pm_clients_returns as cr')
                ->join('sys_pm_clients as t', function ($join) {
                    $join->on('t.id', '=', 'cr.tenant_id')
                        ->where('t.type_id', '=', 2);
                })
                ->join('sys_pm_units as u', 'u.id', '=', 'cr.unit_id')
                ->join('sys_pm_debtors_invoices as i', 'i.id', '=', 'cr.invoice_id')
                ->where('cr.batch_id', $batch->batch_id)
                ->where('cr.property_id', $batch->property_id) // Filter by property_id
                ->select([
                    'cr.id',
                    'cr.ref_number',
                    't.names as tenant_name',
                    'u.unit_code',
                    'cr.trans_amount',
                    'cr.comm_amount',
                    'cr.vat_amount',
                    'cr.payable_amount',
                    'cr.abstract_threshold',
                    'cr.approval_status',
                    'cr.current_status_id',
                    'i.lease_id'
                ])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $returns->map(function ($return) {
                    return [
                        'id' => $return->id,
                        'ref_number' => $return->ref_number,
                        'tenant_name' => $return->tenant_name,
                        'unit_code' => $return->unit_code,
                        'trans_amount' => number_format($return->trans_amount, 2),
                        'comm_amount' => number_format($return->comm_amount, 2),
                        'vat_amount' => number_format($return->vat_amount, 2),
                        'payable_amount' => number_format($return->payable_amount, 2),
                        'abstract_threshold' => $return->abstract_threshold,
                        'approval_status' => $return->approval_status,
                        'current_status_id' => $return->current_status_id,
                        'lease_id' => $return->lease_id,
                        'id' => $return->id
                    ];
                })
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching batch returns data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch batch returns data'
            ], 500);
        }
    }
    /**
     * Get invoice history for a lease (AJAX)
     */
    public function getInvoiceHistory(Request $request, $leaseId)
    {
        try {
            $query = DB::table('sys_pm_debtors_invoices as spdi')
                ->join('sys_set_invoiceable_items as ssii', 'spdi.item_type_id', '=', 'ssii.id')
                ->where('spdi.lease_id', $leaseId)
                //->where('spdi.current_status_id', 1)
                ->orderBy('spdi.date_due', 'asc')
                ->select([
                    'spdi.id as invoice_id',
                    'ssii.item_name',
                    'ssii.advance_payable',
                    DB::raw("TO_CHAR(spdi.date_due, 'DD Mon YYYY') as date_due"),
                    'spdi.commisionable_amount',
                    'spdi.payable_amount',
                    'spdi.current_balance'
                ]);

            $invoices = $query->get();

            // Log query details
            Log::info('Invoice History Query', [
                'lease_id' => $leaseId,
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'count' => $invoices->count(),
                'data' => $invoices->toArray()
            ]);

            return response()->json([
                'success' => true,
                'data' => $invoices
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching invoice history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoice history'
            ], 500);
        }
    }

    /**
     * Get properties for dropdown (AJAX)
     */
    public function getProperties(Request $request)
    {
        try {
            $search = $request->search;

            $properties = Property::select(['id', 'property_name', 'reference_number', 'description'])
                ->when($search, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('property_name', 'LIKE', "%{$search}%")
                            ->orWhere('description', 'LIKE', "%{$search}%")
                            ->orWhere('reference_number', 'LIKE', "%{$search}%");
                    });
                })
                ->get();

            return response()->json([
                'success' => true,
                'properties' => $properties
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching properties: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch properties'
            ], 500);
        }
    }

    /**
     * Show the returns generation page
     */
    public function showGenerate()
    {
        try {
            return view('property.returns.generate-returns');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading returns generation page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load returns generation page');
        }
    }
    /**
     * Process returns generation
     * 3 raw SQL queries wrapped in a transaction:
     * 1) INSERT rent lines into cb_returns_clients (with commission_amount & vat_amount)
     * 2) UPDATE pay_percentage + net_amount + zero commission/vat for non-payable
     * 3) INSERT aggregated summaries into cb_returns_summary
     */
    public function processGenerate(Request $request)
    {
        try {
            $request->validate([
                'property_selection' => 'required|in:all,one',
                'property_id' => 'required_if:property_selection,one|nullable|integer|exists:sys_pm_properties,id',
                'returns_month' => 'required|date_format:Y-m'
            ]);

            $propertySelection = $request->property_selection;
            $propertyId = $request->property_id;
            $returnsMonth = $request->returns_month; // YYYY-MM
            $returnPeriod = str_replace('-', '', $returnsMonth); // YYYYMM
            $generationDate = now();
            $generatedBy = Auth::id();

            [$returnYear, $returnMonthNum] = explode('-', $returnsMonth);
            $returnYear = (int) $returnYear;
            $returnMonthNum = (int) $returnMonthNum;
            $monthName = date('F', mktime(0, 0, 0, $returnMonthNum, 1, $returnYear));
            $prevDate = date('Y-m', strtotime($returnsMonth . '-01 -1 month'));
            $monthStart = $returnsMonth . '-01';
            $nextMonthStart = date('Y-m-d', strtotime($monthStart . ' +1 month'));
            $prevMonthStart = $prevDate . '-01';
            $transactionDate = date('d-M-Y', strtotime($returnsMonth . '-05'));
            $preparedByName = DB::table('josya_users')->where('id', $generatedBy)->value('name') ?? 'System';

            // Property filter clause
            $propertyFilter = '';
            $propertyFilterSummary = '';
            $propBindings = [];
            if ($propertySelection === 'one') {
                $propertyFilter = ' AND i.property_id = ?';
                $propertyFilterSummary = ' AND rc.property_id = ?';
                $propBindings = [$propertyId];
            }

            DB::beginTransaction();

            // ═══════════════════════════════════════════════════════════════════
            // COUNT INVOICES
            // ═══════════════════════════════════════════════════════════════════
            $totalInvoices = DB::selectOne("
                SELECT COUNT(*) as cnt FROM sys_pm_debtors_invoices i
                WHERE i.date_due >= ?::date AND i.date_due < ?::date
                  AND i.current_status_id = 1
                  AND i.debtor_type_id = '2'
                  AND i.property_id IS NOT NULL
                  AND i.unit_id IS NOT NULL
                  AND i.lease_id IS NOT NULL
                  AND i.tenant_id IS NOT NULL
                  {$propertyFilter}
            ", array_merge([$monthStart, $nextMonthStart], $propBindings))->cnt;

            if ($totalInvoices == 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No approved invoices found for the selected period.'
                ], 422);
            }

            Log::info("=== RETURN GENERATION START ===", [
                'returns_month' => $returnsMonth,
                'total_invoices' => $totalInvoices,
                'property_selection' => $propertySelection,
            ]);

            // ═══════════════════════════════════════════════════════════════════
            // DELETE EXISTING UN-APPROVED RETURNS FOR THIS PERIOD
            // ═══════════════════════════════════════════════════════════════════
            if ($propertySelection === 'one') {
                DB::delete("
                    DELETE FROM cb_returns_clients
                    WHERE return_period = ?
                      AND property_id = ?
                      AND return_code IN (
                          SELECT return_code FROM cb_returns_summary
                          WHERE year = ? AND month = ?
                            AND (approved_status = 0 OR approved_status IS NULL)
                            AND property_code = (SELECT reference_number FROM sys_pm_properties WHERE id = ?)
                      )
                ", [$returnPeriod, $propertyId, $returnYear, $returnMonthNum, $propertyId]);

                DB::delete("
                    DELETE FROM cb_returns_summary
                    WHERE year = ? AND month = ?
                      AND (approved_status = 0 OR approved_status IS NULL)
                      AND property_code = (SELECT reference_number FROM sys_pm_properties WHERE id = ?)
                ", [$returnYear, $returnMonthNum, $propertyId]);
            } else {
                DB::delete("
                    DELETE FROM cb_returns_clients
                    WHERE return_period = ?
                      AND return_code IN (
                          SELECT return_code FROM cb_returns_summary
                          WHERE year = ? AND month = ?
                            AND (approved_status = 0 OR approved_status IS NULL)
                      )
                ", [$returnPeriod, $returnYear, $returnMonthNum]);

                DB::delete("
                    DELETE FROM cb_returns_summary
                    WHERE year = ? AND month = ?
                      AND (approved_status = 0 OR approved_status IS NULL)
                ", [$returnYear, $returnMonthNum]);
            }

            // Get next return_id base
            $baseReturnId = DB::selectOne("SELECT COALESCE(MAX(return_id), 35217) + 1 as next_id FROM cb_returns_summary")->next_id;

            // Common WHERE clause for invoice selection
            $invoiceWhere = "
                i.date_due >= ?::date AND i.date_due < ?::date
                AND i.current_status_id = 1
                AND i.debtor_type_id = '2'
                AND i.property_id IS NOT NULL
                AND i.unit_id IS NOT NULL
                AND i.lease_id IS NOT NULL
                AND i.tenant_id IS NOT NULL
                {$propertyFilter}
            ";

            // Common JOINs
            $invoiceJoins = "
                FROM sys_pm_debtors_invoices i
                JOIN sys_pm_properties p ON p.id = i.property_id
                JOIN sys_pm_units u ON u.id = i.unit_id
                JOIN sys_pm_clients t ON t.id = i.tenant_id
                LEFT JOIN sys_pm_service_agreements s ON s.property_id = i.property_id
                LEFT JOIN sys_pm_clients c ON c.id = s.client_d
            ";

            // Approved-return exclusion
            $notApproved = "
                AND NOT EXISTS (
                    SELECT 1 FROM cb_returns_summary rs
                    WHERE rs.return_code = 'R1' || ? || COALESCE(c.reference_number, 'UNKNOWN') || p.reference_number
                      AND rs.approved_status = 1
                )
            ";

            $baseBindings = array_merge([$monthStart, $nextMonthStart], $propBindings, [$returnPeriod]);

            // ═══════════════════════════════════════════════════════════════════
            // QUERY 1: INSERT RENT LINES (one per invoice/tenant)
            // ═══════════════════════════════════════════════════════════════════
            $rentInserted = DB::affectingStatement("
                INSERT INTO cb_returns_clients (
                    return_code, return_period, transaction_number, transaction_date,
                    entry_description, entry_type, dr_cr,
                    property_code, unit_code, tenant_name,
                    net_amount, amount_payable_landlord,
                    vat_amount, commission_amount,
                    property_id, client_id, zone_id, field_officer_id,
                    zone_supervisor_id, zone_manager_id, unit_id, tenant_id,
                    return_id, invoice_id, narration, transaction_ref,
                    is_commissionable
                )
                SELECT
                    'R1' || ? || COALESCE(c.reference_number, 'UNKNOWN') || p.reference_number,
                    ?::integer,
                    '',
                    ?,
                    'RENT',
                    'RENT CURRENT',
                    'CR',
                    p.reference_number,
                    u.unit_code,
                    t.names,
                    i.total_amount,
                    i.total_amount,
                    ROUND(ROUND(i.total_amount * COALESCE(s.commission_percentage, 0) / 100, 2) * COALESCE(s.vat_per, 16) / 100, 2),
                    ROUND(i.total_amount * COALESCE(s.commission_percentage, 0) / 100, 2),
                    i.property_id,
                    COALESCE(s.client_d, 0),
                    i.zone_id,
                    i.field_officer_id,
                    i.zone_supervisor_id,
                    i.zone_manager_id,
                    i.unit_id,
                    i.tenant_id,
                    ? + DENSE_RANK() OVER (ORDER BY i.property_id) - 1,
                    i.id,
                    'Rent for {$monthName} {$returnYear}',
                    i.reference_number,
                    'Yes'
                {$invoiceJoins}
                WHERE {$invoiceWhere}
                {$notApproved}
            ", array_merge(
                [$returnPeriod, $returnPeriod, $transactionDate, $baseReturnId],
                $baseBindings
            ));

            Log::info("Query 1: Rent lines inserted", ['count' => $rentInserted]);

            if ($rentInserted == 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No returns generated. All returns may already be approved.'
                ], 422);
            }

            // ═══════════════════════════════════════════════════════════════════
            // QUERY 2: UPDATE PAY PERCENTAGE, PAYABLE STATUS & NET AMOUNT
            // Sets threshold from prev-month invoice, then zeroes net_amount
            // for non-payable tenants (<85%) in a single UPDATE
            // ═══════════════════════════════════════════════════════════════════
            DB::statement("
                UPDATE cb_returns_clients rc
                SET pay_percentage = sub.pct,
                    current_balance = sub.prev_balance,
                    total_amount = sub.prev_total,
                    threshold_percentage = sub.pct_num,
                    payable_status = sub.pay_status,
                    net_amount = CASE WHEN sub.pay_status IN (1, 2, 3) THEN rc.amount_payable_landlord ELSE 0 END,
                    commission_amount = CASE WHEN sub.pay_status IN (1, 2, 3) THEN rc.commission_amount ELSE 0 END,
                    vat_amount = CASE WHEN sub.pay_status IN (1, 2, 3) THEN rc.vat_amount ELSE 0 END
                FROM (
                    SELECT
                        rc2.id AS rc_id,
                        COALESCE(pi.current_balance, 0) AS prev_balance,
                        COALESCE(pi.total_amount, 0) AS prev_total,
                        CASE
                            WHEN pi.id IS NOT NULL THEN
                                CASE
                                    WHEN pi.total_amount > 0 AND pi.current_balance <= 0 THEN 100.00
                                    WHEN pi.total_amount > 0 AND pi.current_balance >= pi.total_amount THEN 0.00
                                    WHEN pi.total_amount > 0 THEN ROUND(((pi.total_amount - pi.current_balance) / pi.total_amount) * 100, 2)
                                    ELSE 0.00
                                END
                            WHEN NOT EXISTS (
                                SELECT 1 FROM sys_pm_debtors_invoices older
                                WHERE older.tenant_id = rc2.tenant_id
                                  AND older.date_due < ?::date
                                  AND older.current_status_id = 1
                                  AND older.debtor_type_id = '2'
                            ) THEN 100.00
                            ELSE 0.00
                        END AS pct_num,
                        CASE
                            WHEN pi.id IS NOT NULL THEN
                                CASE
                                    WHEN pi.total_amount > 0 AND pi.current_balance <= 0 THEN '100'
                                    WHEN pi.total_amount > 0 AND pi.current_balance >= pi.total_amount THEN '0'
                                    WHEN pi.total_amount > 0 THEN ROUND(((pi.total_amount - pi.current_balance) / pi.total_amount) * 100, 2)::text
                                    ELSE '0'
                                END
                            WHEN NOT EXISTS (
                                SELECT 1 FROM sys_pm_debtors_invoices older
                                WHERE older.tenant_id = rc2.tenant_id
                                  AND older.date_due < ?::date
                                  AND older.current_status_id = 1
                                  AND older.debtor_type_id = '2'
                            ) THEN '100'
                            ELSE '0'
                        END AS pct,
                        CASE
                            WHEN pi.id IS NOT NULL AND (
                                (pi.total_amount > 0 AND pi.current_balance <= 0)
                                OR (pi.total_amount > 0 AND ROUND(((pi.total_amount - pi.current_balance) / pi.total_amount) * 100, 2) >= 85)
                            ) THEN 1
                            WHEN pi.id IS NULL AND NOT EXISTS (
                                SELECT 1 FROM sys_pm_debtors_invoices older
                                WHERE older.tenant_id = rc2.tenant_id
                                  AND older.date_due < ?::date
                                  AND older.current_status_id = 1
                                  AND older.debtor_type_id = '2'
                            ) THEN 2
                            ELSE 0
                        END AS pay_status
                    FROM cb_returns_clients rc2
                    LEFT JOIN LATERAL (
                        SELECT pi2.id, pi2.current_balance, pi2.total_amount
                        FROM sys_pm_debtors_invoices pi2
                        WHERE pi2.tenant_id = rc2.tenant_id
                          AND pi2.date_due >= ?::date AND pi2.date_due < ?::date
                          AND pi2.current_status_id = 1
                          AND pi2.debtor_type_id = '2'
                        ORDER BY pi2.id DESC LIMIT 1
                    ) pi ON true
                    WHERE rc2.return_period = ?
                      AND rc2.entry_type = 'RENT CURRENT'
                      {$propertyFilterSummary}
                ) sub
                WHERE rc.id = sub.rc_id
            ", array_merge(
                [$monthStart, $monthStart, $monthStart, $prevMonthStart, $monthStart, $returnPeriod],
                $propBindings
            ));

            Log::info("Query 2: Updated pay percentages, net_amount, commission and vat");

            // ═══════════════════════════════════════════════════════════════════
            // QUERY 3: INSERT SUMMARIES INTO cb_returns_summary
            // ═══════════════════════════════════════════════════════════════════
            $q3 = "
                INSERT INTO cb_returns_summary (
                    return_code, landlord_code, property_code, year, month,
                    return_description, return_period, return_type,
                    commission_rate, total_credit, commission_total, vatable_total,
                    commission_amount, total_debit, net_amount,
                    transaction_amount, net_balance,
                    prepared_by, prepared_status, prepared_date,
                    approved_status, paid_status, return_id,
                    payable_amount, non_payable_amount, payable_units, total_units
                )
                SELECT
                    rc.return_code,
                    COALESCE(
                        (SELECT cl.reference_number FROM sys_pm_clients cl WHERE cl.id = rc.client_id LIMIT 1),
                        'UNKNOWN'
                    ) AS landlord_code,
                    rc.property_code,
                    ? AS year,
                    ? AS month,
                    COALESCE(
                        (SELECT pp.property_name FROM sys_pm_properties pp WHERE pp.id = rc.property_id LIMIT 1),
                        rc.property_code
                    ) || '-{$monthName} {$returnYear}' AS return_description,
                    ? AS return_period,
                    'MAINRETURN' AS return_type,
                    CASE WHEN SUM(rc.commission_amount) > 0 AND SUM(rc.net_amount) > 0
                        THEN ROUND(SUM(rc.commission_amount) / SUM(rc.net_amount) * 100, 2)
                        ELSE 0
                    END AS commission_rate,
                    SUM(rc.net_amount) AS total_credit,
                    SUM(rc.net_amount) AS commission_total,
                    0 AS vatable_total,
                    SUM(rc.commission_amount) AS commission_amount,
                    SUM(rc.commission_amount) + SUM(rc.vat_amount) AS total_debit,
                    SUM(rc.net_amount) - SUM(rc.commission_amount) - SUM(rc.vat_amount) AS net_amount,
                    0 AS transaction_amount,
                    0 AS net_balance,
                    ? AS prepared_by,
                    1 AS prepared_status,
                    NOW() AS prepared_date,
                    0 AS approved_status,
                    0 AS paid_status,
                    rc.return_id,
                    SUM(CASE WHEN rc.entry_type = 'RENT CURRENT' AND rc.payable_status IN (1, 2, 3) THEN rc.net_amount ELSE 0 END) AS payable_amount,
                    SUM(CASE WHEN rc.entry_type = 'RENT CURRENT' AND (rc.payable_status = 0 OR rc.payable_status IS NULL) THEN rc.amount_payable_landlord ELSE 0 END) AS non_payable_amount,
                    COUNT(DISTINCT CASE WHEN rc.entry_type = 'RENT CURRENT' AND rc.payable_status IN (1, 2, 3) THEN rc.unit_id END) AS payable_units,
                    COUNT(DISTINCT CASE WHEN rc.entry_type = 'RENT CURRENT' THEN rc.unit_id END) AS total_units
                FROM cb_returns_clients rc
                WHERE rc.return_period = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM cb_returns_summary rs WHERE rs.return_code = rc.return_code
                  )
                  {$propertyFilterSummary}
                GROUP BY rc.return_code, rc.property_code, rc.property_id, rc.client_id, rc.return_id
            ";

            $summaryCount = DB::affectingStatement($q3, array_merge(
                [$returnYear, $returnMonthNum, $returnPeriod, $preparedByName, $returnPeriod],
                $propBindings
            ));

            Log::info("Query 3: Inserted summaries", ['summaries' => $summaryCount]);

            DB::commit();

            Log::info("=== RETURN GENERATION COMPLETE ===", [
                'client_lines' => $rentInserted,
                'summaries' => $summaryCount,
                'returns_month' => $returnsMonth,
            ]);

            $message = $propertySelection === 'all'
                ? "Client returns generated successfully for {$summaryCount} properties ({$rentInserted} lines)"
                : "Client returns generated successfully ({$rentInserted} lines)";

            return response()->json([
                'success' => true,
                'message' => $message,
                'count' => $rentInserted,
                'properties_count' => $summaryCount
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating returns: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate returns: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display processed returns listing page
     */
    public function processedReturns()
    {
        try {
            $currentYear = (int) date('Y');
            $currentMonth = (int) date('m');
            $years = ReturnSummary::paid()
                ->select('year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year');

            return view('property.processed-returns.index', compact('currentYear', 'currentMonth', 'years'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading processed returns page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load processed returns page');
        }
    }

    /**
     * Get processed returns data for DataTable (AJAX)
     */
    public function getProcessedReturnsData(Request $request)
    {
        try {
            $year = $request->input('year', date('Y'));
            $month = $request->input('month', date('m'));

            $returns = ReturnSummary::paid()
                ->where('year', $year)
                ->where('month', $month)
                ->orderBy('property_code')
                ->orderBy('landlord_code')
                ->get();

            return response()->json([
                'success' => true,
                'count' => $returns->count(),
                'data' => $returns->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'encrypted_id' => encrypt($r->id),
                        'return_code' => $r->return_code,
                        'landlord_code' => $r->landlord_code,
                        'property_code' => $r->property_code ?? '-',
                        'return_description' => $r->return_description ?? '-',
                        'return_type' => $r->return_type ?? '-',
                        'period' => str_pad($r->month, 2, '0', STR_PAD_LEFT) . '/' . $r->year,
                        'commission_rate' => number_format($r->commission_rate ?? 0, 2),
                        'total_credit' => number_format($r->total_credit ?? 0, 2),
                        'total_debit' => number_format($r->total_debit ?? 0, 2),
                        'commission_amount' => number_format($r->commission_amount ?? 0, 2),
                        'net_amount' => number_format($r->net_amount ?? 0, 2),
                        'amount_paid' => number_format($r->amount_paid ?? 0, 2),
                        'voucher_number' => $r->voucher_number ?? '-',
                        'payment_date' => $r->payment_date ?? '-',
                        'prepared_by' => $r->prepared_by ?? '-',
                    ];
                })
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching processed returns data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch processed returns data'
            ], 500);
        }
    }

    /**
     * Show return details page
     */
    public function showReturnDetails($id)
    {
        try {
            $id = decrypt($id);

            $return = ReturnSummary::findOrFail($id);

            // Fetch all returns for same landlord, property, year, month (including current)
            $allReturns = ReturnSummary::where('landlord_code', $return->landlord_code)
                ->where('property_code', $return->property_code)
                ->where('year', $return->year)
                ->where('month', $return->month)
                ->orderByRaw("CASE WHEN return_type = 'MAINRETURN' THEN 0 ELSE 1 END")
                ->orderBy('return_type')
                ->get(['id', 'return_code', 'return_type']);

            // Compute totals from detail lines so summary matches the detail table
            $lineTotals = ReturnClient::where('return_code', $return->return_code)
                ->selectRaw("
                    SUM(CASE WHEN dr_cr = 'CR' THEN net_amount ELSE 0 END) as total_cr,
                    SUM(CASE WHEN dr_cr = 'DR' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(commission_amount, 0) + COALESCE(vat_amount, 0) ELSE 0 END) as total_dr,
                    SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN net_amount ELSE 0 END) as rent_total,
                    SUM(CASE WHEN entry_type = 'Commission' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(commission_amount, 0) ELSE 0 END) as commission,
                    SUM(CASE WHEN entry_type = 'VAT' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(vat_amount, 0) ELSE 0 END) as vat,
                    SUM(CASE WHEN entry_type = 'Deductions Before Commission' THEN net_amount ELSE 0 END) as deductions,
                    SUM(CASE WHEN entry_type = 'Add Back Deductions' THEN net_amount ELSE 0 END) as add_back
                ")
                ->first();

            return view('property.processed-returns.details', compact('return', 'lineTotals', 'allReturns'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading return details: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load return details');
        }
    }

    /**
     * Get return summary data via AJAX (for switching between related returns)
     */
    public function getReturnSummaryData($id)
    {
        try {
            $id = decrypt($id);
            $return = ReturnSummary::findOrFail($id);

            $lineTotals = ReturnClient::where('return_code', $return->return_code)
                ->selectRaw("
                    SUM(CASE WHEN dr_cr = 'CR' THEN net_amount ELSE 0 END) as total_cr,
                    SUM(CASE WHEN dr_cr = 'DR' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(commission_amount, 0) + COALESCE(vat_amount, 0) ELSE 0 END) as total_dr,
                    SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN net_amount ELSE 0 END) as rent_total,
                    SUM(CASE WHEN entry_type = 'Commission' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(commission_amount, 0) ELSE 0 END) as commission,
                    SUM(CASE WHEN entry_type = 'VAT' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(vat_amount, 0) ELSE 0 END) as vat,
                    SUM(CASE WHEN entry_type = 'Deductions Before Commission' THEN net_amount ELSE 0 END) as deductions,
                    SUM(CASE WHEN entry_type = 'Add Back Deductions' THEN net_amount ELSE 0 END) as add_back
                ")
                ->first();

            return response()->json([
                'success' => true,
                'return' => [
                    'id' => encrypt($return->id),
                    'return_code' => $return->return_code,
                    'property_code' => $return->property_code ?? '-',
                    'landlord_code' => $return->landlord_code,
                    'period' => str_pad($return->month, 2, '0', STR_PAD_LEFT) . '/' . $return->year,
                    'return_type' => $return->return_type ?? '-',
                    'commission_rate' => number_format($return->commission_rate ?? 0, 2),
                    'commission_total' => number_format($return->commission_total ?? 0, 2),
                    'amount_paid' => number_format($return->amount_paid ?? 0, 2),
                    'voucher_number' => $return->voucher_number ?? '-',
                    'cheque_number' => $return->cheque_number ?? '-',
                    'payment_date' => $return->payment_date ?? '-',
                    'remarks' => trim(($return->remarks ?? '') . ' ' . ($return->remarks_2 ?? '')),
                ],
                'lineTotals' => [
                    'rent_total' => number_format($lineTotals->rent_total ?? 0, 2),
                    'add_back' => number_format($lineTotals->add_back ?? 0, 2),
                    'add_back_raw' => (float) ($lineTotals->add_back ?? 0),
                    'total_cr' => number_format($lineTotals->total_cr ?? 0, 2),
                    'deductions' => number_format($lineTotals->deductions ?? 0, 2),
                    'deductions_raw' => (float) ($lineTotals->deductions ?? 0),
                    'commission' => number_format($lineTotals->commission ?? 0, 2),
                    'vat' => number_format($lineTotals->vat ?? 0, 2),
                    'total_dr' => number_format($lineTotals->total_dr ?? 0, 2),
                    'net_amount' => number_format(($lineTotals->total_cr ?? 0) - ($lineTotals->total_dr ?? 0), 2),
                ],
                'unitsUrl' => route('ajax.processed.returns.units', encrypt($return->id)),
                'excelUrl' => route('processed.returns.excel', encrypt($return->id)),
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching return summary: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load return data'], 500);
        }
    }

    /**
     * Get unit details for a specific return
     */
    public function getReturnUnitsData($id)
    {
        try {
            $id = decrypt($id);

            $return = ReturnSummary::findOrFail($id);

            $lines = ReturnClient::where('return_code', $return->return_code)
                ->orderBy('entry_type')
                ->orderBy('unit_code')
                ->orderBy('tenant_name')
                ->get();

            return response()->json([
                'success' => true,
                'count' => $lines->count(),
                'data' => $lines->map(function ($l) {
                    return [
                        'id' => $l->id,
                        'entry_type' => $l->entry_type ?? '-',
                        'tenant_name' => $l->tenant_name ?? '-',
                        'unit_code' => $l->unit_code ?? '-',
                        'narration' => $l->narration ?? '-',
                        'dr_cr' => $l->dr_cr ?? '-',
                        'amount_payable_landlord' => number_format($l->amount_payable_landlord ?? 0, 2),
                        'net_amount' => number_format($l->net_amount ?? 0, 2),
                        'vat_amount' => number_format($l->vat_amount ?? 0, 2),
                        'transaction_ref' => $l->transaction_ref ?? '-',
                        'entry_description' => $l->entry_description ?? '-',
                    ];
                })
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching return units data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch units data'
            ], 500);
        }
    }

    /**
     * Download PDF for a specific return
     */
    public function downloadPdf($id)
    {
        try {
            $id = decrypt($id);

            // Get return details
            $return = DB::table('sys_reports_returns_properties as rp')
                ->select([
                    'rp.*',
                    DB::raw("DATE_FORMAT(rp.period_date, '%b %Y') as period_name"),
                    'c.names as client_name',
                    'c.email_address as client_email',
                    'c.mobile_number as client_phone',
                    DB::raw("CONCAT_WS(' - ', p.reference_number, p.property_name) as property"),
                ])
                ->join('sys_pm_clients as c', 'c.id', '=', 'rp.client_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'rp.property_id')
                ->where('rp.id', $id)
                ->first();

            if (!$return) {
                return response()->json(['success' => false, 'message' => 'Return not found'], 404);
            }

            // Get unit details
            $units = DB::table('sys_reports_returns_items as ri')
                ->select([
                    'ri.unit_code as room_no',
                    DB::raw("MAX(t.names) as tenant_name"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Unit Rent' THEN ri.trans_amount ELSE 0 END) as monthly_rent"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Service Charge' THEN ri.trans_amount ELSE 0 END) as service_charge"),
                    DB::raw("SUM(ri.trans_amount) as total_charges"),
                    DB::raw("SUM(ri.comm_amount) as total_commission"),
                    DB::raw("SUM(ri.comm_vat) as total_commission_vat"),
                    DB::raw("SUM(ri.payable_amount) as net_payable"),
                ])
                ->join('sys_pm_units as u', 'u.id', '=', 'ri.unit_id')
                ->leftJoin('sys_pm_lease_agreements as l', 'l.id', '=', 'ri.lease_id')
                ->leftJoin('sys_pm_clients as t', 't.id', '=', 'l.tenant_id')
                ->where('ri.property_report_id', $id)
                ->groupBy('ri.unit_code')
                ->orderBy('ri.unit_code', 'asc')
                ->get();

            $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8');
            $pdf->SetCreator('CHIPS');
            $pdf->SetAuthor('Property Management System');
            $pdf->SetTitle('Property Return Report');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();

            // Header
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell(0, 10, 'PROPERTY RETURN REPORT', 0, 1, 'C');
            $pdf->Ln(3);

            // Return Info
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(30, 5, 'Client:', 0, 0);
            $pdf->Cell(100, 5, $return->client_name, 0, 0);
            $pdf->Cell(30, 5, 'Period:', 0, 0);
            $pdf->Cell(0, 5, $return->period_name, 0, 1);
            $pdf->Cell(30, 5, 'Property:', 0, 0);
            $pdf->Cell(0, 5, $return->property, 0, 1);
            $pdf->Ln(5);

            // Units breakdown
            if ($units->count() > 0) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, 'UNIT BREAKDOWN', 0, 1);
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->Cell(50, 6, 'Tenant', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Lease Ref', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Rent', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Service', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Water', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Elec', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Total', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Comm', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Comm VAT', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Net', 1, 1, 'C');

                $pdf->SetFont('helvetica', '', 7);
                $unitStartY = $pdf->GetY();
                foreach ($units as $unit) {
                    $pdf->Cell(50, 5, substr($unit->tenant_name ?? '-', 0, 25), 1, 0);
                    $pdf->Cell(25, 5, substr($unit->lease_reference ?? '-', 0, 12), 1, 0);
                    $pdf->Cell(25, 5, number_format($unit->monthly_rent ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->service_charge ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->water_bill ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->elec_bill ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->total_charges ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->total_commission ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->total_commission_vat ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->net_payable ?? 0, 2), 1, 1, 'R');
                }
                $unitEndY = $pdf->GetY();

                // Financial Summary - positioned at bottom right
                $summaryX = 180;
                $summaryY = $unitStartY;
                $pdf->SetXY($summaryX, $summaryY);
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(0, 6, 'FINANCIAL SUMMARY', 0, 1);
                $pdf->SetXY($summaryX, $pdf->GetY());
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(50, 5, 'Gross Returns', 1, 0);
                $pdf->Cell(35, 5, 'KSh ' . number_format($return->total_gross ?? 0, 2), 1, 1, 'R');
                $pdf->SetX($summaryX);
                $pdf->Cell(50, 5, 'Less: Commission', 1, 0);
                $pdf->Cell(35, 5, 'KSh ' . number_format($return->total_commission ?? 0, 2), 1, 1, 'R');
                $pdf->SetX($summaryX);
                $pdf->Cell(50, 5, 'Less: VAT', 1, 0);
                $pdf->Cell(35, 5, 'KSh ' . number_format($return->vat_on_commission ?? 0, 2), 1, 1, 'R');
                $pdf->SetX($summaryX);
                $pdf->Cell(50, 5, 'Less: Recoveries', 1, 0);
                $pdf->Cell(35, 5, 'KSh ' . number_format($return->total_recoveries ?? 0, 2), 1, 1, 'R');
                $pdf->SetX($summaryX);
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(50, 6, 'NET PAYABLE', 1, 0);
                $pdf->Cell(35, 6, 'KSh ' . number_format($return->total_net_amount ?? 0, 2), 1, 1, 'R');
            }

            return $pdf->Output('return_' . $id . '.pdf', 'D');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating PDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download Excel for a specific return
     */
    public function downloadExcel($id)
    {
        try {
            $id = decrypt($id);

            $return = ReturnSummary::findOrFail($id);

            $lines = ReturnClient::where('return_code', $return->return_code)
                ->orderBy('entry_type')
                ->orderBy('unit_code')
                ->orderBy('tenant_name')
                ->get();

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Return Details');

            // Header
            $sheet->setCellValue('A1', 'RETURN DETAILS — ' . $return->return_code);
            $sheet->mergeCells('A1:J1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            // Return Info
            $row = 3;
            $sheet->setCellValue('A' . $row, 'Property:')->setCellValue('B' . $row, $return->property_code ?? '-');
            $sheet->setCellValue('D' . $row, 'Landlord:')->setCellValue('E' . $row, $return->landlord_code);
            $row++;
            $sheet->setCellValue('A' . $row, 'Period:')->setCellValue('B' . $row, str_pad($return->month, 2, '0', STR_PAD_LEFT) . '/' . $return->year);
            $sheet->setCellValue('D' . $row, 'Type:')->setCellValue('E' . $row, $return->return_type ?? '-');
            $row++;
            $sheet->setCellValue('A' . $row, 'Net Amount:')->setCellValue('B' . $row, $return->net_amount ?? 0);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->setCellValue('D' . $row, 'Amount Paid:')->setCellValue('E' . $row, $return->amount_paid ?? 0);
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $row += 2;

            // Detail lines header
            $headers = ['#', 'Entry Type', 'Tenant', 'Unit', 'Description', 'Dr/Cr', 'Landlord Amt', 'Net', 'Commission', 'VAT', 'Pay %', 'Payable', 'Ref'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . $row, $header);
                $sheet->getStyle($col . $row)->getFont()->setBold(true);
                $col++;
            }
            $row++;

            $i = 1;
            foreach ($lines as $l) {
                $sheet->setCellValue('A' . $row, $i++)
                    ->setCellValue('B' . $row, $l->entry_type ?? '-')
                    ->setCellValue('C' . $row, $l->tenant_name ?? '-')
                    ->setCellValue('D' . $row, $l->unit_code ?? '-')
                    ->setCellValue('E' . $row, $l->entry_description ?? '-')
                    ->setCellValue('F' . $row, $l->dr_cr ?? '-')
                    ->setCellValue('G' . $row, $l->amount_payable_landlord ?? 0)
                    ->setCellValue('H' . $row, $l->net_amount ?? 0)
                    ->setCellValue('I' . $row, $l->commission_amount ?? 0)
                    ->setCellValue('J' . $row, $l->vat_amount ?? 0)
                    ->setCellValue('K' . $row, $l->pay_percentage ? $l->pay_percentage . '%' : '')
                    ->setCellValue('L' . $row, $l->payable_status ? 'Yes' : 'No')
                    ->setCellValue('M' . $row, $l->transaction_ref ?? '-');

                foreach (['G', 'H', 'I', 'J'] as $c) {
                    $sheet->getStyle($c . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                }
                $row++;
            }

            foreach (range('A', 'M') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filename = 'return_' . $return->return_code . '_' . date('Y-m-d') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating Excel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Excel'
            ], 500);
        }
    }
}
