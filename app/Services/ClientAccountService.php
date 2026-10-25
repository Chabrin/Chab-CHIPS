<?php

namespace App\Services;

use App\Exceptions\ClientAccountException;
use App\Models\ChartAccount;
use App\Models\Client;
use App\Models\GlAccountSetup;
use App\Models\InvoiceableItem;
use App\Models\LandlordAccount;
use App\Models\Property;
use App\Models\ServiceAgreement;
use App\Traits\GlPostingTrait;
use Illuminate\Support\Facades\Auth;

/**
 * Posts to a client's (landlord's) account when an SLA is approved.
 *
 * The registration fee goes on the client ledger (cb_landlord_account) as a debit, and when
 * the fee is above zero the matching journal is posted to the GL: debit the Clients Account,
 * credit Other Income, both taken from the GL setup. A client that has no ledger rows yet
 * gets a zero row instead, which is what opens the account.
 *
 * When the approver says the fee has already been paid, the fee row is marked recovered (so
 * the returns never deduct it a second time), a matching credit row is added to the ledger,
 * and a receipt journal debits the chosen cash or bank account and credits the Clients Account.
 *
 * Callers run this inside a database transaction. Any failure throws a
 * ClientAccountException so nothing is half posted.
 */
class ClientAccountService
{
    use GlPostingTrait;

    const REGISTRATION_CODE = '0023';
    const REGISTRATION_LABEL = 'Charge Registration Fee';
    const PAYMENT_LABEL = 'Registration Fee Paid';

    const RESULT_CHARGED = 'charged';
    const RESULT_SETTLED = 'settled';
    const RESULT_OPENED = 'opened';
    const RESULT_NONE = 'none';

    /**
     * How a landlord can pay the fee, and the GL setup account each way of paying lands in.
     * These are the company's own accounts; client-fund accounts are left out on purpose.
     */
    const PAYMENT_METHODS = [
        'cash' => ['label' => 'Cash', 'setup' => 'petty_cash_account_id', 'type' => 'CASH'],
        'mpesa' => ['label' => 'M-Pesa', 'setup' => 'mpesa_account_id', 'type' => 'MPESA'],
        'bank' => ['label' => 'Business bank', 'setup' => 'business_bank_account_id', 'type' => 'BANK'],
        'equity' => ['label' => 'Equity Bank', 'setup' => 'equity_bank_account_id', 'type' => 'EQUITY'],
    ];

    /**
     * What the approval page needs to offer the "fee already paid" choice: the fee amount and
     * the ways of paying that have an account in the GL setup, with the account each lands in.
     */
    public function registrationOptions(): array {
        $item = InvoiceableItem::where('billing_code', self::REGISTRATION_CODE)->first();
        $setup = GlAccountSetup::first();

        $methods = [];
        foreach (self::PAYMENT_METHODS as $key => $method) {
            $accountId = $setup->{$method['setup']} ?? null;
            if (!$accountId) {
                continue;
            }
            $account = ChartAccount::whereKey($accountId)->first(['account_name', 'account_code']);
            $methods[] = [
                'key' => $key,
                'label' => $method['label'],
                'account' => $account ? $account->account_name . ' (' . $account->account_code . ')' : null,
            ];
        }

        return ['amount' => $item ? round((float) $item->amount, 2) : 0, 'methods' => $methods];
    }

    /**
     * Post the registration fee row for an approved SLA.
     *
     * Box ticked: post the fee, and settle it at once when a payment is given. Box not ticked:
     * post a zero row only when the client has no ledger rows, otherwise post nothing.
     * Returns what was done.
     *
     * @param ServiceAgreement $sla
     * @param Client $client
     * @param Property|null $property
     * @param int $userId
     * @param array|null $payment ['method' => key of PAYMENT_METHODS, 'reference' => receipt or transaction reference]
     */
    public function postRegistration(ServiceAgreement $sla, Client $client, ?Property $property, int $userId, ?array $payment = null): string {
        $charge = (bool) $sla->charge_registration_fee;

        if (!$charge && LandlordAccount::where('client_id', $client->id)->exists()) {
            return self::RESULT_NONE;
        }

        $reference = 'REG-' . $sla->id;
        if (LandlordAccount::where('transaction_number', $reference)->exists()) {
            return self::RESULT_NONE;
        }

        $amount = $charge ? $this->registrationFee() : 0.0;
        $settle = $amount > 0 && $payment !== null;
        $accounts = $amount > 0 ? $this->glAccounts($settle ? $payment['method'] : null) : null;

        $propertyName = (string) ($property->property_name ?? '');
        $particulars = self::REGISTRATION_LABEL . ($propertyName !== '' ? ' - ' . $propertyName : '') . ($charge ? '' : ' (not charged)');

        LandlordAccount::create($this->ledgerRow($sla, $client, $property, [
            'transaction_number' => $reference,
            'entry_description' => self::REGISTRATION_LABEL,
            'debit_amount' => $amount,
            'total_amount' => $amount,
            'entry_particulars' => mb_substr($particulars, 0, 200),
            'gl_account' => $accounts ? $accounts['credit_code'] : null,
            'is_recovered' => ($amount > 0 && !$settle) ? 0 : 1,
        ]));

        if ($amount <= 0) {
            return self::RESULT_OPENED;
        }

        $this->postJournal($reference, $particulars, $amount, $userId, $accounts['debit_id'], $accounts['credit_id']);

        if (!$settle) {
            return self::RESULT_CHARGED;
        }

        $method = self::PAYMENT_METHODS[$payment['method']];
        $paymentReference = 'REGPAY-' . $sla->id;
        $paidParticulars = self::PAYMENT_LABEL . ($propertyName !== '' ? ' - ' . $propertyName : '') . ' (' . $method['label'] . ' ' . $payment['reference'] . ')';

        LandlordAccount::create($this->ledgerRow($sla, $client, $property, [
            'transaction_number' => $paymentReference,
            'entry_description' => self::PAYMENT_LABEL,
            'credit_amount' => $amount,
            'total_amount' => $amount,
            'payment_ref' => mb_substr((string) $payment['reference'], 0, 100),
            'payment_ref_type' => $method['type'],
            'entry_particulars' => mb_substr($paidParticulars, 0, 200),
            'gl_account' => $accounts['bank_code'],
            'is_recovered' => 1,
        ]));

        $this->postJournal($paymentReference, $paidParticulars, $amount, $userId, $accounts['bank_id'], $accounts['debit_id']);

        return self::RESULT_SETTLED;
    }

