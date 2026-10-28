<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only run if the tables exist
        if (!Schema::hasTable('sys_hr_employees_attendance') || !Schema::hasTable('sys_staff')) {
            return;
        }

        // PostgreSQL version (your current DB)
        DB::statement("
            UPDATE sys_hr_employees_attendance AS a
            SET employee_id = s.id
            FROM sys_staff AS s
            WHERE a.employee_id::text = s.staff_number
               OR a.employee_id::text = ltrim(s.staff_number, '0')
        ");

        // Optional safety: log how many rows were updated
        // You can check with: SELECT COUNT(*) FROM sys_hr_employees_attendance;
    }

    public function down(): void
    {
        // Reversing this accurately is hard because we lose the old staff_number mapping.
        // Leave empty or implement only if you really need to roll back.
    }
};