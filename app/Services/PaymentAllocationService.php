<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\PaymentAllocationTrait;
use App\Traits\ReturnThresholdTrait;

class PaymentAllocationService
{
    use PaymentAllocationTrait, ReturnThresholdTrait;

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * BACKEND SERVICE: Process ALL unallocated payments system-wide (no filters)
     * ═══════════════════════════════════════════════════════════════════════════
     * 
     * This is the main method for scheduled/cron execution.
     * Call this to process EVERYTHING automatically.
     * 
     * Use this when:
     * - Running scheduled/cron jobs
     * - Processing all pending payments automatically
     * - No specific filtering needed
     * 
     * @return array Results summary
     */
    public function processAllPayments()
    {
        Log::info("╔════════════════════════════════════════════════════════════════╗");
        Log::info("║   STARTING FULL PAYMENT ALLOCATION - ALL UNALLOCATED PAYMENTS  ║");
        Log::info("╚════════════════════════════════════════════════════════════════╝");

        return $this->processPayments(null, null, null);
    }

    /**
     * Main entry point - Process ALL unallocated payments system-wide
     * This is the BACKEND SERVICE method that runs automatically (cron/scheduled)
     * 
     * BEHAVIOR (matches guide exactly):
     * 1. Gets ALL unallocated/partially allocated payments (status 11, 12)
     * 2. For EACH payment:
     *    a) Finds ALL unsettled invoices for that lease
     *    b) Sorts invoices: Oldest Month → Priority → Date
     *    c) Allocates payment to EACH invoice until exhausted
     *    d) Updates invoice balances and return thresholds
     * 3. Handles tenant movement (clears old lease invoices first)
     * 4. Handles linked leases (multi-unit tenants)
     * 
     * FILTERS (optional - for manual/specific processing):
     * @param int|null $unitId Filter by specific unit (null = ALL units)
     * @param int|null $clientId Filter by specific landlord (null = ALL landlords)
     * @param int|null $leaseId Filter by specific lease (null = ALL leases)
     * 
     * @return array Results summary with counts and errors
     */
    public function processPayments($unitId = null, $clientId = null, $leaseId = null)
    {
        $results = [
            'payments_processed' => 0,
            'allocations_created' => 0,
            'invoices_cleared' => 0,
            'invoices_partial' => 0,
            'returns_updated' => 0,
            'errors' => []
        ];

        try {
            // Get payment-invoice pairs via optimized SQL query (single query instead of N+1)
            $pairs = $this->getPaymentInvoicePairs($unitId, $clientId, $leaseId, 1000);

            if ($pairs->isEmpty()) {
                Log::info("No payment-invoice pairs found");
                return $results;
            }

            // Group pairs by payment_id
            $paymentGroups = $pairs->groupBy('payment_id');

            Log::info("→ Starting SQL-optimized payment allocation", [
                'unique_payments' => $paymentGroups->count(),
                'total_pairs' => $pairs->count(),
                'unit_id' => $unitId,
                'client_id' => $clientId,
                'lease_id' => $leaseId
            ]);

            // Process each payment's invoices in a single transaction
            foreach ($paymentGroups as $paymentId => $invoicePairs) {
                try {
                    DB::beginTransaction();

                    $firstPair = $invoicePairs->first();

                    // Build payment object from first pair
                    $payment = (object) [
                        'id' => $firstPair->payment_id,
                        'amount' => $firstPair->payment_amount,
                        'payment_date' => $firstPair->payment_date,
                        'lease_id' => $firstPair->lease_id,
                        'tenant_id' => $firstPair->tenant_id,
                        'client_id' => $firstPair->client_id,
                        'property_id' => $firstPair->property_id,
                        'unit_id' => $firstPair->unit_id,
                        'reference' => $firstPair->payment_reference
                    ];

                    Log::debug("↳ Processing payment", [
                        'payment_id' => $payment->id,
                        'invoice_count' => $invoicePairs->count()
                    ]);

                    $paymentAllocations = 0;
                    $paymentCleared = 0;
                    $paymentPartial = 0;
                    $paymentReturnsUpdated = 0;

                    // Process each invoice for this payment
                    foreach ($invoicePairs as $pair) {
                        // Build invoice object
                        $invoice = (object) [
                            'id' => $pair->invoice_id,
                            'total_amount' => $pair->invoice_total,
                            'current_balance' => $pair->invoice_balance,
                            'current_status_id' => $pair->invoice_status,
                            'date_due' => $pair->date_due,
                            'item_type_id' => $pair->item_type_id,
                            'reference_number' => $pair->invoice_reference,
                            'item_name' => $pair->item_name,
                            'payment_priority' => $pair->payment_priority,
                            'advance_payable' => $pair->advance_payable
                        ];

                        // Use existing allocation logic
                        $allocationResult = $this->allocateInvoice($invoice, $payment);

                        if ($allocationResult['allocated']) {
                            $paymentAllocations++;

                            if ($allocationResult['invoice_status'] == 9) {
                                $paymentCleared++;
                            } elseif ($allocationResult['invoice_status'] == 8) {
                                $paymentPartial++;
                            }

                            if ($allocationResult['return_updated']) {
                                $paymentReturnsUpdated++;
                            }
                        }

                        // Check if payment exhausted
                        if (!$this->hasUnallocatedBalance($payment->id, $payment->amount)) {
                            break; // Move to next payment
                        }
                    }

                    DB::commit();

                    $results['payments_processed']++;
                    $results['allocations_created'] += $paymentAllocations;
                    $results['invoices_cleared'] += $paymentCleared;
                    $results['invoices_partial'] += $paymentPartial;
                    $results['returns_updated'] += $paymentReturnsUpdated;

                    Log::info("✓ Payment processed successfully", [
                        'payment_id' => $payment->id,
                        'allocations' => $paymentAllocations
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();

                    $error = [
                        'payment_id' => $paymentId,
                        'payment_ref' => $firstPair->payment_reference ?? 'N/A',
                        'amount' => $firstPair->payment_amount,
                        'error' => $e->getMessage(),
                        'type' => get_class($e)
                    ];

                    $results['errors'][] = $error;

                    Log::error("✗ Payment processing failed", $error);

                    // Continue processing other payments
                    continue;
                }
            }

            Log::info("✓ Payment allocation completed", [
                'payments_processed' => $results['payments_processed'],
                'total_allocations' => $results['allocations_created'],
                'errors' => count($results['errors'])
            ]);

            return $results;
        } catch (\Exception $e) {
            Log::error("✗ Critical error in processPayments", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Process a single payment - allocate to ALL applicable invoices
     * 
     * WORKFLOW (exactly as per guide):
     * STEP 1: Check if tenant moved - clear old lease invoices FIRST
     * STEP 2: Get ALL unsettled invoices for current lease
     * STEP 3: Get ALL invoices from linked leases (multi-unit tenants)
     * 
     * For EACH invoice found:
     * - Calculate remaining balances
     * - Determine scenario (exact/partial/excess)
     * - Create allocation record
     * - Update invoice and payment status
     * - Update return threshold
     * - Continue until payment exhausted OR no more invoices
     * 
     * @param object $payment Payment record
     * @return array Processing results (allocations, cleared, partial, returns_updated)
     */
    protected function processPayment($payment)
    {
        $results = [
            'allocations' => 0,
            'cleared' => 0,
            'partial' => 0,
            'returns_updated' => 0
        ];

        try {
            Log::debug("↳ Processing payment", [
                'payment_id' => $payment->id,
                'lease_id' => $payment->lease_id,
                'amount' => $payment->amount
            ]);

            // STEP 1: Check if tenant moved from another unit (clear old lease invoices first)
            if ($previousLease = $this->checkTenantMovement($payment->lease_id)) {
                Log::debug("  → Tenant moved detected, processing old lease invoices first", [
                    'old_lease_id' => $previousLease->lease_id
                ]);

                $oldInvoices = $this->getUnsettledInvoices($previousLease->lease_id);
                foreach ($oldInvoices as $invoice) {
                    $allocationResult = $this->allocateInvoice($invoice, $payment);
                    $this->updateResults($results, $allocationResult);

                    // Check if payment exhausted
                    if (!$this->hasUnallocatedBalance($payment->id, $payment->amount)) {
                        return $results;
                    }
                }
            }

            // STEP 2: Get current lease invoices
            $currentInvoices = $this->getUnsettledInvoices($payment->lease_id);

            if ($currentInvoices->isEmpty()) {
                Log::debug("  → No unsettled invoices found for lease {$payment->lease_id}");
                return $results;
            }

            Log::debug("  → Found {$currentInvoices->count()} unsettled invoices");

            foreach ($currentInvoices as $invoice) {
                $allocationResult = $this->allocateInvoice($invoice, $payment);
                $this->updateResults($results, $allocationResult);

                // Check if payment exhausted
                if (!$this->hasUnallocatedBalance($payment->id, $payment->amount)) {
                    return $results;
                }
            }

            // STEP 3: Check linked leases (multi-unit tenants)
            $linkedLeases = $this->getLinkedLeases($payment->lease_id);
            if ($linkedLeases->isNotEmpty()) {
                Log::debug("  → Processing {$linkedLeases->count()} linked leases");

                foreach ($linkedLeases as $linkedLease) {
                    $linkedInvoices = $this->getUnsettledInvoices($linkedLease->id);
                    foreach ($linkedInvoices as $invoice) {
                        $allocationResult = $this->allocateInvoice($invoice, $payment);
                        $this->updateResults($results, $allocationResult);

                        // Check if payment exhausted
                        if (!$this->hasUnallocatedBalance($payment->id, $payment->amount)) {
                            return $results;
                        }
                    }
                }
            }

            return $results;
        } catch (\Exception $e) {
            Log::error("✗ Error in processPayment", [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            throw $e; // Re-throw to trigger transaction rollback
        }
    }

    /**
     * Core allocation logic - allocate payment to a specific invoice
     * Handles all 3 scenarios: exact match, partial, excess
     * 
     * @param object $invoice Invoice record
     * @param object $payment Payment record
     * @return array Allocation result
     */
    public function allocateInvoice($invoice, $payment)
    {
        $result = [
            'allocated' => false,
            'scenario' => null,
            'amount' => 0,
            'invoice_status' => null,
            'return_updated' => false
        ];

        try {
            // Calculate payment remaining balance
            $unallocatedAmount = $this->calculatePaymentBalance($payment->id, $payment->amount);

            // If no money left in payment, mark as fully allocated and exit
            if (!($unallocatedAmount > 0)) {
                $this->updatePaymentStatus($payment->id, 13); // Fully Allocated
                return $result;
            }

            // Calculate invoice remaining balance
            $invoiceBalance = $this->calculateInvoiceBalance($invoice->id, $invoice->total_amount);

            // If invoice is already fully paid, skip
            if ($invoiceBalance <= 0) {
                return $result;
            }

            // Determine allocation scenario
            $scenario = $this->determineAllocationScenario($unallocatedAmount, $invoiceBalance);

            if ($scenario['allocate_amount'] > 0) {
                // Create allocation record
                $allocationId = $this->createAllocationRecord(
                    $payment,
                    $invoice,
                    $scenario['allocate_amount'],
                    $unallocatedAmount,
                    $scenario['new_payment_balance'],
                    $invoiceBalance,
                    $scenario['new_invoice_balance']
                );

                if (!$allocationId) {
                    throw new \Exception("Failed to create allocation record");
                }

                // Update invoice balance
                $invoiceUpdated = $this->updateInvoiceBalance(
                    $invoice->id,
                    $scenario['new_invoice_balance'],
                    $scenario['invoice_status']
                );

                if (!$invoiceUpdated) {
                    throw new \Exception("Failed to update invoice balance");
                }

                // Update payment status
                $paymentUpdated = $this->updatePaymentStatus($payment->id, $scenario['payment_status']);

                if (!$paymentUpdated) {
                    throw new \Exception("Failed to update payment status");
                }

                // Update return threshold (this will throw exception if fails)
                $returnUpdated = $this->executeThresholdUpdate($invoice->id, $payment->id);

                $result = [
                    'allocated' => true,
                    'scenario' => $scenario['scenario'],
                    'amount' => $scenario['allocate_amount'],
                    'invoice_status' => $scenario['invoice_status'],
                    'return_updated' => $returnUpdated
                ];

                Log::debug("    ✓ Allocation successful", [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'invoice_ref' => $invoice->reference_number ?? 'N/A',
                    'scenario' => $scenario['scenario'],
                    'amount' => number_format($scenario['allocate_amount'], 2)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("✗ Allocation failed", [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            throw $e; // Re-throw to trigger transaction rollback
        }
    }

    /**
     * Create allocation record in junction table
     * 
     * @param object $payment
     * @param object $invoice
     * @param float $allocateAmount
     * @param float $prevUnallocatedBalance
     * @param float $newUnallocatedBalance
     * @param float $invoiceAmount
     * @param float $newInvoiceBalance
     * @return int Allocation ID
     * @throws \Exception If insert fails
     */
    protected function createAllocationRecord(
        $payment,
        $invoice,
        $allocateAmount,
        $prevUnallocatedBalance,
        $newUnallocatedBalance,
        $invoiceAmount,
        $newInvoiceBalance
    ) {
        try {
            return DB::table('sys_pm_tenants_payments_invoices')->insertGetId([
                'date_time' => now(),
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount_allocated' => $allocateAmount,
                'prev_unallocated_balance' => $prevUnallocatedBalance,
                'new_unallocated_balance' => $newUnallocatedBalance,
                'invoice_amount' => $invoiceAmount,
                'new_invoice_balance' => $newInvoiceBalance,
                'trans_date_time' => $payment->payment_date,
                'lease_id' => $payment->lease_id,
                'tenant_id' => $payment->tenant_id,
                'client_id' => $payment->client_id,
                'property_id' => $payment->property_id,
                'unit_id' => $payment->unit_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create allocation record', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Database error creating allocation: {$e->getMessage()}");
        }
    }

    /**
     * Update invoice balance and status
     * 
     * @param int $invoiceId
     * @param float $newBalance
     * @param int $statusId
     * @return bool
     * @throws \Exception If update fails
     */
    protected function updateInvoiceBalance($invoiceId, $newBalance, $statusId)
    {
        try {
            $affected = DB::table('sys_pm_debtors_invoices')
                ->where('id', $invoiceId)
                ->update([
                    'current_balance' => $newBalance,
                    'current_status_id' => $statusId
                ]);

            if ($affected === 0) {
                throw new \Exception("No rows updated for invoice {$invoiceId}");
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update invoice balance', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Database error updating invoice: {$e->getMessage()}");
        }
    }

    /**
     * Update payment status
     * 
     * @param int $paymentId
     * @param int $statusId
     * @return bool
     * @throws \Exception If update fails
     */
    protected function updatePaymentStatus($paymentId, $statusId)
    {
        try {
            $affected = DB::table('sys_pm_tenants_payments')
                ->where('id', $paymentId)
                ->update([
                    'current_status_id' => $statusId
                ]);

            if ($affected === 0) {
                throw new \Exception("No rows updated for payment {$paymentId}");
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update payment status', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Database error updating payment: {$e->getMessage()}");
        }
    }

    /**
     * Check if payment still has unallocated balance
     * 
     * @param int $paymentId
     * @param float $paymentAmount
     * @return bool
     */
    protected function hasUnallocatedBalance($paymentId, $paymentAmount)
    {
        $balance = $this->calculatePaymentBalance($paymentId, $paymentAmount);
        return $balance > 0;
    }

    /**
     * Update results array with allocation result
     * 
     * @param array &$results Results array (passed by reference)
     * @param array $allocationResult Single allocation result
     * @return void
     */
    protected function updateResults(&$results, $allocationResult)
    {
        if ($allocationResult['allocated']) {
            $results['allocations']++;

            if ($allocationResult['invoice_status'] == 9) {
                $results['cleared']++;
            } elseif ($allocationResult['invoice_status'] == 8) {
                $results['partial']++;
            }

            if ($allocationResult['return_updated']) {
                $results['returns_updated']++;
            }
        }
    }

    /**
     * Get allocation history for a payment
     * 
     * @param int $paymentId
     * @return \Illuminate\Support\Collection
     */
    public function getPaymentAllocations($paymentId)
    {
        return DB::table('sys_pm_tenants_payments_invoices as api')
            ->join('sys_pm_debtors_invoices as i', 'api.invoice_id', '=', 'i.id')
            ->where('api.payment_id', $paymentId)
            ->select([
                'api.*',
                'i.reference_number as invoice_ref',
                'i.total_amount as invoice_total',
                'i.current_balance as invoice_balance'
            ])
            ->orderBy('api.date_time')
            ->get();
    }

    /**
     * Get allocation history for an invoice
     * 
     * @param int $invoiceId
     * @return \Illuminate\Support\Collection
     */
    public function getInvoiceAllocations($invoiceId)
    {
        return DB::table('sys_pm_tenants_payments_invoices as api')
            ->join('sys_pm_tenants_payments as p', 'api.payment_id', '=', 'p.id')
            ->where('api.invoice_id', $invoiceId)
            ->select([
                'api.*',
                'p.reference as payment_ref',
                'p.amount as payment_total',
                'p.payment_date'
            ])
            ->orderBy('api.date_time')
            ->get();
    }

    /**
     * Manual allocation of specific payment to specific invoice
     * Useful for manual corrections or overrides
     * 
     * @param int $paymentId
     * @param int $invoiceId
     * @return array Result
     */
    public function manualAllocate($paymentId, $invoiceId)
    {
        try {
            DB::beginTransaction();

            $payment = DB::table('sys_pm_tenants_payments')->where('id', $paymentId)->first();
            $invoice = DB::table('sys_pm_debtors_invoices')->where('id', $invoiceId)->first();

            if (!$payment || !$invoice) {
                throw new Exception("Payment or invoice not found");
            }

            $result = $this->allocateInvoice($invoice, $payment);

            DB::commit();

            return [
                'success' => $result['allocated'],
                'result' => $result
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in manual allocation: " . $e->getMessage());
            throw $e;
        }
    }
}
