<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a run is processed, the list of what changed since the last payroll is kept with it, so the accountant who reviews
     * the run sees the same list HR saw. Safe to run again.
     */
    public function up(): void
    {
        if (!Schema::hasTable('payroll_runs') || Schema::hasColumn('payroll_runs', 'changes_snapshot')) {
            return;
        }

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->json('changes_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_runs') && Schema::hasColumn('payroll_runs', 'changes_snapshot')) {
            Schema::table('payroll_runs', fn (Blueprint $table) => $table->dropColumn('changes_snapshot'));
        }
    }
};
