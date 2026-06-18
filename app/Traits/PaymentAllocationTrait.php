<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PaymentAllocationTrait
 *
 * Low-level SQL helpers consumed by PaymentAllocationService.
 * All methods use DB::table() — no Eloquent models or relationships.
 *
 * Sections:
 *   A — Balance calculations   (payment remaining, invoice outstanding)
 *   B — Scenario determination (EXACT / PARTIAL / EXCESS)
 *   C — Data queries           (fetch payments, invoices, movement history)
 */
trait PaymentAllocationTrait
{
    // ─────────────────────────────────────────────────────────────────────────
    // A — BALANCE CALCULATIONS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * A-1. How much of this payment is still unallocated?
     * = payment.amount minus the SUM of all allocation rows already written for it.
     */
    protected function calculatePaymentBalance(int $paymentId, float $paymentAmount): float
    {
        $allocated = (float) DB::table('sys_pm_tenants_payments_invoices')
            ->where('payment_id', $paymentId)
            ->sum('amount_allocated');

        return $paymentAmount - $allocated;
    }

    /**
     * A-2. How much of this invoice is still outstanding?
     * = invoice.total_amount minus the SUM of all allocation rows already written for it.
     * Both balances are recalculated fresh on each call so it is safe to call sequentially.
     */
    protected function calculateInvoiceBalance(int $invoiceId, float $invoiceTotalAmount): float
    {
        $allocated = (float) DB::table('sys_pm_tenants_payments_invoices')
            ->where('invoice_id', $invoiceId)
            ->sum('amount_allocated');

        return $invoiceTotalAmount - $allocated;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // B — SCENARIO DETERMINATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * B-1. Compare payment available balance against invoice outstanding balance
     * and return the amounts and status codes for all three possible outcomes.
     *
     * Scenarios:
     *   EXACT_MATCH     — available == invoiceBalance  → both close fully
     *   PARTIAL_PAYMENT — available <  invoiceBalance  → payment exhausted (status 13), invoice partial (8)
     *   EXCESS_PAYMENT  — available >  invoiceBalance  → invoice cleared (9), payment keeps remainder (12)
     *
     * Status codes:
     *   Invoice:  8=Partially Cleared, 9=Cleared
     *   Payment: 12=Partially Allocated, 13=Fully Allocated
     *
     * @return array{scenario:string, allocate_amount:float, new_invoice_balance:float,
     *               new_payment_balance:float, invoice_status:int, payment_status:int}
     */
    protected function determineAllocationScenario(float $available, float $invoiceBalance): array
    {
        // Exact match — both sides close to zero
        if ($available == $invoiceBalance) {
            return [
                'scenario'            => 'EXACT_MATCH',
                'allocate_amount'     => $invoiceBalance,
                'new_invoice_balance' => 0.0,
                'new_payment_balance' => 0.0,
                'invoice_status'      => 9,  // Cleared
                'payment_status'      => 13, // Fully Allocated
            ];
        }

        // Payment is less than the invoice — payment is exhausted, invoice is partial
        if ($available < $invoiceBalance) {
            return [
                'scenario'            => 'PARTIAL_PAYMENT',
                'allocate_amount'     => $available,
                'new_invoice_balance' => round($invoiceBalance - $available, 2),
                'new_payment_balance' => 0.0,
                'invoice_status'      => 8,  // Partially Cleared
                'payment_status'      => 13, // Fully Allocated
            ];
        }

        // Payment exceeds the invoice — invoice cleared, payment has remainder for next invoice
        return [
            'scenario'            => 'EXCESS_PAYMENT',
            'allocate_amount'     => $invoiceBalance,
            'new_invoice_balance' => 0.0,
            'new_payment_balance' => round($available - $invoiceBalance, 2),
            'invoice_status'      => 9,  // Cleared
            'payment_status'      => 12, // Partially Allocated — remainder continues to next invoice
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // C — DATA QUERIES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * C-1. Fetch all payments that still have an unallocated balance (status 11 or 12),
     * sorted oldest payment_date first.
     *
     * Uses a pre-aggregated LEFT JOIN subquery to stay PostgreSQL-safe.
     * (PostgreSQL rejects SELECT p.* with a GROUP BY unless all p.* columns are listed.)
     * The subquery computes total_allocated per payment in one pass, then the outer
     * query filters for rows where amount > total_allocated.
     *
     * The resulting rows carry unallocated_amount as a virtual column so callers do
     * not need a second query to determine the remaining balance.
     */
    protected function getUnallocatedPayments(
        ?int $leaseId  = null,
        ?int $unitId   = null,
        ?int $clientId = null
    ): Collection {
        // Sub-aggregate: total already allocated per payment
        $allocatedSub = DB::table('sys_pm_tenants_payments_invoices')
            ->select(['payment_id', DB::raw('SUM(amount_allocated) as total_allocated')])
            ->groupBy('payment_id');

        return DB::table('sys_pm_tenants_payments as p')
            ->select(['p.*', DB::raw('p.amount - COALESCE(a.total_allocated, 0) as unallocated_amount')])
            ->leftJoinSub($allocatedSub, 'a', 'p.id', '=', 'a.payment_id')
            ->whereIn('p.current_status_id', [11, 12]) // 11=Unallocated, 12=Partially Allocated
            ->whereRaw('p.amount > COALESCE(a.total_allocated, 0)')
            ->when($leaseId,  fn($q) => $q->where('p.lease_id',  $leaseId))
            ->when($unitId,   fn($q) => $q->where('p.unit_id',   $unitId))
            ->when($clientId, fn($q) => $q->where('p.client_id', $clientId))
            ->orderBy('p.payment_date') // Oldest payments first
            ->get();
    }

    /**
     * C-2. Fetch all unsettled invoices for a lease, pre-sorted in allocation priority order:
     *   1. Oldest billing month first (arrears before current — DATE_TRUNC for PostgreSQL)
     *   2. Then by payment_priority on the invoiceable item (e.g. rent before water)
     *   3. Then by date_due (tiebreak within same month + priority)
     *
     * Only includes invoices with current_balance > 0 (outstanding only).
     * $debtorTypeId = 2 targets tenant debtors; adjust if needed.
     *
     * NOTE: DATE_TRUNC is PostgreSQL-specific. Replace with DATE_FORMAT for MySQL.
     */
    protected function getUnsettledInvoices(int $leaseId, int $debtorTypeId = 2): Collection
    {
        return DB::table('sys_pm_debtors_invoices as i')
            ->select(['i.*', 'si.item_name', 'si.payment_priority', 'si.advance_payable'])
            ->leftJoin('sys_set_invoiceable_items as si', 'i.item_type_id', '=', 'si.id')
            ->where('i.lease_id',       $leaseId)
            ->where('i.debtor_type_id', $debtorTypeId)
            ->where('i.current_balance', '>', 0)
            ->orderByRaw("DATE_TRUNC('month', i.date_due)") // Oldest billing month first
            ->orderBy('si.payment_priority')                // Lower number = higher priority
            ->orderBy('i.date_due')                         // Tiebreak within same month
            ->get();
    }

    /**
     * C-3. Check if the tenant transferred from another unit to reach this lease.
     * Returns the movement_request row (which carries the old lease_id) or null.
     * Used by Pass 1 to clear old-lease arrears before touching the current lease.
     */
    protected function checkTenantMovement(int $leaseId): ?object
    {
        return DB::table('sys_pm_movement_requests')
            ->where('new_lease_id', $leaseId)
            ->first();
    }

    /**
     * C-4. Fetch linked leases for multi-unit tenancies.
     * Not yet implemented in the current schema — always returns an empty collection.
     * When the schema is extended, replace this with the appropriate query.
     */
    protected function getLinkedLeases(int $leaseId): Collection
    {
        return collect(); // Stub — linked-lease schema not yet deployed
    }
}
