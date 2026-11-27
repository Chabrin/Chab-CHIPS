<?php

namespace App\Traits;

use App\Services\InvoiceBillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles creation of return summary records in cb_returns_summary.
 * Aggregates line items from cb_returns_clients into summaries.
 */
trait ReturnsSummaryTrait
{
    /**
     * Insert return summaries (MAINRETURN, or MINIRETURN for a mini return) using the original aggregation logic.
     * Kept separate to ensure accurate calculations.
     *
     * @param string $returnPeriod YYYYMM format
     * @param int $returnYear
     * @param int $returnMonthNum
     * @param string $monthName
     * @param string $preparedByName name of the user who generated the returns
     * @param int|null $propertyId Optional: summarise a single property only
     * @param string $returnType MAINRETURN or MINIRETURN; the main utility return (code RU1...) is always UTILITYRETURN
     * @param string|array|null $returnCode Optional: summarise the lines of this return code, or of these codes, only (a mini return)
     * @return int Summaries inserted
     */
    protected function insertMainReturnSummariesLegacy(string $returnPeriod, int $returnYear, int $returnMonthNum, string $monthName, string $preparedByName, ?int $propertyId = null, string $returnType = 'MAINRETURN', string|array|null $returnCode = null): int {
        $propertyFilter = $propertyId ? " AND rc.property_id = ?" : "";
        $returnCodes = $returnCode === null ? null : array_values((array) $returnCode);
        $codeFilter = $returnCodes !== null ? " AND rc.return_code IN (" . implode(', ', array_fill(0, count($returnCodes), '?')) . ")" : "";
        $ownershipRateFilter = '';
        if (\Illuminate\Support\Facades\Schema::hasTable('property_ownership_transfers')) {
            $ownershipRateFilter = "AND (NOT EXISTS (SELECT 1 FROM property_ownership_transfers ot WHERE ot.property_id = rc.property_id AND ot.status = 'completed')
                OR ((sla.client_d = rc.client_id OR EXISTS (SELECT 1 FROM property_ownership_transfers ot WHERE ot.old_sla_id = sla.id AND ot.from_client_id = rc.client_id AND ot.status = 'completed'))
                    AND (sla.date_start IS NULL OR sla.date_start::date <= TO_DATE('{$returnPeriod}', 'YYYYMM'))))";
        }

        // The rent lines already carry their commission and VAT, and insertCommissionLines adds a DR 'COMMISSION' line
        // that repeats the same two totals for the printed return. That line is left out of every total below, or the
        // commission and the VAT would be taken off the landlord twice.
        $vatRate = InvoiceBillingService::VAT_RATE;
        $netAmount = "SUM(CASE WHEN rc.dr_cr = 'CR' THEN rc.net_amount ELSE 0 END)
                    - SUM(rc.commission_amount) - SUM(rc.vat_amount)
                    - SUM(CASE WHEN rc.dr_cr = 'DR' THEN rc.net_amount ELSE 0 END)";

