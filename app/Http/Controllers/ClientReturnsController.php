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
                    SUM(CASE WHEN dr_cr = 'DR' THEN net_amount ELSE 0 END) as total_dr,
                    SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN net_amount ELSE 0 END) as rent_total,
                    SUM(CASE WHEN entry_type = 'Commission' THEN net_amount ELSE 0 END) as commission,
                    SUM(CASE WHEN entry_type = 'VAT' THEN net_amount ELSE 0 END) as vat,
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
                    SUM(CASE WHEN dr_cr = 'DR' THEN net_amount ELSE 0 END) as total_dr,
                    SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN net_amount ELSE 0 END) as rent_total,
                    SUM(CASE WHEN entry_type = 'Commission' THEN net_amount ELSE 0 END) as commission,
                    SUM(CASE WHEN entry_type = 'VAT' THEN net_amount ELSE 0 END) as vat,
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
                        'transaction_ref' => $d->transaction_ref ?? '-',
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
            $headers = ['#', 'Entry Type', 'Tenant', 'Unit', 'Description', 'Dr/Cr', 'Landlord Amt', 'Net', 'VAT', 'Ref'];
            foreach ($headers as $col => $header) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $header);
            }
            $sheet->getStyle('A8:J8')->getFont()->setBold(true);

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
                $sheet->setCellValueByColumnAndRow(9, $row, $line->vat_amount);
                $sheet->setCellValueByColumnAndRow(10, $row, $line->transaction_ref);
                $row++;
            }

            foreach (range('A', 'J') as $col) {
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
                    DB::raw('DATE_FORMAT(spdi.date_due, "%d %b %Y") as date_due'),
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
     */
    public function processGenerate(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'property_selection' => 'required|in:all,one',
                'property_id' => 'required_if:property_selection,one|nullable|integer|exists:sys_pm_properties,id',
                'returns_month' => 'required|date_format:Y-m'
            ]);

            $propertySelection = $request->property_selection;
            $propertyId = $request->property_id;
            $returnsMonth = $request->returns_month; // Format: YYYY-MM
            $batchDate = str_replace('-', '', $returnsMonth); // Format: YYYYMM
            $generationDate = now();
            $generatedBy = Auth::id();

            // Calculate first day of return month for return_date and trans_date_time
            $returnDate = $returnsMonth . '-01'; // Format: YYYY-MM-01

            // Pre-generate batch_id using Unix timestamp for uniqueness
            $batchId = $batchDate . '-' . time(); // Format: YYYYMM-unixTimestamp

            // Start transaction
            DB::beginTransaction();

            // ═══════════════════════════════════════════════════════════════════
            // LOG: Check what invoices exist for the period
            // ═══════════════════════════════════════════════════════════════════
            $invoiceCheckQuery = DB::table('sys_pm_debtors_invoices as i')
                ->where(DB::raw("DATE_FORMAT(i.date_due, '%Y-%m')"), $returnsMonth)
                ->where('i.current_status_id', 1)
                ->where('i.debtor_type_id', 2);

            if ($propertySelection === 'one') {
                $invoiceCheckQuery->where('i.property_id', $propertyId);

                // Debug: Check if property exists and has any invoices at all
                $propertyExists = DB::table('sys_pm_properties')->where('id', $propertyId)->exists();
                $totalInvoicesForProperty = DB::table('sys_pm_debtors_invoices')->where('property_id', $propertyId)->count();

                // Show available months with invoices for this property
                $availableMonths = DB::table('sys_pm_debtors_invoices')
                    ->where('property_id', $propertyId)
                    ->where('current_status_id', 1)
                    ->where('debtor_type_id', 2)
                    ->whereNotNull('date_due')
                    ->selectRaw("DATE_FORMAT(date_due, '%Y-%m') as invoice_month, COUNT(*) as count")
                    ->groupBy('invoice_month')
                    ->orderBy('invoice_month', 'desc')
                    ->limit(12)
                    ->get();

                Log::info("Property Debug", [
                    'property_id' => $propertyId,
                    'property_id_type' => gettype($propertyId),
                    'property_exists' => $propertyExists,
                    'total_invoices_all_time' => $totalInvoicesForProperty,
                    'available_months_with_invoices' => $availableMonths->map(function ($m) {
                        return ['month' => $m->invoice_month, 'invoices' => $m->count];
                    })->toArray(),
                    'sql' => $invoiceCheckQuery->toSql(),
                    'bindings' => $invoiceCheckQuery->getBindings()
                ]);
            }

            $totalInvoices = $invoiceCheckQuery->count();
            Log::info("=== RETURN GENERATION DEBUG ===", [
                'returns_month' => $returnsMonth,
                'property_selection' => $propertySelection,
                'property_id' => $propertyId,
                'total_invoices_for_period' => $totalInvoices
            ]);

            // Check how many already have returns
            $invoicesWithReturnsQuery = DB::table('sys_pm_debtors_invoices as i')
                ->where(DB::raw("DATE_FORMAT(i.date_due, '%Y-%m')"), $returnsMonth)
                ->where('i.current_status_id', 1)
                ->where('i.debtor_type_id', 2)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('sys_pm_clients_returns as cr')
                        ->whereColumn('cr.invoice_id', 'i.id');
                });

            if ($propertySelection === 'one') {
                $invoicesWithReturnsQuery->where('i.property_id', $propertyId);
            }

            $invoicesWithReturns = $invoicesWithReturnsQuery->count();

            Log::info("Invoice Analysis", [
                'invoices_with_returns' => $invoicesWithReturns,
                'invoices_without_returns' => $totalInvoices - $invoicesWithReturns
            ]);

            // Check validation failures
            $validationChecksQuery = DB::table('sys_pm_debtors_invoices as i')
                ->where(DB::raw("DATE_FORMAT(i.date_due, '%Y-%m')"), $returnsMonth)
                ->where('i.current_status_id', 1)
                ->where('i.debtor_type_id', 2);

            if ($propertySelection === 'one') {
                $validationChecksQuery->where('i.property_id', $propertyId);
            }

            $validationChecks = $validationChecksQuery
                ->select([
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN i.property_id IS NULL THEN 1 ELSE 0 END) as null_property'),
                    DB::raw('SUM(CASE WHEN i.unit_id IS NULL THEN 1 ELSE 0 END) as null_unit'),
                    DB::raw('SUM(CASE WHEN i.lease_id IS NULL THEN 1 ELSE 0 END) as null_lease'),
                    DB::raw('SUM(CASE WHEN i.tenant_id IS NULL THEN 1 ELSE 0 END) as null_tenant'),
                    DB::raw('SUM(CASE WHEN EXISTS (SELECT 1 FROM sys_pm_clients_returns cr WHERE cr.invoice_id = i.id) THEN 1 ELSE 0 END) as has_return')
                ])
                ->first();

            Log::info("Validation Check Results", [
                'total_invoices' => $validationChecks->total,
                'missing_property' => $validationChecks->null_property,
                'missing_unit' => $validationChecks->null_unit,
                'missing_lease' => $validationChecks->null_lease,
                'missing_tenant' => $validationChecks->null_tenant,
                'already_have_return' => $validationChecks->has_return
            ]);

            // ═══════════════════════════════════════════════════════════════════
            // DELETE EXISTING RETURNS AND LANDLORD LEDGERS FOR THIS MONTH
            // ═══════════════════════════════════════════════════════════════════
            // This prevents duplicates when regenerating returns for the same month
            // Only deletes returns that are NOT approved (approval_status IS NULL)
            // Does NOT delete if approval_status = 1 (approved) or 101 (approval in progress)

            // Check if approval_status column exists
            $approvalColumnExists = DB::getSchemaBuilder()->hasColumn('sys_pm_clients_returns', 'approval_status');

            Log::info("Checking for existing returns to delete", [
                'return_date' => $returnDate,
                'approval_column_exists' => $approvalColumnExists,
                'property_selection' => $propertySelection,
                'property_id' => $propertyId
            ]);

            // Delete landlord ledger entries first using subquery with JOIN
            $ledgerDeleteSql = "DELETE lal FROM sys_pm_landlord_accounts_transaction lal
                INNER JOIN sys_pm_clients_returns cr ON cr.invoice_id = lal.invoice_id
                WHERE lal.transaction_type_id = 13
                  AND cr.return_date = ?";

            if ($approvalColumnExists) {
                $ledgerDeleteSql .= " AND (cr.approval_status NOT IN (1, 101) OR cr.approval_status IS NULL)";
            }

            if ($propertySelection === 'one') {
                $ledgerDeleteSql .= " AND cr.property_id = ?";
            }

            $ledgerParams = [$returnDate];
            if ($propertySelection === 'one') {
                $ledgerParams[] = $propertyId;
            }

            $deletedLedgers = DB::delete($ledgerDeleteSql, $ledgerParams);

            // Delete returns directly with conditions
            $returnsDeleteSql = "DELETE FROM sys_pm_clients_returns
                WHERE return_date = ?";

            if ($approvalColumnExists) {
                $returnsDeleteSql .= " AND (approval_status NOT IN (1, 101) OR approval_status IS NULL)";
            }

            if ($propertySelection === 'one') {
                $returnsDeleteSql .= " AND property_id = ?";
            }

            $returnsParams = [$returnDate];
            if ($propertySelection === 'one') {
                $returnsParams[] = $propertyId;
            }

            $deletedReturns = DB::delete($returnsDeleteSql, $returnsParams);

            // Delete batch records for this month (batch_number is the return month like '2025-11')
            $batchDeleteSql = "DELETE FROM sys_pm_clients_returns_batches WHERE batch_number = ?";
            $batchParams = [$returnsMonth];

            if ($propertySelection === 'one') {
                $batchDeleteSql .= " AND property_id = ?";
                $batchParams[] = $propertyId;
            }

            $deletedBatches = DB::delete($batchDeleteSql, $batchParams);

            Log::info("✓ Cleared existing returns for regeneration", [
                'deleted_returns' => $deletedReturns,
                'deleted_ledgers' => $deletedLedgers,
                'deleted_batches' => $deletedBatches,
                'return_date' => $returnDate,
                'approval_filter_used' => $approvalColumnExists
            ]);

            // Build the INSERT query
            $query = "INSERT INTO sys_pm_clients_returns (
                date_time,
                ref_number,
                invoice_id,
                return_date,
                narration,
                created_by,
                zone_id,
                client_id,
                property_id,
                unit_id,
                lease_id,
                tenant_id,
                sla_id,
                trans_type_id,
                fo_id,
                zone_supervisor_id,
                zone_manager_id,
                unit_status_id,
                threshold_percentage,
                current_status_id,
                payable_status_id,
                trans_amount,
                commissionable_amount,
                comm_amount,
                comm_vat,
                payable_amount,
                comm_perc,
                vat_amount,
                vat_per,
                commisionable,
                batch_id
            )
            SELECT 
                ? AS date_time,
                i.reference_number AS ref_number,
                i.id AS invoice_id,
                ? AS return_date,
                i.narration AS narration,
                ? AS created_by,
                i.zone_id,
                i.client_id,
                i.property_id,
                i.unit_id,
                i.lease_id,
                i.tenant_id,
                i.sla_id,
                i.item_type_id AS trans_type_id,
                i.field_officer_id AS fo_id,
                i.zone_supervisor_id,
                i.zone_manager_id,
                i.occupancy_status_id AS unit_status_id,
                0 AS threshold_percentage,
                1 AS current_status_id,
                0 AS payable_status_id,
                i.total_amount AS trans_amount,
                i.commisionable_amount AS commissionable_amount,
                i.comm_ammount AS comm_amount,
                i.vat_on_commission AS comm_vat,
                i.payable_amount AS payable_amount,
                i.comm_per AS comm_perc,
                i.vat_ammount AS vat_amount,
                i.vat_per AS vat_per,
                CASE 
                    WHEN i.comm_ammount > 0 THEN 'Yes'
                    ELSE 'No'
                END AS commisionable,
                ? AS batch_id
            FROM sys_pm_debtors_invoices i
            WHERE DATE_FORMAT(i.date_due, '%Y-%m') = ?
              AND i.current_status_id = 1
              AND i.debtor_type_id = 2";

            // Add property filter if specific property is selected
            if ($propertySelection === 'one') {
                $query .= " AND i.property_id = ?";
            }

            $query .= "
              AND i.property_id IS NOT NULL
              AND i.unit_id IS NOT NULL
              AND i.lease_id IS NOT NULL
              AND i.tenant_id IS NOT NULL";

            Log::info("Executing return generation INSERT query", [
                'returns_month' => $returnsMonth,
                'batch_id' => $batchId,
                'property_selection' => $propertySelection
            ]);

            // Execute the insert query with appropriate parameters
            $params = [
                $generationDate,    // date_time
                $returnDate,        // return_date (1st of month)
                $generatedBy,       // created_by
                $batchId,           // batch_id (pre-generated)
                $returnsMonth       // DATE_FORMAT comparison (YYYY-MM format)
            ];

            if ($propertySelection === 'one') {
                $params[] = $propertyId; // property_id filter
            }

            DB::insert($query, $params);

            // Get count of generated returns using exact batch_id
            $affectedRows = DB::table('sys_pm_clients_returns')
                ->where('batch_id', $batchId)
                ->count();

            // ═══════════════════════════════════════════════════════════════════
            // CREATE LANDLORD LEDGER ENTRIES FOR GENERATED RETURNS
            // ═══════════════════════════════════════════════════════════════════
            // Per INVOICE_PAYMENT_TO_LANDLORD_LEDGER_FLOW.md:
            // - Initial state: payable_status = 0 (EXCLUDED from balance)
            // - Will be updated to 1 when invoice payment threshold is met
            // ═══════════════════════════════════════════════════════════════════

            if ($affectedRows > 0) {
                // Use INSERT...SELECT for better performance with large datasets
                $landlordLedgerSql = "INSERT INTO sys_pm_landlord_accounts_transaction (
                    date_time,
                    trans_date_time,
                    client_id,
                    property_id,
                    invoice_id,
                    origin_id,
                    transaction_type_id,
                    narration,
                    amount_type,
                    amount,
                    payable_status,
                    current_status_id,
                    lease_id,
                    tenant_id,
                    unit_id,
                    sla_id,
                    zone_id
                )
                SELECT 
                    ? AS date_time,
                    ? AS trans_date_time,
                    cr.client_id,
                    cr.property_id,
                    cr.invoice_id,
                    cr.id AS origin_id,
                    13 AS transaction_type_id,
                    CONCAT('Client Return - ', cr.ref_number) AS narration,
                    'Credit' AS amount_type,
                    cr.payable_amount AS amount,
                    0 AS payable_status,
                    1 AS current_status_id,
                    cr.lease_id,
                    cr.tenant_id,
                    cr.unit_id,
                    cr.sla_id,
                    cr.zone_id
                FROM sys_pm_clients_returns cr
                WHERE cr.batch_id = ?";

                DB::insert($landlordLedgerSql, [
                    $generationDate,
                    $returnDate,
                    $batchId
                ]);

                Log::info("✓ Created landlord ledger entries via INSERT...SELECT", [
                    'returns_count' => $affectedRows,
                    'batch_id' => $batchId
                ]);
            }

            // Check if any returns were generated
            if ($affectedRows == 0) {
                DB::rollBack();

                // Determine why no returns were generated
                if ($totalInvoices == 0) {
                    $message = 'No approved invoices found for the selected period and property.';

                    // For single property, show available months
                    if ($propertySelection === 'one' && isset($availableMonths) && $availableMonths->isNotEmpty()) {
                        $monthsList = $availableMonths->map(function ($m) {
                            return $m->invoice_month . ' (' . $m->count . ' invoices)';
                        })->join(', ');
                        $message .= ' Available periods: ' . $monthsList;
                    }
                } elseif ($invoicesWithReturns == $totalInvoices) {
                    $message = 'All approved invoices for the selected period already have returns.';
                } else {
                    $message = 'No returns generated. Invoices may be missing required fields (property, unit, lease, or tenant).';
                }

                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 422);
            }

            // Delete existing batch records for this period (to avoid duplicates on regeneration)
            $deleteBatchQuery = DB::table('sys_pm_clients_returns_batches')
                ->where('batch_number', $returnsMonth);

            if ($propertySelection === 'one') {
                $deleteBatchQuery->where('property_id', $propertyId);
            }

            // Get batch IDs before deletion (for approval cleanup)
            $batchIdsToDelete = (clone $deleteBatchQuery)->pluck('id')->toArray();

            // Delete associated approval records first
            if (!empty($batchIdsToDelete)) {
                // Delete approval actions
                DB::table('cb_approval_actions')
                    ->whereIn('approval_item_id', function ($query) use ($batchIdsToDelete) {
                        $query->select('id')
                            ->from('cb_approval_items')
                            ->where('item_type', 'returns')
                            ->whereIn('item_id', $batchIdsToDelete);
                    })
                    ->delete();

                // Delete item approvers
                DB::table('cb_item_approvers')
                    ->whereIn('approval_item_id', function ($query) use ($batchIdsToDelete) {
                        $query->select('id')
                            ->from('cb_approval_items')
                            ->where('item_type', 'returns')
                            ->whereIn('item_id', $batchIdsToDelete);
                    })
                    ->delete();

                // Delete approval items
                $deletedApprovals = DB::table('cb_approval_items')
                    ->where('item_type', 'returns')
                    ->whereIn('item_id', $batchIdsToDelete)
                    ->delete();

                Log::info("✓ Deleted old approval records for regeneration", [
                    'deleted_approvals' => $deletedApprovals,
                    'batch_ids' => $batchIdsToDelete
                ]);
            }

            $deletedBatches = $deleteBatchQuery->delete();

            Log::info("Deleted existing batch records", [
                'deleted_count' => $deletedBatches,
                'batch_number' => $returnsMonth
            ]);

            // Get summary data grouped by property for batch creation
            $batchSummaries = DB::table('sys_pm_clients_returns')
                ->where('batch_id', $batchId)
                ->selectRaw('
                    property_id,
                    batch_id,
                    COUNT(*) as returns_count,
                    SUM(trans_amount) as total_trans_amount,
                    SUM(comm_amount) as total_comm_amount,
                    SUM(payable_amount) as total_payable_amount
                ')
                ->groupBy('property_id', 'batch_id')
                ->get();

            // Create batch records for each property and initialize approval workflow
            foreach ($batchSummaries as $summary) {
                $batch = ClientReturnsBatch::create([
                    'batch_number' => $returnsMonth,
                    'batch_id' => $summary->batch_id,
                    'property_id' => $summary->property_id,
                    'returns_count' => $summary->returns_count ?? 0,
                    'total_trans_amount' => $summary->total_trans_amount ?? 0,
                    'total_comm_amount' => $summary->total_comm_amount ?? 0,
                    'total_payable_amount' => $summary->total_payable_amount ?? 0,
                    'approval_status' => 0, // Pending
                    'generated_by' => $generatedBy,
                    'generated_at' => $generationDate
                ]);

                // ═══════════════════════════════════════════════════════════════════
                // INITIALIZE APPROVAL WORKFLOW
                // ═══════════════════════════════════════════════════════════════════
                // Get field_officer_id from property
                $property = DB::table('sys_pm_properties')
                    ->where('id', $summary->property_id)
                    ->first(['field_officer_id']);

                if ($property && $property->field_officer_id) {
                    // Create approval item record
                    $approvalItem = DB::table('cb_approval_items')->insertGetId([
                        'item_type' => 'returns',
                        'item_id' => $batch->id,
                        'item_reference' => $summary->batch_id,
                        'status' => 'Pending',
                        'current_level' => 1,
                        'total_levels' => 4,
                        'initiated_by' => $generatedBy,
                        'initiated_at' => $generationDate,
                        'created_at' => $generationDate,
                        'updated_at' => $generationDate
                    ]);

                    // Insert Field Officer as first level approver
                    DB::table('cb_item_approvers')->insert([
                        'approval_item_id' => $approvalItem,
                        'approver_id' => $property->field_officer_id,
                        'approver_level' => 1,
                        'is_optional' => 0,
                        'approval_status' => 'Pending',
                        'assigned_at' => $generationDate,
                        'created_at' => $generationDate,
                        'updated_at' => $generationDate
                    ]);

                    // Log initial approval action
                    DB::table('cb_approval_actions')->insert([
                        'item_approver_id' => DB::getPdo()->lastInsertId(),
                        'approval_item_id' => $approvalItem,
                        'actor_id' => $generatedBy,
                        'action' => 'Initiated',
                        'comments' => 'Returns generated and assigned to Field Officer for approval',
                        'action_timestamp' => $generationDate,
                        'ip_address' => request()->ip(),
                        'sequence_number' => 1,
                        'is_current' => 1,
                        'created_at' => $generationDate,
                        'updated_at' => $generationDate
                    ]);
                } else {
                    Log::warning("Field Officer not found for property", [
                        'property_id' => $summary->property_id,
                        'batch_id' => $batch->id
                    ]);
                }
            }

            DB::commit();

            // ═══════════════════════════════════════════════════════════════════
            // DISPATCH BACKGROUND JOB TO UPDATE ABSTRACT THRESHOLDS
            // ═══════════════════════════════════════════════════════════════════
            // Run as queued job to avoid blocking response and prevent timeouts
            // This complex query can take minutes with 6000+ records
            // ═══════════════════════════════════════════════════════════════════

            UpdateAbstractThresholdsJob::dispatch($batchId, $returnsMonth);

            Log::info("✓ Dispatched abstract threshold update job", [
                'batch_id' => $batchId,
                'returns_month' => $returnsMonth
            ]);

            $message = $propertySelection === 'all'
                ? "Client returns generated successfully for {$batchSummaries->count()} properties"
                : 'Client returns generated successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
                'count' => $affectedRows,
                'properties_count' => $batchSummaries->count()
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
                    SUM(CASE WHEN dr_cr = 'DR' THEN net_amount ELSE 0 END) as total_dr,
                    SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN net_amount ELSE 0 END) as rent_total,
                    SUM(CASE WHEN entry_type = 'Commission' THEN net_amount ELSE 0 END) as commission,
                    SUM(CASE WHEN entry_type = 'VAT' THEN net_amount ELSE 0 END) as vat,
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
                    SUM(CASE WHEN dr_cr = 'DR' THEN net_amount ELSE 0 END) as total_dr,
                    SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN net_amount ELSE 0 END) as rent_total,
                    SUM(CASE WHEN entry_type = 'Commission' THEN net_amount ELSE 0 END) as commission,
                    SUM(CASE WHEN entry_type = 'VAT' THEN net_amount ELSE 0 END) as vat,
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
            $headers = ['#', 'Entry Type', 'Tenant', 'Unit', 'Description', 'Dr/Cr', 'Landlord Amt', 'Net', 'VAT', 'Ref'];
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
                    ->setCellValue('I' . $row, $l->vat_amount ?? 0)
                    ->setCellValue('J' . $row, $l->transaction_ref ?? '-');

                foreach (['G', 'H', 'I'] as $c) {
                    $sheet->getStyle($c . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                }
                $row++;
            }

            foreach (range('A', 'J') as $col) {
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
