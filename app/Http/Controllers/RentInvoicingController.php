<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\InvoiceableItem;
use App\Models\LeaseAgreement;
use App\Models\Property;
use App\Models\TempGeneratedInvoice;
use App\Traits\InvoiceCreationTrait;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rent Invoicing sub-tab of the client page's Invoicing tab. Two-stage flow,
 * same shape as UnitBulkUploadController:
 *
 *   1. generate() — computes prorated rent for eligible Active leases and
 *      stages one row per lease into temp_generated_invoices. Nothing is
 *      posted to the real tables yet.
 *   2. pushToMain() — every staged, not-yet-pushed row for a property is
 *      turned into a real invoice via InvoiceCreationTrait::createSingleDebtorInvoice()
 *      — the exact same commission/VAT/payable_amount math and ledger DR
 *      mirror used by InvoicingController::storeInvoiceItem() and the
 *      whole-portfolio bulk generator. One bad row doesn't block the rest.
 */
class RentInvoicingController extends Controller
{
    use InvoiceCreationTrait;

    /**
     * Stage prorated (or full) rent invoices for a property/month. A lease is
     * skipped (not staged, reported separately) if it already has an
     * unpushed staged row or a real RENT invoice for that month.
     */
    public function generate(Request $request)
    {
        try {
            $validated = $request->validate([
                'property_id' => 'required|integer|exists:sys_pm_properties,id',
                'billing_month' => 'required|date_format:Y-m',
                'due_date' => 'required|date',
                'scope' => 'required|in:all,selected',
                'tenant_ids' => 'required_if:scope,selected|array',
                'tenant_ids.*' => 'integer',
                'date_mode' => 'required|in:lease_start,selected_date',
                'selected_date' => 'required_if:date_mode,selected_date|nullable|date',
            ]);

            $property = Property::findOrFail($validated['property_id']);
            $monthStart = new \DateTime($validated['billing_month'] . '-01');
            $monthDays = (int) $monthStart->format('t');
            $returnsPeriod = $monthStart->format('Y-m-d');

            $rentItemId = InvoiceableItem::where('billing_code', '0024')->value('id');

            $query = LeaseAgreement::query()
                ->join('sys_pm_units', 'sys_pm_units.id', '=', 'sys_pm_lease_agreements.unit_id')
                ->where('sys_pm_lease_agreements.property_id', $property->id)
                ->where('sys_pm_lease_agreements.lease_status', 'Active')
                ->where('sys_pm_units.rent_amount', '>', 0)
                ->select('sys_pm_lease_agreements.*', 'sys_pm_units.rent_amount as unit_rent_amount', 'sys_pm_units.unit_code as unit_code_col');

            if ($validated['scope'] === 'selected') {
                if (empty($validated['tenant_ids'])) {
                    return response()->json(['success' => false, 'message' => 'Select at least one tenant.'], 422);
                }
                $query->whereIn('sys_pm_lease_agreements.tenant_id', $validated['tenant_ids']);
            }

            $leases = $query->get();

            if ($leases->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No active leases with rent found for this selection.'], 422);
            }

            $staged = 0;
            $skipped = 0;

            foreach ($leases as $lease) {
                $alreadyStaged = TempGeneratedInvoice::where('lease_id', $lease->id)
                    ->where('returns_period', $returnsPeriod)
                    ->where('pushed_to_main', false)
                    ->exists();
                if ($alreadyStaged) {
                    $skipped++;
                    continue;
                }

                if ($rentItemId) {
                    $alreadyInvoiced = DB::table('sys_pm_debtors_invoices')
                        ->where('lease_id', $lease->id)
                        ->where('item_type_id', $rentItemId)
                        ->whereYear('returns_period', $monthStart->format('Y'))
                        ->whereMonth('returns_period', $monthStart->format('m'))
                        ->exists();
                    if ($alreadyInvoiced) {
                        $skipped++;
                        continue;
                    }
                }

                $rentAmount = (float) $lease->unit_rent_amount;
                [$proratedAmount, $prorationDays] = $this->prorateRent($rentAmount, $monthDays, $validated['date_mode'], $lease->date_start, $validated['selected_date'] ?? null, $validated['billing_month']);

                $tenant = Client::find($lease->tenant_id);

                TempGeneratedInvoice::create([
                    'property_id' => $property->id,
                    'lease_id' => $lease->id,
                    'tenant_id' => $lease->tenant_id,
                    'unit_id' => $lease->unit_id,
                    'tenant_name' => $tenant->names ?? null,
                    'unit_code' => $lease->unit_code_col,
                    'base_rent' => $rentAmount,
                    'prorated_amount' => $proratedAmount,
                    'proration_days' => $prorationDays,
                    'month_days' => $monthDays,
                    'returns_period' => $returnsPeriod,
                    'date_due' => $validated['due_date'],
                    'created_by' => Auth::id(),
                ]);
                $staged++;
            }

            $message = "Staged {$staged} invoice(s) for review.";
            if ($skipped > 0) {
                $message .= " {$skipped} lease(s) skipped (already staged or already invoiced this month).";
            }

            return response()->json(['success' => true, 'message' => $message, 'staged' => $staged, 'skipped' => $skipped]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating staged rent invoices: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to generate invoices: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Rent ÷ days-in-month × remaining-days, where "remaining days" runs
     * from the effective start date (inclusive) to the end of the billing
     * month. lease_start mode only prorates when the lease's date_start
     * actually falls inside the target billing month — a lease that started
     * earlier is charged full rent. selected_date mode always prorates from
     * that one date, applied uniformly to every lease in this run.
     *
     * @return array{0: float, 1: ?int} [prorated_amount, proration_days_or_null]
     */
    private function prorateRent(float $rentAmount, int $monthDays, string $dateMode, $leaseDateStart, ?string $selectedDate, string $billingMonth): array
    {
        if ($dateMode === 'selected_date') {
            $date = new \DateTime($selectedDate);
            $day = (int) $date->format('j');
            $remainingDays = max(0, $monthDays - $day + 1);
            return [round($rentAmount / $monthDays * $remainingDays, 2), $remainingDays];
        }

        // lease_start mode
        if ($leaseDateStart && $leaseDateStart->format('Y-m') === $billingMonth) {
            $day = (int) $leaseDateStart->format('j');
            $remainingDays = max(0, $monthDays - $day + 1);
            return [round($rentAmount / $monthDays * $remainingDays, 2), $remainingDays];
        }

        return [$rentAmount, null];
    }

    /**
     * Not-yet-pushed staged rows for a property (AJAX, for the Rent
     * Invoicing staging DataTable). Optionally narrowed to a billing month.
     */
    public function stagedData(Request $request)
    {
        try {
            $request->validate([
                'property_id' => 'required|integer|exists:sys_pm_properties,id',
                'billing_month' => 'nullable|date_format:Y-m',
            ]);

            $query = TempGeneratedInvoice::where('property_id', $request->property_id)
                ->where('pushed_to_main', false);

            if ($request->filled('billing_month')) {
                $query->where('returns_period', $request->billing_month . '-01');
            }

            $rows = $query->orderBy('id')->get();

            return response()->json(['data' => $rows]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading staged rent invoices: ' . $e->getMessage());
            return response()->json(['data' => [], 'message' => 'Failed to load staged invoices'], 500);
        }
    }

    public function destroyStaged($id)
    {
        try {
            $row = TempGeneratedInvoice::findOrFail($id);

            if ($row->pushed_to_main) {
                return response()->json(['success' => false, 'message' => 'This row has already been pushed and can no longer be deleted here.'], 422);
            }

            $row->delete();

            return response()->json(['success' => true, 'message' => 'Row deleted.']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting staged rent invoice: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete row'], 500);
        }
    }

    public function destroySelectedStaged(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer',
            ]);

            $deleted = TempGeneratedInvoice::whereIn('id', $validated['ids'])
                ->where('pushed_to_main', false)
                ->delete();

            $skipped = count($validated['ids']) - $deleted;

            $message = "Deleted {$deleted} row(s).";
            if ($skipped > 0) {
                $message .= " {$skipped} row(s) were skipped (already pushed).";
            }

            return response()->json(['success' => true, 'message' => $message, 'deleted' => $deleted]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error bulk-deleting staged rent invoices: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete selected rows'], 500);
        }
    }

    /**
     * Push every staged, not-yet-pushed row for a property (optionally
     * narrowed to a billing month) into real invoices. Each row is its own
     * transaction — one failing row stays staged with push_error set,
     * ready to be deleted and re-generated.
     */
    public function pushToMain(Request $request)
    {
        try {
            $request->validate([
                'property_id' => 'required|integer|exists:sys_pm_properties,id',
                'billing_month' => 'nullable|date_format:Y-m',
            ]);

            $query = TempGeneratedInvoice::where('property_id', $request->property_id)
                ->where('pushed_to_main', false);

            if ($request->filled('billing_month')) {
                $query->where('returns_period', $request->billing_month . '-01');
            }

            $rows = $query->orderBy('id')->get();

            if ($rows->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Nothing to push — there are no staged rent invoices for this selection.'], 422);
            }

            $rentItemId = InvoiceableItem::where('billing_code', '0024')->value('id');
            if (!$rentItemId) {
                return response()->json(['success' => false, 'message' => 'RENT invoiceable item (billing_code 0024) not found — cannot push.'], 500);
            }

            $pushed = 0;
            $failed = 0;

            foreach ($rows as $row) {
                try {
                    DB::transaction(function () use ($row, $rentItemId) {
                        $this->createSingleDebtorInvoice([
                            'tenant_id' => $row->tenant_id,
                            'property_id' => $row->property_id,
                            'unit_id' => $row->unit_id,
                            'lease_id' => $row->lease_id,
                            'item_type_id' => $rentItemId,
                            'date_due' => $row->date_due,
                            'returns_period' => $row->returns_period,
                            'total_amount' => $row->prorated_amount,
                        ]);
                        $row->update(['pushed_to_main' => true, 'push_error' => null]);
                    });
                    $pushed++;
                } catch (\Throwable $e) {
                    $failed++;
                    $row->update(['push_error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Pushed {$pushed} invoice(s) to main. {$failed} row(s) still need attention.",
                'pushed' => $pushed,
                'failed' => $failed,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error pushing staged rent invoices: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to push invoices: ' . $e->getMessage()], 500);
        }
    }
}
