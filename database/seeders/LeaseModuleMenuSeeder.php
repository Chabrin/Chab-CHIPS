<?php

/**
 * LeaseModuleMenuSeeder
 *
 * Inserts the Lease Documentation module and its sub-modules into the
 * CHIPS navigation tables. Run this once on the target CHIPS database.
 *
 * Usage:
 *   php artisan db:seed --class=LeaseModuleMenuSeeder
 *
 * Or call from DatabaseSeeder:
 *   $this->call(LeaseModuleMenuSeeder::class);
 *
 * After running, assign sub-module permissions to roles via the CHIPS
 * admin panel (Roles → Edit → Module Access), or extend the
 * seedRolePermissions() method below with your role IDs.
 */

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeaseModuleMenuSeeder extends Seeder
{
    /**
     * Run the seeder.
     */
    public function run(): void
    {
        $now = now()->toDateTimeString();
        $addedBy = 'seeder';

        // ----------------------------------------------------------------
        // 1. Insert the parent module
        // ----------------------------------------------------------------
        // module_url is NULL for parent items — CHIPS renders them as
        // collapsible sidebar headers, not clickable links.

        $moduleId = DB::table('cb_modules')->insertGetId([
            'module_name'  => 'Lease Documentation',
            'module_url'   => null,
            'icon'         => 'description',          // Material icon
            'module_order' => $this->nextModuleOrder(),
            'added_by'     => $addedBy,
            'added_on'     => $now,
            'created_at'   => $now,
            'updated_at'   => $now,
            'deleted'      => 0,
        ]);

        // ----------------------------------------------------------------
        // 2. Define sub-modules
        //    sub_module_url must match the registered route prefix in
        //    routes/web.php (e.g. Route::prefix('leases')->...).
        //    Adjust the prefix below if CHIPS registers these routes
        //    under a different path.
        // ----------------------------------------------------------------
        $subModules = [
            [
                'name'  => 'Dashboard',
                'url'   => '/leases/dashboard',
                'icon'  => 'dashboard',
                'order' => 1,
            ],
            [
                'name'  => 'Active Leases',
                'url'   => '/leases',
                'icon'  => 'article',
                'order' => 2,
            ],
            [
                'name'  => 'New Lease',
                'url'   => '/leases/create',
                'icon'  => 'add_circle',
                'order' => 3,
            ],
            [
                'name'  => 'Lease Workflow',
                'url'   => '/leases/workflow',
                'icon'  => 'account_tree',
                'order' => 4,
            ],
            [
                'name'  => 'Documents Vault',
                'url'   => '/leases/documents',
                'icon'  => 'folder_open',
                'order' => 5,
            ],
            [
                'name'  => 'Digital Signing',
                'url'   => '/leases/signing',
                'icon'  => 'draw',
                'order' => 6,
            ],
            [
                'name'  => 'Lease Templates',
                'url'   => '/leases/templates',
                'icon'  => 'file_copy',
                'order' => 7,
            ],
            [
                'name'  => 'Advocates',
                'url'   => '/leases/lawyers',
                'icon'  => 'gavel',
                'order' => 8,
            ],
            [
                'name'  => 'Print Log',
                'url'   => '/leases/print-log',
                'icon'  => 'print',
                'order' => 9,
            ],
            [
                'name'  => 'Reports',
                'url'   => '/leases/reports',
                'icon'  => 'bar_chart',
                'order' => 10,
            ],
        ];

        $insertedSubModuleIds = [];

        foreach ($subModules as $sub) {
            $insertedSubModuleIds[$sub['name']] = DB::table('cb_sub_modules')->insertGetId([
                'sub_module_name' => $sub['name'],
                'sub_module_url'  => $sub['url'],
                'icon'            => $sub['icon'],
                'order'           => $sub['order'],
                'module_id'       => $moduleId,
                'added_by'        => $addedBy,
                'added_on'        => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        // ----------------------------------------------------------------
        // 3. Assign permissions to roles (optional)
        //
        //    Replace the role IDs below with the actual IDs from your
        //    CHIPS cb_roles table. Remove any roles that should NOT have
        //    access to the Lease Documentation module.
        //
        //    To skip this step and assign permissions via the admin UI
        //    instead, comment out the seedRolePermissions() call.
        // ----------------------------------------------------------------
        $roleIds = [
            // 1,  // Super Admin
            // 2,  // Admin
            // 3,  // Property Manager
        ];

        if (! empty($roleIds)) {
            $this->seedRolePermissions($roleIds, $insertedSubModuleIds, $now);
        }

        $this->command->info("Lease Documentation module created (ID: {$moduleId}) with " . count($subModules) . ' sub-modules.');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Inserts rows into cb_sub_module_permissions for each role × sub-module pair.
     *
     * @param int[]  $roleIds           Role IDs to grant access
     * @param int[]  $subModuleIds      Map of sub-module name → ID
     * @param string $now               Current timestamp string
     */
    private function seedRolePermissions(array $roleIds, array $subModuleIds, string $now): void
    {
        $rows = [];

        foreach ($roleIds as $roleId) {
            foreach ($subModuleIds as $subModuleId) {
                $rows[] = [
                    'role_id'       => $roleId,
                    'sub_module_id' => $subModuleId,
                    'added_by'      => 'seeder',
                    'added_on'      => $now,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        if (! empty($rows)) {
            DB::table('cb_sub_module_permissions')->insert($rows);
        }
    }

    /**
     * Returns the next available module_order value so the Lease module
     * is appended after whatever modules already exist.
     */
    private function nextModuleOrder(): int
    {
        $max = DB::table('cb_modules')->max('module_order');

        return ($max ?? 0) + 1;
    }
}
