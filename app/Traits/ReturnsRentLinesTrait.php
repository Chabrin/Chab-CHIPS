<?php

namespace App\Traits;

use App\Services\InvoiceBillingService;
use App\Services\UtilityReturnsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles inserting rent lines into cb_returns_clients from approved invoices.
 * 
 * This trait extracts CR lines from sys_pm_debtors_invoices. A rent invoice becomes a RENT CURRENT line with
 * commission_amount and vat_amount worked out from the SLA. Every other invoice (deposit, service charge, water,
 * electricity ...) becomes its own line, named after the item, with no commission or VAT, as in the legacy returns;
 * it is only passed on for what the tenant has actually paid, and a deposit the company holds is left out. The exception
 * is an item set to Advance Payable in the invoiceable items: it is passed on at its full amount, paid or not.
 *
 * Rent is read from open, partly paid and cleared invoices (statuses 1, 8, 9) of the return month. Money that comes in
 * after the month's return is passed on in a later run: the line is what is collected now less what earlier return
 * lines already passed on for that invoice, so nothing goes out twice. For a bill that is a line named after the bill;
 * for rent of an earlier month it is a RENT ARREARS line, paid as collected (the 85% rule does not apply to it) with
 * commission and VAT at the SLA rate on the amount. This applies only to invoices raised in this app (created_by is
 * set) that already appeared in an earlier return. The migrated legacy invoices have no created_by: the old system
 * returned their late money in lines that do not point back at the invoice (79% of the legacy invoices that look
 * unreturned have such a RENT ARREARS line for the same tenant, unit and month), so paying them again would double pay.
 *
 * A mini return (sequence 2 or more) uses the same rules but takes only that late money, for invoices of the return
 * month or earlier, counting every line already passed on, the month's main return included.
 *
 * For a client set to "separate return" the utility lines (anything that is not rent, rent arrears or a deposit) go on a
 * utility return: the code is RU instead of R (RU1 the main utility return, RU2 to RU9 utility minis). One pass numbers
 * both returns, so an invoice is on exactly one of them.
 */
