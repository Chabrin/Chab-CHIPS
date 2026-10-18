<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pro-rata annual leave is fractional (a staff member hired in October gets 5.25 days), so the plan's
     * entitled_days can no longer be a whole number.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('sys_hr_leave_calendar', 'entitled_days') || !$this->isWholeNumber()) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sys_hr_leave_calendar ALTER COLUMN entitled_days TYPE numeric(5,2)');
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sys_hr_leave_calendar MODIFY entitled_days DECIMAL(5,2) NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('sys_hr_leave_calendar', 'entitled_days') || $this->isWholeNumber()) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sys_hr_leave_calendar ALTER COLUMN entitled_days TYPE integer USING round(entitled_days)');
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sys_hr_leave_calendar MODIFY entitled_days INT NULL');
        }
    }

    /**
     * Whether the column is still an integer type.
     */
    private function isWholeNumber(): bool
    {
        return in_array(strtolower(Schema::getColumnType('sys_hr_leave_calendar', 'entitled_days')), ['int', 'int4', 'integer', 'bigint', 'int8', 'smallint', 'int2', 'tinyint'], true);
    }
};
