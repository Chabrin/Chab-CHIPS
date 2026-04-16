<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles creation of return summary records in cb_returns_summary.
 * Aggregates line items from cb_returns_clients into summaries.
 */
trait ReturnsSummaryTrait
{
    /**
     * Insert MAINRETURN summary records.
     * Aggregates all lines for each property from cb_returns_clients.
     *
     * @param string $returnPeriod YYYYMM format
     * @param string $transactionDate Display date (DD-Mon-YYYY)
     * @param int $generatedBy User ID
     * @param int $returnYear
     * @param int $returnMonth
     * @return int Summaries inserted
     */
    protected function insertMainReturnSummaries(
        string $returnPeriod,
        string $transactionDate,
        int $generatedBy,
        int $returnYear,
        int $returnMonth
    ): int {
        $summariesInserted = DB::affectingStatement("
            INSERT INTO cb_returns_summary (
                return_code, return_period, return_date,
                property_code, property_name, landlord_name, landlord_code,
                total_returns, rent_amount, amount_withheld,
                gross_commission, vat_commission, net_commission_paid,
                deductions, other_collections, net_amount_payable,
                return_type, approved_status,
                property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id,
                year, month,
                server_time, user_id
            )
            SELECT
                rc.return_code,
                ?::integer,
                ?,
                rc.property_code,
                p.property_name,
                CONCAT_WS(' ', c.first_name, c.last_name),
                c.reference_number,
                -- total_returns: Sum all CR lines
                SUM(CASE WHEN rc.dr_cr = 'CR' THEN COALESCE(rc.net_amount, 0) ELSE 0 END),
                -- rent_amount: Sum only RENT lines (commissionable items)
                SUM(CASE WHEN rc.is_commissionable = 'Yes' THEN COALESCE(rc.net_amount, 0) ELSE 0 END),
                -- amount_withheld: Sum CR lines where payable_status = 0 (below threshold)
                SUM(CASE WHEN rc.dr_cr = 'CR' AND rc.payable_status = 0 THEN COALESCE(rc.amount_payable_landlord, 0) ELSE 0 END),
                -- gross_commission
                SUM(CASE WHEN rc.is_commissionable = 'Yes' THEN COALESCE(rc.commission_amount, 0) ELSE 0 END),
                -- vat_commission (16% of gross)
                SUM(CASE WHEN rc.is_commissionable = 'Yes' THEN COALESCE(rc.vat_amount, 0) ELSE 0 END),
                -- net_commission_paid (gross + VAT)
                SUM(CASE WHEN rc.is_commissionable = 'Yes' THEN COALESCE(rc.commission_amount, 0) + COALESCE(rc.vat_amount, 0) ELSE 0 END),
                -- deductions: Sum DR lines
                SUM(CASE WHEN rc.dr_cr = 'DR' THEN COALESCE(rc.net_amount, 0) ELSE 0 END),
                -- other_collections: Sum non-rent CR lines
                SUM(CASE WHEN rc.dr_cr = 'CR' AND rc.is_commissionable = 'No' THEN COALESCE(rc.net_amount, 0) ELSE 0 END),
                -- net_amount_payable: Total CR - commissions - deductions
                SUM(CASE WHEN rc.dr_cr = 'CR' THEN COALESCE(rc.net_amount, 0) ELSE 0 END)
                - SUM(CASE WHEN rc.is_commissionable = 'Yes' THEN COALESCE(rc.commission_amount, 0) + COALESCE(rc.vat_amount, 0) ELSE 0 END)
                - SUM(CASE WHEN rc.dr_cr = 'DR' THEN COALESCE(rc.net_amount, 0) ELSE 0 END),
                'MAINRETURN',
                0, -- approved_status (pending)
                rc.property_id,
                rc.client_id,
                rc.zone_id,
                rc.field_officer_id,
                rc.zone_supervisor_id,
                rc.zone_manager_id,
                ?,
                ?,
                NOW(),
                ?
            FROM cb_returns_clients rc
            JOIN sys_pm_properties p ON p.id = rc.property_id
            JOIN cb_clients c         ON c.id = rc.client_id
            WHERE rc.return_period = ?
              AND rc.return_code LIKE 'R1%'
            GROUP BY
                rc.return_code,
                rc.property_code,
                p.property_name,
                c.first_name,
                c.last_name,
                c.reference_number,
                rc.property_id,
                rc.client_id,
                rc.zone_id,
                rc.field_officer_id,
                rc.zone_supervisor_id,
                rc.zone_manager_id
        ", [$returnPeriod, $transactionDate, $returnYear, $returnMonth, $generatedBy, $returnPeriod]);

        Log::info("ReturnsSummaryTrait: MAINRETURN summaries inserted", ['count' => $summariesInserted]);

        return $summariesInserted;
    }

    /**
     * Get return summary statistics for a period.
     *
     * @param string $returnPeriod YYYYMM format
     * @return object Summary statistics
     */
    protected function getReturnSummaryStats(string $returnPeriod): object
    {
        return DB::selectOne("
            SELECT
                COUNT(*)                           AS total_returns,
                COUNT(CASE WHEN return_type = 'MAINRETURN' THEN 1 END)   AS main_returns,
                COUNT(CASE WHEN return_type = 'MASTERRETURN' THEN 1 END) AS master_returns,
                COALESCE(SUM(total_returns), 0)       AS gross_total,
                COALESCE(SUM(net_amount_payable), 0)  AS net_payable,
                COALESCE(SUM(net_commission_paid), 0) AS total_commission,
                COALESCE(SUM(deductions), 0)          AS total_deductions
            FROM cb_returns_summary
            WHERE return_period = ?
        ", [$returnPeriod]);
    }

    /**
     * Link cb_returns_clients lines to their summary by return_id.
     *
     * @param string $returnPeriod YYYYMM format
     * @return void
     */
    protected function linkReturnLinesToSummary(string $returnPeriod): void
    {
        DB::statement("
            UPDATE cb_returns_clients rc
            SET return_id = rs.id
            FROM cb_returns_summary rs
            WHERE rc.return_period = ?
              AND rc.return_code   = rs.return_code
              AND rc.return_id    IS NULL
        ", [$returnPeriod]);

        Log::info("ReturnsSummaryTrait: Lines linked to summaries");
    }

    /**
     * Delete all return data for a period (for re-generation).
     *
     * @param string $returnPeriod YYYYMM format
     * @return void
     */
    protected function deleteReturnsForPeriod(string $returnPeriod): void
    {
        // Delete in correct order (children first)
        DB::statement("DELETE FROM cb_returns_clients WHERE return_period = ?", [$returnPeriod]);
        DB::statement("DELETE FROM cb_returns_summary WHERE return_period = ?", [$returnPeriod]);

        Log::info("ReturnsSummaryTrait: Deleted existing returns", ['period' => $returnPeriod]);
    }

    /**
     * Check if returns exist for a period (optionally for a specific property).
     *
     * @param string $returnPeriod YYYYMM format
     * @param int|null $propertyId Optional: Check for single property only
     * @return bool
     */
    protected function returnsExistForPeriod(string $returnPeriod, ?int $propertyId = null): bool
    {
        if ($propertyId) {
            // Check for specific property using property_code from sys_pm_properties
            $count = DB::selectOne("
                SELECT COUNT(*) AS cnt
                FROM cb_returns_summary rs
                JOIN sys_pm_properties p ON p.reference_number = rs.property_code
                WHERE rs.return_period = ?
                  AND p.id = ?
            ", [$returnPeriod, $propertyId]);
        } else {
            $count = DB::selectOne("
                SELECT COUNT(*) AS cnt
                FROM cb_returns_summary
                WHERE return_period = ?
            ", [$returnPeriod]);
        }

        return $count->cnt > 0;
    }
}
