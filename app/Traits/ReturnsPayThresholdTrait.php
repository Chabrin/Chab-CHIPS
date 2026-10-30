<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles updating pay percentage and payable status for rent lines.
 * 
 * The 85% threshold logic:
 * - If tenant paid >= 85% of PREVIOUS month's invoice, they are payable (status 1)
 * - If no previous invoice exists (new tenant), they are payable (status 2)
 * - If previous invoice paid < 85%, they are NOT payable (status 0) and net_amount = 0
 */
trait ReturnsPayThresholdTrait
{
    /**
     * Update pay_percentage, payable_status, and net_amount for rent lines.
     * Non-payable tenants get net_amount = 0 (their rent is withheld).
     *
     * @param string $returnPeriod YYYYMM format
     * @param string $monthStart First day of return month (YYYY-MM-DD)
     * @param string $prevMonthStart First day of previous month (YYYY-MM-DD)
     * @param int|null $propertyId Optional: Update for single property only
     * @return void
     */
    protected function updatePayThresholds(
        string $returnPeriod,
        string $monthStart,
        string $prevMonthStart,
        ?int $propertyId = null
    ): void {
        $propertyFilter = $propertyId ? "AND rc2.property_id = ?" : "";

        // The rule looks at the tenant's earlier RENT invoices only: an unpaid deposit or service charge must not hold
        // back the rent. Legacy invoices have no item_type_id, so the item is matched by its stored name as well.
        $rentItemId = "(SELECT id FROM sys_set_invoiceable_items WHERE billing_code = '0024')";
        $rentOlder = \App\Services\InvoiceBillingService::sameItemCondition('older', $rentItemId, "'RENT'");
        $rentPrev = \App\Services\InvoiceBillingService::sameItemCondition('pi2', $rentItemId, "'RENT'");

        $bindings = [
            $monthStart,
            $monthStart,
            $monthStart,
            $monthStart,
            $returnPeriod,
        ];

        if ($propertyId) {
            $bindings[] = $propertyId;
        }

        DB::statement("
            UPDATE cb_returns_clients rc
            SET pay_percentage = sub.pct,
                current_balance = sub.prev_balance,
                total_amount = sub.prev_total,
                threshold_percentage = sub.pct_num,
                payable_status = sub.pay_status,
                net_amount = CASE WHEN sub.pay_status IN (1, 2, 3) THEN rc.amount_payable_landlord ELSE 0 END,
                commission_amount = CASE WHEN sub.pay_status IN (1, 2, 3) THEN rc.commission_amount ELSE 0 END,
                vat_amount = CASE WHEN sub.pay_status IN (1, 2, 3) THEN rc.vat_amount ELSE 0 END
            FROM (
                SELECT
                    rc2.id AS rc_id,
                    COALESCE(pi.current_balance, 0) AS prev_balance,
                    COALESCE(pi.total_amount, 0) AS prev_total,
                    CASE
                        WHEN pi.id IS NOT NULL THEN
                            CASE
                                WHEN pi.total_amount > 0 AND pi.current_balance <= 0 THEN 100.00
                                WHEN pi.total_amount > 0 AND pi.current_balance >= pi.total_amount THEN 0.00
                                WHEN pi.total_amount > 0 THEN ROUND(((pi.total_amount - pi.current_balance) / pi.total_amount) * 100, 2)
                                ELSE 0.00
                            END
                        WHEN NOT EXISTS (
                            SELECT 1 FROM sys_pm_debtors_invoices older
                            WHERE older.tenant_id = rc2.tenant_id
                              AND older.date_due < ?::date
                              AND older.current_status_id = 1
                              AND older.debtor_type_id = '2'
                              AND {$rentOlder}
                        ) THEN 100.00
                        ELSE 0.00
                    END AS pct_num,
                    CASE
                        WHEN pi.id IS NOT NULL THEN
                            CASE
                                WHEN pi.total_amount > 0 AND pi.current_balance <= 0 THEN '100'
                                WHEN pi.total_amount > 0 AND pi.current_balance >= pi.total_amount THEN '0'
                                WHEN pi.total_amount > 0 THEN ROUND(((pi.total_amount - pi.current_balance) / pi.total_amount) * 100, 2)::text
                                ELSE '0'
                            END
                        WHEN NOT EXISTS (
                            SELECT 1 FROM sys_pm_debtors_invoices older
                            WHERE older.tenant_id = rc2.tenant_id
                              AND older.date_due < ?::date
                              AND older.current_status_id = 1
                              AND older.debtor_type_id = '2'
                              AND {$rentOlder}
                        ) THEN '100'
                        ELSE '0'
                    END AS pct,
                    CASE
                        WHEN pi.id IS NOT NULL AND (
                            (pi.total_amount > 0 AND pi.current_balance <= 0)
                            OR (pi.total_amount > 0 AND ROUND(((pi.total_amount - pi.current_balance) / pi.total_amount) * 100, 2) >= 85)
                        ) THEN 1
                        WHEN pi.id IS NULL AND NOT EXISTS (
                            SELECT 1 FROM sys_pm_debtors_invoices older
                            WHERE older.tenant_id = rc2.tenant_id
                              AND older.date_due < ?::date
                              AND older.current_status_id = 1
                              AND older.debtor_type_id = '2'
                              AND {$rentOlder}
                        ) THEN 1
                        ELSE 0
                    END AS pay_status
                FROM cb_returns_clients rc2
                LEFT JOIN LATERAL (
                    SELECT pi2.id, pi2.current_balance, pi2.total_amount
                    FROM sys_pm_debtors_invoices pi2
                    WHERE pi2.tenant_id = rc2.tenant_id
                      AND pi2.date_due < ?::date
                      AND pi2.current_status_id = 1
                      AND pi2.debtor_type_id = '2'
                      AND {$rentPrev}
                    ORDER BY pi2.date_due DESC, pi2.id DESC LIMIT 1
                ) pi ON true
                WHERE rc2.return_period = ?
                  AND rc2.entry_type = 'RENT CURRENT'
                  {$propertyFilter}
            ) sub
            WHERE rc.id = sub.rc_id
        ", $bindings);

        Log::info("ReturnsPayThresholdTrait: Updated pay percentages", [
            'period' => $returnPeriod,
            'property_id' => $propertyId
        ]);
    }

    /**
     * Get threshold statistics for a return period.
     *
     * @param string $returnPeriod YYYYMM format
     * @return object Contains payable_count, non_payable_count, payable_amount, non_payable_amount
     */
    protected function getThresholdStats(string $returnPeriod): object
    {
        return DB::selectOne("
            SELECT
                COUNT(CASE WHEN payable_status IN (1, 2, 3) THEN 1 END) as payable_count,
                COUNT(CASE WHEN payable_status = 0 OR payable_status IS NULL THEN 1 END) as non_payable_count,
                SUM(CASE WHEN payable_status IN (1, 2, 3) THEN net_amount ELSE 0 END) as payable_amount,
                SUM(CASE WHEN payable_status = 0 OR payable_status IS NULL THEN amount_payable_landlord ELSE 0 END) as non_payable_amount
            FROM cb_returns_clients
            WHERE return_period = ?
              AND entry_type = 'RENT CURRENT'
        ", [$returnPeriod]);
    }
}
