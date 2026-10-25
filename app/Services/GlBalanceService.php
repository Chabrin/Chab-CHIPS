<?php

namespace App\Services;

use App\Models\ChartAccount;
use App\Models\GlAccountSetup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Balances and account ledgers computed from the posted GL journals
 * (sys_gl_transactions headers and sys_gl_transactions_91_repeat lines).
 *
 * A line is a debit or a credit by its entry_type, and its amount is stored with a sign that
 * is ignored here (credits are stored negative), so every sum uses the absolute amount.
 * Balances are only as complete as the journals that have been posted: landlord events that
 * are not posted to the GL yet are not in any figure here.
 */
class GlBalanceService
{
    /** Order the account classes are shown in; anything else follows, then accounts with no class. */
    const CLASS_ORDER = ['Assets', 'Liabilities', 'Equity', 'Income', 'Costs'];
    const UNCLASSIFIED = 'Not classified';

    /** An account ledger covers at most this many days, so the page stays quick. */
    const MAX_RANGE_DAYS = 366;

    protected ClientLedgerService $clientLedger;

    /**
     * Inject the client ledger service (used for the default period).
     *
     * @param ClientLedgerService $clientLedger
     */
    public function __construct(ClientLedgerService $clientLedger) {
        $this->clientLedger = $clientLedger;
    }

    /**
     * The period an account ledger opens on: the last three months, ending today.
     */
    public function defaultPeriod(): array {
        return $this->clientLedger->defaultPeriod();
    }

    /**
     * One chart account's details, or null when the id is not in the chart.
     *
     * @param int $accountId
     */
    public function account(int $accountId): ?array {
        $account = ChartAccount::with('glClass:id,name')->find($accountId);
        if (!$account) {
            return null;
        }

        return [
            'id' => $account->id,
            'code' => (string) $account->account_code,
            'name' => (string) $account->account_name,
            'class' => $account->glClass->name ?? self::UNCLASSIFIED,
            'normally' => (string) $account->normally,
        ];
    }

    /**
     * Every account in the chart with its total debits, total credits and balance as at a date,
     * grouped by class, plus the trial balance totals.
     *
     * The balance is shown as an amount with a side (Dr or Cr). It is flagged abnormal when the
     * side is the opposite of the account's normal side, for example a liability in debit.
     *
     * @param string $asAt Y-m-d, journals up to the end of this day are counted
     * @param bool $hideZero leave out accounts that have no postings at all
     */
    public function balances(string $asAt, bool $hideZero = true): array {
        $end = Carbon::parse($asAt)->endOfDay();

        $sums = DB::table('sys_gl_transactions_91_repeat as d')
            ->join('sys_gl_transactions as h', 'h.id', '=', 'd.parent_id')
            ->where('h.journal_date', '<=', $end)
            ->groupBy('d.account_id')
            ->selectRaw("d.account_id, COUNT(*) AS lines, SUM(CASE WHEN d.entry_type = 'Debit' THEN ABS(d.amount) ELSE 0 END) AS debit, SUM(CASE WHEN d.entry_type = 'Credit' THEN ABS(d.amount) ELSE 0 END) AS credit")
            ->get()
            ->keyBy('account_id');

        $setupRoles = $this->setupRoles();
        $rows = [];
        $inChart = [];

        foreach (ChartAccount::with('glClass:id,name')->orderBy('account_code')->get() as $account) {
            $id = (string) $account->id;
            $inChart[$id] = true;
            $rows[] = $this->row($account->id, (string) $account->account_code, (string) $account->account_name, $account->glClass->name ?? self::UNCLASSIFIED, (string) $account->normally, $setupRoles[$id] ?? [], $sums->get($id));
        }

        // Lines on an id that is not in the chart are kept, so the totals still add up
        foreach ($sums as $id => $sum) {
            if (!isset($inChart[(string) $id])) {
                $rows[] = $this->row((int) $id, '-', 'Account id ' . $id . ' (not in the chart)', self::UNCLASSIFIED, 'Debit', [], $sum);
            }
        }

        $totalDebit = round(array_sum(array_column($rows, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($rows, 'credit')), 2);

        $groups = [];
        $hidden = 0;
        foreach ($rows as $row) {
            if ($hideZero && $row['lines'] === 0) {
                $hidden++;
                continue;
            }
            $groups[$row['class']]['name'] = $row['class'];
            $groups[$row['class']]['accounts'][] = $row;
        }

        uksort($groups, fn($a, $b) => $this->classRank($a) <=> $this->classRank($b) ?: strcmp($a, $b));

        $groups = array_map(function (array $group) {
            $group['count'] = count($group['accounts']);
            $group['debit'] = round(array_sum(array_column($group['accounts'], 'debit')), 2);
            $group['credit'] = round(array_sum(array_column($group['accounts'], 'credit')), 2);

            return $group;
        }, array_values($groups));

        return [
            'as_at' => $asAt,
            'groups' => $groups,
            'hidden' => $hidden,
            'totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
                'difference' => round($totalDebit - $totalCredit, 2),
                'balanced' => abs($totalDebit - $totalCredit) <= 0.01,
            ],
        ];
    }

