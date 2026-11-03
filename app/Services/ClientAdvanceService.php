<?php

namespace App\Services;

use App\Exceptions\ClientAccountException;
use App\Models\ChartAccount;
use App\Models\Client;
use App\Models\ClientAdvance;
use App\Models\CompanyBankAccount;
use App\Models\GlAccountSetup;
use App\Models\LandlordAccount;
use App\Models\PaymentVoucherHeader;
use App\Models\PaymentVoucherLine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Posts an advance to the client's account and to the GL when it is posted.
 *
 * The client ledger (cb_landlord_account) gets the debit rows that the returns recover later.
 * The GL gets two journals, with the accounts taken from the GL setup: the principal is money
 * the client is owed out through a payment voucher (debit Loans & Advances Receivable, credit
 * Accounts Payable), and the interest and charge are earned at once (debit Loans Interests
 * Receivable, credit Interest Income).
 *
 * Both books are written together. Any failure throws a ClientAccountException, so the
 * caller's database transaction rolls everything back and nothing is half posted.
 */
class ClientAdvanceService
{
    protected GlJournalPoster $poster;

    /**
     * Inject the shared GL journal poster.
     *
     * @param GlJournalPoster $poster
     */
    public function __construct(GlJournalPoster $poster) {
        $this->poster = $poster;
    }

    const ENTRY_CODE = '0009';
    const TRANSACTION_TYPE = '2017';
    const VOUCHER_TYPE = 'client_advance';

    /**
     * Put a posted advance on the client ledger and in the GL.
     *
     * An advance with a repayment schedule gets one ledger row per instalment, tagged with the
     * instalment's month so that month's return recovers it, plus one row for the charge. An
     * advance without a schedule gets one row for the whole total, tagged with the disbursement month.
     *
     * @param ClientAdvance $advance
     * @param int $userId
     */
    public function post(ClientAdvance $advance, int $userId): void {
        if (LandlordAccount::where('advance_id', $advance->id)->exists()) {
            throw new ClientAccountException('Advance ' . $advance->reference_number . ' is already on the client account.');
        }

        $accounts = $this->glAccounts();
        $rows = $this->ledgerRows($advance, $userId, $accounts['receivable_code']);

        $principal = round((float) $advance->amount, 2);
        $total = round((float) $advance->total_amount, 2);
        $ledgerTotal = round(array_sum(array_column($rows, 'debit_amount')), 2);

        if (abs($ledgerTotal - $total) > 0.01) {
            throw new ClientAccountException('Advance ' . $advance->reference_number . ' cannot be posted: its client account rows add up to ' . number_format($ledgerTotal, 2) . ' but the advance total is ' . number_format($total, 2) . '.');
        }

        foreach ($rows as $row) {
            LandlordAccount::create($row);
        }

        $this->postJournals($advance, $principal, round($total - $principal, 2), $accounts, $userId);

        Log::info('ClientAdvanceService: advance posted to the client account and the GL', [
            'advance_id' => $advance->id,
            'reference' => $advance->reference_number,
            'ledger_rows' => count($rows),
            'principal' => $principal,
            'interest_and_charge' => round($total - $principal, 2),
        ]);
    }

    /**
     * Book the change in interest when a posted advance is restructured. The ledger rows are rebuilt by the
     * restructure itself, so this only keeps the GL in step: more interest is receivable and earned,
     * less interest is taken back. An advance posted before the GL leg existed has no journal to adjust, so it is left alone.
     *
     * @param ClientAdvance $advance the advance after the restructure
     * @param float $oldTotal the advance total before the restructure
     * @param int $userId
     */
    public function adjustInterest(ClientAdvance $advance, float $oldTotal, int $userId): void {
        $adjustment = $this->interestAdjustment($oldTotal, (float) $advance->total_amount);

        if (!$adjustment) {
            return;
        }

        $delta = $adjustment['increase'] ? $adjustment['amount'] : -$adjustment['amount'];
        $posted = DB::table('sys_gl_transactions')->where('trans_details', 'advance_id:' . $advance->id . ':principal')->exists();

        if (!$posted) {
            Log::warning('ClientAdvanceService: advance has no GL journal, interest adjustment skipped', ['advance_id' => $advance->id, 'delta' => $delta]);
            return;
        }

        $accounts = $this->glAccounts();
        $increase = $adjustment['increase'];
        $amount = $adjustment['amount'];
        $reference = (string) $advance->reference_number;

        $this->journal($reference . '/ADJ', 'Advance ' . $reference . ' restructured, interest ' . ($increase ? 'increased' : 'reduced') . ' by ' . number_format($amount, 2), 'advance_id:' . $advance->id . ':restructure:' . now()->format('YmdHis'), [
            ['account_id' => $increase ? $accounts['interest_receivable_id'] : $accounts['interest_income_id'], 'type' => 'Debit', 'amount' => $amount],
            ['account_id' => $increase ? $accounts['interest_income_id'] : $accounts['interest_receivable_id'], 'type' => 'Credit', 'amount' => $amount],
        ], $userId);
    }

