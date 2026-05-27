<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait PaymentAllocationTrait
{
    // ──────────────────────────────────────────────────────────────
    // BALANCE CALCULATIONS
    // ──────────────────────────────────────────────────────────────

    protected function calculatePaymentBalance(int $paymentId, float $paymentAmount): float
    {
        $allocated = (float) DB::table('sys_pm_tenants_payments_invoices')
            ->where('payment_id', $paymentId)
            ->sum('amount_allocated');

        return $paymentAmount - $allocated;
    }

    protected function calculateInvoiceBalance(int $invoiceId, float $invoiceTotalAmount): float
    {
        $allocated = (float) DB::table('sys_pm_tenants_payments_invoices')
            ->where('invoice_id', $invoiceId)
            ->sum('amount_allocated');

        return $invoiceTotalAmount - $allocated;
    }

    // ──────────────────────────────────────────────────────────────
    // SCENARIO DETERMINATION
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array{scenario:string, allocate_amount:float, new_invoice_balance:float,
     *               new_payment_balance:float, invoice_status:int, payment_status:int}
     */
    protected function determineAllocationScenario(float $available, float $invoiceBalance): array
    {
        if ($available == $invoiceBalance) {
            return [
                'scenario'            => 'EXACT_MATCH',
                'allocate_amount'     => $invoiceBalance,
                'new_invoice_balance' => 0.0,
                'new_payment_balance' => 0.0,
                'invoice_status'      => 9,   // Cleared
                'payment_status'      => 13,  // Fully Allocated
            ];
        }

        if ($available < $invoiceBalance) {
            return [
                'scenario'            => 'PARTIAL_PAYMENT',
                'allocate_amount'     => $available,
                'new_invoice_balance' => $invoiceBalance - $available,
                'new_payment_balance' => 0.0,
                'invoice_status'      => 8,   // Partially Cleared
                'payment_status'      => 13,  // Payment exhausted
            ];
        }

        return [
            'scenario'            => 'EXCESS_PAYMENT',
            'allocate_amount'     => $invoiceBalance,
            'new_invoice_balance' => 0.0,
            'new_payment_balance' => $available - $invoiceBalance,
            'invoice_status'      => 9,   // Cleared
            'payment_status'      => 12,  // Partially Allocated (remainder left)
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // DATA QUERIES
    // ──────────────────────────────────────────────────────────────

    /**
     * Payments with an unallocated balance (status 11 or 12), oldest first.
     *
     * Uses a pre-aggregated subquery to stay PostgreSQL-safe — avoids
     * SELECT p.* with GROUP BY p.id, which PostgreSQL rejects.
     */
    protected function getUnallocatedPayments(
        ?int $leaseId  = null,
        ?int $unitId   = null,
        ?int $clientId = null
    ): Collection {
        $allocatedSub = DB::table('sys_pm_tenants_payments_invoices')
            ->select(['payment_id', DB::raw('SUM(amount_allocated) as total_allocated')])
            ->groupBy('payment_id');

        return DB::table('sys_pm_tenants_payments as p')
            ->select(['p.*', DB::raw('p.amount - COALESCE(a.total_allocated, 0) as unallocated_amount')])
            ->leftJoinSub($allocatedSub, 'a', 'p.id', '=', 'a.payment_id')
            ->whereIn('p.current_status_id', [11, 12])
            ->whereRaw('p.amount > COALESCE(a.total_allocated, 0)')
            ->when($leaseId,  fn($q) => $q->where('p.lease_id', $leaseId))
            ->when($unitId,   fn($q) => $q->where('p.unit_id', $unitId))
            ->when($clientId, fn($q) => $q->where('p.client_id', $clientId))
            ->orderBy('p.payment_date')
            ->get();
    }

    /**
     * Unsettled invoices for a lease, ordered: oldest billing month → priority → due date.
     *
     * Uses DATE_TRUNC (PostgreSQL). Switch to DATE_FORMAT for MySQL-only deployments.
     */
    protected function getUnsettledInvoices(int $leaseId, int $debtorTypeId = 2): Collection
    {
        return DB::table('sys_pm_debtors_invoices as i')
            ->select(['i.*', 'si.item_name', 'si.payment_priority', 'si.advance_payable'])
            ->leftJoin('sys_set_invoiceable_items as si', 'i.item_type_id', '=', 'si.id')
            ->where('i.lease_id', $leaseId)
            ->where('i.debtor_type_id', $debtorTypeId)
            ->where('i.current_balance', '>', 0)
            ->orderByRaw("DATE_TRUNC('month', i.date_due)")
            ->orderBy('si.payment_priority')
            ->orderBy('i.date_due')
            ->get();
    }

    /** Returns the previous lease record if this tenant moved units to reach $leaseId. */
    protected function checkTenantMovement(int $leaseId): ?object
    {
        return DB::table('sys_pm_movement_requests')
            ->where('new_lease_id', $leaseId)
            ->first();
    }

    /** Linked-lease support — not yet implemented in the current schema. */
    protected function getLinkedLeases(int $leaseId): Collection // @phpstan-ignore-line
    {
        return collect();
    }
}
