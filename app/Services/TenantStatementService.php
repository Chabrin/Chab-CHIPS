<?php

namespace App\Services;

use App\Models\TenantAccountTransaction;

class TenantStatementService
{
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
                'reference' => $txn->invoice_number ?: ($txn->receipt_number ?: ($txn->entry_ref ?: '-')),
                'narration' => $txn->narration ?: ($isDebit ? 'Invoice' : 'Payment'),
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
