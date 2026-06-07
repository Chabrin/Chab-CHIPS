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
        Schema::table('sys_gl_chart_accounts_master', function (Blueprint $table) {
            $table->unique('account_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_gl_chart_accounts_master', function (Blueprint $table) {
            $table->dropUnique(['account_code']);
        });
    }
};
