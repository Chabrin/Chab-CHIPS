<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles landlord account entries for returns processing.
 * 
 * Entry Type Codes:
 * - 0005: RETURNS (CR) - Return credit to landlord
 * - 0006: FINAL PAYMENT (DR) - Payment made to landlord
 * - 0008: Opening/Closing Balance
 * - 0009: ADVANCE (DR) - Advance payments to be recovered
 * - 0014: CHEQUE CHARGES (DR)
 * - 0019: BANKING SERVICES
 */
trait ReturnsLandlordAccountTrait
{
    /**
     * Insert Opening Balance B/F entries for each property in returns.
     * This carries forward the previous period's Closing Balance.
     *
     * @param string $returnPeriod YYYYMM format
     * @param string $transactionDate Display date (DD-Mon-YYYY)
     * @param int $generatedBy User ID
     * @param int $returnYear
     * @param int $returnMonth
     * @return int Lines inserted
     */
    protected function insertOpeningBalanceEntries(
        string $returnPeriod,
        string $transactionDate,
        int $generatedBy,
        int $returnYear,
        int $returnMonth
    ): int {
        // Calculate previous period
        $prevMonth = $returnMonth - 1;
        $prevYear  = $returnYear;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }
        $prevPeriod = sprintf('%04d%02d', $prevYear, $prevMonth);

