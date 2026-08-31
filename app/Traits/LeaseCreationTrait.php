<?php

namespace App\Traits;

use App\Models\Client;
use App\Models\InvoiceableItem;
use App\Models\LeaseAgreement;
use App\Models\LeaseAgreementInvoiceableItem;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Shared between LeasesController (the deliberate "create a lease" flow) and
 * TenantsController's returning-tenant reassignment flow, so a LeaseAgreement
 * is always built, ref-numbered, and given its invoiceable items exactly the
 * same way regardless of which flow created it — same "can't drift apart"
 * reasoning as TenantRecordHelperTrait.
 */
trait LeaseCreationTrait
{
    /**
     * Create a LeaseAgreement row — ref number, invoiceable items, and the
     * unit's occupancy flip all included — the shared core of
     * LeasesController::store().
     *
     * $data keys: client_id, property_id, unit_id, tenant_id, sla_id,
     * date_start, years, auto_renewal (default 'No'), escalation_rate
     * (default 0), escalation_frequency (default 'Annually'),
     * service_charges_amount (nullable), linked_lease_id (nullable).
     *
     * $invoiceableItems: array of ['item' => id, 'amount' => x, 'cycle' => id, 'prepay_client' => 'Yes'|null],
     * matching the shape LeasesController::store() already validates and stores.
     */
    private function createLeaseAgreementRecord(array $data, array $invoiceableItems = []): LeaseAgreement
    {
        $startDate = new \DateTime($data['date_start']);
        $endDate = clone $startDate;
        $endDate->modify('+' . $data['years'] . ' years');

        $property = Property::findOrFail($data['property_id']);
        $unit = Unit::findOrFail($data['unit_id']);

        $leaseAgreement = LeaseAgreement::create([
            'date_time' => now(),
            'client_id' => $data['client_id'],
            'property_id' => $data['property_id'],
            'unit_id' => $data['unit_id'],
            'tenant_id' => $data['tenant_id'],
            'sla_id' => $data['sla_id'],
            'zone_id' => $property->zone_id ?? null,
            'date_start' => $data['date_start'],
            'date_end' => $endDate->format('Y-m-d'),
            'years' => $data['years'],
            'auto_renewal' => $data['auto_renewal'] ?? 'No',
            'escalation_rate' => $data['escalation_rate'] ?? 0,
            'escalation_frequency' => $data['escalation_frequency'] ?? 'Annually',
            'service_charges_amount' => $data['service_charges_amount'] ?? null,
            'linked_lease_id' => $data['linked_lease_id'] ?? null,
            'initial_water_meter_reading' => $unit->initial_water_meter_reading ?? 0,
            'created_by_id' => Auth::id(),
            'current_status_id' => 1, // Pending — awaiting maker-checker approval
            'lease_status' => 'Active',
            'ref_number' => null, // Set below, once the ID exists
        ]);

        $tenant = Client::find($data['tenant_id']);
        $leaseAgreement->ref_number = $this->generateLeaseReferenceNumber(
            $data['unit_id'],
            $tenant->names ?? ''
        );
        $leaseAgreement->save();

        if (!empty($invoiceableItems)) {
            $this->storeInvoiceableItems($leaseAgreement->id, $invoiceableItems);
        }

        // Update unit occupancy status to "Occupied"
        $unit->occupancy_status_id = 38;
        $unit->save();

        return $leaseAgreement;
    }

    protected function storeInvoiceableItems($leaseAgreementId, $invoiceableItems)
    {
        foreach ($invoiceableItems as $item) {
            if (isset($item['item']) && isset($item['amount']) && $item['item'] !== '') {
                // Get the invoiceable item to retrieve commissionable and vatable info
                $invoiceableItem = InvoiceableItem::find($item['item']);

                LeaseAgreementInvoiceableItem::create([
                    'parent_id' => $leaseAgreementId,
                    'item_id' => $item['item'],
                    'amount' => $item['amount'],
                    'cycle_id' => $item['cycle'],
                    'commissionable' => $invoiceableItem->commissionable ?? 'No',
                    'comm_per' => $invoiceableItem->commissionable === 'Yes' ? ($invoiceableItem->commission_percentage ?? 0) : 0,
                    'vatable' => $invoiceableItem->vatable ?? 'No',
                    'prepay_client' => isset($item['prepay_client']) && $item['prepay_client'] === 'Yes' ? 'Yes' : 'No',
                ]);
            }
        }

        Log::info('Invoiceable items stored successfully', [
            'lease_agreement_id' => $leaseAgreementId,
            'items_count' => count($invoiceableItems)
        ]);
    }

    /**
     * Generate a reference number for the lease, matching the convention already
     * used throughout the existing data: {UnitCode}-{TenantName}
     * Example: 138G-16-ZYRON LAURA
     *
     * @param int $unitId
     * @param string|null $tenantName
     * @return string
     */
    protected function generateLeaseReferenceNumber($unitId, $tenantName)
    {
        $unit = Unit::find($unitId);
        $unitCode = $unit->unit_code ?? '';
        $tenantName = trim((string) $tenantName);

        return trim($unitCode . '-' . $tenantName, '-');
    }
}
