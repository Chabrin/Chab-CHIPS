<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\InvoiceableItem;
use App\Models\InvoiceableItemCycle;
use App\Models\LeaseAgreement;
use App\Models\Property;
use App\Models\ServiceAgreement;
use App\Models\SlaInvoiceableItem;
use App\Models\TempGeneratedInvoice;
use App\Models\Unit;
use App\Traits\InvoiceCreationTrait;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backs the client page's Invoicing tab — two sub-tabs, split by billing
 * cycle rather than by item:
 *
 *   - One Off: items from the property's SLA (cb_sla_invoiceable_items) whose
 *     invoiceable item has a One-Time default cycle. No staging — nothing to
 *     prorate or review, so createOneOff() posts straight to the real tables.
 *   - Recurring: Rent keeps its existing two-stage staged-review flow
 *     (generate() stages prorated rows into temp_generated_invoices;
 *     pushToMain() commits them), since it's the one item that needs
 *     proration math reviewed before committing. Every other recurring SLA
 *     item (water/electricity/etc, any cycle other than One-Time) posts
 *     directly via createRecurringItem() — same no-staging rationale as
 *     One Off, just on a recurring cadence.
 *
 * Every invoice — however it's created — goes through
 * InvoiceCreationTrait::createSingleDebtorInvoice(), so the commission/VAT/
 * payable_amount math and the sys_pm_tenants_accounts_trans DR mirror never
 * diverge between paths, and cycle_id is always recorded (see that trait).
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

    /**
     * The property's most-recent service agreement — same "most recent wins"
     * ordering used throughout the SLA/lease-creation flows elsewhere in the
     * app (NULLS LAST so an undated SLA doesn't wrongly outrank a dated one).
     */
    private function resolveCurrentSla(int $propertyId): ?ServiceAgreement
    {
        return ServiceAgreement::where('property_id', $propertyId)
            ->orderByRaw('date_start DESC NULLS LAST')
            ->first();
    }

    /**
     * SLA invoiceable items for a property, split by cycle. $oneTime true
     * returns only the One-Time cycle (the One Off tab's item picker);
     * false returns everything else (the Recurring tab's "Other Items"
     * picker). Items with no default_cycle_id configured at all (e.g. Water
     * Bill/Electricity Bill in this app's seed data) are NOT one-time, so
     * they fall to the Recurring side by default — a left join on the cycles
     * table, not an inner join, so they aren't silently dropped from both
     * lists.
     */
    private function slaItemsByCycle(int $propertyId, bool $oneTime)
    {
        $sla = $this->resolveCurrentSla($propertyId);
        if (!$sla) {
            return ['sla' => null, 'items' => collect()];
        }

        $items = SlaInvoiceableItem::where('cb_sla_invoiceable_items.sla_id', $sla->id)
            ->join('sys_set_invoiceable_items as i', 'i.id', '=', 'cb_sla_invoiceable_items.item_id')
            ->leftJoin('sys_set_invoiceable_items_cycles as c', 'c.id', '=', 'i.default_cycle_id')
            ->where('i.billing_code', '!=', '0024') // RENT — handled by the dedicated staged/prorated flow, never this list
            ->when($oneTime, fn ($q) => $q->where('c.cycle_name', 'One-Time'))
            ->when(!$oneTime, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('c.cycle_name')->orWhere('c.cycle_name', '!=', 'One-Time')))
            ->select(
                'cb_sla_invoiceable_items.item_id',
                'i.item_name',
                'i.billing_code',
                'c.id as cycle_id',
                'c.cycle_name',
                'cb_sla_invoiceable_items.amount',
                'cb_sla_invoiceable_items.percentage_of_rent'
            )
            ->orderBy('i.item_name')
            ->get();

        return ['sla' => $sla, 'items' => $items];
    }

    /**
     * One Off tab's item picker (AJAX) — SLA items whose default cycle is
     * One-Time.
     */
    public function getOneOffItems(Request $request)
    {
        try {
            $request->validate(['property_id' => 'required|integer|exists:sys_pm_properties,id']);

            $result = $this->slaItemsByCycle((int) $request->property_id, true);

            return response()->json([
                'items' => $result['items'],
                'has_sla' => $result['sla'] !== null,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading one-off invoiceable items: ' . $e->getMessage());
            return response()->json(['items' => [], 'message' => 'Failed to load items'], 500);
        }
    }

    /**
     * Recurring tab's "Other Items" picker (AJAX) — SLA items whose default
     * cycle is anything but One-Time (Rent itself is handled separately by
     * generate()/pushToMain(), not through this list).
     */
    public function getRecurringItems(Request $request)
    {
        try {
            $request->validate(['property_id' => 'required|integer|exists:sys_pm_properties,id']);

            $result = $this->slaItemsByCycle((int) $request->property_id, false);

            return response()->json([
                'items' => $result['items'],
                'has_sla' => $result['sla'] !== null,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading recurring invoiceable items: ' . $e->getMessage());
            return response()->json(['items' => [], 'message' => 'Failed to load items'], 500);
        }
    }

    /**
     * Resolve the amount to invoice for one lease: an explicit override wins;
     * otherwise the SLA item's flat amount; otherwise its percentage_of_rent
     * applied to the unit's own rent_amount. Null means neither the override
     * nor the SLA item gave us anything to charge.
     */
    private function resolveItemAmount(SlaInvoiceableItem $slaItem, ?float $override, float $unitRentAmount): ?float
    {
        if ($override !== null) {
            return $override;
        }
        if ($slaItem->amount !== null) {
            return (float) $slaItem->amount;
        }
        if ($slaItem->percentage_of_rent !== null) {
            return round($unitRentAmount * ((float) $slaItem->percentage_of_rent) / 100, 2);
        }
        return null;
    }

    /**
     * Create one invoice per selected (or all) active-lease tenant for a
     * single SLA item, isolating each lease's failure from the rest — same
     * per-row resilience as pushToMain(). createSingleDebtorInvoice() already
     * wraps its own DB::transaction(), so nothing here wraps it again.
     */
    private function createPerLeaseInvoices(Property $property, array $leaseWhere, int $itemId, string $dueDate, string $returnsPeriod, int $cycleId, ?float $amountOverride): array
    {
        $slaItem = SlaInvoiceableItem::where('sla_id', $leaseWhere['sla_id'])->where('item_id', $itemId)->first();
        if (!$slaItem) {
            throw new \RuntimeException('This item is not configured on the property\'s service agreement.');
        }

        $leasesQuery = LeaseAgreement::where('property_id', $property->id)->where('lease_status', 'Active');
        if (!empty($leaseWhere['tenant_ids'])) {
            $leasesQuery->whereIn('tenant_id', $leaseWhere['tenant_ids']);
        }
        $leases = $leasesQuery->get();

        if ($leases->isEmpty()) {
            throw new \RuntimeException('No active leases found for this selection.');
        }

        $created = 0;
        $failed = 0;
        $errors = [];

        foreach ($leases as $lease) {
            $unit = Unit::find($lease->unit_id);
            $amount = $this->resolveItemAmount($slaItem, $amountOverride, (float) ($unit->rent_amount ?? 0));

            if ($amount === null) {
                $failed++;
                $errors[] = "{$lease->unit_id}: no amount configured for this item and none provided.";
                continue;
            }

            try {
                $this->createSingleDebtorInvoice([
                    'tenant_id' => $lease->tenant_id,
                    'property_id' => $property->id,
                    'unit_id' => $lease->unit_id,
                    'lease_id' => $lease->id,
                    'item_type_id' => $itemId,
                    'date_due' => $dueDate,
                    'returns_period' => $returnsPeriod,
                    'total_amount' => $amount,
                    'cycle_id' => $cycleId,
                ]);
                $created++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = $e->getMessage();
            }
        }

        return [$created, $failed, $errors];
    }

    /**
     * One Off tab: create a one-time invoice (no staging, no proration) for
     * one SLA item across the selected tenant(s). The amount is auto-filled
     * from the SLA item's configured amount/percentage_of_rent unless the
     * caller provides an explicit override (applied to every tenant in this
     * run, since a percentage-based item otherwise varies per unit).
     */
    public function createOneOff(Request $request)
    {
        try {
            $validated = $request->validate([
                'property_id' => 'required|integer|exists:sys_pm_properties,id',
                'item_id' => 'required|integer|exists:sys_set_invoiceable_items,id',
                'due_date' => 'required|date',
                'scope' => 'required|in:all,selected',
                'tenant_ids' => 'required_if:scope,selected|array',
                'tenant_ids.*' => 'integer',
                'amount_override' => 'nullable|numeric|min:0',
            ]);

            if ($validated['scope'] === 'selected' && empty($validated['tenant_ids'])) {
                return response()->json(['success' => false, 'message' => 'Select at least one tenant.'], 422);
            }

            $property = Property::findOrFail($validated['property_id']);
            $sla = $this->resolveCurrentSla($property->id);
            if (!$sla) {
                return response()->json(['success' => false, 'message' => 'This property has no service agreement.'], 422);
            }

            $oneTimeCycleId = InvoiceableItemCycle::where('cycle_name', 'One-Time')->value('id');
            if (!$oneTimeCycleId) {
                return response()->json(['success' => false, 'message' => 'One-Time billing cycle is not configured.'], 500);
            }

            [$created, $failed, $errors] = $this->createPerLeaseInvoices(
                $property,
                ['sla_id' => $sla->id, 'tenant_ids' => $validated['tenant_ids'] ?? []],
                $validated['item_id'],
                $validated['due_date'],
                now()->format('Y-m-d'),
                $oneTimeCycleId,
                isset($validated['amount_override']) && $validated['amount_override'] !== '' ? (float) $validated['amount_override'] : null
            );

            return response()->json([
                'success' => true,
                'message' => "{$created} invoice(s) created." . ($failed ? " {$failed} failed." : ''),
                'created' => $created,
                'failed' => $failed,
                'errors' => $errors,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating one-off invoices: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create invoices: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Recurring tab's "Other Items" section: same no-staging, direct-post
     * shape as createOneOff(), but for a recurring SLA item (cycle other
     * than One-Time) against a specific billing month.
     */
    public function createRecurringItem(Request $request)
    {
        try {
            $validated = $request->validate([
                'property_id' => 'required|integer|exists:sys_pm_properties,id',
                'item_id' => 'required|integer|exists:sys_set_invoiceable_items,id',
                'billing_month' => 'required|date_format:Y-m',
                'due_date' => 'required|date',
                'scope' => 'required|in:all,selected',
                'tenant_ids' => 'required_if:scope,selected|array',
                'tenant_ids.*' => 'integer',
                'amount_override' => 'nullable|numeric|min:0',
            ]);

            if ($validated['scope'] === 'selected' && empty($validated['tenant_ids'])) {
                return response()->json(['success' => false, 'message' => 'Select at least one tenant.'], 422);
            }

            $property = Property::findOrFail($validated['property_id']);
            $sla = $this->resolveCurrentSla($property->id);
            if (!$sla) {
                return response()->json(['success' => false, 'message' => 'This property has no service agreement.'], 422);
            }

            $invoiceableItem = InvoiceableItem::findOrFail($validated['item_id']);
            if ($invoiceableItem->billing_code === '0024') {
                return response()->json(['success' => false, 'message' => 'RENT is invoiced through the Rent tab, not here.'], 422);
            }

            // Items with no default_cycle_id configured (e.g. Water Bill in
            // this app's seed data) still land on this tab — see
            // slaItemsByCycle() — so fall back to Monthly rather than reject
            // them outright.
            $cycleId = $invoiceableItem->default_cycle_id ?? InvoiceableItemCycle::where('cycle_name', 'Monthly')->value('id');
            if (!$cycleId) {
                return response()->json(['success' => false, 'message' => 'Monthly billing cycle is not configured.'], 500);
            }

            [$created, $failed, $errors] = $this->createPerLeaseInvoices(
                $property,
                ['sla_id' => $sla->id, 'tenant_ids' => $validated['tenant_ids'] ?? []],
                $validated['item_id'],
                $validated['due_date'],
                $validated['billing_month'] . '-01',
                $cycleId,
                isset($validated['amount_override']) && $validated['amount_override'] !== '' ? (float) $validated['amount_override'] : null
            );

            return response()->json([
                'success' => true,
                'message' => "{$created} invoice(s) created." . ($failed ? " {$failed} failed." : ''),
                'created' => $created,
                'failed' => $failed,
                'errors' => $errors,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating recurring item invoices: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create invoices: ' . $e->getMessage()], 500);
        }
    }
}
