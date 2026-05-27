<?php

namespace App\Traits;

use App\Models\GlAccountSetup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GlPostingTrait — reusable double-entry GL journal posting.
 *
 * Usage:
 *   use GlPostingTrait;
 *
 *   $this->postGlEntry([
 *       'type'      => 'invoice_payment',
 *       'amount'    => 5000.00,
 *       'date'      => '2026-05-24',
 *       'reference' => 'MPESA/ABC/123',
 *       'narration' => 'Tenant payment for INV-001',
 *       'commission' => ['amount' => 500.00],   // optional
 *       'payment_method' => 'mpesa',            // optional
 *   ]);
 *
 * To add a new transaction type, add a case to resolveGlLines()
 * and a corresponding protected method glLines{TypeName}().
 */
trait GlPostingTrait
{
    // ────────────────────────────────────────────────────────────────────────────
    // PUBLIC ENTRY POINT
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * Post a balanced double-entry GL journal.
     *
     * Recognised $config keys:
     *   type            string       Entry type key — dispatched to glLines{Type}()
     *   amount          float        Primary transaction amount
     *   date            string       Journal date (Y-m-d). Defaults to today.
     *   reference       string       External reference (MPESA code, cheque no., etc.)
     *   narration       string       Human-readable description
     *   entered_by      int          User ID. Defaults to Auth::id().
     *   trans_type_id   int          sys_set_transactions_types.id
     *   trans_details   string       Internal detail string (e.g. "inv_id:pmt_id:ref")
     *   lines           array        Explicit lines — skips auto-resolution if provided.
     *                                Each line: ['account_id', 'type' (Debit|Credit), 'amount', 'narration']
     *   commission      array|null   ['amount' => float, 'account_id' => int|null]
     *   payment_method  string       'mpesa'|'coop'|'equity'|'clients'|'business'
     *   bank_account_id int          Explicit GL bank account ID (overrides payment_method lookup)
     *
     * @return int|null  sys_gl_transactions.id of the created header, or null if skipped.
     */
    public function postGlEntry(array $config): ?int
    {
        $setup = GlAccountSetup::first();

        if (!$setup) {
            Log::warning('GlPostingTrait: no GL account setup — entry skipped.', [
                'config_type' => $config['type'] ?? 'unknown',
            ]);
            return null;
        }

        $lines = $config['lines'] ?? $this->resolveGlLines($config, $setup);

        if (empty($lines)) {
            Log::warning('GlPostingTrait: no lines resolved — entry skipped.', [
                'config_type' => $config['type'] ?? 'unknown',
            ]);
            return null;
        }

        $this->validateGlBalance($lines);

        return DB::transaction(function () use ($config, $setup, $lines) {

            $headerId = DB::table('sys_gl_transactions')->insertGetId([
                'date_time'            => now(),
                'journal_date'         => $config['date'] ?? now()->toDateString(),
                'reference'            => $config['reference'] ?? null,
                'currency_id'          => 1,
                'accounting_period_id' => $setup->active_fiscal_year_id,
                'trans_details'        => $config['trans_details'] ?? null,
                'current_status_id'    => 1,
                'trans_type_id'        => $config['trans_type_id'] ?? null,
                'entered_by'           => $config['entered_by'] ?? Auth::id(),
            ]);

            foreach ($lines as $line) {
                DB::table('sys_gl_transactions_91_repeat')->insert([
                    'parent_id'  => $headerId,
                    'account_id' => $line['account_id'],
                    'entry_type' => $line['type'],   // 'Debit' or 'Credit'
                    'narration'  => $line['narration'] ?? ($config['narration'] ?? null),
                    // Convention from existing data: debits positive, credits negative
                    'amount'     => $line['type'] === 'Debit'
                                    ? abs($line['amount'])
                                    : -abs($line['amount']),
                ]);
            }

            Log::info('GlPostingTrait: journal posted.', [
                'header_id' => $headerId,
                'type'      => $config['type'] ?? 'manual',
                'reference' => $config['reference'] ?? null,
                'lines'     => count($lines),
            ]);

            return $headerId;
        });
    }

    // ────────────────────────────────────────────────────────────────────────────
    // DISPATCHER
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * Route to the correct line-builder based on config['type'].
     * Add a new case here whenever a new transaction type is implemented.
     */
    protected function resolveGlLines(array $config, GlAccountSetup $setup): array
    {
        return match ($config['type'] ?? '') {
            'invoice_payment' => $this->glLinesInvoicePayment($config, $setup),
            // Future entries — uncomment and implement as needed:
            // 'invoice_raised'        => $this->glLinesInvoiceRaised($config, $setup),
            // 'client_return'         => $this->glLinesClientReturn($config, $setup),
            // 'payment_voucher'       => $this->glLinesPaymentVoucher($config, $setup),
            // 'landlord_remittance'   => $this->glLinesLandlordRemittance($config, $setup),
            default => [],
        };
    }