    /**
     * Create the payment voucher that pays a posted advance's principal to the client. The voucher then goes
     * through cheque printing and approval like any other; approving it posts the bank side (see postPayout).
     * An advance has one live voucher: a new one can only be made after the earlier one was rejected.
     *
     * @param ClientAdvance $advance
     * @param int $sourceAccountId the company bank account the money is paid from
     * @param int $userId
     */
    public function createPayoutVoucher(ClientAdvance $advance, int $sourceAccountId, int $userId): PaymentVoucherHeader {
        $advance = ClientAdvance::whereKey($advance->id)->lockForUpdate()->firstOrFail();
        $reference = (string) $advance->reference_number;

        if ((int) $advance->current_status_id !== 6) {
            throw new ClientAccountException('Advance ' . $reference . ' must be posted before it can be paid out.');
        }

        if (!$this->hasPrincipalJournal($advance)) {
            throw new ClientAccountException('Advance ' . $reference . ' was posted before advances were posted to the GL, so it has no principal journal and cannot be paid out by voucher.');
        }

        $existing = $advance->voucher_id ? PaymentVoucherHeader::find($advance->voucher_id) : null;

        if ($existing && $existing->voucher_status !== 'Rejected') {
            throw new ClientAccountException('Advance ' . $reference . ' already has payment voucher ' . $existing->voucher_number . '.');
        }

        if (!CompanyBankAccount::where('current_status_id', 1)->whereKey($sourceAccountId)->exists()) {
            throw new ClientAccountException('The bank account to pay from was not found or is not active.');
        }

        // Fail now, not at approval, when the bank account has no GL account to post to.
        $this->bankGlAccountId($sourceAccountId);

        $client = Client::withoutGlobalScope('user_clients_properties')->find($advance->client_id);

        if (!$client) {
            throw new ClientAccountException('The client of advance ' . $reference . ' could not be found.');
        }

        $payeeBank = DB::table('clients_bank_details as d')
            ->leftJoin('sys_set_banks as b', 'b.id', '=', 'd.bank_id')
            ->where('d.client_id', $advance->client_id)
            ->orderByDesc('d.is_default')
            ->orderBy('d.id')
            ->first(['d.account_number', 'b.bank_name']);

        $principal = round((float) $advance->amount, 2);
        $sequence = ((int) PaymentVoucherHeader::max('voucher_sequence')) + 1;
        $now = now();
        $number = 'PV' . $now->year . str_pad($now->month, 2, '0', STR_PAD_LEFT) . $sequence;
        $preparedBy = mb_substr((string) (User::find($userId)->name ?? $userId), 0, 50);

        $header = PaymentVoucherHeader::create([
            'voucher_sequence' => $sequence,
            'voucher_number' => $number,
            'year' => $now->year,
            'month' => $now->month,
            'voucher_date' => $now,
            'description' => mb_substr('ADVANCE ' . $reference, 0, 60),
            'vendor_id' => (string) $advance->client_id,
            'vendor_name' => mb_substr((string) $client->names, 0, 60),
            'vendor_type' => 'Landlord',
            'debit_amount' => $principal,
            'credit_amount' => 0,
            'entry_count' => 1,
            'voucher_status' => 'Pending',
            'is_deleted' => 0,
            'prepared_by' => $preparedBy,
            'is_printed' => 0,
        ]);

        PaymentVoucherLine::create([
            'voucher_number' => $number,
            'entry_id' => $header->id,
            'entry_number' => $advance->id,
            'entry_year' => $now->year,
            'entry_month' => $now->month,
            'voucher_type' => self::VOUCHER_TYPE,
            'entry_description' => mb_substr('ADVANCE PAYMENT ' . $reference, 0, 500),
            'payee_name' => mb_substr((string) $client->names, 0, 50),
            'account_bank' => $payeeBank ? mb_substr((string) $payeeBank->bank_name, 0, 45) : null,
            'account_number' => $payeeBank ? mb_substr((string) $payeeBank->account_number, 0, 30) : null,
            'amount' => $principal,
            'dr_cr' => 'DR',
            'is_approved' => 'No',
            'cheque_type' => 'Cheque',
            'source_account_id' => $sourceAccountId,
        ]);

        $advance->update(['voucher_id' => $header->id]);

        return $header;
    }

