<?php

namespace App\Services;

/**
 * The amounts on a tenant invoice: the VAT carve-out, the commissionable base, the commission and what is
 * payable to the landlord. One place for the formula, so the single-invoice trait and the bulk generator
 * cannot drift apart.
 *
 * Only rent carries VAT. Deposits, service charges and utility bills never do, and commission follows the
 * item's own commissionable flag, so a deposit is never commissionable. Rent VAT is inclusive, as in the legacy
 * data: the VAT is 16/116 of the rent, and commission is worked out on the rent less that VAT.
 */
class InvoiceBillingService
{
    const RENT_CODE = '0024';
    const VAT_RATE = 16;

    /**
     * Invoice statuses a return may see: 1 open, 8 partly paid, 9 cleared. Allocation moves an invoice from 1 to 8 or
     * 9, so a list of only 1 drops every invoice the tenant has paid. Cancelled and recovery-closed invoices stay out.
     */
    const RETURNABLE_STATUSES = '1, 8, 9';

    /**
     * SQL condition that says an existing invoice is for a given invoiceable item. Invoices raised by this app
     * carry item_type_id. Migrated legacy invoices (96% of all invoices) have it empty and name the item in
     * return_type_id, or in the narration ("Service Charge FOR August 2026") when that is blank. The duplicate
     * guards use this so they see the legacy invoices too; matching on item_type_id alone blocks nothing.
     *
     * @param string $inv the alias (or table name) of the existing invoice
     * @param string $itemId SQL for the item id: a column or an integer
     * @param string $itemName SQL for the item name: a column or a quoted string
     */
    public static function sameItemCondition(string $inv, string $itemId, string $itemName): string {
        return "({$inv}.item_type_id = {$itemId} OR ({$inv}.item_type_id IS NULL AND ({$inv}.return_type_id = {$itemName} OR (COALESCE({$inv}.return_type_id, '') = '' AND {$inv}.narration ILIKE ({$itemName} || ' FOR %')))))";
    }

    /**
     * The first day of the month of a date, as Y-m-d. Returns are keyed by the first of the month, so every
     * invoice's returns_period is normalised to it, whatever day the invoice was raised.
     *
     * @param string $date any date string
     */
    public static function firstOfMonth(string $date): string {
        return date('Y-m-01', strtotime($date));
    }

    /**
     * The GL lines for a tenant invoice, in the shape of the existing invoice journals: the tenant owes the full
     * amount (debit Tenants Account), the landlord is credited what is left after commission (credit Clients Account,
     * or Tenants Deposits Payable for a deposit the company holds) and the commission is credited to Rent Commission.
     * The commission line is left out when there is none, so a deposit is a two-line journal.
     *
     * @param float $total the invoice total
     * @param float $commission the commission on the invoice
     * @param int $receivableId the Tenants Account id
     * @param int $clientsId the Clients Account id
     * @param int $commissionId the Rent Commission account id
     * @param int|null $depositPayableId the account credited instead of Clients Account for a company-held deposit
     * @return array lines of account_id, type, amount
     */
    public static function invoiceJournalLines(float $total, float $commission, int $receivableId, int $clientsId, int $commissionId, ?int $depositPayableId = null): array {
        $total = round($total, 2);
        $commission = round($commission, 2);

        $lines = [
            ['account_id' => $receivableId, 'type' => 'Debit', 'amount' => $total],
            ['account_id' => $depositPayableId ?? $clientsId, 'type' => 'Credit', 'amount' => round($total - $commission, 2)],
        ];

        if ($commission > 0) {
            $lines[] = ['account_id' => $commissionId, 'type' => 'Credit', 'amount' => $commission];
        }

        return $lines;
    }

    /**
     * Work out the VAT, commission and payable amounts of one invoice line.
     *
     * @param float $total the invoice total
     * @param bool $isRent whether the invoice item is RENT
     * @param bool $commissionable whether the invoice item is commissionable
     * @param bool $vatableUnit whether the unit is VAT-able
     * @param float $commissionPercentage the SLA commission percentage
     * @return array vat_amount, commissionable_amount, commission_amount, payable_amount, vat_on_commission
     */
    public static function computeAmounts(float $total, bool $isRent, bool $commissionable, bool $vatableUnit, float $commissionPercentage): array {
        $vatAmount = ($isRent && $vatableUnit) ? round($total * self::VAT_RATE / (100 + self::VAT_RATE), 2) : 0.0;
        $base = $total - $vatAmount;
        $commission = $commissionable ? round($base * ($commissionPercentage / 100), 2) : 0.0;

        return [
            'vat_amount' => $vatAmount,
            'commissionable_amount' => $commissionable ? round($base, 2) : 0.0,
            'commission_amount' => $commission,
            'payable_amount' => round($total - $commission, 2),
            'vat_on_commission' => $commissionable ? round($commission * (self::VAT_RATE / 100), 2) : 0.0,
        ];
    }
}
