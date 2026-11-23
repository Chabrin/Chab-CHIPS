<?php

namespace App\Services;

use App\Models\ServiceAgreement;
use App\Models\SlaInvoiceableItem;
use Carbon\Carbon;

/**
 * Copies the terms of an existing SLA into the Add SLA form.
 *
 * Only the commercial terms are copied. The property, the uploaded documents, the reference
 * number, the approval status, the dates and the registration fee are left for the user to set,
 * so a clone goes through the normal create and approval flow as a brand new SLA.
 */
class ServiceAgreementCloneService
{
    const STATUS_LABELS = [
        1 => 'Pending',
        2 => 'Ended',
        5 => 'Approved',
        41 => 'Rejected',
    ];

    /**
     * List a client's SLAs for the "Copy terms from" dropdown, newest first.
     *
     * @param int $clientId
     * @return array
     */
    public function optionsForClient(int $clientId): array {
        return ServiceAgreement::with('property')
            ->where('client_d', $clientId)
            ->orderByRaw('date_start DESC NULLS LAST')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ServiceAgreement $sla) => [
                'id' => $sla->id,
                'label' => $this->optionLabel($sla),
            ])
            ->all();
    }

    /**
     * Build the form values to copy from an SLA, including its tenant invoiceable items.
     *
     * @param ServiceAgreement $sla
     * @return array
     */
    public function termsFor(ServiceAgreement $sla): array {
        $items = SlaInvoiceableItem::where('sla_id', $sla->id)
            ->with('item.cycle')
            ->get()
            ->map(fn (SlaInvoiceableItem $row) => $this->itemRow($row))
            ->filter()
            ->values()
            ->all();

        return $this->mapTerms($sla, $items);
    }

    /**
     * Map an SLA's columns onto the Add SLA form's field names and values.
     *
     * @param ServiceAgreement $sla
     * @param array $items
     * @return array
     */
    public function mapTerms(ServiceAgreement $sla, array $items): array {
        return [
            'commission_percentage' => $sla->commission_percentage,
            'vat_able' => in_array($sla->vat, ['Yes', 'Flat Rate'], true) ? $sla->vat : 'No',
            'vat_percentage' => $sla->vat_per,
            'flat_rate_vat' => $sla->flat_rate_vat,
            'auto_renewal' => $this->formAutoRenewal($sla->auto_renewal),
            'move_in_inspection_required' => $sla->move_in_inspection_required ? '1' : '0',
            'payment_date' => $sla->landlord_payment_date,
            'duration_years' => $sla->years,
            'returns_combination' => $sla->returns_combination ?: 'each_separate',
            'items' => $items,
        ];
    }

    /**
     * Turn a stored invoiceable item row into the shape the form's items table uses.
     * Returns null when the item no longer exists, so a dead row is not cloned.
     *
     * @param SlaInvoiceableItem $row
     * @return array|null
     */
    public function itemRow(SlaInvoiceableItem $row): ?array {
        if (!$row->item) {
            return null;
        }

        return [
            'item_id' => $row->item_id,
            'item_name' => $row->item->item_name ?? '',
            'billing_code' => $row->item->billing_code ?? '',
            'default_cycle_name' => $row->item->cycle->cycle_name ?? null,
            'advance_payable' => $row->item->advance_payable ?: 'No',
            'choice' => $row->amount !== null ? 'amount' : ($row->percentage_of_rent !== null ? 'percentage' : null),
            'amount' => $row->amount,
            'percentage_of_rent' => $row->percentage_of_rent,
        ];
    }

    /**
     * The Add SLA form posts auto renewal as 1 (Yes) or 2 (No), while the edit form and
     * migrated SLAs store Yes or No. Accept either and return the create form's value.
     *
     * @param mixed $stored
     * @return string
     */
    public function formAutoRenewal($stored): string {
        return in_array($stored, ['Yes', '1', 1, true], true) ? '1' : '2';
    }

    /**
     * One line for the dropdown: reference, property, status and end date.
     *
     * @param ServiceAgreement $sla
     * @return string
     */
    private function optionLabel(ServiceAgreement $sla): string {
        $status = self::STATUS_LABELS[(int) $sla->current_status_id] ?? null;
        $end = $sla->date_end ? 'ends ' . Carbon::parse($sla->date_end)->format('d M Y') : null;
        $detail = implode(', ', array_filter([$status, $end]));

        return trim(($sla->reference_number ?: 'SLA #' . $sla->id) . ' — ' . ($sla->property->property_name ?? 'Unknown property') . ($detail ? " ({$detail})" : ''));
    }
}
