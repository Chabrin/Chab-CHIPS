<?php

namespace App\Console\Commands;

use App\Models\LeaseAgreement;
use App\Models\User;
use App\Notifications\LeaseExpiringNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckExpiringLeases extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'leases:check-expiring';

    /**
     * The console command description.
     */
    protected $description = 'Check for leases expiring soon and send notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expiring leases...');

        // First, mark expired leases
        $this->markExpiredLeases();

        // Define notification thresholds (in days)
        $thresholds = [90, 60, 30, 14, 7, 3, 1];

        $today = Carbon::now()->startOfDay();
        $notificationsSent = 0;

        foreach ($thresholds as $days) {
            $targetDate = $today->copy()->addDays($days)->endOfDay();
            
            // Find active leases expiring on the target date
            $expiringLeases = LeaseAgreement::where('current_status_id', 5) // Active/Approved leases
                ->whereDate('date_end', $targetDate->toDateString())
                ->with(['property', 'tenant', 'fieldOfficer'])
                ->get();

            foreach ($expiringLeases as $lease) {
                // Get users to notify (field officer, property manager, zone supervisor)
                $usersToNotify = collect();

                // Add field officer
                if ($lease->fieldOfficer) {
                    $usersToNotify->push($lease->fieldOfficer);
                }

                // Add property-related users
                if ($lease->property) {
                    if ($lease->property->fieldOfficer) {
                        $usersToNotify->push($lease->property->fieldOfficer);
                    }
                    if ($lease->property->zoneSupervisor) {
                        $usersToNotify->push($lease->property->zoneSupervisor);
                    }
                    if ($lease->property->zoneManager) {
                        $usersToNotify->push($lease->property->zoneManager);
                    }
                }

                // Add users with specific permissions (e.g., lease management)
                $managementUsers = User::whereHas('roles', function($query) {
                    $query->whereIn('name', ['admin', 'super-admin', 'property-manager']);
                })->get();
                
                $usersToNotify = $usersToNotify->merge($managementUsers)->unique('id');

                // Send notifications
                foreach ($usersToNotify as $user) {
                    try {
                        $user->notify(new LeaseExpiringNotification($lease, $days));
                        $notificationsSent++;
                    } catch (\Exception $e) {
                        $this->error("Failed to send notification for lease {$lease->lease_ref}: {$e->getMessage()}");
                    }
                }

                $this->info("Sent notifications for lease {$lease->lease_ref} expiring in {$days} days");
            }
        }

        $this->info("Total notifications sent: {$notificationsSent}");
        return Command::SUCCESS;
    }

    /**
     * Mark leases as expired when their end date has passed
     */
    protected function markExpiredLeases()
    {
        $today = Carbon::now()->startOfDay();
        
        // Find all active leases where end date is before today
        $expiredLeases = LeaseAgreement::where('current_status_id', 5) // Active/Approved status
            ->where('date_end', '<', $today->toDateString())
            ->get();

        $expiredCount = 0;

        foreach ($expiredLeases as $lease) {
            try {
                // Update lease status to expired
                $lease->current_status_id = 22; // Expired status
                $lease->lease_status = 'Expired';
                $lease->save();

                $expiredCount++;
                $this->info("Marked lease {$lease->ref_number} as expired (End date: {$lease->date_end})");

            } catch (\Exception $e) {
                $this->error("Failed to mark lease {$lease->ref_number} as expired: {$e->getMessage()}");
            }
        }

        if ($expiredCount > 0) {
            $this->info("Successfully marked {$expiredCount} lease(s) as expired");
        } else {
            $this->info("No leases to mark as expired");
        }
    }
}