trait ReturnsRentLinesTrait
{
    /**
     * Insert rent lines from approved invoices into cb_returns_clients.
     *
     * @param string $returnPeriod YYYYMM format
     * @param string $monthStart First day of return month (YYYY-MM-01)
     * @param string $transactionDate Display date for transaction (DD-Mon-YYYY)
     * @param int $baseReturnId Starting return_id for DENSE_RANK
     * @param int $generatedBy User ID who generated the returns
     * @param string $monthName Month name for narration
     * @param int $returnYear Year for narration
     * @param int|null $propertyId Optional: Generate for single property only
     * @param int $sequence 1 for the main return, 2 to 9 for a mini return (the digit after the R of the return code)
     * @return int Number of lines inserted
     */
    protected function insertRentLines(string $returnPeriod, string $monthStart, string $transactionDate, int $baseReturnId, int $generatedBy, string $monthName, int $returnYear, ?int $propertyId = null, int $sequence = 1): int {
        if (!preg_match('/^\d{4}-\d{2}-01$/', $monthStart)) {
            throw new \InvalidArgumentException("The return month must be the first day of a month (YYYY-MM-01), got {$monthStart}.");
        }
        if ($sequence < 1 || $sequence > 9) {
            throw new \InvalidArgumentException("A return code has one digit for its sequence, got {$sequence}.");
        }

        $isMini = $sequence > 1;
        $month = "DATE '{$monthStart}'";
        $propertyFilter = $propertyId ? "AND i.property_id = ?" : "";

        // The item of an invoice: its catalog name, or for legacy invoices (no item_type_id) the name stored on the
        // invoice, or the narration prefix when that is blank ("Service Charge FOR August 2026").
        $itemName = "LEFT(COALESCE(it.item_name, NULLIF(i.return_type_id, ''), split_part(i.narration, ' FOR ', 1)), 80)";
        $isRent = "(UPPER({$itemName}) = 'RENT')";
        // Rent of the return month is a RENT CURRENT line; rent of an earlier month that comes in late is RENT ARREARS.
        // A mini return has no current-month rent line, everything it carries is late money.
        $isCurrent = $isMini ? "FALSE" : "(i.returns_period::date = {$month})";
        $rentCurrent = "({$isRent} AND {$isCurrent})";
        $rentArrears = "({$isRent} AND NOT {$isCurrent})";
        // What the tenant has paid of an invoice (never more than the invoice), less what earlier returns already passed
        // on for it: only the difference is passed to the landlord now, so money that comes in late goes out once.
        $collected = "LEAST(i.total_amount, GREATEST(i.total_amount - COALESCE(i.current_balance, i.total_amount), 0))";
        // An item set to Advance Payable (a bill, not rent) is passed on at its full amount before the tenant has paid it,
        // so what is owed to the landlord is the invoice, not the collection. Only invoices raised in this app qualify: the
        // legacy ones were already returned by the old system. Later collections then add nothing, because the amount
        // passed on is subtracted below.
        $advance = "(NOT {$isRent} AND i.created_by IS NOT NULL AND COALESCE(it.advance_payable, 'No') = 'Yes')";
        $owed = "CASE WHEN {$advance} THEN i.total_amount ELSE {$collected} END";
        $payable = "GREATEST({$owed} - prev.returned, 0)";

        // A main return takes this month's invoices (rent and bills), plus invoices of earlier months that were raised
        // in this app, already appeared in an earlier return and have been paid further since. A mini return takes only
        // that late money, from the return month and before. An invoice that never appeared in a return is left alone,
        // and so is a migrated legacy invoice, so what the legacy system already returned cannot be paid twice.
        $sweep = ($isMini ? "i.returns_period::date <= {$month}" : "i.returns_period::date < {$month}") . " AND i.created_by IS NOT NULL AND prev.lines > 0 AND {$payable} > 0";
        $wanted = $isMini ? "({$sweep})" : "(i.returns_period::date = {$month} OR ({$sweep}))";
        // The same limit on the invoice alone, so the look-up of earlier returns runs only for invoices with money paid
        $paidSome = "i.created_by IS NOT NULL AND i.total_amount > COALESCE(i.current_balance, i.total_amount)";
        $prefilter = $isMini
            ? "i.returns_period::date <= {$month} AND {$paidSome}"
            : "(i.returns_period::date = {$month} OR (i.returns_period::date < {$month} AND {$paidSome}))";

        $invoiceWhere = "
            i.debtor_type_id = '2'
            AND i.current_status_id IN (" . InvoiceBillingService::RETURNABLE_STATUSES . ")
            AND i.property_id IS NOT NULL
            AND i.unit_id IS NOT NULL
            AND i.lease_id IS NOT NULL
            AND i.tenant_id IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM sys_pm_deposits dep WHERE dep.invoice_id = i.id::text AND dep.held_by = 'Company')
            AND {$prefilter}
            AND {$wanted}
            {$propertyFilter}
        ";
        // Commission is worked out on the rent after its inclusive VAT, as on the invoice (rent of a VAT-able unit). On a
        // RENT CURRENT line that is the whole invoice, on a RENT ARREARS line only the part passed on now.
        $commissionOn = function (string $amount): string {
            $rate = InvoiceBillingService::VAT_RATE;

            return "ROUND(({$amount} - CASE WHEN u.vat_able = 'Yes' THEN ROUND({$amount} * {$rate} / (100 + {$rate}), 2) ELSE 0 END) * COALESCE(s.commission_percentage, 0) / 100, 2)";
        };
        $commissionCurrent = $commissionOn('i.total_amount');
        $commissionLate = $commissionOn($payable);
        $commission = "CASE WHEN {$rentCurrent} THEN {$commissionCurrent} ELSE {$commissionLate} END";

        // The look-up of earlier lines: a main return leaves out its own period (regeneration replaces those lines); a
        // mini return counts them all, the main return of the month included.
        $previousPeriod = $isMini ? "" : "AND pl.return_period <> ?::integer";
        $invoiceJoins = "
            FROM sys_pm_debtors_invoices i
            LEFT JOIN sys_set_invoiceable_items it ON it.id = i.item_type_id
            JOIN sys_pm_properties p ON p.id = i.property_id
            JOIN sys_pm_units u ON u.id = i.unit_id
            JOIN cb_tenant_records t ON t.id = i.tenant_id
            LEFT JOIN LATERAL (
                SELECT COALESCE(SUM(pl.net_amount), 0) AS returned, COUNT(*) AS lines
                FROM cb_returns_clients pl
                WHERE pl.invoice_id = i.id AND pl.dr_cr = 'CR' {$previousPeriod}
            ) prev ON true
            LEFT JOIN (
                SELECT DISTINCT ON (property_id) * FROM sys_pm_service_agreements
                ORDER BY property_id, date_start DESC NULLS LAST, id DESC
            ) s ON s.property_id = i.property_id
            LEFT JOIN sys_pm_clients c ON c.id = s.client_d
            LEFT JOIN sys_pm_clients pcl ON pcl.id = p.client_id
        ";

