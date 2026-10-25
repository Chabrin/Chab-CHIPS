<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Loads the company org-structure document (departments, positions and who reports to whom) onto the
 * existing sys_set_departments and sys_set_staff_positions rows.
 *
 * Run it manually; it is not called from DatabaseSeeder or anything automatic:
 *   php artisan db:seed --class=OrgStructureHierarchySeeder
 * To see what it would do without writing anything:
 *   ORG_SEED_DRY_RUN=1 php artisan db:seed --class=OrgStructureHierarchySeeder
 *
 * Safe to re-run. Existing departments and positions are matched by name (case-insensitive) and are never
 * renamed. On an existing position, department_id and reports_to_id are only filled in where they are
 * currently NULL, so anything set by hand is left alone. Positions that are not in the document are not touched.
 */
class OrgStructureHierarchySeeder extends Seeder
{
    /**
     * When true nothing is written; the summary shows what would change.
     */
    public bool $dryRun = false;

    /**
     * Effective dry-run flag for the current run (the property above or the ORG_SEED_DRY_RUN env var).
     */
    private bool $preview = false;

    /**
     * Placeholder (negative) ids handed out to rows that a dry run would have created.
     */
    private int $placeholderId = 0;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->preview = $this->dryRun || filter_var(getenv('ORG_SEED_DRY_RUN'), FILTER_VALIDATE_BOOLEAN);
        $this->placeholderId = 0;

        $report = [
            'departments_created' => [],
            'departments_reused' => [],
            'positions_created' => [],
            'positions_updated' => [],
            'positions_unchanged' => [],
            'kept' => [],
            'untouched' => [],
        ];

        DB::transaction(function () use (&$report) {
            $departmentIds = $this->syncDepartments($report);
            $this->syncPositions($departmentIds, $report);
        });

