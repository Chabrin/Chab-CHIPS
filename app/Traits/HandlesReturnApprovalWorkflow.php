<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Handles the complete post-approval workflow for client returns.
 * 
 * Based on POST-APPROVAL RETURNS WORKFLOW DOCUMENTATION
 * Implements the following stages:
 * 1. Landlord Ledger Credit Entry
 * 2. Commission Debit Entry (if applicable)
 * 3. GL Entries for Commission
 * 4. Status Updates
 */
trait HandlesReturnApprovalWorkflow
{
    /**
     * Process final approval of returns batch.
     * This is the main entry point triggered when accountant approves.
     * 
     * @param object $batch The ClientReturnsBatch model
     * @param int $approvedBy User ID who approved
     * @param string|null $comments Approval comments
     * @return bool Success status
     */
    protected function processFinalApproval($batch, $approvedBy, $comments = null)
    {
        try {
            Log::info('Starting final approval workflow', [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'approved_by' => $approvedBy
            ]);

            $batchId = $batch->batch_id ?? (str_replace('-', '', $batch->batch_number) . '-' . $batch->id);
            $processedCount = 0;

            // Process returns in chunks to avoid memory exhaustion
            // Filter by BOTH batch_id AND property_id to only process returns for this specific property
            DB::table('sys_pm_clients_returns')
                ->where('batch_id', $batchId)
                ->where('property_id', $batch->property_id)
                ->orderBy('id')
                ->chunk(100, function ($returns) use ($approvedBy, &$processedCount) {
                    foreach ($returns as $return) {
                        DB::beginTransaction();

                        try {
                            // Update return status to Approved (current_status_id = 5)
                            $this->updateReturnStatus($return->id, $approvedBy);

                            // Create landlord ledger credit entry
                            $this->createLandlordLedgerCredit($return, $approvedBy);

                            // Create commission debit entry (if applicable)
                            if ($return->comm_amount > 0) {
                                $this->createCommissionDebit($return, $approvedBy);
                            }

                            DB::commit();
                            $processedCount++;
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Log::error('Error processing return in batch', [
                                'return_id' => $return->id,
                                'error' => $e->getMessage()
                            ]);
                            // Continue processing other returns
                        }
                    }
                });

            // Record workflow activity
            $this->recordWorkflowActivity($batch, $approvedBy, $comments);

            Log::info('Final approval workflow completed successfully', [
                'batch_id' => $batch->id,
                'returns_processed' => $processedCount
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Error in final approval workflow', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get all returns associated with a batch.
     * 
     * @param object $batch
     * @return array
     */
    protected function getReturnsInBatch($batch)
    {
        // batch_id in sys_pm_clients_returns is formatted like: 202511-1770197000
        // We need to find returns that match this batch AND property
        // The batch_id format is: batch_number (without dash) + timestamp
        // Or we can use the batch's generated batch_id if it exists
        $batchId = $batch->batch_id ?? (str_replace('-', '', $batch->batch_number) . '-' . $batch->id);

        return DB::table('sys_pm_clients_returns')
            ->where('batch_id', $batchId)
            ->where('property_id', $batch->property_id)
            ->get();
    }

    /**
     * Update return status to Approved (status 5).
     * 
     * @param int $returnId
     * @param int $approvedBy
     * @return void
     */
    protected function updateReturnStatus($returnId, $approvedBy)
    {
        DB::table('sys_pm_clients_returns')
            ->where('id', $returnId)
            ->update([
                'current_status_id' => 5, // Approved
                'approval_status' => 1 // Approved
            ]);

        Log::info('Return status updated to Approved', [
            'return_id' => $returnId,
            'approved_by' => $approvedBy
        ]);
    }

    /**
     * Create CREDIT entry in landlord ledger for the return amount.
     * 
     * This represents the amount OWED TO the landlord.
     * Initially created as UNPAYABLE (payable_status = 0).
     * Will be updated to PAYABLE when tenant pays invoice.
     * 
     * Table: sys_pm_landlord_accounts_transaction
     * 
     * @param object $return
     * @param int $approvedBy
     * @return void
     */
    protected function createLandlordLedgerCredit($return, $approvedBy)
    {
        // Get unit code for narration
        $unitCode = DB::table('sys_pm_units')
            ->where('id', $return->unit_id)
            ->value('unit_code') ?? 'Unknown';

        // Create origin_id: format '33:return_id:timestamp'
        $originId = '33:' . $return->id . ':' . time();

        $ledgerData = [
            'date_time' => now(),
            'trans_date_time' => $return->return_date,
            'client_id' => $return->client_id,
            'property_id' => $return->property_id,
            'unit_id' => $return->unit_id,
            'lease_id' => $return->lease_id,
            'tenant_id' => $return->tenant_id,
            'invoice_id' => $return->invoice_id,
            'origin_id' => $originId,
            'transaction_type_id' => 13, // Client Return
            'narration' => 'Return Payable Unit - ' . $unitCode,
            'amount_type' => 'Credit',
            'amount' => $return->trans_amount,
            'current_status_id' => 1, // Pending
            'payable_status' => 0, // UNPAYABLE initially (will be updated when tenant pays)
            'sla_id' => $return->sla_id ?? null
        ];

        DB::table('sys_pm_landlord_accounts_transaction')->insert($ledgerData);

        Log::info('Landlord ledger credit entry created', [
            'return_id' => $return->id,
            'client_id' => $return->client_id,
            'amount' => $return->trans_amount,
            'origin_id' => $originId,
            'payable_status' => 0
        ]);
    }

    /**
     * Create DEBIT entry for commission charges (if applicable).
     * 
     * This reduces the amount owed to the landlord by the commission amount.
     * Also creates corresponding GL entry crediting commission revenue account.
     * 
     * Table: sys_pm_landlord_accounts_transaction
     * 
     * @param object $return
     * @param int $approvedBy
     * @return void
     */
    protected function createCommissionDebit($return, $approvedBy)
    {
        // Get unit code for narration
        $unitCode = DB::table('sys_pm_units')
            ->where('id', $return->unit_id)
            ->value('unit_code') ?? 'Unknown';

        // Create origin_id: format '33:return_id:timestamp:comm'
        $originId = '33:' . $return->id . ':' . time() . ':comm';

        $commissionData = [
            'date_time' => now(),
            'trans_date_time' => $return->return_date,
            'client_id' => $return->client_id,
            'property_id' => $return->property_id,
            'unit_id' => $return->unit_id,
            'lease_id' => $return->lease_id,
            'tenant_id' => $return->tenant_id,
            'invoice_id' => $return->invoice_id,
            'origin_id' => $originId,
            'transaction_type_id' => 21, // Commission Charge
            'narration' => 'Commission Charge Unit - ' . $unitCode,
            'amount_type' => 'Debit',
            'amount' => -abs($return->comm_amount), // Negative for debit
            'current_status_id' => 1, // Pending
            'payable_status' => 0,
            'sla_id' => $return->sla_id ?? null
        ];

        DB::table('sys_pm_landlord_accounts_transaction')->insert($commissionData);

        Log::info('Commission debit entry created', [
            'return_id' => $return->id,
            'client_id' => $return->client_id,
            'commission_amount' => $return->comm_amount,
            'origin_id' => $originId
        ]);

        // Create GL entry for commission revenue
        $this->createCommissionGLEntry($return, $approvedBy, $unitCode);
    }

    /**
     * Create GL entry crediting commission revenue account.
     * 
     * Table: sys_gl_entries
     * 
     * @param object $return
     * @param int $approvedBy
     * @param string $unitCode
     * @return void
     */
    protected function createCommissionGLEntry($return, $approvedBy, $unitCode)
    {
        // Get commission revenue account from GL settings
        // Try to find the rent_commission_account_id from any GL settings table
        $commissionAccountId = null;

        // Try different possible table names for GL settings
        $possibleTables = ['sys_gl_accounts_settings', 'sys_gl_settings', 'gl_settings'];

        foreach ($possibleTables as $table) {
            try {
                $settings = DB::table($table)->first();
                if ($settings && isset($settings->rent_commission_account_id)) {
                    $commissionAccountId = $settings->rent_commission_account_id;
                    break;
                }
            } catch (\Exception $e) {
                // Table doesn't exist, try next one
                continue;
            }
        }

        if (!$commissionAccountId) {
            Log::warning('Commission GL account not configured - skipping GL entry', [
                'return_id' => $return->id
            ]);
            return;
        }

        // Create journal entry
        $journalDate = date('Y-m-d');
        $transDetails = '33:' . $return->id; // Reference to return

        $glData = [
            'journal_date' => $journalDate,
            'account_id' => $commissionAccountId,
            'amount' => -abs($return->comm_amount), // Negative for credit
            'entry_type' => 'Credit',
            'narration' => 'Commission Charge Unit - ' . $unitCode,
            'trans_type_id' => 21, // Commission Charge
            'trans_details' => $transDetails,
            'entered_by' => $approvedBy,
            'entry_date' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ];

        DB::table('sys_gl_entries')->insert($glData);

        Log::info('Commission GL entry created', [
            'return_id' => $return->id,
            'account_id' => $commissionAccountId,
            'amount' => $return->comm_amount,
            'trans_details' => $transDetails
        ]);
    }

    /**
     * Record workflow activity for audit trail.
     * 
     * Table: sys_workflow_activities
     * 
     * @param object $batch
     * @param int $approvedBy
     * @param string|null $comments
     * @return void
     */
    protected function recordWorkflowActivity($batch, $approvedBy, $comments = null)
    {
        $activityData = [
            'trans_id' => $batch->id,
            'action_id' => 5, // Approved status
            'actioned_by' => $approvedBy,
            'actioned_date' => now(),
            'determinant' => 'Yes', // Final approval
            'comments' => $comments ?? 'Final approval granted - Batch processed'
        ];

        DB::table('sys_workflow_activities')->insert($activityData);

        Log::info('Workflow activity recorded', [
            'batch_id' => $batch->id,
            'actioned_by' => $approvedBy,
            'determinant' => 'Yes'
        ]);
    }

    /**
     * Check if a return has already been processed to avoid duplicates.
     * 
     * @param int $returnId
     * @return bool
     */
    protected function isReturnAlreadyProcessed($returnId)
    {
        // Check if landlord ledger entry already exists
        $exists = DB::table('sys_pm_landlord_accounts_transaction')
            ->where('origin_id', 'like', '33:' . $returnId . ':%')
            ->where('transaction_type_id', 13) // Client Return
            ->where('amount_type', 'Credit')
            ->exists();

        return $exists;
    }

    /**
     * Get total returns amount in batch for validation.
     * 
     * @param object $batch
     * @return float
     */
    protected function getBatchTotalAmount($batch)
    {
        $batchId = $batch->batch_id ?? (str_replace('-', '', $batch->batch_number) . '-' . $batch->id);

        return DB::table('sys_pm_clients_returns')
            ->where('batch_id', $batchId)
            ->where('property_id', $batch->property_id)
            ->sum('trans_amount');
    }

    /**
     * Validate batch before processing.
     * 
     * @param object $batch
     * @return array ['valid' => bool, 'message' => string]
     */
    protected function validateBatchForApproval($batch)
    {
        // Check if batch exists
        if (!$batch) {
            return ['valid' => false, 'message' => 'Batch not found'];
        }

        // Check if batch is active
        if (!$batch->is_active) {
            return ['valid' => false, 'message' => 'Batch is not active'];
        }

        // Check if batch has returns
        $batchId = $batch->batch_id ?? (str_replace('-', '', $batch->batch_number) . '-' . $batch->id);

        $returnsCount = DB::table('sys_pm_clients_returns')
            ->where('batch_id', $batchId)
            ->where('property_id', $batch->property_id)
            ->count();

        if ($returnsCount == 0) {
            return ['valid' => false, 'message' => 'No returns found in batch'];
        }

        return ['valid' => true, 'message' => 'Batch is valid'];
    }
}
