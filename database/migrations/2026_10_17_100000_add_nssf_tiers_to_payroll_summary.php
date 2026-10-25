<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the NSSF Tier I / Tier II split alongside the NSSF total on each payroll summary.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('sys_hr_payroll_summary', 'nssf_tier1')) {
            Schema::table('sys_hr_payroll_summary', function (Blueprint $table) {
                $table->decimal('nssf_tier1', 12, 2)->nullable();
            });
        }

        if (!Schema::hasColumn('sys_hr_payroll_summary', 'nssf_tier2')) {
            Schema::table('sys_hr_payroll_summary', function (Blueprint $table) {
                $table->decimal('nssf_tier2', 12, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['nssf_tier1', 'nssf_tier2'] as $column) {
            if (Schema::hasColumn('sys_hr_payroll_summary', $column)) {
                Schema::table('sys_hr_payroll_summary', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
