<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypesTableSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'National ID / Passport', 'is_mandatory' => true],
            ['name' => 'KRA PIN Certificate', 'is_mandatory' => true],
            ['name' => 'SHA Card / Registration', 'is_mandatory' => false],
            ['name' => 'NSSF Card', 'is_mandatory' => false],
            ['name' => 'Signed Employment Contract', 'is_mandatory' => true],
            ['name' => 'Academic Certificates & Transcripts', 'is_mandatory' => false],
            ['name' => 'Professional Certificates / Licenses', 'is_mandatory' => false],
            ['name' => 'Disciplinary / Show Cause Letter', 'is_mandatory' => false],
            ['name' => 'Curriculum Vitae (CV)', 'is_mandatory' => false],
        ];

        foreach ($types as $type) {
            DocumentType::firstOrCreate(['name' => $type['name']], $type);
        }
    }
}