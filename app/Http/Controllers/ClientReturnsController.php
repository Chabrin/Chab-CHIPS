<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use App\Services\ReturnApprovalWorkflowService;
use App\Exceptions\ReturnApprovalException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\ClientReturnsBatch;
use App\Models\ReturnSummary;
use App\Models\ReturnClient;
use App\Jobs\UpdateAbstractThresholdsJob;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Traits\GeneralTraits\Reusables;

use App\Traits\ReturnsRentLinesTrait;
use App\Traits\ReturnsPayThresholdTrait;
use App\Traits\ReturnsDeductionsTrait;
use App\Traits\ReturnsSummaryTrait;
use App\Traits\ReturnsMasterReturnTrait;
use App\Traits\ReturnsLandlordAccountTrait;
use App\Traits\PdfPageNumbers;

class ClientReturnsController extends Controller
{
    use PdfPageNumbers;
    use ReturnsRentLinesTrait;
    use ReturnsPayThresholdTrait;
    use ReturnsDeductionsTrait;
    use ReturnsSummaryTrait;
    use ReturnsMasterReturnTrait;
    use ReturnsLandlordAccountTrait;
    use Reusables;

    protected ReturnApprovalWorkflowService $returnApprovals;

    /**
     * Inject the service that decides who approves a return.
     */
    public function __construct(ReturnApprovalWorkflowService $returnApprovals) {
        $this->returnApprovals = $returnApprovals;
    }

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

            Log::info("Fetching returns list data for year={$year}, month={$month}, user_id={$userId}");


            // Check if user has a global role (sees all returns, no property filter)
            $hasGlobalRole = $this->checkGlobalRole($userId);

            $query = ReturnSummary::unpaid()
                ->where('year', $year)
                ->where('month', $month);
            //count of lines for sorting in datatable

            // Role-based filtering: non-global users only see returns for properties assigned to them
            $allowedPropertyCodes = null;
            if (!$hasGlobalRole) {
                $staffId = DB::table('sys_user_staff_links')
                    ->where('user_id', $userId)
                    ->value('staff_id');

                if (!$staffId) {
                    $query->whereRaw('1 = 0');
                } else {
                    // Allowed property reference_numbers (assigned to this staff)
                    $allowedPropertyCodes = DB::table('sys_pm_properties as spp')
                        ->join('sys_pm_zones_assignments as spza', 'spza.property_id', '=', 'spp.id')
                        ->where('spza.staff_id', $staffId)
                        ->where('spza.current_status_id', 1)
                        ->distinct()
                        ->pluck('spp.reference_number')
                        ->all();

                    // Allowed landlord reference_numbers (landlords who own at least one assigned property)
                    $allowedLandlordCodes = DB::table('sys_pm_clients as spc')
                        ->join('sys_pm_properties as spp', 'spp.client_id', '=', 'spc.id')
                        ->join('sys_pm_zones_assignments as spza', 'spza.property_id', '=', 'spp.id')
                        ->where('spza.staff_id', $staffId)
                        ->where('spza.current_status_id', 1)
                        ->distinct()
                        ->pluck('spc.reference_number')
                        ->all();

                    // Pre-compute visible MASTERRETURN ids for this period: landlord must have an
                    // assigned property AND the master's children (via transaction_ref CSV, with
                    // legacy fallback to landlord+period) must include at least one assigned property
                    $visibleMasterIds = [];
                    if (!empty($allowedLandlordCodes) && !empty($allowedPropertyCodes)) {
                        $masters = DB::table('cb_returns_summary')
                            ->where('return_type', 'MASTERRETURN')
                            ->where('year', $year)
                            ->where('month', $month)
                            ->whereIn('landlord_code', $allowedLandlordCodes)
                            ->select('id', 'landlord_code', 'transaction_ref')
                            ->get();

                        foreach ($masters as $m) {
                            $childCodes = array_filter(array_map('trim', explode(',', (string) ($m->transaction_ref ?? ''))));
                            $childQ = DB::table('cb_returns_summary')->whereIn('property_code', $allowedPropertyCodes);
                            if (!empty($childCodes)) {
                                $childQ->whereIn('return_code', $childCodes);
                            } else {
                                // Legacy master without transaction_ref: match children by landlord+period
                                $childQ->where('landlord_code', $m->landlord_code)
                                    ->where('year', $year)
                                    ->where('month', $month)
                                    ->where('return_type', 'MAINRETURN');
                            }
                            if ($childQ->exists()) {
                                $visibleMasterIds[] = $m->id;
                            }
                        }
                    }

                    $query->where(function ($q) use ($allowedPropertyCodes, $visibleMasterIds) {
                        // MASTERRETURNs — visibility pre-resolved above
                        $q->where(function ($qm) use ($visibleMasterIds) {
                            $qm->where('return_type', 'MASTERRETURN');
                            if (empty($visibleMasterIds)) {
                                $qm->whereRaw('1 = 0');
                            } else {
                                $qm->whereIn('id', $visibleMasterIds);
                            }
                        })
                            // Non-master returns — match by property_code
                            ->orWhere(function ($qp) use ($allowedPropertyCodes) {
                                $qp->where('return_type', '!=', 'MASTERRETURN');
                                if (empty($allowedPropertyCodes)) {
                                    $qp->whereRaw('1 = 0');
                                } else {
                                    $qp->whereIn('property_code', $allowedPropertyCodes);
                                }
                            });
                    });
                }
            }

            // Get property names and client names in one lookup
            $returns = $query->orderBy('property_code')
                ->orderBy('landlord_code')
                ->get();
            //log count of returns fetched
            Log::info("Fetched {$returns->count()} returns for year={$year}, month={$month}, user_id={$userId}");

            // Properties each return covers (a master return has none of its own, so it carries its children's)
            $coveredCodes = $this->returnPropertyCodes($returns, (int) $year, (int) $month);
            if ($allowedPropertyCodes !== null) {
                $coveredCodes = array_map(fn($codes) => array_values(array_intersect($codes, $allowedPropertyCodes)), $coveredCodes);
            }

