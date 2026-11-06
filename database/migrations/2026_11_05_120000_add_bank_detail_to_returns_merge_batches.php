<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A client whose properties are merged is paid into one bank account. Record which of the client's bank details the
 * merged return is paid into. Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cb_returns_merge_batches') && !Schema::hasColumn('cb_returns_merge_batches', 'bank_detail_id')) {
            DB::statement('ALTER TABLE cb_returns_merge_batches ADD COLUMN bank_detail_id INTEGER NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cb_returns_merge_batches', 'bank_detail_id')) {
            DB::statement('ALTER TABLE cb_returns_merge_batches DROP COLUMN bank_detail_id');
        }
    }
};
