<?php

namespace App\Traits;

use App\Models\DebtorInvoice;
use App\Models\InvoiceableItem;
use App\Models\LeaseAgreement;
use App\Models\Property;
use App\Models\TenantAccountTransaction;
use App\Models\Unit;
use App\Services\InvoiceBillingService;
use App\Services\InvoiceJournalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Shared between InvoicingController::storeInvoiceItem() (the interactive
 * single-invoice form) and the client-page invoicing flows
 * (RentInvoicingController, UtilityInvoicingController), so every invoice —
 * however it's created — gets the exact same commission/VAT/payable_amount
 * math and sys_pm_tenants_accounts_trans DR mirror. Don't duplicate this
 * formula a third way; add a caller instead.
 */
trait InvoiceCreationTrait
{
    /**
     * Posts the invoice to the GL; the using class sets it in its constructor.
     */
    protected InvoiceJournalService $invoiceJournals;

    /**
     * $data keys: tenant_id, property_id, unit_id, lease_id, item_type_id,
     * date_due, returns_period, total_amount, and optionally cycle_id (a
     * sys_set_invoiceable_items_cycles id — defaults to the invoiceable
     * item's own default_cycle_id when omitted, so every invoice records
     * which billing cycle it belongs to, e.g. to tell one-off from recurring).
     *
     * Throws \RuntimeException on any business failure (duplicate invoice
     * for this tenant/item/month — same rule storeInvoiceItem() already
     * enforced). Callers doing a batch run should catch \Throwable per row
     * (see RentInvoicingController::pushToMain()/createOneOff()/createRecurringItem())
     * rather than letting one bad row abort the whole run.
     */
    protected function createSingleDebtorInvoice(array $data): DebtorInvoice
    {
        return DB::transaction(function () use ($data) {
            // Derive the payee only after a concurrent ownership handover has finished.
            Property::whereKey($data['property_id'])->lockForUpdate()->firstOrFail();
            return $this->createSingleDebtorInvoiceForLockedProperty($data);
        });
    }

