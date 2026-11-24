<?php

namespace App\Traits;

use App\Services\MergedReturnService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles MASTERRETURN generation using merge batch configuration.
 *
 * Mirrors the legacy merged-return shape observed in production (e.g.
 * R1202603LAN-01072) so that summary totals and client lines line up
 * with downstream payment, PDF and Excel logic.
 *
 * Summary math:
 *   total_credit = SUM(child.net_amount)
 *   total_debit  = SUM(child.total_debit)              [informational]
 *   commission_amount = SUM(child.commission_amount)   [informational]
 *   net_amount   = total_credit - landlord_account_deductions_total
 *   net_balance  = net_amount
 *
 * Client lines (cb_returns_clients) for the master:
 *   - One NETSUM (CR) row per child summary, carrying that child's
 *     net_amount/total_debit/total_credit for visibility.
 *   - One DEDUCTION (DR) row per qualifying cb_landlord_account entry
 *     not yet recovered for the period.
 */
trait ReturnsMasterReturnTrait
{
    /**
     * Entry-type codes from cb_landlord_account that count as
     * landlord-level deductions to absorb into the master.
     */
    protected array $masterLandlordDeductionCodes = [
        '0007', '0008', '0009', '0010', '0012', '0013', '0014',
        '0015', '0016', '0019', '0020', '0023', '0024', '0026',
        '0030', '0036',
    ];

    /**
     * Generate MASTERRETURN summaries for all active merge batches.
     */
    protected function generateMasterReturns(
        string $returnPeriod,
        string $transactionDate,
        int $generatedBy,
        int $returnYear,
        int $returnMonth
    ): int {
        // The rent returns of a merge batch are merged into one payment; so are the utility returns of a client who has
        // them (an RU1 return per property), into a second, utility payment.
        return $this->generateMasterReturnsOfKind(false, $returnPeriod, $transactionDate, $generatedBy, $returnYear, $returnMonth)
            + $this->generateMasterReturnsOfKind(true, $returnPeriod, $transactionDate, $generatedBy, $returnYear, $returnMonth);
    }

