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
        // Get active merge batches with their aggregated data
        $masterReturns = DB::select("
            WITH batch_property_returns AS (
                -- Get MAINRETURN summaries for properties in active batches
                SELECT
                    mb.id AS batch_id,
                    mb.batch_code,
                    mb.batch_name,
                    mb.client_id AS batch_client_id,
                    rs.id AS child_return_id,
                    rs.return_code AS child_return_code,
                    rs.property_code,
                    rs.property_id,
                    rs.total_returns,
                    rs.rent_amount,
                    rs.amount_withheld,
                    rs.gross_commission,
                    rs.vat_commission,
                    rs.net_commission_paid,
                    rs.deductions,
                    rs.other_collections,
                    rs.net_amount_payable,
                    rs.zone_id,
                    rs.field_officer_id,
                    rs.zone_supervisor_id,
                    rs.zone_manager_id
                FROM cb_returns_merge_batches mb
                JOIN cb_returns_merge_batch_items mbi ON mbi.batch_id = mb.id
                JOIN cb_returns_summary rs ON rs.property_id = mbi.property_id
                                           AND rs.return_period = ?
                                           AND rs.return_type = 'MAINRETURN'
                WHERE mb.is_active = true
            ),
            batch_aggregates AS (
                -- Aggregate per batch
                SELECT
                    batch_id,
                    batch_code,
                    batch_name,
                    batch_client_id,
                    STRING_AGG(child_return_code, ',' ORDER BY child_return_code) AS child_return_codes,
                    STRING_AGG(child_return_id::text, ',' ORDER BY child_return_code) AS child_return_ids,
                    COUNT(*) AS property_count,
                    SUM(total_returns)        AS total_returns,
                    SUM(rent_amount)          AS rent_amount,
                    SUM(amount_withheld)      AS amount_withheld,
                    SUM(gross_commission)     AS gross_commission,
                    SUM(vat_commission)       AS vat_commission,
                    SUM(net_commission_paid)  AS net_commission_paid,
                    SUM(deductions)           AS deductions,
                    SUM(other_collections)    AS other_collections,
                    SUM(net_amount_payable)   AS net_amount_payable,
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

            // Insert MASTERRETURN summary
            DB::statement("
                INSERT INTO cb_returns_summary (
                    return_code, return_period, return_date,
                    property_code, property_name, landlord_name, landlord_code,
                    total_returns, rent_amount, amount_withheld,
                    gross_commission, vat_commission, net_commission_paid,
                    deductions, other_collections, net_amount_payable,
                    return_type, approved_status, transaction_ref,
                    client_id, zone_id, field_officer_id,
                    zone_supervisor_id, zone_manager_id,
                    year, month,
                    server_time, user_id
                )
                SELECT
                    ?,
                    ?::integer,
                    ?,
                    ?,  -- batch_code as property_code
                    ?,  -- batch_name as property_name
                    CONCAT_WS(' ', c.first_name, c.last_name),
                    c.reference_number,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    'MASTERRETURN',
                    0,
                    ?,  -- child return codes
                    ?,
                    ?, ?, ?, ?,
                    ?, ?,
                    NOW(),
                    ?
                FROM cb_clients c
                WHERE c.id = ?
            ", [
                $masterReturnCode,
                $returnPeriod,
                $transactionDate,
                $batch->batch_code,
                $batch->batch_name,
                $batch->total_returns,
                $batch->rent_amount,
                $batch->amount_withheld,
                $batch->gross_commission,
                $batch->vat_commission,
                $batch->net_commission_paid,
                $batch->deductions,
                $batch->other_collections,
                $batch->net_amount_payable,
                $batch->child_return_codes,
                $batch->batch_client_id,
                $batch->zone_id,
                $batch->field_officer_id,
                $batch->zone_supervisor_id,
                $batch->zone_manager_id,
                $returnYear,
                $returnMonth,
                $generatedBy,
                $batch->batch_client_id
            ]);

            // Mark child MAINRETURNs as merged
            $this->markChildReturnsAsMerged(
                $batch->child_return_ids,
                $masterReturnCode,
                $returnPeriod
            );

            // Copy all line items from child returns to MASTERRETURN
            $this->copyLinesToMasterReturn(
                $masterReturnCode,
                $batch->child_return_ids,
                $returnPeriod
            );

            $masterReturnCount++;

            Log::info("ReturnsMasterReturnTrait: MASTERRETURN created", [
                'master_code'    => $masterReturnCode,
                'batch'          => $batch->batch_name,
                'properties'     => $batch->property_count,
                'child_returns'  => $batch->child_return_codes,
                'net_payable'    => $batch->net_amount_payable
            ]);
        }

        return $masterReturnCount;
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
            FROM cb_clients
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
     * Copy line items from child returns to MASTERRETURN.
     * Preserves original line details but updates return_code.
     *
     * @param string $masterReturnCode The MASTERRETURN code
     * @param string $childReturnIds Comma-separated child return IDs
     * @param string $returnPeriod YYYYMM format
     * @return void
     */
    protected function copyLinesToMasterReturn(
        string $masterReturnCode,
        string $childReturnIds,
        string $returnPeriod
    ): void {
        $ids = explode(',', $childReturnIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Insert new lines with MASTERRETURN code, linking back to original
        DB::statement("
            INSERT INTO cb_returns_clients (
                return_code, return_period, transaction_number, transaction_date,
                entry_description, entry_type, dr_cr,
                property_code, unit_code, tenant_name,
                invoice_amount, commission_rate, gross_commission_rate,
                commission_amount, vat_amount, net_amount, amount_payable_landlord,
                narration, pay_percentage, payable_status,
                property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id, unit_id, tenant_id,
                return_id, service_agreement_id, invoice_id,
                is_commissionable, transaction_source, transaction_ref,
                deduction_point_2,
                server_time, user_id
            )
            SELECT
                ?,  -- master return code
                return_period,
                transaction_number,
                transaction_date,
                entry_description,
                entry_type,
                dr_cr,
                property_code,
                unit_code,
                tenant_name,
                invoice_amount,
                commission_rate,
                gross_commission_rate,
                commission_amount,
                vat_amount,
                net_amount,
                amount_payable_landlord,
                narration,
                pay_percentage,
                payable_status,
                property_id,
                client_id,
                zone_id,
                field_officer_id,
                zone_supervisor_id,
                zone_manager_id,
                unit_id,
                tenant_id,
                NULL,  -- will be linked later
                service_agreement_id,
                invoice_id,
                is_commissionable,
                transaction_source,
                return_code,  -- original return code as reference
                deduction_point_2,
                server_time,
                user_id
            FROM cb_returns_clients
            WHERE return_id IN ({$placeholders})
              AND return_period = ?
        ", array_merge([$masterReturnCode], $ids, [$returnPeriod]));
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
                mb.batch_code,
                mb.batch_name,
                mbi.property_id,
                p.reference_number AS property_code,
                p.property_name
            FROM cb_returns_merge_batches mb
            JOIN cb_returns_merge_batch_items mbi ON mbi.batch_id = mb.id
            JOIN sys_pm_properties p ON p.id = mbi.property_id
            WHERE mb.client_id = ?
              AND mb.is_active = true
            ORDER BY mb.batch_code, p.reference_number
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
              AND mb.is_active = true
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
                c.first_name,
                c.last_name,
                c.reference_number AS landlord_code
            FROM cb_returns_summary rs
            JOIN cb_clients c ON c.id = rs.client_id
            WHERE rs.return_period = ?
              AND rs.return_type = 'MASTERRETURN'
            ORDER BY rs.return_code
        ", [$returnPeriod]);
    }
}
