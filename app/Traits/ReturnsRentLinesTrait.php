<?php

namespace App\Traits;

use App\Services\InvoiceBillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles inserting rent lines into cb_returns_clients from approved invoices.
 * 
 * This trait extracts CR lines from sys_pm_debtors_invoices. A rent invoice becomes a RENT CURRENT line with
 * commission_amount and vat_amount worked out from the SLA. Every other invoice (deposit, service charge, water,
 * electricity ...) becomes its own line, named after the item, with no commission or VAT, as in the legacy returns;
 * it is only passed on for what the tenant has actually paid, and a deposit the company holds is left out.
 */
trait ReturnsRentLinesTrait
{
    /**
     * Insert rent lines from approved invoices into cb_returns_clients.
     *
     * @param string $returnPeriod YYYYMM format
     * @param string $monthStart First day of return month (YYYY-MM-DD)
     * @param string $transactionDate Display date for transaction (DD-Mon-YYYY)
     * @param int $baseReturnId Starting return_id for DENSE_RANK
     * @param int $generatedBy User ID who generated the returns
     * @param string $monthName Month name for narration
     * @param int $returnYear Year for narration
     * @param int|null $propertyId Optional: Generate for single property only
     * @return int Number of rent lines inserted
     */
    protected function insertRentLines(
        string $returnPeriod,
        string $monthStart,
        string $transactionDate,
        int $baseReturnId,
        int $generatedBy,
        string $monthName,
        int $returnYear,
        ?int $propertyId = null
    ): int {
        $propertyFilter = $propertyId ? "AND i.property_id = ?" : "";

        $invoiceWhere = "
            i.returns_period::date = ?::date
            AND i.current_status_id = 1
            AND i.debtor_type_id = '2'
            AND i.property_id IS NOT NULL
            AND i.unit_id IS NOT NULL
            AND i.lease_id IS NOT NULL
            AND i.tenant_id IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM sys_pm_deposits dep WHERE dep.invoice_id = i.id::text AND dep.held_by = 'Company')
            {$propertyFilter}
        ";

        // The item of an invoice: its catalog name, or for legacy invoices (no item_type_id) the name stored on the
        // invoice, or the narration prefix when that is blank ("Service Charge FOR August 2026").
        $itemName = "LEFT(COALESCE(it.item_name, NULLIF(i.return_type_id, ''), split_part(i.narration, ' FOR ', 1)), 80)";
        $isRent = "(UPPER({$itemName}) = 'RENT')";
        // What the tenant has paid of a non-rent invoice: only that is passed to the landlord.
        $collected = "GREATEST(i.total_amount - COALESCE(i.current_balance, i.total_amount), 0)";
        // Commission is worked out on the rent after its inclusive VAT, as on the invoice (rent of a VAT-able unit).
        $commissionBase = "(i.total_amount - CASE WHEN u.vat_able = 'Yes' THEN ROUND(i.total_amount * " . InvoiceBillingService::VAT_RATE . " / (100 + " . InvoiceBillingService::VAT_RATE . "), 2) ELSE 0 END)";
        $commission = "ROUND({$commissionBase} * COALESCE(s.commission_percentage, 0) / 100, 2)";

        $invoiceJoins = "
            FROM sys_pm_debtors_invoices i
            LEFT JOIN sys_set_invoiceable_items it ON it.id = i.item_type_id
            JOIN sys_pm_properties p ON p.id = i.property_id
            JOIN sys_pm_units u ON u.id = i.unit_id
            JOIN cb_tenant_records t ON t.id = i.tenant_id
            LEFT JOIN (
                SELECT DISTINCT ON (property_id) * FROM sys_pm_service_agreements
                ORDER BY property_id, date_start DESC NULLS LAST, id DESC
            ) s ON s.property_id = i.property_id
            LEFT JOIN sys_pm_clients c ON c.id = s.client_d
        ";

        $notApproved = "
            AND NOT EXISTS (
                SELECT 1 FROM cb_returns_summary rs
                WHERE rs.return_code = 'R1' || ? || COALESCE(c.reference_number, 'UNKNOWN') || p.reference_number
                  AND rs.approved_status = 1
            )
        ";

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
                is_commissionable, payable_status, server_time, user_id
            )
            SELECT
                'R1' || ? || COALESCE(c.reference_number, 'UNKNOWN') || p.reference_number,
                ?::integer,
                '',
                ?,
                CASE WHEN {$isRent} THEN 'RENT' ELSE {$itemName} END,
                CASE WHEN {$isRent} THEN 'RENT CURRENT' ELSE {$itemName} END,
                'CR',
                p.reference_number,
                u.unit_code,
                t.names,
                CASE WHEN {$isRent} THEN i.total_amount ELSE {$collected} END,
                i.total_amount,
                CASE WHEN {$isRent} THEN ROUND({$commission} * COALESCE(s.vat_per, 16) / 100, 2) ELSE 0 END,
                CASE WHEN {$isRent} THEN {$commission} ELSE 0 END,
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
                CASE WHEN {$isRent} THEN 'Rent for {$monthName} {$returnYear}' ELSE {$itemName} || ' for {$monthName} {$returnYear}' END,
                i.reference_number,
                CASE WHEN {$isRent} THEN 'Yes' ELSE 'No' END,
                CASE WHEN {$isRent} THEN NULL WHEN {$collected} > 0 THEN 1 ELSE 0 END,
                NOW(),
                ?
            {$invoiceJoins}
            WHERE {$invoiceWhere}
            {$notApproved}
        ", array_merge(
            [
                $returnPeriod,
                $returnPeriod,
                $transactionDate,
                $baseReturnId,
                $generatedBy,
                $monthStart,
            ],
            $propertyId ? [$propertyId] : [],
            [$returnPeriod]
        ));

        Log::info("ReturnsRentLinesTrait: Rent lines inserted", [
            'count' => $rentInserted,
            'property_id' => $propertyId
        ]);

        return $rentInserted;
    }

    /**
     * Count approved invoices available for return generation.
     *
     * @param string $monthStart First day of return month (YYYY-MM-DD)
     * @param int|null $propertyId Optional: Count for single property only
     * @return int
     */
    protected function countApprovedInvoices(string $monthStart, ?int $propertyId = null): int
    {
        $propertyFilter = $propertyId ? "AND i.property_id = ?" : "";

        $bindings = [$monthStart];
        if ($propertyId) {
            $bindings[] = $propertyId;
        }

        return DB::selectOne("
            SELECT COUNT(*) as cnt FROM sys_pm_debtors_invoices i
            WHERE i.returns_period::date = ?::date
              AND i.current_status_id = 1
              AND i.debtor_type_id = '2'
              AND i.property_id IS NOT NULL
              AND i.unit_id IS NOT NULL
              AND i.lease_id IS NOT NULL
              AND i.tenant_id IS NOT NULL
              {$propertyFilter}
        ", $bindings)->cnt ?? 0;
    }

    /**
     * Check if returns already exist for this period.
     *
     * @param int $returnYear
     * @param int $returnMonth
     * @return int
     */
    protected function countExistingReturns(int $returnYear, int $returnMonth): int
    {
        return DB::selectOne("
            SELECT COUNT(*) as cnt FROM cb_returns_summary
            WHERE year = ? AND month = ?
        ", [$returnYear, $returnMonth])->cnt ?? 0;
    }

    /**
     * Get the next available return_id base.
     *
     * @return int
     */
    protected function getNextReturnIdBase(): int
    {
        return DB::selectOne("SELECT COALESCE(MAX(return_id), 35217) + 1 as next_id FROM cb_returns_summary")->next_id;
    }
}