    /**
     * Generate the MASTERRETURNs of one kind for all active merge batches: the merged rent returns, or the merged
     * utility returns.
     *
     * @param bool $utility true to merge the utility returns (UTILITYRETURN), false to merge the main returns
     * @param string $returnPeriod YYYYMM
     * @param string $transactionDate
     * @param int $generatedBy
     * @param int $returnYear
     * @param int $returnMonth
     */
    protected function generateMasterReturnsOfKind(bool $utility, string $returnPeriod, string $transactionDate, int $generatedBy, int $returnYear, int $returnMonth): int {
        $batches = $this->collectMasterBatchAggregates($returnPeriod, $utility);

        if (empty($batches)) {
            return 0;
        }

        $preparedByName = DB::table('josya_users')->where('id', $generatedBy)->value('name') ?? 'System';
        $monthName = date('F', mktime(0, 0, 0, $returnMonth, 1, $returnYear));
        $masterTransactionDate = $transactionDate ?: date('d-M-Y', strtotime("{$returnYear}-{$returnMonth}-05"));

        $created = 0;

        foreach ($batches as $batch) {
            $landlordCode = $batch->landlord_code ?: $this->getLandlordCodeForClient((int) $batch->batch_client_id);
            if ($landlordCode === '') {
                Log::warning('ReturnsMasterReturnTrait: skipping batch without landlord code', [
                    'batch_id'   => $batch->batch_id,
                    'client_id'  => $batch->batch_client_id,
                ]);
                continue;
            }

            // A landlord with two batches gets two merged returns; the second one's code ends -2 so the two never collide
            $codePrefix = $utility ? 'RU1' : 'R1';
            $usedCodes = DB::table('cb_returns_summary')->where('return_code', 'like', $codePrefix . $returnPeriod . $landlordCode . '%')->pluck('return_code')->all();
            $masterReturnCode = MergedReturnService::masterCode($returnPeriod, $landlordCode, $usedCodes, $codePrefix);

            // Master totals
            $childNetSum   = (float) $batch->child_net_amount_sum;
            $childDebitSum = (float) $batch->child_total_debit_sum;
            $childCommSum  = (float) $batch->child_commission_sum;

            // The landlord-level debits (no property) this merged return takes, up to what the property returns pay
            // Deductions come off the rent payment only: the utility payment carries none
            $deductionRows = $utility ? [] : $this->pickMasterDeductionRows((int) $batch->batch_client_id, $returnYear, $returnMonth, $childNetSum);
            $deductionsTotal = round((float) array_sum(array_column($deductionRows, 'debit_amount')), 2);

            $masterNetAmount = round($childNetSum - $deductionsTotal, 2);

            $nextReturnId = (int) DB::selectOne(
                'SELECT COALESCE(MAX(return_id), 0) + 1 AS next_id FROM cb_returns_summary'
            )->next_id;

            $description = trim(($batch->landlord_name ?? '') . "-{$monthName}-{$returnYear}-MASTER RETURN" . ($utility ? ' - Utilities' : ''));

            DB::insert("
                INSERT INTO cb_returns_summary (
                    return_code, landlord_code, property_code,
                    year, month, return_description, return_period, return_type,
                    commission_rate, total_credit, commission_total, vatable_total,
                    commission_amount, total_debit, net_amount,
                    transaction_amount, net_balance,
                    prepared_by, prepared_status, prepared_date,
                    saved_status, finalized_status,
                    approved_status, paid_status,
                    return_tag, return_status, remarks,
                    transaction_ref, return_id,
                    payable_amount, non_payable_amount, payable_units, total_units,
                    landlord_name, client_id, zone_id, field_officer_id,
                    zone_supervisor_id, zone_manager_id,
                    server_time, user_id
                ) VALUES (
                    ?, ?, '',
                    ?, ?, ?, ?, 'MASTERRETURN',
                    0, ?, 0, 0,
                    ?, ?, ?,
                    0, ?,
                    ?, 1, NOW(),
                    1, 0,
                    0, 0,
                    '1', 0, 'Master Return Created',
                    ?, ?,
                    0, 0, 0, 0,
                    ?, ?, ?, ?,
                    ?, ?,
                    NOW(), ?
                )
            ", [
                $masterReturnCode,
                $landlordCode,
                $returnYear, $returnMonth,
                $description, $returnPeriod,
                round($childNetSum, 2),
                round($childCommSum, 2),
                round($childDebitSum, 2),
                $masterNetAmount,
                $masterNetAmount,
                $preparedByName,
                $batch->child_return_codes,
                $nextReturnId,
                $batch->landlord_name,
                $batch->batch_client_id,
                $batch->zone_id,
                $batch->field_officer_id,
                $batch->zone_supervisor_id,
                $batch->zone_manager_id,
                $generatedBy,
            ]);

            $this->insertMasterNetSumLines(
                $masterReturnCode,
                $returnPeriod,
                $masterTransactionDate,
                $nextReturnId,
                $batch->child_return_codes,
                $generatedBy
            );

            $this->insertMasterReturnDeductions(
                $masterReturnCode,
                $returnPeriod,
                $masterTransactionDate,
                $nextReturnId,
                $deductionRows,
                $generatedBy
            );

            $this->markChildReturnsAsMerged(
                $batch->child_summary_ids,
                $masterReturnCode,
                $returnPeriod
            );

            $created++;

            Log::info('ReturnsMasterReturnTrait: MASTERRETURN created', [
                'master_code'       => $masterReturnCode,
                'batch'             => $batch->batch_name,
                'children'          => $batch->property_count,
                'child_codes'       => $batch->child_return_codes,
                'total_credit'      => $childNetSum,
                'deductions_total'  => $deductionsTotal,
                'net_amount'        => $masterNetAmount,
            ]);
        }

