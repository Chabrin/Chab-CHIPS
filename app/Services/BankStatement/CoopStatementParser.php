<?php

namespace App\Services\BankStatement;

/**
 * The Co-operative Bank statement template: REFERENCE, POSTINGDATE, VALUEDATE, CODE, ST_NAME, CREDITACCOUNT, AMOUNT,
 * USERID, PAYMENTMETHOD, CHEQUENUMBER, NARRATION. CODE holds the unit code the payer quoted (confirmed on a real file of
 * 1,011 rows; ST_NAME repeats it). When CODE is blank the NARRATION is tried: its first "~" part, then any code in it.
 * POSTINGDATE comes as "13-NOV-2026 00:21:34".
 */
class CoopStatementParser extends StatementParser
{
    /**
     * The headers a file must have to be the Co-op template (the page lists them above the upload box).
     */
    public const REQUIRED = ['REFERENCE', 'POSTINGDATE', 'AMOUNT', 'CODE'];

    /**
     * The name of the template for messages.
     */
    public function label(): string {
        return 'Co-operative Bank';
    }

    /**
     * The headers a file must have to be the Co-op template.
     *
     * @return string[]
     */
    protected function requiredHeaders(): array {
        return self::REQUIRED;
    }

    /**
     * Turn one Co-op row into the columns of cb_coop_statement_lines.
     *
     * @param array $row header name to cell value
     */
    protected function mapRow(array $row): array {
        $reference = $this->text($row['REFERENCE'] ?? null);
        $amount = $this->amount($row['AMOUNT'] ?? null);
        $date = $this->date($row['POSTINGDATE'] ?? null);
        $code = $this->text($row['CODE'] ?? null);
        $narration = $this->text($row['NARRATION'] ?? null);

        return [
            'reference' => $reference,
            'posting_date' => $date,
            'value_date' => $this->date($row['VALUEDATE'] ?? null),
            'code' => $code,
            'st_name' => $this->text($row['ST_NAME'] ?? null),
            'credit_account' => $this->text($row['CREDITACCOUNT'] ?? null),
            'amount' => $amount,
            'bank_user_id' => $this->text($row['USERID'] ?? null),
            'payment_method' => $this->text($row['PAYMENTMETHOD'] ?? null),
            'cheque_number' => $this->text($row['CHEQUENUMBER'] ?? null),
            'narration' => $narration,
            'unit_code' => $this->unitCodeCell($code) ?? $this->unitCodeCell(explode('~', (string) $narration)[0]) ?? $this->unitCode($code, $narration),
            'payer_name' => $this->text($row['ST_NAME'] ?? null),
        ] + $this->judge($amount, $date, $reference);
    }
}
