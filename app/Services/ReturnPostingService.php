<?php

namespace App\Services;

use App\Exceptions\ClientAccountException;
use App\Models\Client;
use App\Models\GlAccountSetup;
use App\Models\LandlordAccount;
use App\Models\PaymentVoucherHeader;
use App\Models\PaymentVoucherLine;
use App\Models\ReturnSummary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The accounting of a client return, in the client's ledger (cb_landlord_account) and the GL together.
 *
 * Final approval of a return (postApproval): the ledger gets a RETURNS credit (0005) of what the return owes the client
 * before the deductions, the debits the return proposed (advances, registration fee) are flagged recovered against it,
 * advances are repaid and their receivables cleared in the GL, and the VAT on commission is taken off the client's account.
 * The debits themselves are already on the ledger, so credit less those debits is what is paid out.
 *
 * Approval of the payment voucher (postPayout): the ledger gets a FINAL PAYMENTS debit (0006) and the GL moves the amount
 * from the Clients Account through Accounts Payable to the bank. The ledger then returns to nil.
 *
 * Both write the ledger and the GL in the caller's database transaction. Any failure throws a ClientAccountException, so
 * nothing is half posted.
 */
class ReturnPostingService
{
    const RETURNS_CODE = '0005';
    const PAYMENT_CODE = '0006';
    const TRANSACTION_TYPE = '2009';
    const SOURCE_LEDGER = 'LANDLORD ACCOUNT';

    protected GlJournalPoster $poster;
    protected ClientAdvanceService $advances;

    /**
     * Inject the shared GL poster and the advance service.
     *
     * @param GlJournalPoster $poster
     * @param ClientAdvanceService $advances
     */
    public function __construct(GlJournalPoster $poster, ClientAdvanceService $advances) {
        $this->poster = $poster;
        $this->advances = $advances;
    }

    /**
     * The figures of a return, from its line totals.
     *
     * gross is what the return owes the client after commission and VAT, before any deduction. ledger_credit is the
     * RETURNS credit to post: gross less the deductions that have no debit on the ledger (money recoveries). The debits
     * on the ledger (ledgerDeductions) are already there, so net, the amount paid out, is gross less all deductions.
     *
     * @param float $credits total of the credit lines (rent, bills passed on)
     * @param float $commission commission on the rent lines
     * @param float $vat VAT on that commission
     * @param float $ledgerDeductions deductions that are debits already on the client ledger (advances, fee)
     * @param float $otherDeductions deductions with no ledger debit (money recoveries)
     * @return array ['gross' => float, 'net' => float, 'ledger_credit' => float]
     */
    public static function settle(float $credits, float $commission, float $vat, float $ledgerDeductions, float $otherDeductions): array {
        $gross = round($credits - $commission - $vat, 2);

        return [
            'gross' => $gross,
            'net' => round($gross - $ledgerDeductions - $otherDeductions, 2),
            'ledger_credit' => round($gross - $otherDeductions, 2),
        ];
    }

    /**
     * Post a return that has just had its final approval.
     *
     * @param ReturnSummary $return the approved return summary
     * @param int $userId the user who gave the final approval
     */
    public function postApproval(ReturnSummary $return, int $userId): void {
        $code = (string) $return->return_code;

        if (DB::table('cb_landlord_account')->where('entry_type_code', self::RETURNS_CODE)->where('return_code', $code)->exists()) {
            return;
        }

        $totals = $this->lineTotals($code);
        $figures = self::settle($totals['credits'], $totals['commission'], $totals['vat'], $totals['ledger_deductions'], $totals['other_deductions']);

        if (abs($figures['net'] - round((float) $return->net_balance, 2)) > 0.01) {
            throw new ClientAccountException('Return ' . $code . ' no longer adds up: its lines give ' . number_format($figures['net'], 2) . ' but the return says ' . number_format((float) $return->net_balance, 2) . '. Generate the returns again.');
        }

        $client = $this->client($return);

        if ($figures['ledger_credit'] > 0) {
            $this->postCredit($return, $client, $figures['ledger_credit'], $userId);
        }

        $recovered = $this->recoverReservedRows($return, $totals['ledger_deductions']);
        $this->advances->recoverFromReturn($recovered, $code, $userId);

        if (config('accounting.post_return_vat_journals') && $totals['vat'] > 0) {
            $this->postVat($return, $totals['vat'], $userId);
        }
    }

