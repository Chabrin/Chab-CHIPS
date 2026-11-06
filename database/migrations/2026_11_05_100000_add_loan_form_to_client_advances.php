<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep the signed loan form with the advance: where the file is stored, its original name, who
 * attached it and when. Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sys_pm_clients_advances_requisitions')) {
            return;
        }

        $columns = [
            'loan_form_path' => 'VARCHAR(255) NULL',
            'loan_form_name' => 'VARCHAR(255) NULL',
            'loan_form_uploaded_by' => 'INTEGER NULL',
            'loan_form_uploaded_at' => 'TIMESTAMP NULL',
        ];

        foreach ($columns as $name => $definition) {
            if (!Schema::hasColumn('sys_pm_clients_advances_requisitions', $name)) {
                DB::statement("ALTER TABLE sys_pm_clients_advances_requisitions ADD COLUMN {$name} {$definition}");
            }
        }
    }

    public function down(): void
    {
        foreach (['loan_form_path', 'loan_form_name', 'loan_form_uploaded_by', 'loan_form_uploaded_at'] as $name) {
            if (Schema::hasColumn('sys_pm_clients_advances_requisitions', $name)) {
                DB::statement("ALTER TABLE sys_pm_clients_advances_requisitions DROP COLUMN {$name}");
            }
        }
    }
};