    /**
     * Post the bank side of an approved advance payout: debit Accounts Payable (the principal the client
     * was owed since the advance was posted) and credit the bank the voucher was paid from. Called inside
     * the voucher approval's transaction, so a failure here undoes the approval too. Posting the same
     * voucher twice does nothing.
     *
     * @param ClientAdvance $advance
     * @param PaymentVoucherHeader $voucher
     * @param int $userId
     */
    public function postPayout(ClientAdvance $advance, PaymentVoucherHeader $voucher, int $userId): void {
        $advance = ClientAdvance::whereKey($advance->id)->lockForUpdate()->firstOrFail();
        $reference = (string) $advance->reference_number;

        if ((int) $advance->voucher_id !== (int) $voucher->id) {
            throw new ClientAccountException('Voucher ' . $voucher->voucher_number . ' is not the current payment voucher of advance ' . $reference . '.');
        }

        if (!$this->hasPrincipalJournal($advance)) {
            throw new ClientAccountException('Advance ' . $reference . ' has no principal journal in the GL, so the payout cannot be posted against it.');
        }

        $details = 'advance_id:' . $advance->id . ':payout:' . $voucher->id;

        if (DB::table('sys_gl_transactions')->where('trans_details', $details)->exists()) {
            return;
        }

        $lines = PaymentVoucherLine::where('entry_id', $voucher->id)->get();
        $amount = round((float) $lines->sum('amount'), 2);

        if (abs($amount - round((float) $advance->amount, 2)) > 0.005) {
            throw new ClientAccountException('Voucher ' . $voucher->voucher_number . ' pays ' . number_format($amount, 2) . ' but advance ' . $reference . ' is for ' . number_format((float) $advance->amount, 2) . '.');
        }

        $accounts = $this->glAccounts();
        $journalLines = [['account_id' => $accounts['payable_id'], 'type' => 'Debit', 'amount' => $amount]];

        foreach ($lines as $line) {
            $journalLines[] = ['account_id' => $this->bankGlAccountId($line->source_account_id ? (int) $line->source_account_id : null), 'type' => 'Credit', 'amount' => round((float) $line->amount, 2)];
        }

        $this->journal($reference . '/PAY', 'Advance ' . $reference . ' paid out, voucher ' . $voucher->voucher_number, $details, $journalLines, $userId);
    }

    /**
     * What a client can still be advanced: 80% of a year of average monthly returns, less what is already
     * outstanding. Never negative; zero means no advance can be given.
     *
     * @param float $averageMonthlyReturns average of the client's RETURNS credits over the last six complete months
     * @param float $outstanding balance of the client's Pending, Approved and Posted advances
     */
    public function availableLimit(float $averageMonthlyReturns, float $outstanding): float {
        return max(0.0, round($averageMonthlyReturns * 12 * 0.8 - $outstanding, 2));
    }

    /**
     * The interest change when a posted advance is restructured, from the advance total before and after.
     * More interest means more receivable and more income; less means the reverse. Null when nothing changed.
     *
     * @param float $oldTotal
     * @param float $newTotal
     * @return array|null ['increase' => bool, 'amount' => float]
     */
    public function interestAdjustment(float $oldTotal, float $newTotal): ?array {
        $delta = round($newTotal - $oldTotal, 2);

        if (abs($delta) < 0.01) {
            return null;
        }

        return ['increase' => $delta > 0, 'amount' => abs($delta)];
    }

