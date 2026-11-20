<?php

namespace App\Services;

use App\Models\AuditLogs;
use App\Models\Bank;
use App\Models\ContractChange;
use App\Models\Department;
use App\Models\EmployeeContract;
use App\Models\HelbDeduction;
use App\Models\PayrollElement;
use App\Models\PayrollPeriod;
use App\Models\PayrollPeriodItem;
use App\Models\Position;
use App\Models\ProratedDeduction;
use App\Models\Staff;
use App\Models\TaxBand;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Everything that has changed since the last payroll and could change what people are paid, so whoever runs or approves the
 * payroll can decide with the facts in front of them: contract and salary changes, staff records, loans and deductions, and
 * the company-wide settings, each with who changed it and when. It also lists what else feeds this month's pay (one-time
 * items and sick leave). It only informs; it never blocks a run.
 *
 * The result holds plain text and numbers only, so it can be kept with the run and shown again to the accountant.
 */
class PayrollChangeFeed
{
    /** Most rows shown for one group; the group still says how many there are. */
    private const ROW_LIMIT = 60;

    private const ACTIVE = 5;

    /** @var array<string, Collection> lookups loaded once per build */
    private array $lookups = [];

    public function __construct(private PayrollPreRunCheck $check)
    {
    }

    /**
     * @return array{period: string, since: array{label: string, date: string}, taken_at: string, changes_total: int, high_total: int, groups: array<int, array>, warnings: array<int, string>}
     */
    public function forPeriod(PayrollPeriod $period): array
    {
        $this->lookups = [];
        $since = $this->check->since($period);
        $from = $since['date'];
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $warnings = [];

        // People who have ever had a contract: a change to anyone else cannot touch a payroll
        $withContract = array_flip(EmployeeContract::query()->whereNotNull('staff_id')->pluck('staff_id')->map(fn ($id) => (int) $id)->all());

        $safely = function (string $label, callable $build) use (&$warnings): Collection {
            try {
                return collect($build());
            } catch (\Throwable $e) {
                Log::warning("Payroll change list: could not read {$label}: " . $e->getMessage());
                $warnings[] = $label;

                return collect();
            }
        };

        $changeGroups = [
            ['contracts', 'Contracts and salaries', 'Pay is worked out from the contract, so check these first.', $safely('contracts', fn () => $this->contractRows($from))],
            ['staff', 'Staff records that affect pay', 'Bank details feed the payment file; the disability flag and status decide who is paid and how.', $safely('staff records', fn () => $this->staffRows($from, $withContract))],
            ['deductions', 'Loans and deductions', 'HELB loans and prorated deductions taken from pay.', $safely('loans and deductions', fn () => $this->deductionRows($from, $withContract))],
            ['settings', 'Company-wide settings', 'These apply to everyone in the run.', $safely('company-wide settings', fn () => $this->settingRows($from))],
        ];
        $includedGroups = [
            ['one_time', 'One-time items entered for this month', 'Bonuses, arrears and deductions for this month only.', $safely('one-time items', fn () => $this->oneTimeRows($period))],
            ['sick_leave', 'Sick leave that reduces pay this month', 'Unpaid days are taken off pay for these staff.', $safely('sick leave', fn () => $this->sickLeaveRows($start, $end, $withContract))],
        ];

        $users = User::whereIn('id', collect($changeGroups)->merge($includedGroups)->flatMap(fn ($group) => $group[3]->pluck('by'))->filter()->unique()->values())->pluck('name', 'id');

        $groups = [];
        foreach ([['change', $changeGroups], ['included', $includedGroups]] as [$kind, $definitions]) {
            foreach ($definitions as [$key, $title, $hint, $rows]) {
                if ($rows->isEmpty()) {
                    continue;
                }
                $sorted = $rows->sortByDesc(fn ($row) => $row['at']?->getTimestamp() ?? 0)->values();
                $groups[] = [
                    'key' => $key,
                    'kind' => $kind,
                    'title' => $title,
                    'hint' => $hint,
                    'count' => $sorted->count(),
                    'high' => $sorted->where('high', true)->count(),
                    'more' => max(0, $sorted->count() - self::ROW_LIMIT),
                    'rows' => $sorted->take(self::ROW_LIMIT)->map(fn ($row) => [
                        'who' => $row['who'],
                        'what' => $row['what'],
                        'detail' => $row['detail'],
                        'by' => $row['by'] ? ($users[$row['by']] ?? 'Unknown user') : null,
                        'at' => $row['at']?->format('d M Y, H:i'),
                        'high' => $row['high'],
                    ])->all(),
                ];
            }
        }

        $changed = collect($groups)->where('kind', 'change');

        return [
            'period' => $period->start_date->format('F Y'),
            'since' => ['label' => $since['label'], 'date' => $from->format('d M Y, H:i')],
            'taken_at' => now()->format('d M Y, H:i'),
            'changes_total' => (int) $changed->sum('count'),
            'high_total' => (int) $changed->sum('high'),
            'groups' => $groups,
            'warnings' => $warnings,
        ];
    }