    /**
     * Finalise a merged return (MASTERRETURN) once every property return in it has had its final approval. Each property
     * return keeps its own approval and its own RETURNS credit, so the merged return posts no credit of its own (that would
     * credit the client twice). What it settles is the landlord-level debits reserved for it at generation: they are
     * flagged recovered here, and advances paid by them are updated. The merged return is then payable by one voucher.
     * Does nothing when the return is not part of a merged return or a property return is still waiting.
     *
     * @param ReturnSummary $child the property return that has just had its final approval
     * @param int $userId the approver
     * @return bool whether the merged return was finalised
     */
    public function finaliseMasterIfReady(ReturnSummary $child, int $userId): bool {
        $masterCode = (string) ($child->parent_return_code ?? '');

        if ($masterCode === '') {
            return false;
        }

        $master = ReturnSummary::where('return_code', $masterCode)->where('return_type', 'MASTERRETURN')->lockForUpdate()->first();

        if (!$master || (int) $master->approved_status === 1) {
            return false;
        }

        $children = ReturnSummary::where('parent_return_code', $masterCode)->whereIn('return_type', ['MAINRETURN', 'UTILITYRETURN'])->get();

        if ($children->isEmpty() || $children->contains(fn ($c) => (int) $c->approved_status !== 1)) {
            return false;
        }

        $totals = $this->lineTotals($masterCode);
        $childNets = round((float) $children->sum('net_amount'), 2);
        $expectedNet = round($childNets - $totals['ledger_deductions'], 2);

        if (abs($totals['credits'] - $childNets) > 0.01 || abs($expectedNet - round((float) $master->net_balance, 2)) > 0.01) {
            throw new ClientAccountException('Merged return ' . $masterCode . ' no longer adds up: its property returns give ' . number_format($childNets, 2) . ' less ' . number_format($totals['ledger_deductions'], 2) . ' of deductions, but it says ' . number_format((float) $master->net_balance, 2) . '. Generate the returns again.');
        }

        $recovered = $this->recoverReservedRows($master, $totals['ledger_deductions']);
        $this->advances->recoverFromReturn($recovered, $masterCode, $userId);

        ReturnSummary::where('id', $master->id)->update([
            'approved_status' => 1,
            'approved_by' => 'System (all properties approved)',
            'approved_date' => now(),
        ]);

        return true;
    }

