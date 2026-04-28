<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles inserting deduction and collection lines into cb_returns_clients.
 * 
 * This includes:
 * - Advance Payments-From Landlord Acc (DR) from cb_landlord_account
 * - Recoveries (DR) from cb_money_recoveries
 * - OTHER PAYMENTS/DEPOSITS (CR) from cb_money_recoveries
 */
trait ReturnsDeductionsTrait
{
    /**
     * Insert Advance Payments from Landlord Account as DR lines.
     * These are expenses paid by agent on landlord's behalf to be recovered.
     *
     * @param string $returnPeriod YYYYMM format
     * @param string $transactionDate Display date (DD-Mon-YYYY)
     * @param int $generatedBy User ID
     * @param int $returnYear
     * @param int $returnMonth
     * @param int|null $propertyId Optional: Generate for single property only
     * @return int Lines inserted
     */
    protected function insertAdvancePaymentLines(
        string $returnPeriod,
        string $transactionDate,
        int $generatedBy,
        int $returnYear,
        int $returnMonth,
        ?int $propertyId = null
    ): int {
        $propertyFilter = $propertyId ? "AND rc_base.property_id = ?" : "";

        // Bindings: 1) return_period integer, 2) transaction_date, 3) user_id, 
        // 4) return_period for subquery, 5) returnYear, 6) returnMonth, 7) optional property_id
        $bindings = [$returnPeriod, $transactionDate, $generatedBy, $returnPeriod, $returnYear, $returnMonth];
        if ($propertyId) {
            $bindings[] = $propertyId;
        }

        // Recoverable entry type codes from sys_set_invoiceable_items
        // Filter by specific year and month being computed
        // EXCLUDE clients in active merge batches - those get deductions on MASTERRETURN instead
        $advancesInserted = DB::affectingStatement("
            INSERT INTO cb_returns_clients (
                return_code, return_period, transaction_number, transaction_date,
                entry_description, entry_type, dr_cr,
                property_code, net_amount, narration,
                property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id,
                return_id, deduction_point_2, is_commissionable, transaction_source,
                server_time, user_id
            )
            SELECT DISTINCT ON (la.id)
                rc_base.return_code,
                ?::integer,
                la.transaction_number,
                ?,
                COALESCE(ii.item_name, la.entry_description),
                COALESCE(ii.item_name, 'Landlord Account Deduction'),
                'DR',
                rc_base.property_code,
                la.debit_amount,
                COALESCE(la.entry_particulars, la.entry_description),
                rc_base.property_id,
                la.client_id,
                rc_base.zone_id,
                rc_base.field_officer_id,
                rc_base.zone_supervisor_id,
                rc_base.zone_manager_id,
                rc_base.return_id,
                NULL,
                'No',
                'LANDLORD ACCOUNT',
                NOW(),
                ?
            FROM cb_landlord_account la
            LEFT JOIN sys_set_invoiceable_items ii ON ii.billing_code = la.entry_type_code
            JOIN (
                SELECT DISTINCT ON (client_id)
                       return_code, return_id, property_code, property_id, client_id,
                       zone_id, field_officer_id, zone_supervisor_id, zone_manager_id
                FROM cb_returns_clients
                WHERE return_period = ?
                  AND return_code LIKE 'R1%'
                ORDER BY client_id, id
            ) rc_base ON rc_base.client_id = la.client_id
            WHERE la.debit_amount > 0
              AND COALESCE(la.is_recovered, 0) != 1
              AND la.year = ?
              AND la.month = ?
              AND la.entry_type_code IN ('0007','0008','0009','0010','0012','0013','0014','0015','0016','0019','0020','0023','0024','0026','0030','0036')
              -- Exclude landlords in merge batches (deductions go to MASTERRETURN)
              AND NOT EXISTS (
                  SELECT 1 FROM cb_returns_merge_batches mb
                  WHERE mb.landlord_id = la.client_id
              )
              {$propertyFilter}
            ORDER BY la.id
        ", $bindings);

        // Mark the recovered entries in cb_landlord_account (only for the specific year/month)
        // Exclude merge batch landlords - those are handled by MASTERRETURN generation
        if ($advancesInserted > 0) {
            DB::statement("
                UPDATE cb_landlord_account la
                SET is_recovered = 1,
                    recovery_year = ?,
                    recovery_month = ?,
                    return_code = rc.return_code,
                    recovery_server_time = NOW()
                FROM (
                    SELECT DISTINCT ON (client_id)
                           return_code, client_id
                    FROM cb_returns_clients
                    WHERE return_period = ?
                      AND return_code LIKE 'R1%'
                    ORDER BY client_id, id
                ) rc
                WHERE rc.client_id = la.client_id
                  AND la.debit_amount > 0
                  AND COALESCE(la.is_recovered, 0) != 1
                  AND la.year = ?
                  AND la.month = ?
                  AND la.entry_type_code IN ('0007','0008','0009','0010','0012','0013','0014','0015','0016','0019','0020','0023','0024','0026','0030','0036')
                  AND NOT EXISTS (
                      SELECT 1 FROM cb_returns_merge_batches mb
                      WHERE mb.landlord_id = la.client_id
                  )
            ", [$returnYear, $returnMonth, $returnPeriod, $returnYear, $returnMonth]);
        }

        Log::info("ReturnsDeductionsTrait: Advance payment DR lines inserted", [
            'count' => $advancesInserted,
            'property_id' => $propertyId
        ]);

        return $advancesInserted;
    }

    /**
     * Insert Recoveries from cb_money_recoveries as DR lines.
     * These are amounts being recovered from tenants (SAT Deposit, Overpaid, etc.)
     *
     * @param string $returnPeriod YYYYMM format
     * @param string $transactionDate Display date (DD-Mon-YYYY)
     * @param int $generatedBy User ID
     * @param int $returnYear
     * @param int $returnMonth
     * @param int|null $propertyId Optional: Generate for single property only
     * @return int Lines inserted
     */
    protected function insertRecoveryLines(
        string $returnPeriod,
        string $transactionDate,
        int $generatedBy,
        int $returnYear,
        int $returnMonth,
        ?int $propertyId = null
    ): int {
        $propertyFilter = $propertyId ? "AND mr.property_id = ?" : "";

        $bindings = [$returnPeriod, $transactionDate, $generatedBy, $returnPeriod, $returnYear, $returnMonth];
        if ($propertyId) {
            $bindings[] = $propertyId;
        }

        $recoveriesInserted = DB::affectingStatement("
            INSERT INTO cb_returns_clients (
                return_code, return_period, transaction_number, transaction_date,
                entry_description, entry_type, dr_cr,
                property_code, unit_code, tenant_name,
                net_amount, narration,
                property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id, unit_id, tenant_id,
                return_id, deduction_point_2, is_commissionable,
                server_time, user_id
            )
            SELECT
                rc_base.return_code,
                ?::integer,
                COALESCE(mr.transaction_number, ''),
                ?,
                mr.transaction_description,
                'Recoveries',
                'DR',
                rc_base.property_code,
                mr.unit_code,
                mr.tenant_name,
                mr.debit_amount,
                mr.transaction_description,
                mr.property_id,
                mr.client_id,
                mr.zone_id,
                mr.field_officer_id,
                mr.zone_supervisor_id,
                mr.zone_manager_id,
                mr.unit_id,
                mr.tenant_id,
                rc_base.return_id,
                'After Comm',
                'No',
                NOW(),
                ?
            FROM cb_money_recoveries mr
            JOIN LATERAL (
                SELECT return_code, return_id, property_code
                FROM cb_returns_clients
                WHERE return_period = ? AND property_id = mr.property_id
                LIMIT 1
            ) rc_base ON true
            WHERE mr.transaction_class = 'DR'
              AND mr.year = ?
              AND mr.month = ?
              AND mr.return_id IS NULL
              {$propertyFilter}
        ", $bindings);

        Log::info("ReturnsDeductionsTrait: Recovery DR lines inserted", [
            'count' => $recoveriesInserted,
            'property_id' => $propertyId
        ]);

        return $recoveriesInserted;
    }

    /**
     * Insert CR collections from cb_money_recoveries (OTHER PAYMENTS/DEPOSITS).
     * Water Bills, Service Charges, Electricity collected from tenants.
     *
     * @param string $returnPeriod YYYYMM format
     * @param string $transactionDate Display date (DD-Mon-YYYY)
     * @param int $generatedBy User ID
     * @param int $returnYear
     * @param int $returnMonth
     * @param int|null $propertyId Optional: Generate for single property only
     * @return int Lines inserted
     */
    protected function insertCollectionLines(
        string $returnPeriod,
        string $transactionDate,
        int $generatedBy,
        int $returnYear,
        int $returnMonth,
        ?int $propertyId = null
    ): int {
        $propertyFilter = $propertyId ? "AND mr.property_id = ?" : "";

        $bindings = [$returnPeriod, $transactionDate, $generatedBy, $returnPeriod, $returnYear, $returnMonth];
        if ($propertyId) {
            $bindings[] = $propertyId;
        }

        $collectionsInserted = DB::affectingStatement("
            INSERT INTO cb_returns_clients (
                return_code, return_period, transaction_number, transaction_date,
                entry_description, entry_type, dr_cr,
                property_code, unit_code, tenant_name,
                net_amount, amount_payable_landlord, narration,
                property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id, unit_id, tenant_id,
                return_id, is_commissionable, transaction_source,
                server_time, user_id
            )
            SELECT
                rc_base.return_code,
                ?::integer,
                COALESCE(mr.transaction_number, ''),
                ?,
                mr.transaction_type,
                'OTHER PAYMENTS/DEPOSITS',
                'CR',
                rc_base.property_code,
                mr.unit_code,
                mr.tenant_name,
                mr.credit_amount,
                mr.credit_amount,
                mr.transaction_description,
                mr.property_id,
                mr.client_id,
                mr.zone_id,
                mr.field_officer_id,
                mr.zone_supervisor_id,
                mr.zone_manager_id,
                mr.unit_id,
                mr.tenant_id,
                rc_base.return_id,
                'No',
                'TENANT ACCOUNT',
                NOW(),
                ?
            FROM cb_money_recoveries mr
            JOIN LATERAL (
                SELECT return_code, return_id, property_code
                FROM cb_returns_clients
                WHERE return_period = ? AND property_id = mr.property_id
                LIMIT 1
            ) rc_base ON true
            WHERE mr.transaction_class = 'CR'
              AND mr.year = ?
              AND mr.month = ?
              AND mr.return_id IS NULL
              {$propertyFilter}
        ", $bindings);

        Log::info("ReturnsDeductionsTrait: Collection CR lines inserted", [
            'count' => $collectionsInserted,
            'property_id' => $propertyId
        ]);

        return $collectionsInserted;
    }

    /**
     * Mark landlord account entries as recovered after return generation.
     *
     * @param string $returnPeriod YYYYMM format
     * @param int $returnYear
     * @param int $returnMonth
     * @param int|null $propertyId Optional: Mark for single property only
     * @return void
     */
    protected function markLandlordAccountRecovered(
        string $returnPeriod,
        int $returnYear,
        int $returnMonth,
        ?int $propertyId = null
    ): void {
        // Note: This is now handled within insertAdvancePaymentLines()
        // This function is kept for backward compatibility but marking 
        // happens immediately after insertion to ensure consistency.
        // The insertAdvancePaymentLines already marks entries with:
        // - entry_type_code IN ('0009', '0013', '0014')
        // - is_recovered = 0
        // - Joining by client_id (landlord level)

        Log::info("ReturnsDeductionsTrait: Landlord account entries already marked as recovered in step 3", [
            'property_id' => $propertyId
        ]);
    }

    /**
     * Link money recoveries to their return summaries.
     *
     * @param string $returnPeriod YYYYMM format
     * @param int $returnYear
     * @param int $returnMonth
     * @param int|null $propertyId Optional: Link for single property only
     * @return void
     */
    protected function linkMoneyRecoveriesToReturns(
        string $returnPeriod,
        int $returnYear,
        int $returnMonth,
        ?int $propertyId = null
    ): void {
        $propertyFilter = $propertyId ? "AND mr.property_id = ?" : "";

        $bindings = [$returnYear, $returnMonth, $returnPeriod];
        if ($propertyId) {
            $bindings[] = $propertyId;
        }

        DB::statement("
            UPDATE cb_money_recoveries mr
            SET return_id = rs.id,
                is_locked = true
            FROM cb_returns_summary rs
            JOIN sys_pm_properties p ON p.reference_number = rs.property_code
            WHERE mr.year  = ?
              AND mr.month = ?
              AND mr.return_id IS NULL
              AND mr.property_id = p.id
              AND rs.return_period = ?
              AND rs.approved_status = 0
              {$propertyFilter}
        ", $bindings);

        Log::info("ReturnsDeductionsTrait: Money recoveries linked to returns", [
            'property_id' => $propertyId
        ]);
    }

    /**
     * Insert Commission DR summary lines for each return.
     * Creates a single "Commission" DR entry per return with the total commission
     * calculated from all commissionable lines (for backward compatibility with reports).
     *
     * @param string $returnPeriod YYYYMM format
     * @param string $transactionDate Display date (DD-Mon-YYYY)
     * @param int $generatedBy User ID
     * @param int|null $propertyId Optional: Generate for single property only
     * @return int Commission lines inserted
     */
    protected function insertCommissionLines(
        string $returnPeriod,
        string $transactionDate,
        int $generatedBy,
        ?int $propertyId = null
    ): int {
        $propertyFilter = $propertyId ? "AND rc.property_id = ?" : "";

        $bindings = [$returnPeriod, $transactionDate, $generatedBy, $returnPeriod];
        if ($propertyId) {
            $bindings[] = $propertyId;
        }

        $commissionsInserted = DB::affectingStatement("
            INSERT INTO cb_returns_clients (
                return_code, return_period, transaction_number, transaction_date,
                entry_description, entry_type, dr_cr,
                property_code, net_amount, vat_amount, narration,
                property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id,
                return_id, is_commissionable, transaction_source,
                server_time, user_id
            )
            SELECT
                return_code,
                ?::integer,
                '',
                ?,
                'Commission',
                'COMMISSION',
                'DR',
                property_code,
                total_commission,
                total_vat,
                'Computed commission on rent',
                property_id,
                client_id,
                zone_id,
                field_officer_id,
                zone_supervisor_id,
                zone_manager_id,
                return_id,
                'No',
                'COMPUTED',
                NOW(),
                ?
            FROM (
                SELECT
                    rc.return_code,
                    rc.property_code,
                    rc.property_id,
                    rc.client_id,
                    MAX(rc.zone_id) AS zone_id,
                    MAX(rc.field_officer_id) AS field_officer_id,
                    MAX(rc.zone_supervisor_id) AS zone_supervisor_id,
                    MAX(rc.zone_manager_id) AS zone_manager_id,
                    MAX(rc.return_id) AS return_id,
                    COALESCE(SUM(rc.commission_amount), 0) AS total_commission,
                    COALESCE(SUM(rc.vat_amount), 0) AS total_vat
                FROM cb_returns_clients rc
                WHERE rc.return_period = ?
                  AND rc.is_commissionable = 'Yes'
                  AND rc.return_code LIKE 'R1%'
                  {$propertyFilter}
                GROUP BY rc.return_code, rc.property_code, rc.property_id, rc.client_id
                HAVING SUM(rc.commission_amount) > 0
            ) commission_totals
        ", $bindings);

        Log::info("ReturnsDeductionsTrait: Commission DR lines inserted", [
            'count' => $commissionsInserted,
            'property_id' => $propertyId
        ]);

        return $commissionsInserted;
    }
}
