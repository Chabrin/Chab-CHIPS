<?php

namespace App\Console\Commands;

use App\Services\PositionRoles;
use Illuminate\Console\Command;

/**
 * Link the positions that have no system role yet to one: an existing role where the names match (or config/position_roles.php says
 * so), otherwise a new role with no access. A dry run unless --apply is given. Nobody's access changes.
 */
class LinkPositionRoles extends Command
{
    protected $signature = 'hr:link-position-roles {--apply : Make the changes. Without it nothing is changed and the plan is only shown}';

    protected $description = 'Link every position to its system role (shows the plan first; --apply makes the changes)';

    public function handle(PositionRoles $roles): int
    {
        $plan = $roles->plan();
        if ($plan->isEmpty()) {
            $this->info('Every position already has a role.');

            return self::SUCCESS;
        }

        $this->table(
            ['Position', 'Staff', 'What happens', 'Role', 'People with that role'],
            $plan->map(fn ($item) => [
                $item['position']->position_name,
                $item['staff'],
                $item['action'] === 'use_existing' ? 'Use existing role (shown under the position name)' : 'Create a new role with no access',
                $item['action'] === 'use_existing' ? $item['role']->name : '(new)',
                $item['action'] === 'use_existing' ? $item['role_users'] : '',
            ])->all()
        );
        $existing = $plan->where('action', 'use_existing')->count();
        $this->line($existing . ' use an existing role; ' . ($plan->count() - $existing) . ' get a new role. Nobody\'s access changes: people keep the roles they have.');

        if (!$this->option('apply')) {
            $this->warn('Nothing was changed. Check the table (and config/position_roles.php), then run again with --apply.');

            return self::SUCCESS;
        }

        $done = $roles->apply();
        $this->info("Done: {$done['linked']} linked to an existing role, {$done['created']} new roles created.");

        return self::SUCCESS;
    }
}
