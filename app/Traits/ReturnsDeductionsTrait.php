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
        $propertyFilter = $propertyId ? "AND la.property_id = ?" : "";

        $bindings = [$returnPeriod, $transactionDate, $generatedBy, $returnPeriod, $returnYear, $returnMonth];
        if ($propertyId) {
            $bindings[] = $propertyId;
        }

        $advancesInserted = DB::affectingStatement("
            INSERT INTO cb_returns_clients (
                return_code, return_period, transaction_number, transaction_date,
                entry_description, entry_type, dr_cr,
                property_code, net_amount, narration,
                property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id,
                return_id, deduction_point_2, is_commissionable,
                server_time, user_id
            )
            SELECT
                rc_base.return_code,
                ?::integer,
                '',
                ?,
                la.entry_description,
                'Advance Payments-From Landlord Acc',
                'DR',
                rc_base.property_code,
                la.debit_amount,
                COALESCE(la.entry_particulars, la.entry_description),
                la.property_id,
                la.client_id,
                rc_base.zone_id,
                rc_base.field_officer_id,
                rc_base.zone_supervisor_id,
                rc_base.zone_manager_id,
                rc_base.return_id,
                NULL,
                'No',
                NOW(),
                ?
            FROM cb_landlord_account la
            JOIN LATERAL (
                SELECT return_code, return_id, property_code,
                       zone_id, field_officer_id, zone_supervisor_id, zone_manager_id
                FROM cb_returns_clients
                WHERE return_period = ? AND property_id = la.property_id
                LIMIT 1
            ) rc_base ON true
            WHERE la.debit_amount > 0
              AND la.is_recovered = 0
              AND la.year = ?
              AND la.month = ?
              AND la.entry_type_code != '0005'
              {$propertyFilter}
        ", $bindings);

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
        $propertyFilter = $propertyId ? "AND la.property_id = ?" : "";

        $bindings = [$returnYear, $returnMonth, $returnPeriod, $returnYear, $returnMonth];
        if ($propertyId) {
            $bindings[] = $propertyId;
        }

        DB::statement("
            UPDATE cb_landlord_account la
            SET is_recovered = 1,
                recovery_year        = ?,
                recovery_month       = ?,
                return_code          = rc_base.return_code,
                recovery_server_time = NOW()
            FROM (
                SELECT DISTINCT ON (property_id) property_id, return_code
                FROM cb_returns_clients
                WHERE return_period = ?
                  AND return_code LIKE 'R1%'
                ORDER BY property_id, return_code
            ) rc_base
            WHERE la.property_id = rc_base.property_id
              AND la.debit_amount > 0
              AND la.is_recovered = 0
              AND la.year  = ?
              AND la.month = ?
              AND la.entry_type_code != '0005'
              {$propertyFilter}
        ", $bindings);

        Log::info("ReturnsDeductionsTrait: Landlord account entries marked as recovered", [
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
}