    /**
     * Post the payout of an approved return when its payment voucher gets its final approval.
     *
     * @param ReturnSummary $return the return the voucher pays
     * @param PaymentVoucherHeader $voucher the approved payment voucher
     * @param int $userId the approver
     */
    public function postPayout(ReturnSummary $return, PaymentVoucherHeader $voucher, int $userId): void {
        // Also protects pre-existing pending vouchers if the property was placed in a merge batch afterwards.
        $return = ReturnSummary::whereKey($return->id)->lockForUpdate()->firstOrFail();
        if (!ReturnSummary::whereKey($return->id)->eligibleForVoucher()->exists()) {
            throw new ClientAccountException('This property return must be paid through its merged return.');
        }
        $code = (string) $return->return_code;

        if ((int) $return->approved_status !== 1) {
            throw new ClientAccountException('Return ' . $code . ' is not approved, so voucher ' . $voucher->voucher_number . ' cannot be paid.');
        }

        // A merged return carries no credit of its own: each property return in it holds its credit
        $isMaster = $return->return_type === 'MASTERRETURN';
        $creditCodes = $isMaster ? ReturnSummary::where('parent_return_code', $code)->pluck('return_code')->all() : [$code];

        $withCredit = DB::table('cb_landlord_account')->where('entry_type_code', self::RETURNS_CODE)->whereIn('return_code', $creditCodes)->distinct()->count('return_code');

        if (empty($creditCodes) || $withCredit < count($creditCodes)) {
            throw new ClientAccountException('Return ' . $code . ' has no RETURNS credit on the client account: it was approved before returns posted at approval. Generate and approve it again.');
        }

        $details = 'return:' . $return->id . ':payout:' . $voucher->id;

        if ($this->poster->exists($details)) {
            return;
        }

        $lines = PaymentVoucherLine::where('entry_id', $voucher->id)->get();
        $amount = round((float) $lines->sum('amount'), 2);

        if (abs($amount - round((float) $return->net_balance, 2)) > 0.005) {
            throw new ClientAccountException('Voucher ' . $voucher->voucher_number . ' pays ' . number_format($amount, 2) . ' but return ' . $code . ' is for ' . number_format((float) $return->net_balance, 2) . '.');
        }

        $accounts = $this->accounts(['clients_account_id' => 'Clients Account', 'payable_control_account_id' => 'Accounts Payable']);
        $journalLines = [
            ['account_id' => $accounts['clients_account_id'], 'type' => 'Debit', 'amount' => $amount],
            ['account_id' => $accounts['payable_control_account_id'], 'type' => 'Credit', 'amount' => $amount],
            ['account_id' => $accounts['payable_control_account_id'], 'type' => 'Debit', 'amount' => $amount],
        ];

        foreach ($lines as $line) {
            $journalLines[] = ['account_id' => $this->poster->bankGlAccountId($line->source_account_id ? (int) $line->source_account_id : null), 'type' => 'Credit', 'amount' => round((float) $line->amount, 2)];
        }

        $this->poster->journal($code . '/PAY', 'Return ' . $code . ' paid out, voucher ' . $voucher->voucher_number, $details, $journalLines, $userId, self::TRANSACTION_TYPE);

        $this->postPaymentRow($return, $voucher, $lines->first(), $amount, $userId);

        ReturnSummary::where('id', $return->id)->update(['paid_status' => 1, 'amount_paid' => $amount]);

        // The property returns of a merged return are paid with it, as in the old system: each is flagged paid for its own net
        if ($isMaster) {
            ReturnSummary::where('parent_return_code', $code)->where('paid_status', 0)->update(['paid_status' => 1, 'amount_paid' => DB::raw('net_balance')]);
        }

        DB::table('sys_pm_debtors_invoices')
            ->whereIn('id', DB::table('cb_returns_clients')->whereIn('return_code', array_merge([$code], $creditCodes))->whereNotNull('invoice_id')->select('invoice_id'))
            ->update(['processed' => 'Yes']);
    }

    /**
     * Free a return for a new payment voucher after its voucher was rejected.
     *
     * @param PaymentVoucherHeader $voucher the rejected voucher
     */
    public function releaseVoucher(PaymentVoucherHeader $voucher): void {
        $line = PaymentVoucherLine::where('entry_id', $voucher->id)->where('voucher_type', 'client_returns')->first();

        if ($line) {
            ReturnSummary::where('id', $line->entry_number)->where('paid_status', 0)->update(['voucher_generated' => 0]);
        }
    }