    /**
     * Whether the advance's principal was posted to the GL. An advance posted before the GL posting was
     * built has ledger rows but no journal, so there is no payable for a payout to clear.
     *
     * @param ClientAdvance $advance
     */
    public function hasPrincipalJournal(ClientAdvance $advance): bool {
        return DB::table('sys_gl_transactions')->where('trans_details', 'advance_id:' . $advance->id . ':principal')->exists();
    }

    /**
     * Free an advance for a new payment voucher after its voucher was rejected.
     *
     * @param PaymentVoucherHeader $voucher
     */
    public function releaseVoucher(PaymentVoucherHeader $voucher): void {
        $line = PaymentVoucherLine::where('entry_id', $voucher->id)->first();

        if ($line) {
            ClientAdvance::where('id', $line->entry_number)->where('voucher_id', $voucher->id)->update(['voucher_id' => null]);
        }
    }

    /**
     * The GL account the bank side of a payment goes to: the company bank account's own GL account when it
     * has one, otherwise the GL setup's business bank account.
     *
     * @param int|null $sourceAccountId the company bank account the voucher is paid from
     */
    public function bankGlAccountId(?int $sourceAccountId): int {
        return $this->poster->bankGlAccountId($sourceAccountId);
    }

    /**
     * The code of the receivable account advances are debited to, for labelling client ledger rows.
     */
    public function receivableCode(): ?string {
        $id = GlAccountSetup::first()->loans_receivable_account_id ?? null;

        return $id ? ChartAccount::whereKey($id)->value('account_code') : null;
    }

    /**
     * The client ledger rows for an advance.
     *
     * @param ClientAdvance $advance
     * @param int $userId
     * @param string|null $glCode the receivable account code, shown on each row
     */
    protected function ledgerRows(ClientAdvance $advance, int $userId, ?string $glCode): array {
        $reference = (string) $advance->reference_number;

        if (empty($advance->property_id)) {
            throw new ClientAccountException('Advance ' . $reference . ' has no property, so it cannot be posted to the client account.');
        }

        $client = Client::withoutGlobalScope('user_clients_properties')->find($advance->client_id);
        $property = DB::table('sys_pm_properties')->where('id', $advance->property_id)->first(['reference_number']);

        if (!$client || !$property) {
            throw new ClientAccountException('The client or property of advance ' . $reference . ' could not be found.');
        }

        $landlordCode = mb_substr((string) $client->reference_number, 0, 20);
        $common = [
            'landlord_code' => $landlordCode,
            'landlord_name' => mb_substr((string) $client->names, 0, 60),
            'property_code' => mb_substr((string) $property->reference_number, 0, 20),
            'entry_type_code' => self::ENTRY_CODE,
            'entry_description' => 'ADVANCE',
            'entry_date' => now(),
            'credit_amount' => 0,
            'gl_account' => $glCode,
            'vendor_id' => $landlordCode,
            'audit_user' => mb_substr((string) (User::find($userId)->name ?? $userId), 0, 20),
            'is_recovered' => 0,
            'client_id' => $advance->client_id,
            'property_id' => (int) $advance->property_id,
            'advance_id' => $advance->id,
        ];

        $schedule = $advance->schedule()->orderBy('id')->get();
        $rows = [];

        if ($schedule->isNotEmpty() && !empty($advance->repayment_months)) {
            $count = $schedule->count();
            $disbursed = Carbon::parse($advance->disbursement_date)->format('d M Y');

            foreach ($schedule as $index => $entry) {
                $payDate = Carbon::parse($entry->payment_date);
                $instalment = round((float) $entry->principal_amount + (float) $entry->interest, 2);
                $label = 'Advance ' . $reference . ' Instalment ' . ($index + 1) . ' of ' . $count . ($index === 0 ? ' disbursed ' . $disbursed : '');

                $rows[] = array_merge($common, [
                    'year' => $payDate->year,
                    'month' => $payDate->month,
                    'transaction_number' => $reference . '/' . ($index + 1),
                    'entry_particulars' => mb_substr($label, 0, 200),
                    'debit_amount' => $instalment,
                    'total_amount' => $instalment,
                    'schedule_id' => $entry->id,
                ]);
            }

            // The charge is part of what the client owes but is in no instalment, so it gets its own
            // row, recovered with the first instalment. Without it the advance balance never reaches zero.
            $charge = round((float) $advance->charge_amount, 2);

            if ($charge > 0) {
                $first = Carbon::parse($schedule->first()->payment_date);

                $rows[] = array_merge($common, [
                    'year' => $first->year,
                    'month' => $first->month,
                    'transaction_number' => $reference . '/CHG',
                    'entry_particulars' => mb_substr('Advance ' . $reference . ' charge', 0, 200),
                    'debit_amount' => $charge,
                    'total_amount' => $charge,
                    'schedule_id' => null,
                ]);
            }

            return $rows;
        }

        $total = round((float) $advance->total_amount, 2);
        $disbursement = Carbon::parse($advance->disbursement_date);

        $rows[] = array_merge($common, [
            'year' => $disbursement->year,
            'month' => $disbursement->month,
            'transaction_number' => $reference,
            'entry_particulars' => mb_substr('Advance ' . $reference . ' disbursed ' . $disbursement->format('d M Y'), 0, 200),
            'debit_amount' => $total,
            'total_amount' => $total,
            'schedule_id' => null,
        ]);

        return $rows;
    }

