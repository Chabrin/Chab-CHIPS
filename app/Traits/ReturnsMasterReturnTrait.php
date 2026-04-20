<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles MASTERRETURN generation using merge batch configuration.
 * 
 * MASTERRETURNs aggregate multiple MAINRETURN summaries for properties
 * configured in the same batch via cb_returns_merge_batches.
 * 
 * The transaction_ref column in cb_returns_clients stores the child
 * return codes as a comma-separated list for traceability.
 */
trait ReturnsMasterReturnTrait
{
    /**
     * Generate MASTERRETURN summaries for all active merge batches.
     *
     * @param string $returnPeriod YYYYMM format
     * @param string $transactionDate Display date (DD-Mon-YYYY)
     * @param int $generatedBy User ID
     * @param int $returnYear
     * @param int $returnMonth
     * @return int Number of MASTERRETURNs created
     */
    protected function generateMasterReturns(
        string $returnPeriod,
        string $transactionDate,
        int $generatedBy,
        int $returnYear,
        int $returnMonth
    ): int {
        // Get active merge batches with their aggregated data using CORRECT columns
        $masterReturns = DB::select("
            WITH batch_property_returns AS (
                -- Get MAINRETURN summaries for properties in active batches
                SELECT
                    mb.id AS batch_id,
                    mb.batch_reference AS batch_code,
                    mb.landlord_name AS batch_name,
                    mb.landlord_id AS batch_client_id,
                    rs.id AS child_summary_id,
                    rs.return_id AS child_return_id,
                    rs.return_code AS child_return_code,
                    rs.property_code,
                    mbi.property_id,
                    -- Use columns that actually have data
                    COALESCE(rs.total_credit, 0) AS total_credit,
                    COALESCE(rs.total_debit, 0) AS total_debit,
                    COALESCE(rs.net_amount, 0) AS net_amount,
                    COALESCE(rs.commission_amount, 0) AS commission_amount,
                    COALESCE(rs.commission_total, 0) AS commission_total,
                    COALESCE(rs.vatable_total, 0) AS vatable_total,
                    COALESCE(rs.transaction_amount, 0) AS transaction_amount,
                    COALESCE(rs.net_balance, 0) AS net_balance,
                    COALESCE(rs.payable_amount, 0) AS payable_amount,
                    COALESCE(rs.non_payable_amount, 0) AS non_payable_amount,
                    COALESCE(rs.payable_units, 0) AS payable_units,
                    COALESCE(rs.total_units, 0) AS total_units,
                    rs.commission_rate,
                    rs.zone_id,
                    rs.field_officer_id,
                    rs.zone_supervisor_id,
                    rs.zone_manager_id
                FROM cb_returns_merge_batches mb
                JOIN cb_returns_merge_batch_items mbi ON mbi.batch_id = mb.id
                JOIN sys_pm_properties p ON p.id = mbi.property_id
                JOIN cb_returns_summary rs ON rs.property_code = p.reference_number
                                           AND rs.return_period = ?
                                           AND rs.return_type = 'MAINRETURN'
            ),
            batch_aggregates AS (
                -- Aggregate per batch
                SELECT
                    batch_id,
                    batch_code,
                    batch_name,
                    batch_client_id,
                    STRING_AGG(child_return_code, ',' ORDER BY child_return_code) AS child_return_codes,
                    STRING_AGG(child_summary_id::text, ',' ORDER BY child_return_code) AS child_summary_ids,
                    STRING_AGG(child_return_id::text, ',' ORDER BY child_return_code) AS child_return_ids,
                    COUNT(*) AS property_count,
                    -- Aggregate correct columns
                    SUM(total_credit)       AS total_credit,
                    SUM(total_debit)        AS total_debit,
                    SUM(net_amount)         AS net_amount,
                    SUM(commission_amount)  AS commission_amount,
                    SUM(commission_total)   AS commission_total,
                    SUM(vatable_total)      AS vatable_total,
                    SUM(transaction_amount) AS transaction_amount,
                    SUM(net_balance)        AS net_balance,
                    SUM(payable_amount)     AS payable_amount,
                    SUM(non_payable_amount) AS non_payable_amount,
                    SUM(payable_units)      AS payable_units,
                    SUM(total_units)        AS total_units,
                    AVG(commission_rate)    AS commission_rate,
                    MIN(zone_id) AS zone_id,
                    MIN(field_officer_id) AS field_officer_id,
                    MIN(zone_supervisor_id) AS zone_supervisor_id,
                    MIN(zone_manager_id) AS zone_manager_id
                FROM batch_property_returns
                GROUP BY batch_id, batch_code, batch_name, batch_client_id
                HAVING COUNT(*) > 1  -- Only create MASTERRETURN when multiple properties
            )
            SELECT * FROM batch_aggregates
        ", [$returnPeriod]);

        $masterReturnCount = 0;

        foreach ($masterReturns as $batch) {
            // Generate MASTERRETURN code: R1YYYYMM + LAN-XXXXX
            $landlordCode = $this->getLandlordCodeForClient($batch->batch_client_id);
            $masterReturnCode = 'R1' . $returnPeriod . $landlordCode;

            // Get next return_id for MASTERRETURN
            $nextReturnId = DB::selectOne("SELECT COALESCE(MAX(return_id), 0) + 1 as next_id FROM cb_returns_summary")->next_id;

            // Insert MASTERRETURN summary using CORRECT columns
            DB::statement("
                INSERT INTO cb_returns_summary (
                    return_code, return_period, return_type,
                    property_code, landlord_code, landlord_name,
                    year, month, return_description,
                    total_credit, total_debit, net_amount,
                    commission_amount, commission_total, commission_rate, vatable_total,
                    transaction_amount, net_balance,
                    payable_amount, non_payable_amount, payable_units, total_units,
                    approved_status, paid_status, prepared_status,
                    transaction_ref, return_id,
                    client_id, zone_id, field_officer_id,
                    zone_supervisor_id, zone_manager_id,
                    server_time, user_id
                )
                SELECT
                    ?,
                    ?::integer,
                    'MASTERRETURN',
                    '',  -- property_code empty for MASTERRETURN
                    c.reference_number,
                    c.names,
                    ?, ?,
                    'Returns for ' || ? || ' ' || ?,
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?,
                    ?, ?, ?, ?,
                    0, 0, 1,
                    ?,  -- child return codes
                    ?,  -- return_id
                    ?,
                    ?, ?, ?, ?,
                    NOW(),
                    ?
                FROM sys_pm_clients c
                WHERE c.id = ?
            ", [
                $masterReturnCode,
                $returnPeriod,
                $returnYear,
                $returnMonth,
                date('F', mktime(0, 0, 0, $returnMonth, 1)),
                $returnYear,
                $batch->total_credit,
                $batch->total_debit,
                $batch->net_amount,
                $batch->commission_amount,
                $batch->commission_total,
                $batch->commission_rate,
                $batch->vatable_total,
                $batch->transaction_amount,
                $batch->net_balance,
                $batch->payable_amount,
                $batch->non_payable_amount,
                $batch->payable_units,
                $batch->total_units,
                $batch->child_return_codes,
                $nextReturnId,
                $batch->batch_client_id,
                $batch->zone_id,
                $batch->field_officer_id,
                $batch->zone_supervisor_id,
                $batch->zone_manager_id,
                $generatedBy,
                $batch->batch_client_id
            ]);

            // Mark child MAINRETURNs as merged
            $this->markChildReturnsAsMerged(
                $batch->child_summary_ids,
                $masterReturnCode,
                $returnPeriod
            );

            // Create RETURN NET SUM lines for MASTERRETURN
            $this->copyLinesToMasterReturn(
                $masterReturnCode,
                explode(',', $batch->child_return_codes),
                $returnPeriod,
                $nextReturnId
            );

            // Add landlord-level deductions to MASTERRETURN
            $this->insertMasterReturnDeductions(
                $masterReturnCode,
                $returnPeriod,
                $nextReturnId,
                $batch->batch_client_id,
                $returnYear,
                $returnMonth,
                $generatedBy
            );

            $masterReturnCount++;

            Log::info("ReturnsMasterReturnTrait: MASTERRETURN created", [
                'master_code'    => $masterReturnCode,
                'batch'          => $batch->batch_name,
                'properties'     => $batch->property_count,
                'child_returns'  => $batch->child_return_codes,
                'total_credit'   => $batch->total_credit,
                'net_amount'     => $batch->net_amount
            ]);
        }

        return $masterReturnCount;
    }

