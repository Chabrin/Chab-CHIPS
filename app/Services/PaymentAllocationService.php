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

/**
 * PaymentAllocationService
 *
 * Implements Phase 2 of the payment lifecycle: matching tenant payments to invoices.
 *
 * Phase 1 (bank extract → payment record + GL Journals 1 & 2) is handled upstream
 * by processPaymentsLogs(). This service owns Phase 2 only.
 *
 * Allocation order per payment:
 *   Pass 1 — Previous lease (tenant transferred from another unit — clear old arrears first)
 *   Pass 2 — Current lease
 *   Pass 3 — Linked leases (adjacent units under one tenancy)
 *
 * Within each pass, invoices are sorted: oldest billing month → payment_priority → date_due.
 *
 * GL Journal 3 (commission + client funds split) is posted after every allocation row
 * via GlPostingTrait::postGlEntry() with type 'payment_allocation'. It is FATAL —
 * a GL failure throws and rolls back the entire payment transaction.
 *
 * Status codes used:
 *   Payment: 11=Unallocated, 12=Partially Allocated, 13=Fully Allocated
 *   Invoice:  8=Partially Cleared, 9=Cleared, 62=Cleared for Returns
 *   Recovery invoice: 34=Paid Vacant, 35=Sec Deposit, 36=Absconded, 37=Paid Owner
 */
class PaymentAllocationService
{
    use PaymentAllocationTrait, ReturnThresholdTrait, GlPostingTrait;

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION 1 — PUBLIC ENTRY POINTS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * 1-A. Cron / batch entry point.
     * Allocates every payment system-wide that is status 11 or 12 and has remaining balance.
     */
    public function processAllPayments(): array
    {
        return $this->processPayments();
    }

