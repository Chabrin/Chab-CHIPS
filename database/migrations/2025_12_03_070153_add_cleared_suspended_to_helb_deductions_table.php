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
        Schema::table('sys_helb_deductions', function (Blueprint $table) {
            $table->integer('cleared')->default(0)->after('start_deduction_date');
            $table->integer('suspended')->default(0)->after('cleared');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_helb_deductions', function (Blueprint $table) {
            $table->dropColumn(['cleared', 'suspended']);
        });
    }
};
