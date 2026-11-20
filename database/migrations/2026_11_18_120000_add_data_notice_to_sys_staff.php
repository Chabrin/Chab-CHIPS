<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff are shown a notice of what personal data the company collects and why, and their agreement is recorded with its date.
 * Staff added before this stay empty (agreement not recorded).
 *
 * - data_notice_agreed_at: when the staff member agreed (online, in the onboarding link) or when HR recorded that they did.
 * - data_notice_recorded_by: the HR user who recorded it for them; empty when the staff member agreed themselves online.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_staff', function (Blueprint $table) {
            if (!Schema::hasColumn('sys_staff', 'data_notice_agreed_at')) {
                $table->timestamp('data_notice_agreed_at')->nullable();
            }
            if (!Schema::hasColumn('sys_staff', 'data_notice_recorded_by')) {
                $table->unsignedBigInteger('data_notice_recorded_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sys_staff', function (Blueprint $table) {
            foreach (['data_notice_agreed_at', 'data_notice_recorded_by'] as $column) {
                if (Schema::hasColumn('sys_staff', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
