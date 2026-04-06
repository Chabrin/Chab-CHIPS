<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SuperModuleSeeder extends Seeder
{
    public function run(): void
    {
        $superModules = [
            ['name' => 'Management',     'icon' => 'admin_panel_settings', 'order' => 1],
            ['name' => 'Accounting',     'icon' => 'account_balance',      'order' => 2],
            ['name' => 'HR',             'icon' => 'groups',               'order' => 3],
            ['name' => 'Auditing',       'icon' => 'verified',             'order' => 4],
            ['name' => 'GIS',            'icon' => 'map',                  'order' => 5],
            ['name' => 'Configurations', 'icon' => 'tune',                 'order' => 6],
            ['name' => 'System Setup',   'icon' => 'settings',             'order' => 7],
            ['name' => 'Reports',        'icon' => 'analytics',            'order' => 8],
        ];

        foreach ($superModules as $sm) {
            DB::table('cb_super_modules')->updateOrInsert(
                ['name' => $sm['name']],
                array_merge($sm, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
