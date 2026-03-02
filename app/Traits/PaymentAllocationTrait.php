<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait PaymentAllocationTrait
{
    /**
     * Calculate remaining unallocated balance for a payment
     * 
     * @param int $paymentId
     * @param float $paymentAmount
     * @return float
     */
    protected function calculatePaymentBalance($paymentId, $paymentAmount)
    {
        // Get sum of all allocations from this payment
        $sumAllocations = DB::table('sys_pm_tenants_payments_invoices')
            ->where('payment_id', $paymentId)
            ->sum('amount_allocated');

        return floatval($paymentAmount - $sumAllocations);
    }

    /**
     * Calculate remaining unpaid balance for an invoice
     * 
     * @param int $invoiceId
     * @param float $invoiceTotalAmount
     * @return float
     */
    protected function calculateInvoiceBalance($invoiceId, $invoiceTotalAmount)
    {
        // Get sum of all allocations to this invoice
        $sumAllocations = DB::table('sys_pm_tenants_payments_invoices')
            ->where('invoice_id', $invoiceId)
            ->sum('amount_allocated');

        return floatval($invoiceTotalAmount - $sumAllocations);
    }

    /**
     * Determine allocation scenario and amounts
     * Returns: [scenario, allocate_amount, new_invoice_balance, new_payment_balance, invoice_status, payment_status]
     * 
     * Scenarios:
     * 1. EXACT_MATCH: payment amount exactly matches invoice balance
     * 2. PARTIAL_PAYMENT: payment less than invoice balance (partial payment)
     * 3. EXCESS_PAYMENT: payment greater than invoice balance (excess/overpayment)
     * 
     * @param float $unallocatedAmount Payment remaining balance
     * @param float $invoiceBalance Invoice remaining balance
     * @return array
     */
    protected function determineAllocationScenario($unallocatedAmount, $invoiceBalance)
    {
        $allocateAmount = 0;
        $newInvoiceBalance = 0;
        $newPaymentBalance = 0;
        $invoiceStatus = null;
        $paymentStatus = null;
        $scenario = null;

        // SCENARIO 1: Payment EXACTLY matches invoice balance
        if ($unallocatedAmount == $invoiceBalance) {
            $scenario = 'EXACT_MATCH';
            $allocateAmount = $invoiceBalance;
            $invoiceStatus = 9; // Cleared
            $paymentStatus = 13; // Fully Allocated
            $newInvoiceBalance = 0;
            $newPaymentBalance = 0;
        }

        // SCENARIO 2: Payment LESS THAN invoice balance (partial payment)
        elseif ($unallocatedAmount < $invoiceBalance) {
            $scenario = 'PARTIAL_PAYMENT';
            $allocateAmount = $unallocatedAmount; // Use ALL remaining payment
            $invoiceStatus = 8; // Partially Cleared
            $paymentStatus = 13; // Fully Allocated (payment exhausted)
            $newInvoiceBalance = $invoiceBalance - $unallocatedAmount;
            $newPaymentBalance = 0; // Payment used up
        }

        // SCENARIO 3: Payment GREATER THAN invoice balance (excess payment)
        elseif ($unallocatedAmount > $invoiceBalance) {
            $scenario = 'EXCESS_PAYMENT';
            $allocateAmount = $invoiceBalance; // Use only what's needed
            $invoiceStatus = 9; // Cleared
            $paymentStatus = 12; // Partially Allocated (money left over)
            $newInvoiceBalance = 0;
            $newPaymentBalance = $unallocatedAmount - $invoiceBalance; // Leftover money
        }

        return [
            'scenario' => $scenario,
            'allocate_amount' => $allocateAmount,
            'new_invoice_balance' => $newInvoiceBalance,
            'new_payment_balance' => $newPaymentBalance,
            'invoice_status' => $invoiceStatus,
            'payment_status' => $paymentStatus
        ];
    }

    /**
     * Get unallocated payments for a lease or client
     * 
     * @param int|null $leaseId
     * @param int|null $unitId
     * @param int|null $clientId
     * @return \Illuminate\Support\Collection
     */
    protected function getUnallocatedPayments($leaseId = null, $unitId = null, $clientId = null)
    {
        $query = DB::table('sys_pm_tenants_payments as p')
            ->select([
                'p.*',
                DB::raw('(p.amount - COALESCE(SUM(api.amount_allocated), 0)) as unallocated_amount')
            ])
            ->leftJoin('sys_pm_tenants_payments_invoices as api', 'p.id', '=', 'api.payment_id')
            ->whereIn('p.current_status_id', [11, 12]) // Unallocated or Partially Allocated
            ->groupBy('p.id');

        if ($leaseId) {
            $query->where('p.lease_id', $leaseId);
        }

        if ($unitId) {
            $query->where('p.unit_id', $unitId);
        }

        if ($clientId) {
            $query->where('p.client_id', $clientId);
        }

        return $query->having('unallocated_amount', '>', 0)
            ->orderBy('p.payment_date')
            //->limit(100) // Process 100 payments per batch
            ->get();
    }

    /**
     * Get payment-invoice matching pairs via optimized SQL query
     * This replaces N+1 queries with a single JOIN query
     * Only matches:
     * - Payments with unallocated balance (status 11=Unallocated or 12=Partially Allocated)
     * - Invoices with unpaid balance (current_balance > 0)
     * 
     * @param int|null $unitId
     * @param int|null $clientId
     * @param int|null $leaseId
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    protected function getPaymentInvoicePairs($unitId = null, $clientId = null, $leaseId = null, $limit = 1000)
    {
        // Step 1: Get payments with their total allocated amounts (subquery)
        $paymentsSubquery = DB::table('sys_pm_tenants_payments as p')
            ->select([
                'p.*',
                DB::raw('COALESCE(SUM(api.amount_allocated), 0) as total_allocated'),
                DB::raw('(p.amount - COALESCE(SUM(api.amount_allocated), 0)) as payment_balance')
            ])
            ->leftJoin('sys_pm_tenants_payments_invoices as api', 'p.id', '=', 'api.payment_id')
            ->whereIn('p.current_status_id', [11, 12]) // Only unallocated or partially allocated
            ->groupBy('p.id')
            ->having('payment_balance', '>', 0); // Only payments with remaining balance

        // Apply payment filters to subquery
        if ($leaseId) {
            $paymentsSubquery->where('p.lease_id', $leaseId);
        }
        if ($unitId) {
            $paymentsSubquery->where('p.unit_id', $unitId);
        }
        if ($clientId) {
            $paymentsSubquery->where('p.client_id', $clientId);
        }

        // Step 2: Join with invoices to create payment-invoice pairs
        $query = DB::table(DB::raw("({$paymentsSubquery->toSql()}) as p"))
            ->mergeBindings($paymentsSubquery)
            ->select([
                // Payment fields
                'p.id as payment_id',
                'p.amount as payment_amount',
                'p.payment_date',
                'p.lease_id',
                'p.tenant_id',
                'p.client_id',
                'p.property_id',
                'p.unit_id',
                'p.current_status_id as payment_status',
                'p.reference as payment_reference',
                'p.payment_balance',
                // Invoice fields
                'i.id as invoice_id',
                'i.total_amount as invoice_total',
                'i.current_balance as invoice_balance',
                'i.current_status_id as invoice_status',
                'i.date_due',
                'i.item_type_id',
                'i.reference_number as invoice_reference',
                // Item configuration
                'si.item_name',
                'si.payment_priority',
                'si.advance_payable'
            ])
            // Join with invoices on same lease
            ->join('sys_pm_debtors_invoices as i', function ($join) {
                $join->on('p.lease_id', '=', 'i.lease_id')
                    ->where('i.debtor_type_id', '=', 2) // Tenants only
                    ->where('i.current_balance', '>', 0); // Only unpaid/partially paid invoices
            })
            // Join with item configuration for priority/settings
            ->leftJoin('sys_set_invoiceable_items as si', 'i.item_type_id', '=', 'si.id')
            // Order by payment date (oldest first), then invoice due date, then priority
            ->orderBy('p.payment_date')
            ->orderByRaw("DATE_FORMAT(i.date_due, '%Y-%m-01')")
            ->orderBy('si.payment_priority')
            ->orderBy('i.date_due')
            ->limit($limit);

        return $query->get();
    }

    /**
     * Get unsettled invoices for a lease
     * 
     * @param int $leaseId
     * @param int $debtorTypeId Default: 2 (Tenants)
     * @return \Illuminate\Support\Collection
     */
    protected function getUnsettledInvoices($leaseId, $debtorTypeId = 2)
    {
        return DB::table('sys_pm_debtors_invoices as i')
            ->select([
                'i.*',
                'si.item_name',
                'si.payment_priority',
                'si.advance_payable'
            ])
            ->leftJoin('sys_set_invoiceable_items as si', 'i.item_type_id', '=', 'si.id')
            ->where('i.lease_id', $leaseId)
            ->where('i.debtor_type_id', $debtorTypeId)
            ->where('i.current_balance', '>', 0) // Not fully paid
            ->orderByRaw("DATE_FORMAT(i.date_due, '%Y-%m-01')") // Month
            ->orderBy('si.payment_priority') // Priority from invoiceable items
            ->orderBy('i.date_due') // Date
            ->get();
    }

    /**
     * Check if tenant moved from another unit
     * 
     * @param int $leaseId
     * @return object|null Previous lease details
     */
    protected function checkTenantMovement($leaseId)
    {
        return DB::table('sys_pm_movement_requests')
            ->where('new_lease_id', $leaseId)
            ->first();
    }

    /**
     * Get linked leases for multi-unit tenants
     * Note: Feature not implemented in current database schema
     * 
     * @param int $leaseId
     * @return \Illuminate\Support\Collection
     */
    protected function getLinkedLeases($leaseId)
    {
        // Feature not available - no linked_lease_id column in database
        // Return empty collection to skip this step
        return collect([]);
    }
}
