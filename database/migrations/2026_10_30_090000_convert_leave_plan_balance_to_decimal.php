<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sys_hr_leave_calendar', 'balance')) {
            return;
        }

        $type = strtolower(Schema::getColumnType('sys_hr_leave_calendar', 'balance'));
        if (!in_array($type, ['string', 'varchar', 'character varying', 'text'], true)) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sys_hr_leave_calendar ALTER COLUMN balance TYPE numeric(8,2) USING NULLIF(BTRIM(balance), '')::numeric");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sys_hr_leave_calendar MODIFY balance DECIMAL(8,2) NULL");
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('sys_hr_leave_calendar', 'balance')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql' && Schema::getColumnType('sys_hr_leave_calendar', 'balance') === 'numeric') {
            DB::statement('ALTER TABLE sys_hr_leave_calendar ALTER COLUMN balance TYPE varchar(255) USING balance::text');
        } elseif (DB::getDriverName() === 'mysql' && Schema::getColumnType('sys_hr_leave_calendar', 'balance') !== 'string') {
            DB::statement('ALTER TABLE sys_hr_leave_calendar MODIFY balance VARCHAR(255) NULL');
        }
    }
};
