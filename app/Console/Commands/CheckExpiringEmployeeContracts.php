<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Services\EmployeeContractReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckExpiringEmployeeContracts extends Command
{
    protected $signature = 'hr:check-expiring-contracts {--dry-run : Preview the summary without saving notifications}';

    protected $description = 'Update one contract expiry report summary for HR Managers and Directors';

    public function handle(EmployeeContractReport $report): int
    {
        $summary = $report->summary();
        $this->info("Report: {$summary['due_7']} within 7 days; {$summary['due_14']} within 14 days; {$summary['due_30']} within 30 days; {$summary['expired']} already expired.");
        $recipients = User::where('block', 0)->whereHas('roles', fn ($query) => $query->whereIn('name', ['hr_manager', 'directors']))->get();
        if ($recipients->isEmpty()) {
            $this->warn('No unblocked HR Manager or Directors users were found. No summary notifications created.');
            return self::SUCCESS;
        }

        $totals = ['created' => 0, 'updated' => 0, 'resolved' => 0, 'skipped' => 0];
        foreach ($recipients as $user) {
            $result = $this->option('dry-run')
                ? $this->delivery($user, $summary, false)
                : DB::transaction(function () use ($user, $summary) {
                    // Serialize runs per recipient, including simultaneous manual checks.
                    User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                    return $this->delivery($user, $summary, true);
                });
            $totals[$result]++;
        }
        $prefix = $this->option('dry-run') ? 'Preview — would' : 'Summary notifications —';
        $this->info("{$prefix} create {$totals['created']}, update {$totals['updated']}, resolve {$totals['resolved']}; unchanged {$totals['skipped']}.");
        if ($this->option('dry-run')) $this->info('No changes made.');
        return self::SUCCESS;
    }

    private function delivery(User $user, array $summary, bool $write): string
    {
        if ($write) {
            // Preserve old alert history, but stop old individual reminders cluttering the bell.
            Notification::where('type', 'EmployeeContractExpiry')->where('notifiable_type', $user->getMorphClass())
                ->where('notifiable_id', $user->id)->unread()->update(['read_at' => now()]);
        }
        $notifications = Notification::where('type', 'EmployeeContractExpirySummary')
            ->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id);
        $unread = (clone $notifications)->unread()->orderByDesc('id')->first();
        $count = $summary['due_30'];
        if ($count === 0) {
            if (!$unread) return 'skipped';
            if ($write) $unread->update(['read_at' => now(), 'data' => $summary, 'message' => 'No current approved employee contracts expire within the next 30 days.']);
            return 'resolved';
        }

        // Once read, do not add another summary on the same Nairobi calendar day.
        $dayStart = CarbonImmutable::today('Africa/Nairobi');
        if (!$unread && (clone $notifications)->where(function ($query) use ($dayStart) {
            $query->where(fn ($query) => $query->where('created_at', '>=', $dayStart)->where('created_at', '<', $dayStart->addDay()))
                ->orWhere(fn ($query) => $query->where('read_at', '>=', $dayStart)->where('read_at', '<', $dayStart->addDay()));
        })->exists()) return 'skipped';

        $attributes = [
            'data' => $summary,
            'title' => 'Employee contract expiry report',
            'message' => $count . ($count === 1 ? ' employee contract expires' : ' employee contracts expire') . ' within 30 days (' . $summary['as_of'] . ' to ' . $summary['through'] . '). View the report to review employees and departments.',
            'action_url' => route('contract.index', ['window' => '30']),
        ];
        if ($write) {
            if ($unread) {
                $unread->update($attributes);
            } else {
                Notification::create($attributes + ['type' => 'EmployeeContractExpirySummary', 'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id]);
            }
        }
        return $unread ? 'updated' : 'created';
    }
}
