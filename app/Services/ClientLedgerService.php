<?php

namespace App\Services;

use App\Models\LandlordAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Builds the Client Ledgers list: one row per landlord with the state of their account
 * on the client ledger (cb_landlord_account).
 *
 * The balance follows the same rule as the Landlord Account Statement so a landlord shows
 * the same figure in the list and on their statement: entries are taken in the statement's
 * order (date, year, month, id), an "Opening Balance B/F" or "Closing Balance C/F" row sets
 * the running balance to its own credit less debit, and every other row adds credit less debit.
 * That makes the balance the sum of credit less debit from the last such row onward.
 *
 * A landlord with no ledger rows has no account open yet.
 */
class ClientLedgerService
{
    const FILTER_ACCOUNT = ['all', 'open', 'none'];
    const FILTER_FEE = ['all', 'unpaid'];
    const FILTER_BALANCE = ['all', 'owed_to', 'owed_by'];

    /**
     * One row per landlord, newest activity first, optionally narrowed by filters.
     *
     * Filters: account (all|open|none), fee (all|unpaid), balance (all|owed_to|owed_by).
     * owed_to = we owe the landlord (balance above zero), owed_by = the landlord owes us.
     *
     * @param array $filters
     */
    public function summaries(array $filters = []): Collection {
        $account = $filters['account'] ?? 'all';
        $fee = $filters['fee'] ?? 'all';
        $balance = $filters['balance'] ?? 'all';

        return collect(DB::select($this->query()))
            ->map(fn($row) => $this->present($row))
            ->filter(function (array $row) use ($account, $fee, $balance) {
                if ($account === 'open' && !$row['has_account']) {
                    return false;
                }
                if ($account === 'none' && $row['has_account']) {
                    return false;
                }
                if ($fee === 'unpaid' && $row['unpaid_fee'] <= 0) {
                    return false;
                }
                if ($balance === 'owed_to' && $row['balance'] <= 0) {
                    return false;
                }
                if ($balance === 'owed_by' && $row['balance'] >= 0) {
                    return false;
                }

                return true;
            })
            ->sortBy([['has_account', 'desc'], ['last_activity', 'desc'], ['names', 'asc']])
            ->values();
    }

    /**
     * The landlord behind an account, or null when the id is not a landlord.
     *
     * @param int $clientId
     */
    public function account(int $clientId): ?array {
        $client = DB::table('sys_pm_clients')
            ->where('id', $clientId)
            ->where('reference_number', 'like', 'LAN%')
            ->first(['id', 'names', 'reference_number', 'mobile_number']);

        if (!$client) {
            return null;
        }

        return [
            'id' => (int) $client->id,
            'names' => (string) ($client->names ?? ''),
            'reference_number' => (string) ($client->reference_number ?? ''),
            'mobile_number' => (string) ($client->mobile_number ?? ''),
        ];
    }

    /**
     * Every entry on a landlord's ledger in statement order, each with its running balance.
     * Dates come back as Y-m-d so the page can sort on them; seq is the position in the
     * statement (1 = oldest).
     *
     * @param int $clientId
     */
    public function entries(int $clientId): array {
        $transactions = LandlordAccount::where('client_id', $clientId)
            ->orderBy('entry_date', 'asc')
            ->orderByRaw('EXTRACT(YEAR FROM entry_date) ASC')
            ->orderByRaw('EXTRACT(MONTH FROM entry_date) ASC')
            ->orderBy('id', 'asc')
            ->get();

        return collect($this->statementEntries($transactions))->values()->map(fn($entry, $i) => [
            'seq' => $i + 1,
            'date' => $entry['date'] ? substr((string) $entry['date'], 0, 10) : null,
            'date_formatted' => $entry['date_formatted'],
            'reference' => $entry['reference'],
            'narration' => $entry['narration'],
            'debit' => round($entry['debit'], 2),
            'credit' => round($entry['credit'], 2),
            'balance' => round($entry['balance'], 2),
        ])->all();
    }

