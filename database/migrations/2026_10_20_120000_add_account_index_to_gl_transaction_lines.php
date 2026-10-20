<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index the account column on the GL journal lines. The account balances and the account ledger
 * look lines up by account, and without this index each lookup reads the whole table.
 * Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sys_gl_transactions_91_repeat') && Schema::hasColumn('sys_gl_transactions_91_repeat', 'account_id')) {
            DB::statement('CREATE INDEX IF NOT EXISTS sys_gl_lines_account_id_index ON sys_gl_transactions_91_repeat (account_id)');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sys_gl_lines_account_id_index');
    }
};