    // ── contracts and salaries ───────────────────────────────────────────

    private function contractRows(Carbon $from): Collection
    {
        $names = $this->staffNames();
        $rows = collect();

        $logged = ContractChange::with('element')->where('created_at', '>', $from)->orderBy('created_at')->get();
        foreach ($logged as $change) {
            $who = $names[$change->staff_id] ?? null;
            if ($who === null) {
                continue;
            }
            [$what, $detail, $high] = match ($change->field) {
                ContractChange::CONTRACT_CREATED => ['New contract', 'Starting salary ' . $this->money($change->new_value), false],
                ContractChange::BASIC_SALARY => ['Basic salary', $this->money($change->old_value) . ' to ' . $this->money($change->new_value), true],
                default => ['Recurring item', $this->check->describeElementChange($change), false],
            };
            $rows->push($this->row($who, $what, $detail, $change->changed_by, $change->created_at, $high));
        }

        // The contract's own audit trail catches what the change log does not: dates, position, status and removal
        $loggedSalaries = $logged->where('field', ContractChange::BASIC_SALARY)->map(fn ($c) => $c->contract_id . '|' . $this->money($c->new_value))->all();
        $contractStaff = EmployeeContract::query()->pluck('staff_id', 'id');

        foreach ($this->audits('sys_hr_employee_contracts', $from) as $audit) {
            $old = $audit->old_data ?? [];
            $new = $audit->new_data ?? [];
            $staffId = (int) ($contractStaff[$audit->record_id] ?? $old['staff_id'] ?? $new['staff_id'] ?? 0);
            $who = $names[$staffId] ?? null;
            if ($who === null) {
                continue;
            }

            if ($audit->operation === 'delete') {
                $rows->push($this->row($who, 'Contract removed', 'Basic salary was ' . $this->money($old['basic_salary'] ?? 0), $audit->user_id, $audit->created_at, true));
                continue;
            }
            if ($audit->operation !== 'update') {
                continue;
            }

            foreach (['date_start' => 'Contract start date', 'date_end' => 'Contract end date'] as $field => $label) {
                if (array_key_exists($field, $new)) {
                    $rows->push($this->row($who, $label, $this->date($old[$field] ?? null) . ' to ' . $this->date($new[$field]), $audit->user_id, $audit->created_at, $field === 'date_end'));
                }
            }
            if (array_key_exists('current_status_id', $new)) {
                $label = fn ($id) => (int) $id === self::ACTIVE ? 'Active' : 'Not active';
                $rows->push($this->row($who, 'Contract status', $label($old['current_status_id'] ?? null) . ' to ' . $label($new['current_status_id']), $audit->user_id, $audit->created_at, true));
            }
            if (array_key_exists('position_id', $new)) {
                $positions = $this->lookup('positions', fn () => Position::pluck('position_name', 'id'));
                $rows->push($this->row($who, 'Position on contract', ($positions[$old['position_id'] ?? null] ?? 'none') . ' to ' . ($positions[$new['position_id']] ?? 'none'), $audit->user_id, $audit->created_at, false));
            }
            if (array_key_exists('basic_salary', $new) && !in_array($audit->record_id . '|' . $this->money($new['basic_salary']), $loggedSalaries, true)) {
                $rows->push($this->row($who, 'Basic salary', $this->money($old['basic_salary'] ?? 0) . ' to ' . $this->money($new['basic_salary']), $audit->user_id, $audit->created_at, true));
            }
        }

        return $rows;
    }

    // ── staff records ────────────────────────────────────────────────────

