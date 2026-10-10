<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Staged tenant rows get an optional lease end date; blank means an open-ended lease.
 */
return new class extends Migration
{
    /**
     * Add the lease_end_date column if it is missing.
     */
    public function up(): void
    {
        if (!Schema::hasTable('temp_batch_tenants') || Schema::hasColumn('temp_batch_tenants', 'lease_end_date')) {
            return;
        }

        Schema::table('temp_batch_tenants', function (Blueprint $table) {
            $table->string('lease_end_date')->nullable()->after('lease_start_date');
        });
    }

    /**
     * Drop the lease_end_date column if present.
     */
    public function down(): void
    {
        if (Schema::hasColumn('temp_batch_tenants', 'lease_end_date')) {
            Schema::table('temp_batch_tenants', function (Blueprint $table) {
                $table->dropColumn('lease_end_date');
            });
        }
    }
};