    /**
     * One account's journal lines for a period, oldest first, with the balance brought forward
     * and a running balance. The running balance is in the account's normal direction, so a
     * negative figure means the account is on its abnormal side.
     *
     * Returns null when the account is not in the chart.
     *
     * @param int $accountId
     * @param string $from Y-m-d, first day
     * @param string $to Y-m-d, last day
     * @param int $maxLines most lines to return; the totals always cover the whole period
     */
    public function accountLedger(int $accountId, string $from, string $to, int $maxLines = 5000): ?array {
        $account = $this->account($accountId);
        if (!$account) {
            return null;
        }

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();
        $normallyDebit = $account['normally'] === 'Debit';

        $base = DB::table('sys_gl_transactions_91_repeat as d')
            ->join('sys_gl_transactions as h', 'h.id', '=', 'd.parent_id')
            ->where('d.account_id', (string) $accountId);

        $opening = (clone $base)->where('h.journal_date', '<', $start)
            ->selectRaw("COALESCE(SUM(CASE WHEN d.entry_type = 'Debit' THEN ABS(d.amount) ELSE 0 END), 0) AS debit, COALESCE(SUM(CASE WHEN d.entry_type = 'Credit' THEN ABS(d.amount) ELSE 0 END), 0) AS credit")
            ->first();

        $period = (clone $base)->whereBetween('h.journal_date', [$start, $end]);

        $totals = (clone $period)
            ->selectRaw("COUNT(*) AS lines, COALESCE(SUM(CASE WHEN d.entry_type = 'Debit' THEN ABS(d.amount) ELSE 0 END), 0) AS debit, COALESCE(SUM(CASE WHEN d.entry_type = 'Credit' THEN ABS(d.amount) ELSE 0 END), 0) AS credit")
            ->first();

        $openingNet = round((float) $opening->debit - (float) $opening->credit, 2);
        $running = $openingNet;
        $entries = [];

        $lines = (clone $period)
            ->orderBy('h.journal_date')->orderBy('h.id')->orderBy('d.id')
            ->limit($maxLines)
            ->get(['h.id as journal_id', 'h.journal_date', 'h.reference', 'd.narration', 'd.entry_type', 'd.amount']);

        foreach ($lines as $line) {
            $amount = abs((float) $line->amount);
            $isDebit = $line->entry_type === 'Debit';
            $running = round($running + ($isDebit ? $amount : -$amount), 2);

            $entries[] = [
                'journal_id' => $line->journal_id,
                'date' => $line->journal_date ? Carbon::parse($line->journal_date)->format('d-m-Y') : '',
                'reference' => (string) ($line->reference ?? ''),
                'narration' => (string) ($line->narration ?? ''),
                'debit' => $isDebit ? $amount : 0.0,
                'credit' => $isDebit ? 0.0 : $amount,
                'balance' => ($normallyDebit ? $running : -$running) + 0.0,
            ];
        }

        $debit = round((float) $totals->debit, 2);
        $credit = round((float) $totals->credit, 2);
        $closingNet = round($openingNet + $debit - $credit, 2);

        return [
            'account' => $account,
            'from' => $from,
            'to' => $to,
            'opening' => ($normallyDebit ? $openingNet : -$openingNet) + 0.0,
            'debits' => $debit,
            'credits' => $credit,
            'closing' => ($normallyDebit ? $closingNet : -$closingNet) + 0.0,
            'closing_side' => $closingNet >= 0 ? 'Dr' : 'Cr',
            'line_count' => (int) $totals->lines,
            'truncated' => (int) $totals->lines > count($entries),
            'entries' => $entries,
        ];
    }

    /**
     * Shape one account's figures for the balances page.
     *
     * @param int $id
     * @param string $code
     * @param string $name
     * @param string $class
     * @param string $normally Debit or Credit
     * @param array $setupRoles the GL setup fields that point at this account
     * @param object|null $sum the grouped sums for the account, or null when nothing is posted
     */
    protected function row(int $id, string $code, string $name, string $class, string $normally, array $setupRoles, ?object $sum): array {
        $debit = round((float) ($sum->debit ?? 0), 2);
        $credit = round((float) ($sum->credit ?? 0), 2);
        $net = round($debit - $credit, 2);
        $side = $net >= 0 ? 'Dr' : 'Cr';

        return [
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'class' => $class,
            'normally' => $normally,
            'setup_roles' => $setupRoles,
            'lines' => (int) ($sum->lines ?? 0),
            'debit' => $debit,
            'credit' => $credit,
            'balance' => abs($net),
            'side' => $net == 0 ? '' : $side,
            'abnormal' => $net != 0 && (($side === 'Dr') !== ($normally === 'Debit')),
        ];
    }

    /**
     * For each chart account the GL setup points at, the setup fields that do so
     * (for example 130 => ['clients_account_id']). These are the accounts the system relies on.
     */
    protected function setupRoles(): array {
        $setup = GlAccountSetup::first();
        if (!$setup) {
            return [];
        }

        $roles = [];
        foreach ($setup->getAttributes() as $field => $value) {
            if ($field !== 'id' && $field !== 'active_fiscal_year_id' && str_ends_with($field, '_id') && is_numeric($value) && (int) $value > 0) {
                $roles[(string) (int) $value][] = $field;
            }
        }

        return $roles;
    }

    /**
     * Where a class sits in the display order.
     *
     * @param string $class
     */
    protected function classRank(string $class): int {
        if ($class === self::UNCLASSIFIED) {
            return 1000;
        }
        $index = array_search($class, self::CLASS_ORDER, true);

        return $index === false ? 500 : $index;
    }
}