    private function staffRows(Carbon $from, array $withContract): Collection
    {
        $names = $this->staffNames();
        $rows = collect();

        foreach ($this->audits('sys_staff', $from) as $audit) {
            if ($audit->operation !== 'update' || !isset($withContract[(int) $audit->record_id]) || !isset($names[$audit->record_id])) {
                continue;
            }
            $old = $audit->old_data ?? [];
            $new = $audit->new_data ?? [];
            $who = $names[$audit->record_id];
            $add = fn (string $what, string $detail, bool $high) => $rows->push($this->row($who, $what, $detail, $audit->user_id, $audit->created_at, $high));

            if (array_key_exists('bank_name', $new)) {
                $banks = $this->lookup('banks', fn () => Bank::pluck('bank_name', 'id'));
                $add('Bank', ($banks[$old['bank_name'] ?? null] ?? 'none') . ' to ' . ($banks[$new['bank_name']] ?? 'none'), true);
            }
            if (array_key_exists('account_number', $new)) {
                // Only the last four digits: enough to see it changed without spreading the number around
                $add('Bank account number', $this->mask($old['account_number'] ?? null) . ' to ' . $this->mask($new['account_number']), true);
            }
            if (array_key_exists('disability', $new)) {
                $add('Disability (PAYE relief)', ucfirst((string) ($old['disability'] ?? 'no')) . ' to ' . ucfirst((string) $new['disability']), true);
            }
            if (array_key_exists('current_status_id', $new)) {
                $add('Staff status', (Staff::STATUSES[$old['current_status_id'] ?? null] ?? 'Unknown') . ' to ' . (Staff::STATUSES[$new['current_status_id']] ?? 'Unknown'), true);
            }
            if (array_key_exists('position_id', $new)) {
                $positions = $this->lookup('positions', fn () => Position::pluck('position_name', 'id'));
                $add('Position', ($positions[$old['position_id'] ?? null] ?? 'none') . ' to ' . ($positions[$new['position_id']] ?? 'none'), false);
            }
            if (array_key_exists('department_id', $new)) {
                $departments = $this->lookup('departments', fn () => Department::pluck('department_name', 'id'));
                $add('Department', ($departments[$old['department_id'] ?? null] ?? 'none') . ' to ' . ($departments[$new['department_id']] ?? 'none'), false);
            }
        }

        return $rows;
    }

    // ── loans and deductions ─────────────────────────────────────────────

    private function deductionRows(Carbon $from, array $withContract): Collection
    {
        $names = $this->staffNames();
        $rows = collect();

        // The loan's balance and cleared flag change on their own each time a run is approved, so only the terms HR sets are listed
        $loanTables = [
            ['sys_helb_deductions', 'HELB loan', HelbDeduction::pluck('staff_id', 'id'), ['monthly_deduction' => 'HELB monthly deduction', 'start_deduction_date' => 'HELB start date']],
            ['sys_hr_employee_amortized_deductions', 'Deduction plan', ProratedDeduction::pluck('staff_id', 'id'), ['monthly_deduction' => 'Monthly deduction', 'total_amount' => 'Total to repay', 'deduction_date' => 'Deduction start date']],
        ];

        foreach ($loanTables as [$table, $label, $owners, $fields]) {
            foreach ($this->audits($table, $from) as $audit) {
                $old = $audit->old_data ?? [];
                $new = $audit->new_data ?? [];
                $staffId = (int) ($owners[$audit->record_id] ?? $old['staff_id'] ?? $new['staff_id'] ?? 0);
                $who = $names[$staffId] ?? null;
                if ($who === null || !isset($withContract[$staffId])) {
                    continue;
                }
                $add = fn (string $what, string $detail) => $rows->push($this->row($who, $what, $detail, $audit->user_id, $audit->created_at, false));

                if ($audit->operation === 'create') {
                    $add("{$label} added", 'Monthly deduction ' . $this->money($new['monthly_deduction'] ?? 0));
                } elseif ($audit->operation === 'delete') {
                    $add("{$label} removed", 'Monthly deduction was ' . $this->money($old['monthly_deduction'] ?? 0));
                } elseif ($audit->operation === 'update') {
                    foreach ($fields as $field => $text) {
                        if (array_key_exists($field, $new)) {
                            $isDate = str_contains($field, 'date');
                            $format = fn ($value) => $isDate ? $this->date($value) : $this->money($value);
                            $add($text, $format($old[$field] ?? null) . ' to ' . $format($new[$field]));
                        }
                    }
                    if (array_key_exists('suspended', $new)) {
                        $add($label, (int) $new['suspended'] === 1 ? 'Suspended, so nothing is taken' : 'Resumed, so it is taken again');
                    }
                }
            }
        }

        return $rows;
    }

    // ── company-wide settings ────────────────────────────────────────────