        // A client who wants a separate utility return (sys_pm_clients.utility_returns) has the utilities of their properties
        // on a return of their own: the code is RU instead of R, so the two returns of a property and month never share
        // a code. Everyone else is on the R return, as always.
        // A property whose agreement has no client (landlord UNKNOWN) cannot be approved anyway, so it is not split.
        $utilityLine = "(c.id IS NOT NULL AND COALESCE(pcl.utility_returns, 'with_rent') = '" . UtilityReturnsService::SEPARATE_RETURN . "' AND " . UtilityReturnsService::utilityItemSql($itemName) . ")";
        $returnCode = "CASE WHEN {$utilityLine} THEN 'RU{$sequence}' ELSE 'R{$sequence}' END || ? || COALESCE(c.reference_number, 'UNKNOWN') || p.reference_number";

        // Frozen on the line, so a statement printed later does not change when the unit's VAT flag does: whether the
        // rent of the line has VAT inside it, and the unit's place on the statement (its last position in an earlier return).
        $vatable = "CASE WHEN {$isRent} AND u.vat_able = 'Yes' THEN 'Yes' ELSE 'No' END";
        // One pass over the earlier lines (limited to the property when generating for one) takes the last position of each
        // unit; a look-up per invoice line was several times slower on a busy month.
        $positionScope = $propertyId ? 'AND property_id = ' . (int) $propertyId : '';
        $roomPositions = "LEFT JOIN (SELECT DISTINCT ON (unit_id) unit_id, unit_seq_id FROM cb_returns_clients WHERE unit_seq_id IS NOT NULL {$positionScope} ORDER BY unit_id, return_period DESC, id DESC) seq ON seq.unit_id = i.unit_id";
        $roomPosition = "seq.unit_seq_id";
        $notApproved = "
            AND NOT EXISTS (
                SELECT 1 FROM cb_returns_summary rs
                WHERE rs.return_code = {$returnCode}
                  AND rs.approved_status = 1
            )
        ";

        // Placeholders in the order they appear: return code, period, transaction date, return id, user, the period the
        // look-up leaves out (main only), property, return code of the approved check
        $bindings = [$returnPeriod, $returnPeriod, $transactionDate, $baseReturnId, $generatedBy];
        if (!$isMini) {
            $bindings[] = $returnPeriod;
        }
        if ($propertyId) {
            $bindings[] = $propertyId;
        }
        $bindings[] = $returnPeriod;

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
                is_commissionable, payable_status, transaction_source, server_time, user_id,
                is_vatable, unit_seq_id
            )
            SELECT
                {$returnCode},
                ?::integer,
                '',
                ?,
                CASE WHEN {$isRent} THEN 'RENT' ELSE {$itemName} END,
                CASE WHEN {$rentCurrent} THEN 'RENT CURRENT' WHEN {$rentArrears} THEN 'RENT ARREARS' ELSE {$itemName} END,
                'CR',
                p.reference_number,
                u.unit_code,
                t.names,
                CASE WHEN {$rentCurrent} THEN i.total_amount ELSE {$payable} END,
                i.total_amount,
                CASE WHEN {$isRent} THEN ROUND(({$commission}) * COALESCE(s.vat_per, 16) / 100, 2) ELSE 0 END,
                CASE WHEN {$isRent} THEN {$commission} ELSE 0 END,
                i.property_id,
                COALESCE(s.client_d, 0),
                i.zone_id,
                i.field_officer_id,
                i.zone_supervisor_id,
                i.zone_manager_id,
                i.unit_id,
                i.tenant_id,
                ? + DENSE_RANK() OVER (ORDER BY i.property_id, CASE WHEN {$utilityLine} THEN 1 ELSE 0 END) - 1,
                i.id,
                CASE
                    WHEN {$rentCurrent} THEN 'Rent for {$monthName} {$returnYear}'
                    WHEN {$rentArrears} THEN 'Rent for ' || TRIM(TO_CHAR(i.returns_period::date, 'FMMonth YYYY'))
                    ELSE {$itemName} || ' for ' || TRIM(TO_CHAR(i.returns_period::date, 'FMMonth YYYY'))
                END,
                i.reference_number,
                CASE WHEN {$isRent} THEN 'Yes' ELSE 'No' END,
                CASE WHEN {$rentCurrent} THEN NULL WHEN {$payable} > 0 THEN 1 ELSE 0 END,
                CASE WHEN {$rentArrears} THEN 'TENANT ACCOUNT' END,
                NOW(),
                ?,
                {$vatable},
                {$roomPosition}
            {$invoiceJoins}
            {$roomPositions}
            WHERE {$invoiceWhere}
            {$notApproved}
        ", $bindings);

        Log::info("ReturnsRentLinesTrait: Rent lines inserted", [
            'count' => $rentInserted,
            'property_id' => $propertyId,
            'sequence' => $sequence,
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
              AND i.current_status_id IN (" . InvoiceBillingService::RETURNABLE_STATUSES . ")
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
