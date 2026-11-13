<?php

namespace App\Services;

use App\Models\ContractChange;
use App\Models\ContractElement;
use App\Models\ContractElementExclusion;
use App\Models\EmployeeContract;
use App\Models\PayrollPeriod;
use App\Models\PayrollPeriodItem;
use App\Models\PayrollRun;
use App\Models\PayrollSummary;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * What to look at before a payroll period is run: staff who would be left out, odd salaries, what changed since the
 * last approved run and anything on a contract that the run will ignore. It only reports; it never blocks a run.
 */
class PayrollPreRunCheck
{
    /** Longest list shown for one finding; the count still reflects every match. */
    private const LIST_LIMIT = 100;

    /**
     * The full check for one period.
     *
     * @return array{locked: bool, since: array, summary: array, sections: array}
     */
    public function forPeriod(PayrollPeriod $period): array
    {
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $periodDays = $period->start_date->diffInDays($period->end_date) + 1;

        $contracts = $this->contractsInRun($start, $end);
        $inRun = $contracts->unique('staff_id')->values();
        $staffIds = $inRun->pluck('staff_id');
        $since = $this->since($period);

        $sections = [];
        $add = function (string $key, string $title, string $level, string $hint, Collection $rows) use (&$sections) {
            if ($rows->isEmpty()) {
                return;
            }
            $sections[] = [
                'key' => $key,
                'title' => $title,
                'level' => $level,
                'hint' => $hint,
                'count' => $rows->count(),
                'rows' => $rows->take(self::LIST_LIMIT)->values()->all(),
            ];
        };

        $add('no_contract', 'Active staff with no current contract', 'warning',
            'They are not in this run. Add a contract if they should be paid.',
            Staff::whereIn('current_status_id', [1, 4])->whereNotIn('id', $staffIds)->orderBy('full_names')
                ->get(['id', 'full_names', 'staff_number'])
                ->map(fn ($staff) => ['name' => $staff->full_names, 'detail' => (string) $staff->staff_number]));

        $add('zero_salary', 'Contracts with no basic salary', 'warning',
            'These staff would be paid nothing but still charged the statutory minimums.',
            $inRun->filter(fn ($contract) => (float) $contract->basic_salary <= 0)
                ->map(fn ($contract) => ['name' => $contract->staff->full_names, 'detail' => 'Basic salary is 0']));

        $duplicates = $contracts->groupBy('staff_id')->filter(fn ($group) => $group->count() > 1);
        $add('duplicate_contracts', 'Staff with more than one current contract', 'warning',
            'Only the latest contract is paid. End the old one to stop this warning.',
            $duplicates->map(fn ($group) => ['name' => $group->first()->staff->full_names, 'detail' => $group->count() . ' contracts']));

        $add('partial_month', 'Contracts that do not cover the whole period', 'info',
            'Pay is pro-rated for the days the contract is active.',
            $inRun->map(function ($contract) use ($start, $end, $periodDays) {
                $days = $this->activeDays($contract, $start, $end);

                return $days < $periodDays
                    ? ['name' => $contract->staff->full_names, 'detail' => "Paid for {$days} of {$periodDays} days"]
                    : null;
            })->filter());

        $changes = $this->changesSince($since['date'], $staffIds);

        $add('new_contracts', 'New contracts', 'info', 'Started since ' . $since['label'] . '.',
            $changes->where('field', ContractChange::CONTRACT_CREATED)
                ->map(fn ($change) => ['name' => $change->staff->full_names, 'detail' => 'Starting salary ' . number_format((float) $change->new_value, 2)]));

        $add('salary_changes', 'Salary changes', 'info', 'Changed since ' . $since['label'] . '.',
            $changes->where('field', ContractChange::BASIC_SALARY)
                ->map(fn ($change) => ['name' => $change->staff->full_names, 'detail' => number_format((float) $change->old_value, 2) . ' to ' . number_format((float) $change->new_value, 2)]));

        $add('element_changes', 'Changes to recurring items', 'info', 'Changed since ' . $since['label'] . '.',
            $changes->whereIn('field', [
                ContractChange::ELEMENT_ADDED, ContractChange::ELEMENT_REMOVED, ContractChange::ELEMENT_CHANGED,
                ContractChange::ELEMENT_TERMS, ContractChange::ITEM_EXCLUDED, ContractChange::ITEM_INCLUDED,
            ])->map(fn ($change) => ['name' => $change->staff->full_names, 'detail' => $this->describeElementChange($change)]));

        $add('ignored_rows', 'Items on contracts that the run ignores', 'warning',
            'One-time items and a Basic Salary row never repeat from a contract. Enter one-time items on this page instead.',
            $this->ignoredRows($inRun)->map(fn ($row) => ['name' => $row['element'], 'detail' => $row['staff']]));

        $termRows = $this->contractTermRows($inRun);
        $add('once_only_this_period', 'Once-only items taken in this run', 'info',
            'Each is taken in this period only and will not appear in the next one.',
            $termRows->filter(fn ($entry) => $entry['row']->isOnce() && $entry['row']->appliesInPeriod($start))
                ->map(fn ($entry) => ['name' => $entry['staff'], 'detail' => $entry['row']->payrollElement->element_name . ' ' . number_format((float) $entry['row']->amount, 2)]));

        $add('once_only_never', 'Once-only items the run will never take', 'warning',
            'The month chosen is outside the contract, or no month was chosen. Change it on the contract.',
            $termRows->filter(fn ($entry) => $entry['row']->isOnce() && !$this->onceMonthInsideContract($entry['row'], $entry['contract']))
                ->map(fn ($entry) => ['name' => $entry['staff'], 'detail' => $entry['row']->payrollElement->element_name . ' (' . ($entry['row']->once_month?->format('M Y') ?? 'no month') . ')']));

        $add('left_out_items', 'Company-wide items left out for one person', 'info',
            'Set on the contract with a reason. Everyone else still has these items.',
            $this->leftOutItems($inRun)->map(fn ($entry) => ['name' => $entry['staff'], 'detail' => $entry['item'] . ' - ' . $entry['reason']]));

        $items = PayrollPeriodItem::with(['element', 'staff'])->where('period_id', $period->id)->get();
        $add('unapplied_items', 'One-time items that will not be applied', 'warning',
            'These staff have no current contract in this period, so the run will skip their item.',
            $items->whereNotNull('staff_id')->reject(fn ($item) => $staffIds->contains($item->staff_id))
                ->map(fn ($item) => ['name' => $item->staff?->full_names ?? 'Unknown staff', 'detail' => ($item->element?->element_name ?? 'Item') . ' ' . number_format((float) $item->amount, 2)]));

        return [
            'locked' => PayrollSummary::where('period_id', $period->id)->exists(),
            'since' => $since,
            'summary' => [
                'staff_in_run' => $inRun->count(),
                'items' => $items->count(),
                'one_time_allowances' => round((float) $items->filter(fn ($item) => $item->element?->element_type == '2')->sum('amount'), 2),
                'one_time_deductions' => round((float) $items->filter(fn ($item) => $item->element?->element_type != '2')->sum('amount'), 2),
            ],
            'sections' => $sections,
        ];
    }

