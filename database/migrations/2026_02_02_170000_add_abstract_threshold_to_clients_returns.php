<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add abstract_threshold column to track historical/estimated threshold
     * This is separate from threshold_percentage (actual payment threshold)
     */
    public function up(): void
    {
        Schema::table('sys_pm_clients_returns', function (Blueprint $table) {
            $table->decimal('abstract_threshold', 10, 2)
                ->default(0.00)
                ->after('threshold_percentage')
                ->comment('Estimated threshold based on historical payment patterns');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_pm_clients_returns', function (Blueprint $table) {
            $table->dropColumn('abstract_threshold');
        });
    }
};
