<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Notification;
use App\Models\User;
use App\Services\EmployeeContractExpiry;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckExpiringEmployeeContracts extends Command
{
    protected $signature = 'hr:check-expiring-contracts {--dry-run : Preview the number of reminders without saving notifications}';

    protected $description = 'Notify HR Managers and Directors about employee contracts nearing or past expiry';

    public function handle(EmployeeContractExpiry $expiry): int
    {
        if (!Schema::hasTable('employee_contract_reminders')) {
            $this->error('Run php artisan migrate before checking employee contract reminders.');
            return self::FAILURE;
        }

        $recipients = User::where('block', 0)->whereHas('roles', fn ($query) => $query->whereIn('name', ['hr_manager', 'directors']))->get();
        if ($recipients->isEmpty()) {
            $this->warn('No unblocked HR Manager or Directors users were found. No reminders created.');
            return self::SUCCESS;
        }

        $today = CarbonImmutable::today('Africa/Nairobi');
        $count = 0;
        $contracts = Contract::with('employee')->where('current_status_id', 5)
            ->whereHas('employee', fn ($query) => $query->where('current_status_id', 1))
            ->whereDate('date_start', '<=', $today->toDateString())
            ->whereDate('date_end', '<=', $today->addDays(30)->toDateString())
            // A renewed contract that has already started supersedes an older one.
            ->whereNotExists(function ($query) use ($today) {
                $query->selectRaw('1')->from('sys_hr_employee_contracts as newer')
                    ->whereColumn('newer.staff_id', 'sys_hr_employee_contracts.staff_id')
                    ->whereColumn('newer.date_start', '>', 'sys_hr_employee_contracts.date_start')
                    ->where('newer.current_status_id', 5)
                    ->whereDate('newer.date_start', '<=', $today->toDateString());
            });

        foreach ($contracts->lazyById(100) as $contract) {
            $tracking = $expiry->tracking($contract);
            if (!in_array($tracking['status'], ['expiring_soon', 'expired'], true)) continue;
            $days = $tracking['days_remaining'];
            $threshold = $expiry->reminder($days);
            $date = $contract->date_end->format('Y-m-d');
            foreach ($recipients as $user) {
                $key = ['contract_id' => $contract->id, 'expiry_date' => $date, 'days_before' => $threshold, 'user_id' => $user->id];
                if ($this->option('dry-run')) {
                    if (!DB::table('employee_contract_reminders')->where($key)->exists()) $count++;
                    continue;
                }
                $count += DB::transaction(function () use ($key, $contract, $user, $days, $date) {
                    if (!DB::table('employee_contract_reminders')->insertOrIgnore($key + ['created_at' => now()])) return 0;
                    $unit = abs($days) === 1 ? 'day' : 'days';
                    $timing = $days < 0 ? 'expired ' . abs($days) . " {$unit} ago" : ($days === 0 ? 'expires today' : "expires in {$days} {$unit}");
                    Notification::create([
                        'type' => 'EmployeeContractExpiry',
                        'notifiable_type' => $user->getMorphClass(),
                        'notifiable_id' => $user->id,
                        'data' => ['contract_id' => $contract->id, 'staff_id' => $contract->staff_id, 'expiry_date' => $date, 'days_remaining' => $days],
                        'title' => $days < 0 ? 'Employee contract expired' : 'Employee contract expiring',
                        'message' => "Employment contract for {$contract->employee->full_names} {$timing} (end date: {$date}).",
                        'action_url' => route('contract.index', ['contract_id' => $contract->id]),
                    ]);
                    return 1;
                });
            }
        }
        $this->info($this->option('dry-run') ? "Would create {$count} reminder(s). No changes made." : "Created {$count} employee contract reminder(s).");
        return self::SUCCESS;
    }
}
