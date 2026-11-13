<?php

namespace App\Services\BankStatement;

use App\Exceptions\BankStatementException;

/**
 * The repository of bank statement templates. Each bank has its own line table and its own parser, and the four
 * column names the matcher and the page need (reference, amount, date, description) because they are called something
 * different in each file, plus the headings the file must have (the page shows them above each bank's upload box).
 * Adding a bank means adding one entry here, one line table and one parser class.
 */
class BankStatementBanks
{
    /**
     * Every bank template the system can read, keyed by the short name stored on an upload.
     *
     * @return array<string, array{label: string, columns: string[], table: string, parser: string, ref: string, amount: string, date: string, text: string}>
     */
    public static function all(): array {
        return [
            'equity' => [
                'label' => 'Equity Bank',
                'columns' => EquityStatementParser::REQUIRED,
                'table' => 'cb_equity_statement_lines',
                'parser' => EquityStatementParser::class,
                'ref' => 'ref_num',
                'amount' => 'tran_amt',
                'date' => 'tran_date',
                'text' => 'tran_particular',
            ],
            'coop' => [
                'label' => 'Co-operative Bank',
                'columns' => CoopStatementParser::REQUIRED,
                'table' => 'cb_coop_statement_lines',
                'parser' => CoopStatementParser::class,
                'ref' => 'reference',
                'amount' => 'amount',
                'date' => 'posting_date',
                'text' => 'narration',
            ],
        ];
    }

    /**
     * One bank's definition. Throws when the name is not one of the known banks.
     *
     * @param string $bank equity or coop
     */
    public static function get(string $bank): array {
        $all = self::all();

        if (!isset($all[$bank])) {
            throw new BankStatementException('Choose the bank this statement came from.');
        }

        return $all[$bank];
    }
}
