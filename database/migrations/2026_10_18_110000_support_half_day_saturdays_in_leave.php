<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Columns that held whole days and now need to hold half days: table => [column => precision]. */
    private const COLUMNS = [
        'sys_hr_leave_requests' => ['days' => 6, 'requested_days' => 6],
        'sys_hr_leave_calendar_slots' => ['days_count' => 5],
    ];

    /**
     * Saturday is a half working day, so leave can be charged in half days: widen the whole-number day columns
     * and record how much of a day each leave day charged (1 for a full day, 0.5 for a Saturday).
     */
    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column => $precision) {
                if (Schema::hasColumn($table, $column) && $this->isWholeNumber($table, $column)) {
                    $this->widen($table, $column, $precision);
                }
            }
        }

        if (Schema::hasTable('sys_hr_leave_requests_234_repeat') && !Schema::hasColumn('sys_hr_leave_requests_234_repeat', 'day_fraction')) {
            Schema::table('sys_hr_leave_requests_234_repeat', function (Blueprint $table) {
                $table->decimal('day_fraction', 3, 2)->default(1);
            });
        }
    }

    /**
     * Drop the per-day amount and put the day columns back to whole numbers.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sys_hr_leave_requests_234_repeat', 'day_fraction')) {
            Schema::table('sys_hr_leave_requests_234_repeat', function (Blueprint $table) {
                $table->dropColumn('day_fraction');
            });
        }

        foreach (self::COLUMNS as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                if (Schema::hasColumn($table, $column) && !$this->isWholeNumber($table, $column)) {
                    $this->narrow($table, $column);
                }
            }
        }
    }

    /**
     * Whether a column is still an integer type.
     */
    private function isWholeNumber(string $table, string $column): bool
    {
        return in_array(strtolower(Schema::getColumnType($table, $column)), ['int', 'int4', 'integer', 'bigint', 'int8', 'smallint', 'int2', 'tinyint'], true);
    }

    /**
     * Change an integer column to a two-decimal number.
     */
    private function widen(string $table, string $column, int $precision): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE numeric({$precision},2)");
        } elseif (DB::getDriverName() === 'mysql') {
            $null = $column === 'days_count' ? 'NOT NULL' : 'NULL';
            DB::statement("ALTER TABLE {$table} MODIFY {$column} DECIMAL({$precision},2) {$null}");
        }
    }

    /**
     * Change a two-decimal column back to a whole number.
     */
    private function narrow(string $table, string $column): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE integer USING round({$column})");
        } elseif (DB::getDriverName() === 'mysql') {
            $null = $column === 'days_count' ? 'NOT NULL' : 'NULL';
            DB::statement("ALTER TABLE {$table} MODIFY {$column} INT {$null}");
        }
    }
};
