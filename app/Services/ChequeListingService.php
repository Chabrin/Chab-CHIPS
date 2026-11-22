<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * The list of all cheques: one row for each cheque number on each payment voucher, with its bank, payee, amount, the
 * voucher it was written for, the voucher's status and whether it was printed. A cheque exists because a voucher took a
 * cheque number, so the list is built from the vouchers' lines (old vouchers carry the number as text, new ones also
 * link the cheque leaf). Cheque charge lines are left out of the amount: they are the company's charge, not paid by cheque.
 */
class ChequeListingService
{
    const SORTS = ['date' => 'g.voucher_date', 'cheque' => 'g.cheque_number', 'amount' => 'g.amount', 'payee' => 'g.payee', 'voucher' => 'g.voucher_number'];
    const PER_PAGE = [25, 50, 100, 200];
    const STATUSES = ['Pending', 'Approved', 'Rejected', 'Cancelled', 'Paid'];

    /**
     * One page of cheques, with the totals of everything the filters match.
     *
     * @param array $filters date_from, date_to (voucher date), status, printed (yes or no), bank_account_id, search (cheque no., voucher no. or payee), amount_min, amount_max
     * @param string $sort one of the keys of SORTS
     * @param string $direction asc or desc
     * @param int $page 1 based
     * @param int $perPage one of PER_PAGE
     * @return array rows, total (cheques matched), amount (their sum), page, per_page, pages
     */
    public function page(array $filters, string $sort = 'date', string $direction = 'desc', int $page = 1, int $perPage = 50): array {
        $perPage = in_array($perPage, self::PER_PAGE, true) ? $perPage : 50;
        $page = max(1, $page);
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $orderBy = self::SORTS[$sort] ?? self::SORTS['date'];

        $grouped = $this->grouped($filters);
        $wrapped = DB::query()->fromSub($grouped, 'g');

        $total = (int) (clone $wrapped)->count();
        $amount = round((float) (clone $wrapped)->sum('g.amount'), 2);

        $rows = (clone $wrapped)
            ->orderBy($orderBy, $direction)
            ->orderByDesc('g.voucher_id')
            ->forPage($page, $perPage)
            ->get();

        $accounts = $this->accountLabels($rows->pluck('bank_account_id')->filter()->unique()->all());

        return [
            'rows' => $rows->map(fn ($row) => [
                'voucher_id' => (int) $row->voucher_id,
                'encrypted_voucher_id' => encrypt($row->voucher_id),
                'cheque_number' => (string) $row->cheque_number,
                'bank' => $row->bank_account_id ? ($accounts[$row->bank_account_id] ?? '-') : '-',
                'payee' => (string) ($row->payee ?: '-'),
                'amount' => round((float) $row->amount, 2),
                'lines' => (int) $row->line_count,
                'voucher_number' => (string) $row->voucher_number,
                'voucher_date' => $row->voucher_date ? date('d-M-Y', strtotime((string) $row->voucher_date)) : '-',
                'status' => (string) ($row->voucher_status ?: 'Pending'),
                'printed' => (int) $row->is_printed === 1,
            ])->all(),
            'total' => $total,
            'amount' => $amount,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * The grouped, filtered query: a row for each cheque number of each voucher.
     *
     * @param array $filters
     */
    protected function grouped(array $filters) {
        $grammar = DB::getQueryGrammar();
        $description = $grammar->wrap('l.entry_description');
        $amount = $grammar->wrap('l.amount');
        $bankAccount = 'COALESCE(' . $grammar->wrap('leaf.bank_account_id') . ', ' . $grammar->wrap('l.source_account_id') . ')';
        $amountSum = "SUM(CASE WHEN UPPER({$description}) LIKE 'CHEQUE CHARGE%' THEN 0 ELSE {$amount} END)";

        $query = DB::table('cb_payment_vouchers_lines_new as l')
            ->join('cb_payment_vouchers_headers_new as h', 'h.voucher_number', '=', 'l.voucher_number')
            ->leftJoin('sys_fin_cheque_books_261_repeat as leaf', 'leaf.id', '=', 'l.cheque_leaf_id')
            ->whereNotNull('l.cheque_number')
            ->where('l.cheque_number', '<>', '')
            ->groupBy('h.id', 'h.voucher_number', 'h.voucher_date', 'h.voucher_status', 'h.is_printed', 'h.vendor_name', 'l.cheque_number')
            ->select([
                'h.id as voucher_id', 'h.voucher_number', 'h.voucher_date', 'h.voucher_status', 'h.is_printed', 'l.cheque_number',
                DB::raw('MAX(' . $grammar->wrap('h.vendor_name') . ') as payee'),
                DB::raw("{$amountSum} as amount"),
                DB::raw('COUNT(*) as line_count'),
                DB::raw("MAX({$bankAccount}) as bank_account_id"),
            ]);

        if (!empty($filters['date_from'])) {
            $query->whereDate('h.voucher_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('h.voucher_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['status'])) {
            $query->where('h.voucher_status', $filters['status']);
        }
        if (($filters['printed'] ?? '') === 'yes') {
            $query->where('h.is_printed', 1);
        } elseif (($filters['printed'] ?? '') === 'no') {
            $query->where(fn ($q) => $q->where('h.is_printed', 0)->orWhereNull('h.is_printed'));
        }
        if (!empty($filters['bank_account_id'])) {
            $query->whereRaw($bankAccount . ' = ?', [(int) $filters['bank_account_id']]);
        }
        if (!empty($filters['search'])) {
            $like = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('l.cheque_number', 'like', $like)
                    ->orWhere('h.voucher_number', 'like', $like)
                    ->orWhere('h.vendor_name', 'like', $like)
                    ->orWhere('l.payee_name', 'like', $like)
                    ->orWhere('l.entry_description', 'like', $like);
            });
        }
        if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
            $query->havingRaw("{$amountSum} >= CAST(? AS DECIMAL(18,2))", [(float) $filters['amount_min']]);
        }
        if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
            $query->havingRaw("{$amountSum} <= CAST(? AS DECIMAL(18,2))", [(float) $filters['amount_max']]);
        }

        return $query;
    }

    /**
     * The short bank name of each company bank account (CO-OP - BUSINESS ACCOUNT gives CO-OP).
     *
     * @param array $accountIds
     * @return array account id => label
     */
    protected function accountLabels(array $accountIds): array {
        if (!$accountIds) {
            return [];
        }

        return DB::table('sys_set_bank_accounts as a')
            ->leftJoin('sys_set_banks as b', 'b.id', '=', 'a.bank_id')
            ->whereIn('a.id', $accountIds)
            ->get(['a.id', 'a.account_name', 'b.bank_name'])
            ->mapWithKeys(fn ($row) => [(int) $row->id => VoucherPrintService::bankLabel($row->account_name, $row->bank_name)])
            ->all();
    }
}
