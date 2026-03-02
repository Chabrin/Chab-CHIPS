<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_pm_movement_requests', function (Blueprint $table) {
            $table->boolean('move_with_balance')->default(false)->after('current_balance');
        });
    }

    public function down(): void
    {
        Schema::table('sys_pm_movement_requests', function (Blueprint $table) {
            $table->dropColumn('move_with_balance');
        });
    }
};
