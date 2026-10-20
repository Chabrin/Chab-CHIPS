<?php

namespace App\Services;

use App\Models\Client;
use App\Models\LandlordAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Builds the Client Ledgers list: one row per landlord with the state of their account
 * on the client ledger (cb_landlord_account).
 *
 * The balance is credit less debit over every entry whose type counts toward the balance, as set
 * in the landlord entry type registry (LandlordEntryTypeService). Entries whose type does not count
 * (for example the balance snapshots the returns process writes) are still shown, but leave the
 * balance unchanged. The list and the statement use the same rule, so they show the same figure.
 *
 * A landlord with no ledger rows has no account open yet.
 */
class ClientLedgerService
{
    const FILTER_ACCOUNT = ['all', 'open', 'none'];
    const FILTER_FEE = ['all', 'unpaid'];
    const FILTER_BALANCE = ['all', 'owed_to', 'owed_by'];

    protected LandlordEntryTypeService $entryTypes;

    /**
     * Inject the entry type registry.
     *
     * @param LandlordEntryTypeService $entryTypes
     */
    public function __construct(LandlordEntryTypeService $entryTypes) {
        $this->entryTypes = $entryTypes;
    }

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

        $visible = $this->visibleLandlordIds();

        return collect(DB::select($this->query()))
            ->map(fn($row) => $this->present($row))
            ->filter(function (array $row) use ($account, $fee, $balance, $visible) {
                if ($visible !== null && !isset($visible[$row['id']])) {
                    return false;
                }
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
     * The landlord behind an account, or null when the id is not a landlord or the signed-in
     * user's zones do not include them.
     *
     * @param int $clientId
     */
    public function account(int $clientId): ?array {
        $client = Client::query()
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
            'debit' => round($entry['debit'], 2) + 0.0,
            'credit' => round($entry['credit'], 2) + 0.0,
            'balance' => round($entry['balance'], 2) + 0.0,
            'counted' => $entry['counted'],
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
     * cannot be placed in a period and are only counted. Entries whose type does not count toward
     * the balance are listed but left out of the debit and credit totals.
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

        // Entries with no date can sit in no period, so they are counted over the whole ledger
        $undated = count(array_filter($lines, fn($line) => !$line['date']));
        $opening = 0.0;
        $rows = [];
        $debits = 0.0;
        $credits = 0.0;
        $hasUncounted = false;

        foreach ($lines as $line) {
            $date = $line['date'] ? substr((string) $line['date'], 0, 10) : null;

            if ($date === null) {
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
            if (!$line['counted']) {
                $hasUncounted = true;
            } else {
                $debits += $line['debit'];
                $credits += $line['credit'];
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'opening' => round($opening, 2) + 0.0,
            'rows' => $rows,
            'debits' => round($debits, 2) + 0.0,
            'credits' => round($credits, 2) + 0.0,
            'closing' => round($rows ? (float) end($rows)['balance'] : $opening, 2) + 0.0,
            'has_uncounted' => $hasUncounted,
            'undated' => $undated,
            'properties' => DB::table('sys_pm_properties')->where('client_id', $clientId)->orderBy('property_name')->pluck('property_name')->filter()->values()->all(),
        ];
    }

    /**
     * Run a landlord's ledger rows (already in statement order) into statement lines with a
     * running balance. A row whose entry type counts adds credit less debit; a row whose type does
     * not count is listed with counted = false and leaves the balance as it was.
     *
     * @param iterable $transactions
     */
    public function statementEntries($transactions): array {
        $statementEntries = [];
        $balance = 0;

        foreach ($transactions as $txn) {
            $debit = abs($txn->debit_amount ?? 0);
            $credit = abs($txn->credit_amount ?? 0);
            $counted = $this->entryTypes->counts($txn->entry_type_code, $txn->entry_description);

            if ($counted) {
                $balance += $credit - $debit;
            }

            $statementEntries[] = [
                'counted' => $counted,
                'date' => $txn->entry_date,
                'reference' => $txn->transaction_number ?? '-',
                'narration' => $txn->entry_particulars ?: ($txn->entry_description ?? '-'),
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
     * The landlords the signed-in user may see, keyed by id, or null when there is no restriction.
     * Zone-limited users (field officers, zone managers and the like) see only their own zones,
     * the same visibility the Clients pages give them.
     */
    protected function visibleLandlordIds(): ?array {
        if (Client::zoneAccessState() === null) {
            return null;
        }

        return array_flip(Client::query()->where('reference_number', 'like', 'LAN%')->pluck('id')->all());
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
            'balance' => round((float) $row->balance, 2) + 0.0,
            'unpaid_fee' => round((float) $row->unpaid_fee, 2) + 0.0,
        ];
    }

    /**
     * The SQL behind the list. Every landlord (reference LAN...) is returned, with zeros and
     * null dates when there are no ledger rows; summaries() then narrows it to the landlords the
     * signed-in user may see. The balance counts only entries whose type counts (see the registry).
     *
     * unpaid_fee is the registration fee (entry code 0023) still waiting to be recovered.
     */
    protected function query(): string {
        $code = ClientAccountService::REGISTRATION_CODE;
        $entryTypes = $this->entryTypes->lateralJoin('l');

        return "
            WITH rows AS (
                SELECT l.client_id, l.entry_date, l.entry_type_code, l.debit_amount, l.is_recovered,
                    COALESCE(et.counts_toward_balance, TRUE) AS counts,
                    ABS(COALESCE(l.credit_amount, 0)) - ABS(COALESCE(l.debit_amount, 0)) AS net
                FROM cb_landlord_account l
                {$entryTypes}
                WHERE l.client_id IS NOT NULL
            ),
            summary AS (
                SELECT client_id,
                    COUNT(*) AS entries,
                    MIN(entry_date) AS first_entry,
                    MAX(entry_date) AS last_entry,
                    COALESCE(SUM(net) FILTER (WHERE counts), 0) AS balance,
                    COALESCE(SUM(debit_amount) FILTER (
                        WHERE entry_type_code = '{$code}' AND debit_amount > 0 AND COALESCE(is_recovered, 0) = 0
                    ), 0) AS unpaid_fee
                FROM rows
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
