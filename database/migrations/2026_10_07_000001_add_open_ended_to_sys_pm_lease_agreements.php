<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Mark leases that deliberately have no end date, so they can be told apart from legacy leases that are just missing one.
 */
return new class extends Migration
{
    /**
     * Add the open_ended flag if it is missing.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sys_pm_lease_agreements') || Schema::hasColumn('sys_pm_lease_agreements', 'open_ended')) {
            return;
        }

        Schema::table('sys_pm_lease_agreements', function (Blueprint $table) {
            $table->boolean('open_ended')->default(false);
        });
    }

    /**
     * Drop the open_ended flag if present.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sys_pm_lease_agreements', 'open_ended')) {
            Schema::table('sys_pm_lease_agreements', function (Blueprint $table) {
                $table->dropColumn('open_ended');
            });
        }
    }
};
