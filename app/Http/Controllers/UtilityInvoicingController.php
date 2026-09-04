<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\InvoiceableItem;
use App\Models\LeaseAgreement;
use App\Models\Property;
use App\Models\ServiceAgreement;
use App\Traits\InvoiceCreationTrait;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Utilities Invoicing sub-tab of the client page's Invoicing tab. Unlike
 * Rent Invoicing there's no staging table — every invoice here posts
 * straight to the real tables (via InvoiceCreationTrait), same as the
 * interactive single-invoice form already does, because there's no
 * proration math to review first.
 *
 * Two modes:
 *   - generateFixed(): batch, for tenants whose property SLA has a fixed
 *     rate for the chosen utility (water_bill_selected/cost or
 *     electricity_bill_selected/cost).
 *   - oneOff(): one tenant, one manually-entered amount — for utilities
 *     that aren't fixed (e.g. metered water), reusing
 *     InvoicingController::storeInvoiceItem()'s exact same underlying logic.
 */
class UtilityInvoicingController extends Controller
{
    use InvoiceCreationTrait;

    private const BILLING_CODES = ['water' => '0003', 'electricity' => '0001'];

    /**
     * Batch-generate for every eligible lease (all, or a selected subset)
     * whose property's current SLA has this utility fixed. Posts directly —
     * no staging. Each lease is independent; one failure doesn't block others.
     */
    public function generateFixed(Request $request)
    {
        try {
            $validated = $request->validate([
                'property_id' => 'required|integer|exists:sys_pm_properties,id',
                'utility_type' => 'required|in:water,electricity',
                'billing_month' => 'required|date_format:Y-m',
                'due_date' => 'required|date',
                'scope' => 'required|in:all,selected',
                'tenant_ids' => 'required_if:scope,selected|array',
                'tenant_ids.*' => 'integer',
            ]);

            $property = Property::findOrFail($validated['property_id']);
            $billingCode = self::BILLING_CODES[$validated['utility_type']];
            $itemId = InvoiceableItem::where('billing_code', $billingCode)->value('id');

            if (!$itemId) {
                return response()->json(['success' => false, 'message' => 'Invoiceable item not found for this utility.'], 500);
            }

            $costField = $validated['utility_type'] === 'water' ? 'water_bill_cost' : 'electricity_bill_cost';
            $selectedField = $validated['utility_type'] === 'water' ? 'water_bill_selected' : 'electricity_bill_selected';

            $monthStart = new \DateTime($validated['billing_month'] . '-01');
            $returnsPeriod = $monthStart->format('Y-m-d');

            // Property's current SLA — most-recent by date_start, same resolution
            // rule the bulk generator uses for Service Charge.
            $sla = ServiceAgreement::where('property_id', $property->id)
                ->orderByRaw('date_start DESC NULLS LAST')
                ->first();

            if (!$sla || !$sla->{$selectedField} || (float) $sla->{$costField} <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This property\'s SLA does not have a fixed ' . $validated['utility_type'] . ' rate configured.',
                ], 422);
            }

            $fixedAmount = (float) $sla->{$costField};

            $query = LeaseAgreement::where('property_id', $property->id)
                ->where('lease_status', 'Active');

            if ($validated['scope'] === 'selected') {
                if (empty($validated['tenant_ids'])) {
                    return response()->json(['success' => false, 'message' => 'Select at least one tenant.'], 422);
                }
                $query->whereIn('tenant_id', $validated['tenant_ids']);
            }

            $leases = $query->get();

            if ($leases->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No active leases found for this selection.'], 422);
            }

            $created = 0;
            $failed = 0;
            $errors = [];

            foreach ($leases as $lease) {
                try {
                    $this->createSingleDebtorInvoice([
                        'tenant_id' => $lease->tenant_id,
                        'property_id' => $property->id,
                        'unit_id' => $lease->unit_id,
                        'lease_id' => $lease->id,
                        'item_type_id' => $itemId,
                        'date_due' => $validated['due_date'],
                        'returns_period' => $returnsPeriod,
                        'total_amount' => $fixedAmount,
                    ]);
                    $created++;
                } catch (\Throwable $e) {
                    $failed++;
                    $tenantName = Client::find($lease->tenant_id)->names ?? "tenant #{$lease->tenant_id}";
                    $errors[] = "{$tenantName}: {$e->getMessage()}";
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Created {$created} invoice(s). {$failed} skipped.",
                'created' => $created,
                'failed' => $failed,
                'errors' => $errors,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating fixed-rate utility invoices: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to generate invoices: ' . $e->getMessage()], 500);
        }
    }

    /**
     * One tenant, one manually-entered amount (e.g. a metered water reading
     * that isn't the SLA's fixed rate). Posts directly via the same shared
     * invoice-creation logic as every other path.
     */
    public function oneOff(Request $request)
    {
        try {
            $validated = $request->validate([
                'tenant_id' => 'required|integer|exists:sys_pm_clients,id',
                'utility_type' => 'required|in:water,electricity',
                'amount' => 'required|numeric|min:0',
                'billing_month' => 'required|date_format:Y-m',
                'due_date' => 'required|date',
            ]);

            $lease = LeaseAgreement::where('tenant_id', $validated['tenant_id'])
                ->where('lease_status', 'Active')
                ->latest('date_start')
                ->first();

            if (!$lease) {
                return response()->json(['success' => false, 'message' => 'No active lease found for this tenant.'], 422);
            }

            $billingCode = self::BILLING_CODES[$validated['utility_type']];
            $itemId = InvoiceableItem::where('billing_code', $billingCode)->value('id');

            if (!$itemId) {
                return response()->json(['success' => false, 'message' => 'Invoiceable item not found for this utility.'], 500);
            }

            $monthStart = new \DateTime($validated['billing_month'] . '-01');

            $invoice = $this->createSingleDebtorInvoice([
                'tenant_id' => $validated['tenant_id'],
                'property_id' => $lease->property_id,
                'unit_id' => $lease->unit_id,
                'lease_id' => $lease->id,
                'item_type_id' => $itemId,
                'date_due' => $validated['due_date'],
                'returns_period' => $monthStart->format('Y-m-d'),
                'total_amount' => $validated['amount'],
            ]);

            return response()->json(['success' => true, 'message' => 'Invoice created successfully.', 'invoice' => $invoice]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating one-off utility invoice: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create invoice: ' . $e->getMessage()], 500);
        }
    }
}