        return $created;
    }

    /**
     * Pull aggregated child summaries grouped by merge batch for the period.
     * Skips any batch that resolves to a single MAINRETURN (nothing to merge). With $utility the children are the
     * batch's utility returns (UTILITYRETURN) instead of its main returns.
     */
    protected function collectMasterBatchAggregates(string $returnPeriod, bool $utility = false): array
    {
        return DB::select("
            WITH child_returns AS (
                SELECT
                    mb.id              AS batch_id,
                    mb.batch_reference AS batch_code,
                    mb.landlord_name   AS batch_name,
                    mb.landlord_id     AS batch_client_id,
                    rs.id              AS child_summary_id,
                    rs.return_code     AS child_return_code,
                    rs.property_code,
                    COALESCE(rs.net_amount, 0)        AS child_net_amount,
                    COALESCE(rs.total_debit, 0)       AS child_total_debit,
                    COALESCE(rs.commission_amount, 0) AS child_commission,
                    rs.zone_id,
                    rs.field_officer_id,
                    rs.zone_supervisor_id,
                    rs.zone_manager_id
                FROM cb_returns_merge_batches mb
                JOIN cb_returns_merge_batch_items mbi ON mbi.batch_id = mb.id
                JOIN sys_pm_properties p ON p.id = mbi.property_id
                JOIN cb_returns_summary rs ON rs.property_code = p.reference_number
                                           AND rs.return_period = ?
                                           AND rs.return_type   = ?
                                           AND COALESCE(rs.is_merged, FALSE) = FALSE
            )
            SELECT
                cr.batch_id,
                cr.batch_code,
                cr.batch_name,
                cr.batch_client_id,
                cl.reference_number AS landlord_code,
                cl.names            AS landlord_name,
                COUNT(*)            AS property_count,
                STRING_AGG(cr.child_return_code,        ',' ORDER BY cr.child_return_code) AS child_return_codes,
                STRING_AGG(cr.child_summary_id::text,   ',' ORDER BY cr.child_return_code) AS child_summary_ids,
                SUM(cr.child_net_amount)   AS child_net_amount_sum,
                SUM(cr.child_total_debit)  AS child_total_debit_sum,
                SUM(cr.child_commission)   AS child_commission_sum,
                MIN(cr.zone_id)            AS zone_id,
                MIN(cr.field_officer_id)   AS field_officer_id,
                MIN(cr.zone_supervisor_id) AS zone_supervisor_id,
                MIN(cr.zone_manager_id)    AS zone_manager_id
            FROM child_returns cr
            LEFT JOIN sys_pm_clients cl ON cl.id = cr.batch_client_id
            GROUP BY cr.batch_id, cr.batch_code, cr.batch_name, cr.batch_client_id, cl.reference_number, cl.names
            HAVING COUNT(*) > 1
            ORDER BY cr.batch_id
        ", [$returnPeriod, $utility ? 'UTILITYRETURN' : 'MAINRETURN']);
    }

    /**
     * The landlord-level debits on the client's account that the merged return takes: debits with no property (the
     * registration fee, cheque and banking charges, advances not tied to a property), the same ones a normal return takes
     * (this month's, and earlier advances and the registration fee), advances only once paid out to the client, oldest
     * first, and no more than the property returns pay. A debit tied to a property is on that property's own return.
     *
     * @param int $clientId
     * @param int $returnYear
     * @param int $returnMonth
     * @param float $available the sum of the property returns' nets
     * @return array rows of id, debit_amount and the columns the deduction line needs, in ledger order
     */
    protected function pickMasterDeductionRows(int $clientId, int $returnYear, int $returnMonth, float $available): array {
        $placeholders = implode(',', array_fill(0, count($this->masterLandlordDeductionCodes), '?'));

        $rows = DB::select("
            SELECT la.id, la.debit_amount, la.entry_particulars, la.entry_description, la.payment_ref, la.payment_ref_type,
                   la.entry_date, la.property_code, la.property_id, la.client_id
            FROM cb_landlord_account la
            WHERE la.client_id = ?
              AND la.property_id IS NULL
              AND la.debit_amount > 0
              AND COALESCE(la.is_recovered, 0) != 1
              AND la.return_code IS NULL
              AND la.entry_type_code IN ({$placeholders})
              AND (
                    (la.year = ? AND la.month = ?)
                    OR (la.year * 100 + la.month < ? AND (la.advance_id IS NOT NULL OR la.entry_type_code = '0023'))
                  )
              {$this->advancePaidSql('la')}
            ORDER BY la.id
        ", array_merge([$clientId], $this->masterLandlordDeductionCodes, [$returnYear, $returnMonth, $returnYear * 100 + $returnMonth]));

        $chosen = [];
        $running = 0.0;

        foreach ($rows as $row) {
            $running = round($running + (float) $row->debit_amount, 2);

            if ($running > round($available, 2)) {
                break;
            }

            $chosen[] = (array) $row;
        }

        return $chosen;
    }

    /**
     * Insert one NETSUM (CR) row per child return (main, or utility for a merged utility return) onto the master.
     * Each row carries the child's net_amount, total_debit, total_credit
     * for traceability and downstream rendering.
     */
    protected function insertMasterNetSumLines(
        string $masterReturnCode,
        string $returnPeriod,
        string $transactionDate,
        int $masterReturnId,
        string $childReturnCodes,
        int $userId
    ): int {
        $codes = array_filter(array_map('trim', explode(',', $childReturnCodes)));
        if (empty($codes)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($codes), '?'));

        return DB::affectingStatement("
            INSERT INTO cb_returns_clients (
                return_code, return_period, transaction_number, transaction_date,
                entry_description, entry_type, transaction_code,
                narration, transaction_ref,
                property_code, dr_cr,
                amount_text, amount_payable_landlord, amount_payable_tenant,
                residual_debit, residual_credit, net_amount,
                remarks, transaction_source,
                vat_amount, commission_amount,
                property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id,
                return_id,
                server_time, user_id
            )
            SELECT
                ?, ?::integer, '', ?,
                'NETSUM', 'RETURN NET SUM', 'RETURN-NETSUM',
                COALESCE(p.property_name, rs.property_code) || '-RETURN NET SUM',
                rs.return_code,
                rs.property_code, 'CR',
                TO_CHAR(COALESCE(rs.net_amount, 0), 'FM9999999990.00'),
                COALESCE(rs.net_amount, 0),
                0,
                COALESCE(rs.total_debit, 0),
                COALESCE(rs.total_credit, 0),
                COALESCE(rs.net_amount, 0),
                'Transfered to Master',
                NULL,
                0, 0,
                p.id, rs.client_id, rs.zone_id, rs.field_officer_id,
                rs.zone_supervisor_id, rs.zone_manager_id,
                ?,
                NOW(), ?
            FROM cb_returns_summary rs
            LEFT JOIN sys_pm_properties p ON p.reference_number = rs.property_code
            WHERE rs.return_code IN ({$placeholders})
              AND rs.return_period = ?
              AND rs.return_type   IN ('MAINRETURN', 'UTILITYRETURN')
            ORDER BY rs.return_code
        ", array_merge(
            [$masterReturnCode, $returnPeriod, $transactionDate, $masterReturnId, $userId],
            $codes,
            [$returnPeriod]
        ));
    }

    /**
     * Put the chosen landlord-level debits on the merged return as DR lines and reserve them for it. Nothing is recovered
     * here: the debits are flagged recovered when the merged return is finalised (ReturnPostingService), and regenerating
     * the returns frees them again, as for any return.
     *
     * @param string $masterReturnCode
     * @param string $returnPeriod
     * @param string $transactionDate
     * @param int $returnId
     * @param array $rows from pickMasterDeductionRows()
     * @param int $userId
     */
    protected function insertMasterReturnDeductions(
        string $masterReturnCode,
        string $returnPeriod,
        string $transactionDate,
        int $returnId,
        array $rows,
        int $userId
    ): int {
        foreach ($rows as $row) {
            DB::table('cb_returns_clients')->insert([
                'return_code' => $masterReturnCode,
                'return_period' => (int) $returnPeriod,
                'transaction_number' => '',
                'transaction_date' => $transactionDate,
                'entry_description' => 'DEDUCTION',
                'entry_type' => 'Advance Payments-From Landlord Acc',
                'narration' => $row['entry_particulars'] ?: $row['entry_description'],
                'transaction_ref' => trim(($row['payment_ref'] ?? '') . ' ' . ($row['payment_ref_type'] ?? '')) ?: null,
                'source_date' => $row['entry_date'],
                'property_code' => (string) ($row['property_code'] ?? ''),
                'dr_cr' => 'DR',
                'amount_text' => number_format((float) $row['debit_amount'], 2, '.', ''),
                'amount_payable_landlord' => 0,
                'amount_payable_tenant' => 0,
                'residual_debit' => (float) $row['debit_amount'],
                'residual_credit' => 0,
                'net_amount' => (float) $row['debit_amount'],
                'remarks' => 'Payment OK',
                'transaction_source' => 'LANDLORD ACCOUNT',
                'vat_amount' => 0,
                'commission_amount' => 0,
                'property_id' => $row['property_id'],
                'client_id' => $row['client_id'],
                'return_id' => $returnId,
                'server_time' => now(),
                'user_id' => $userId,
            ]);
        }

        if (!empty($rows)) {
            DB::table('cb_landlord_account')->whereIn('id', array_column($rows, 'id'))->update(['return_code' => $masterReturnCode]);

            Log::info('ReturnsMasterReturnTrait: landlord deductions reserved for the merged return', [
                'master_code' => $masterReturnCode,
                'count' => count($rows),
            ]);
        }

        return count($rows);
    }

    /**
     * Mark the child MAINRETURN summaries as merged into a MASTERRETURN.
     */
    protected function markChildReturnsAsMerged(
        string $childSummaryIds,
        string $masterReturnCode,
        string $returnPeriod
    ): void {
        $ids = array_filter(array_map('trim', explode(',', $childSummaryIds)));
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        DB::statement("
            UPDATE cb_returns_summary
            SET parent_return_code = ?,
                is_merged          = TRUE
            WHERE id IN ({$placeholders})
              AND return_period = ?
        ", array_merge([$masterReturnCode], $ids, [$returnPeriod]));
    }

    /**
     * Get landlord reference code for a client.
     */
    protected function getLandlordCodeForClient(int $clientId): string
    {
        $result = DB::selectOne("
            SELECT reference_number
            FROM sys_pm_clients
            WHERE id = ?
        ", [$clientId]);

        return $result ? (string) $result->reference_number : '';
    }

    /**
     * Get batched properties for a landlord.
     */
    protected function getBatchedPropertiesForLandlord(int $clientId): array
    {
        return DB::select("
            SELECT
                mb.id              AS batch_id,
                mb.batch_reference AS batch_code,
                mb.landlord_name   AS batch_name,
                mbi.property_id,
                p.reference_number AS property_code,
                p.property_name
            FROM cb_returns_merge_batches mb
            JOIN cb_returns_merge_batch_items mbi ON mbi.batch_id = mb.id
            JOIN sys_pm_properties p ON p.id = mbi.property_id
            WHERE mb.landlord_id = ?
            ORDER BY mb.batch_reference, p.reference_number
        ", [$clientId]);
    }

    /**
     * Check if a property is part of an active merge batch.
     */
    protected function isPropertyInMergeBatch(int $propertyId): bool
    {
        $result = DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM cb_returns_merge_batch_items mbi
            JOIN cb_returns_merge_batches mb ON mb.id = mbi.batch_id
            WHERE mbi.property_id = ?
        ", [$propertyId]);

        return $result && (int) $result->cnt > 0;
    }

    /**
     * Get MASTERRETURN summaries for a period.
     */
    protected function getMasterReturnsForPeriod(string $returnPeriod): array
    {
        return DB::select("
            SELECT
                rs.*,
                c.names            AS landlord_name_lookup,
                c.reference_number AS landlord_code_lookup
            FROM cb_returns_summary rs
            JOIN sys_pm_clients c ON c.id = rs.client_id
            WHERE rs.return_period = ?
              AND rs.return_type   = 'MASTERRETURN'
            ORDER BY rs.return_code
        ", [$returnPeriod]);
    }
}
