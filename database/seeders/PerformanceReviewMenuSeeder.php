<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Adds the Performance Review module to the HR menu, just below HR Reports, with its pages and which roles see each one.
 *
 * NOT run automatically and not part of DatabaseSeeder: the role lists below are a proposal for the RBAC review. Run it
 * only when told, with:  php artisan db:seed --class=PerformanceReviewMenuSeeder
 *
 * It is safe to run again: nothing is duplicated, and it only ever adds (a module, pages, and role-to-page rows); it never
 * removes or changes what is already there. A role named below that does not exist is skipped and listed in the output.
 * What a person can open inside a page is decided by the server (position chain and HR roles), not by this menu.
 */
class PerformanceReviewMenuSeeder extends Seeder
{
    private const HR_SUPER_MODULE = 3;

    private const MODULE_NAME = 'Performance Review';

    private const AFTER_MODULE = 'HR Reports';

    /** Everyone who has a staff record and a login: they rate themselves and read their final rating. */
    private const ALL_STAFF = ['directors', 'hr_manager', 'property_manager', 'zone_manager', 'senior_field_officer', 'field_officer', 'accountant', 'assistant_accountant', 'internal_auditor', 'qc_manager', 'it_department', 'office_admin_payments', 'office_assistant'];

    /** Roles whose positions have people reporting to them, so they may be named as someone's supervisor. */
    private const SUPERVISORS = ['directors', 'hr_manager', 'property_manager', 'zone_manager', 'senior_field_officer', 'accountant', 'internal_auditor', 'qc_manager', 'office_admin_payments', 'office_assistant'];

    /** HR: the same roles the leave approval and the Performance Review code treat as HR. */
    private const HR = ['hr_manager', 'directors', 'superadministrator'];

    /** [page name, url, roles that see it] in the order they appear. */
    private const PAGES = [
        ['My Review', '/hr/performance/my-review', self::ALL_STAFF],
        ['Team Reviews', '/hr/performance/team-reviews', self::SUPERVISORS],
        ['All Reviews', '/hr/performance/all-reviews', self::HR],
        ['Review Cycles', '/hr/performance/cycles', self::HR],
        ['KPIs', '/hr/performance/kpis', self::HR],
        ['Supervisors', '/hr/performance/supervisors', self::HR],
        // Added with the joint review meeting and the appeal. Meetings is for everyone: a committee member opens the meetings they sit on here.
        ['Meetings', '/hr/performance/meetings', self::ALL_STAFF],
        ['Appeals', '/hr/performance/appeals', self::HR],
        ['Committee', '/hr/performance/committee', self::HR],
    ];

    public function run(): void
    {
        $now = now();
        $log = [];

        DB::transaction(function () use ($now, &$log) {
            $moduleId = $this->module($now, $log);

            foreach (self::PAGES as $index => [$name, $url, $roles]) {
                $pageId = DB::table('cb_sub_modules')->where('sub_module_url', $url)->value('id');
                if ($pageId) {
                    $log[] = "page exists: {$name}";
                } else {
                    $pageId = DB::table('cb_sub_modules')->insertGetId([
                        'sub_module_name' => $name,
                        'sub_module_url' => $url,
                        'icon' => null,
                        'order' => $index + 1,
                        'added_by' => 'system',
                        'added_on' => $now,
                        'module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $log[] = "page added: {$name}";
                }

                foreach ($roles as $roleName) {
                    $roleId = DB::table('cb_roles')->where('name', $roleName)->value('id');
                    if (! $roleId) {
                        $log[] = "  role not found, skipped: {$roleName} (for {$name})";

                        continue;
                    }
                    $has = DB::table('cb_sub_module_permissions')->where('role_id', $roleId)->where('sub_module_id', $pageId)->exists();
                    if (! $has) {
                        DB::table('cb_sub_module_permissions')->insert([
                            'role_id' => $roleId,
                            'sub_module_id' => $pageId,
                            'added_by' => 'system',
                            'added_on' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $log[] = "  {$name} given to {$roleName}";
                    }
                }
            }
        });

        foreach ($log as $line) {
            $this->command?->line($line);
        }
    }

    /** The Performance Review module, made just below HR Reports when it is not there yet. */
    private function module($now, array &$log): int
    {
        $existing = DB::table('cb_modules')->where('module_name', self::MODULE_NAME)->where('super_module_id', self::HR_SUPER_MODULE)->value('id');
        if ($existing) {
            $log[] = 'module exists: '.self::MODULE_NAME;

            return (int) $existing;
        }

        $after = DB::table('cb_modules')->where('module_name', self::AFTER_MODULE)->where('super_module_id', self::HR_SUPER_MODULE)->value('module_order');
        $order = $after !== null ? (int) $after + 1 : 5;

        $id = DB::table('cb_modules')->insertGetId([
            'module_name' => self::MODULE_NAME,
            'module_url' => null,
            'icon' => 'rate_review',
            'module_order' => $order,
            'added_by' => 'system',
            'added_on' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted' => 0,
            'super_module_id' => self::HR_SUPER_MODULE,
        ]);
        $log[] = 'module added: '.self::MODULE_NAME." (order {$order}, below ".self::AFTER_MODULE.')';

        return (int) $id;
    }
}
