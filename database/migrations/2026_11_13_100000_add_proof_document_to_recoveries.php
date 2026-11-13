<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep the proof document of a recovery (the landlord's signed acknowledgement or receipt, a PDF) with the recovery:
 * where the file is stored, its original name, who attached it and when. Same four columns as the advance loan form.
 * Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sys_pm_recoveries')) {
            return;
        }

        $columns = [
            'proof_path' => 'VARCHAR(255) NULL',
            'proof_name' => 'VARCHAR(255) NULL',
            'proof_uploaded_by' => 'BIGINT NULL',
            'proof_uploaded_at' => 'TIMESTAMP(0) WITHOUT TIME ZONE NULL',
        ];

        foreach ($columns as $name => $definition) {
            if (!Schema::hasColumn('sys_pm_recoveries', $name)) {
                DB::statement("ALTER TABLE sys_pm_recoveries ADD COLUMN {$name} {$definition}");
            }
        }
    }

    public function down(): void
    {
        foreach (['proof_path', 'proof_name', 'proof_uploaded_by', 'proof_uploaded_at'] as $name) {
            if (Schema::hasColumn('sys_pm_recoveries', $name)) {
                DB::statement("ALTER TABLE sys_pm_recoveries DROP COLUMN {$name}");
            }
        }
    }
};
