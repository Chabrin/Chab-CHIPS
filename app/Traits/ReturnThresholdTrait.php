<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait ReturnThresholdTrait
{
    /**
     * Calculate payment threshold percentage for an invoice
     * 
     * Formula: (current_balance / commisionable_amount) × 100
     * Note: current_balance = PAID amount (not remaining balance)
     * 
     * @param float $commisionableAmount Invoice commisionable amount
     * @param float $currentBalance Invoice paid amount (what tenant has paid)
     * @return float Threshold percentage (0-100) - represents % PAID
     */
    protected function calculateThreshold($commisionableAmount, $currentBalance)
    {
        if ($commisionableAmount <= 0) {
            return 0;
        }

        // current_balance IS the paid amount
        $threshold = ($currentBalance / $commisionableAmount) * 100;

        return round($threshold, 2);
    }

    /**
     * Determine payable status based on threshold and business rules
     * 
     * Payable Status IDs:
     * 0 = Unpayable (< 85% and prev month < 85%)
     * 1 = Payable (= 100% or ≥ 85%)
     * 2 = No Past Invoice (first invoice for this tenant+item)
     * 3 = Past Passed Threshold (current < 85% but prev month ≥ 85%) - STILL PAYABLE!
     * 17 = Processed (payment made to landlord)
     * 
     * @param int $invoiceId
     * @param float $threshold Current threshold percentage
     * @param int $invoiceStatusId Current invoice status
     * @param string $advancePayable Item configuration (Yes/No)
     * @param int $leaseId
     * @param int $itemTypeId
     * @param string $invoiceDateDue
     * @return int Payable status ID
     */
    protected function determinePayableStatus(
        $invoiceId,
        $threshold,
        $invoiceStatusId,
        $advancePayable,
        $leaseId,
        $itemTypeId,
        $invoiceDateDue
    ) {
        $allowedThreshold = 85; // Configurable threshold
        $payableStatusId = 0; // Default: Unpayable

        // ═══════════════════════════════════════════════════════════
        // RULE 1: INVOICE FULLY PAID → ALWAYS PAYABLE
        // ═══════════════════════════════════════════════════════════
        if ($invoiceStatusId == 9) { // Cleared
            return 1; // Fully Paid - Payable
        }

        // ═══════════════════════════════════════════════════════════
        // RULE 2: CHECK IF RECOVERY INVOICE → UNPAYABLE
        // ═══════════════════════════════════════════════════════════
        $recoveryStatuses = [37, 35, 36, 34]; // Paid Owner, Sat Deposit, Absconded, Paid Vacant
        if (in_array($invoiceStatusId, $recoveryStatuses)) {
            return 0; // Unpayable
        }

        // ═══════════════════════════════════════════════════════════
        // RULE 3: ADVANCE PAYMENT LOGIC (85% THRESHOLD)
        // ═══════════════════════════════════════════════════════════
        if ($advancePayable === 'Yes') {
            // Check current month threshold
            if ($threshold >= $allowedThreshold) {
                return 1; // Current month passed threshold - Payable
            }

            // Check previous month threshold
            $previousMonthThreshold = $this->checkPreviousMonthThreshold(
                $leaseId,
                $itemTypeId,
                $invoiceDateDue
            );

            if ($previousMonthThreshold === null) {
                // No past invoice exists
                return 2; // No Past Invoice
            }

            if ($previousMonthThreshold >= $allowedThreshold) {
                // Previous month passed threshold
                return 3; // Past Passed Threshold - STILL PAYABLE!
            }

            // Neither current nor previous month passed threshold
            return 0; // Unpayable
        }

        // ═══════════════════════════════════════════════════════════
        // RULE 4: NO ADVANCE PAYMENT → MUST BE 100%
        // ═══════════════════════════════════════════════════════════
        if ($threshold >= 100) {
            return 1; // Fully paid - Payable
        }

        return 0; // Unpayable
    }

    /**
     * Check previous month's threshold for same lease and item type
     * 
     * @param int $leaseId
     * @param int $itemTypeId
     * @param string $currentInvoiceDateDue
     * @return float|null Previous month threshold or null if not found
     */
    protected function checkPreviousMonthThreshold($leaseId, $itemTypeId, $currentInvoiceDateDue)
    {
        try {
            // Calculate previous month
            $previousMonth = date('Y-m-01', strtotime('-1 month', strtotime($currentInvoiceDateDue)));

            // Get previous month return
            $previousReturn = DB::table('sys_pm_clients_returns as cr')
                ->where('cr.return_date', $previousMonth)
                ->where('cr.lease_id', $leaseId)
                ->where('cr.trans_type_id', $itemTypeId)
                ->where('cr.current_status_id', 1) // Pending
                ->select('cr.threshold_percentage')
                ->first();

            if ($previousReturn) {
                return floatval($previousReturn->threshold_percentage);
            }

            return null; // No previous month return found
        } catch (\Exception $e) {
            Log::error('Error checking previous month threshold: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update return record with new threshold and payable status
     * 
     * @param int $invoiceId
     * @param float $threshold
     * @param int $payableStatusId
     * @return bool
     * @throws \Exception If update fails
     */
    protected function updateReturnRecord($invoiceId, $threshold, $payableStatusId)
    {
        try {
            // Update return record
            $updated = DB::table('sys_pm_clients_returns')
                ->where('invoice_id', $invoiceId)
                ->update([
                    'threshold_percentage' => $threshold,        // REAL threshold from actual payment
                    'abstract_threshold' => $threshold,          // Update abstract too (real data overrides estimate)
                    'payable_status_id' => $payableStatusId
                ]);

            if ($updated === 0) {
                Log::warning("No return record updated for invoice {$invoiceId} - may not exist");
                return true;
            }

            // ═══════════════════════════════════════════════════════════════════
            // SYNC LANDLORD LEDGER: Update payable_status when return becomes payable
            // ═══════════════════════════════════════════════════════════════════
            // Payable statuses: 1 = Payable (≥85% or 100%), 2 = No Past Invoice, 3 = Past Passed Threshold
            // ═══════════════════════════════════════════════════════════════════

            if (in_array($payableStatusId, [1, 2, 3])) {
                $ledgerUpdated = DB::table('sys_pm_landlord_accounts_transaction')
                    ->where('invoice_id', $invoiceId)
                    ->where('amount_type', 'Credit')
                    ->where('transaction_type_id', 13) // Client return transaction
                    ->update([
                        'payable_status' => $payableStatusId
                    ]);

                Log::info("✓ Updated landlord ledger from payment", [
                    'invoice_id' => $invoiceId,
                    'threshold' => $threshold,
                    'payable_status_id' => $payableStatusId,
                    'ledger_records_updated' => $ledgerUpdated
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Database error updating return record', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to update return record: {$e->getMessage()}");
        }
    }

    /**
     * Get return record by invoice ID
     * 
     * @param int $invoiceId
     * @return object|null
     */
    protected function getReturnByInvoiceId($invoiceId)
    {
        return DB::table('sys_pm_clients_returns')
            ->where('invoice_id', $invoiceId)
            ->first();
    }

    /**
     * Get item configuration details
     * 
     * @param int $itemTypeId
     * @return object|null
     */
    protected function getItemConfiguration($itemTypeId)
    {
        return DB::table('sys_set_invoiceable_items')
            ->where('id', $itemTypeId)
            ->select(['id', 'item_name', 'advance_payable', 'commissionable', 'payment_priority'])
            ->first();
    }

    /**
     * Complete threshold update workflow for an invoice
     * Updates threshold and payable status based on all business rules
     * 
     * NOTE: This method does NOT use its own transaction.
     * It should be called within an existing transaction from the allocation service.
     * 
     * @param int $invoiceId
     * @param int|null $paymentId
     * @return bool
     * @throws \Exception If critical data is missing or update fails
     */
    protected function executeThresholdUpdate($invoiceId, $paymentId)
    {
        try {
            // Get invoice details
            $invoice = DB::table('sys_pm_debtors_invoices')
                ->where('id', $invoiceId)
                ->first();

            if (!$invoice) {
                throw new \Exception("Invoice not found for threshold update: {$invoiceId}");
            }

            // Get return record
            $return = $this->getReturnByInvoiceId($invoiceId);
            if (!$return) {
                Log::warning("No return record found for invoice {$invoiceId} - skipping threshold update");
                return true; // Not an error, just no return to update
            }

            // Get item configuration
            $itemConfig = $this->getItemConfiguration($invoice->item_type_id);
            if (!$itemConfig) {
                throw new \Exception("Item configuration not found for item_type_id: {$invoice->item_type_id}");
            }

            // Calculate threshold using commisionable_amount (not total_amount)
            $threshold = $this->calculateThreshold($invoice->commisionable_amount, $invoice->current_balance);

            // Determine payable status
            $payableStatusId = $this->determinePayableStatus(
                $invoiceId,
                $threshold,
                $invoice->current_status_id,
                $itemConfig->advance_payable,
                $invoice->lease_id,
                $invoice->item_type_id,
                $invoice->date_due
            );

            // Update return record
            $updated = $this->updateReturnRecord($invoiceId, $threshold, $payableStatusId);

            if (!$updated) {
                throw new \Exception("Failed to update return record for invoice {$invoiceId}");
            }

            // ═══════════════════════════════════════════════════════════════════
            // UPDATE LANDLORD LEDGER (STAGE 3 of flow)
            // ═══════════════════════════════════════════════════════════════════
            // Per INVOICE_PAYMENT_TO_LANDLORD_LEDGER_FLOW.md:
            // When return becomes payable (payable_status_id = 1), update the
            // corresponding landlord ledger entry to payable_status = 1
            // This makes the credit INCLUDED in landlord balance calculations
            // ═══════════════════════════════════════════════════════════════════

            if ($payableStatusId == 1) {
                $this->updateLandlordLedger($invoiceId);
            }

            Log::info("✓ Threshold updated", [
                'invoice_id' => $invoiceId,
                'payment_id' => $paymentId,
                'threshold' => $threshold,
                'payable_status_id' => $payableStatusId,
                'payable_status_name' => $this->getPayableStatusName($payableStatusId)
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("✗ Failed to execute threshold update", [
                'invoice_id' => $invoiceId,
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw to trigger transaction rollback
        }
    }

    /**
     * Update landlord ledger payable status
     * 
     * Per INVOICE_PAYMENT_TO_LANDLORD_LEDGER_FLOW.md:
     * When a return becomes payable (payable_status_id = 1), we need to update
     * the corresponding landlord ledger entry to mark it as payable.
     * This makes the credit entry INCLUDED in landlord balance calculations.
     * 
     * @param int $invoiceId Invoice ID to update landlord ledger for
     * @return bool
     * @throws \Exception If update fails
     */
    protected function updateLandlordLedger($invoiceId)
    {
        try {
            $affected = DB::table('sys_pm_landlord_accounts_transaction')
                ->where('invoice_id', $invoiceId)
                ->where('amount_type', 'Credit')
                ->where('transaction_type_id', 13) // Client Return
                ->update([
                    'payable_status' => 1 // Now PAYABLE - included in balance
                ]);

            if ($affected > 0) {
                Log::info("✓ Landlord ledger updated to payable", [
                    'invoice_id' => $invoiceId,
                    'records_updated' => $affected
                ]);
            } else {
                Log::warning("No landlord ledger entry found to update", [
                    'invoice_id' => $invoiceId
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update landlord ledger', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Database error updating landlord ledger: {$e->getMessage()}");
        }
    }

    /**
     * Get payable status name from ID
     * 
     * @param int $statusId
     * @return string
     */
    protected function getPayableStatusName($statusId)
    {
        $statuses = [
            0 => 'Unpayable',
            1 => 'Payable',
            2 => 'No Past Invoice',
            3 => 'Past Passed Threshold',
            17 => 'Processed'
        ];

        return $statuses[$statusId] ?? 'Unknown';
    }

    /**
     * Batch update thresholds for multiple invoices
     * Useful for cron jobs or batch processing
     * 
     * @param array $invoiceIds
     * @return array Results with success/failure counts
     */
    protected function batchUpdateThresholds(array $invoiceIds)
    {
        $results = [
            'total' => count($invoiceIds),
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($invoiceIds as $invoiceId) {
            try {
                if ($this->executeThresholdUpdate($invoiceId, null)) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'invoice_id' => $invoiceId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
