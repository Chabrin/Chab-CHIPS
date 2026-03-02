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
        Schema::table('sys_pm_clients_returns', function (Blueprint $table) {
            $table->string('batch_id', 50)->nullable()
                ->after('commisionable')
                ->comment('Format: YYYYMM-PropertyID (e.g., 202601-101)');

            $table->index('batch_id', 'idx_batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_pm_clients_returns', function (Blueprint $table) {
            $table->dropIndex('idx_batch_id');
            $table->dropColumn('batch_id');
        });
    }
};
