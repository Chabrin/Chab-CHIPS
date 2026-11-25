<?php

namespace App\Services\BankStatement;

/**
 * The Equity Bank statement template: TRAN_DATE, SECURITY NO., TRAN_AMT, TRAN_TYPE, VALUE_DATE, REF_NUM, TRAN_RMKS,
 * SECURITY NO 2, TRAN_PARTICULAR, MODE OF PAYMENT. The unit code is read from REF_NUM, the reference number, which holds
 * it for cash and agent deposits (TRAN_PARTICULAR repeats it at the start, for example
 * "187G-8/Akinyi/KECYeUNL8Lvb/KECYeUNL8Lvb/10-11-2026"). Where REF_NUM is a bank reference instead, app payments carry
 * the code inside TRAN_PARTICULAR ("APP|375F-61/Rent/...") and a cheque in TRAN_RMKS, so those are searched next, in that
 * order. M-Pesa paybill lines carry none. Only CREDIT lines are payments.
 */
class EquityStatementParser extends StatementParser
{
    /**
     * The headers a file must have to be the Equity template (the page lists them above the upload box).
     */
    public const REQUIRED = ['TRAN_DATE', 'TRAN_AMT', 'TRAN_TYPE', 'REF_NUM', 'TRAN_PARTICULAR'];

    /**
     * The name of the template for messages.
     */
    public function label(): string {
        return 'Equity Bank';
    }

    /**
     * The headers a file must have to be the Equity template.
     *
     * @return string[]
     */
    protected function requiredHeaders(): array {
        return self::REQUIRED;
    }

    /**
     * Turn one Equity row into the columns of cb_equity_statement_lines.
     *
     * @param array $row header name to cell value
     */
    protected function mapRow(array $row): array {
        $particular = $this->text($row['TRAN_PARTICULAR'] ?? null);
        $type = $this->text($row['TRAN_TYPE'] ?? null);
        $amount = $this->amount($row['TRAN_AMT'] ?? null);
        $date = $this->date($row['TRAN_DATE'] ?? null);
        // A cheque has no REF_NUM; the bank's own transaction number in SECURITY NO. stands in for it
        $reference = $this->text($row['REF_NUM'] ?? null) ?? $this->text($row['SECURITY NO.'] ?? null);
        // The unit code is the reference number (REF_NUM); only when that is a bank reference instead (app payments,
        // cheques) are TRAN_PARTICULAR and then TRAN_RMKS searched
        $unitCode = $this->unitCodeCell($this->text($row['REF_NUM'] ?? null)) ?? $this->unitCode($particular, $this->text($row['TRAN_RMKS'] ?? null));

        $judged = $this->judge($amount, $date, $reference);
        if ($judged['match_status'] === 'Pending' && $type !== null && strtoupper($type) !== 'CREDIT') {
            $judged = ['match_status' => 'Skipped', 'match_note' => 'Not money in (' . $type . ')'];
        }

        // With the unit code first the payer's name is the second part; otherwise it cannot be told from the text
        $parts = $particular !== null ? array_map('trim', explode('/', $particular)) : [];
        $payer = $unitCode !== null && isset($parts[1]) && strtoupper($parts[0]) === $unitCode && $parts[1] !== '' ? $parts[1] : null;

        return [
            'tran_date' => $date,
            'security_no' => $this->text($row['SECURITY NO.'] ?? null),
            'tran_amt' => $amount,
            'tran_type' => $type,
            'value_date' => $this->date($row['VALUE_DATE'] ?? null),
            'ref_num' => $reference,
            'tran_rmks' => $this->text($row['TRAN_RMKS'] ?? null),
            'security_no_2' => $this->text($row['SECURITY NO 2'] ?? null),
            'tran_particular' => $particular,
            'mode_of_payment' => $this->text($row['MODE OF PAYMENT'] ?? null),
            'unit_code' => $unitCode,
            'payer_name' => $payer,
        ] + $judged;
    }
}
