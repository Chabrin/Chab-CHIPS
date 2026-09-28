<?php

namespace App\Traits;

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
        $batches = $this->collectMasterBatchAggregates($returnPeriod);

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

            $masterReturnCode = 'R1' . $returnPeriod . $landlordCode;

            // Master totals
            $childNetSum   = (float) $batch->child_net_amount_sum;
            $childDebitSum = (float) $batch->child_total_debit_sum;
            $childCommSum  = (float) $batch->child_commission_sum;

            $deductionAggregate = $this->aggregateMasterLandlordDeductions(
                (int) $batch->batch_client_id,
                $returnYear,
                $returnMonth
            );
            $deductionsTotal = (float) $deductionAggregate['total'];

            $masterNetAmount = round($childNetSum - $deductionsTotal, 2);

            $nextReturnId = (int) DB::selectOne(
                'SELECT COALESCE(MAX(return_id), 0) + 1 AS next_id FROM cb_returns_summary'
            )->next_id;

            $description = trim(($batch->landlord_name ?? '') . "-{$monthName}-{$returnYear}-MASTER RETURN");

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
                (int) $batch->batch_client_id,
                $returnYear,
                $returnMonth,
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
     * Skips any batch that resolves to a single MAINRETURN (nothing to merge).
     */
    protected function collectMasterBatchAggregates(string $returnPeriod): array
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
                                           AND rs.return_type   = 'MAINRETURN'
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
        ", [$returnPeriod]);
    }

    /**
     * Pre-compute the landlord-account deduction total so that it can be
     * subtracted from the master's net_amount before the summary is inserted.
     */
    protected function aggregateMasterLandlordDeductions(int $clientId, int $returnYear, int $returnMonth): array
    {
        $placeholders = implode(',', array_fill(0, count($this->masterLandlordDeductionCodes), '?'));

        $row = DB::selectOne("
            SELECT
                COALESCE(SUM(la.debit_amount), 0) AS total_amount,
                COUNT(*) AS line_count
            FROM cb_landlord_account la
            WHERE la.client_id = ?
              AND la.debit_amount > 0
              AND COALESCE(la.is_recovered, 0) != 1
              AND la.year  = ?
              AND la.month = ?
              AND la.entry_type_code IN ({$placeholders})
        ", array_merge([$clientId, $returnYear, $returnMonth], $this->masterLandlordDeductionCodes));

        return [
            'total' => (float) ($row->total_amount ?? 0),
            'count' => (int)   ($row->line_count   ?? 0),
        ];
    }

    /**
     * Insert one NETSUM (CR) row per child MAINRETURN onto the master.
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
              AND rs.return_type   = 'MAINRETURN'
            ORDER BY rs.return_code
        ", array_merge(
            [$masterReturnCode, $returnPeriod, $transactionDate, $masterReturnId, $userId],
            $codes,
            [$returnPeriod]
        ));
    }

    /**
     * Insert landlord-level deductions from cb_landlord_account onto the master,
     * preserving property_code/property_id where the deduction is property-specific
     * (e.g. BANKING SERVICES). Marks consumed entries as recovered.
     */
    protected function insertMasterReturnDeductions(
        string $masterReturnCode,
        string $returnPeriod,
        string $transactionDate,
        int $returnId,
        int $clientId,
        int $returnYear,
        int $returnMonth,
        int $userId
    ): int {
        $placeholders = implode(',', array_fill(0, count($this->masterLandlordDeductionCodes), '?'));

        $inserted = DB::affectingStatement("
            INSERT INTO cb_returns_clients (
                return_code, return_period, transaction_number, transaction_date,
                entry_description, entry_type, transaction_code,
                narration, transaction_ref, source_date,
                property_code, dr_cr,
                amount_text, amount_payable_landlord, amount_payable_tenant,
                residual_debit, residual_credit, net_amount,
                remarks, transaction_source,
                vat_amount, commission_amount,
                property_id, client_id,
                return_id,
                server_time, user_id
            )
            SELECT
                ?, ?::integer, '', ?,
                'DEDUCTION',
                'Advance Payments-From Landlord Acc',
                NULL,
                COALESCE(la.entry_particulars, la.entry_description),
                CASE
                    WHEN COALESCE(la.payment_ref, '') = '' THEN NULL
                    WHEN COALESCE(la.payment_ref_type, '') = '' THEN la.payment_ref
                    ELSE la.payment_ref || ' ' || la.payment_ref_type
                END,
                la.entry_date,
                COALESCE(la.property_code, ''),
                'DR',
                TO_CHAR(la.debit_amount, 'FM9999999990.00'),
                0, 0,
                la.debit_amount, 0, la.debit_amount,
                'Payment OK',
                'LANDLORD ACCOUNT',
                0, 0,
                la.property_id,
                la.client_id,
                ?,
                NOW(),
                ?
            FROM cb_landlord_account la
            WHERE la.client_id = ?
              AND la.debit_amount > 0
              AND COALESCE(la.is_recovered, 0) != 1
              AND la.year  = ?
              AND la.month = ?
              AND la.entry_type_code IN ({$placeholders})
            ORDER BY la.entry_date, la.id
        ", array_merge(
            [$masterReturnCode, $returnPeriod, $transactionDate, $returnId, $userId, $clientId, $returnYear, $returnMonth],
            $this->masterLandlordDeductionCodes
        ));

        if ($inserted > 0) {
            $recoveredIds = array_column(DB::select("
                UPDATE cb_landlord_account
                SET is_recovered         = 1,
                    recovery_year        = ?,
                    recovery_month       = ?,
                    return_code          = ?,
                    recovery_server_time = NOW()
                WHERE client_id = ?
                  AND debit_amount > 0
                  AND COALESCE(is_recovered, 0) != 1
                  AND year  = ?
                  AND month = ?
                  AND entry_type_code IN ({$placeholders})
                RETURNING id
            ", array_merge(
                [$returnYear, $returnMonth, $masterReturnCode, $clientId, $returnYear, $returnMonth],
                $this->masterLandlordDeductionCodes
            )), 'id');

            Log::info('ReturnsMasterReturnTrait: landlord deductions absorbed into MASTERRETURN', [
                'master_code' => $masterReturnCode,
                'client_id'   => $clientId,
                'count'       => $inserted,
            ]);

            // Mirrors the standard returns path — without this, ClientAdvance.current_balance
            // never reflects a merge-batch recovery.
            $this->syncAdvanceRecoveryStatus($recoveredIds);
        }

        return $inserted;
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
