<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_pm_recoveries', function (Blueprint $table) {
            $table->timestamp('posted_to_landlord_at')->nullable()->after('current_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('sys_pm_recoveries', function (Blueprint $table) {
            $table->dropColumn('posted_to_landlord_at');
        });
    }
};