    /**
     * 1-B. Scoped allocation — same algorithm as 1-A but limited to one unit / client / lease.
     * Used by manual workflow triggers from the UI.
     *
     * Returns a summary counter array:
     *   payments_processed  — payment records touched
     *   allocations_created — allocation rows written
     *   invoices_cleared    — invoices fully settled (status 9)
     *   invoices_partial    — invoices partially settled (status 8)
     *   returns_updated     — return threshold records updated
     *   errors              — array of per-payment failures (payment rolls back; others continue)
     */
    public function processPayments(
        ?int $unitId   = null,
        ?int $clientId = null,
        ?int $leaseId  = null
    ): array {
        // 1. Initialise result counters
        $totals = [
            'payments_processed' => 0,
            'allocations_created' => 0,
            'invoices_cleared'    => 0,
            'invoices_partial'    => 0,
            'returns_updated'     => 0,
            'errors'              => [],
        ];

        // 2. Fetch all eligible payments (status 11/12, amount > already allocated)
        $payments = $this->getUnallocatedPayments($leaseId, $unitId, $clientId);

        if ($payments->isEmpty()) {
            return $totals;
        }

        Log::info('[Allocation] Batch started', [
            'payment_count' => $payments->count(),
            'filters'       => compact('unitId', 'clientId', 'leaseId'),
        ]);

        // 3. Process each payment in its own transaction so one failure does not abort others
        foreach ($payments as $payment) {
            try {
                DB::beginTransaction();

                $result = $this->processPayment($payment);

                DB::commit();

                $totals['payments_processed']++;
                $totals['allocations_created'] += $result['allocations'];
                $totals['invoices_cleared']     += $result['cleared'];
                $totals['invoices_partial']     += $result['partial'];
                $totals['returns_updated']      += $result['returns_updated'];
            } catch (\Exception $e) {
                DB::rollBack();
                $totals['errors'][] = [
                    'payment_id' => $payment->id,
                    'reference'  => $payment->reference ?? null,
                    'error'      => $e->getMessage(),
                ];
                Log::error('[Allocation] Payment failed — rolled back', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        Log::info('[Allocation] Batch complete', [
            'processed' => $totals['payments_processed'],
            'allocated' => $totals['allocations_created'],
            'errors'    => count($totals['errors']),
        ]);

        return $totals;
    }

    /**
     * 1-C. Manual single-pair allocation — operator correction or override.
     * Forces the specific payment → invoice pair regardless of sort order.
     */
    public function manualAllocate(int $paymentId, int $invoiceId): array
    {
        // 1. Load both records before opening the transaction
        $payment = DB::table('sys_pm_tenants_payments')->where('id', $paymentId)->first();
        $invoice = DB::table('sys_pm_debtors_invoices')->where('id', $invoiceId)->first();

        if (!$payment || !$invoice) {
            throw new RuntimeException('Payment or invoice not found.');
        }

        // 2. Allocate inside a transaction
        DB::beginTransaction();

        try {
            $result = $this->allocateInvoice($invoice, $payment);
            DB::commit();

            return ['success' => $result['allocated'], 'result' => $result];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Allocation] Manual allocation failed', [
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION 2 — READ / HISTORY
    // ─────────────────────────────────────────────────────────────────────────

    /** 2-A. All allocation rows for one payment (with invoice context), oldest first. */
    public function getPaymentAllocations(int $paymentId): Collection
    {
        return DB::table('sys_pm_tenants_payments_invoices as a')
            ->join('sys_pm_debtors_invoices as i', 'a.invoice_id', '=', 'i.id')
            ->where('a.payment_id', $paymentId)
            ->select([
                'a.*',
                'i.reference_number as invoice_ref',
                'i.total_amount     as invoice_total',
                'i.current_balance  as invoice_balance',
            ])
            ->orderBy('a.date_time')
            ->get();
    }

    /** 2-B. All allocation rows for one invoice (with payment context), oldest first. */
    public function getInvoiceAllocations(int $invoiceId): Collection
    {
        return DB::table('sys_pm_tenants_payments_invoices as a')
            ->join('sys_pm_tenants_payments as p', 'a.payment_id', '=', 'p.id')
            ->where('a.invoice_id', $invoiceId)
            ->select([
                'a.*',
                'p.reference    as payment_ref',
                'p.amount       as payment_total',
                'p.payment_date',
            ])
            ->orderBy('a.date_time')
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION 3 — CORE ALLOCATION: ONE PAYMENT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * 3-A. Run all three passes for a single payment.
     *
     * The $payment object comes from getUnallocatedPayments() and already carries
     * unallocated_amount (pre-aggregated) so we avoid a re-query here.
     *
     * @return array{allocations:int, cleared:int, partial:int, returns_updated:int}
     */
    protected function processPayment(object $payment): array
    {
        $results   = ['allocations' => 0, 'cleared' => 0, 'partial' => 0, 'returns_updated' => 0];
        $remaining = (float) ($payment->unallocated_amount
            ?? $this->calculatePaymentBalance($payment->id, (float) $payment->amount));

        // Guard: if already exhausted, force status closed and return
        if ($remaining <= 0) {
            $this->setPaymentStatus($payment->id, 13); // 13 = Fully Allocated
            return $results;
        }

        // Pass 0 — money aimed at one invoice (the payment carries its invoice_id) goes there first, as in the old
        // system. Only untagged money follows the order below: oldest month first, bills before rent.
        if (!empty($payment->invoice_id)) {
            $tagged = $this->getUnsettledInvoices((int) $payment->lease_id)->where('id', (int) $payment->invoice_id);
            [$remaining, $results] = $this->allocateToInvoices($tagged, $payment, $remaining, $results);
            if ($remaining <= 0) return $results;
        }

        // Pass 1 — previous lease: if the tenant transferred units, clear old-lease arrears first
        $previousLease = $this->checkTenantMovement($payment->lease_id);
        if ($previousLease) {
            [$remaining, $results] = $this->allocateToInvoices(
                $this->getUnsettledInvoices($previousLease->lease_id),
                $payment,
                $remaining,
                $results
            );
            if ($remaining <= 0) return $results;
        }

        // Pass 2 — current lease
        [$remaining, $results] = $this->allocateToInvoices(
            $this->getUnsettledInvoices($payment->lease_id),
            $payment,
            $remaining,
            $results
        );
        if ($remaining <= 0) return $results;

        // Pass 3 — linked leases (adjacent units under one tenancy; stub until schema is extended)
        foreach ($this->getLinkedLeases($payment->lease_id) as $linked) {
            [$remaining, $results] = $this->allocateToInvoices(
                $this->getUnsettledInvoices($linked->id),
                $payment,
                $remaining,
                $results
            );
            if ($remaining <= 0) return $results;
        }

        return $results;
    }

    /**
     * 3-B. Walk an invoice list, allocating against each until the payment is exhausted.
     *
     * Carries $remaining forward so allocateInvoice() receives the current balance
     * without issuing a SUM query per invoice.
     *
     * @return array{float, array}
     */
    private function allocateToInvoices(
        Collection $invoices,
        object     $payment,
        float      $remaining,
        array      $results
    ): array {
        foreach ($invoices as $invoice) {
            if ($remaining <= 0) break;

            $alloc = $this->allocateInvoice($invoice, $payment, $remaining);

            if (!$alloc['allocated']) continue;

            $remaining -= $alloc['amount'];
            $results['allocations']++;

            if ($alloc['invoice_status'] === 9)    $results['cleared']++;
            elseif ($alloc['invoice_status'] === 8) $results['partial']++;

            if ($alloc['return_updated']) $results['returns_updated']++;
        }

        return [$remaining, $results];
    }

    /**
     * 3-C. Allocate one payment against one invoice — the core write operation.
     *
     * Three scenarios (from the allocation algorithm):
     *   EXACT_MATCH     — remaining == invoice balance  → both fully closed
     *   PARTIAL_PAYMENT — remaining <  invoice balance  → payment exhausted, invoice partially cleared
     *   EXCESS_PAYMENT  — remaining >  invoice balance  → invoice cleared, payment has remainder
     *
     * Writes in this exact sequence:
     *   (i)    Resolve balances
     *   (ii)   Determine scenario + amounts
     *   (iii)  Insert row into sys_pm_tenants_payments_invoices
     *   (iv)   Update sys_pm_debtors_invoices (balance + status)
     *   (v)    Update sys_pm_tenants_payments (payment status)
     *   (vi)   Credit the tenant ledger (sys_pm_tenants_accounts_trans)
     *   (viii) Update return threshold and landlord ledger
     *   (ix)   Post GL Journal 3 — fatal, rolls the allocation back
     *
     * @param  float|null $precomputedBalance  Caller's running balance — skips a re-query when provided.
     * @return array{allocated:bool, scenario:string|null, amount:float, invoice_status:int|null, return_updated:bool}
     */
    public function allocateInvoice(
        object $invoice,
        object $payment,
        ?float $precomputedBalance = null
    ): array {
        $empty = [
            'allocated'      => false,
            'scenario'       => null,
            'amount'         => 0.0,
            'invoice_status' => null,
            'return_updated' => false,
        ];

        // (i) Resolve current balances fresh from the DB (or use caller's pre-computed value)
        $available      = $precomputedBalance
            ?? $this->calculatePaymentBalance($payment->id, (float) $payment->amount);
        $invoiceBalance = $this->calculateInvoiceBalance($invoice->id, (float) $invoice->total_amount);

        // Guard: nothing to allocate on either side
        if ($available <= 0) {
            $this->setPaymentStatus($payment->id, 13);
            return $empty;
        }
        if ($invoiceBalance <= 0) {
            return $empty; // Already settled by an earlier payment
        }

        // (ii) Determine the allocation scenario and compute all resulting amounts
        $scenario = $this->determineAllocationScenario($available, $invoiceBalance);

        // Recovery payments use a special invoice close code instead of the standard 9=Cleared
        // (34=Paid Vacant, 35=Security Deposit, 36=Absconded, 37=Paid Owner)
        if (!empty($payment->recovery_type_id) && $scenario['invoice_status'] === 9) {
            $scenario['invoice_status'] = (int) $payment->recovery_type_id;
        }

        // (iii) Write the allocation ledger row
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
            // Dimensional context — all sourced from the payment record
            'client_id'          => $payment->client_id          ?? null,
            'tenant_id'          => $payment->tenant_id          ?? null,
            'lease_id'           => $payment->lease_id           ?? null,
            'property_id'        => $payment->property_id        ?? null,
            'unit_id'            => $payment->unit_id            ?? null,
            'sla_id'             => $payment->sla_id             ?? null,
            'zone_id'            => $payment->zone_id            ?? null,
            'field_officer_id'   => $payment->field_officer_id   ?? null,
            'zone_supervisor_id' => $payment->zone_supervisor_id ?? null,
            'zone_manager_id'    => $payment->zone_manager_id    ?? null,
        ]);

        if (!$allocationId) {
            throw new RuntimeException(
                "Allocation row insert failed — payment {$payment->id} / invoice {$invoice->id}"
            );
        }

        // (iv) Decrement invoice balance and update its status
        $this->requireRowsAffected(
            DB::table('sys_pm_debtors_invoices')
                ->where('id', $invoice->id)
                ->update([
                    'current_balance'   => $scenario['new_invoice_balance'],
                    'current_status_id' => $scenario['invoice_status'],
                ]),
            "Invoice {$invoice->id}"
        );

        // (v) Update payment status (12 = Partially Allocated, 13 = Fully Allocated)
        $this->requireRowsAffected(
            DB::table('sys_pm_tenants_payments')
                ->where('id', $payment->id)
                ->update(['current_status_id' => $scenario['payment_status']]),
            "Payment {$payment->id}"
        );

        // (vi) Credit the tenant's ledger for the amount paid against this invoice, as the old system did
        $this->postTenantLedgerCredit($payment, $invoice, $scenario['allocate_amount']);

        // (viii) Recalculate the return threshold and update the landlord ledger
        $returnUpdated = $this->executeThresholdUpdate($invoice->id, $payment->id);

        // (ix) Post GL Journal 3 — commission split + client funds (fatal — failure rolls back the transaction)
        $this->postAllocationGl($payment, $invoice, $scenario['allocate_amount']);

        Log::info('[Allocation] Row written', [
            'payment_id'     => $payment->id,
            'invoice_id'     => $invoice->id,
            'scenario'       => $scenario['scenario'],
            'amount'         => $scenario['allocate_amount'],
            'invoice_status' => $scenario['invoice_status'],
        ]);

        return [
            'allocated'      => true,
            'scenario'       => $scenario['scenario'],
            'amount'         => $scenario['allocate_amount'],
            'invoice_status' => $scenario['invoice_status'],
            'return_updated' => $returnUpdated,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION 4 — POST-ALLOCATION SIDE-EFFECTS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * 4-A. Credit the tenant's ledger with the amount allocated to one invoice.
     * One row per allocation, like the old system: the narration is the invoice's ("RENT FOR July 2026"),
     * pay_for is the item, and the receipt and invoice numbers tie the row to the payment and the invoice.
     * The invoice's own debit row is written when the invoice is raised, so the ledger now nets.
     *
     * @param object $payment the payment row
     * @param object $invoice the invoice row being paid
     * @param float $amount the amount allocated to the invoice
     */
    private function postTenantLedgerCredit(object $payment, object $invoice, float $amount): void
    {
        $item = InvoiceBillingService::itemName($invoice);
        $receipt = trim((string) ($payment->receipt_number ?? ''));

        DB::table('sys_pm_tenants_accounts_trans')->insert([
            'date_time'          => $payment->payment_date ?: now(),
            'narration'          => $invoice->narration ?: ($item . ' FOR ' . Carbon::parse($invoice->date_due ?? now())->format('F Y')),
            'pay_for'            => $item,
            'dr_cr'              => 'CR',
            'debit_amount'       => 0,
            'credit_amount'      => $amount,
            // receipt_number on the ledger is numeric, so a text receipt is left out; entry_ref holds the reference
            'receipt_number'     => ctype_digit($receipt) ? $receipt : null,
            'entry_ref'          => $payment->reference ?? null,
            'entry_by'           => $payment->created_by ?? null,
            'invoice_number'     => $invoice->reference_number ?? null,
            'invoice_id'         => $invoice->id,
            'payment_id'         => $payment->id,
            'zone_id'            => $payment->zone_id ?? null,
            'property_id'        => $payment->property_id ?? null,
            'unit_id'            => $payment->unit_id ?? null,
            'lease_id'           => $payment->lease_id ?? null,
            'client_id'          => $payment->client_id ?? null,
            'tenant_id'          => $payment->tenant_id ?? null,
            'sla_id'             => $payment->sla_id ?? null,
            'field_officer_id'   => $payment->field_officer_id ?? null,
            'zone_supervisor_id' => $payment->zone_supervisor_id ?? null,
            'zone_manager_id'    => $payment->zone_manager_id ?? null,
            'current_status_id'  => 1,
        ]);
    }

    /**
     * 4-B. Post GL Journal 3 — commission + client funds split.
     *
     * Journal 3 (per the allocation docs):
     *   DR  Earned Commission Account   [commission portion]
     *   DR  Client Bank / Trust Account [allocated amount − commission]
     *   CR  Channel Bank Account        [full allocated amount]
     *
     * Commission is read from the SLA linked to the payment and applies to rent invoices only (0 for any other item).
     *
     * FATAL: any exception propagates up to the caller, causing the wrapping DB transaction
     * to roll back. The allocation row, invoice update, and GL entry are all-or-nothing.
     */
    private function postAllocationGl(object $payment, object $invoice, float $allocatedAmount): void
    {
        // Commission is earned on rent only: water, service charge and deposits go to the landlord whole
        $commissionPct = 0.0;
        if (!empty($payment->sla_id) && InvoiceBillingService::isRentInvoice($invoice)) {
            $commissionPct = (float) DB::table('sys_pm_service_agreements')
                ->where('id', $payment->sla_id)
                ->value('commission_percentage');
        }

        $commissionAmount = round($allocatedAmount * $commissionPct / 100, 2);

        // Delegate the journal construction to GlPostingTrait via the 'payment_allocation' type
        $this->postGlEntry([
            'type'              => 'payment_allocation',
            'amount'            => $allocatedAmount,
            'commission_amount' => $commissionAmount,
            'payment_method'    => $payment->payment_method ?? null,
            'date'              => $payment->payment_date
                ? Carbon::parse($payment->payment_date)->toDateString()
                : now()->toDateString(),
            'reference'         => $payment->reference   ?? null,
            'entered_by'        => $payment->created_by  ?? null,
            'trans_type_id'     => 10, // Tenant Payment Allocation
            'trans_details'     => "{$invoice->id}:{$payment->id}:" . ($payment->reference ?? ''),
            'narration'         => 'Allocation — ' . ($invoice->reference_number ?? "Invoice #{$invoice->id}"),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION 5 — GUARDS / UTILITIES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * 5-A. Force a payment to a specific status.
     * Used to close payments that are already exhausted before any allocation loop begins.
     */
    private function setPaymentStatus(int $paymentId, int $statusId): void
    {
        $this->requireRowsAffected(
            DB::table('sys_pm_tenants_payments')
                ->where('id', $paymentId)
                ->update(['current_status_id' => $statusId]),
            "Payment {$paymentId} status update"
        );
    }

    /**
     * 5-B. Assert that an UPDATE touched at least one row.
     * Throws RuntimeException on zero-row updates to catch silent mismatches
     * (e.g. record deleted between the read and the write).
     */
    private function requireRowsAffected(int $affected, string $label): void
    {
        if ($affected === 0) {
            throw new RuntimeException("{$label} — UPDATE affected 0 rows. Record may not exist.");
        }
    }
}