            $propertyCodes = collect($coveredCodes)->flatten()->unique()->filter();
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
                'properties' => $propertyCodes
                    ->map(fn($code) => ['code' => $code, 'name' => $propertyNames[$code] ?? $code])
                    ->sortBy(fn($p) => strtolower($p['name']))
                    ->values(),
                'data' => $returns->map(function ($r) use ($propertyNames, $clientNames, $coveredCodes) {
                    $name = $clientNames[$r->landlord_code] ?? '-';
                    $num = preg_replace('/^LAN-?/i', '', $r->landlord_code ?? '');
                    return [
                        'id' => $r->id,
                        'encrypted_id' => encrypt($r->id),
                        'return_code' => $r->return_code,
                        'landlord_code' => $r->landlord_code,
                        'client_name' => $name . ' (' . $num . ')',
                        'property_code' => $r->property_code ?? '-',
                        'property_codes' => $coveredCodes[$r->id] ?? [],
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

    /**
     * Property reference numbers each listed return covers, keyed by return id: a main/mini return has its
     * own property, a master return has the properties of its child (main) returns.
     *
     * @param \Illuminate\Support\Collection $returns
     * @param int $year
     * @param int $month
     * @return array
     */
    private function returnPropertyCodes($returns, int $year, int $month): array {
        $codesOf = fn($ref) => array_filter(array_map('trim', explode(',', (string) $ref)));
        $masters = $returns->where('return_type', 'MASTERRETURN');
        $children = collect();

        if ($masters->isNotEmpty()) {
            $listedCodes = $masters->flatMap(fn($m) => $codesOf($m->transaction_ref))->unique()->values()->all();

            // Same child lookup as the master return PDF: the listed return codes, else the landlord's main returns of the period
            $children = DB::table('cb_returns_summary')
                ->where('return_type', 'MAINRETURN')
                ->where(fn($q) => $q->whereIn('return_code', $listedCodes)->orWhere(fn($q2) => $q2->where('year', $year)->where('month', $month)))
                ->get(['return_code', 'landlord_code', 'property_code']);
        }

        $covered = [];
        foreach ($returns as $r) {
            if ($r->return_type !== 'MASTERRETURN') {
                $covered[$r->id] = array_values(array_filter([$r->property_code]));
                continue;
            }

            $listed = $codesOf($r->transaction_ref);
            $mine = $listed ? $children->whereIn('return_code', $listed) : $children->where('landlord_code', $r->landlord_code);
            $covered[$r->id] = $mine->pluck('property_code')->filter()->unique()->values()->all();
        }

        return $covered;
    }

    // ═══════════════════════════════════════════════════════════════════
    // UNPROCESSED RETURNS — separate from processed returns
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Build the header info shown above the financial summary on both
     * the unprocessed and processed return-details pages. For MASTERRETURNs
     * the property column lists every child property and the period uses
     * the full month name (e.g. "March 2026").
     *
     * @return array{return_code:string,property_label:string,landlord_label:string,period:string,return_type:string}
     */
    protected function buildReturnHeaderInfo(ReturnSummary $return): array
    {
        $isMaster = ($return->return_type === 'MASTERRETURN');

        $landlordName = $return->landlord_name
            ?: DB::table('sys_pm_clients')->where('reference_number', $return->landlord_code)->value('names');
        $landlordLabel = trim((string) $return->landlord_code . ($landlordName ? ' - ' . $landlordName : ''));

        if ($isMaster) {
            $childCodes = [];
            if (!empty($return->transaction_ref)) {
                $childCodes = array_filter(array_map('trim', explode(',', $return->transaction_ref)));
            }

            $childQuery = DB::table('cb_returns_summary as rs')
                ->leftJoin('sys_pm_properties as p', 'p.reference_number', '=', 'rs.property_code')
                ->where('rs.return_type', 'MAINRETURN');

            if (!empty($childCodes)) {
                $childQuery->whereIn('rs.return_code', $childCodes);
            } else {
                // Legacy fallback: no transaction_ref recorded → match by landlord/period
                $childQuery->where('rs.landlord_code', $return->landlord_code)
                    ->where('rs.year', $return->year)
                    ->where('rs.month', $return->month);
            }

            $children = $childQuery->select('rs.property_code', 'p.property_name')
                ->orderBy('rs.property_code')
                ->get();

            $propertyLabel = $children
                ->map(fn($c) => trim((string) $c->property_code . ($c->property_name ? ' - ' . $c->property_name : '')))
                ->filter()
                ->implode(', ');
        } else {
            $propertyName = $return->property_name
                ?: DB::table('sys_pm_properties')->where('reference_number', $return->property_code)->value('property_name');
            $propertyLabel = trim((string) ($return->property_code ?? '') . ($propertyName ? ' - ' . $propertyName : ''));
        }

        $period = $isMaster
            ? date('F Y', mktime(0, 0, 0, (int) $return->month, 1, (int) $return->year))
            : str_pad($return->month, 2, '0', STR_PAD_LEFT) . '/' . $return->year;

        return [
            'return_code'    => $return->return_code,
            'property_label' => $propertyLabel !== '' ? $propertyLabel : '-',
            'landlord_label' => $landlordLabel !== '' ? $landlordLabel : '-',
            'period'         => $period,
            'return_type'    => $return->return_type ?? '-',
        ];
    }

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

            // The DR 'COMMISSION' line repeats the commission and VAT already on the rent lines, so it stays out of the debits.
            $lineTotals = ReturnClient::where('return_code', $return->return_code)
                ->selectRaw("
                    SUM(CASE WHEN dr_cr = 'CR' THEN net_amount ELSE 0 END) as total_cr,
                    SUM(CASE WHEN dr_cr = 'DR' AND COALESCE(entry_type, '') <> 'COMMISSION' THEN net_amount ELSE 0 END)
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

            $headerInfo = $this->buildReturnHeaderInfo($return);

            return view('property.returns.unprocessed-details', compact('return', 'lineTotals', 'allReturns', 'headerInfo'));
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
                    SUM(CASE WHEN dr_cr = 'DR' AND COALESCE(entry_type, '') <> 'COMMISSION' THEN net_amount ELSE 0 END)
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

            $headerInfo = $this->buildReturnHeaderInfo($return);

            return response()->json([
                'success' => true,
                'return' => [
                    'id' => encrypt($return->id),
                    'return_code' => $return->return_code,
                    'property_code' => $return->property_code ?? '-',
                    'property_label' => $headerInfo['property_label'],
                    'landlord_code' => $return->landlord_code,
                    'landlord_label' => $headerInfo['landlord_label'],
                    'period' => $headerInfo['period'],
                    'return_type' => $headerInfo['return_type'],
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
                'pdfUrl' => route('returns.pdf', encrypt($return->id)),
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

            $details = ReturnClient::forReturn($return->return_code);

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
    public function downloadUnprocessedPdf($id)
    {
        try {
            $id = decrypt($id);
            $return = ReturnSummary::findOrFail($id);

            $lines = ReturnClient::forReturn($return->return_code);

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
                $ed  = strtoupper(trim($l->entry_description ?? ''));
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

            // Rows in unit order (B2 before B10), whichever entry type introduced the unit
            $tenantRows = $this->sortTenantRowsByUnit($tenantRows);

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

            $landlordName = $return->landlord_name
                ?: DB::table('sys_pm_clients')->where('reference_number', $return->landlord_code)->value('names');
            $propertyName = $return->property_name
                ?: DB::table('sys_pm_properties')->where('reference_number', $return->property_code)->value('property_name');

            $propertyDisplay = $propertyName
                ? trim($propertyName . ' - ' . ($return->property_code ?? ''))
                : ($return->property_code ?? '-');
            $landlordDisplay = $landlordName
                ? trim($landlordName . ' (' . ($return->landlord_code ?? '') . ')')
                : ($return->landlord_code ?? '-');

            $signoff = [
                'prepared' => [
                    'name' => $return->prepared_by ?? '-',
                    'date' => $return->prepared_date ? \Carbon\Carbon::parse($return->prepared_date)->format('d/m/Y') : '-'
                ],
                'checked' => [
                    'name' => $return->checked_by ?? '-',
                    'date' => $return->checked_date ? \Carbon\Carbon::parse($return->checked_date)->format('d/m/Y') : '-'
                ],
                'approved' => [
                    'name' => $return->approved_by ?? '-',
                    'date' => $return->approved_date ? \Carbon\Carbon::parse($return->approved_date)->format('d/m/Y') : '-'
                ]
            ];

            $data = compact('return', 'tenantRows', 'totals', 'summary', 'signoff', 'propertyDisplay', 'landlordDisplay');

            $pdf = Pdf::loadView('property.processed-returns.pdf', $data)
                ->setPaper('A4', 'landscape')
                ->setOption(['enable_font_subsetting' => true]);

            $dompdf = $pdf->getDomPDF();
            $dompdf->render();
            $this->addPdfPageNumbers($dompdf);

            $fileName = 'Unprocessed_Return_' . $return->return_code . '_' . date('Y-m-d') . '.pdf';
            $token = request()->query('download_token');
            $pdfContent = $dompdf->output();

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');
            if ($token) {
                header('Set-Cookie: download_complete_' . $token . '=1; Path=/; Max-Age=60; SameSite=Lax');
            }
            echo $pdfContent;
            exit;
        } catch (Exception $e) {
            Log::error('Error downloading unprocessed return pdf: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download PDF');
        }
    }

    public function downloadUnprocessedExcel($id)
    {
        try {
            $id = decrypt($id);
            $return = ReturnSummary::findOrFail($id);

            $lines = ReturnClient::forReturn($return->return_code);

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
                ->join('cb_tenant_records as t', 't.id', '=', 'cr.tenant_id')
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
                        $q->where('property_name', 'ILIKE', "%{$search}%")
                            ->orWhere('description', 'ILIKE', "%{$search}%")
                            ->orWhere('reference_number', 'ILIKE', "%{$search}%");
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
     * Process returns generation using modular traits.
     * 
     * Flow:
     * 1) Insert rent CR lines from invoices (ReturnsRentLinesTrait)
     * 2) Update pay threshold percentages (ReturnsPayThresholdTrait)
     * 3) Insert deductions: advances, recoveries, collections (ReturnsDeductionsTrait)
     * 4) Insert MAINRETURN summaries (ReturnsSummaryTrait)
     * 5) Generate MASTERRETURNs for batched properties (ReturnsMasterReturnTrait)
     * 6) Mark landlord account entries as recovered (ReturnsLandlordAccountTrait)
     * 7) Create approval workflow items
     * 
     * Supports optional property_id for single-property generation.
     */
    public function processGenerate(Request $request)
    {
        try {
            $request->validate([
                'returns_month' => 'required|date_format:Y-m',
                'property_id'   => 'nullable|integer|exists:sys_pm_properties,id'
            ]);

            // Parse input parameters
            $returnsMonth = $request->returns_month; // YYYY-MM
            $returnPeriod = str_replace('-', '', $returnsMonth); // YYYYMM
            $generatedBy = Auth::id();
            $requestedPropertyId = $request->property_id ? (int) $request->property_id : null;

            [$returnYear, $returnMonthNum] = explode('-', $returnsMonth);
            $returnYear = (int) $returnYear;
            $returnMonthNum = (int) $returnMonthNum;
            $monthName = date('F', mktime(0, 0, 0, $returnMonthNum, 1, $returnYear));
            $monthStart = $returnsMonth . '-01';
            $prevMonthStart = date('Y-m-01', strtotime($monthStart . ' -1 month'));
            $transactionDate = date('d-M-Y', strtotime($returnsMonth . '-05'));
            $preparedByName = DB::table('josya_users')->where('id', $generatedBy)->value('name') ?? 'System';

            // The approvers come from the 'returns_approval' workflow: do not generate returns nobody can approve
            try {
                $this->returnApprovals->assertConfigured();
            } catch (ReturnApprovalException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            // ═══════════════════════════════════════════════════════════════════
            // CHECK FOR MERGE BATCH: If single property is in merge batch, get all partners
            // ═══════════════════════════════════════════════════════════════════
            $mergeInfo = null;
            $propertyIds = [];
            $isMergeBatchMode = false;

            if ($requestedPropertyId) {
                $mergeInfo = $this->getMergeBatchInfo($requestedPropertyId);
                if ($mergeInfo) {
                    $propertyIds = $mergeInfo['property_ids'];
                    $isMergeBatchMode = true;
                    Log::info("Merge batch detected", [
                        'requested_property' => $requestedPropertyId,
                        'batch_reference' => $mergeInfo['batch_reference'],
                        'all_properties' => $propertyIds
                    ]);
                } else {
                    $propertyIds = [$requestedPropertyId];
                }
            }

            DB::beginTransaction();

            // ═══════════════════════════════════════════════════════════════════
            // VALIDATION: Check for approved invoices
            // ═══════════════════════════════════════════════════════════════════
            $totalInvoices = 0;
            if ($isMergeBatchMode) {
                foreach ($propertyIds as $pid) {
                    $totalInvoices += $this->countApprovedInvoices($monthStart, $pid);
                }
            } else {
                $totalInvoices = $this->countApprovedInvoices($monthStart, $requestedPropertyId);
            }

            if ($totalInvoices == 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $requestedPropertyId
                        ? 'No  invoices found for this property in the selected period.'
                        : 'No  invoices found for the selected period.'
                ], 422);
            }

            Log::info("=== RETURN GENERATION START ===", [
                'returns_month'    => $returnsMonth,
                'total_invoices'   => $totalInvoices,
                'property_id'      => $requestedPropertyId,
                'merge_batch_mode' => $isMergeBatchMode,
                'property_ids'     => $propertyIds ?: null,
            ]);

            // ═══════════════════════════════════════════════════════════════════
            // VALIDATION & DELETE: Check approved returns and delete unapproved
            // ═══════════════════════════════════════════════════════════════════
            if ($isMergeBatchMode) {
                // Check and delete for ALL properties in merge batch
                foreach ($propertyIds as $pid) {
                    if ($this->approvedReturnsExistForPeriod($returnPeriod, $pid)) {
                        DB::rollBack();
                        $propName = DB::table('sys_pm_properties')->where('id', $pid)->value('property_name') ?? "ID {$pid}";
                        return response()->json([
                            'success' => false,
                            'message' => "Returns for {$monthName} {$returnYear} have been approved for merge partner '{$propName}' and cannot be regenerated."
                        ], 422);
                    }
                }
                foreach ($propertyIds as $pid) {
                    $deleted = $this->deleteUnapprovedReturnsForPeriod($returnPeriod, $pid);
                    if ($deleted['summaries'] > 0) {
                        Log::info("Pre-generation cleanup: Deleted unapproved returns for merge partner", [
                            'period' => $returnPeriod,
                            'property_id' => $pid,
                            'deleted' => $deleted
                        ]);
                    }
                }
            } else {
                $propMsg = $requestedPropertyId ? ' for this property' : '';
                if ($this->approvedReturnsExistForPeriod($returnPeriod, $requestedPropertyId)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Returns for {$monthName} {$returnYear} have been approved{$propMsg} and cannot be regenerated."
                    ], 422);
                }
                $deleted = $this->deleteUnapprovedReturnsForPeriod($returnPeriod, $requestedPropertyId);
                if ($deleted['summaries'] > 0) {
                    Log::info("Pre-generation cleanup: Deleted unapproved returns", [
                        'period' => $returnPeriod,
                        'property_id' => $requestedPropertyId,
                        'deleted' => $deleted
                    ]);
                }
            }

            // Get next return_id base
            $baseReturnId = $this->getNextReturnIdBase();

            // ═══════════════════════════════════════════════════════════════════
            // GENERATION: Process properties (loop for merge batch, single otherwise)
            // ═══════════════════════════════════════════════════════════════════
            $rentInserted = 0;
            $advancesInserted = 0;
            $recoveriesInserted = 0;
            $collectionsInserted = 0;
            $commissionsInserted = 0;
            $summaryCount = 0;

            $propertiesToProcess = $isMergeBatchMode ? $propertyIds : [$requestedPropertyId];

            foreach ($propertiesToProcess as $propertyId) {
                // STEP 1: INSERT RENT LINES
                $rentInserted += $this->insertRentLines(
                    $returnPeriod,
                    $monthStart,
                    $transactionDate,
                    $baseReturnId,
                    $generatedBy,
                    $monthName,
                    $returnYear,
                    $propertyId
                );

                // STEP 2: UPDATE PAY THRESHOLDS
                $this->updatePayThresholds($returnPeriod, $monthStart, $prevMonthStart, $propertyId);

                // STEP 3: INSERT DEDUCTIONS
                $advancesInserted += $this->insertAdvancePaymentLines(
                    $returnPeriod,
                    $transactionDate,
                    $generatedBy,
                    $returnYear,
                    $returnMonthNum,
                    $propertyId
                );

                $recoveriesInserted += $this->insertRecoveryLines(
                    $returnPeriod,
                    $transactionDate,
                    $generatedBy,
                    $returnYear,
                    $returnMonthNum,
                    $propertyId
                );

                $collectionsInserted += $this->insertCollectionLines(
                    $returnPeriod,
                    $transactionDate,
                    $generatedBy,
                    $returnYear,
                    $returnMonthNum,
                    $propertyId
                );

                // STEP 3b: INSERT COMMISSION DR LINES
                $commissionsInserted += $this->insertCommissionLines(
                    $returnPeriod,
                    $transactionDate,
                    $generatedBy,
                    $propertyId
                );

                // STEP 4: INSERT MAINRETURN SUMMARIES
                $summaryCount += $this->insertMainReturnSummariesLegacy(
                    $returnPeriod,
                    $returnYear,
                    $returnMonthNum,
                    $monthName,
                    $preparedByName,
                    $propertyId
                );

                // STEP 6: MARK AS RECOVERED
                $this->markLandlordAccountRecovered($returnPeriod, $returnYear, $returnMonthNum, $propertyId);
                $this->linkMoneyRecoveriesToReturns($returnPeriod, $returnYear, $returnMonthNum, $propertyId);

                // STEP 7: CREATE APPROVAL WORKFLOW ITEMS
                $this->createApprovalWorkflowItems($returnPeriod, $generatedBy, $propertyId);

                Log::info("Processed property", [
                    'property_id' => $propertyId,
                    'merge_batch_mode' => $isMergeBatchMode
                ]);
            }

            if ($rentInserted == 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No returns generated. All returns may already be approved.'
                ], 422);
            }

            Log::info("Steps 1-4, 6-7 completed", [
                'rent' => $rentInserted,
                'advances' => $advancesInserted,
                'recoveries' => $recoveriesInserted,
                'collections' => $collectionsInserted,
                'commissions' => $commissionsInserted,
                'summaries' => $summaryCount
            ]);

            // ═══════════════════════════════════════════════════════════════════
            // STEP 5: GENERATE MASTERRETURNs (ReturnsMasterReturnTrait)
            // Generate if: all properties OR merge batch mode (all batch properties processed)
            // ═══════════════════════════════════════════════════════════════════
            $masterReturnCount = 0;
            if (!$requestedPropertyId || $isMergeBatchMode) {
                $masterReturnCount = $this->generateMasterReturns(
                    $returnPeriod,
                    $transactionDate,
                    $generatedBy,
                    $returnYear,
                    $returnMonthNum
                );
                Log::info("Step 5: MASTERRETURNs generated", ['count' => $masterReturnCount]);
            } else {
                Log::info("Step 5: MASTERRETURN generation skipped (single property not in merge batch)");
            }

            DB::commit();

            Log::info("=== RETURN GENERATION COMPLETE ===", [
                'rent_lines'       => $rentInserted,
                'advance_lines'    => $advancesInserted,
                'recovery_lines'   => $recoveriesInserted,
                'collection_lines' => $collectionsInserted,
                'main_returns'     => $summaryCount,
                'master_returns'   => $masterReturnCount,
                'returns_month'    => $returnsMonth,
                'requested_property' => $requestedPropertyId,
                'merge_batch_mode' => $isMergeBatchMode,
                'properties_processed' => count($propertiesToProcess)
            ]);

            // Build response message
            $totalLines = $rentInserted + $advancesInserted + $recoveriesInserted + $collectionsInserted;
            if ($isMergeBatchMode) {
                $propNames = DB::table('sys_pm_properties')
                    ->whereIn('id', $propertyIds)
                    ->pluck('property_name')
                    ->toArray();
                $propLabel = " for merge batch '{$mergeInfo['batch_reference']}' (" . count($propertyIds) . " properties: " . implode(', ', $propNames) . ")";
            } elseif ($requestedPropertyId) {
                $propLabel = " for property ID {$requestedPropertyId}";
            } else {
                $propLabel = "";
            }

            $message = "Client returns generated successfully{$propLabel}: {$summaryCount} MAINRETURN(s)";
            if ($masterReturnCount > 0) {
                $message .= ", {$masterReturnCount} MASTERRETURN(s)";
            }
            $message .= " ({$rentInserted} rent + {$advancesInserted} advance + {$recoveriesInserted} recovery + {$collectionsInserted} collection lines)";

            return response()->json([
                'success'            => true,
                'message'            => $message,
                'count'              => $rentInserted,
                'properties_count'   => $summaryCount,
                'master_count'       => $masterReturnCount,
                'property_id'        => $requestedPropertyId,
                'merge_batch'        => $isMergeBatchMode ? [
                    'batch_reference' => $mergeInfo['batch_reference'],
                    'property_ids'    => $propertyIds,
                    'properties_count' => count($propertyIds)
                ] : null
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
     * Get merge batch information for a property.
     * Returns null if property is not in any merge batch.
     *
     * @param int $propertyId
     * @return array|null ['batch_id', 'batch_reference', 'property_ids']
     */
    protected function getMergeBatchInfo(int $propertyId): ?array
    {
        // Check if property is in any merge batch
        $batchItem = DB::table('cb_returns_merge_batch_items')
            ->where('property_id', $propertyId)
            ->first();

        if (!$batchItem) {
            return null;
        }

        // Get batch info
        $batch = DB::table('cb_returns_merge_batches')
            ->where('id', $batchItem->batch_id)
            ->first();

        if (!$batch) {
            return null;
        }

        // Get all property IDs in this batch
        $propertyIds = DB::table('cb_returns_merge_batch_items')
            ->where('batch_id', $batchItem->batch_id)
            ->pluck('property_id')
            ->toArray();

        return [
            'batch_id'        => $batch->id,
            'batch_reference' => $batch->batch_reference,
            'landlord_name'   => $batch->landlord_name,
            'property_ids'    => $propertyIds
        ];
    }

    /**
     * Insert MAINRETURN summaries using original aggregation logic.
     * Kept separate to ensure accurate calculations.
     */
    protected function insertMainReturnSummariesLegacy(
        string $returnPeriod,
        int $returnYear,
        int $returnMonthNum,
        string $monthName,
        string $preparedByName,
        ?int $propertyId = null
    ): int {
        $propertyFilter = $propertyId ? " AND rc.property_id = ?" : "";

        // The rent lines already carry their commission and VAT, and insertCommissionLines adds a DR 'COMMISSION' line
        // that repeats the same two totals for the printed return. That line is left out of every total below, or the
        // commission and the VAT would be taken off the landlord twice.
        $netAmount = "SUM(CASE WHEN rc.dr_cr = 'CR' THEN rc.net_amount ELSE 0 END)
                    - SUM(rc.commission_amount) - SUM(rc.vat_amount)
                    - SUM(CASE WHEN rc.dr_cr = 'DR' THEN rc.net_amount ELSE 0 END)";

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
                {$netAmount} AS net_amount,
                0 AS transaction_amount,
                {$netAmount} AS net_balance,
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
              AND COALESCE(rc.entry_type, '') <> 'COMMISSION'
              {$propertyFilter}
              AND NOT EXISTS (
                  SELECT 1 FROM cb_returns_summary rs WHERE rs.return_code = rc.return_code
              )
            GROUP BY rc.return_code, rc.property_code, rc.property_id, rc.client_id, rc.return_id
        ";

        $bindings = [$returnYear, $returnMonthNum, $returnPeriod, $preparedByName, $returnPeriod];
        if ($propertyId) {
            $bindings[] = $propertyId;
        }

        return DB::affectingStatement($q3, $bindings);
    }

    /**
     * Send the new return summaries for approval, with the approvers of the returns approval workflow.
     */
    protected function createApprovalWorkflowItems(string $returnPeriod, int $userId, ?int $propertyId = null): int {
        return $this->returnApprovals->createItems($returnPeriod, $userId, request()->ip(), $propertyId);
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
            $userId = Auth::id();

            $query = ReturnSummary::paid()
                ->leftJoin('sys_pm_clients', 'sys_pm_clients.reference_number', '=', 'cb_returns_summary.landlord_code')
                ->leftJoin('sys_pm_properties', 'sys_pm_properties.reference_number', '=', 'cb_returns_summary.property_code')
                ->where('cb_returns_summary.year', $year)
                ->where('cb_returns_summary.month', $month);

            // Role-based filtering: non-global users only see returns for properties assigned to them
            if (!$this->checkGlobalRole($userId)) {
                $staffId = DB::table('sys_user_staff_links')
                    ->where('user_id', $userId)
                    ->value('staff_id');

                if (!$staffId) {
                    $query->whereRaw('1 = 0');
                } else {
                    // Allowed property reference_numbers (assigned to this staff)
                    $allowedPropertyCodes = DB::table('sys_pm_properties as spp')
                        ->join('sys_pm_zones_assignments as spza', 'spza.property_id', '=', 'spp.id')
                        ->where('spza.staff_id', $staffId)
                        ->where('spza.current_status_id', 1)
                        ->distinct()
                        ->pluck('spp.reference_number')
                        ->all();

                    // Allowed landlord reference_numbers (landlords who own at least one assigned property)
                    $allowedLandlordCodes = DB::table('sys_pm_clients as spc')
                        ->join('sys_pm_properties as spp', 'spp.client_id', '=', 'spc.id')
                        ->join('sys_pm_zones_assignments as spza', 'spza.property_id', '=', 'spp.id')
                        ->where('spza.staff_id', $staffId)
                        ->where('spza.current_status_id', 1)
                        ->distinct()
                        ->pluck('spc.reference_number')
                        ->all();

                    // Pre-compute visible MASTERRETURN ids for this period: landlord must have an
                    // assigned property AND the master's children (via transaction_ref CSV, with
                    // legacy fallback to landlord+period) must include at least one assigned property
                    $visibleMasterIds = [];
                    if (!empty($allowedLandlordCodes) && !empty($allowedPropertyCodes)) {
                        $masters = DB::table('cb_returns_summary')
                            ->where('return_type', 'MASTERRETURN')
                            ->where('year', $year)
                            ->where('month', $month)
                            ->whereIn('landlord_code', $allowedLandlordCodes)
                            ->select('id', 'landlord_code', 'transaction_ref')
                            ->get();

                        foreach ($masters as $m) {
                            $childCodes = array_filter(array_map('trim', explode(',', (string) ($m->transaction_ref ?? ''))));
                            $childQ = DB::table('cb_returns_summary')->whereIn('property_code', $allowedPropertyCodes);
                            if (!empty($childCodes)) {
                                $childQ->whereIn('return_code', $childCodes);
                            } else {
                                // Legacy master without transaction_ref: match children by landlord+period
                                $childQ->where('landlord_code', $m->landlord_code)
                                    ->where('year', $year)
                                    ->where('month', $month)
                                    ->where('return_type', 'MAINRETURN');
                            }
                            if ($childQ->exists()) {
                                $visibleMasterIds[] = $m->id;
                            }
                        }
                    }

                    $query->where(function ($q) use ($allowedPropertyCodes, $visibleMasterIds) {
                        // MASTERRETURNs — visibility pre-resolved above
                        $q->where(function ($qm) use ($visibleMasterIds) {
                            $qm->where('cb_returns_summary.return_type', 'MASTERRETURN');
                            if (empty($visibleMasterIds)) {
                                $qm->whereRaw('1 = 0');
                            } else {
                                $qm->whereIn('cb_returns_summary.id', $visibleMasterIds);
                            }
                        })
                            // Non-master returns — match by property_code
                            ->orWhere(function ($qp) use ($allowedPropertyCodes) {
                                $qp->where('cb_returns_summary.return_type', '!=', 'MASTERRETURN');
                                if (empty($allowedPropertyCodes)) {
                                    $qp->whereRaw('1 = 0');
                                } else {
                                    $qp->whereIn('cb_returns_summary.property_code', $allowedPropertyCodes);
                                }
                            });
                    });
                }
            }

            $returns = $query->orderBy('cb_returns_summary.property_code')
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
                    SUM(CASE WHEN dr_cr = 'DR' AND COALESCE(entry_type, '') <> 'COMMISSION' THEN net_amount ELSE 0 END)
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

            $headerInfo = $this->buildReturnHeaderInfo($return);

            return view('property.processed-returns.details', compact('return', 'lineTotals', 'allReturns', 'headerInfo'));
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
                    SUM(CASE WHEN dr_cr = 'DR' AND COALESCE(entry_type, '') <> 'COMMISSION' THEN net_amount ELSE 0 END)
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

            $headerInfo = $this->buildReturnHeaderInfo($return);

            return response()->json([
                'success' => true,
                'return' => [
                    'id' => encrypt($return->id),
                    'return_code' => $return->return_code,
                    'property_code' => $return->property_code ?? '-',
                    'property_label' => $headerInfo['property_label'],
                    'landlord_code' => $return->landlord_code,
                    'landlord_label' => $headerInfo['landlord_label'],
                    'period' => $headerInfo['period'],
                    'return_type' => $headerInfo['return_type'],
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

            $lines = ReturnClient::forReturn($return->return_code);

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
            $signoff = $this->buildReturnSignoffData($return);

            $allowedTypes = ['MINIRETURN', 'MAINRETURN', 'MASTERRETURN'];
            if (!in_array($return->return_type, $allowedTypes)) {
                abort(403, 'PDF download is only available for MINIRETURN, MAINRETURN and MASTERRETURN returns.');
            }

            if ($return->return_type === 'MASTERRETURN') {
                $masterData = $this->buildMasterReturnExportData($return);
                $pdf = Pdf::loadView('property.processed-returns.master-pdf', array_merge($masterData, [
                    'return' => $return,
                    'signoff' => $signoff,
                ]))->setPaper('A4', 'portrait')->setOption(['enable_font_subsetting' => true]);

                $dompdf = $pdf->getDomPDF();
                $dompdf->render();
                $this->addPdfPageNumbers($dompdf);

                $fileName = 'Return_' . $return->return_code . '_' . date('Y-m-d') . '.pdf';
                $token    = request()->query('download_token');
                $pdfContent = $dompdf->output();

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $fileName . '"');
                header('Cache-Control: max-age=0');
                if ($token) {
                    header('Set-Cookie: download_complete_' . $token . '=1; Path=/; Max-Age=60; SameSite=Lax');
                }
                echo $pdfContent;
                exit;
            }

            $lines = ReturnClient::forReturn($return->return_code);

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

            // Rows in unit order (B2 before B10), whichever entry type introduced the unit
            $tenantRows = $this->sortTenantRowsByUnit($tenantRows);

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

            $landlordName = $return->landlord_name
                ?: DB::table('sys_pm_clients')->where('reference_number', $return->landlord_code)->value('names');
            $propertyName = $return->property_name
                ?: DB::table('sys_pm_properties')->where('reference_number', $return->property_code)->value('property_name');

            $propertyDisplay = $propertyName
                ? trim($propertyName . ' - ' . ($return->property_code ?? ''))
                : ($return->property_code ?? '-');
            $landlordDisplay = $landlordName
                ? trim($landlordName . ' (' . ($return->landlord_code ?? '') . ')')
                : ($return->landlord_code ?? '-');

            $data = compact('return', 'tenantRows', 'totals', 'summary', 'signoff', 'propertyDisplay', 'landlordDisplay');

            $pdf = Pdf::loadView('property.processed-returns.pdf', $data)
                ->setPaper('A4', 'landscape')
                ->setOption(['enable_font_subsetting' => true]);

            // Render first so total page count is accurate, then draw page numbers.
            $dompdf = $pdf->getDomPDF();
            $dompdf->render();
            $this->addPdfPageNumbers($dompdf);

            $fileName = 'Return_' . $return->return_code . '_' . date('Y-m-d') . '.pdf';
            $token    = request()->query('download_token');
            $pdfContent = $dompdf->output();

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

            $allowedTypes = ['MINIRETURN', 'MAINRETURN', 'MASTERRETURN'];
            if (!in_array($return->return_type, $allowedTypes)) {
                abort(403, 'Excel download is only available for MINIRETURN, MAINRETURN and MASTERRETURN returns.');
            }

            if ($return->return_type === 'MASTERRETURN') {
                $masterData = $this->buildMasterReturnExportData($return);

                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Master Return');

                $sheet->setCellValue('A1', 'CHABRIN AGENCIES LIMITED');
                $sheet->mergeCells('A1:C1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                $sheet->setCellValue('A2', 'PROPERTY MANAGING AGENTS');
                $sheet->mergeCells('A2:C2');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                $sheet->setCellValue('A3', '5th Floor, Room 517, NACICO Plaza Landhies Road, PO Box 16659-00620 Nairobi, Kenya');
                $sheet->mergeCells('A3:F3');

                $sheet->setCellValue('A5', 'RETURNS FOR THE MONTH OF ' . strtoupper($masterData['monthLabel']));
                $sheet->mergeCells('A5:C5');
                $sheet->getStyle('A5')->getFont()->setBold(true);

                $sheet->setCellValue('E5', 'Landlord');
                $sheet->setCellValue('F5', $masterData['landlordName']);
                $sheet->setCellValue('E6', 'Property');
                $sheet->setCellValue('F6', $masterData['propertyName']);

                $row = 8;
                $sheet->setCellValue('A' . $row, 'RETURN NET SUM');
                $sheet->mergeCells('A' . $row . ':C' . $row);
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;

                foreach (['A' => 'Narration', 'B' => 'Ref', 'C' => 'Amount'] as $col => $header) {
                    $sheet->setCellValue($col . $row, $header);
                    $sheet->getStyle($col . $row)->getFont()->setBold(true);
                }
                $row++;

                foreach ($masterData['credits'] as $line) {
                    $sheet->setCellValue('A' . $row, $line['narration']);
                    $sheet->setCellValue('B' . $row, $line['ref']);
                    $sheet->setCellValue('C' . $row, $line['amount']);
                    $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $row++;
                }

                $sheet->setCellValue('A' . $row, 'Subtotal');
                $sheet->setCellValue('C' . $row, $masterData['totals']['credits']);
                $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
                $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row += 2;

                $sheet->setCellValue('A' . $row, 'LESS');
                $sheet->mergeCells('A' . $row . ':D' . $row);
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;

                foreach (['A' => 'Date', 'B' => 'Narration', 'C' => 'Ref', 'D' => 'Amount'] as $col => $header) {
                    $sheet->setCellValue($col . $row, $header);
                    $sheet->getStyle($col . $row)->getFont()->setBold(true);
                }
                $row++;

                foreach ($masterData['debits'] as $line) {
                    $sheet->setCellValue('A' . $row, $line['date']);
                    $sheet->setCellValue('B' . $row, $line['narration']);
                    $sheet->setCellValue('C' . $row, $line['ref']);
                    $sheet->setCellValue('D' . $row, $line['amount']);
                    $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $row++;
                }

                $sheet->setCellValue('B' . $row, 'Subtotal');
                $sheet->setCellValue('D' . $row, $masterData['totals']['debits']);
                $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
                $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row += 2;

                $sheet->setCellValue('A' . $row, 'OTHER RECEIPTS FROM LANDLORD ACC');
                $sheet->mergeCells('A' . $row . ':C' . $row);
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;

                foreach (['A' => 'Narration', 'B' => 'Ref', 'C' => 'Amount'] as $col => $header) {
                    $sheet->setCellValue($col . $row, $header);
                    $sheet->getStyle($col . $row)->getFont()->setBold(true);
                }
                $row++;

                foreach ($masterData['otherReceipts'] as $line) {
                    $sheet->setCellValue('A' . $row, $line['narration']);
                    $sheet->setCellValue('B' . $row, $line['ref']);
                    $sheet->setCellValue('C' . $row, $line['amount']);
                    $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $row++;
                }

                $sheet->setCellValue('A' . $row, 'Subtotal');
                $sheet->setCellValue('C' . $row, $masterData['totals']['other_receipts']);
                $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
                $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row += 2;

                $sheet->setCellValue('A' . $row, 'Amount Payable');
                $sheet->setCellValue('D' . $row, $masterData['totals']['amount_payable']);
                $row++;
                $sheet->setCellValue('A' . $row, 'Amount Paid');
                $sheet->setCellValue('D' . $row, $masterData['totals']['amount_paid']);
                $row++;
                $sheet->setCellValue('A' . $row, 'Balance');
                $sheet->setCellValue('D' . $row, $masterData['totals']['balance']);
                $sheet->getStyle('A' . ($row - 2) . ':D' . $row)->getFont()->setBold(true);
                foreach (range($row - 2, $row) as $totRow) {
                    $sheet->getStyle('D' . $totRow)->getNumberFormat()->setFormatCode('#,##0.00');
                }

                $row += 3;
                $signoffs = ['Prepared By', 'Checked By', 'Approved By', 'Client Name'];
                $startCols = ['A', 'C', 'E', 'G'];
                foreach ($signoffs as $index => $title) {
                    $col = $startCols[$index];
                    $sheet->setCellValue($col . $row, $title);
                    $sheet->getStyle($col . $row)->getFont()->setBold(true);
                }

                foreach (range('A', 'G') as $col) {
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
            }

            $lines = ReturnClient::forReturn($return->return_code);

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

    private function buildMasterReturnExportData(ReturnSummary $return): array
    {
        $lines = ReturnClient::where('return_code', $return->return_code)
            ->orderBy('source_date')
            ->orderBy('transaction_date')
            ->orderBy('entry_type')
            ->orderBy('transaction_ref')
            ->orderBy('narration')
            ->get();

        $credits = [];
        $otherReceipts = [];
        $debits = [];
        $totalCredits = 0.0;
        $totalOtherReceipts = 0.0;
        $totalDebits = 0.0;

        foreach ($lines as $line) {
            $amount = (float) ($line->net_amount ?? $line->amount_payable_landlord ?? 0);
            $item = [
                'narration' => $line->narration ?: ($line->entry_type ?: $line->entry_description ?: '-'),
                'ref' => $line->transaction_ref ?: '-',
                'amount' => $amount,
                'date' => $line->source_date
                    ? $line->source_date->format('d-m-Y')
                    : (!empty($line->transaction_date) ? date('d-m-Y', strtotime((string) $line->transaction_date)) : '-'),
                'sort_date' => $line->source_date
                    ? $line->source_date->format('Y-m-d')
                    : (!empty($line->transaction_date) ? date('Y-m-d', strtotime((string) $line->transaction_date)) : '9999-12-31'),
            ];

            $narrationText = strtoupper(trim((string) ($line->narration ?: $line->entry_type ?: $line->entry_description ?: '')));

            if (str_contains($narrationText, 'OPENING AT START OF MONTH')) {
                $otherReceipts[] = $item;
                $totalOtherReceipts += $amount;
                continue;
            }

            if (strtoupper((string) $line->dr_cr) === 'DR') {
                $debits[] = $item;
                $totalDebits += $amount;
            } else {
                $credits[] = $item;
                $totalCredits += $amount;
            }
        }

        usort($debits, function ($left, $right) {
            return strcmp($left['sort_date'], $right['sort_date']);
        });

        $landlordName = $return->landlord_name
            ?: DB::table('sys_pm_clients')->where('reference_number', $return->landlord_code)->value('names');
        $landlordDisplay = $landlordName
            ? trim($landlordName . ' (' . ($return->landlord_code ?? '') . ')')
            : ($return->landlord_code ?? '-');

        $isMaster = ($return->return_type === 'MASTERRETURN');
        if ($isMaster) {
            $childCodes = !empty($return->transaction_ref)
                ? array_filter(array_map('trim', explode(',', $return->transaction_ref)))
                : [];

            $childQuery = DB::table('cb_returns_summary as rs')
                ->leftJoin('sys_pm_properties as p', 'p.reference_number', '=', 'rs.property_code')
                ->where('rs.return_type', 'MAINRETURN');
            if (!empty($childCodes)) {
                $childQuery->whereIn('rs.return_code', $childCodes);
            } else {
                $childQuery->where('rs.landlord_code', $return->landlord_code)
                    ->where('rs.year', $return->year)
                    ->where('rs.month', $return->month);
            }
            $children = $childQuery->select('rs.property_code', 'p.property_name')
                ->orderBy('rs.property_code')
                ->get();

            $propertyDisplay = $children
                ->map(fn($c) => trim(($c->property_name ? $c->property_name . ' - ' : '') . (string) $c->property_code))
                ->filter()
                ->implode(', ') ?: '-';
        } else {
            $propertyName = $return->property_name
                ?: DB::table('sys_pm_properties')->where('reference_number', $return->property_code)->value('property_name');
            $propertyDisplay = $propertyName
                ? trim($propertyName . ' - ' . ($return->property_code ?? ''))
                : ($return->property_code ?? '-');
        }

        return [
            'credits' => $credits,
            'otherReceipts' => $otherReceipts,
            'debits' => $debits,
            'totals' => [
                'credits' => $totalCredits,
                'other_receipts' => $totalOtherReceipts,
                'debits' => $totalDebits,
                'amount_payable' => ($totalCredits + $totalOtherReceipts) - $totalDebits,
                'amount_paid' => (float) ($return->amount_paid ?? 0),
                'balance' => (($totalCredits + $totalOtherReceipts) - $totalDebits) - (float) ($return->amount_paid ?? 0),
            ],
            'landlordName' => $landlordDisplay,
            'propertyName' => $propertyDisplay,
            'monthLabel' => date('F Y', strtotime(sprintf('%04d-%02d-01', (int) $return->year, (int) $return->month))),
        ];
    }

    /**
     * Put the pivoted tenant rows of a return PDF in unit order (B2 before B10); a row with no unit stays first.
     */
    private function sortTenantRowsByUnit(array $tenantRows): array {
        uasort($tenantRows, fn($a, $b) => strnatcasecmp((string) $a['unit_code'], (string) $b['unit_code']) ?: strnatcasecmp((string) $a['tenant_name'], (string) $b['tenant_name']));

        return $tenantRows;
    }

    private function buildReturnSignoffData(ReturnSummary $return): array
    {
        return [
            'prepared' => [
                'name' => $return->prepared_by ?: '-',
                'date' => $this->formatSignoffDate($return->prepared_date),
            ],
            'checked' => [
                'name' => $return->checked_by ?: '-',
                'date' => $this->formatSignoffDate($return->checked_date),
            ],
            'approved' => [
                'name' => $return->approved_by ?: '-',
                'date' => $this->formatSignoffDate($return->approved_date),
            ],
        ];
    }

    private function formatSignoffDate($value): string
    {
        if (empty($value)) {
            return '-';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d-m-Y');
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return '-';
        }

        return date('d-m-Y', $timestamp);
    }
}
