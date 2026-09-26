<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Speeds up the Arrears report's filter (current_status_id had no index at all).
     * No date-range truncation — this ledger has no opening-balance snapshot to make that safe.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sys_pm_tenants_accounts_trans')) {
            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS idx_tenants_acct_txn_status_date ON sys_pm_tenants_accounts_trans (current_status_id, date_time)');
    }

    /**
     * Drop the composite index.
     */
    public function down(): void
    {
        if (!Schema::hasTable('sys_pm_tenants_accounts_trans')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_tenants_acct_txn_status_date');
    }
};
