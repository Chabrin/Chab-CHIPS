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
        Schema::table('sys_hr_payroll_elements', function (Blueprint $table) {
            $table->string('element_key')->nullable()->after('element_name');
        });
    }


    public function down(): void
    {
        Schema::table('sys_hr_payroll_elements', function (Blueprint $table) {
            $table->dropColumn('element_key');
        });
    }
};
