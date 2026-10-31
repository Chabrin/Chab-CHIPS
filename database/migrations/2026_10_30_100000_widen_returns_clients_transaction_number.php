<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen cb_returns_clients.transaction_number from 12 to 30 characters. Returns generation copies the
 * client ledger reference into this column, and instalment advance references such as
 * ADV-2026-005/1 and ADV-2026-005/CHG are 14 to 16 characters, so the insert failed and rolled back
 * the whole month's returns. Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cb_returns_clients') || !Schema::hasColumn('cb_returns_clients', 'transaction_number')) {
            return;
        }

        $current = DB::selectOne(
            'SELECT character_maximum_length AS len FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
            ['cb_returns_clients', 'transaction_number']
        );

        if ($current && $current->len !== null && (int) $current->len < 30) {
            DB::statement('ALTER TABLE cb_returns_clients ALTER COLUMN transaction_number TYPE VARCHAR(30)');
        }
    }

    public function down(): void
    {
        // Not reversed: narrowing the column would fail or truncate any longer references already stored.
    }
};
