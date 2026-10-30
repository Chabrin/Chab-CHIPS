<?php

namespace App\Services;

use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class EmployeeContractReport
{
    /** One eligibility rule shared by the report and the scheduled summary. */
    public function currentContracts(): Builder
    {
        $today = CarbonImmutable::today('Africa/Nairobi')->toDateString();
        return Contract::query()->where('current_status_id', 5)
            ->whereHas('employee', fn ($query) => $query->whereIn('current_status_id', [1, 4]))
            ->whereDate('date_start', '<=', $today)->whereNotNull('date_end')
            ->whereRaw('DATE(date_end) >= DATE(date_start)')
            ->whereNotExists(function ($query) use ($today) {
                $query->selectRaw('1')->from('sys_hr_employee_contracts as newer')
                    ->whereColumn('newer.staff_id', 'sys_hr_employee_contracts.staff_id')
                    ->whereColumn('newer.date_start', '>', 'sys_hr_employee_contracts.date_start')
                    ->where('newer.current_status_id', 5)->whereDate('newer.date_start', '<=', $today)
                    ->whereNotNull('newer.date_end')->whereRaw('DATE(newer.date_end) >= DATE(newer.date_start)');
            });
    }

    public function summary(): array
    {
        $today = CarbonImmutable::today('Africa/Nairobi');
        $query = $this->currentContracts();
        $counts = [];
        foreach ([7, 14, 30] as $days) {
            $counts['due_' . $days] = (clone $query)->whereDate('date_end', '>=', $today->toDateString())
                ->whereDate('date_end', '<=', $today->addDays($days)->toDateString())->count();
        }
        $counts['expired'] = (clone $query)->whereDate('date_end', '<', $today->toDateString())->count();
        $counts['expired_30'] = (clone $query)->whereDate('date_end', '<', $today->toDateString())
            ->whereDate('date_end', '>=', $today->subDays(30)->toDateString())->count();
        return $counts + ['as_of' => $today->toDateString(), 'through' => $today->addDays(30)->toDateString(),
            'generated_at' => CarbonImmutable::now('Africa/Nairobi')->format('Y-m-d H:i:s'), 'timezone' => 'Africa/Nairobi'];
    }

    public function approvalLabel(?int $status): string
    {
        return match ($status) { 1 => 'Pending', 5 => 'Approved', 24 => 'Partially approved', 41 => 'Rejected', default => 'Other (' . ($status ?? 'unknown') . ')' };
    }

    public function dashboard(): array
    {
        $summary = $this->summary();
        $upcoming = $this->currentContracts()->with('employee.department')
            ->whereDate('date_end', '>=', $summary['as_of'])
            ->whereDate('date_end', '<=', $summary['through'])
            ->orderBy('date_end')->orderBy('id')->limit(5)->get()
            ->map(fn (Contract $contract) => [
                'id' => $contract->id,
                'employee' => $contract->employee->full_names,
                'staff_number' => $contract->employee->staff_number ?? $contract->staff_number,
                'department' => $contract->employee->department?->department_name,
                'end_date' => $contract->date_end->format('Y-m-d'),
                'days_remaining' => app(EmployeeContractExpiry::class)->tracking($contract)['days_remaining'],
                'report_url' => route('contract.index', ['contract_id' => $contract->id, 'window' => '30']),
            ])->values();

        return ['summary' => $summary, 'upcoming' => $upcoming];
    }
}
