<?php

namespace App\Traits;

use App\Models\AdvanceSchedule;
use App\Models\ClientAdvance;
use App\Models\LandlordAccount;
use App\Services\UtilityReturnsService;
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
     * Propose the deductions of a return: the client's unrecovered debits (advances, registration fee and the other
     * deduction types) as DR lines, oldest first, up to what the return owes the client after commission and VAT.
     *
     * A debit is taken if it is from the return's month, or from an earlier month and is an advance, the registration fee,
     * a paid-owner debit (0007) or the debit of a confirmed recovery (0024 with a REC-year-number reference, which may be
     * confirmed after its month's return was approved), so what did not fit one month's return rolls forward. The older
     * 0024 debits (Landlord Recovery Posting, no REC- reference) stay with their own month. Nothing is recovered here: the debits are only reserved
     * for this return (return_code is set, is_recovered stays 0) and are recovered when the return gets its final
     * approval (ReturnPostingService). Regenerating the return frees them again.
     *
     * Advances are recovered by main returns only, and only once they were paid to the client (see advancePaidSql). A mini
     * return, which is for money that came in late, never takes an advance row.
     *
     * @param string $returnPeriod YYYYMM format
     * @param string $transactionDate Display date (DD-Mon-YYYY)
     * @param int $generatedBy User ID
     * @param int $returnYear
     * @param int $returnMonth
     * @param int|null $propertyId Optional: Generate for single property only
     * @param string|null $returnCode Optional: the one return (a mini return) the debits are reserved for; without it the main returns (R1) of the period
     * @param bool $batchProperty Only for a client whose properties are merged: take the debits tied to this property (an advance belongs to the property chosen when it was taken). The debits with no property go to the merged return (ReturnsMasterReturnTrait). Clients without a merge batch are handled with this off.
     * @return int Lines inserted
     */
    protected function insertAdvancePaymentLines(string $returnPeriod, string $transactionDate, int $generatedBy, int $returnYear, int $returnMonth, ?int $propertyId = null, ?string $returnCode = null, bool $batchProperty = false): int {
        $propertyFilter = $propertyId ? "WHERE property_id = ?" : "";
        $codeFilter = $returnCode !== null ? "return_code = ?" : "return_code LIKE 'R1%'";
        $advanceFilter = $returnCode !== null ? "AND la.advance_id IS NULL" : $this->advancePaidSql('la');

        // Bindings in the order the placeholders appear: return period, optional return code, optional property, month,
        // month, month index, return period (integer column), transaction date, user
        $bindings = [$returnPeriod];
        if ($returnCode !== null) {
            $bindings[] = $returnCode;
        }
        if ($propertyId) {
            $bindings[] = $propertyId;
        }
        array_push($bindings, $returnYear, $returnMonth, $returnYear * 100 + $returnMonth, $returnPeriod, $transactionDate, $generatedBy);

        // A client with a merge batch is left out of the normal path: the debits tied to a property go to that property's
        // return ($batchProperty mode), the rest to the merged return
        $baseKey = $batchProperty ? 'client_id, property_id' : 'client_id';
        $scope = $batchProperty
            ? "EXISTS (SELECT 1 FROM cb_returns_merge_batches mb WHERE mb.landlord_id = la.client_id) AND la.property_id = b.property_id"
            : "NOT EXISTS (SELECT 1 FROM cb_returns_merge_batches mb WHERE mb.landlord_id = la.client_id)";

        $advancesInserted = DB::affectingStatement("
            WITH first_lines AS (
                SELECT DISTINCT ON ({$baseKey})
                       return_code, return_id, property_code, property_id, client_id,
                       zone_id, field_officer_id, zone_supervisor_id, zone_manager_id
                FROM cb_returns_clients
                WHERE return_period = ?
                  AND {$codeFilter}
                ORDER BY {$baseKey}, id
            ), base AS (
                SELECT * FROM first_lines {$propertyFilter}
            ), available AS (
                SELECT b.client_id, b.property_id,
                       SUM(CASE WHEN rc.dr_cr = 'CR' THEN rc.net_amount ELSE 0 END)
                         - SUM(COALESCE(rc.commission_amount, 0)) - SUM(COALESCE(rc.vat_amount, 0)) AS amount
                FROM base b
                JOIN cb_returns_clients rc ON rc.return_code = b.return_code
                GROUP BY b.client_id, b.property_id
            ), candidates AS (
                SELECT la.id, la.client_id, b.property_id AS base_property,
                       SUM(la.debit_amount) OVER (PARTITION BY la.client_id, b.property_id ORDER BY la.id) AS running
                FROM cb_landlord_account la
                JOIN base b ON b.client_id = la.client_id
                WHERE la.debit_amount > 0
                  AND COALESCE(la.is_recovered, 0) != 1
                  AND la.return_code IS NULL
                  {$advanceFilter}
                  AND la.entry_type_code IN ('0007','0008','0009','0010','0012','0013','0014','0015','0016','0019','0020','0023','0024','0026','0030','0036')
                  AND (
                        (la.year = ? AND la.month = ?)
                        OR (la.year * 100 + la.month < ? AND (la.advance_id IS NOT NULL OR la.entry_type_code IN ('0023', '0007') OR (la.entry_type_code = '0024' AND la.transaction_number LIKE 'REC-%')))
                      )
                  AND {$scope}
            ), chosen AS (
                SELECT c.id, c.base_property
                FROM candidates c
                JOIN available a ON a.client_id = c.client_id AND a.property_id = c.base_property
                WHERE c.running <= a.amount
            ), reserved AS (
                UPDATE cb_landlord_account la
                SET return_code = b.return_code
                FROM chosen ch, base b
                WHERE la.id = ch.id
                  AND b.client_id = la.client_id
                  AND b.property_id = ch.base_property
                RETURNING la.id AS la_id, la.transaction_number, la.debit_amount, la.entry_type_code,
                          la.entry_description, la.entry_particulars, la.client_id, ch.base_property,
                          la.entry_date, la.payment_ref, la.payment_ref_type
            )
            INSERT INTO cb_returns_clients (
                return_code, return_period, transaction_number, transaction_date,
                entry_description, entry_type, dr_cr,
                property_code, net_amount, narration,
                property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id,
                return_id, deduction_point_2, is_commissionable, transaction_source,
                server_time, user_id, source_date, transaction_ref
            )
            SELECT DISTINCT ON (r.la_id)
                b.return_code,
                ?::integer,
                r.transaction_number,
                ?,
                COALESCE(ii.item_name, r.entry_description),
                COALESCE(ii.item_name, 'Landlord Account Deduction'),
                'DR',
                b.property_code,
                r.debit_amount,
                COALESCE(r.entry_particulars, r.entry_description),
                b.property_id,
                r.client_id,
                b.zone_id,
                b.field_officer_id,
                b.zone_supervisor_id,
                b.zone_manager_id,
                b.return_id,
                NULL,
                'No',
                'LANDLORD ACCOUNT',
                NOW(),
                ?,
                r.entry_date,
                TRIM(COALESCE(r.payment_ref, '') || ' ' || COALESCE(r.payment_ref_type, ''))
            FROM reserved r
            JOIN base b ON b.client_id = r.client_id AND b.property_id = r.base_property
            LEFT JOIN sys_set_invoiceable_items ii ON ii.billing_code = r.entry_type_code
            ORDER BY r.la_id
        ", $bindings);

        Log::info("ReturnsDeductionsTrait: Advance payment DR lines proposed", [
            'count' => $advancesInserted,
            'property_id' => $propertyId
        ]);

        return $advancesInserted;
    }

    /**
     * SQL condition that keeps an advance's client ledger rows off a return until the advance has been paid to the client:
     * its payment voucher is Approved. An advance with no voucher is only taken when it was posted before advances were
     * posted to the GL (no principal journal), as those were paid under the old process and have no voucher to wait for.
     * Rows that are not advance rows are not affected.
     *
     * @param string $alias the cb_landlord_account table or alias in the query
     */
    protected function advancePaidSql(string $alias): string {
        return "AND ({$alias}.advance_id IS NULL OR EXISTS (
            SELECT 1 FROM sys_pm_clients_advances_requisitions adv
            WHERE adv.id = {$alias}.advance_id
              AND (
                  EXISTS (SELECT 1 FROM cb_payment_vouchers_headers_new pv WHERE pv.id = adv.voucher_id AND pv.voucher_status = 'Approved')
                  OR (adv.voucher_id IS NULL AND NOT EXISTS (SELECT 1 FROM sys_gl_transactions g WHERE g.trans_details::text = 'advance_id:' || adv.id || ':principal'))
              )
        ))";
    }

    /**
     * Propagate just-recovered ADVANCE (0009) ledger rows onto ClientAdvance/AdvanceSchedule.
     * Takes the caller's own updated ids (not a year/month re-query) so repeat calls in one request can't double-decrement.
     *
     * @param int[] $landlordAccountIds cb_landlord_account.id values just updated by the caller
     */
    protected function syncAdvanceRecoveryStatus(array $landlordAccountIds): void
    {
        if (empty($landlordAccountIds)) {
            return;
        }

        $recoveredRows = LandlordAccount::whereIn('id', $landlordAccountIds)
            ->where('entry_type_code', '0009')
            ->whereNotNull('advance_id')
            ->get(['id', 'advance_id', 'schedule_id', 'debit_amount']);

        if ($recoveredRows->isEmpty()) {
            return;
        }

        foreach ($recoveredRows->groupBy('advance_id') as $advanceId => $rows) {
            $advance = ClientAdvance::find($advanceId);
            if (!$advance) {
                continue;
            }

            $recoveredAmount = (float) $rows->sum('debit_amount');
            $newBalance = max(0, (float) $advance->current_balance - $recoveredAmount);

            $advance->current_balance = $newBalance;
            if ($newBalance <= 0.0) {
                $advance->current_status_id = 2; // Cleared
            }
            $advance->save();

            $scheduleIds = $rows->pluck('schedule_id')->filter()->unique();
            if ($scheduleIds->isNotEmpty()) {
                AdvanceSchedule::whereIn('id', $scheduleIds)
                    ->update(['current_status_id' => 2]); // cleared
            }

            Log::info('syncAdvanceRecoveryStatus: advance balance reconciled', [
                'advance_id'       => $advanceId,
                'recovered_amount' => $recoveredAmount,
                'new_balance'      => $newBalance,
                'schedules_cleared' => $scheduleIds->values()->all(),
            ]);
        }
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
                -- A recovery is a deduction: it goes on the rent return, never on a utility return (RU)
                SELECT return_code, return_id, property_code
                FROM cb_returns_clients
                WHERE return_period = ? AND property_id = mr.property_id AND return_code NOT LIKE 'RU%'
                ORDER BY id
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

        // A collected bill belongs on the utility return (RU) of a client who has one. When the property has no utility
        // line this month the utility return takes its code from the rent return's, and the summary step creates it.
        $wantsUtility = "(COALESCE(pcl.utility_returns, 'with_rent') = '" . UtilityReturnsService::SEPARATE_RETURN . "' AND " . UtilityReturnsService::utilityItemSql('mr.transaction_type') . ")";

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
                CASE WHEN {$wantsUtility} AND rc_base.return_code NOT LIKE 'RU%' AND rc_base.return_code NOT LIKE '%UNKNOWN%' THEN 'RU' || SUBSTRING(rc_base.return_code FROM 2) ELSE rc_base.return_code END,
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
            LEFT JOIN sys_pm_properties mp ON mp.id = mr.property_id
            LEFT JOIN sys_pm_clients pcl ON pcl.id = mp.client_id
            JOIN LATERAL (
                -- Prefer the return of the kind the item belongs to (utility or rent)
                SELECT return_code, return_id, property_code
                FROM cb_returns_clients
                WHERE return_period = ? AND property_id = mr.property_id
                ORDER BY CASE WHEN (return_code LIKE 'RU%') = {$wantsUtility} THEN 0 ELSE 1 END, id
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

        // Placeholders in the order they appear: the period of the return looked up, year, month, the period of the exists check, property
        $bindings = [$returnPeriod, $returnYear, $returnMonth, $returnPeriod];
        if ($propertyId) {
            $bindings[] = $propertyId;
        }

        // A property can have a rent return and a utility return: link to the rent return when there is one. A recovery
        // is a deduction, so it links only to a rent return (it waits when the property has only a utility return).
        DB::statement("
            UPDATE cb_money_recoveries mr
            SET return_id = (
                    SELECT rs.id
                    FROM cb_returns_summary rs
                    JOIN sys_pm_properties p ON p.reference_number = rs.property_code
                    WHERE p.id = mr.property_id
                      AND rs.return_period = ?
                      AND rs.approved_status = 0
                      AND (mr.transaction_class <> 'DR' OR rs.return_code NOT LIKE 'RU%')
                    ORDER BY (rs.return_code LIKE 'RU%'), rs.id
                    LIMIT 1
                ),
                is_locked = true
            WHERE mr.year  = ?
              AND mr.month = ?
              AND mr.return_id IS NULL
              AND EXISTS (
                    SELECT 1 FROM cb_returns_summary rs2
                    JOIN sys_pm_properties p2 ON p2.reference_number = rs2.property_code
                    WHERE p2.id = mr.property_id AND rs2.return_period = ? AND rs2.approved_status = 0
                      AND (mr.transaction_class <> 'DR' OR rs2.return_code NOT LIKE 'RU%')
              )
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
     * @param string|null $returnCode Optional: the one return (a mini return) to add the line to; without it the main returns (R1) of the period
     * @return int Commission lines inserted
     */
    protected function insertCommissionLines(string $returnPeriod, string $transactionDate, int $generatedBy, ?int $propertyId = null, ?string $returnCode = null): int {
        $propertyFilter = $propertyId ? "AND rc.property_id = ?" : "";
        $codeFilter = $returnCode !== null ? "AND rc.return_code = ?" : "AND rc.return_code LIKE 'R1%'";

        $bindings = [$returnPeriod, $transactionDate, $generatedBy, $returnPeriod];
        if ($returnCode !== null) {
            $bindings[] = $returnCode;
        }
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
                  {$codeFilter}
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
