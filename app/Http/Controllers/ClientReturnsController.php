<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use App\Services\ReturnApprovalWorkflowService;
use App\Services\MiniReturnService;
use App\Services\ReturnStatementService;
use App\Services\UtilityReturnsService;
use App\Services\MergedReturnGenerationService;
use App\Services\ReturnListService;
use App\Exports\ReturnStatementExport;
use App\Exceptions\ReturnApprovalException;
use App\Exceptions\MiniReturnException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
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
use App\Traits\PdfPageNumbers;

class ClientReturnsController extends Controller
{
    use PdfPageNumbers;
    use \App\Traits\ChecksMenuAccess;
    use ReturnsRentLinesTrait;
    use ReturnsPayThresholdTrait;
    use ReturnsDeductionsTrait;
    use ReturnsSummaryTrait;
    use ReturnsMasterReturnTrait;
    use Reusables;

    protected ReturnApprovalWorkflowService $returnApprovals;
    protected MiniReturnService $miniReturns;
    protected ReturnStatementService $statements;
    protected ReturnStatementExport $statementExport;
    protected UtilityReturnsService $utilityReturns;

    /**
     * Inject the service that decides who approves a return, the one that generates mini returns, and the statement
     * figures and Excel export.
     */
    public function __construct(ReturnApprovalWorkflowService $returnApprovals, MiniReturnService $miniReturns, ReturnStatementService $statements, ReturnStatementExport $statementExport, UtilityReturnsService $utilityReturns) {
        $this->returnApprovals = $returnApprovals;
        $this->miniReturns = $miniReturns;
        $this->statements = $statements;
        $this->statementExport = $statementExport;
        $this->utilityReturns = $utilityReturns;
    }