    /**
     * Add up a return's lines. The Commission debit line only repeats the commission and VAT already on the rent lines, so
     * it is left out.
     *
     * @param string $returnCode
     */
    protected function lineTotals(string $returnCode): array {
        $row = DB::selectOne("
            SELECT
                COALESCE(SUM(CASE WHEN dr_cr = 'CR' THEN net_amount END), 0) AS credits,
                COALESCE(SUM(CASE WHEN COALESCE(entry_type, '') <> 'COMMISSION' THEN commission_amount END), 0) AS commission,
                COALESCE(SUM(CASE WHEN COALESCE(entry_type, '') <> 'COMMISSION' THEN vat_amount END), 0) AS vat,
                COALESCE(SUM(CASE WHEN dr_cr = 'DR' AND COALESCE(entry_type, '') <> 'COMMISSION' AND transaction_source = ? THEN net_amount END), 0) AS ledger_deductions,
                COALESCE(SUM(CASE WHEN dr_cr = 'DR' AND COALESCE(entry_type, '') <> 'COMMISSION' AND COALESCE(transaction_source, '') <> ? THEN net_amount END), 0) AS other_deductions
            FROM cb_returns_clients
            WHERE return_code = ?
        ", [self::SOURCE_LEDGER, self::SOURCE_LEDGER, $returnCode]);

        return [
            'credits' => round((float) $row->credits, 2),
            'commission' => round((float) $row->commission, 2),
            'vat' => round((float) $row->vat, 2),
            'ledger_deductions' => round((float) $row->ledger_deductions, 2),
            'other_deductions' => round((float) $row->other_deductions, 2),
        ];
    }

    /**
     * The client the return belongs to, found without the user's zone restrictions: an approver does not have to be able
     * to see the client elsewhere in the system.
     *
     * @param ReturnSummary $return
     */
    protected function client(ReturnSummary $return): Client {
        $client = Client::withoutGlobalScope('user_clients_properties')->where('reference_number', $return->landlord_code)->first();

        if (!$client) {
            throw new ClientAccountException('Client ' . $return->landlord_code . ' of return ' . $return->return_code . ' could not be found.');
        }

        return $client;
    }

    /**
     * Post the RETURNS credit to the client's ledger.
     *
     * @param ReturnSummary $return
     * @param Client $client
     * @param float $amount
     * @param int $userId
     */
    protected function postCredit(ReturnSummary $return, Client $client, float $amount, int $userId): void {
        $property = DB::table('sys_pm_properties')->where('reference_number', $return->property_code)->first(['id', 'property_name']);
        $monthName = date('F', mktime(0, 0, 0, (int) $return->month, 1));
        $particulars = 'RETURNS-' . ($property->property_name ?? $return->property_code) . ' ' . $monthName . ' ' . $return->year;

        LandlordAccount::create($this->ledgerRow($return, $client, $property, $userId, [
            'entry_type_code' => self::RETURNS_CODE,
            'entry_description' => 'RETURNS',
            'entry_particulars' => mb_substr($particulars, 0, 100),
            'debit_amount' => 0.00,
            'credit_amount' => $amount,
            'total_amount' => $amount,
            'payment_ref' => 'RETURN',
            'payment_ref_type' => 'RETURN',
            'gl_account' => 'EXPENSE',
            'pv_number' => null,
            'return_code' => $return->return_code,
        ]));
    }

    /**
     * Post the FINAL PAYMENTS debit to the client's ledger, linked to the voucher by its number as the legacy rows are.
     *
     * @param ReturnSummary $return
     * @param PaymentVoucherHeader $voucher
     * @param PaymentVoucherLine|null $firstLine the voucher's first line, for the cheque and bank
     * @param float $amount
     * @param int $userId
     */
    protected function postPaymentRow(ReturnSummary $return, PaymentVoucherHeader $voucher, ?PaymentVoucherLine $firstLine, float $amount, int $userId): void {
        $client = $this->client($return);
        $property = DB::table('sys_pm_properties')->where('reference_number', $return->property_code)->first(['id', 'property_name']);
        $monthName = date('F', mktime(0, 0, 0, (int) $return->month, 1));
        $label = $property->property_name ?? ($return->property_code ?: 'MERGED RETURN');

        LandlordAccount::create($this->ledgerRow($return, $client, $property, $userId, [
            'entry_type_code' => self::PAYMENT_CODE,
            'entry_description' => 'FINAL PAYMENTS',
            'entry_particulars' => mb_substr('FINAL PAYMENTS-' . $label . ' ' . $monthName . ' ' . $return->year, 0, 100),
            'debit_amount' => $amount,
            'credit_amount' => 0.00,
            'total_amount' => $amount,
            'payment_ref' => $firstLine ? mb_substr((string) $firstLine->cheque_number, 0, 20) : null,
            'payment_ref_type' => $firstLine ? mb_substr((string) $firstLine->source_bank, 0, 20) : null,
            'gl_account' => null,
            'pv_number' => $voucher->voucher_number,
            'return_code' => null,
        ]));
    }

    /**
     * The common columns of a client ledger row written by a return.
     *
     * @param ReturnSummary $return
     * @param Client $client
     * @param object|null $property the property's id and name
     * @param int $userId
     * @param array $row the columns that differ between rows
     */
    protected function ledgerRow(ReturnSummary $return, Client $client, ?object $property, int $userId, array $row): array {
        $maxId = DB::table('cb_landlord_account')->max('id') ?? 0;

        return array_merge([
            'year' => $return->year,
            'month' => $return->month,
            'landlord_code' => $return->landlord_code,
            'landlord_name' => mb_substr((string) $client->names, 0, 50),
            'property_code' => $return->property_code,
            'transaction_number' => 'A' . ($maxId + 1),
            'entry_sequence' => null,
            'entry_date' => now(),
            'account_status' => 0,
            'interest_rate' => 0.00,
            'audit_user' => mb_substr((string) (User::find($userId)->name ?? $userId), 0, 20),
            'vendor_id' => null,
            'is_recovered' => 0,
            'recovery_year' => null,
            'recovery_month' => null,
            'return_transaction' => null,
            'client_id' => $client->id,
            'property_id' => $property->id ?? null,
        ], $row);
    }

    /**
     * Flag the debits this return reserved at generation as recovered, now that it is approved. What is flagged must be
     * exactly what the return's deduction lines say, or the approval stops.
     *
     * @param ReturnSummary $return
     * @param float $expected total of the return's deduction lines that are ledger debits
     * @return \Illuminate\Support\Collection the rows that were flagged
     */
    protected function recoverReservedRows(ReturnSummary $return, float $expected) {
        $rows = DB::table('cb_landlord_account')
            ->where('return_code', $return->return_code)
            ->where('debit_amount', '>', 0)
            ->whereRaw('COALESCE(is_recovered, 0) != 1')
            ->lockForUpdate()
            ->get();

        $reserved = round((float) $rows->sum('debit_amount'), 2);

        if (abs($reserved - $expected) > 0.01) {
            throw new ClientAccountException('Return ' . $return->return_code . ' deducts ' . number_format($expected, 2) . ' on its lines but ' . number_format($reserved, 2) . ' is reserved on the client account. Generate the returns again.');
        }

        if ($rows->isNotEmpty()) {
            DB::table('cb_landlord_account')->whereIn('id', $rows->pluck('id'))->update([
                'is_recovered' => 1,
                'recovery_year' => $return->year,
                'recovery_month' => $return->month,
                'recovery_server_time' => now(),
            ]);
        }

        return $rows;
    }

    /**
     * Take the VAT on commission off the client's account and put it in VAT Control (debit Clients Account, credit VAT Control).
     *
     * @param ReturnSummary $return
     * @param float $vat
     * @param int $userId
     */
    protected function postVat(ReturnSummary $return, float $vat, int $userId): void {
        $details = 'return:' . $return->id . ':vat';

        if ($this->poster->exists($details)) {
            return;
        }

        $accounts = $this->accounts(['clients_account_id' => 'Clients Account', 'vat_control_account_id' => 'VAT Control']);

        $this->poster->journal($return->return_code . '/VAT', 'VAT on commission, return ' . $return->return_code, $details, [
            ['account_id' => $accounts['clients_account_id'], 'type' => 'Debit', 'amount' => $vat],
            ['account_id' => $accounts['vat_control_account_id'], 'type' => 'Credit', 'amount' => $vat],
        ], $userId, self::TRANSACTION_TYPE);
    }

    /**
     * GL accounts from the GL setup. A missing one stops the posting with a message saying which.
     *
     * @param array $needed setup column => label
     */
    protected function accounts(array $needed): array {
        $setup = GlAccountSetup::first();
        $accounts = [];

        foreach ($needed as $column => $label) {
            $id = $setup->{$column} ?? null;

            if (!$id) {
                throw new ClientAccountException('The GL setup is missing the ' . $label . ' account.');
            }

            $accounts[$column] = (int) $id;
        }

        return $accounts;
    }
}
