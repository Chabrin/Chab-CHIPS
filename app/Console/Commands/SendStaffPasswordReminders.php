<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Services\PasswordSecuritySettings;
use App\Services\StaffPasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendStaffPasswordReminders extends Command
{
    protected $signature = 'staff:password-reminders';
    protected $description = 'Notify staff before their password expires, on the days set in Password Security settings';

    /**
     * Send the expiry reminders. Does nothing while password security is switched off.
     *
     * @param StaffPasswordPolicy $policy
     * @param PasswordSecuritySettings $settings
     */
    public function handle(StaffPasswordPolicy $policy, PasswordSecuritySettings $settings): int
    {
        $reminderDays = $settings->reminderDays();
        if (!$settings->enabled() || !$reminderDays) {
            return self::SUCCESS;
        }

        User::where('block', false)->where(function ($query) {
            $query->whereNotNull('staff_id')->orWhereHas('staffLink');
        })->chunkById(200, function ($users) use ($policy, $reminderDays) {
            foreach ($users as $user) {
                DB::transaction(function () use ($user, $policy, $reminderDays) {
                    $user = User::whereKey($user->id)->lockForUpdate()->first();
                    if (!$user || $user->block || $user->requireReset || !$policy->appliesTo($user)) {
                        return;
                    }
                    $expires = $policy->expiresAt($user);
                    if (now()->gte($expires)) {
                        return;
                    }
                    $days = (int) now('Africa/Nairobi')->startOfDay()->diffInDays($expires->copy()->timezone('Africa/Nairobi')->startOfDay(), false);
                    if (!in_array($days, $reminderDays, true)) {
                        return;
                    }
                    $inserted = DB::table('password_expiry_reminders')->insertOrIgnore([
                        'user_id' => $user->id, 'expires_at' => $expires, 'days_before' => $days,
                    ]);
                    if ($inserted) {
                        Notification::create([
                            'type' => 'StaffPasswordExpiring', 'notifiable_type' => $user->getMorphClass(),
                            'notifiable_id' => $user->id, 'title' => 'Your password expires soon',
                            'message' => 'Your password expires in '.$days.' '.($days === 1 ? 'day' : 'days').
                                ' ('.$expires->copy()->timezone('Africa/Nairobi')->format('d M Y, H:i').' EAT). Please change it before then.',
                            'data' => ['expires_at' => $expires->toIso8601String(), 'days_before' => $days],
                            'action_url' => route('user.profile', ['tab' => 'security'], false),
                        ]);
                    }
                });
            }
        });

        return self::SUCCESS;
    }
}