    /**
     * Warnings the run itself reports for the contracts it is about to pay.
     *
     * @return string[]
     */
    public function processWarnings(Collection $contracts, int $periodId): array
    {
        $warnings = [];

        foreach ($this->ignoredRows($contracts) as $row) {
            $warnings[] = "{$row['element']} is on the contract of {$row['staff']}, but the run ignores it. Enter one-time items on Monthly Payroll Items.";
        }

        $staffIds = $contracts->pluck('staff_id');
        $unapplied = PayrollPeriodItem::with(['element', 'staff'])->where('period_id', $periodId)
            ->whereNotNull('staff_id')->get()->reject(fn ($item) => $staffIds->contains($item->staff_id));
        foreach ($unapplied as $item) {
            $warnings[] = ($item->element?->element_name ?? 'A one-time item') . ' for ' . ($item->staff?->full_names ?? 'a staff member') . ' was not applied because they have no current contract in this period.';
        }

        return $warnings;
    }

    /**
     * Contract rows the run ignores (one-time elements and a Basic Salary row), one entry per element with the
     * staff who hold it.
     *
     * @return Collection<int, array{element: string, staff: string}>
     */
    public function ignoredRows(Collection $contracts): Collection
    {
        if ($contracts->isEmpty()) {
            return collect();
        }

        $staffByContract = $contracts->mapWithKeys(fn ($contract) => [$contract->id => $contract->staff?->full_names ?? 'Unknown staff']);

        return ContractElement::with('payrollElement')
            ->whereIn('parent_id', $staffByContract->keys())
            ->get()
            ->filter(fn ($row) => $row->payrollElement
                && ($row->payrollElement->isOneTime() || strtolower((string) $row->payrollElement->element_key) === 'basic_salary'))
            ->groupBy('element_id')
            ->map(function ($rows) use ($staffByContract) {
                $names = $rows->map(fn ($row) => $staffByContract[$row->parent_id])->unique()->values();
                $shown = $names->take(6)->implode(', ');

                return [
                    'element' => $rows->first()->payrollElement->element_name,
                    'staff' => $shown . ($names->count() > 6 ? ' and ' . ($names->count() - 6) . ' more' : ''),
                ];
            })
            ->values();
    }

