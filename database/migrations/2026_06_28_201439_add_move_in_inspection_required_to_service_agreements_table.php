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
        Schema::table('sys_pm_service_agreements', function (Blueprint $table) {
            $table->boolean('move_in_inspection_required')->default(false)->after('auto_renewal');
        });
    }

    public function down(): void
    {
        Schema::table('sys_pm_service_agreements', function (Blueprint $table) {
            $table->dropColumn('move_in_inspection_required');
        });
    }
};
