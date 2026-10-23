<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrgStructureHierarchySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Departments and their positions according to V2.0 Org Structure
        $structure = [
            'Board of Directors' => [
                ['name' => 'Board Members', 'reports_to' => null],
            ],
            'Administration & General' => [
                ['name' => 'Managing Director', 'reports_to' => null],
                ['name' => 'Operations & HR Manager', 'reports_to' => 'Managing Director'],
                ['name' => 'Senior Admin Assistant', 'reports_to' => 'Operations & HR Manager'],
                ['name' => 'Admin Assistant', 'reports_to' => 'Senior Admin Assistant'],
                ['name' => 'Office Assistant', 'reports_to' => 'Admin Assistant'],
                ['name' => 'Caretaker & General Duties Officer', 'reports_to' => 'Admin Assistant'],
                ['name' => 'Cleaner & General Duties Officer', 'reports_to' => 'Admin Assistant'],
                ['name' => 'Security', 'reports_to' => 'Admin Assistant'],
            ],
            'Quality Assurance & Valuation' => [
                ['name' => 'QA & Valuation Manager', 'reports_to' => 'Managing Director'],
                ['name' => 'IT Support Officer', 'reports_to' => 'QA & Valuation Manager'],
            ],
            'Accounting & Finance' => [
                ['name' => 'Chief Accountant', 'reports_to' => 'Managing Director'],
                ['name' => 'Accountant', 'reports_to' => 'Chief Accountant'],
            ],
            'Property Management' => [
                ['name' => 'Property Manager', 'reports_to' => 'Managing Director'],
                ['name' => 'Assistant Property Manager', 'reports_to' => 'Property Manager'],
                ['name' => 'Zone Managers', 'reports_to' => 'Assistant Property Manager'],
                ['name' => 'Senior Field Officers', 'reports_to' => 'Zone Managers'],
                ['name' => 'Field Officers', 'reports_to' => 'Senior Field Officers'],
            ],
            'Internal Audit' => [
                ['name' => 'Internal Auditor', 'reports_to' => 'Managing Director'],
                ['name' => 'Audit Assistant', 'reports_to' => 'Internal Auditor'],
            ],
        ];

        DB::transaction(function () use ($structure) {
            // PASS 1: Ensure all departments exist and create/update positions with their department_id
            foreach ($structure as $deptName => $positions) {
                // Find or insert the department in sys_set_departments
                $department = DB::table('sys_set_departments')
                    ->where('department_name', 'ILIKE', $deptName)
                    ->first();

                if (!$department) {
                    $deptId = DB::table('sys_set_departments')->insertGetId([
                        'department_name' => $deptName,
                        'date_time'       => now(),
                    ]);
                } else {
                    $deptId = $department->id;
                }

                foreach ($positions as $pos) {
                    $title = $pos['name'];
                    $slug = Str::slug($title, '_');

                    // Check if the position exists (case-insensitive lookup)
                    $existing = DB::table('sys_set_staff_positions')
                        ->where('position_name', 'ILIKE', $title)
                        ->orWhere('slug_name', $slug)
                        ->first();

                    if ($existing) {
                        DB::table('sys_set_staff_positions')
                            ->where('id', $existing->id)
                            ->update([
                                'department_id' => $deptId,
                                'slug_name'     => $existing->slug_name ?: $slug,
                            ]);
                    } else {
                        DB::table('sys_set_staff_positions')->insert([
                            'department_id'     => $deptId,
                            'position_name'     => $title,
                            'slug_name'         => $slug,
                            'current_status_id' => 5, // Active
                            'date_time'         => now(),
                        ]);
                    }
                }
            }

            // PASS 2: Link reports_to_id for all positions
            foreach ($structure as $deptName => $positions) {
                foreach ($positions as $pos) {
                    $title = $pos['name'];
                    $reportsToName = $pos['reports_to'];

                    $currentPos = DB::table('sys_set_staff_positions')
                        ->where('position_name', 'ILIKE', $title)
                        ->first();

                    if (!$currentPos) {
                        continue;
                    }

                    $reportsToId = null;
                    if ($reportsToName) {
                        $supervisor = DB::table('sys_set_staff_positions')
                            ->where('position_name', 'ILIKE', $reportsToName)
                            ->first();

                        $reportsToId = $supervisor ? $supervisor->id : null;
                    }

                    DB::table('sys_set_staff_positions')
                        ->where('id', $currentPos->id)
                        ->update(['reports_to_id' => $reportsToId]);
                }
            }
        });

        $this->command->info('Organization structure positions and reporting lines synced successfully.');
    }
}