    /**
     * The fields every registration ledger row shares, with the row's own fields laid over them.
     *
     * @param ServiceAgreement $sla
     * @param Client $client
     * @param Property|null $property
     * @param array $row
     */
    protected function ledgerRow(ServiceAgreement $sla, Client $client, ?Property $property, array $row): array {
        $now = now();

        return array_merge([
            'year' => $now->year,
            'month' => $now->month,
            'landlord_code' => mb_substr((string) $client->reference_number, 0, 20),
            'landlord_name' => mb_substr((string) $client->names, 0, 60),
            'property_code' => mb_substr((string) ($property->reference_number ?? ''), 0, 20),
            'entry_type_code' => self::REGISTRATION_CODE,
            'entry_date' => $now,
            'debit_amount' => 0,
            'credit_amount' => 0,
            'account_status' => 0,
            'interest_rate' => 0,
            'audit_user' => mb_substr((string) (Auth::user()->name ?? 'SYSTEM'), 0, 20),
            'client_id' => $client->id,
            'property_id' => $property->id ?? $sla->property_id,
        ], $row);
    }

    /**
     * Post one balanced journal to the GL. A journal that cannot be posted fails the approval.
     *
     * @param string $reference
     * @param string $narration
     * @param float $amount
     * @param int $userId
     * @param int $debitId
     * @param int $creditId
     */
    protected function postJournal(string $reference, string $narration, float $amount, int $userId, int $debitId, int $creditId): void {
        $headerId = $this->postGlEntry([
            'type' => 'generic',
            'amount' => $amount,
            'date' => now()->toDateString(),
            'reference' => $reference,
            'narration' => mb_substr($narration, 0, 250),
            'entered_by' => $userId,
            'debit_account_id' => $debitId,
            'credit_account_id' => $creditId,
        ]);

        if (!$headerId) {
            throw new ClientAccountException('The registration fee journal could not be posted.');
        }
    }

    /**
     * The registration fee amount, read from the REGISTRATION item in Invoiceable Items.
     */
    protected function registrationFee(): float {
        $item = InvoiceableItem::where('billing_code', self::REGISTRATION_CODE)->first();

        if (!$item) {
            throw new ClientAccountException('The REGISTRATION item (code ' . self::REGISTRATION_CODE . ') is not set up in Invoiceable Items.');
        }

        return round((float) $item->amount, 2);
    }

    /**
     * The GL accounts for the fee journals, from the GL setup: the Clients Account to debit and
     * the Other Income account to credit, plus the cash or bank account when the fee is paid.
     *
     * @param string|null $method key of PAYMENT_METHODS, or null when the fee is not being paid now
     */
    protected function glAccounts(?string $method): array {
        $setup = GlAccountSetup::first();
        $debitId = $setup->clients_account_id ?? null;
        $creditId = $setup->other_income_account_id ?? null;

        if (!$debitId || !$creditId) {
            throw new ClientAccountException('The GL setup is missing the Clients Account or the Other Income account.');
        }

        $accounts = [
            'debit_id' => (int) $debitId,
            'credit_id' => (int) $creditId,
            'credit_code' => ChartAccount::whereKey($creditId)->value('account_code'),
        ];

        if ($method !== null) {
            $paid = self::PAYMENT_METHODS[$method] ?? null;
            $bankId = $paid ? ($setup->{$paid['setup']} ?? null) : null;

            if (!$bankId) {
                throw new ClientAccountException('The GL setup has no account for ' . ($paid['label'] ?? 'this way of paying') . '.');
            }

            $accounts['bank_id'] = (int) $bankId;
            $accounts['bank_code'] = ChartAccount::whereKey($bankId)->value('account_code');
        }

        return $accounts;
    }
}