        $openingInserted = DB::affectingStatement("
            INSERT INTO cb_returns_clients (
                return_code, return_period, transaction_number, transaction_date,
                entry_description, entry_type, dr_cr,
                property_code, net_amount, narration,
                property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id,
                return_id, is_commissionable, is_balance_entry,
                server_time, user_id
            )
            SELECT
                rc_base.return_code,
                ?::integer,
                '',
                ?,
                'Opening Balance B/F',
                'BALANCE',
                CASE WHEN prev_bal.balance >= 0 THEN 'CR' ELSE 'DR' END,
                rc_base.property_code,
                ABS(prev_bal.balance),
                'Balance brought forward from previous period',
                rc_base.property_id,
                rc_base.client_id,
                rc_base.zone_id,
                rc_base.field_officer_id,
                rc_base.zone_supervisor_id,
                rc_base.zone_manager_id,
                rc_base.return_id,
                'No',
                true,
                NOW(),
                ?
            FROM (
                -- Get closing balance from previous period
                SELECT DISTINCT ON (property_id)
                    property_id,
                    COALESCE(
                        (SELECT SUM(CASE WHEN dr_cr = 'CR' THEN net_amount ELSE -net_amount END)
                         FROM cb_returns_clients
                         WHERE property_id = rc.property_id
                           AND return_period = ?
                           AND return_code LIKE 'R1%'),
                        0
                    ) AS balance
                FROM cb_returns_clients rc
                WHERE return_period = ?
                  AND return_code LIKE 'R1%'
            ) prev_bal
            JOIN LATERAL (
                SELECT return_code, return_id, property_code, property_id, client_id,
                       zone_id, field_officer_id, zone_supervisor_id, zone_manager_id
                FROM cb_returns_clients
                WHERE return_period = ?
                  AND property_id = prev_bal.property_id
                  AND return_code LIKE 'R1%'
                LIMIT 1
            ) rc_base ON true
            WHERE prev_bal.balance != 0
        ", [
            $returnPeriod,
            $transactionDate,
            $generatedBy,
            $prevPeriod,
            $prevPeriod,
            $returnPeriod
        ]);

        Log::info("ReturnsLandlordAccountTrait: Opening balance entries inserted", ['count' => $openingInserted]);

        return $openingInserted;
    }

    /**
     * Insert Closing Balance entries (calculated field, for display).
     *
     * @param string $returnPeriod YYYYMM format
     * @param string $transactionDate Display date (DD-Mon-YYYY)
     * @param int $generatedBy User ID
     * @return int Lines inserted
     */
    protected function insertClosingBalanceEntries(
        string $returnPeriod,
        string $transactionDate,
        int $generatedBy
    ): int {
        $closingInserted = DB::affectingStatement("
            INSERT INTO cb_returns_clients (
                return_code, return_period, transaction_number, transaction_date,
                entry_description, entry_type, dr_cr,
                property_code, net_amount, narration,
                property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id,
                return_id, is_commissionable, is_balance_entry,
                server_time, user_id
            )
            SELECT
                return_code,
                ?::integer,
                '',
                ?,
                'Closing Balance C/F',
                'BALANCE',
                CASE WHEN running_balance >= 0 THEN 'CR' ELSE 'DR' END,
                property_code,
                ABS(running_balance),
                'Balance to be carried forward',
                property_id,
                client_id,
                zone_id,
                field_officer_id,
                zone_supervisor_id,
                zone_manager_id,
                return_id,
                'No',
                true,
                NOW(),
                ?
            FROM (
                SELECT DISTINCT ON (property_id)
                    return_code,
                    property_code,
                    property_id,
                    client_id,
                    zone_id,
                    field_officer_id,
                    zone_supervisor_id,
                    zone_manager_id,
                    return_id,
                    SUM(
                        CASE WHEN dr_cr = 'CR' THEN COALESCE(net_amount, 0)
                             ELSE -COALESCE(net_amount, 0)
                        END
                    ) OVER (PARTITION BY property_id) AS running_balance
                FROM cb_returns_clients
                WHERE return_period = ?
                  AND return_code LIKE 'R1%'
            ) balances
        ", [$returnPeriod, $transactionDate, $generatedBy, $returnPeriod]);

        Log::info("ReturnsLandlordAccountTrait: Closing balance entries inserted", ['count' => $closingInserted]);

        return $closingInserted;
    }

    /**
     * Create RETURNS credit entry in landlord account after return approval.
     *
     * @param int $returnSummaryId cb_returns_summary.id
     * @param float $creditAmount Amount to credit
     * @param int $createdBy User ID
     * @return void
     */
    protected function createReturnsCreditEntry(
        int $returnSummaryId,
        float $creditAmount,
        int $createdBy
    ): void {
        DB::statement("
            INSERT INTO cb_landlord_account (
                year, month, landlord_code, property_code,
                entry_type_code, entry_description, entry_particulars,
                debit_amount, credit_amount, balance,
                is_recovered, return_code,
                property_id, client_id,
                server_time, user_id
            )
            SELECT
                rs.year,
                rs.month,
                rs.landlord_code,
                rs.property_code,
                '0005',  -- RETURNS entry type
                'RETURNS',
                CONCAT('Return for ', TO_CHAR(TO_DATE(rs.return_period::text, 'YYYYMM'), 'Month YYYY')),
                0,
                ?,
                COALESCE(
                    (SELECT balance FROM cb_landlord_account
                     WHERE client_id = rs.client_id
                       AND property_id = rs.property_id
                     ORDER BY id DESC LIMIT 1),
                    0
                ) + ?,
                1,  -- Already recovered (this is the return itself)
                rs.return_code,
                rs.property_id,
                rs.client_id,
                NOW(),
                ?
            FROM cb_returns_summary rs
            WHERE rs.id = ?
        ", [$creditAmount, $creditAmount, $createdBy, $returnSummaryId]);

        Log::info("ReturnsLandlordAccountTrait: Return credit entry created", [
            'return_id' => $returnSummaryId,
            'amount'    => $creditAmount
        ]);
    }

    /**
     * Get landlord account balance for a property.
     *
     * @param int $propertyId
     * @param int $clientId
     * @return float Current balance
     */
    protected function getLandlordAccountBalance(int $propertyId, int $clientId): float
    {
        $result = DB::selectOne("
            SELECT COALESCE(balance, 0) AS balance
            FROM cb_landlord_account
            WHERE property_id = ?
              AND client_id = ?
            ORDER BY id DESC
            LIMIT 1
        ", [$propertyId, $clientId]);

        return $result ? (float) $result->balance : 0.0;
    }

    /**
     * Get unrecovered landlord account entries for a property and period.
     *
     * @param int $propertyId
     * @param int $year
     * @param int $month
     * @return array Unrecovered entries
     */
    protected function getUnrecoveredEntries(int $propertyId, int $year, int $month): array
    {
        return DB::select("
            SELECT *
            FROM cb_landlord_account
            WHERE property_id = ?
              AND year = ?
              AND month = ?
              AND is_recovered = 0
              AND debit_amount > 0
              AND entry_type_code != '0005'
            ORDER BY id
        ", [$propertyId, $year, $month]);
    }

    /**
     * Insert cheque charges as DR lines in returns.
     *
     * @param string $returnPeriod YYYYMM format
     * @param string $transactionDate Display date (DD-Mon-YYYY)
     * @param int $generatedBy User ID
     * @param int $returnYear
     * @param int $returnMonth
     * @return int Lines inserted
     */
    protected function insertChequeChargeLines(
        string $returnPeriod,
        string $transactionDate,
        int $generatedBy,
        int $returnYear,
        int $returnMonth
    ): int {
        $chargesInserted = DB::affectingStatement("
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
                'Cheque Charges',
                'DR',
                rc_base.property_code,
                la.debit_amount,
                COALESCE(la.entry_particulars, 'Cheque charges'),
                la.property_id,
                la.client_id,
                rc_base.zone_id,
                rc_base.field_officer_id,
                rc_base.zone_supervisor_id,
                rc_base.zone_manager_id,
                rc_base.return_id,
                'After Comm',
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
              AND la.entry_type_code = '0014'
        ", [$returnPeriod, $transactionDate, $generatedBy, $returnPeriod, $returnYear, $returnMonth]);

        Log::info("ReturnsLandlordAccountTrait: Cheque charge DR lines inserted", ['count' => $chargesInserted]);

        return $chargesInserted;
    }

    /**
     * Calculate running balances for landlord account report.
     *
     * @param int $clientId
     * @param int $propertyId
     * @param int $year
     * @param int $month
     * @return array Entries with running balance
     */
    protected function getLandlordAccountStatement(
        int $clientId,
        int $propertyId,
        int $year,
        int $month
    ): array {
        return DB::select("
            SELECT
                la.*,
                SUM(COALESCE(credit_amount, 0) - COALESCE(debit_amount, 0))
                    OVER (ORDER BY la.id) AS running_balance
            FROM cb_landlord_account la
            WHERE la.client_id = ?
              AND la.property_id = ?
              AND (la.year < ? OR (la.year = ? AND la.month <= ?))
            ORDER BY la.id
        ", [$clientId, $propertyId, $year, $year, $month]);
    }

    /**
     * Compute and update landlord account Opening/Closing balances for a return period.
     * This should be called BEFORE pulling balances for returns.
     *
     * Process:
     * 1. Calculate closing balance from previous period
     * 2. Insert/Update Opening Balance B/F entry (entry_type_code = '0008')
     * 3. After all transactions, calculate closing balance
     * 4. Insert/Update Closing Balance C/F entry (entry_type_code = '0008')
     *
     * @param int $returnYear
     * @param int $returnMonth
     * @param int|null $propertyId Optional - compute for single property only
     * @return array Statistics ['opening_updated' => int, 'closing_updated' => int]
     */
    protected function computeLandlordAccountBalances(
        int $returnYear,
        int $returnMonth,
        ?int $propertyId = null
    ): array {
        $propertyFilter = $propertyId ? " AND la.property_id = ?" : "";

        // Calculate previous period
        $prevMonth = $returnMonth - 1;
        $prevYear  = $returnYear;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }

        Log::info("Computing landlord account balances", [
            'year'        => $returnYear,
            'month'       => $returnMonth,
            'prev_year'   => $prevYear,
            'prev_month'  => $prevMonth,
            'property_id' => $propertyId
        ]);

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 1: Remove existing Opening Balance entries for this period
        // ═══════════════════════════════════════════════════════════════════════
        $deleteSql = "
            DELETE FROM cb_landlord_account
            WHERE year = ?
              AND month = ?
              AND entry_type_code = '0008'
              AND entry_description ILIKE '%opening%balance%'
              {$propertyFilter}
        ";
        $deleteBindings = [$returnYear, $returnMonth];
        if ($propertyId) {
            $deleteBindings[] = $propertyId;
        }

        $deletedOpening = DB::affectingStatement($deleteSql, $deleteBindings);
        Log::info("Removed existing opening balance entries", ['count' => $deletedOpening]);

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 2: Insert new Opening Balance B/F entries
        // Based on previous period's closing balance
        // ═══════════════════════════════════════════════════════════════════════
        $openingSql = "
            INSERT INTO cb_landlord_account (
                year, month, landlord_code, property_code,
                entry_type_code, entry_sequence, entry_description,
                entry_date, debit_amount, credit_amount,
                entry_particulars, is_recovered,
                property_id, client_id, server_time
            )
            SELECT 
                ? AS year,
                ? AS month,
                p.landlord_code,
                p.property_code,
                '0008' AS entry_type_code,
                1 AS entry_sequence,
                'Opening Balance B/F' AS entry_description,
                DATE_TRUNC('month', MAKE_DATE(?, ?, 1)) AS entry_date,
                CASE WHEN prev_balance.balance < 0 THEN ABS(prev_balance.balance) ELSE 0 END AS debit_amount,
                CASE WHEN prev_balance.balance >= 0 THEN prev_balance.balance ELSE 0 END AS credit_amount,
                'Balance brought forward from ' || TO_CHAR(MAKE_DATE(?, ?, 1), 'Month YYYY') AS entry_particulars,
                1 AS is_recovered,
                p.property_id,
                p.client_id,
                NOW()
            FROM (
                -- Calculate closing balance for each property from previous period
                SELECT 
                    la.property_id,
                    la.client_id,
                    SUM(COALESCE(la.credit_amount, 0) - COALESCE(la.debit_amount, 0)) AS balance
                FROM cb_landlord_account la
                WHERE (la.year < ? OR (la.year = ? AND la.month <= ?))
                  AND la.entry_type_code != '0008'  -- Exclude balance entries
                  {$propertyFilter}
                GROUP BY la.property_id, la.client_id
                HAVING ABS(SUM(COALESCE(la.credit_amount, 0) - COALESCE(la.debit_amount, 0))) > 0.001
            ) prev_balance
            JOIN LATERAL (
                -- Get property and client details
                SELECT 
                    prop.id AS property_id,
                    prop.reference_number AS property_code,
                    c.reference_number AS landlord_code,
                    c.id AS client_id
                FROM sys_pm_properties prop
                JOIN sys_pm_clients c ON c.id = prop.client_id
                WHERE prop.id = prev_balance.property_id
            ) p ON true
        ";

        $openingBindings = [
            $returnYear,
            $returnMonth,           // year, month
            $returnYear,
            $returnMonth,           // entry_date
            $prevYear,
            $prevMonth,               // entry_particulars
            $prevYear,
            $prevYear,
            $prevMonth     // previous period filter
        ];
        if ($propertyId) {
            $openingBindings[] = $propertyId;
        }

        $openingInserted = DB::affectingStatement($openingSql, $openingBindings);
        Log::info("Inserted opening balance entries", ['count' => $openingInserted]);

        return [
            'deleted_old'      => $deletedOpening,
            'opening_inserted' => $openingInserted
        ];
    }

    /**
     * Compute and insert Closing Balance C/F entries for a return period.
     * This should be called AFTER all return lines have been processed.
     *
     * @param int $returnYear
     * @param int $returnMonth
     * @param int|null $propertyId Optional - compute for single property only
     * @return int Number of closing balance entries inserted
     */
    protected function computeClosingBalances(
        int $returnYear,
        int $returnMonth,
        ?int $propertyId = null
    ): int {
        $propertyFilter = $propertyId ? " AND la.property_id = ?" : "";

        Log::info("Computing closing balances", [
            'year'        => $returnYear,
            'month'       => $returnMonth,
            'property_id' => $propertyId
        ]);

        // Remove existing Closing Balance entries for this period
        $deleteSql = "
            DELETE FROM cb_landlord_account
            WHERE year = ?
              AND month = ?
              AND entry_type_code = '0008'
              AND entry_description ILIKE '%closing%balance%'
              {$propertyFilter}
        ";
        $deleteBindings = [$returnYear, $returnMonth];
        if ($propertyId) {
            $deleteBindings[] = $propertyId;
        }

        $deletedClosing = DB::affectingStatement($deleteSql, $deleteBindings);
        Log::info("Removed existing closing balance entries", ['count' => $deletedClosing]);

        // Insert new Closing Balance C/F entries
        $closingSql = "
            INSERT INTO cb_landlord_account (
                year, month, landlord_code, property_code,
                entry_type_code, entry_sequence, entry_description,
                entry_date, debit_amount, credit_amount,
                entry_particulars, is_recovered,
                property_id, client_id, server_time
            )
            SELECT 
                ? AS year,
                ? AS month,
                p.landlord_code,
                p.property_code,
                '0008' AS entry_type_code,
                999 AS entry_sequence,  -- High sequence to appear last
                'Closing Balance C/F' AS entry_description,
                (DATE_TRUNC('month', MAKE_DATE(?, ?, 1)) + INTERVAL '1 month - 1 day')::date AS entry_date,
                CASE WHEN current_balance.balance < 0 THEN ABS(current_balance.balance) ELSE 0 END AS debit_amount,
                CASE WHEN current_balance.balance >= 0 THEN current_balance.balance ELSE 0 END AS credit_amount,
                'Balance to be carried forward to ' || TO_CHAR(MAKE_DATE(?, ?, 1) + INTERVAL '1 month', 'Month YYYY') AS entry_particulars,
                1 AS is_recovered,
                p.property_id,
                p.client_id,
                NOW()
            FROM (
                -- Calculate running balance for each property up to and including current period
                SELECT 
                    la.property_id,
                    la.client_id,
                    SUM(COALESCE(la.credit_amount, 0) - COALESCE(la.debit_amount, 0)) AS balance
                FROM cb_landlord_account la
                WHERE (la.year < ? OR (la.year = ? AND la.month <= ?))
                  AND la.entry_type_code != '0008'  -- Exclude balance entries in calculation
                  {$propertyFilter}
                GROUP BY la.property_id, la.client_id
            ) current_balance
            JOIN LATERAL (
                -- Get property and client details
                SELECT 
                    prop.id AS property_id,
                    prop.reference_number AS property_code,
                    c.reference_number AS landlord_code,
                    c.id AS client_id
                FROM sys_pm_properties prop
                JOIN sys_pm_clients c ON c.id = prop.client_id
                WHERE prop.id = current_balance.property_id
            ) p ON true
        ";

        // Calculate next month for entry_particulars
        $nextMonth = $returnMonth + 1;
        $nextYear  = $returnYear;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }

        $closingBindings = [
            $returnYear,
            $returnMonth,           // year, month
            $returnYear,
            $returnMonth,           // entry_date
            $nextYear,
            $nextMonth,               // entry_particulars (next month)
            $returnYear,
            $returnYear,
            $returnMonth  // current period filter
        ];
        if ($propertyId) {
            $closingBindings[] = $propertyId;
        }

        $closingInserted = DB::affectingStatement($closingSql, $closingBindings);
        Log::info("Inserted closing balance entries", ['count' => $closingInserted]);

        return $closingInserted;
    }

    /**
     * Get the computed Opening Balance for a property in a given period.
     *
     * @param int $propertyId
     * @param int $year
     * @param int $month
     * @return float|null Opening balance amount (null if not found)
     */
    protected function getOpeningBalance(int $propertyId, int $year, int $month): ?float
    {
        $result = DB::selectOne("
            SELECT 
                CASE WHEN credit_amount > 0 THEN credit_amount ELSE -debit_amount END AS balance
            FROM cb_landlord_account
            WHERE property_id = ?
              AND year = ?
              AND month = ?
              AND entry_type_code = '0008'
              AND entry_description ILIKE '%opening%balance%'
            LIMIT 1
        ", [$propertyId, $year, $month]);

        return $result ? (float) $result->balance : null;
    }

    /**
     * Get the computed Closing Balance for a property in a given period.
     *
     * @param int $propertyId
     * @param int $year
     * @param int $month
     * @return float|null Closing balance amount (null if not found)
     */
    protected function getClosingBalance(int $propertyId, int $year, int $month): ?float
    {
        $result = DB::selectOne("
            SELECT 
                CASE WHEN credit_amount > 0 THEN credit_amount ELSE -debit_amount END AS balance
            FROM cb_landlord_account
            WHERE property_id = ?
              AND year = ?
              AND month = ?
              AND entry_type_code = '0008'
              AND entry_description ILIKE '%closing%balance%'
            LIMIT 1
        ", [$propertyId, $year, $month]);

        return $result ? (float) $result->balance : null;
    }

    /**
     * Recalculate all balances for a property across all periods.
     * Useful for fixing balance discrepancies.
     *
     * @param int $propertyId
     * @return array Summary of balances recalculated
     */
    protected function recalculatePropertyBalances(int $propertyId): array
    {
        // Get all distinct periods for this property
        $periods = DB::select("
            SELECT DISTINCT year, month
            FROM cb_landlord_account
            WHERE property_id = ?
            ORDER BY year, month
        ", [$propertyId]);

        $results = [];

        foreach ($periods as $period) {
            $this->computeLandlordAccountBalances(
                (int) $period->year,
                (int) $period->month,
                $propertyId
            );

            $closing = $this->computeClosingBalances(
                (int) $period->year,
                (int) $period->month,
                $propertyId
            );

            $results[] = [
                'year'    => $period->year,
                'month'   => $period->month,
                'closing' => $closing
            ];
        }

        Log::info("Recalculated balances for property", [
            'property_id' => $propertyId,
            'periods'     => count($results)
        ]);

        return $results;
    }
}
