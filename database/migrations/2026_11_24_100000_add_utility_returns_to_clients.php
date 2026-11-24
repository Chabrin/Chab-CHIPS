<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How a client wants utilities on the return: with the rent (the default, one return and one report), as a separate
 * report (one return and one payment, with a rent report and a utility report), or as a separate return (a rent return
 * and a utility return, each paid on its own). Every client starts on "with_rent", so no existing return changes.
 * Safe to run more than once.
 */
return new class extends Migration
{
    private const TABLE = 'sys_pm_clients';
    private const COLUMN = 'utility_returns';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        // Raw statement: a column added with a constant default is instant on PostgreSQL, even on a large table
        DB::statement('ALTER TABLE ' . self::TABLE . " ADD COLUMN " . self::COLUMN . " VARCHAR(20) NOT NULL DEFAULT 'with_rent'");
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TABLE) && Schema::hasColumn(self::TABLE, self::COLUMN)) {
            DB::statement('ALTER TABLE ' . self::TABLE . ' DROP COLUMN ' . self::COLUMN);
        }
    }
};
