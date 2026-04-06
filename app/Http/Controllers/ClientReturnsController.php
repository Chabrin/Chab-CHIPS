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
use Barryvdh\DomPDF\Facade\Pdf;

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

            // Check if user has a global role (sees all returns, no property filter)
            $hasGlobalRole = DB::table('cb_role_user as ru')
                ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                ->where('ru.user_id', $userId)
                ->whereIn('r.name', ['top_management', 'clerical_officers', 'accountants'])
                ->exists();

            $query = ReturnSummary::unpaid()
                ->where('year', $year)
                ->where('month', $month);

            // Role-based filtering: property-level staff only see their properties
            if (!$hasGlobalRole) {
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

            // Get property names and client names in one lookup
            $returns = $query->orderBy('property_code')
                ->orderBy('landlord_code')
                ->get();

            $propertyCodes = $returns->pluck('property_code')->unique()->filter();
            $propertyNames = DB::table('sys_pm_properties')
                ->whereIn('reference_number', $propertyCodes)
                ->pluck('property_name', 'reference_number');

            $landlordCodes = $returns->pluck('landlord_code')->unique()->filter();
            $clientNames = DB::table('sys_pm_clients')
                ->whereIn('reference_number', $landlordCodes)
                ->pluck('names', 'reference_number');

            return response()->json([
                'success' => true,
                'count' => $returns->count(),
                'data' => $returns->map(function ($r) use ($propertyNames, $clientNames) {
                    $name = $clientNames[$r->landlord_code] ?? '-';
                    $num = preg_replace('/^LAN-?/i', '', $r->landlord_code ?? '');
                    return [
                        'id' => $r->id,
                        'encrypted_id' => encrypt($r->id),
                        'return_code' => $r->return_code,
                        'landlord_code' => $r->landlord_code,
                        'client_name' => $name . ' (' . $num . ')',
                        'property_code' => $r->property_code ?? '-',
                        'property_name' => $propertyNames[$r->property_code] ?? '-',
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
                    'paid_status' => (int) $return->paid_status,
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
                        'encrypted_id' => encrypt($d->id),
                        'entry_type' => $d->entry_type ?? '-',
                        'narration' => $d->narration ?? '-',
                        'tenant_name' => $d->tenant_name ?? '-',
                        'unit_code' => $d->unit_code ?? '-',
                        'entry_description' => $d->entry_description ?? '-',
                        'dr_cr' => $d->dr_cr ?? '-',
                        'amount_payable_landlord' => number_format($d->amount_payable_landlord ?? 0, 2),
                        'net_amount' => number_format($d->net_amount ?? 0, 2),
                        'vat_amount' => number_format($d->vat_amount ?? 0, 2),
                        'pay_percentage' => $d->pay_percentage ?? '',
                        'return_period' => $d->return_period ?? '-',
                        'payable_status' => (int) $d->payable_status,
                        'payable_status_reason' => $d->payable_status_reason ?? '',
                    ];
                })
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching unprocessed return units: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load detail lines'], 500);
        }
    }

    /**
     * Set recovery status on a return line (AJAX)
     * Only allowed when the parent summary is unpaid (paid_status=0) and not approved (approved_status=0)
     */
    public function setRecoveryStatus(Request $request, $lineId)
    {
        try {
            $lineId = decrypt($lineId);
            $line = ReturnClient::findOrFail($lineId);

            $summary = ReturnSummary::where('return_code', $line->return_code)->firstOrFail();

            if ($summary->paid_status != 0 || $summary->approved_status != 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recovery cannot be set — return is already approved or paid.',
                ], 403);
            }

            $request->validate([
                'payable_status' => 'required|integer|in:34,35,36,37',
            ]);

            $reasons = [
                34 => 'Paid Vacant',
                35 => 'Sat Deposit',
                36 => 'Absconded',
                37 => 'Paid Owner',
            ];

            $line->payable_status = (int) $request->payable_status;
            $line->payable_status_reason = $reasons[$line->payable_status];
            $line->save();

            $recomputed = $this->recomputeReturnSummary($summary);

            return response()->json([
                'success' => true,
                'message' => 'Recovery status saved.',
                'summary' => $recomputed,
            ]);
        } catch (Exception $e) {
            Log::error('Error setting recovery status: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to save recovery status.'], 500);
        }
    }

    /**
     * Recompute payable_amount, non_payable_amount, payable_units, total_units
     * on cb_returns_summary for the given return.
     */
    private function recomputeReturnSummary(ReturnSummary $summary): array
    {
        $stats = ReturnClient::where('return_code', $summary->return_code)
            ->selectRaw("
                SUM(CASE WHEN entry_type = 'RENT CURRENT' AND payable_status IN (1,2,3) THEN net_amount ELSE 0 END) AS payable_amount,
                SUM(CASE WHEN entry_type = 'RENT CURRENT' AND (payable_status NOT IN (1,2,3) OR payable_status IS NULL) THEN amount_payable_landlord ELSE 0 END) AS non_payable_amount,
                COUNT(DISTINCT CASE WHEN entry_type = 'RENT CURRENT' AND payable_status IN (1,2,3) THEN unit_id END) AS payable_units,
                COUNT(DISTINCT CASE WHEN entry_type = 'RENT CURRENT' THEN unit_id END) AS total_units
            ")
            ->first();

        $summary->payable_amount     = $stats->payable_amount     ?? 0;
        $summary->non_payable_amount = $stats->non_payable_amount ?? 0;
        $summary->payable_units      = $stats->payable_units      ?? 0;
        $summary->total_units        = $stats->total_units        ?? 0;
        $summary->save();

        return [
            'payable_amount'     => number_format((float) ($stats->payable_amount ?? 0), 2),
            'non_payable_amount' => number_format((float) ($stats->non_payable_amount ?? 0), 2),
            'payable_units'      => (int) ($stats->payable_units ?? 0),
            'total_units'        => (int) ($stats->total_units ?? 0),
        ];
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
                'returns_month' => 'required|date_format:Y-m'
            ]);
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

            DB::beginTransaction();

            // ═══════════════════════════════════════════════════════════════════
            // COUNT INVOICES
            // ═══════════════════════════════════════════════════════════════════
            $totalInvoices = DB::selectOne("
                SELECT COUNT(*) as cnt FROM sys_pm_debtors_invoices i
                WHERE i.date_due = ?::date
                  AND i.current_status_id = 1
                  AND i.debtor_type_id = '2'
                  AND i.property_id IS NOT NULL
                  AND i.unit_id IS NOT NULL
                  AND i.lease_id IS NOT NULL
                  AND i.tenant_id IS NOT NULL
            ", [$monthStart])->cnt;

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
            ]);

            // ═══════════════════════════════════════════════════════════════════
            // BLOCK IF RETURNS ALREADY EXIST FOR THIS PERIOD
            // ═══════════════════════════════════════════════════════════════════
            $existingCount = DB::selectOne("
                SELECT COUNT(*) as cnt FROM cb_returns_summary
                WHERE year = ? AND month = ?
            ", [$returnYear, $returnMonthNum])->cnt;

            if ($existingCount > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Returns for {$monthName} {$returnYear} have already been generated and cannot be regenerated."
                ], 422);
            }

            // Get next return_id base
            $baseReturnId = DB::selectOne("SELECT COALESCE(MAX(return_id), 35217) + 1 as next_id FROM cb_returns_summary")->next_id;

            // Common WHERE clause for invoice selection
            $invoiceWhere = "
                i.date_due = ?::date
                AND i.current_status_id = 1
                AND i.debtor_type_id = '2'
                AND i.property_id IS NOT NULL
                AND i.unit_id IS NOT NULL
                AND i.lease_id IS NOT NULL
                AND i.tenant_id IS NOT NULL
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

            $baseBindings = [$monthStart, $returnPeriod];

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
                    is_commissionable, server_time, user_id
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
                    'Yes',
                    NOW(),
                    ?
                {$invoiceJoins}
                WHERE {$invoiceWhere}
                {$notApproved}
            ", array_merge(
                [$returnPeriod, $returnPeriod, $transactionDate, $baseReturnId, $generatedBy],
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
                            ) THEN 1
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
                ) sub
                WHERE rc.id = sub.rc_id
            ", [
                $monthStart,
                $monthStart,
                $monthStart,
                $prevMonthStart,
                $monthStart,
                $returnPeriod
            ]);

            Log::info("Query 2: Updated pay percentages, net_amount, commission and vat");

            // ═══════════════════════════════════════════════════════════════════
            // QUERY 2B: INSERT Advance Payments from Landlord Account as DR lines
            // Picks up all debit entries scheduled for this return period.
            // year+month on cb_landlord_account = WHEN to recover (schedule target).
            // recovery_year+recovery_month = WHEN it was actually recovered (written by Q3B).
            // The LATERAL join implicitly limits to properties in this batch.
            // ═══════════════════════════════════════════════════════════════════
            $advancesInserted = DB::affectingStatement("
                INSERT INTO cb_returns_clients (
                    return_code, return_period, transaction_number, transaction_date,
                    entry_description, entry_type, dr_cr,
                    property_code, net_amount, narration,
                    property_id, client_id, zone_id, field_officer_id,
                    zone_supervisor_id, zone_manager_id,
                    return_id, deduction_point_2, is_commissionable,
                    server_time, user_id
                )
                SELECT
                    rc_base.return_code,
                    ?::integer,
                    '',
                    ?,
                    la.entry_description,
                    'Advance Payments-From Landlord Acc',
                    'DR',
                    rc_base.property_code,
                    la.debit_amount,
                    COALESCE(la.entry_particulars, la.entry_description),
                    la.property_id,
                    la.client_id,
                    rc_base.zone_id,
                    rc_base.field_officer_id,
                    rc_base.zone_supervisor_id,
                    rc_base.zone_manager_id,
                    rc_base.return_id,
                    NULL,
                    'No',
                    NOW(),
                    ?
                FROM cb_landlord_account la
                JOIN LATERAL (
                    SELECT return_code, return_id, property_code,
                           zone_id, field_officer_id, zone_supervisor_id, zone_manager_id
                    FROM cb_returns_clients
                    WHERE return_period = ? AND property_id = la.property_id
                    LIMIT 1
                ) rc_base ON true
                WHERE la.debit_amount > 0
                  AND la.is_recovered = 0
                  AND la.year = ?
                  AND la.month = ?
                  AND la.entry_type_code != '0005'
            ", [$returnPeriod, $transactionDate, $generatedBy, $returnPeriod, $returnYear, $returnMonthNum]);

            Log::info("Query 2B: Advance payment DR lines inserted", ['count' => $advancesInserted]);

            // ═══════════════════════════════════════════════════════════════════
            // QUERY 2C: INSERT Recoveries from cb_money_recoveries as DR lines
            // Picks up DR-class entries for this year/month not yet linked to a return.
            // The LATERAL join implicitly limits to properties in this batch.
            // ═══════════════════════════════════════════════════════════════════
            $recoveriesInserted = DB::affectingStatement("
                INSERT INTO cb_returns_clients (
                    return_code, return_period, transaction_number, transaction_date,
                    entry_description, entry_type, dr_cr,
                    property_code, unit_code, tenant_name,
                    net_amount, narration,
                    property_id, client_id, zone_id, field_officer_id,
                    zone_supervisor_id, zone_manager_id, unit_id, tenant_id,
                    return_id, deduction_point_2, is_commissionable,
                    server_time, user_id
                )
                SELECT
                    rc_base.return_code,
                    ?::integer,
                    COALESCE(mr.transaction_number, ''),
                    ?,
                    mr.transaction_description,
                    'Recoveries',
                    'DR',
                    rc_base.property_code,
                    mr.unit_code,
                    mr.tenant_name,
                    mr.debit_amount,
                    mr.transaction_description,
                    mr.property_id,
                    mr.client_id,
                    mr.zone_id,
                    mr.field_officer_id,
                    mr.zone_supervisor_id,
                    mr.zone_manager_id,
                    mr.unit_id,
                    mr.tenant_id,
                    rc_base.return_id,
                    'After Comm',
                    'No',
                    NOW(),
                    ?
                FROM cb_money_recoveries mr
                JOIN LATERAL (
                    SELECT return_code, return_id, property_code
                    FROM cb_returns_clients
                    WHERE return_period = ? AND property_id = mr.property_id
                    LIMIT 1
                ) rc_base ON true
                WHERE mr.transaction_class = 'DR'
                  AND mr.year = ?
                  AND mr.month = ?
                  AND mr.return_id IS NULL
            ", [$returnPeriod, $transactionDate, $generatedBy, $returnPeriod, $returnYear, $returnMonthNum]);

            Log::info("Query 2C: Recovery DR lines inserted", ['count' => $recoveriesInserted]);

            // ═══════════════════════════════════════════════════════════════════
            // QUERY 2D: INSERT CR collections from cb_money_recoveries as CR lines
            // Water Bills, Service Charges, Electricity Bills, etc. collected
            // from tenants that form part of the landlord's return credit.
            // Mirrors how CHRACT CR rows appeared in CHRTXS as OTHER PAYMENTS/DEPOSITS.
            // ═══════════════════════════════════════════════════════════════════
            $collectionsInserted = DB::affectingStatement("
                INSERT INTO cb_returns_clients (
                    return_code, return_period, transaction_number, transaction_date,
                    entry_description, entry_type, dr_cr,
                    property_code, unit_code, tenant_name,
                    net_amount, amount_payable_landlord, narration,
                    property_id, client_id, zone_id, field_officer_id,
                    zone_supervisor_id, zone_manager_id, unit_id, tenant_id,
                    return_id, is_commissionable, transaction_source,
                    server_time, user_id
                )
                SELECT
                    rc_base.return_code,
                    ?::integer,
                    COALESCE(mr.transaction_number, ''),
                    ?,
                    mr.transaction_type,
                    'OTHER PAYMENTS/DEPOSITS',
                    'CR',
                    rc_base.property_code,
                    mr.unit_code,
                    mr.tenant_name,
                    mr.credit_amount,
                    mr.credit_amount,
                    mr.transaction_description,
                    mr.property_id,
                    mr.client_id,
                    mr.zone_id,
                    mr.field_officer_id,
                    mr.zone_supervisor_id,
                    mr.zone_manager_id,
                    mr.unit_id,
                    mr.tenant_id,
                    rc_base.return_id,
                    'No',
                    'TENANT ACCOUNT',
                    NOW(),
                    ?
                FROM cb_money_recoveries mr
                JOIN LATERAL (
                    SELECT return_code, return_id, property_code
                    FROM cb_returns_clients
                    WHERE return_period = ? AND property_id = mr.property_id
                    LIMIT 1
                ) rc_base ON true
                WHERE mr.transaction_class = 'CR'
                  AND mr.year = ?
                  AND mr.month = ?
                  AND mr.return_id IS NULL
            ", [$returnPeriod, $transactionDate, $generatedBy, $returnPeriod, $returnYear, $returnMonthNum]);

            Log::info("Query 2D: Tenant collection CR lines inserted", ['count' => $collectionsInserted]);

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
                    CASE WHEN SUM(rc.commission_amount) > 0
                              AND SUM(CASE WHEN rc.dr_cr = 'CR' THEN rc.net_amount ELSE 0 END) > 0
                        THEN ROUND(SUM(rc.commission_amount) / SUM(CASE WHEN rc.dr_cr = 'CR' THEN rc.net_amount ELSE 0 END) * 100, 2)
                        ELSE 0
                    END AS commission_rate,
                    SUM(CASE WHEN rc.dr_cr = 'CR' THEN rc.net_amount ELSE 0 END) AS total_credit,
                    SUM(CASE WHEN rc.dr_cr = 'CR' THEN rc.net_amount ELSE 0 END) AS commission_total,
                    0 AS vatable_total,
                    SUM(rc.commission_amount) AS commission_amount,
                    SUM(rc.commission_amount) + SUM(rc.vat_amount)
                        + SUM(CASE WHEN rc.dr_cr = 'DR' THEN rc.net_amount ELSE 0 END) AS total_debit,
                    SUM(CASE WHEN rc.dr_cr = 'CR' THEN rc.net_amount ELSE 0 END)
                        - SUM(rc.commission_amount) - SUM(rc.vat_amount)
                        - SUM(CASE WHEN rc.dr_cr = 'DR' THEN rc.net_amount ELSE 0 END) AS net_amount,
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
                GROUP BY rc.return_code, rc.property_code, rc.property_id, rc.client_id, rc.return_id
            ";

            $summaryCount = DB::affectingStatement(
                $q3,
                [$returnYear, $returnMonthNum, $returnPeriod, $preparedByName, $returnPeriod]
            );

            Log::info("Query 3: Inserted summaries", ['summaries' => $summaryCount]);

            // ═══════════════════════════════════════════════════════════════════
            // QUERY 3B: Mark landlord account entries as recovered
            // Uses the same filter as Q2B; DISTINCT property_id→return_code join
            // avoids any per-row lookups.
            // ═══════════════════════════════════════════════════════════════════
            DB::statement("
                UPDATE cb_landlord_account la
                SET is_recovered = 1,
                    recovery_year        = ?,
                    recovery_month       = ?,
                    return_code          = rc_base.return_code,
                    recovery_server_time = NOW()
                FROM (
                    SELECT DISTINCT ON (property_id) property_id, return_code
                    FROM cb_returns_clients
                    WHERE return_period = ?
                      AND return_code LIKE 'R1%'
                    ORDER BY property_id, return_code
                ) rc_base
                WHERE la.property_id = rc_base.property_id
                  AND la.debit_amount > 0
                  AND la.is_recovered = 0
                  AND la.year  = ?
                  AND la.month = ?
                  AND la.entry_type_code != '0005'
            ", [$returnYear, $returnMonthNum, $returnPeriod, $returnYear, $returnMonthNum]);

            // ═══════════════════════════════════════════════════════════════════
            // QUERY 3C: Link money recoveries to their new return_id
            // ═══════════════════════════════════════════════════════════════════
            DB::statement("
                UPDATE cb_money_recoveries mr
                SET return_id  = rs.id,
                    is_locked  = true
                FROM cb_returns_summary rs
                JOIN sys_pm_properties p ON p.reference_number = rs.property_code
                WHERE mr.year  = ?
                  AND mr.month = ?
                  AND mr.return_id IS NULL
                  AND mr.property_id = p.id
                  AND rs.return_period = ?
                  AND rs.approved_status = 0
            ", [$returnYear, $returnMonthNum, $returnPeriod]);

            Log::info("Query 3B/3C: Landlord account marked recovered, recoveries linked");

            // ═══════════════════════════════════════════════════════════════════
            // CREATE APPROVAL WORKFLOW ITEMS FOR NEW SUMMARIES (bulk SQL)
            // ═══════════════════════════════════════════════════════════════════
            $userId = auth()->id();
            $clientIp = request()->ip();

            // Q4: Bulk insert approval items
            $approvalCount = DB::affectingStatement("
                INSERT INTO cb_approval_items (
                    item_type, item_id, item_reference, status,
                    current_level, total_levels, initiated_by, initiated_at,
                    created_at, updated_at
                )
                SELECT
                    'return_summary', rs.id, rs.return_code, 'Pending',
                    1, 4, ?, NOW(),
                    NOW(), NOW()
                FROM cb_returns_summary rs
                JOIN sys_pm_properties p ON p.reference_number = rs.property_code
                WHERE rs.return_period = ?
                  AND rs.approved_status = 0
                  AND p.field_officer_id IS NOT NULL
                ON CONFLICT (item_type, item_id) DO NOTHING
            ", [$userId, $returnPeriod]);

            // Q5: Bulk insert level-1 approvers
            DB::statement("
                INSERT INTO cb_item_approvers (
                    approval_item_id, approver_id, approver_level,
                    is_optional, approval_status, assigned_at,
                    created_at, updated_at
                )
                SELECT
                    ai.id, p.field_officer_id, 1,
                    0, 'Pending', NOW(),
                    NOW(), NOW()
                FROM cb_approval_items ai
                JOIN cb_returns_summary rs ON rs.id = ai.item_id AND ai.item_type = 'return_summary'
                JOIN sys_pm_properties p ON p.reference_number = rs.property_code
                WHERE rs.return_period = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM cb_item_approvers ia
                      WHERE ia.approval_item_id = ai.id AND ia.approver_level = 1
                  )
            ", [$returnPeriod]);

            // Q6: Bulk insert initial actions
            DB::statement("
                INSERT INTO cb_approval_actions (
                    item_approver_id, approval_item_id, actor_id,
                    from_level, to_level, action, comments,
                    action_timestamp, ip_address, sequence_number, is_current,
                    created_at, updated_at
                )
                SELECT
                    ia.id, ai.id, ?,
                    0, 1, 'Initiated', 'Return submitted for approval',
                    NOW(), ?, 1, 1,
                    NOW(), NOW()
                FROM cb_item_approvers ia
                JOIN cb_approval_items ai ON ai.id = ia.approval_item_id
                JOIN cb_returns_summary rs ON rs.id = ai.item_id AND ai.item_type = 'return_summary'
                WHERE rs.return_period = ?
                  AND ia.approver_level = 1
                  AND NOT EXISTS (
                      SELECT 1 FROM cb_approval_actions aa
                      WHERE aa.item_approver_id = ia.id AND aa.action = 'Initiated'
                  )
            ", [$userId, $clientIp, $returnPeriod]);

            Log::info("Approval items created", ['count' => $approvalCount]);

            DB::commit();

            Log::info("=== RETURN GENERATION COMPLETE ===", [
                'client_lines'       => $rentInserted,
                'advance_lines'      => $advancesInserted,
                'recovery_lines'     => $recoveriesInserted,
                'collection_lines'   => $collectionsInserted,
                'summaries'          => $summaryCount,
                'returns_month'      => $returnsMonth,
            ]);

            $totalLines = $rentInserted + $advancesInserted + $recoveriesInserted + $collectionsInserted;
            $message = "Client returns generated successfully for {$summaryCount} properties ({$rentInserted} rent + {$advancesInserted} advance + {$recoveriesInserted} recovery + {$collectionsInserted} collection lines)";

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
                ->leftJoin('sys_pm_clients', 'sys_pm_clients.reference_number', '=', 'cb_returns_summary.landlord_code')
                ->leftJoin('sys_pm_properties', 'sys_pm_properties.reference_number', '=', 'cb_returns_summary.property_code')
                ->where('cb_returns_summary.year', $year)
                ->where('cb_returns_summary.month', $month)
                ->orderBy('cb_returns_summary.property_code')
                ->orderBy('cb_returns_summary.landlord_code')
                ->select('cb_returns_summary.*', 'sys_pm_clients.names as client_name', 'sys_pm_properties.property_name')
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
                        'client_name' => ($r->client_name ?? '-') . ' (' . preg_replace('/^LAN-?/i', '', $r->landlord_code ?? '') . ')',
                        'property_code' => $r->property_code ?? '-',
                        'property_name' => $r->property_name ?? '-',
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
                'pdfUrl'   => route('processed.returns.pdf', encrypt($return->id)),
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
     * Download PDF for a specific return — landscape A4, tenant-pivot layout
     */
    public function downloadPdf($id)
    {
        try {
            $id = decrypt($id);

            $return = ReturnSummary::findOrFail($id);

            $allowedTypes = ['MINIRETURN', 'MAINRETURN'];
            if (!in_array($return->return_type, $allowedTypes)) {
                abort(403, 'PDF download is only available for MINIRETURN and MAINRETURN returns.');
            }

            $lines = ReturnClient::where('return_code', $return->return_code)
                ->orderBy('tenant_name')
                ->orderBy('unit_code')
                ->get();

            // Recompute line totals (same formula as details page)
            $lineTotals = ReturnClient::where('return_code', $return->return_code)
                ->selectRaw("
                    SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN net_amount ELSE 0 END) as rent_total,
                    SUM(CASE WHEN entry_type = 'Commission' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(commission_amount, 0) ELSE 0 END) as commission,
                    SUM(CASE WHEN entry_type = 'VAT' THEN net_amount ELSE 0 END)
                      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(vat_amount, 0) ELSE 0 END) as vat
                ")
                ->first();

            // Pivot lines by tenant + unit
            $tenantRows = [];
            $totals = [
                'monthly_rent'   => 0.0,
                'rent_arrears'   => 0.0,
                'vat'            => 0.0,
                'house_deposit'  => 0.0,
                'elec_deposit'   => 0.0,
                'water_deposit'  => 0.0,
                'elec_bill'      => 0.0,
                'water_bill'     => 0.0,
                'service_charge' => 0.0,
            ];

            foreach ($lines as $l) {
                $key = ($l->tenant_name ?? '') . '||' . ($l->unit_code ?? '');
                if (!isset($tenantRows[$key])) {
                    $tenantRows[$key] = [
                        'tenant_name'       => $l->tenant_name ?? '-',
                        'unit_code'         => $l->unit_code ?? '-',
                        'monthly_rent'      => 0.0,
                        'rent_arrears'      => 0.0,
                        'vat'               => 0.0,
                        'house_deposit_amt' => 0.0,
                        'house_deposit_ref' => '',
                        'elec_deposit_amt'  => 0.0,
                        'elec_deposit_ref'  => '',
                        'water_deposit_amt' => 0.0,
                        'water_deposit_ref' => '',
                        'elec_bill'         => 0.0,
                        'water_bill'        => 0.0,
                        'service_charge'    => 0.0,
                        'remarks'           => '',
                    ];
                }

                $et  = strtoupper(trim($l->entry_type ?? ''));
                $ed  = strtoupper(trim($l->entry_description ?? '')); // fallback for entries stored under generic types
                $amt = (float) ($l->net_amount ?? 0);
                $ref = $l->transaction_ref ?? '';

                if (str_contains($et, 'HOUSE') && str_contains($et, 'DEPOSIT')) {
                    $tenantRows[$key]['house_deposit_amt'] += $amt;
                    if ($ref) $tenantRows[$key]['house_deposit_ref'] = $ref;
                    $totals['house_deposit'] += $amt;
                } elseif ((str_contains($et, 'ELEC') || str_contains($et, 'ELECTRIC')) && str_contains($et, 'DEPOSIT')) {
                    $tenantRows[$key]['elec_deposit_amt'] += $amt;
                    if ($ref) $tenantRows[$key]['elec_deposit_ref'] = $ref;
                    $totals['elec_deposit'] += $amt;
                } elseif (str_contains($et, 'WATER') && str_contains($et, 'DEPOSIT')) {
                    $tenantRows[$key]['water_deposit_amt'] += $amt;
                    if ($ref) $tenantRows[$key]['water_deposit_ref'] = $ref;
                    $totals['water_deposit'] += $amt;
                } elseif (str_contains($et, 'RENT ARREARS') || str_contains($ed, 'RENT ARREARS')) {
                    $tenantRows[$key]['rent_arrears'] += $amt;
                    $totals['rent_arrears'] += $amt;
                } elseif (str_contains($et, 'RENT') || str_contains($ed, 'RENT')) {
                    $tenantRows[$key]['monthly_rent'] += $amt;
                    $totals['monthly_rent'] += $amt;
                } elseif (str_contains($et, 'ELEC') || str_contains($et, 'ELECTRIC') || str_contains($ed, 'ELEC') || str_contains($ed, 'ELECTRIC')) {
                    $tenantRows[$key]['elec_bill'] += $amt;
                    $totals['elec_bill'] += $amt;
                } elseif (str_contains($et, 'WATER') || str_contains($ed, 'WATER')) {
                    $tenantRows[$key]['water_bill'] += $amt;
                    $totals['water_bill'] += $amt;
                } elseif (str_contains($et, 'SERVICE') || str_contains($ed, 'SERVICE')) {
                    $tenantRows[$key]['service_charge'] += $amt;
                    $totals['service_charge'] += $amt;
                }

                if ((float) ($l->vat_amount ?? 0) > 0) {
                    $tenantRows[$key]['vat'] += (float) $l->vat_amount;
                    $totals['vat'] += (float) $l->vat_amount;
                }

                if (!empty($l->remarks)) {
                    $tenantRows[$key]['remarks'] = trim($tenantRows[$key]['remarks'] . ' ' . $l->remarks);
                }
            }

            // Financial summary
            $commTotal      = (float) ($return->commission_total ?? 0);
            $commission     = (float) ($lineTotals->commission ?? 0);
            $vat            = (float) ($lineTotals->vat ?? 0);
            $balAfterComm   = $commTotal - $commission;
            $balLessVat     = $balAfterComm - $vat;
            $houseDeposits  = $totals['house_deposit'];
            $waterElecBills = $totals['elec_bill'] + $totals['water_bill'];

            $summary = [
                'total_rent'       => (float) ($lineTotals->rent_total ?? 0),
                'comm_total'       => $commTotal,
                'commission'       => $commission,
                'bal_after_comm'   => $balAfterComm,
                'vat'              => $vat,
                'bal_less_vat'     => $balLessVat,
                'house_deposits'   => $houseDeposits,
                'water_elec_bills' => $waterElecBills,
                'net_income'       => $balLessVat + $houseDeposits + $waterElecBills,
            ];

            $logoPath = public_path('assets/images/logo/logo_dark.png');
            $logoSrc  = file_exists($logoPath)
                ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
                : '';

            $data = compact('return', 'tenantRows', 'totals', 'summary', 'logoSrc');

            $pdf = Pdf::loadView('property.processed-returns.pdf', $data);
            $pdf->setPaper('A4', 'landscape');

            $fileName = 'Return_' . $return->return_code . '_' . date('Y-m-d') . '.pdf';
            $token    = request()->query('download_token');
            $pdfContent = $pdf->output();

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');
            if ($token) {
                header('Set-Cookie: download_complete_' . $token . '=1; Path=/; Max-Age=60; SameSite=Lax');
            }
            echo $pdfContent;
            exit;
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating return PDF: ' . $e->getMessage());
            return back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
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

            $allowedTypes = ['MINIRETURN', 'MAINRETURN'];
            if (!in_array($return->return_type, $allowedTypes)) {
                abort(403, 'Excel download is only available for MINIRETURN and MAINRETURN returns.');
            }

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
                    ->setCellValue('B' . $row, $l->narration ?? '-')
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
            $dlToken = request()->query('download_token');
            if ($dlToken) {
                header('Set-Cookie: download_complete_' . $dlToken . '=1; Path=/; Max-Age=60; SameSite=Lax');
            }

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
