<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sys_hr_employee_amortized_deductions', function (Blueprint $table) {
            $table->integer('cleared')->default(0)->after('current_status_id');
            $table->integer('suspended')->default(0)->after('cleared');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_hr_employee_amortized_deductions', function (Blueprint $table) {
            $table->dropColumn(['cleared', 'suspended']);
        });
    }
};
