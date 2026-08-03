<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_pm_clients_advances_requisitions_89_repeat', function (Blueprint $table) {
            $table->boolean('is_restructured')->default(false)->after('current_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('sys_pm_clients_advances_requisitions_89_repeat', function (Blueprint $table) {
            $table->dropColumn('is_restructured');
        });
    }
};