    /**
     * Insert landlord-level deductions from cb_landlord_account to MASTERRETURN.
     *
     * @param string $masterReturnCode
     * @param string $returnPeriod
     * @param int $returnId
     * @param int $clientId Landlord client ID
     * @param int $returnYear
     * @param int $returnMonth
     * @param int $userId
     * @return int Lines inserted
     */
    protected function insertMasterReturnDeductions(
        string $masterReturnCode,
        string $returnPeriod,
        int $returnId,
        int $clientId,
        int $returnYear,
        int $returnMonth,
        int $userId
    ): int {
        // Insert landlord-level deductions from cb_landlord_account
        $inserted = DB::affectingStatement("
            INSERT INTO cb_returns_clients (
                return_code, return_period, transaction_number, transaction_date,
                entry_description, entry_type, dr_cr,
                property_code, net_amount, narration,
                property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id,
                return_id, is_commissionable, transaction_source, transaction_ref,
                server_time, user_id
            )
            SELECT
                ?,
                ?::integer,
                la.transaction_number,
                TO_CHAR(NOW(), 'DD-Mon-YYYY'),
                'DEDUCTION',
                COALESCE(ii.item_name, 'Advance Payments-From Landlord Acc'),
                'DR',
                '',  -- empty property_code for landlord-level deductions
                la.debit_amount,
                COALESCE(la.entry_particulars, la.entry_description),
                NULL,
                la.client_id,
                NULL, NULL, NULL, NULL,
                ?,
                'No',
                'LANDLORD ACCOUNT',
                la.transaction_number,
                NOW(),
                ?
            FROM cb_landlord_account la
            LEFT JOIN sys_set_invoiceable_items ii ON ii.billing_code = la.entry_type_code
            WHERE la.client_id = ?
              AND la.debit_amount > 0
              AND COALESCE(la.is_recovered, 0) != 1
              AND la.year = ?
              AND la.month = ?
              AND la.entry_type_code IN ('0007','0008','0009','0010','0012','0013','0014','0015','0016','0019','0020','0023','0024','0026','0030','0036')
        ", [$masterReturnCode, $returnPeriod, $returnId, $userId, $clientId, $returnYear, $returnMonth]);

        // Mark as recovered
        if ($inserted > 0) {
            DB::statement("
                UPDATE cb_landlord_account
                SET is_recovered = 1,
                    recovery_year = ?,
                    recovery_month = ?,
                    return_code = ?,
                    recovery_server_time = NOW()
                WHERE client_id = ?
                  AND debit_amount > 0
                  AND COALESCE(is_recovered, 0) != 1
                  AND year = ?
                  AND month = ?
                  AND entry_type_code IN ('0007','0008','0009','0010','0012','0013','0014','0015','0016','0019','0020','0023','0024','0026','0030','0036')
            ", [$returnYear, $returnMonth, $masterReturnCode, $clientId, $returnYear, $returnMonth]);

            Log::info("ReturnsMasterReturnTrait: Added landlord deductions to MASTERRETURN", [
                'master_code' => $masterReturnCode,
                'client_id'   => $clientId,
                'count'       => $inserted
            ]);
        }

        return $inserted;
    }

    /**
     * Get landlord reference code for a client.
     *
     * @param int $clientId
     * @return string Landlord code (e.g., LAN-00015)
     */
    protected function getLandlordCodeForClient(int $clientId): string
    {
        $result = DB::selectOne("
            SELECT reference_number
            FROM sys_pm_clients
            WHERE id = ?
        ", [$clientId]);

        return $result ? $result->reference_number : '';
    }

    /**
     * Mark child MAINRETURN summaries as merged into a MASTERRETURN.
     *
     * @param string $childReturnIds Comma-separated return IDs
     * @param string $masterReturnCode The MASTERRETURN code
     * @param string $returnPeriod YYYYMM format
     * @return void
     */
    protected function markChildReturnsAsMerged(
        string $childReturnIds,
        string $masterReturnCode,
        string $returnPeriod
    ): void {
        $ids = explode(',', $childReturnIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        DB::statement("
            UPDATE cb_returns_summary
            SET parent_return_code = ?,
                is_merged         = true
            WHERE id IN ({$placeholders})
              AND return_period = ?
        ", array_merge([$masterReturnCode], $ids, [$returnPeriod]));
    }

    /**
     * Create RETURN NET SUM lines for MASTERRETURN.
     * Instead of copying all child lines, creates summary lines per child MAINRETURN.
     *
     * @param string $masterReturnCode The MASTERRETURN code
     * @param array $childReturnCodes Array of child return codes
     * @param string $returnPeriod YYYYMM format
     * @param int $masterReturnId The return_id for the MASTERRETURN
     * @return void
     */
    protected function copyLinesToMasterReturn(
        string $masterReturnCode,
        array $childReturnCodes,
        string $returnPeriod,
        int $masterReturnId
    ): void {
        if (empty($childReturnCodes)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($childReturnCodes), '?'));

        // Create RETURN NET SUM lines - one per child MAINRETURN showing its net total
        DB::statement("
            INSERT INTO cb_returns_clients (
                return_code, return_period, transaction_number, transaction_date,
                entry_description, entry_type, dr_cr,
                property_code, unit_code, tenant_name,
                commission_amount, vat_amount, net_amount, amount_payable_landlord,
                narration, pay_percentage, payable_status,
                property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id, unit_id, tenant_id,
                return_id, invoice_id,
                is_commissionable, transaction_source, transaction_ref,
                server_time, user_id
            )
            SELECT
                ?,  -- master return code
                ?::integer,  -- return_period as integer
                rs.property_code,  -- transaction_number = property code (fits varchar 12)
                TO_CHAR(NOW(), 'DD-Mon-YYYY'),
                'NETSUM',  -- entry_description
                'RETURN NET SUM',  -- entry_type
                'CR',  -- dr_cr
                rs.property_code,
                NULL,  -- unit_code
                rs.landlord_name,  -- tenant_name = landlord name
                rs.commission_amount,
                COALESCE(rs.vatable_total, 0),
                rs.net_amount,  -- The net total for this child
                rs.net_amount,
                'Net return for property ' || rs.property_code,
                100,  -- pay_percentage
                1,    -- payable_status
                p.id,  -- property_id
                rs.client_id,
                rs.zone_id,
                rs.field_officer_id,
                rs.zone_supervisor_id,
                rs.zone_manager_id,
                NULL,  -- unit_id
                NULL,  -- tenant_id
                ?,  -- master return_id
                NULL,  -- invoice_id
                0,  -- is_commissionable
                'MASTERRETURN',
                rs.return_code,  -- transaction_ref = child return code
                NOW(),
                rs.user_id
            FROM cb_returns_summary rs
            LEFT JOIN sys_pm_properties p ON p.reference_number = rs.property_code
            WHERE rs.return_code IN ({$placeholders})
              AND rs.return_period = ?
              AND rs.return_type = 'MAINRETURN'
        ", array_merge([$masterReturnCode, $returnPeriod, $masterReturnId], $childReturnCodes, [$returnPeriod]));

        Log::info("ReturnsMasterReturnTrait: created RETURN NET SUM lines for MASTERRETURN", [
            'master_code'   => $masterReturnCode,
            'child_codes'   => $childReturnCodes,
            'return_period' => $returnPeriod
        ]);
    }

    /**
     * Get batched properties for a landlord.
     *
     * @param int $clientId Landlord client ID
     * @return array Properties in batches
     */
    protected function getBatchedPropertiesForLandlord(int $clientId): array
    {
        return DB::select("
            SELECT
                mb.id AS batch_id,
                mb.batch_reference AS batch_code,
                mb.landlord_name AS batch_name,
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
     *
     * @param int $propertyId
     * @return bool
     */
    protected function isPropertyInMergeBatch(int $propertyId): bool
    {
        $result = DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM cb_returns_merge_batch_items mbi
            JOIN cb_returns_merge_batches mb ON mb.id = mbi.batch_id
            WHERE mbi.property_id = ?
        ", [$propertyId]);

        return $result->cnt > 0;
    }

    /**
     * Get MASTERRETURN summaries for a period.
     *
     * @param string $returnPeriod YYYYMM format
     * @return array MASTERRETURN records
     */
    protected function getMasterReturnsForPeriod(string $returnPeriod): array
    {
        return DB::select("
            SELECT
                rs.*,
                c.names AS landlord_name,
                c.reference_number AS landlord_code
            FROM cb_returns_summary rs
            JOIN sys_pm_clients c ON c.id = rs.client_id
            WHERE rs.return_period = ?
              AND rs.return_type = 'MASTERRETURN'
            ORDER BY rs.return_code
        ", [$returnPeriod]);
    }
}
