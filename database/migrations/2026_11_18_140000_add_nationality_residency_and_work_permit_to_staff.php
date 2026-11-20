<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Citizenship and residency are two answers, not one: the country of citizenship first, then whether the person is a tax resident.
 * A staff member from another country also has a work permit (or Alien ID) with an expiry date, which HR is reminded about.
 *
 * - nationality: the country of citizenship.
 * - residency_status: RESIDENT or NON_RESIDENT.
 * - work_permit_number / work_permit_expiry: for staff who are not Kenyan.
 *
 * The old citizenship column (Resident / NO) stays and keeps being written, so anything reading it is unchanged. Existing staff are
 * filled in from it: "Resident" becomes Kenya and Resident; "NO" becomes Non-resident with the country left for HR to fill in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_staff', function (Blueprint $table) {
            if (!Schema::hasColumn('sys_staff', 'nationality')) {
                $table->string('nationality', 100)->nullable();
            }
            if (!Schema::hasColumn('sys_staff', 'residency_status')) {
                $table->string('residency_status', 20)->nullable();
            }
            if (!Schema::hasColumn('sys_staff', 'work_permit_number')) {
                $table->string('work_permit_number', 100)->nullable();
            }
            if (!Schema::hasColumn('sys_staff', 'work_permit_expiry')) {
                $table->date('work_permit_expiry')->nullable();
            }
        });

        DB::table('sys_staff')->whereNull('residency_status')->whereRaw("LOWER(citizenship) = 'resident'")
            ->update(['residency_status' => 'RESIDENT', 'nationality' => 'Kenya']);
        DB::table('sys_staff')->whereNull('residency_status')->whereRaw("UPPER(citizenship) = 'NO'")
            ->update(['residency_status' => 'NON_RESIDENT']);
    }

    public function down(): void
    {
        Schema::table('sys_staff', function (Blueprint $table) {
            foreach (['nationality', 'residency_status', 'work_permit_number', 'work_permit_expiry'] as $column) {
                if (Schema::hasColumn('sys_staff', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
