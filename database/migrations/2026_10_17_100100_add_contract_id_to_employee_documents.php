<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link an uploaded document to the contract it is the signed copy of, and make sure the
     * document types the staff forms rely on exist.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('employee_documents', 'contract_id')) {
            Schema::table('employee_documents', function (Blueprint $table) {
                $table->unsignedBigInteger('contract_id')->nullable()->index();
            });
        }

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
            if (!DB::table('document_types')->where('name', $type['name'])->exists()) {
                DB::table('document_types')->insert($type + ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_documents', 'contract_id')) {
            Schema::table('employee_documents', function (Blueprint $table) {
                $table->dropColumn('contract_id');
            });
        }
    }
};