        $q3 = "
            INSERT INTO cb_returns_summary (
                return_code, landlord_code, property_code, year, month,
                return_description, return_period, return_type,
                commission_rate, total_credit, commission_total, vatable_total,
                commission_amount, total_debit, net_amount,
                transaction_amount, net_balance,
                prepared_by, prepared_status, prepared_date,
                approved_status, paid_status, return_id,
                payable_amount, non_payable_amount, payable_units, total_units
            )
            SELECT
                rc.return_code,
                COALESCE(
                    (SELECT cl.reference_number FROM sys_pm_clients cl WHERE cl.id = rc.client_id LIMIT 1),
                    'UNKNOWN'
                ) AS landlord_code,
                rc.property_code,
                ? AS year,
                ? AS month,
                COALESCE(
                    (SELECT pp.property_name FROM sys_pm_properties pp WHERE pp.id = rc.property_id LIMIT 1),
                    rc.property_code
                ) || '-{$monthName} {$returnYear}' || CASE WHEN rc.return_code LIKE 'RU%' THEN ' - Utilities' ELSE '' END AS return_description,
                ? AS return_period,
                CASE WHEN rc.return_code LIKE 'RU1%' THEN 'UTILITYRETURN' ELSE ? END AS return_type,
                COALESCE(
                    (SELECT sla.commission_percentage FROM sys_pm_service_agreements sla WHERE sla.property_id = rc.property_id {$ownershipRateFilter} ORDER BY sla.date_start DESC NULLS LAST, sla.id DESC LIMIT 1),
                    CASE WHEN SUM(rc.commission_amount) > 0
                              AND SUM(CASE WHEN rc.dr_cr = 'CR' THEN rc.net_amount ELSE 0 END) > 0
                        THEN ROUND(SUM(rc.commission_amount) / SUM(CASE WHEN rc.dr_cr = 'CR' THEN rc.net_amount ELSE 0 END) * 100, 2)
                        ELSE 0
                    END
                ) AS commission_rate,
                SUM(CASE WHEN rc.dr_cr = 'CR' THEN rc.net_amount ELSE 0 END) AS total_credit,
                -- The commissionable amount, as the old system stored it: rent and arrears less the VAT inside the rent of VAT units
                SUM(CASE WHEN rc.dr_cr = 'CR' AND rc.is_commissionable = 'Yes'
                         THEN rc.net_amount - CASE WHEN rc.is_vatable = 'Yes' THEN ROUND(rc.net_amount * {$vatRate} / (100 + {$vatRate}), 2) ELSE 0 END
                         ELSE 0 END) AS commission_total,
                -- The VAT-inclusive rent of VAT units (the old system's vatable_total)
                SUM(CASE WHEN rc.dr_cr = 'CR' AND rc.is_vatable = 'Yes' THEN rc.net_amount ELSE 0 END) AS vatable_total,
                SUM(rc.commission_amount) AS commission_amount,
                SUM(rc.commission_amount) + SUM(rc.vat_amount)
                    + SUM(CASE WHEN rc.dr_cr = 'DR' THEN rc.net_amount ELSE 0 END) AS total_debit,
                {$netAmount} AS net_amount,
                0 AS transaction_amount,
                {$netAmount} AS net_balance,
                ? AS prepared_by,
                1 AS prepared_status,
                NOW() AS prepared_date,
                0 AS approved_status,
                0 AS paid_status,
                rc.return_id,
                SUM(CASE WHEN rc.entry_type = 'RENT CURRENT' AND rc.payable_status IN (1, 2, 3) THEN rc.net_amount ELSE 0 END) AS payable_amount,
                SUM(CASE WHEN rc.entry_type = 'RENT CURRENT' AND (rc.payable_status = 0 OR rc.payable_status IS NULL) THEN rc.amount_payable_landlord ELSE 0 END) AS non_payable_amount,
                COUNT(DISTINCT CASE WHEN rc.entry_type = 'RENT CURRENT' AND rc.payable_status IN (1, 2, 3) THEN rc.unit_id END) AS payable_units,
                COUNT(DISTINCT CASE WHEN rc.entry_type = 'RENT CURRENT' THEN rc.unit_id END) AS total_units
            FROM cb_returns_clients rc
            WHERE rc.return_period = ?
              AND COALESCE(rc.entry_type, '') <> 'COMMISSION'
              {$propertyFilter}
              {$codeFilter}
              AND NOT EXISTS (
                  SELECT 1 FROM cb_returns_summary rs WHERE rs.return_code = rc.return_code
              )
            GROUP BY rc.return_code, rc.property_code, rc.property_id, rc.client_id, rc.return_id
        ";

        $bindings = [$returnYear, $returnMonthNum, $returnPeriod, $returnType, $preparedByName, $returnPeriod];
        if ($propertyId) {
            $bindings[] = $propertyId;
        }
        foreach ($returnCodes ?? [] as $code) {
            $bindings[] = $code;
        }

        return DB::affectingStatement($q3, $bindings);
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
     * Delete unapproved returns for regeneration.
     * Only deletes returns where approved_status != 1.
     * Also resets landlord account recovery flags.
     * Also deletes MASTERRETURNs when their child MAINRETURNs are being deleted.
     *
     * @param string $returnPeriod YYYYMM format
     * @param int|null $propertyId Optional: Delete for single property only
     * @return array Counts of deleted records
     */
    protected function deleteUnapprovedReturnsForPeriod(string $returnPeriod, ?int $propertyId = null): array
    {
        // Protect a processed property return and its processed merged parent before deleting any lines.
        if ($this->approvedReturnsExistForPeriod($returnPeriod, $propertyId)) {
            throw new \RuntimeException('Approved or paid returns cannot be regenerated. Record any correction in a new return.');
        }
        // Get return codes to delete (for resetting landlord account)
        if ($propertyId) {
            $returnsToDelete = DB::select("
                SELECT rs.return_code, rs.parent_return_code
                FROM cb_returns_summary rs
                JOIN sys_pm_properties p ON p.reference_number = rs.property_code
                WHERE rs.return_period = ?
                  AND rs.approved_status != 1
                  AND p.id = ?
            ", [$returnPeriod, $propertyId]);
        } else {
            $returnsToDelete = DB::select("
                SELECT return_code, parent_return_code
                FROM cb_returns_summary
                WHERE return_period = ?
                  AND approved_status != 1
            ", [$returnPeriod]);
        }

        $returnCodes = array_map(fn($r) => $r->return_code, $returnsToDelete);

        // Also add parent MASTERRETURNs (from parent_return_code) that should be deleted
        foreach ($returnsToDelete as $r) {
            if (!empty($r->parent_return_code) && !in_array($r->parent_return_code, $returnCodes)) {
                $returnCodes[] = $r->parent_return_code;
            }
        }

        // A pending legacy master may belong to another batch of the same landlord. Only explicitly linked
        // parents above are candidates; never infer deletions from landlord and month alone.

        if (empty($returnCodes)) {
            return ['clients' => 0, 'summaries' => 0, 'landlord_reset' => 0];
        }

        $placeholders = implode(',', array_fill(0, count($returnCodes), '?'));

        // Delete cb_returns_clients lines
        $clientsDeleted = DB::delete("
            DELETE FROM cb_returns_clients
            WHERE return_period = ?
              AND return_code IN ({$placeholders})
        ", array_merge([$returnPeriod], $returnCodes));

        // Their approval items go too, or they are left behind pointing at returns that no longer exist
        $this->deleteApprovalItemsForReturns($returnPeriod, $returnCodes);

        // Delete cb_returns_summary entries (both MAINRETURN and MASTERRETURN)
        $summariesDeleted = DB::delete("
            DELETE FROM cb_returns_summary
            WHERE return_period = ?
              AND approved_status != 1
              AND return_code IN ({$placeholders})
        ", array_merge([$returnPeriod], $returnCodes));

        // Reset landlord account recovery flags
        $landlordReset = DB::affectingStatement("
            UPDATE cb_landlord_account
            SET is_recovered = 0,
                recovery_year = NULL,
                recovery_month = NULL,
                return_code = NULL,
                recovery_server_time = NULL
            WHERE return_code IN ({$placeholders})
        ", $returnCodes);

        Log::info("ReturnsSummaryTrait: Deleted unapproved returns for regeneration", [
            'period' => $returnPeriod,
            'property_id' => $propertyId,
            'return_codes' => $returnCodes,
            'clients_deleted' => $clientsDeleted,
            'summaries_deleted' => $summariesDeleted,
            'landlord_reset' => $landlordReset
        ]);

        return [
            'clients' => $clientsDeleted,
            'summaries' => $summariesDeleted,
            'landlord_reset' => $landlordReset
        ];
    }

    /**
     * Delete the approval items (with their approvers and actions) of unapproved returns that are about to be deleted.
     *
     * @param string $returnPeriod YYYYMM format
     * @param array $returnCodes return codes of the returns being deleted
     * @return void
     */
    protected function deleteApprovalItemsForReturns(string $returnPeriod, array $returnCodes): void
    {
        if (empty($returnCodes)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($returnCodes), '?'));
        $items = "SELECT ai.id FROM cb_approval_items ai
            JOIN cb_returns_summary rs ON rs.id = ai.item_id
            WHERE ai.item_type = 'return_summary'
              AND rs.return_period = ?
              AND rs.approved_status != 1
              AND rs.return_code IN ({$placeholders})";
        $bindings = array_merge([$returnPeriod], $returnCodes);

        DB::delete("DELETE FROM cb_approval_actions WHERE approval_item_id IN ({$items})", $bindings);
        DB::delete("DELETE FROM cb_item_approvers WHERE approval_item_id IN ({$items})", $bindings);
        DB::delete("DELETE FROM cb_approval_items WHERE id IN ({$items})", $bindings);
    }

    /**
     * Check if APPROVED returns exist for a period (optionally for a specific property).
     * Unapproved returns can be deleted and regenerated.
     *
     * @param string $returnPeriod YYYYMM format
     * @param int|null $propertyId Optional: Check for single property only
     * @return bool
     */
    protected function approvedReturnsExistForPeriod(string $returnPeriod, ?int $propertyId = null): bool
    {
        if ($propertyId) {
            $count = DB::selectOne("
                SELECT COUNT(*) AS cnt
                FROM cb_returns_summary rs
                JOIN sys_pm_properties p ON p.reference_number = rs.property_code
                WHERE rs.return_period = ?
                  AND (rs.approved_status = 1 OR rs.paid_status = 1 OR COALESCE(rs.is_merged, FALSE) = TRUE
                    OR COALESCE(rs.parent_return_code, '') <> '' OR EXISTS (
                      SELECT 1 FROM cb_returns_summary parent
                      WHERE parent.return_code = rs.parent_return_code
                        AND (parent.approved_status = 1 OR parent.paid_status = 1)
                  ))
                  AND p.id = ?
            ", [$returnPeriod, $propertyId]);
        } else {
            $count = DB::selectOne("
                SELECT COUNT(*) AS cnt
                FROM cb_returns_summary
                WHERE return_period = ?
                  AND (approved_status = 1 OR paid_status = 1)
            ", [$returnPeriod]);
        }

        return $count->cnt > 0;
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