    // ────────────────────────────────────────────────────────────────────────────
    // INVOICE PAYMENT
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * GL lines for a tenant invoice payment allocation.
     *
     * Without commission:
     *   DR  Bank Account         [amount]     — cash received
     *   CR  Tenants Account      [amount]     — clears debtor balance
     *
     * With commission deducted at source by property manager:
     *   DR  Bank Account         [amount - commission]   — net cash remitted
     *   DR  Earned Commission    [commission]            — manager's cut already deducted
     *   CR  Tenants Account      [amount]                — clears full debtor balance
     *
     * Config keys consumed:
     *   amount           float       Allocated payment amount (gross)
     *   payment_method   string      'mpesa'|'coop'|'equity'|'clients'|'business'
     *   bank_account_id  int         Override: explicit GL bank account ID
     *   commission       array|null  ['amount' => float, 'account_id' => int|null]
     *                                account_id defaults to setup.earned_commission_acc_id
     *   narration        string      Base narration (suffixes appended per line)
     */
    protected function glLinesInvoicePayment(array $config, GlAccountSetup $setup): array
    {
        $amount = (float) ($config['amount'] ?? 0);
        if ($amount <= 0) {
            return [];
        }

        $commission       = $config['commission'] ?? null;
        $commissionAmount = (float) ($commission['amount'] ?? 0);
        $commissionAccId  = $commission['account_id'] ?? $setup->earned_commission_acc_id;

        $tenantAccId = $setup->tenants_account_id;
        $bankAccId   = $config['bank_account_id']
                       ?? $this->resolvePaymentBankAccount($config['payment_method'] ?? null, $setup);

        $base = $config['narration'] ?? 'Tenant invoice payment';

        $lines = [];

        if ($commissionAmount > 0 && $commissionAccId) {
            // Net receipt into bank (gross minus commission already deducted)
            $lines[] = [
                'account_id' => $bankAccId,
                'type'       => 'Debit',
                'amount'     => $amount - $commissionAmount,
                'narration'  => $base . ' — net bank receipt',
            ];
            // Commission deducted at source
            $lines[] = [
                'account_id' => $commissionAccId,
                'type'       => 'Debit',
                'amount'     => $commissionAmount,
                'narration'  => $base . ' — commission deduction',
            ];
        } else {
            // Full amount into bank
            $lines[] = [
                'account_id' => $bankAccId,
                'type'       => 'Debit',
                'amount'     => $amount,
                'narration'  => $base . ' — bank receipt',
            ];
        }

        // Credit tenants account for the full gross amount
        $lines[] = [
            'account_id' => $tenantAccId,
            'type'       => 'Credit',
            'amount'     => $amount,
            'narration'  => $base . ' — tenant account cleared',
        ];

        return $lines;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // SHARED HELPERS
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * Resolve the GL bank account ID from a payment method string.
     * Extend the match arms as new channels are mapped.
     */
    protected function resolvePaymentBankAccount(?string $method, GlAccountSetup $setup): ?int
    {
        return match (strtolower(trim($method ?? ''))) {
            'mpesa'          => $setup->mpesa_account_id,
            'coop', 'co-op'  => $setup->co_op_bank_id,
            'equity'         => $setup->equity_bank_account_id,
            'clients'        => $setup->clients_bank_account_id,
            default          => $setup->business_bank_account_id,
        };
    }

    /**
     * Warn (not throw) if debits and credits do not balance.
     * A balanced journal must have: sum(DR) == sum(CR).
     */
    protected function validateGlBalance(array $lines): void
    {
        $dr = array_sum(array_column(
            array_filter($lines, fn($l) => $l['type'] === 'Debit'),
            'amount'
        ));
        $cr = array_sum(array_column(
            array_filter($lines, fn($l) => $l['type'] === 'Credit'),
            'amount'
        ));

        if (abs($dr - $cr) > 0.005) {
            Log::warning('GlPostingTrait: unbalanced journal.', [
                'debits'  => round($dr, 4),
                'credits' => round($cr, 4),
                'diff'    => round(abs($dr - $cr), 4),
            ]);
        }
    }
}
