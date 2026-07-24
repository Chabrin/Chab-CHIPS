<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employment_termination', function (Blueprint $table) {
            $table->unsignedBigInteger('discipline_case_id')->nullable()->after('staff_id');
            $table->unsignedBigInteger('discipline_outcome_id')->nullable()->after('discipline_case_id');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employment_termination', function (Blueprint $table) {
            $table->dropColumn(['discipline_case_id', 'discipline_outcome_id']);
        });
    }
};
