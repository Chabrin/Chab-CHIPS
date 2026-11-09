<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Payroll reads NSSF from HR Settings > NSSF Rates: the rate, where Tier I ends and where Tier II ends, from a start date.
    public function up(): void
    {
        if (!Schema::hasTable('sys_hr_nssf_rates')) {
            return;
        }

        Schema::table('sys_hr_nssf_rates', function (Blueprint $table) {
            if (!Schema::hasColumn('sys_hr_nssf_rates', 'lower_earnings_limit')) {
                $table->decimal('lower_earnings_limit', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('sys_hr_nssf_rates', 'upper_earnings_limit')) {
                $table->decimal('upper_earnings_limit', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('sys_hr_nssf_rates', 'tier1_name')) {
                $table->string('tier1_name', 60)->nullable();
            }
            if (!Schema::hasColumn('sys_hr_nssf_rates', 'tier2_name')) {
                $table->string('tier2_name', 60)->nullable();
            }
        });

        // Rows brought over from the old system may have kept their own ids and left the id counter behind,
        // which would stop the screen adding a rate.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("select setval(pg_get_serial_sequence('sys_hr_nssf_rates', 'id'), coalesce((select max(id) from sys_hr_nssf_rates), 0) + 1, false)");
        }

        // Start the screen with the figures payroll has been using, so pay does not change. Older rows have no limits and are not used.
        if (!DB::table('sys_hr_nssf_rates')->whereNotNull('lower_earnings_limit')->exists()) {
            $percent = round((float) config('nssf.rate') * 100, 2);
            DB::table('sys_hr_nssf_rates')->insert([
                'from_date' => '2026-02-01 00:00:00',
                'employee_perc' => $percent,
                'employer_perc' => $percent,
                'lower_earnings_limit' => config('nssf.lower_earnings_limit'),
                'upper_earnings_limit' => config('nssf.upper_earnings_limit'),
                'maximum_contribution' => round((float) config('nssf.rate') * (float) config('nssf.upper_earnings_limit'), 2),
                'tier1_name' => config('nssf.tier1_label'),
                'tier2_name' => config('nssf.tier2_label'),
                'current_status_id' => 5,
                'date_time' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('sys_hr_nssf_rates')) {
            return;
        }

        foreach (['lower_earnings_limit', 'upper_earnings_limit', 'tier1_name', 'tier2_name'] as $column) {
            if (Schema::hasColumn('sys_hr_nssf_rates', $column)) {
                Schema::table('sys_hr_nssf_rates', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
