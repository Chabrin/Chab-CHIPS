<?php

namespace App\Services;

use App\Exceptions\ClientAccountException;
use App\Models\DebtorInvoice;
use App\Models\GlAccountSetup;
use App\Traits\GlPostingTrait;
use Illuminate\Support\Facades\DB;

/**
 * Posts a tenant invoice to the GL: the tenant owes the amount (debit Tenants Account), the landlord is credited
 * what is left after commission (credit Clients Account) and the commission is credited to Rent Commission, the
 * shape of the existing invoice journals. A deposit has no commission, so it is a two-line journal.
 *
 * Posting is switched by config('accounting.post_invoice_journals') and is off until the accountant has agreed the
 * timing. When it is on, the journal is written in the same database transaction as the invoice and its ledger row,
 * so a missing GL account refuses the invoice with a message naming the account and nothing is half posted.
 */
class InvoiceJournalService
{
    use GlPostingTrait;

    const TRANSACTION_TYPE_ID = 6; // Tenant Invoice

    /**
     * Whether invoices are posted to the GL.
     */
    public function enabled(): bool {
        return (bool) config('accounting.post_invoice_journals', false);
    }

    /**
     * Post one invoice. Does nothing when posting is off, and posting the same invoice twice returns the first journal.
     *
     * @param DebtorInvoice $invoice
     * @param string|null $depositHeldBy Company when the invoice is a deposit the company keeps, otherwise null
     * @return int|null the journal header id, or null when posting is off
     */
    public function postInvoice(DebtorInvoice $invoice, ?string $depositHeldBy = null): ?int {
        // A zero-amount invoice (rent 0 with a deposit entered) has nothing to post.
        if (!$this->enabled() || (float) $invoice->total_amount <= 0) {
            return null;
        }

        $details = 'invoice_id:' . $invoice->id . ':invoice';
        $existing = DB::table('sys_gl_transactions')->where('trans_details', $details)->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $commission = round((float) $invoice->comm_ammount, 2);
        $accounts = $this->accounts($commission > 0, $depositHeldBy === 'Company');

        $headerId = $this->postGlEntry([
            'type' => 'generic',
            'lines' => InvoiceBillingService::invoiceJournalLines((float) $invoice->total_amount, $commission, $accounts['receivable'], $accounts['clients'], $accounts['commission'] ?? 0, $accounts['deposit_payable'] ?? null),
            'date' => date('Y-m-d', strtotime((string) $invoice->date_time)),
            'reference' => mb_substr((string) $invoice->reference_number, 0, 250),
            'narration' => mb_substr('Tenant Invoice - ' . $invoice->narration, 0, 250),
            'trans_details' => $details,
            'trans_type_id' => self::TRANSACTION_TYPE_ID,
            'entered_by' => $invoice->created_by,
        ]);

        if (!$headerId) {
            throw new ClientAccountException('The GL journal for invoice ' . $invoice->reference_number . ' could not be posted.');
        }

        return $headerId;
    }

    /**
     * Post one journal per property for every invoice of a bulk run, in three set-based statements, so a run of
     * about 41,000 invoices does not mean 41,000 journals. Called inside the run's transaction.
     *
     * @param string $batchNumber the bulk run's batch number
     * @param int $userId
     * @return int the number of journals posted, 0 when posting is off or the batch is already posted
     */
    public function postBatch(string $batchNumber, int $userId): int {
        if (!$this->enabled()) {
            return 0;
        }

        $tag = 'batch:' . $batchNumber . ':property:';

        // Posting the same batch twice would double every journal.
        if (DB::table('sys_gl_transactions')->where('trans_details', 'like', $tag . '%')->exists()) {
            return 0;
        }

        // The commission account is only needed when some invoice of the run carries commission.
        $hasCommission = DB::table('sys_pm_debtors_invoices')->where('batch_number', $batchNumber)->where('comm_ammount', '>', 0)->exists();
        $accounts = $this->accounts($hasCommission, false);
        $period = GlAccountSetup::first()->active_fiscal_year_id;

        $posted = DB::affectingStatement("
            INSERT INTO sys_gl_transactions (date_time, journal_date, reference, currency_id, accounting_period_id, trans_details, current_status_id, trans_type_id, entered_by)
            SELECT NOW(), MIN(inv.date_time)::date, 'INV' || ?::text || '-P' || inv.property_id, 1, ?, ?::text || inv.property_id, 1, ?, ?
            FROM sys_pm_debtors_invoices inv
            WHERE inv.batch_number = ?
            GROUP BY inv.property_id
        ", [$batchNumber, $period, $tag, self::TRANSACTION_TYPE_ID, $userId, $batchNumber]);

        $lineSql = "
            WITH agg AS (
                SELECT property_id, SUM(total_amount) AS total, COALESCE(SUM(comm_ammount), 0) AS comm
                FROM sys_pm_debtors_invoices
                WHERE batch_number = ?
                GROUP BY property_id
            )
            INSERT INTO sys_gl_transactions_91_repeat (parent_id, account_id, entry_type, narration, amount)
            SELECT h.id, ?::text, ?, ?, %s
            FROM agg
            JOIN sys_gl_transactions h ON h.trans_details = ?::text || agg.property_id
            %s
        ";
        $narration = 'Tenant invoices, batch ' . $batchNumber;

        DB::insert(sprintf($lineSql, 'ROUND(agg.total, 2)', ''), [$batchNumber, $accounts['receivable'], 'Debit', $narration, $tag]);
        DB::insert(sprintf($lineSql, '-ROUND(agg.total - agg.comm, 2)', ''), [$batchNumber, $accounts['clients'], 'Credit', $narration, $tag]);

        if ($hasCommission) {
            DB::insert(sprintf($lineSql, '-ROUND(agg.comm, 2)', 'WHERE agg.comm > 0'), [$batchNumber, $accounts['commission'], 'Credit', $narration, $tag]);
        }

        // The set-based path bypasses the usual balance check, so check the whole run here and refuse an unbalanced one.
        $net = DB::table('sys_gl_transactions_91_repeat as l')->join('sys_gl_transactions as h', 'h.id', '=', 'l.parent_id')->where('h.trans_details', 'like', $tag . '%')->sum('l.amount');

        if (abs((float) $net) > 0.005) {
            throw new ClientAccountException('The GL journals for batch ' . $batchNumber . ' do not balance (' . round((float) $net, 2) . '), so the run was refused.');
        }

        return $posted;
    }

    /**
     * The GL accounts for tenant invoices, from the GL setup. A missing one stops the posting with a message naming it.
     *
     * @param bool $needCommission whether the Rent Commission account is needed
     * @param bool $needDepositPayable whether the Tenants Deposits Payable account is needed
     */
    protected function accounts(bool $needCommission, bool $needDepositPayable): array {
        $setup = GlAccountSetup::first();
        $needed = ['receivable' => ['tenants_account_id', 'Tenants Account'], 'clients' => ['clients_account_id', 'Clients Account']];

        if ($needCommission) {
            $needed['commission'] = ['rent_commission_account_id', 'Rent Commission'];
        }

        if ($needDepositPayable) {
            $needed['deposit_payable'] = ['deposits_payable_account_id', 'Tenants Deposits Payable'];
        }

        $accounts = [];

        foreach ($needed as $key => [$column, $label]) {
            $id = $setup->{$column} ?? null;

            if (!$id) {
                throw new ClientAccountException('The GL setup is missing the ' . $label . ' account, so the invoice cannot be posted.');
            }

            $accounts[$key] = (int) $id;
        }

        return $accounts;
    }
}