    /**
     * Post the advance's GL journals: the principal, and the interest and charge when there are any.
     *
     * @param ClientAdvance $advance
     * @param float $principal
     * @param float $fees interest plus charge
     * @param array $accounts from glAccounts()
     * @param int $userId
     */
    protected function postJournals(ClientAdvance $advance, float $principal, float $fees, array $accounts, int $userId): void {
        $reference = (string) $advance->reference_number;

        $this->journal($reference, 'Advance ' . $reference . ' principal', 'advance_id:' . $advance->id . ':principal', [
            ['account_id' => $accounts['receivable_id'], 'type' => 'Debit', 'amount' => $principal],
            ['account_id' => $accounts['payable_id'], 'type' => 'Credit', 'amount' => $principal],
        ], $userId);

        if ($fees > 0) {
            $this->journal($reference . '/INT', 'Advance ' . $reference . ' interest and charge', 'advance_id:' . $advance->id . ':interest', [
                ['account_id' => $accounts['interest_receivable_id'], 'type' => 'Debit', 'amount' => $fees],
                ['account_id' => $accounts['interest_income_id'], 'type' => 'Credit', 'amount' => $fees],
            ], $userId);
        }
    }

    /**
     * Post one balanced journal. A journal that cannot be posted fails the whole posting.
     *
     * @param string $reference
     * @param string $narration
     * @param string $details stored on the journal header, used to find the journal again
     * @param array $lines each ['account_id' => int, 'type' => 'Debit'|'Credit', 'amount' => float]
     * @param int $userId
     */
    protected function journal(string $reference, string $narration, string $details, array $lines, int $userId): int {
        return $this->poster->journal($reference, $narration, $details, $lines, $userId, self::TRANSACTION_TYPE);
    }

    /**
     * Apply the advance rows a return has recovered: lower each advance's balance (it is cleared only when fully repaid),
     * clear the instalments that were paid, and post one journal that moves the recovered principal and interest off the
     * receivables (debit Clients Account, credit Loans & Advances Receivable and Loans Interests Receivable).
     *
     * @param Collection $rows the cb_landlord_account rows the return recovered (id, advance_id, schedule_id, transaction_number, debit_amount)
     * @param string $returnCode the return that recovered them
     * @param int $userId
     * @return array ['principal' => float, 'interest' => float] what was moved off the receivables
     */
    public function recoverFromReturn(Collection $rows, string $returnCode, int $userId): array {
        $principal = 0.0;
        $interest = 0.0;

        foreach ($rows->whereNotNull('advance_id')->groupBy('advance_id') as $advanceId => $advanceRows) {
            $advance = ClientAdvance::whereKey($advanceId)->lockForUpdate()->first();

            if (!$advance) {
                throw new ClientAccountException('Advance ' . $advanceId . ' recovered by return ' . $returnCode . ' could not be found.');
            }

            $schedules = $advance->schedule()->whereIn('id', $advanceRows->pluck('schedule_id')->filter()->unique())->get()->keyBy('id');
            $recovered = 0.0;

            foreach ($advanceRows as $row) {
                $schedule = $row->schedule_id ? ($schedules[$row->schedule_id] ?? null) : null;
                [$rowPrincipal, $rowInterest] = self::splitRecovery((float) $row->debit_amount, (string) $row->transaction_number, $schedule ? (float) $schedule->principal_amount : null, (float) $advance->amount);

                $principal += $rowPrincipal;
                $interest += $rowInterest;
                $recovered += (float) $row->debit_amount;
            }

            $balance = max(0, round((float) $advance->current_balance - $recovered, 2));
            $advance->current_balance = $balance;
            if ($balance <= 0.0) {
                $advance->current_status_id = 2; // Cleared
            }
            $advance->save();

            if ($schedules->isNotEmpty()) {
                $advance->schedule()->whereIn('id', $schedules->keys())->update(['current_status_id' => 2]);
            }
        }

        $principal = round($principal, 2);
        $interest = round($interest, 2);
        $details = 'return:' . $returnCode . ':advance-recovery';

        if (($principal + $interest) > 0 && !$this->poster->exists($details)) {
            $accounts = $this->recoveryAccounts();
            $lines = [['account_id' => $accounts['clients_id'], 'type' => 'Debit', 'amount' => round($principal + $interest, 2)]];

            if ($principal > 0) {
                $lines[] = ['account_id' => $accounts['receivable_id'], 'type' => 'Credit', 'amount' => $principal];
            }
            if ($interest > 0) {
                $lines[] = ['account_id' => $accounts['interest_receivable_id'], 'type' => 'Credit', 'amount' => $interest];
            }

            $this->journal($returnCode . '/ADV', 'Advances recovered from return ' . $returnCode, $details, $lines, $userId);
        }

        return ['principal' => $principal, 'interest' => $interest];
    }