        $this->printSummary($report);
    }

    /**
     * Departments from the document. "reuse" is the name of the existing row the document code maps to;
     * a department is only created when neither that row nor one named like the document exists.
     */
    private function departmentPlan(): array
    {
        return [
            'BOD' => ['name' => 'Board of Directors', 'reuse' => null],
            'ADM' => ['name' => 'Administration & General', 'reuse' => 'ADMINISTRATION'],
            'FIN' => ['name' => 'Accounting & Finance', 'reuse' => 'ACCOUNTS'],
            'PMD' => ['name' => 'Property Management', 'reuse' => 'FIELD'],
            'QAV' => ['name' => 'Quality Assurance & Valuation', 'reuse' => null],
            'IAD' => ['name' => 'Internal Audit', 'reuse' => null],
        ];
    }

    /**
     * Positions from the document, in document order. "aliases" are existing position names that count as
     * the same position; "reports_to" is the document title of the boss, or null when the document gives none.
     */
    private function positionPlan(): array
    {
        return [
            ['title' => 'Board Members', 'dept' => 'BOD', 'reports_to' => null, 'aliases' => []],
            ['title' => 'Managing Director', 'dept' => 'ADM', 'reports_to' => null, 'aliases' => ['MANAGING DIRECTOR']],
            ['title' => 'Operations & HR Manager', 'dept' => 'ADM', 'reports_to' => 'Managing Director', 'aliases' => ['OP & HR MANAGERR']],
            ['title' => 'Senior Admin Assistant', 'dept' => 'ADM', 'reports_to' => 'Operations & HR Manager', 'aliases' => []],
            ['title' => 'Admin Assistant', 'dept' => 'ADM', 'reports_to' => 'Senior Admin Assistant', 'aliases' => ['ADMINISTRATIVE ASSISTANT']],
            ['title' => 'Office Assistant', 'dept' => 'ADM', 'reports_to' => 'Admin Assistant', 'aliases' => ['OFFICE ASSISTANT']],
            ['title' => 'Caretaker & General Duties Officer', 'dept' => 'ADM', 'reports_to' => 'Admin Assistant', 'aliases' => []],
            ['title' => 'Cleaner & General Duties Officer', 'dept' => 'ADM', 'reports_to' => 'Admin Assistant', 'aliases' => []],
            ['title' => 'Security', 'dept' => 'ADM', 'reports_to' => 'Admin Assistant', 'aliases' => []],
            ['title' => 'QA & Valuation Manager', 'dept' => 'QAV', 'reports_to' => 'Managing Director', 'aliases' => []],
            ['title' => 'IT Support Officer', 'dept' => 'QAV', 'reports_to' => 'QA & Valuation Manager', 'aliases' => ['IT SUPPORT']],
            ['title' => 'Chief Accountant', 'dept' => 'FIN', 'reports_to' => 'Managing Director', 'aliases' => ['CHIEF ACCOUNTANT']],
            ['title' => 'Accountant', 'dept' => 'FIN', 'reports_to' => 'Chief Accountant', 'aliases' => []],
            // The document gives Property Manager no boss, so none is invented here.
            ['title' => 'Property Manager', 'dept' => 'PMD', 'reports_to' => null, 'aliases' => ['PROPERTY MANAGER']],
            ['title' => 'Assistant Property Manager', 'dept' => 'PMD', 'reports_to' => 'Property Manager', 'aliases' => ['ASSISTANT PROPERTY MANAGER']],
            ['title' => 'Zone Manager', 'dept' => 'PMD', 'reports_to' => 'Assistant Property Manager', 'aliases' => ['ZONE MANAGER', 'Zone Managers']],
            ['title' => 'Senior Field Officer', 'dept' => 'PMD', 'reports_to' => 'Zone Manager', 'aliases' => ['SENIOR FIELD OFFICER', 'Senior Field Officers']],
            ['title' => 'Field Officer', 'dept' => 'PMD', 'reports_to' => 'Senior Field Officer', 'aliases' => ['FIELD OFFICER', 'Field Officers']],
            // No boss in the document, and the existing AUDITOR row is deliberately not aliased (its mapping is undecided).
            ['title' => 'Internal Auditor', 'dept' => 'IAD', 'reports_to' => null, 'aliases' => []],
            ['title' => 'Audit Assistant', 'dept' => 'IAD', 'reports_to' => 'Internal Auditor', 'aliases' => []],
        ];
    }

    /**
     * Reuse the existing department for every document code, creating only the ones that are missing.
     *
     * @param array $report
     * @return array Department id keyed by document code (placeholder ids on a dry run)
     */
    private function syncDepartments(array &$report): array
    {
        $existing = DB::table('sys_set_departments')->get();
        $ids = [];

        foreach ($this->departmentPlan() as $code => $department) {
            $found = null;
            foreach (array_filter([$department['reuse'], $department['name']]) as $candidate) {
                $found = $this->findByName($existing, 'department_name', $candidate);
                if ($found) {
                    break;
                }
            }

            if ($found) {
                $ids[$code] = (int) $found->id;
                $report['departments_reused'][] = $code . ' -> ' . $found->department_name;
                continue;
            }

            $ids[$code] = $this->preview
                ? --$this->placeholderId
                : (int) DB::table('sys_set_departments')->insertGetId([
                    'department_name' => $department['name'],
                    'date_time' => now(),
                ]);
            $report['departments_created'][] = $code . ' -> ' . $department['name'];
        }

        return $ids;
    }

    /**
     * Match or create every document position, then fill in department_id and reports_to_id where they are empty.
     *
     * @param array $departmentIds
     * @param array $report
     */
    private function syncPositions(array $departmentIds, array &$report): void
    {
        $existing = DB::table('sys_set_staff_positions')->get();
        $plan = $this->positionPlan();

        // Current reporting lines (position id => boss id), kept up to date as rows are changed
        $bossOf = [];
        foreach ($existing as $row) {
            $bossOf[(int) $row->id] = $row->reports_to_id !== null ? (int) $row->reports_to_id : null;
        }

        // PASS 1: find each document position among the existing rows, or create it without a boss yet
        $resolved = [];
        $matchedIds = [];
        foreach ($plan as $spec) {
            $found = null;
            foreach (array_merge([$spec['title']], $spec['aliases']) as $candidate) {
                $found = $this->findByName($existing, 'position_name', $candidate);
                if ($found) {
                    break;
                }
            }

            if ($found) {
                $matchedIds[] = (int) $found->id;
                $resolved[$spec['title']] = [
                    'id' => (int) $found->id,
                    'name' => $found->position_name,
                    'department_id' => $found->department_id !== null ? (int) $found->department_id : null,
                    'created' => false,
                ];
                continue;
            }

            $id = $this->preview
                ? --$this->placeholderId
                : (int) DB::table('sys_set_staff_positions')->insertGetId([
                    'department_id' => $departmentIds[$spec['dept']],
                    'position_name' => $spec['title'],
                    'slug_name' => Str::slug($spec['title'], '_'),
                    'current_status_id' => 5,
                    'date_time' => now(),
                ]);
            $bossOf[$id] = null;
            $resolved[$spec['title']] = [
                'id' => $id,
                'name' => $spec['title'],
                'department_id' => $departmentIds[$spec['dept']],
                'created' => true,
            ];
        }

        // PASS 2: department and boss, only where the current value is NULL
        foreach ($plan as $spec) {
            $position = $resolved[$spec['title']];
            $id = $position['id'];
            $changes = [];

            $wantedDepartment = $departmentIds[$spec['dept']];
            if ($position['department_id'] === null) {
                $changes['department_id'] = $wantedDepartment;
            } elseif ($position['department_id'] !== $wantedDepartment) {
                $report['kept'][] = $position['name'] . ': department_id left at ' . $position['department_id'] . ' (document says ' . $spec['dept'] . ')';
            }

            if ($spec['reports_to'] !== null) {
                $wantedBoss = $resolved[$spec['reports_to']]['id'];
                if ($bossOf[$id] === null) {
                    if ($wantedBoss === $id || $this->reportsToWouldLoop($bossOf, $id, $wantedBoss)) {
                        $report['kept'][] = $position['name'] . ': reports_to_id not set, ' . $spec['reports_to'] . ' would create a reporting loop';
                    } else {
                        $changes['reports_to_id'] = $wantedBoss;
                        $bossOf[$id] = $wantedBoss;
                    }
                } elseif ($bossOf[$id] !== $wantedBoss) {
                    $report['kept'][] = $position['name'] . ': reports_to_id left at ' . $bossOf[$id] . ' (document says ' . $spec['reports_to'] . ')';
                }
            }

            if (!$this->preview) {
                foreach ($changes as $column => $value) {
                    // whereNull makes sure a value that is already set is never overwritten
                    DB::table('sys_set_staff_positions')->where('id', $id)->whereNull($column)->update([$column => $value]);
                }
            }

            if ($position['created']) {
                $report['positions_created'][] = $position['name'] . ' (' . $spec['dept'] . ')';
            } elseif ($changes) {
                $report['positions_updated'][] = $position['name'] . ' (' . implode(', ', array_keys($changes)) . ')';
            } else {
                $report['positions_unchanged'][] = $position['name'];
            }
        }

        foreach ($existing as $row) {
            if (!in_array((int) $row->id, $matchedIds, true)) {
                $report['untouched'][] = $row->position_name;
            }
        }
    }

    /**
     * Find the row whose name column equals the given name, ignoring case and surrounding spaces.
     *
     * @param \Illuminate\Support\Collection $rows
     * @param string $column
     * @param string $name
     */
    private function findByName($rows, string $column, string $name): ?object
    {
        $wanted = mb_strtolower(trim($name));

        foreach ($rows as $row) {
            if (mb_strtolower(trim((string) $row->{$column})) === $wanted) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Whether making $bossId the boss of $id would close a loop, found by walking up from $bossId.
     *
     * @param array $bossOf
     * @param int $id
     * @param int $bossId
     */
    private function reportsToWouldLoop(array $bossOf, int $id, int $bossId): bool
    {
        $visited = [];
        $current = $bossId;

        while ($current !== null && !in_array($current, $visited, true)) {
            if ($current === $id) {
                return true;
            }
            $visited[] = $current;
            $current = $bossOf[$current] ?? null;
        }

        return false;
    }

    /**
     * Print what was (or, on a dry run, would be) done.
     *
     * @param array $report
     */
    private function printSummary(array $report): void
    {
        $say = fn (string $line) => $this->command?->info($line);

        $say(($this->preview ? '[DRY RUN - nothing was written] ' : '') . 'Organization structure summary');
        $say('Departments created (' . count($report['departments_created']) . '): ' . implode('; ', $report['departments_created']));
        $say('Departments reused (' . count($report['departments_reused']) . '): ' . implode('; ', $report['departments_reused']));
        $say('Positions created (' . count($report['positions_created']) . '): ' . implode('; ', $report['positions_created']));
        $say('Positions updated (' . count($report['positions_updated']) . '): ' . implode('; ', $report['positions_updated']));
        $say('Positions skipped, already complete (' . count($report['positions_unchanged']) . '): ' . implode('; ', $report['positions_unchanged']));

        foreach ($report['kept'] as $note) {
            $this->command?->warn('Kept existing value - ' . $note);
        }

        $say('Positions left untouched (' . count($report['untouched']) . '): ' . implode('; ', $report['untouched']));
    }
}
