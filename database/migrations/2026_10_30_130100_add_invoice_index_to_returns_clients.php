<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index cb_returns_clients.invoice_id. Returns generation now looks up, per invoice, what earlier returns already
 * passed on to the landlord, and without this index that lookup scans the whole lines table once per invoice.
 * Safe to run more than once.
 */
return new class extends Migration
{
    /**
     * Create the partial index when the table and column exist and the index is not there yet.
     */
    public function up(): void {
        if (DB::getDriverName() !== 'pgsql' || !Schema::hasTable('cb_returns_clients') || !Schema::hasColumn('cb_returns_clients', 'invoice_id')) {
            return;
        }

        // Give up after 10 seconds rather than queue behind a long write and hold up the table. Run it again when quiet.
        DB::statement("SET LOCAL lock_timeout = '10s'");
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cb_returns_clients_invoice ON cb_returns_clients (invoice_id) WHERE invoice_id IS NOT NULL');
    }

    /**
     * Drop the index.
     */
    public function down(): void {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_cb_returns_clients_invoice');
        }
    }
};