    /**
     * The recurring rows on the contracts being paid, each with its contract and the staff member's name.
     *
     * @return Collection<int, array{row: ContractElement, contract: EmployeeContract, staff: string}>
     */
    private function contractTermRows(Collection $contracts): Collection
    {
        if ($contracts->isEmpty()) {
            return collect();
        }

        $byId = $contracts->keyBy('id');

        return ContractElement::with('payrollElement')->whereIn('parent_id', $byId->keys())->get()
            ->filter(fn ($row) => $row->payrollElement?->canBeOnContract())
            ->map(fn ($row) => [
                'row' => $row,
                'contract' => $byId[$row->parent_id],
                'staff' => $byId[$row->parent_id]->staff?->full_names ?? 'Unknown staff',
            ])
            ->sortBy('staff')
            ->values();
    }

    /**
     * Whether a once-only row names a month the contract runs. Contract dates can change after the row was saved, which would
     * leave the month outside the contract and the item never taken.
     */
    private function onceMonthInsideContract(ContractElement $row, EmployeeContract $contract): bool
    {
        if ($row->once_month === null) {
            return false;
        }

        $month = $row->once_month->format('Y-m');
        $from = $contract->date_start?->format('Y-m');
        $to = $contract->date_end?->format('Y-m');

        return ($from === null || $month >= $from) && ($to === null || $month <= $to);
    }

    /**
     * The company-wide items left out for the people being paid, with the reason given.
     *
     * @return Collection<int, array{staff: string, item: string, reason: string}>
     */
    private function leftOutItems(Collection $contracts): Collection
    {
        if ($contracts->isEmpty()) {
            return collect();
        }

        $byId = $contracts->keyBy('id');

        return ContractElementExclusion::with('element')->whereIn('contract_id', $byId->keys())->get()
            ->map(fn ($left) => [
                'staff' => $byId[$left->contract_id]->staff?->full_names ?? 'Unknown staff',
                'item' => $left->element?->element_name ?? 'Item',
                'reason' => (string) $left->reason,
            ])
            ->sortBy('staff')
            ->values();
    }

    /**
     * Current contracts for a period, newest first, the same way the run picks them.
     */
    private function contractsInRun(string $start, string $end): Collection
    {
        return EmployeeContract::with('staff')->active()->coveringPeriod($start, $end)->get()
            ->filter(fn ($contract) => $contract->staff)
            ->sortByDesc(fn ($contract) => [(string) $contract->date_start?->toDateString(), $contract->id])
            ->values();
    }

    /**
     * Days a contract is active inside the period.
     */
    private function activeDays(EmployeeContract $contract, string $start, string $end): int
    {
        $from = max($start, $contract->date_start?->toDateString() ?? $start);
        $to = min($end, $contract->date_end?->toDateString() ?? $end);

        return $to < $from ? 0 : (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
    }

    /**
     * The point changes are measured from: when the last approved run before this period was processed, or the
     * last 31 days when there is none.
     *
     * @return array{date: Carbon, label: string}
     */
    private function since(PayrollPeriod $period): array
    {
        $previous = PayrollRun::with('period')
            ->where('status', PayrollRun::STATUS_APPROVED)
            ->whereHas('period', fn ($query) => $query->whereDate('end_date', '<', $period->start_date->toDateString()))
            ->orderByDesc('processed_at')
            ->first();

        if ($previous && $previous->processed_at) {
            return [
                'date' => $previous->processed_at,
                'label' => 'the ' . ($previous->period?->start_date?->format('F Y') ?? 'last') . ' payroll was processed',
            ];
        }

        return ['date' => now()->subDays(31), 'label' => 'the last 31 days'];
    }

    /**
     * Logged contract changes for the staff in the run since a point in time.
     */
    private function changesSince(Carbon $since, Collection $staffIds): Collection
    {
        return ContractChange::with(['element', 'staff'])
            ->where('created_at', '>', $since)
            ->whereIn('staff_id', $staffIds)
            ->whereHas('staff')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * A recurring item change in words, for example "Sacco Loan: 1,000.00 to 1,500.00".
     */
    private function describeElementChange(ContractChange $change): string
    {
        $name = $change->element?->element_name ?? 'Item';

        return match ($change->field) {
            ContractChange::ELEMENT_ADDED => "{$name} added at " . number_format((float) $change->new_value, 2),
            ContractChange::ELEMENT_REMOVED => "{$name} removed (was " . number_format((float) $change->old_value, 2) . ')',
            ContractChange::ELEMENT_TERMS => "{$name}: terms changed from {$change->old_value} to {$change->new_value}",
            ContractChange::ITEM_EXCLUDED => "{$name} left out for this person ({$change->new_value})",
            ContractChange::ITEM_INCLUDED => "{$name} put back (was left out: {$change->old_value})",
            default => "{$name}: " . number_format((float) $change->old_value, 2) . ' to ' . number_format((float) $change->new_value, 2),
        };
    }
}
