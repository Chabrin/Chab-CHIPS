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
            // ST_NAME is not the payer: on a real file it repeats the unit code, so the name comes from NARRATION only
            'payer_name' => $this->nameInNarration($narration),
        ] + $this->judge($amount, $date, $reference);
    }

    /**
     * The payer's name from a narration that carries one in a fixed place: an M-Pesa paybill line
     * ("UKDBR1QDM2~2547xxxxxxxx~account~MPESAC2B_400200~NAME") or a Pesalink line ("PESALINK~ref~NAME~..."). Any other
     * narration is free text, where the name cannot be told apart, so it gives null. Shown on the manual match page only.
     *
     * @param string|null $narration
     */
    private function nameInNarration(?string $narration): ?string {
        if ($narration === null) {
            return null;
        }

        $parts = array_map('trim', explode('~', $narration));
        $name = null;

        if (count($parts) >= 5 && stripos($parts[3], 'MPESA') === 0) {
            $name = $parts[4];
        } elseif (count($parts) >= 3 && strcasecmp($parts[0], 'PESALINK') === 0) {
            $name = $parts[2];
        }

        return $name !== null && $name !== '' ? $name : null;
    }
}
