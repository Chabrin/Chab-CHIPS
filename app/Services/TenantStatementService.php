<?php

namespace App\Services;

use App\Models\TenantAccountTransaction;

class TenantStatementService
{
    /**
     * The reference a statement line shows. An invoice shows its invoice number. A payment shows its own reference (the
     * receipt, or the bank or M-Pesa reference) and only falls back to the invoice number it paid when it has none, so a
     * payment never reads like the invoice it settled.
     *
     * @param bool $isDebit
     * @param string|null $invoiceNumber
     * @param mixed $receiptNumber
     * @param string|null $entryRef
     */
    public static function referenceOf(bool $isDebit, ?string $invoiceNumber, $receiptNumber, ?string $entryRef): string {
        if ($isDebit) {
            return $invoiceNumber ?: ($receiptNumber ?: ($entryRef ?: '-'));
        }

        return ($entryRef ?: ($receiptNumber ?: ($invoiceNumber ?: '-')));
    }

    /**
     * The narration a statement line shows, starting with what the line is: an invoice line starts INVOICE, a payment line
     * PAYMENT (PAID TO OWNER or RECOVERY when the tenant did not pay us, see the payment mode), followed by what it was for.
     * A debit that is not an invoice (a refund or an adjustment) keeps its own wording.
     *
     * @param bool $isDebit
     * @param string|null $narration the ledger narration
     * @param string|null $invoiceNumber
     * @param string|null $paymentMode CA, PO, REC and so on
     */
    public static function narrationOf(bool $isDebit, ?string $narration, ?string $invoiceNumber, ?string $paymentMode): string {
        $text = trim((string) $narration);

        if ($isDebit) {
            if (!$invoiceNumber) {
                return $text !== '' ? $text : 'Invoice';
            }
            $label = 'INVOICE';
        } else {
            $label = match (strtoupper((string) $paymentMode)) {
                'PO' => 'PAID TO OWNER',
                'REC' => 'RECOVERY',
                default => 'PAYMENT',
            };
        }

        if ($text === '') {
            return $label;
        }

        return str_starts_with(strtoupper($text), $label) ? $text : $label . ' - ' . $text;
    }

    public function build(int $tenantId, ?int $propertyId, ?string $dateFrom, ?string $dateTo): array
    {
        $ledger = TenantAccountTransaction::where('tenant_id', $tenantId)
            ->where('current_status_id', 1);

        if ($propertyId !== null) {
            $ledger->where('property_id', $propertyId);
        }

        $openingBalance = null;
        if ($dateFrom !== null) {
            $openingBalance = (float) (clone $ledger)->whereDate('date_time', '<', $dateFrom)
                ->selectRaw('COALESCE(SUM(ABS(credit_amount)), 0) - COALESCE(SUM(ABS(debit_amount)), 0) AS balance')
                ->value('balance');

            $ledger->whereDate('date_time', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $ledger->whereDate('date_time', '<=', $dateTo);
        }

        $entries = [];
        $balance = $openingBalance ?? 0.0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $transactions = $ledger->orderBy('date_time')->orderBy('id')->get();

        foreach ($transactions as $txn) {
            $isDebit = strtoupper($txn->dr_cr ?? '') === 'DR';
            $debit = abs((float) $txn->debit_amount);
            $credit = abs((float) $txn->credit_amount);
            $totalDebit += $debit;
            $totalCredit += $credit;
            $balance += $credit - $debit;

            $entries[] = [
                'date' => $txn->date_time,
                'reference' => self::referenceOf($isDebit, $txn->invoice_number, $txn->receipt_number, $txn->entry_ref),
                'narration' => self::narrationOf($isDebit, $txn->narration, $txn->invoice_number, $txn->payment_mode),
                'debit' => $debit,
                'credit' => $credit,
                'type' => $isDebit ? 'invoice' : 'payment',
                'balance' => round($balance, 2),
                'date_formatted' => $txn->date_time?->format('d-m-Y') ?? '-',
                'debit_formatted' => $debit > 0 ? number_format($debit, 2) : '-',
                'credit_formatted' => $credit > 0 ? number_format($credit, 2) : '-',
                'balance_formatted' => number_format($balance, 2),
            ];
        }

        return [
            'statement' => $entries,
            'summary' => [
                'opening_balance' => $openingBalance === null ? null : round($openingBalance, 2),
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'closing_balance' => round($balance, 2),
            ],
        ];
    }
}
