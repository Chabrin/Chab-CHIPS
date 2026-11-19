<?php

namespace App\Services;

use App\Models\DebtorInvoice;
use App\Models\Deposit;
use App\Models\LeaseAgreement;
use App\Models\TenantAccountTransaction;
use App\Models\User;
use App\Traits\GlPostingTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantReviewInvoiceService
{
    use GlPostingTrait;

    public function rejectInvoices(LeaseAgreement $lease, User $user): bool
    {
        $accountsPending = Schema::hasTable('sys_pm_tenants_payments')
            && DB::table('sys_pm_tenants_payments')->where('tenant_id', $lease->tenant_id)->where('amount', '>', 0)->exists();
        $invoices = DebtorInvoice::where('lease_id', $lease->id)->orderBy('id')->lockForUpdate()->get();
        foreach ($invoices as $invoice) {
            if ((int) $invoice->current_status_id === 3) continue;
            $hasMoney = $invoice->paymentAllocations()->exists()
                || TenantAccountTransaction::where('invoice_id', $invoice->id)->where('credit_amount', '>', 0)->exists()
                || round((float) $invoice->current_balance, 2) !== round((float) $invoice->total_amount, 2);
            $batchPosted = Schema::hasTable('sys_gl_transactions')
                && DB::table('sys_gl_transactions')->where('trans_details', 'batch:'.$invoice->batch_number.':property:'.$invoice->property_id)->exists();
            if ($hasMoney || $batchPosted) {
                $accountsPending = true;
                continue;
            }
            $debits = TenantAccountTransaction::where('invoice_id', $invoice->id)->where('dr_cr', 'DR')->get();
            foreach ($debits as $debit) {
                $credit = $debit->replicate();
                $credit->fill(['date_time' => now(), 'narration' => 'Onboarding rejected - reverse '.$invoice->reference_number,
                    'dr_cr' => 'CR', 'debit_amount' => 0, 'credit_amount' => $debit->debit_amount,
                    'entry_by' => $user->id, 'entry_ref' => 'tenant-review-reversal:'.$invoice->id.':'.$debit->id]);
                $credit->save();
            }
            $this->reverseJournal($invoice, $user);
            $invoice->update(['current_status_id' => 3, 'current_balance' => 0]);
            Deposit::where('invoice_id', $invoice->id)->update(['current_status_id' => 3, 'invoice_balance' => 0]);
        }
        return $accountsPending;
    }

    private function reverseJournal(DebtorInvoice $invoice, User $user): void
    {
        if (!Schema::hasTable('sys_gl_transactions')) return;
        $original = DB::table('sys_gl_transactions')->where('trans_details', 'invoice_id:'.$invoice->id.':invoice')->first();
        if (!$original) return;
        $tag = 'tenant-review-reversal:invoice:'.$invoice->id;
        if (DB::table('sys_gl_transactions')->where('trans_details', $tag)->exists()) return;
        $lines = DB::table('sys_gl_transactions_91_repeat')->where('parent_id', $original->id)->get()
            ->map(fn ($line) => ['account_id' => $line->account_id,
                'type' => $line->entry_type === 'Debit' ? 'Credit' : 'Debit',
                'amount' => abs((float) $line->amount)])->all();
        $net = array_sum(array_map(fn ($line) => $line['type'] === 'Debit' ? $line['amount'] : -$line['amount'], $lines));
        if (!$lines || abs($net) > 0.005) {
            throw new \RuntimeException('The original invoice journal needs Accounts reconciliation before reversal.');
        }
        $id = $this->postGlEntry(['type' => 'generic', 'lines' => $lines,
            'date' => now()->toDateString(), 'reference' => $invoice->reference_number,
            'narration' => 'Rejected tenant onboarding invoice reversal', 'trans_details' => $tag,
            'trans_type_id' => InvoiceJournalService::TRANSACTION_TYPE_ID, 'entered_by' => $user->id]);
        if (!$id) throw new \RuntimeException('The invoice reversal could not be posted.');
    }
}
