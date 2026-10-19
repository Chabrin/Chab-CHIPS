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
            ->first(['id', 'names', 'reference_number', 'mobile_number', 'email_address', 'national_id']);

        if (!$client) {
            return null;
        }

        return [
            'id' => (int) $client->id,
            'names' => (string) ($client->names ?? ''),
            'reference_number' => (string) ($client->reference_number ?? ''),
            'mobile_number' => (string) ($client->mobile_number ?? ''),
            'email_address' => (string) ($client->email_address ?? ''),
            'national_id' => (string) ($client->national_id ?? ''),
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
     * The period a statement opens on when nobody picks one: the last three months, ending today.
     * Every link into the statement page relies on this, so the period is set in one place.
     */
    public function defaultPeriod(): array {
        $to = now();

        return ['from' => $to->copy()->subMonthsNoOverflow(3)->addDay()->toDateString(), 'to' => $to->toDateString()];
    }

    /**
     * A landlord's statement for a period: the balance brought forward from before the period,
     * every entry inside it with its running balance, and the totals and closing balance.
     *
     * Balances follow the same rule as the rest of the ledger (see statementEntries), so the
     * closing balance is the account balance as at the end of the period. Entries with no date
     * cannot be placed in a period and are only counted. Rows carried forward from the old
     * system (B/F and C/F) are listed but left out of the debit and credit totals.
     *
     * @param int $clientId
     * @param string $from Y-m-d, first day of the period
     * @param string $to Y-m-d, last day of the period
     */
    public function statement(int $clientId, string $from, string $to): array {
        $lines = $this->statementEntries(
            LandlordAccount::where('client_id', $clientId)
                ->orderBy('entry_date', 'asc')
                ->orderByRaw('EXTRACT(YEAR FROM entry_date) ASC')
                ->orderByRaw('EXTRACT(MONTH FROM entry_date) ASC')
                ->orderBy('id', 'asc')
                ->get()
        );

        $opening = 0.0;
        $rows = [];
        $undated = 0;
        $debits = 0.0;
        $credits = 0.0;
        $hasCarriedForward = false;

        foreach ($lines as $line) {
            $date = $line['date'] ? substr((string) $line['date'], 0, 10) : null;

            if ($date === null) {
                $undated++;
                continue;
            }
            if ($date < $from) {
                $opening = (float) $line['balance'];
                continue;
            }
            if ($date > $to) {
                break;
            }

            $rows[] = array_merge($line, ['date_ymd' => $date]);
            if ($line['is_marker']) {
                $hasCarriedForward = true;
            } else {
                $debits += $line['debit'];
                $credits += $line['credit'];
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'opening' => round($opening, 2),
            'rows' => $rows,
            'debits' => round($debits, 2),
            'credits' => round($credits, 2),
            'closing' => round($rows ? (float) end($rows)['balance'] : $opening, 2),
            'has_carried_forward' => $hasCarriedForward,
            'undated' => $undated,
            'properties' => DB::table('sys_pm_properties')->where('client_id', $clientId)->orderBy('property_name')->pluck('property_name')->filter()->values()->all(),
        ];
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
                'is_marker' => in_array($narration, ['Opening Balance B/F', 'Closing Balance C/F'], true),
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