    private function settingRows(Carbon $from): Collection
    {
        $rows = collect();

        // Payroll items (allowances and deductions defined for the company)
        $elementNames = PayrollElement::pluck('element_name', 'id');
        $elementFields = ['element_name' => 'Name', 'element_type' => 'Kind', 'amount' => 'Amount', 'recurrence' => 'How often', 'applies_to' => 'Applies to', 'formula_id' => 'Calculation', 'current_status_id' => 'Status'];
        foreach ($this->audits('sys_hr_payroll_elements', $from) as $audit) {
            $old = $audit->old_data ?? [];
            $new = $audit->new_data ?? [];
            $name = $elementNames[$audit->record_id] ?? $old['element_name'] ?? $new['element_name'] ?? 'Payroll item';
            if ($audit->operation === 'create') {
                $rows->push($this->row($name, 'Payroll item added', $this->elementValue('element_type', $new['element_type'] ?? null) . ', ' . ($new['recurrence'] ?? 'recurring') . ', ' . $this->money($new['amount'] ?? 0), $audit->user_id, $audit->created_at, true));
            } elseif ($audit->operation === 'delete') {
                $rows->push($this->row($name, 'Payroll item removed', $this->elementValue('element_type', $old['element_type'] ?? null), $audit->user_id, $audit->created_at, true));
            } elseif ($audit->operation === 'update') {
                $parts = [];
                foreach ($elementFields as $field => $label) {
                    if (array_key_exists($field, $new)) {
                        $parts[] = $label . ': ' . $this->elementValue($field, $old[$field] ?? null) . ' to ' . $this->elementValue($field, $new[$field]);
                    }
                }
                if ($parts) {
                    $rows->push($this->row($name, 'Payroll item changed', implode('; ', $parts), $audit->user_id, $audit->created_at, true));
                }
            }
        }

        // Tax bands (PAYE)
        $bands = TaxBand::query()->get()->keyBy('id');
        foreach ($this->audits('um_tax_bands', $from) as $audit) {
            $old = $audit->old_data ?? [];
            $new = $audit->new_data ?? [];
            $band = $bands[$audit->record_id] ?? null;
            $range = fn ($data) => number_format((float) ($data['range_from'] ?? 0), 0) . ' to ' . (($data['range_to'] ?? null) === null ? 'no limit' : number_format((float) $data['range_to'], 0));
            if ($audit->operation === 'create') {
                $rows->push($this->row('Income tax (PAYE)', 'Tax band added', $range($new) . ' at ' . ($new['tax_rate'] ?? '?') . '%', $audit->user_id, $audit->created_at, true));
            } elseif ($audit->operation === 'delete') {
                $rows->push($this->row('Income tax (PAYE)', 'Tax band removed', $range($old) . ' at ' . ($old['tax_rate'] ?? '?') . '%', $audit->user_id, $audit->created_at, true));
            } elseif ($audit->operation === 'update') {
                $parts = [];
                foreach (['range_from' => 'From', 'range_to' => 'To', 'tax_rate' => 'Rate %'] as $field => $label) {
                    if (array_key_exists($field, $new)) {
                        $parts[] = "{$label}: " . ($old[$field] ?? 'none') . ' to ' . ($new[$field] ?? 'none');
                    }
                }
                if ($parts) {
                    $rows->push($this->row('Income tax (PAYE)', 'Tax band ' . ($band ? number_format((float) $band->range_from, 0) . ' to ' . ($band->range_to === null ? 'no limit' : number_format((float) $band->range_to, 0)) : '#' . $audit->record_id) . ' changed', implode('; ', $parts), $audit->user_id, $audit->created_at, true));
                }
            }
        }

        // NSSF rates
        if (Schema::hasTable('sys_hr_nssf_rates')) {
            $nssfFields = ['from_date' => 'Applies from', 'employee_perc' => 'Employee %', 'employer_perc' => 'Employer %', 'maximum_contribution' => 'Maximum', 'lower_earnings_limit' => 'Lower limit', 'upper_earnings_limit' => 'Upper limit'];
            foreach ($this->audits('sys_hr_nssf_rates', $from) as $audit) {
                $old = $audit->old_data ?? [];
                $new = $audit->new_data ?? [];
                if ($audit->operation === 'create') {
                    $rows->push($this->row('NSSF', 'Rate added', 'Applies from ' . $this->date($new['from_date'] ?? null), $audit->user_id, $audit->created_at, true));
                } elseif ($audit->operation === 'delete') {
                    $rows->push($this->row('NSSF', 'Rate removed', 'Applied from ' . $this->date($old['from_date'] ?? null), $audit->user_id, $audit->created_at, true));
                } elseif ($audit->operation === 'update') {
                    $parts = [];
                    foreach ($nssfFields as $field => $label) {
                        if (array_key_exists($field, $new)) {
                            $isDate = $field === 'from_date';
                            $parts[] = "{$label}: " . ($isDate ? $this->date($old[$field] ?? null) . ' to ' . $this->date($new[$field]) : ($old[$field] ?? 'none') . ' to ' . $new[$field]);
                        }
                    }
                    if ($parts) {
                        $rows->push($this->row('NSSF', 'Rates changed', implode('; ', $parts), $audit->user_id, $audit->created_at, true));
                    }
                }
            }
        }

        return $rows;
    }

