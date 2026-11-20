<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Services\StaffPermitExpiry;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tell the HR Manager and the Directors, in the bell, about work permits and Alien IDs that are about to run out or already have.
 * One summary each: it is updated while it is unread, and cleared when nothing needs attention any more.
 */
class CheckExpiringWorkPermits extends Command
{
    protected $signature = 'hr:check-expiring-permits {--dry-run : Show the summary without saving notifications}';

    protected $description = 'Remind HR Managers and Directors about work permits that expire soon or have expired';

    private const TYPE = 'WorkPermitExpirySummary';

    public function handle(StaffPermitExpiry $permits): int
    {
        $summary = $permits->summary();
        $this->info("Work permits: {$summary['expired']} expired; {$summary['due_14']} within 14 days; {$summary['due_30']} within 30 days; {$summary['due_60']} within " . StaffPermitExpiry::WINDOW_DAYS . ' days.');
        $message = $permits->message($summary);

        $recipients = User::where('block', 0)->whereHas('roles', fn ($query) => $query->whereIn('name', ['hr_manager', 'directors']))->get();
        if ($recipients->isEmpty()) {
            $this->warn('No unblocked HR Manager or Directors users were found. No notifications created.');

            return self::SUCCESS;
        }

        $totals = ['created' => 0, 'updated' => 0, 'resolved' => 0, 'skipped' => 0];
        foreach ($recipients as $user) {
            $result = $this->option('dry-run') ? 'skipped' : DB::transaction(fn () => $this->deliver($user, $summary, $message));
            $totals[$result]++;
        }
        $this->info(($this->option('dry-run') ? 'Preview, nothing saved. ' : '') . "Notifications: created {$totals['created']}, updated {$totals['updated']}, resolved {$totals['resolved']}, unchanged {$totals['skipped']}.");

        return self::SUCCESS;
    }

    private function deliver(User $user, array $summary, ?string $message): string
    {
        User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        $notifications = Notification::where('type', self::TYPE)->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id);
        $unread = (clone $notifications)->unread()->orderByDesc('id')->first();

        if ($message === null) {
            if (!$unread) {
                return 'skipped';
            }
            $unread->update(['read_at' => now(), 'data' => $summary, 'message' => 'No work permits need attention now.']);

            return 'resolved';
        }

        // Once read, do not add another on the same Nairobi calendar day
        $dayStart = CarbonImmutable::today('Africa/Nairobi');
        if (!$unread && (clone $notifications)->where(function ($query) use ($dayStart) {
            $query->where(fn ($q) => $q->where('created_at', '>=', $dayStart)->where('created_at', '<', $dayStart->addDay()))
                ->orWhere(fn ($q) => $q->where('read_at', '>=', $dayStart)->where('read_at', '<', $dayStart->addDay()));
        })->exists()) {
            return 'skipped';
        }

        $attributes = [
            'data' => $summary,
            'title' => 'Work permits',
            'message' => $message,
            'action_url' => route('staff.index'),
        ];
        if ($unread) {
            $unread->update($attributes);

            return 'updated';
        }
        Notification::create($attributes + ['type' => self::TYPE, 'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id]);

        return 'created';
    }
}
