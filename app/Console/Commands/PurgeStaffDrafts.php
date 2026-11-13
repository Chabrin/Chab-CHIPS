<?php

namespace App\Console\Commands;

use App\Models\StaffDraft;
use Illuminate\Console\Command;

/**
 * Removes Add Staff drafts nobody has touched for a while, with the ID copies and photos saved in them. Drafts hold personal
 * data, so one that was never finished should not stay for ever.
 *
 * It only shows what it would remove unless --apply is given, and it is not scheduled: how long drafts may stay is for HR to
 * decide, and then it can be added to routes/console.php.
 *
 *   php artisan hr:purge-staff-drafts --days=30           what would go
 *   php artisan hr:purge-staff-drafts --days=30 --apply   remove them
 */
class PurgeStaffDrafts extends Command
{
    protected $signature = 'hr:purge-staff-drafts {--days=30 : Remove drafts not saved for this many days} {--apply : Remove them (without it, only shows how many)}';

    protected $description = 'Remove Add Staff drafts that have not been saved for a number of days (shows what it would do unless --apply is given)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        // Only drafts still open: the emptied record of a staff member who was saved holds nothing to purge
        $old = StaffDraft::open()->where('updated_at', '<', now()->subDays($days))->get();

        $this->info($old->count() . ($old->count() === 1 ? ' draft has' : ' drafts have') . " not been saved for {$days} days or more.");

        if (!$this->option('apply')) {
            $this->warn('Nothing was removed. Run it again with --apply to remove them and their files.');

            return self::SUCCESS;
        }

        $old->each->discard();
        $this->info('Removed ' . $old->count() . '.');

        return self::SUCCESS;
    }
}