    // ── what else feeds this month ───────────────────────────────────────

    private function oneTimeRows(PayrollPeriod $period): Collection
    {
        return PayrollPeriodItem::with(['element', 'staff'])->where('period_id', $period->id)->orderBy('id')->get()->map(function ($item) {
            $detail = number_format((float) $item->amount, 2) . ($item->note ? ' (' . $item->note . ')' : '');

            return $this->row($item->staff?->full_names ?? 'All staff', $item->element?->element_name ?? 'Item', $detail, $item->created_by, $item->created_at, false);
        });
    }

    private function sickLeaveRows(string $start, string $end, array $withContract): Collection
    {
        $names = $this->staffNames();
        $taken = DB::table('sys_hr_leave_requests_234_repeat as leave_day')
            ->join('sys_hr_leave_requests as leave_request', 'leave_request.id', '=', 'leave_day.parent_id')
            ->join('sys_hr_leave_types as leave_type', 'leave_type.id', '=', 'leave_request.leave_type_id')
            ->where('leave_request.current_status_id', self::ACTIVE)
            ->where('leave_day.current_status_id', self::ACTIVE)
            ->whereBetween(DB::raw('DATE(leave_day.leave_date)'), [$start, $end])
            ->whereRaw('LOWER(leave_type.type_name) LIKE ?', ['%sick%'])
            ->groupBy('leave_request.staff_id')
            ->selectRaw('leave_request.staff_id as staff_id, SUM(COALESCE(leave_day.day_fraction, 1)) as days, SUM(COALESCE(leave_day.day_fraction, 1) * (100 - COALESCE(leave_day.pay_rate, 100)) / 100.0) as unpaid')
            ->get();

        return $taken->filter(fn ($row) => (float) $row->unpaid > 0 && isset($withContract[(int) $row->staff_id]) && isset($names[$row->staff_id]))
            ->map(fn ($row) => $this->row($names[$row->staff_id], 'Unpaid sick leave', $this->days((float) $row->unpaid) . ' unpaid of ' . $this->days((float) $row->days) . ' sick leave', null, null, false))
            ->values();
    }

    // ── small helpers ────────────────────────────────────────────────────

    private function audits(string $table, Carbon $from): Collection
    {
        return AuditLogs::where('table_name', $table)->where('created_at', '>', $from)->orderBy('id')->get();
    }

    private function staffNames(): Collection
    {
        return $this->lookup('staff', fn () => Staff::pluck('full_names', 'id'));
    }

    /** A name lookup loaded the first time it is needed. */
    private function lookup(string $key, callable $load): Collection
    {
        return $this->lookups[$key] ??= $load();
    }

    private function row(string $who, string $what, string $detail, $by, ?Carbon $at, bool $high): array
    {
        return ['who' => $who, 'what' => $what, 'detail' => $detail, 'by' => $by ? (int) $by : null, 'at' => $at, 'high' => $high];
    }

    private function money($value): string
    {
        return number_format((float) $value, 2);
    }

    private function days(float $days): string
    {
        return rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.') . ($days == 1.0 ? ' day' : ' days');
    }

    private function date($value): string
    {
        if ($value === null || $value === '') {
            return 'none';
        }

        try {
            return Carbon::parse($value)->format('d M Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function mask($value): string
    {
        $digits = preg_replace('/\s+/', '', (string) $value);

        return $digits === '' ? 'none' : \App\Support\MaskedNumber::tail($digits);
    }

    private function elementValue(string $field, $value): string
    {
        if ($value === null || $value === '') {
            return 'none';
        }

        return match ($field) {
            'element_type' => (string) $value === '2' ? 'Earnings' : 'Deduction',
            'amount' => $this->money($value),
            'current_status_id' => (int) $value === self::ACTIVE ? 'Active' : 'Not active',
            default => (string) $value,
        };
    }
}
