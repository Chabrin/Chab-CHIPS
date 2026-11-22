<?php

namespace App\Services;

use App\Models\ReturnClient;
use App\Models\ReturnSummary;

/**
 * Shows how the amount of a return payment voucher was reached, in the order of the return statement: the rent, the
 * commission and VAT taken off, the other money added, then each deduction (advances recovered, cheque charges, caretaker
 * and so on) down to the amount payable. It is the return statement's own figures, in one short list for the voucher screen.
 * A merged return has no tenant rows, so it lists its own lines (one net per property, then the deductions).
 */
class ReturnVoucherBreakdownService
{
    protected ReturnStatementService $statements;

    /**
     * Inject the service that works out the return statement.
     *
     * @param ReturnStatementService $statements
     */
    public function __construct(ReturnStatementService $statements) {
        $this->statements = $statements;
    }

    /**
     * The rows that explain a return's amount, and the amount the voucher pays.
     *
     * @param ReturnSummary $return
     * @return array rows (kind, label, reference, date, amount), net_balance, and merged (whether the rows are a merged return's own lines)
     */
    public function forReturn(ReturnSummary $return): array {
        if ($return->return_type === 'MASTERRETURN') {
            return $this->mergedRows($return);
        }

        $lines = ReturnClient::forReturn($return->return_code);
        $statement = $this->statements->statement($return, $lines);
        $summary = $statement['summary'];
        $rows = [];

        foreach ($this->statements->summaryRows($return) as [$key, $label, $kind]) {
            $amount = round((float) ($summary[$key] ?? 0), 2);
            // Lines that are nil are left out, except the ones that carry the sums
            if ($amount == 0.0 && !in_array($kind, ['strong', 'closing'], true)) {
                continue;
            }
            $rows[] = ['kind' => $kind, 'label' => $label, 'reference' => '', 'date' => '', 'amount' => $amount];
        }

        foreach ($statement['settlementRows'] as $row) {
            $rows[] = ['kind' => $row['kind'], 'label' => $row['label'], 'reference' => $row['ref'], 'date' => $row['date'], 'amount' => round((float) $row['amount'], 2)];
        }

        return ['rows' => $rows, 'net_balance' => round((float) $return->net_balance, 2), 'merged' => false];
    }

    /**
     * A merged return's own lines: the net of each property return it sums up, then the deductions taken from the landlord account.
     *
     * @param ReturnSummary $return
     */
    protected function mergedRows(ReturnSummary $return): array {
        $rows = [];

        foreach (ReturnClient::forReturn($return->return_code) as $line) {
            $amount = round((float) ($line->net_amount ?? 0), 2);
            if ($amount == 0.0) {
                continue;
            }

            $rows[] = [
                'kind' => $line->dr_cr === 'CR' ? 'add' : 'less',
                'label' => (string) ($line->narration ?: $line->entry_description),
                'reference' => (string) ($line->transaction_ref ?? ''),
                'date' => '',
                'amount' => $amount,
            ];
        }

        $rows[] = ['kind' => 'closing', 'label' => 'Amount payable', 'reference' => '', 'date' => '', 'amount' => round((float) $return->net_balance, 2)];

        return ['rows' => $rows, 'net_balance' => round((float) $return->net_balance, 2), 'merged' => true];
    }
}
