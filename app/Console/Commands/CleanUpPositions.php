<?php

namespace App\Console\Commands;

use App\Models\Position;
use Database\Seeders\OrgStructureHierarchySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tidies the positions list down to the company's approved positions (the org-structure document that
 * OrgStructureHierarchySeeder loads). A position that is not in it is deleted when nothing uses it, and switched off
 * when something does (staff, contracts, payroll...), so nobody loses their position and it stops being offered.
 *
 * It only shows what it would do unless --apply is given. Positions the zone features look up by slug are never touched.
 *
 *   php artisan hr:positions-cleanup                       what would change
 *   php artisan hr:positions-cleanup --keep="Security Guard"   keep one more name
 *   php artisan hr:positions-cleanup --apply               do it
 */
class CleanUpPositions extends Command
{
    protected $signature = 'hr:positions-cleanup {--apply : Make the changes (without it, only shows them)} {--keep=* : A position name to keep as it is (can be given more than once)}';

    protected $description = 'Delete or switch off positions that are not in the approved org structure (shows what it would do unless --apply is given)';

    /** Positions the property zone pages find staff by; switching these off would not break them, but they are never part of a clean-up. */
    private const PROTECTED_SLUGS = ['field_officer', 'zone_manager', 'zone_supervisor', 'zone_auditor'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $approved = array_map('mb_strtolower', array_merge(OrgStructureHierarchySeeder::approvedPositionNames(), (array) $this->option('keep')));

        $plan = [];
        foreach (Position::orderBy('position_name')->get() as $position) {
            $plan[] = ['position' => $position] + $this->decide($position, $approved);
        }

        $this->table(['Position', 'What happens', 'Why'], array_map(fn ($row) => [$row['position']->position_name, $row['action'], $row['why']], $plan));

        $delete = array_filter($plan, fn ($row) => $row['action'] === 'delete');
        $off = array_filter($plan, fn ($row) => $row['action'] === 'switch off');
        $this->info(count($delete) . ' to delete, ' . count($off) . ' to switch off, ' . count(array_filter($plan, fn ($row) => $row['action'] === 'keep')) . ' kept.');

        if (!$apply) {
            $this->warn('Nothing was changed. Run it again with --apply to make these changes.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($delete, $off) {
            foreach ($delete as $row) {
                $row['position']->delete();
            }
            foreach ($off as $row) {
                $row['position']->update(['is_active' => false]);
            }
        });
        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * @return array{action: string, why: string}
     */
    private function decide(Position $position, array $approved): array
    {
        if (in_array(mb_strtolower(trim($position->position_name)), $approved, true)) {
            return ['action' => 'keep', 'why' => 'In the approved list'];
        }
        if (in_array($position->slug_name, self::PROTECTED_SLUGS, true)) {
            return ['action' => 'keep', 'why' => 'The zone pages find staff by this position'];
        }

        $uses = $position->usageNotes();
        if (!$uses) {
            return ['action' => 'delete', 'why' => 'Not approved, and nothing uses it'];
        }
        if (!$position->is_active) {
            return ['action' => 'keep', 'why' => 'Already switched off (' . implode(', ', $uses) . ')'];
        }

        return ['action' => 'switch off', 'why' => 'Not approved, but ' . implode(', ', $uses)];
    }
}