    /**
     * How much of a recovered advance row is principal and how much is interest and charge. An instalment row holds its
     * schedule's principal, the separate charge row (/CHG) is all charge, and a one-off advance row holds the advance's
     * principal. The rest of each row is interest or charge.
     *
     * @param float $debit the ledger row's debit amount
     * @param string $transactionNumber the ledger row's reference, which ends /CHG on the charge row
     * @param float|null $schedulePrincipal the instalment's principal, or null when the row is not an instalment
     * @param float $advancePrincipal the advance's principal
     * @return array [principal, interest]
     */
    public static function splitRecovery(float $debit, string $transactionNumber, ?float $schedulePrincipal, float $advancePrincipal): array {
        if (str_ends_with($transactionNumber, '/CHG')) {
            return [0.0, round($debit, 2)];
        }

        $principal = round(min($schedulePrincipal ?? $advancePrincipal, $debit), 2);

        return [$principal, round($debit - $principal, 2)];
    }

    /**
     * The GL accounts an advance recovery posts to, from the GL setup. A missing one stops the posting with a message
     * saying which.
     */
    protected function recoveryAccounts(): array {
        $setup = GlAccountSetup::first();
        $needed = [
            'clients' => ['clients_account_id', 'Clients Account'],
            'receivable' => ['loans_receivable_account_id', 'Loans & Advances Receivable'],
            'interest_receivable' => ['interests_receivable_account_id', 'Loans Interests Receivable'],
        ];
        $accounts = [];

        foreach ($needed as $key => [$column, $label]) {
            $id = $setup->{$column} ?? null;

            if (!$id) {
                throw new ClientAccountException('The GL setup is missing the ' . $label . ' account.');
            }

            $accounts[$key . '_id'] = (int) $id;
        }

        return $accounts;
    }

    /**
     * The GL accounts for advances, from the GL setup. A missing one stops the posting with a message
     * saying which, so a journal is never silently skipped.
     */
    protected function glAccounts(): array {
        $setup = GlAccountSetup::first();
        $needed = [
            'receivable' => ['loans_receivable_account_id', 'Loans & Advances Receivable'],
            'interest_receivable' => ['interests_receivable_account_id', 'Loans Interests Receivable'],
            'interest_income' => ['loans_interests_revenue_account_id', 'Loans Interest Income'],
            'payable' => ['payable_control_account_id', 'Accounts Payable'],
        ];
        $accounts = [];

        foreach ($needed as $key => [$column, $label]) {
            $id = $setup->{$column} ?? null;

            if (!$id) {
                throw new ClientAccountException('The GL setup is missing the ' . $label . ' account.');
            }

            $accounts[$key . '_id'] = (int) $id;
        }

        $accounts['receivable_code'] = ChartAccount::whereKey($accounts['receivable_id'])->value('account_code');

        return $accounts;
    }
}
