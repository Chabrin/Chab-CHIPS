<?php

namespace App\Services;

use App\Models\PaymentVoucherHeader;
use App\Models\PaymentVoucherLine;
use Illuminate\Support\Facades\DB;

/**
 * What the printed payment voucher shows, worked out from the voucher and its lines, to match the voucher the office
 * has always printed: the voucher's own date, each line's narration ending with the month and year it is posted to
 * (FINAL PAYMENTS FLORAH HOUSE-NOV-26), the bank the cheque is drawn on, the cheque number, the cheque charges listed
 * apart from the grand total, the amount in words, and the names of who prepared, checked and authorised it.
 */
class VoucherPrintService
{
    /** A line whose narration starts with this is a cheque charge: the company's charge, not money paid to the payee. */
    const CHEQUE_CHARGE = 'CHEQUE CHARGE';

    /**
     * Everything the print view needs for a voucher.
     *
     * @param PaymentVoucherHeader $voucher
     * @return array date, lines, charges, total, words, prepared_by, checked_by, authorized_by
     */
    public function forVoucher(PaymentVoucherHeader $voucher): array {
        $lines = PaymentVoucherLine::where('voucher_number', $voucher->voucher_number)->orderBy('id')->get();
        $banks = $this->banksOfLines($lines);

        $rows = [];
        $charges = [];

        foreach ($lines as $line) {
            $description = (string) ($line->entry_description ?? '');

            if (self::isChequeCharge($description)) {
                $charges[] = ['description' => $description, 'amount' => (float) $line->amount];
                continue;
            }

            $rows[] = [
                'description' => self::narrationWithPeriod($description, (int) $voucher->month, (int) $voucher->year),
                'bank' => $banks[$line->id] ?? '',
                'cheque' => (string) ($line->cheque_number ?? ''),
                'amount' => (float) $line->amount,
            ];
        }

        $total = round(array_sum(array_column($rows, 'amount')), 2);

        return [
            'date' => $voucher->voucher_date ? $voucher->voucher_date->format('d-M-Y') : '',
            'lines' => $rows,
            'charges' => $charges,
            'total' => $total,
            'words' => self::amountInWords($total),
            'prepared_by' => (string) ($voucher->prepared_by ?? ''),
            'checked_by' => (string) ($voucher->checked_by ?? ''),
            'authorized_by' => (string) ($voucher->authorized_by ?? ''),
        ];
    }

    /**
     * Whether a narration is a cheque charge.
     *
     * @param string $description
     */
    public static function isChequeCharge(string $description): bool {
        return str_starts_with(strtoupper(trim($description)), self::CHEQUE_CHARGE);
    }

    /**
     * A narration with the month and year of the voucher's posting at its end, as the office writes it (-NOV-26). A narration
     * that already ends that way is left as it is, so a printed voucher is never stamped twice.
     *
     * @param string $description
     * @param int $month 1 to 12
     * @param int $year
     */
    public static function narrationWithPeriod(string $description, int $month, int $year): string {
        $description = trim($description);

        if ($description === '' || $month < 1 || $month > 12 || $year < 2000 || preg_match('/-[A-Za-z]{3}-\d{2}$/', $description)) {
            return $description;
        }

        return $description . '-' . strtoupper(date('M', mktime(0, 0, 0, $month, 1))) . '-' . substr((string) $year, -2);
    }

    /**
     * The short name of a bank for the Bank column: the part of the company account's name before " - " (CO-OP - BUSINESS
     * ACCOUNT gives CO-OP), or the bank's own name when the account name has no such part.
     *
     * @param string|null $accountName
     * @param string|null $bankName
     */
    public static function bankLabel(?string $accountName, ?string $bankName): string {
        $accountName = trim((string) $accountName);

        if (str_contains($accountName, ' - ')) {
            return strtoupper(trim(explode(' - ', $accountName, 2)[0]));
        }

        return strtoupper($accountName !== '' ? $accountName : trim((string) $bankName));
    }

    /**
     * The bank each line's cheque is drawn on: the bank account of its cheque leaf, or the source account saved on the line.
     *
     * @param \Illuminate\Support\Collection $lines
     * @return array line id => short bank name
     */
    protected function banksOfLines($lines): array {
        $accountOfLine = [];
        $leafIds = $lines->pluck('cheque_leaf_id')->filter()->unique()->all();
        $leafAccount = $leafIds
            ? DB::table('sys_fin_cheque_books_261_repeat')->whereIn('id', $leafIds)->pluck('bank_account_id', 'id')->all()
            : [];

        foreach ($lines as $line) {
            $accountId = $leafAccount[$line->cheque_leaf_id] ?? $line->source_account_id ?? null;
            if ($accountId) {
                $accountOfLine[$line->id] = (int) $accountId;
            }
        }

        if (!$accountOfLine) {
            return [];
        }

        $accounts = DB::table('sys_set_bank_accounts as a')
            ->leftJoin('sys_set_banks as b', 'b.id', '=', 'a.bank_id')
            ->whereIn('a.id', array_unique($accountOfLine))
            ->get(['a.id', 'a.account_name', 'b.bank_name'])
            ->keyBy('id');

        $labels = [];
        foreach ($accountOfLine as $lineId => $accountId) {
            $account = $accounts->get($accountId);
            if ($account) {
                $labels[$lineId] = self::bankLabel($account->account_name, $account->bank_name);
            }
        }

        return $labels;
    }

    /**
     * An amount in words the way the printed voucher writes it: *NINE THOUSAND FOUR HUNDRED FIFTY SHILLINGS AND ZERO CENTS ONLY ***
     *
     * @param float $amount
     */
    public static function amountInWords(float $amount): string {
        $amount = round(max($amount, 0), 2);
        $whole = (int) floor($amount);
        $cents = (int) round(($amount - $whole) * 100);

        return '*' . ($whole === 0 ? 'ZERO' : self::words($whole)) . ' SHILLINGS AND ' . ($cents === 0 ? 'ZERO' : self::words($cents)) . ' CENTS ONLY ***';
    }

    /**
     * A whole number in capital words, with a hyphen in the twenties to nineties (EIGHTY-TWO).
     *
     * @param int $number more than zero
     */
    protected static function words(int $number): string {
        $ones = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
        $tens = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];
        $parts = [];

        foreach ([1000000000 => 'BILLION', 1000000 => 'MILLION', 1000 => 'THOUSAND'] as $value => $label) {
            if ($number >= $value) {
                $parts[] = self::words((int) floor($number / $value)) . ' ' . $label;
                $number %= $value;
            }
        }

        if ($number >= 100) {
            $parts[] = $ones[(int) floor($number / 100)] . ' HUNDRED';
            $number %= 100;
        }

        if ($number >= 20) {
            $parts[] = $tens[(int) floor($number / 10)] . ($number % 10 > 0 ? '-' . $ones[$number % 10] : '');
        } elseif ($number > 0) {
            $parts[] = $ones[$number];
        }

        return implode(' ', $parts);
    }
}
