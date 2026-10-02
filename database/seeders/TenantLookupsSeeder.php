<?php

namespace Database\Seeders;

use App\Models\Gender;
use App\Models\Salutation;
use Illuminate\Database\Seeder;

class TenantLookupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['MALE', 'FEMALE', 'OTHER'] as $name) {
            Gender::firstOrCreate(['name' => $name]);
        }

        foreach (['Mr', 'Mrs', 'Ms', 'Dr', 'Prof'] as $name) {
            Salutation::firstOrCreate(['name' => $name]);
        }
    }
}