    /**
     * Display the unpaid returns listing page
     */
    public function index()
    {
        try {
            $currentYear = (int) request()->input('year', date('Y'));
            $currentMonth = (int) request()->input('month', date('m'));
            $years = range(max((int) date('Y'), $currentYear), min((int) date('Y') - 4, $currentYear));

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
            if ($request->filled('generation')) {
                $query->whereIn('id', $request->session()->get('returns_generation.' . $request->input('generation'), []));
            }
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
                        ->leftJoin('sys_set_zones as spz', 'spz.id', '=', 'spp.zone_id')
                        ->where(fn ($q) => \App\Models\Client::addZoneAccessPredicate($q, (int) $staffId))
                        ->distinct()
                        ->pluck('spp.reference_number')
                        ->all();

                    // Allowed landlord reference_numbers (landlords who own at least one assigned property)
                    $allowedLandlordCodes = DB::table('sys_pm_clients as spc')
                        ->join('sys_pm_properties as spp', 'spp.client_id', '=', 'spc.id')
                        ->leftJoin('sys_set_zones as spz', 'spz.id', '=', 'spp.zone_id')
                        ->where(fn ($q) => \App\Models\Client::addZoneAccessPredicate($q, (int) $staffId))
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

            // Approval levels follow the current workflow. Migrated records remain readable if it is not configured.
            try {
                $levels = $this->returnApprovals->totalLevels();
            } catch (ReturnApprovalException $e) {
                $levels = null;
            }
            $list = app(ReturnListService::class)->present($returns, $allowedPropertyCodes, $levels);
            return response()->json(['success' => true] + app(\App\Services\ReturnListContextService::class)->enrich($list, $levels));
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
     * Say in words why one return line is not payable, or an empty string when it is payable or the line has no
     * payable status (returns loaded from the old system carry none). A rent line is withheld by the 85% rule on the
     * tenant's previous rent invoice; any other line is withheld because the tenant has not paid it yet.
     *
     * @param object $line a cb_returns_clients row
     * @return string
     */
    private function notPayableReason($line): string {
        if ($line->payable_status === null || (int) $line->payable_status !== 0) {
            return '';
        }
        if (($line->entry_type ?? '') !== 'RENT CURRENT') {
            return 'Nothing collected from the tenant yet';
        }

        $percent = rtrim(rtrim(number_format((float) ($line->pay_percentage ?? 0), 2, '.', ''), '0'), '.');
        if ($line->total_amount === null || $line->current_balance === null) {
            return "Previous rent invoice {$percent}% paid; 85% needed";
        }

        $paid = max((float) $line->total_amount - (float) $line->current_balance, 0);

        return 'Previous rent invoice ' . number_format($paid, 2) . ' of ' . number_format((float) $line->total_amount, 2) . " paid ({$percent}%); 85% needed";
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
                ->whereIn('return_type', ['MAINRETURN', 'UTILITYRETURN'])
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
                ;

            if (!empty($childCodes)) {
                // The codes the merged return lists: main returns, or utility returns for a merged utility return
                $childQuery->whereIn('rs.return_code', $childCodes);
            } else {
                // Legacy fallback: no transaction_ref recorded → match by landlord/period
                $childQuery->where('rs.return_type', 'MAINRETURN')
                    ->where('rs.landlord_code', $return->landlord_code)
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
            $heading = date('F Y', mktime(0, 0, 0, (int) $return->month, 1, (int) $return->year)) . ' - Merged Return';
        } else {
            $propertyName = $return->property_name
                ?: DB::table('sys_pm_properties')->where('reference_number', $return->property_code)->value('property_name');
            $propertyLabel = trim((string) ($return->property_code ?? '') . ($propertyName ? ' - ' . $propertyName : ''));
            $heading = $this->propertyReturnExportInfo($return, $propertyName)['title'];
        }

        $period = $isMaster
            ? date('F Y', mktime(0, 0, 0, (int) $return->month, 1, (int) $return->year))
            : str_pad($return->month, 2, '0', STR_PAD_LEFT) . '/' . $return->year;

        return [
            'return_code'    => $return->return_code,
            'heading'        => $heading,
            'property_label' => $propertyLabel !== '' ? $propertyLabel : '-',
            'landlord_label' => $landlordLabel !== '' ? $landlordLabel : '-',
            'period'         => $period,
            'return_type'    => $return->return_type ?? '-',
            'merged'         => $isMaster ? $this->mergedSummary($return) : null,
        ];
    }

    /**
     * The summary of a merged return for its details page: one row per property return with its net and state, what the
     * landlord-level deductions take off, the net payable and the bank account it is paid into.
     *
     * @param ReturnSummary $master
     */
    protected function mergedSummary(ReturnSummary $master): array {
        $service = app(\App\Services\MergedReturnService::class);
        $rows = $service->propertyReturns($master)->map(function ($r) {
            return [
                'property' => trim($r->property_code . ' ' . ($r->property_name ?? '')),
                'return_code' => $r->return_code,
                'net' => (float) $r->net_balance,
                'status' => (int) $r->paid_status === 1 ? 'Paid' : ((int) $r->approved_status === 1 ? 'Approved' : 'Waiting approval'),
                'url' => route('returns.details', encrypt($r->id)),
            ];
        })->all();

        $total = round((float) array_sum(array_column($rows, 'net')), 2);
        $batch = $service->batchOf($master);

        return [
            'properties' => $rows,
            'total' => $total,
            'deductions' => round($total - (float) $master->net_balance, 2),
            'net' => (float) $master->net_balance,
            'bank' => $service->payeeBank($master),
            'batch_reference' => $batch->batch_reference ?? null,
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

            $lineTotals = $this->statements->lineTotals($return->return_code);

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

            $lineTotals = $this->statements->lineTotals($return->return_code);

            $headerInfo = $this->buildReturnHeaderInfo($return);

            return response()->json([
                'success' => true,
                'return' => [
                    'id' => encrypt($return->id),
                    'return_code' => $return->return_code,
                    'heading' => $headerInfo['heading'],
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
                'statement_html' => $this->statementHtml($return, $details, false),
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
                        'payable_status_reason' => $d->payable_status_reason ?: $this->notPayableReason($d),
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
        return $this->downloadPdf($id);
    }

    public function downloadUnprocessedExcel($id)
    {
        return $this->downloadExcel($id);
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
                ->when(!app(\App\Services\ReturnAccessService::class)->canApproveDirectly((int) Auth::id()), fn ($q) =>
                    $q->whereIn('field_officer_id', DB::table('sys_user_staff_links')->where('user_id', Auth::id())->select('staff_id')))
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
        if (!app(\App\Services\ReturnAccessService::class)->canGenerate((int) Auth::id())) {
            return $this->denied();
        }
        try {
            return view('property.returns.generate-returns');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading returns generation page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load returns generation page');
        }
    }

    /** Read-only review of a configured batch for the selected month. */
    public function mergeReadiness(Request $request)
    {
        if (!app(\App\Services\ReturnAccessService::class)->canGenerate((int) Auth::id()) || !$this->menuAllowed('/returns/generate')) {
            return $this->denied('json');
        }
        $values = $request->validate([
            'returns_month' => 'required|date_format:Y-m',
            'property_id' => 'required|integer|exists:sys_pm_properties,id',
        ]);
        try {
            $batch = $this->mergedGenerationBatch((int) $values['property_id']);
            $state = app(MergedReturnGenerationService::class)
                ->readiness($batch['batch_id'], str_replace('-', '', $values['returns_month']));
            $this->assertMergeGenerationAccess(array_column($state['properties'], 'property_id'));
            return response()->json(['success' => true] + $state);
        } catch (ReturnApprovalException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function mergedGenerationBatch(?int $propertyId): array
    {
        if (!$propertyId || !($batch = $this->getMergeBatchInfo($propertyId))) {
            throw new ReturnApprovalException('Choose a property belonging to a configured merge batch.');
        }
        $this->assertMergeGenerationAccess($batch['property_ids']);
        return $batch;
    }

    private function assertMergeGenerationAccess(array $propertyIds): void
    {
        foreach ($propertyIds as $id) {
            if ($this->generationRefusal((int) $id) !== null) {
                throw new ReturnApprovalException('You must have access to every property in the batch to generate its merged return. Ask the chief accountant or a director to generate it.');
            }
        }
    }

    private function generateMergedReturn(Request $request, ?int $propertyId, string $period, int $userId)
    {
        $batch = $this->mergedGenerationBatch($propertyId);
        $ids = DB::transaction(function () use ($batch, $request, $propertyId, $period, $userId) {
            DB::table('cb_returns_merge_batches')->where('id', $batch['batch_id'])->lockForUpdate()->first();
            // Recheck every member's access while batch edits are excluded, before generating anything.
            $lockedBatch = $this->mergedGenerationBatch($propertyId);
            return app(MergedReturnGenerationService::class)->generate($lockedBatch['batch_id'], $period, $userId, $request->input('preview_token'));
        });
        return response()->json([
            'success' => true,
            'message' => count($ids) . ' merged return(s) generated from approved individual returns. The payment voucher still follows its approval process.',
            'master_count' => count($ids),
            'redirect_url' => $this->generationRedirectUrl($ids, (int) substr($period, 0, 4), (int) substr($period, 4, 2)),
        ]);
    }

    /**
     * Process returns generation using modular traits.
     * 
     * Flow:
     * 1) Insert rent CR lines from invoices (ReturnsRentLinesTrait)
     * 2) Update pay threshold percentages (ReturnsPayThresholdTrait)
     * 3) Insert deductions: advances, recoveries, collections (ReturnsDeductionsTrait)
     * 4) Insert MAINRETURN summaries (ReturnsSummaryTrait)
     * Merged returns are generated separately, only after individual final approvals.
     * 6) Mark landlord account entries as recovered (ReturnsDeductionsTrait)
     * 7) Leave drafts for the field officer to forward for approval
     * 
     * Supports optional property_id for single-property generation.
     */
    public function processGenerate(Request $request)
    {
        $generationTransactionStarted = false;
        try {
            $request->validate([
                'returns_month' => 'required|date_format:Y-m',
                'property_id'   => 'required_if:return_type,merged|nullable|integer|exists:sys_pm_properties,id',
                'return_type'   => 'nullable|in:main,mini,merged',
                'preview_token' => 'required_if:return_type,merged|string|size:64'
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

            // A field officer generates for one property of their own zone; a director or chief accountant for any property
            $generationRefusal = $this->generationRefusal($requestedPropertyId);
            if ($generationRefusal !== null) {
                return response()->json(['success' => false, 'message' => $generationRefusal], 403);
            }

            if ($request->input('return_type') === 'merged') {
                return $this->generateMergedReturn($request, $requestedPropertyId, $returnPeriod, (int) $generatedBy);
            }

            // The approvers come from the 'returns_approval' workflow: do not generate returns nobody can approve
            try {
                $this->returnApprovals->assertConfigured();
            } catch (ReturnApprovalException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            // A mini return carries only the money that came in late for one property
            if ($request->input('return_type') === 'mini') {
                return $this->generateMiniReturn($requestedPropertyId, $returnsMonth, (int) $generatedBy);
            }

            // Individual generation never expands to the other properties of a configured merge batch.
            $propertyIds = $requestedPropertyId ? [$requestedPropertyId] : Property::query()->pluck('id')->all();

            DB::beginTransaction();
            $generationTransactionStarted = true;

            // ═══════════════════════════════════════════════════════════════════
            // VALIDATION: Check for approved invoices
            // ═══════════════════════════════════════════════════════════════════
            // Lock before checking approval so concurrent requests cannot rebuild a return being approved.
            $propertyIds = DB::table('sys_pm_properties')->whereIn('id', $propertyIds)->orderBy('id')
                ->lockForUpdate()->pluck('id')->all();
            $protectedProperties = [];
            foreach ($propertyIds as $pid) {
                ReturnSummary::where('return_period', $returnPeriod)
                    ->where('property_code', DB::table('sys_pm_properties')->where('id', $pid)->value('reference_number'))
                    ->orderBy('id')->lockForUpdate()->get();
                if ($this->approvedReturnsExistForPeriod($returnPeriod, $pid)) {
                    if ($requestedPropertyId) {
                        DB::rollBack();
                        return response()->json(['success' => false, 'message' => 'This property already has approved, paid or merged returns for this period and cannot be regenerated.'], 422);
                    }
                    $protectedProperties[] = $pid;
                }
            }
            $propertyIds = array_values(array_diff($propertyIds, $protectedProperties));
            $totalInvoices = 0;
            foreach ($propertyIds as $pid) {
                $totalInvoices += $this->countApprovedInvoices($monthStart, $pid);
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
                'property_ids'     => $propertyIds ?: null,
            ]);

            // ═══════════════════════════════════════════════════════════════════
            // VALIDATION & DELETE: Check approved returns and delete unapproved
            // ═══════════════════════════════════════════════════════════════════
            foreach ($propertyIds as $pid) {
                $this->deleteUnapprovedReturnsForPeriod($returnPeriod, $pid);
            }

            // ═══════════════════════════════════════════════════════════════════
            // GENERATION: Process only the selected, unapproved individual properties
            // ═══════════════════════════════════════════════════════════════════
            $rentInserted = 0;
            $advancesInserted = 0;
            $recoveriesInserted = 0;
            $collectionsInserted = 0;
            $commissionsInserted = 0;
            $summaryCount = 0;

            $propertiesToProcess = $propertyIds;

            foreach ($propertiesToProcess as $propertyId) {
                $baseReturnId = $this->getNextReturnIdBase();
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

                // A client whose properties are merged: the debits tied to this property (its advances) go on this
                // property's return, the rest on the merged return
                $advancesInserted += $this->insertAdvancePaymentLines(
                    $returnPeriod,
                    $transactionDate,
                    $generatedBy,
                    $returnYear,
                    $returnMonthNum,
                    $propertyId,
                    null,
                    true
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

                // STEP 7: the returns stay drafts. They go under approval only when the field officer forwards them to the manager.

                Log::info("Processed property", [
                    'property_id' => $propertyId
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

            // Merging is a separate action after all required individual returns have final approval.
            $masterReturnCount = 0;

            $generatedReturns = ReturnSummary::where('return_period', $returnPeriod)
                ->whereIn('return_type', ['MAINRETURN', 'UTILITYRETURN'])
                ->whereIn('property_code', DB::table('sys_pm_properties')->whereIn('id', $propertyIds)->select('reference_number'))
                ->get(['id', 'parent_return_code']);
            $generatedIds = $generatedReturns->pluck('id')->all();
            $masterIds = ReturnSummary::whereIn('return_code', $generatedReturns->pluck('parent_return_code')->filter()->unique())
                ->pluck('id')->all();
            $generatedIds = array_values(array_unique(array_merge($generatedIds, $masterIds)));

            DB::commit();
            $generationTransactionStarted = false;

            Log::info("=== RETURN GENERATION COMPLETE ===", [
                'rent_lines'       => $rentInserted,
                'advance_lines'    => $advancesInserted,
                'recovery_lines'   => $recoveriesInserted,
                'collection_lines' => $collectionsInserted,
                'main_returns'     => $summaryCount,
                'master_returns'   => $masterReturnCount,
                'returns_month'    => $returnsMonth,
                'requested_property' => $requestedPropertyId,
                'properties_processed' => count($propertiesToProcess)
            ]);

            // Build response message
            if ($requestedPropertyId) {
                $propLabel = " for property ID {$requestedPropertyId}";
            } else {
                $propLabel = "";
            }

            $message = "Client returns generated successfully{$propLabel}: {$summaryCount} MAINRETURN(s)";
            $message .= " ({$rentInserted} rent + {$advancesInserted} advance + {$recoveriesInserted} recovery + {$collectionsInserted} collection lines). They are drafts: forward them to the manager from the Returns List.";

            return response()->json([
                'success'            => true,
                'redirect_url'       => $this->generationRedirectUrl($generatedIds, $returnYear, $returnMonthNum),
                'message'            => $message,
                'count'              => $rentInserted,
                'properties_count'   => $summaryCount,
                'master_count'       => $masterReturnCount,
                'property_id'        => $requestedPropertyId,
                'merge_batch'        => null
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (ReturnApprovalException $e) {
            // A zone with no manager login (or an incomplete workflow): nothing is generated, and the message says what to fix
            if ($generationTransactionStarted) {
                DB::rollBack();
            }

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            if ($generationTransactionStarted) {
                DB::rollBack();
            }
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
     * Generate a mini return for one property and month (the money that came in late and is on no return yet) and
     * answer the generate screen.
     *
     * @param int|null $propertyId the property chosen on the screen
     * @param string $returnsMonth YYYY-MM
     * @param int $userId the user generating it
     */
    protected function generateMiniReturn(?int $propertyId, string $returnsMonth, int $userId) {
        if (!$propertyId) {
            return response()->json(['success' => false, 'message' => 'A mini return is for one property: choose the property.'], 422);
        }

        try {
            $result = $this->miniReturns->generate($propertyId, $returnsMonth, $userId, request()->ip());
        } catch (MiniReturnException|ReturnApprovalException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating mini return: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to generate the mini return: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => (count($result['return_codes']) > 1 ? 'Mini returns ' : 'Mini return ') . implode(' and ', $result['return_codes']) . ' generated as a draft. Forward it to the manager when it is ready: ' . $result['lines'] . ' line(s), net ' . number_format($result['net_balance'], 2) . '.',
            'count' => 1,
            'properties_count' => 1,
            'master_count' => 0,
            'return_code' => $result['return_code'],
            'redirect_url' => route('returns.details', encrypt(ReturnSummary::where('return_code', $result['return_code'])->sole()->id)),
            'property_id' => $propertyId,
            'merge_batch' => null,
        ]);
    }

    protected function generationRedirectUrl(array $returnIds, int $year, int $month): string
    {
        if (count($returnIds) === 1) {
            return route('returns.details', encrypt($returnIds[0]));
        }

        $generation = (string) \Illuminate\Support\Str::uuid();
        request()->session()->put('returns_generation.' . $generation, $returnIds);

        return route('returns.batches.index', ['year' => $year, 'month' => $month, 'generation' => $generation]);
    }

    /**
     * Why the user may not generate returns for the property asked for, or null when they may. A user with a global role
     * (director, accountant) generates for any property; everyone else, a field officer, for one property they can see,
     * which is one of their own zone.
     *
     * @param int|null $propertyId the one property asked for, or null for every property
     */
    protected function generationRefusal(?int $propertyId): ?string {
        return app(\App\Services\ReturnAccessService::class)->generationRefusal((int) Auth::id(), $propertyId);
    }

    /**
     * Forward one return to the manager (AJAX): it goes under approval, at level 1, only now. Needs the Generate Returns
     * menu, and a user limited to a zone can forward only their own zone's returns.
     */
    public function forwardReturn($encryptedId)
    {
        if (!app(\App\Services\ReturnAccessService::class)->canForward((int) Auth::id()) || !$this->menuAllowed('/returns/generate')) {
            return $this->denied('json');
        }

        try {
            [$ok, $message] = $this->forwardOne((int) decrypt($encryptedId));

            return response()->json(['success' => $ok, 'message' => $message], $ok ? 200 : 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error forwarding a return: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to forward the return'], 500);
        }
    }

    /**
     * Forward several returns at once (AJAX, ids[] of encrypted return ids), each checked on its own. The answer says how
     * many went and why the others did not.
     */
    public function forwardReturns(Request $request)
    {
        if (!app(\App\Services\ReturnAccessService::class)->canForward((int) Auth::id()) || !$this->menuAllowed('/returns/generate')) {
            return $this->denied('json');
        }

        $ids = array_slice((array) $request->input('ids', []), 0, 500);
        $forwarded = 0;
        $refused = [];

        $alreadyForwarded = [];

        foreach ($ids as $encrypted) {
            // The rent or utility twin of a return forwarded a moment ago in this same request is already on its way
            if (in_array((int) decrypt($encrypted), $alreadyForwarded, true)) {
                continue;
            }

            try {
                [$ok, $message, $done] = array_pad($this->forwardOne((int) decrypt($encrypted)), 3, []);
                $alreadyForwarded = array_merge($alreadyForwarded, $done);
            } catch (Exception $e) {
                $ok = false;
                $message = 'Could not forward one return.';
                Log::error('Error forwarding a return: ' . $e->getMessage());
            }
            $ok ? $forwarded++ : $refused[$message] = ($refused[$message] ?? 0) + 1;
        }

        return response()->json([
            'success' => $forwarded > 0,
            'forwarded' => $forwarded,
            'refused' => $refused,
            'message' => $forwarded . ' return(s) forwarded to the manager.' . ($refused ? ' Not forwarded: ' . implode('; ', array_map(fn($m, $n) => "{$n} x {$m}", array_keys($refused), $refused)) : ''),
        ], $forwarded > 0 ? 200 : 422);
    }

    /**
     * Forward one return for the signed-in user.
     *
     * @param int $returnId
     * @return array [bool done, string message, int[] ids of every return forwarded (the return and its rent or utility twin)]
     */
    protected function forwardOne(int $returnId): array
    {
        $return = DB::table('cb_returns_summary')->where('id', $returnId)->first();
        if (!$return) {
            return [false, 'Return not found.'];
        }

        $propertyId = (int) DB::table('sys_pm_properties')->where('reference_number', $return->property_code)->value('id');
        $refusal = $this->returnApprovals->forwardRefusal($return) ?? ($propertyId ? $this->returnApprovals->zoneRefusal($propertyId) : null);
        if ($refusal) {
            return [false, $refusal];
        }

        try {
            DB::transaction(fn () => $this->returnApprovals->forward($return, (int) Auth::id(), request()->ip()));
        } catch (ReturnApprovalException $e) {
            return [false, $e->getMessage()];
        }

        // A client with a utility return has two returns for the month (rent and utility): they go to the manager together
        $forwarded = [$return->return_code];
        $forwardedIds = [(int) $return->id];
        if (in_array($return->return_type, ['MAINRETURN', 'UTILITYRETURN'], true)) {
            $siblings = DB::table('cb_returns_summary')
                ->where('return_period', $return->return_period)
                ->where('property_code', $return->property_code)
                ->where('id', '!=', $return->id)
                ->whereIn('return_type', ['MAINRETURN', 'UTILITYRETURN'])
                ->get();

            foreach ($siblings as $sibling) {
                if ($this->returnApprovals->forwardRefusal($sibling) !== null) {
                    continue;
                }
                try {
                    DB::transaction(fn () => $this->returnApprovals->forward($sibling, (int) Auth::id(), request()->ip()));
                    $forwarded[] = $sibling->return_code;
                    $forwardedIds[] = (int) $sibling->id;
                } catch (ReturnApprovalException $e) {
                    Log::warning('A sibling return was not forwarded: ' . $e->getMessage());
                }
            }
        }

        return [true, 'Return ' . implode(' and ', $forwarded) . ' forwarded to the manager.', $forwardedIds];
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

            $lineTotals = $this->statements->lineTotals($return->return_code);

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

            $lineTotals = $this->statements->lineTotals($return->return_code);

            $headerInfo = $this->buildReturnHeaderInfo($return);

            return response()->json([
                'success' => true,
                'return' => [
                    'id' => encrypt($return->id),
                    'return_code' => $return->return_code,
                    'heading' => $headerInfo['heading'],
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
                'statement_html' => $this->statementHtml($return, $lines, true),
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

            $allowedTypes = ['MINIRETURN', 'MAINRETURN', 'UTILITYRETURN', 'MASTERRETURN'];
            if (!in_array($return->return_type, $allowedTypes)) {
                abort(403, 'PDF download is only available for main, mini, utility and merged returns.');
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

            // The same figures as the details page and the Excel file; a client who wants two reports can ask for one side
            $part = $this->requestedStatementPart($return);
            $statement = $this->buildTenantStatementData($return, $lines, $part);
            $tenantRows = $statement['tenantRows'];
            $totals = $statement['totals'];
            $summary = $statement['summary'];
            $summaryRows = $statement['summaryRows'];
            $settlementRows = $statement['settlementRows'];
            $arrears = $statement['arrears'];

            $landlordName = $return->landlord_name
                ?: DB::table('sys_pm_clients')->where('reference_number', $return->landlord_code)->value('names');
            $propertyName = $return->property_name
                ?: DB::table('sys_pm_properties')->where('reference_number', $return->property_code)->value('property_name');
            $exportInfo = $this->propertyReturnExportInfo($return, $propertyName, $part);

            $propertyDisplay = $propertyName
                ? trim($propertyName . ' - ' . ($return->property_code ?? ''))
                : ($return->property_code ?? '-');
            $landlordDisplay = $landlordName
                ? trim($landlordName . ' (' . ($return->landlord_code ?? '') . ')')
                : ($return->landlord_code ?? '-');

            $columns = $statement['columns'];
            $data = compact('return', 'tenantRows', 'totals', 'summary', 'summaryRows', 'settlementRows', 'arrears', 'signoff', 'propertyDisplay', 'landlordDisplay', 'exportInfo', 'part', 'columns');

            $pdf = Pdf::loadView($part === ReturnStatementService::PART_UTILITY ? 'property.processed-returns.utility-pdf' : 'property.processed-returns.pdf', $data)
                ->setPaper('A4', 'landscape')
                ->setOption(['enable_font_subsetting' => true]);

            // Render first so total page count is accurate, then draw page numbers.
            $dompdf = $pdf->getDomPDF();
            $dompdf->render();
            $this->addPdfPageNumbers($dompdf);

            $token    = request()->query('download_token');
            $pdfContent = $dompdf->output();

            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . $this->returnExportDisposition($exportInfo['filename_stem'] . '.pdf'));
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

            $allowedTypes = ['MINIRETURN', 'MAINRETURN', 'UTILITYRETURN', 'MASTERRETURN'];
            if (!in_array($return->return_type, $allowedTypes)) {
                abort(403, 'Excel download is only available for main, mini, utility and merged returns.');
            }

            if ($return->return_type === 'MASTERRETURN') {
                $masterData = $this->buildMasterReturnExportData($return);

                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $isUtilityMaster = str_starts_with((string) $return->return_code, 'RU');
                $sheet->setTitle($isUtilityMaster ? 'Master Utility Return' : 'Master Return');

                $sheet->setCellValue('A1', 'CHABRIN AGENCIES LIMITED');
                $sheet->mergeCells('A1:C1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                $sheet->setCellValue('A2', 'PROPERTY MANAGING AGENTS');
                $sheet->mergeCells('A2:C2');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                $sheet->setCellValue('A3', '5th Floor, Room 517, NACICO Plaza Landhies Road, PO Box 16659-00620 Nairobi, Kenya');
                $sheet->mergeCells('A3:F3');

                $sheet->setCellValue('A5', 'RETURNS FOR THE MONTH OF ' . strtoupper($masterData['monthLabel']) . ($isUtilityMaster ? ' - UTILITIES' : ''));
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
            $propertyName = $return->property_name
                ?: DB::table('sys_pm_properties')->where('reference_number', $return->property_code)->value('property_name');
            $part = $this->requestedStatementPart($return);
            $exportInfo = $this->propertyReturnExportInfo($return, $propertyName, $part);
            $header = $this->buildReturnHeaderInfo($return);
            $header['title'] = $exportInfo['title'];
            $statementData = $this->buildTenantStatementData($return, $lines, $part);
            $spreadsheet = $part === ReturnStatementService::PART_UTILITY
                ? $this->statementExport->buildUtility($return, $statementData, $header, $this->buildReturnSignoffData($return))
                : $this->statementExport->build($return, $statementData, $header, $this->buildReturnSignoffData($return));

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: ' . $this->returnExportDisposition($exportInfo['filename_stem'] . '.xlsx'));
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

    /** @return array{title:string,type:string,filename_stem:string} */
    private function propertyReturnExportInfo(ReturnSummary $return, ?string $propertyName, string $part = ReturnStatementService::PART_ALL): array
    {
        $period = date('F Y', mktime(0, 0, 0, (int) $return->month, 1, (int) $return->year));
        $type = 'Main Return';
        if ($return->return_type === 'UTILITYRETURN') {
            $type = 'Utility Return';
        }
        if ($return->return_type === 'MINIRETURN') {
            $type = str_starts_with($return->return_code, 'RU') ? 'Utility Mini Return' : 'Mini Return';
            if (preg_match('/^RU?([2-9])/i', $return->return_code, $matches)) {
                $type .= ' ' . ((int) $matches[1] - MiniReturnService::FIRST_SEQUENCE + 1);
            } else {
                $type .= ' ' . $return->id;
            }
        }

        // A client who wants two reports gets a rent report and a utility report of the same return
        if ($part === ReturnStatementService::PART_RENT) {
            $type .= ' - Rent Report';
        } elseif ($part === ReturnStatementService::PART_UTILITY) {
            $type .= ' - Utility Report';
        }

        $property = trim($propertyName ?? '') ?: ($return->property_code ?: 'Property ' . $return->id);
        $filenameProperty = str_replace(['<', '>', ':', '"', '/', '\\', '|', '?', '*'], ' ', $property);
        $filenameProperty = preg_replace('/[\s\p{Cc}]+/u', ' ', $filenameProperty);
        $filenameProperty = trim(mb_strcut($filenameProperty, 0, 180, 'UTF-8'), ' .') ?: 'Property ' . $return->id;

        return [
            'title' => "{$property} - {$period} - {$type}",
            'type' => $type,
            'filename_stem' => "{$filenameProperty} - {$period} - {$type}",
        ];
    }

    private function returnExportDisposition(string $filename): string
    {
        return HeaderUtils::makeDisposition('attachment', $filename, str_replace('%', '', Str::ascii($filename)));
    }

    /**
     * Everything the statement shows for a return (rows, totals, summary, blocks I to O, arrears), from its lines.
     */
    private function buildTenantStatementData(ReturnSummary $return, $lines, string $part = ReturnStatementService::PART_ALL): array
    {
        return $this->statements->statement($return, $lines, null, $part);
    }

    /**
     * The part of the statement a download asks for (?part=rent or ?part=utility), only for a client who wants two
     * reports; anything else is the whole return.
     */
    private function requestedStatementPart(ReturnSummary $return): string
    {
        $part = request()->query('part');

        if (in_array($part, [ReturnStatementService::PART_RENT, ReturnStatementService::PART_UTILITY], true)
            && $return->return_type !== 'MASTERRETURN'
            && !str_starts_with((string) $return->return_code, 'RU')
            && $this->utilityReturns->modeForReturn($return) === UtilityReturnsService::SEPARATE_REPORT) {
            return $part;
        }

        return ReturnStatementService::PART_ALL;
    }

    /**
     * The statement shown on the return details pages: one statement, or for a client who wants two reports a rent
     * report and a utility report in tabs. A merged return has none.
     *
     * @param bool $processed whether the page is the processed returns one (its downloads have their own routes)
     */
    private function statementHtml(ReturnSummary $return, $lines, bool $processed): string
    {
        if ($return->return_type === 'MASTERRETURN') {
            return '';
        }

        // A utility return is already the utility side: it has no second report
        if (!str_starts_with((string) $return->return_code, 'RU') && $this->utilityReturns->modeForReturn($return) === UtilityReturnsService::SEPARATE_REPORT) {
            return view('property.processed-returns.partials.statement-parts', [
                'return' => $return,
                'rentStatement' => $this->buildTenantStatementData($return, $lines, ReturnStatementService::PART_RENT),
                'utilityStatement' => $this->buildTenantStatementData($return, $lines, ReturnStatementService::PART_UTILITY),
                'pdfUrl' => route($processed ? 'processed.returns.pdf' : 'returns.pdf', encrypt($return->id)),
                'excelUrl' => route($processed ? 'processed.returns.excel' : 'returns.excel', encrypt($return->id)),
            ])->render();
        }

        return view('property.processed-returns.partials.statement', array_merge(
            $this->buildTenantStatementData($return, $lines), ['return' => $return]
        ))->render();
    }

    /** Edit a tenant's statement note without changing the return's financial or approval state. */
    public function saveStatementRemarks(Request $request, $id, $lineId)
    {
        try {
            $returnId = decrypt($id);
            $returnLineId = decrypt($lineId);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            abort(404);
        }
        $return = ReturnSummary::findOrFail($returnId);
        $userId = (int) auth()->id();
        abort_unless($userId > 0, 403);
        if (!$this->checkGlobalRole($userId)) {
            $assigned = DB::table('sys_pm_properties as p')
                ->join('sys_pm_zones_assignments as a', 'a.property_id', '=', 'p.id')
                ->join('sys_user_staff_links as u', 'u.staff_id', '=', 'a.staff_id')
                ->where('u.user_id', $userId)->where('a.current_status_id', 1)
                ->where('p.reference_number', $return->property_code)->exists();
            abort_unless($assigned, 403, 'You may only edit remarks for your assigned properties.');
        }
        $values = $request->validate([
            'remarks' => 'nullable|string|max:' . ReturnStatementService::REMARKS_LIMIT,
            'expected_remarks' => 'present|nullable|string',
        ]);
        return DB::transaction(function () use ($return, $returnLineId, $values) {
            $line = ReturnClient::where('return_code', $return->return_code)->whereKey($returnLineId)->lockForUpdate()->firstOrFail();
            if (trim((string) ($line->remarks ?? '')) !== trim((string) ($values['expected_remarks'] ?? ''))) {
                return response()->json(['message' => 'These remarks changed since you opened the statement. Reload the return before saving.'], 409);
            }
            $line->remarks = $values['remarks'] ?? '';
            $line->save();
            return response()->json(['success' => true, 'remarks' => $line->remarks]);
        });
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

            if (str_starts_with(strtoupper(trim((string) $line->entry_type)), 'OTHER RECEIPTS') || str_contains($narrationText, 'OPENING AT START OF MONTH')) {
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
                ->leftJoin('sys_pm_properties as p', 'p.reference_number', '=', 'rs.property_code');
            if (!empty($childCodes)) {
                $childQuery->whereIn('rs.return_code', $childCodes);
            } else {
                $childQuery->where('rs.return_type', 'MAINRETURN')
                    ->where('rs.landlord_code', $return->landlord_code)
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