    /**
     * Run a landlord's ledger rows (already in statement order) into statement lines with a
     * running balance. This is the one place the balance rule lives for the statement: an
     * "Opening Balance B/F" or "Closing Balance C/F" row sets the balance to its own credit
     * less debit, every other row adds credit less debit.
     *
     * @param iterable $transactions
     */
    public function statementEntries($transactions): array {
        $statementEntries = [];
        $balance = 0;

        foreach ($transactions as $txn) {
            $debit = abs($txn->debit_amount ?? 0);
            $credit = abs($txn->credit_amount ?? 0);

            // Update balance only for non-balance entries
            // If entry is marked as opening/closing balance, use it to set the balance
            if (
                stripos($txn->entry_description, 'Opening Balance B/F') !== false ||
                stripos($txn->entry_particulars, 'Opening Balance B/F') !== false
            ) {
                // Opening balance - set balance to this amount
                $balance = $credit - $debit;
                $narration = 'Opening Balance B/F';
            } elseif (
                stripos($txn->entry_description, 'Closing Balance C/F') !== false ||
                stripos($txn->entry_particulars, 'Closing Balance C/F') !== false
            ) {
                // Closing balance - set balance to this amount
                $balance = $credit - $debit;
                $narration = 'Closing Balance C/F';
            } else {
                // Regular transaction - update balance
                $balance += $credit - $debit;
                $narration = $txn->entry_particulars ?: ($txn->entry_description ?? '-');
            }

            $statementEntries[] = [
                'date' => $txn->entry_date,
                'reference' => $txn->transaction_number ?? '-',
                'narration' => $narration,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
                'date_formatted' => $txn->entry_date ? date('d-m-Y', strtotime($txn->entry_date)) : '',
                'debit_formatted' => $debit > 0 ? number_format($debit, 2) : '',
                'credit_formatted' => $credit > 0 ? number_format($credit, 2) : '',
                'balance_formatted' => number_format($balance, 2),
            ];
        }

        return $statementEntries;
    }

    /**
     * Turn one database row into the array the page and the export use.
     *
     * @param object $row
     */
    protected function present(object $row): array {
        return [
            'id' => (int) $row->id,
            'encrypted_id' => Crypt::encrypt((int) $row->id),
            'names' => (string) ($row->names ?? ''),
            'reference_number' => (string) ($row->reference_number ?? ''),
            'mobile_number' => (string) ($row->mobile_number ?? ''),
            'has_account' => (int) $row->entries > 0,
            'entries' => (int) $row->entries,
            'opened' => $row->first_entry ? substr($row->first_entry, 0, 10) : null,
            'last_activity' => $row->last_entry ? substr($row->last_entry, 0, 10) : null,
            'balance' => round((float) $row->balance, 2),
            'unpaid_fee' => round((float) $row->unpaid_fee, 2),
        ];
    }

    /**
     * The SQL behind the list. Every landlord (reference LAN...) is returned, with zeros and
     * null dates when there are no ledger rows. The global client scope is skipped on purpose:
     * this is a finance page, like the landlord search on the statement.
     *
     * unpaid_fee is the registration fee (entry code 0023) still waiting to be recovered.
     */
    protected function query(): string {
        $code = ClientAccountService::REGISTRATION_CODE;

        return "
            WITH ordered AS (
                SELECT l.client_id, l.entry_date, l.entry_type_code, l.debit_amount, l.is_recovered,
                    ROW_NUMBER() OVER (
                        PARTITION BY l.client_id
                        ORDER BY l.entry_date, EXTRACT(YEAR FROM l.entry_date), EXTRACT(MONTH FROM l.entry_date), l.id
                    ) AS rn,
                    COALESCE(
                        l.entry_description ILIKE '%Opening Balance B/F%' OR l.entry_particulars ILIKE '%Opening Balance B/F%'
                        OR l.entry_description ILIKE '%Closing Balance C/F%' OR l.entry_particulars ILIKE '%Closing Balance C/F%',
                        FALSE
                    ) AS is_marker,
                    ABS(COALESCE(l.credit_amount, 0)) - ABS(COALESCE(l.debit_amount, 0)) AS net
                FROM cb_landlord_account l
                WHERE l.client_id IS NOT NULL
            ),
            marked AS (
                SELECT ordered.*, MAX(CASE WHEN is_marker THEN rn END) OVER (PARTITION BY client_id) AS last_marker
                FROM ordered
            ),
            summary AS (
                SELECT client_id,
                    COUNT(*) AS entries,
                    MIN(entry_date) AS first_entry,
                    MAX(entry_date) AS last_entry,
                    COALESCE(SUM(net) FILTER (WHERE rn >= COALESCE(last_marker, 1)), 0) AS balance,
                    COALESCE(SUM(debit_amount) FILTER (
                        WHERE entry_type_code = '{$code}' AND debit_amount > 0 AND COALESCE(is_recovered, 0) = 0
                    ), 0) AS unpaid_fee
                FROM marked
                GROUP BY client_id
            )
            SELECT c.id, c.names, c.reference_number, c.mobile_number,
                COALESCE(s.entries, 0) AS entries,
                s.first_entry, s.last_entry,
                COALESCE(s.balance, 0) AS balance,
                COALESCE(s.unpaid_fee, 0) AS unpaid_fee
            FROM sys_pm_clients c
            LEFT JOIN summary s ON s.client_id = c.id
            WHERE c.reference_number LIKE 'LAN%'
        ";
    }
}
