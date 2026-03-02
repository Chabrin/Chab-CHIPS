<?php

namespace App\Console\Commands;

use App\Http\Controllers\LeaseEscalationController;
use App\Models\LeaseEscalation;
use App\Models\LeaseEscalationNotificationSetting;
use App\Models\User;
use App\Notifications\LeaseEscalationDueNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessEscalations extends Command
{
    protected $signature   = 'escalations:process';
    protected $description = 'Send pre-effective-date notifications and auto-apply approved lease escalations';

    public function handle(): int
    {
        $this->info('[' . now()->toDateTimeString() . '] Processing lease escalations...');

        $settings    = LeaseEscalationNotificationSetting::instance();
        $notifyRoles = $settings->notify_roles ?? [];

        // Users who should receive notifications
        $recipients = $this->resolveRecipients($notifyRoles);

        $notified = 0;
        $applied  = 0;

        // ── 1. Fire upcoming notifications ──────────────────────────
        // Find approved-pending escalations where notification not yet sent
        // and where today falls within the notification window.
        $pending = LeaseEscalation::where('status', 'approved_pending_effective')
            ->whereNull('notification_sent_at')
            ->with(['property', 'unit'])
            ->get();

        foreach ($pending as $esc) {
            if ($esc->isDueForNotification()) {
                $this->dispatchNotifications($recipients, $esc, 'upcoming');
                $esc->notification_sent_at = now();
                $esc->save();
                $notified++;
                $this->info("  → Notified: escalation #{$esc->id} ({$esc->reference_number})");
            }
        }

        // ── 2. Auto-apply escalations whose effective date has passed ─
        $due = LeaseEscalation::where('status', 'approved_pending_effective')
            ->whereDate('effective_date', '<=', Carbon::today())
            ->with(['property', 'unit'])
            ->get();

        $controller = app(LeaseEscalationController::class);

        foreach ($due as $esc) {
            try {
                $isOverdue = $esc->effective_date->lt(Carbon::today());

                $controller->applyEscalation($esc);
                $applied++;

                $type = $isOverdue ? 'overdue_applied' : 'applied';
                $this->dispatchNotifications($recipients, $esc->fresh(), $type);

                $this->info("  → Applied: escalation #{$esc->id} ({$esc->reference_number})" .
                    ($isOverdue ? ' [OVERDUE]' : ''));
            } catch (\Exception $e) {
                Log::error("ProcessEscalations: failed to apply #{$esc->id}: " . $e->getMessage());
                $this->error("  ✗ Failed to apply #{$esc->id}: " . $e->getMessage());
            }
        }

        $this->info("Done. Notified: {$notified}, Applied: {$applied}.");

        return Command::SUCCESS;
    }

    // ────────────────────────────────────────────────────────────────

    /**
     * Resolve a collection of User models from an array of role names.
     * Falls back to all users if no roles are configured.
     */
    private function resolveRecipients(array $roles): \Illuminate\Support\Collection
    {
        if (empty($roles)) {
            return collect();
        }

        return User::whereHas('roles', fn ($q) => $q->whereIn('name', $roles))->get();
    }

    /**
     * Send in-app + email notification to each recipient.
     */
    private function dispatchNotifications(
        \Illuminate\Support\Collection $recipients,
        LeaseEscalation $esc,
        string $type
    ): void {
        foreach ($recipients as $user) {
            try {
                $user->notify(new LeaseEscalationDueNotification($esc, $type));
            } catch (\Exception $e) {
                Log::warning("ProcessEscalations: notification to user #{$user->id} failed: " . $e->getMessage());
            }
        }
    }
}