    private function createSingleDebtorInvoiceForLockedProperty(array $data): DebtorInvoice
    {
        // Returns are keyed by the first of the month, so a one-off raised mid-month still belongs to that month's return.
        $data['returns_period'] = InvoiceBillingService::firstOfMonth($data['returns_period']);

        $property = Property::find($data['property_id']);
        $lease = LeaseAgreement::find($data['lease_id']);
        $unit = Unit::find($data['unit_id']);
        $invoiceableItem = InvoiceableItem::find($data['item_type_id']);
        // The SLA on the lease, otherwise the property's current one (almost no lease carries sla_id).
        $serviceAgreement = $lease ? $lease->resolvedServiceAgreement($data['returns_period']) : null;

        $landlordId = $property ? $property->client_id : null;
        if ($property && ($ownership = app(\App\Services\PropertyOwnershipResolver::class)->agreement($property->id, $data['returns_period']))) {
            $landlordId = $ownership->client_d;
        }

        // Only one invoice per tenant per month for these recurring item types.
        $singlePerMonthItems = [
            'Water Bill', 'Service Charge', 'RENT', 'Electricity Bill',
            'House Deposit', 'Electricity Deposit', 'Water Deposit',
        ];

        if ($invoiceableItem && in_array($invoiceableItem->item_name, $singlePerMonthItems, true)) {
            $periodDate = new \DateTime($data['returns_period']);

            // Legacy invoices have no item_type_id, so the item is matched by name as well.
            $itemMatch = InvoiceBillingService::sameItemCondition('sys_pm_debtors_invoices', (string) (int) $invoiceableItem->id, DB::getPdo()->quote($invoiceableItem->item_name));

            // Per tenant and unit, so a returning tenant moved to a second unit can still be billed there.
            $duplicateExists = DebtorInvoice::where('tenant_id', $data['tenant_id'])
                ->where('unit_id', $data['unit_id'])
                ->whereRaw($itemMatch)
                ->whereYear('returns_period', $periodDate->format('Y'))
                ->whereMonth('returns_period', $periodDate->format('m'))
                ->exists();

            if ($duplicateExists) {
                throw new \RuntimeException(
                    "This tenant already has a {$invoiceableItem->item_name} invoice for {$periodDate->format('F Y')}."
                );
            }
        }

        $totalAmount = $data['total_amount'];
        $isRentItem = $invoiceableItem && $invoiceableItem->billing_code === InvoiceBillingService::RENT_CODE;
        // Rent is always commissionable (legacy: commission on rent only); any other item follows its own flag.
        $isCommissionable = $isRentItem || ($invoiceableItem && $invoiceableItem->commissionable === 'Yes');
        $isVatable = $unit && $unit->vat_able === 'Yes';
        $commissionPercentage = $serviceAgreement ? $serviceAgreement->commission_percentage : 0;

        // VAT, commission and payable amounts come from one formula (rent only carries VAT).
        $amounts = InvoiceBillingService::computeAmounts(
            (float) $totalAmount,
            (bool) $isRentItem,
            (bool) $isCommissionable,
            (bool) $isVatable,
            (float) $commissionPercentage
        );
        $vatAmount = $amounts['vat_amount'];
        $commAmount = $amounts['commission_amount'];
        $commissionableAmount = $amounts['commissionable_amount'];
        $payableAmount = $amounts['payable_amount'];
        $vatOnCommission = $amounts['vat_on_commission'];

        // Reference number format matches the bulk generator (InvoicingController::processGenerateInvoices).
        $unitCode = $unit ? $unit->unit_code : 'UNKNOWN';
        $itemName = $invoiceableItem ? $invoiceableItem->item_name : 'Unknown Item';
        $period = date('M Y', strtotime($data['returns_period']));
        $referenceNumber = 'INV' . date('Y', strtotime($data['returns_period'])) .
            date('m', strtotime($data['returns_period'])) .
            $unitCode . '-' . $itemName . ' for ' . $period . '-' . $data['lease_id'];

        // The S prefix keeps a single invoice out of any bulk run's batch (those are digits only), even in the same minute.
        $batchNumber = 'S' . date('YmdHis');

        return DB::transaction(function () use (
            $data, $property, $lease, $unit, $itemName, $landlordId, $totalAmount,
            $commAmount, $commissionPercentage, $isCommissionable, $vatAmount,
            $commissionableAmount, $payableAmount, $vatOnCommission, $referenceNumber, $batchNumber,
            $invoiceableItem, $serviceAgreement
        ) {
            $invoice = DebtorInvoice::create([
                'date_time' => now(),
                'date_due' => $data['date_due'],
                'returns_period' => $data['returns_period'],
                'zone_id' => $lease ? $lease->zone_id : null,
                'property_id' => $data['property_id'],
                'unit_id' => $data['unit_id'],
                'lease_id' => $data['lease_id'],
                'tenant_id' => $data['tenant_id'],
                'client_id' => $landlordId,
                'sla_id' => $serviceAgreement?->id,
                'created_by' => Auth::id(),
                'reference_number' => $referenceNumber,
                'narration' => $itemName . ' FOR ' . date('F Y', strtotime($data['returns_period'])),
                'debtor_type_id' => 2, // Tenant
                'total_amount' => $totalAmount,
                'trans_details' => '18:' . ($lease ? $lease->id : 0) . ':' . intval(microtime(true) * 1000),
                'current_status_id' => 1, // Pending
                'processed' => 'No',
                'comm_ammount' => $commAmount,
                'comm_per' => $isCommissionable ? $commissionPercentage : 0,
                'vat_ammount' => $vatAmount,
                'vat_per' => 16.00,
                'commisionable_amount' => $commissionableAmount,
                'payable_amount' => $payableAmount,
                'item_type_id' => $data['item_type_id'],
                'return_type_id' => $itemName,
                'cycle_id' => $data['cycle_id'] ?? ($invoiceableItem->default_cycle_id ?? null),
                'current_balance' => $totalAmount,
                'occupancy_status_id' => $unit ? $unit->occupancy_status_id : null,
                'field_officer_id' => $property ? $property->field_officer_id : null,
                'zone_supervisor_id' => $property ? $property->zone_supervisor_id : null,
                'zone_manager_id' => $property ? $property->zone_manager_id : null,
                'vat_on_commission' => $vatOnCommission,
                'override_period_check' => 1,
                'batch_number' => $batchNumber,
            ]);

            // Mirrors PropertyConfirmationController::applyTenantExitSettlementApproval()'s
            // DR posting field mapping.
            TenantAccountTransaction::create([
                'date_time' => now(),
                'narration' => $itemName . ' FOR ' . date('F Y', strtotime($data['returns_period'])),
                'pay_for' => $itemName,
                'dr_cr' => 'DR',
                'debit_amount' => $totalAmount,
                'credit_amount' => 0,
                'invoice_number' => $referenceNumber,
                'invoice_id' => $invoice->id,
                'zone_id' => $lease ? $lease->zone_id : null,
                'property_id' => $data['property_id'],
                'unit_id' => $data['unit_id'],
                'lease_id' => $data['lease_id'],
                'client_id' => $landlordId,
                'tenant_id' => $data['tenant_id'],
                'sla_id' => $serviceAgreement?->id,
                'field_officer_id' => $property ? $property->field_officer_id : null,
                'zone_supervisor_id' => $property ? $property->zone_supervisor_id : null,
                'zone_manager_id' => $property ? $property->zone_manager_id : null,
                'current_status_id' => 1,
                'entry_by' => Auth::id(),
            ]);

            // The GL journal, in the same transaction; a missing GL account refuses the invoice. Off until agreed.
            $this->invoiceJournals->postInvoice($invoice, $data['deposit_held_by'] ?? null);

            return $invoice;
        });
    }
}
