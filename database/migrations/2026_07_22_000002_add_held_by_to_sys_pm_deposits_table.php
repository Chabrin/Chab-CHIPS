<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_pm_deposits', function (Blueprint $table) {
            // Which party actually holds this deposit — distinct from whether a deposit
            // exists at all. NULL only for historical rows predating this column; every
            // new deposit captured going forward is required to set this explicitly.
            $table->string('held_by', 20)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('sys_pm_deposits', function (Blueprint $table) {
            $table->dropColumn('held_by');
        });
    }
};
