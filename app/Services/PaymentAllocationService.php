<?php

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\GlPostingTrait;
use App\Traits\PaymentAllocationTrait;
use App\Traits\ReturnThresholdTrait;

class PaymentAllocationService
{
    use PaymentAllocationTrait, ReturnThresholdTrait, GlPostingTrait;

    // ──────────────────────────────────────────────────────────────
    // PUBLIC API
    // ──────────────────────────────────────────────────────────────

    /** Allocate every unallocated payment system-wide (cron entry point). */
    public function processAllPayments(): array
    {
        return $this->processPayments();
    }

    /**
     * Allocate all unallocated payments, optionally scoped to a unit / client / lease.
     *
     * Processing order per payment:
     *   1. Old-lease invoices (if tenant moved from another unit)
     *   2. Current-lease invoices  — oldest billing month → priority → due date
     *   3. Linked-lease invoices   — stub, not yet implemented
     */
    public function processPayments(?int $unitId = null, ?int $clientId = null, ?int $leaseId = null): array
    {
        $totals = [
            'payments_processed' => 0,
            'allocations_created' => 0,
            'invoices_cleared'    => 0,
            'invoices_partial'    => 0,
            'returns_updated'     => 0,
            'errors'              => [],
        ];

        $payments = $this->getUnallocatedPayments($leaseId, $unitId, $clientId);

        if ($payments->isEmpty()) {
            return $totals;
        }

        Log::info('PaymentAllocationService: starting allocation', [
            'count'   => $payments->count(),
            'filters' => compact('unitId', 'clientId', 'leaseId'),
        ]);

        foreach ($payments as $payment) {
            try {
                DB::beginTransaction();
                $r = $this->processPayment($payment);
                DB::commit();

                $totals['payments_processed']++;
                $totals['allocations_created'] += $r['allocations'];
                $totals['invoices_cleared']     += $r['cleared'];
                $totals['invoices_partial']     += $r['partial'];
                $totals['returns_updated']      += $r['returns_updated'];
            } catch (\Exception $e) {
                DB::rollBack();
                $totals['errors'][] = [
                    'payment_id' => $payment->id,
                    'reference'  => $payment->reference ?? null,
                    'error'      => $e->getMessage(),
                ];
                Log::error('PaymentAllocationService: payment failed', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        Log::info('PaymentAllocationService: allocation complete', [
            'processed' => $totals['payments_processed'],
            'allocated' => $totals['allocations_created'],
            'errors'    => count($totals['errors']),
        ]);

        return $totals;
    }

    /**
     * Manually allocate a specific payment to a specific invoice.
     * Useful for corrections or operator overrides.
     */
    public function manualAllocate(int $paymentId, int $invoiceId): array
    {
        DB::beginTransaction();

        try {
            $payment = DB::table('sys_pm_tenants_payments')->where('id', $paymentId)->first();
            $invoice = DB::table('sys_pm_debtors_invoices')->where('id', $invoiceId)->first();

            if (!$payment || !$invoice) {
                throw new RuntimeException('Payment or invoice not found.');
            }

            $result = $this->allocateInvoice($invoice, $payment);

            DB::commit();

            return ['success' => $result['allocated'], 'result' => $result];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PaymentAllocationService: manual allocation failed', [
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ──────────────────────────────────────────────────────────────
    // READ / HISTORY
    // ──────────────────────────────────────────────────────────────

    public function getPaymentAllocations(int $paymentId): Collection
    {
        return DB::table('sys_pm_tenants_payments_invoices as a')
            ->join('sys_pm_debtors_invoices as i', 'a.invoice_id', '=', 'i.id')
            ->where('a.payment_id', $paymentId)
            ->select([
                'a.*',
                'i.reference_number as invoice_ref',
                'i.total_amount as invoice_total',
                'i.current_balance as invoice_balance',
            ])
            ->orderBy('a.date_time')
            ->get();
    }

    public function getInvoiceAllocations(int $invoiceId): Collection
    {
        return DB::table('sys_pm_tenants_payments_invoices as a')
            ->join('sys_pm_tenants_payments as p', 'a.payment_id', '=', 'p.id')
            ->where('a.invoice_id', $invoiceId)
            ->select([
                'a.*',
                'p.reference as payment_ref',
                'p.amount as payment_total',
                'p.payment_date',
            ])
            ->orderBy('a.date_time')
            ->get();
    }

    // ──────────────────────────────────────────────────────────────
    // CORE ALLOCATION
    // ──────────────────────────────────────────────────────────────

    /**
     * Process all invoices for one payment in the correct order.
     *
     * Uses unallocated_amount from the query result when available
     * (avoids an extra SUM query per payment).
     *
     * @return array{allocations:int, cleared:int, partial:int, returns_updated:int}
     */
    protected function processPayment(object $payment): array
    {
        $results = ['allocations' => 0, 'cleared' => 0, 'partial' => 0, 'returns_updated' => 0];

        $remaining = isset($payment->unallocated_amount)
            ? (float) $payment->unallocated_amount
            : $this->calculatePaymentBalance($payment->id, $payment->amount);

        if ($remaining <= 0) {
            $this->setPaymentStatus($payment->id, 13);
            return $results;
        }

        // Step 1 — clear old-lease invoices first if tenant moved
        if ($prev = $this->checkTenantMovement($payment->lease_id)) {
            [$remaining, $results] = $this->allocateToInvoices(
                $this->getUnsettledInvoices($prev->lease_id),
                $payment, $remaining, $results
            );
            if ($remaining <= 0) return $results;
        }

        // Step 2 — current lease
        [$remaining, $results] = $this->allocateToInvoices(
            $this->getUnsettledInvoices($payment->lease_id),
            $payment, $remaining, $results
        );
        if ($remaining <= 0) return $results;

        // Step 3 — linked leases (stub — always empty until schema is extended)
        foreach ($this->getLinkedLeases($payment->lease_id) as $linked) {
            [$remaining, $results] = $this->allocateToInvoices(
                $this->getUnsettledInvoices($linked->id),
                $payment, $remaining, $results
            );
            if ($remaining <= 0) return $results;
        }

        return $results;
    }

    /**
     * Iterate a list of invoices and allocate until the payment is exhausted.
     * Tracks the remaining balance locally — no extra DB query per invoice.
     *
     * @return array{float, array}
     */
    private function allocateToInvoices(Collection $invoices, object $payment, float $remaining, array $results): array
    {
        foreach ($invoices as $invoice) {
            $alloc = $this->allocateInvoice($invoice, $payment, $remaining);

            if ($alloc['allocated']) {
                $remaining -= $alloc['amount'];
                $results['allocations']++;

                if ($alloc['invoice_status'] === 9)      $results['cleared']++;
                elseif ($alloc['invoice_status'] === 8)  $results['partial']++;

                if ($alloc['return_updated']) $results['returns_updated']++;
            }

            if ($remaining <= 0) break;
        }

        return [$remaining, $results];
    }

    /**
     * Core single-invoice allocation.
     *
     * Handles EXACT_MATCH, PARTIAL_PAYMENT, and EXCESS_PAYMENT.
     * Writes the allocation record, updates invoice and payment status,
     * updates the return threshold, and posts a GL journal (non-fatal).
     *
     * @param  float|null $precomputedBalance  Pass when already known to avoid the re-query.
     * @return array{allocated:bool, scenario:string|null, amount:float, invoice_status:int|null, return_updated:bool}
     */
    public function allocateInvoice(object $invoice, object $payment, ?float $precomputedBalance = null): array
    {
        $empty = ['allocated' => false, 'scenario' => null, 'amount' => 0,
                  'invoice_status' => null, 'return_updated' => false];

        $available      = $precomputedBalance ?? $this->calculatePaymentBalance($payment->id, $payment->amount);
        $invoiceBalance = $this->calculateInvoiceBalance($invoice->id, $invoice->total_amount);

        if ($available <= 0) {
            $this->setPaymentStatus($payment->id, 13);
            return $empty;
        }

        if ($invoiceBalance <= 0) {
            return $empty;
        }

        $scenario = $this->determineAllocationScenario($available, $invoiceBalance);

        // Write allocation record
        $allocationId = DB::table('sys_pm_tenants_payments_invoices')->insertGetId([
            'date_time'                => now(),
            'payment_id'               => $payment->id,
            'invoice_id'               => $invoice->id,
            'amount_allocated'         => $scenario['allocate_amount'],
            'prev_unallocated_balance' => $available,
            'new_unallocated_balance'  => $scenario['new_payment_balance'],
            'invoice_amount'           => $invoiceBalance,
            'new_invoice_balance'      => $scenario['new_invoice_balance'],
            'trans_date_time'          => $payment->payment_date,
            'lease_id'                 => $payment->lease_id,
            'tenant_id'                => $payment->tenant_id,
            'client_id'                => $payment->client_id,
            'property_id'              => $payment->property_id,
            'unit_id'                  => $payment->unit_id,
        ]);

        if (!$allocationId) {
            throw new RuntimeException(
                "Failed to insert allocation record for payment {$payment->id} / invoice {$invoice->id}"
            );
        }

        // Update invoice balance + status
        $this->requireRowsAffected(
            DB::table('sys_pm_debtors_invoices')->where('id', $invoice->id)->update([
                'current_balance'   => $scenario['new_invoice_balance'],
                'current_status_id' => $scenario['invoice_status'],
            ]),
            "Invoice {$invoice->id}"
        );

        // Update payment status
        $this->requireRowsAffected(
            DB::table('sys_pm_tenants_payments')->where('id', $payment->id)->update([
                'current_status_id' => $scenario['payment_status'],
            ]),
            "Payment {$payment->id}"
        );

        $returnUpdated = $this->executeThresholdUpdate($invoice->id, $payment->id);

        // GL journal — non-fatal; never rolls back the allocation on GL failure
        try {
            $this->postGlEntry([
                'type'          => 'invoice_payment',
                'amount'        => $scenario['allocate_amount'],
                'date'          => $payment->payment_date
                                    ? Carbon::parse($payment->payment_date)->toDateString()
                                    : now()->toDateString(),
                'reference'     => $payment->reference ?? null,
                'narration'     => 'Tenant payment — ' . ($invoice->reference_number ?? 'Invoice #' . $invoice->id),
                'entered_by'    => $payment->created_by ?? null,
                'trans_type_id' => 10,
                'trans_details' => "{$invoice->id}:{$payment->id}:" . ($payment->reference ?? ''),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PaymentAllocationService: GL posting failed — allocation preserved', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
        }

        Log::debug('PaymentAllocationService: allocated', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'scenario'   => $scenario['scenario'],
            'amount'     => $scenario['allocate_amount'],
        ]);

        return [
            'allocated'      => true,
            'scenario'       => $scenario['scenario'],
            'amount'         => $scenario['allocate_amount'],
            'invoice_status' => $scenario['invoice_status'],
            'return_updated' => $returnUpdated,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────

    private function setPaymentStatus(int $paymentId, int $statusId): void
    {
        $this->requireRowsAffected(
            DB::table('sys_pm_tenants_payments')
                ->where('id', $paymentId)
                ->update(['current_status_id' => $statusId]),
            "Payment {$paymentId}"
        );
    }

    /** Throws if an UPDATE affected zero rows — catches silent misses. */
    private function requireRowsAffected(int $affected, string $label): void
    {
        if ($affected === 0) {
            throw new RuntimeException("{$label} update affected 0 rows — record may not exist.");
        }
    }
}
