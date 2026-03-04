<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement("SET SESSION sql_mode = ''");

        Schema::table('sys_pm_properties_repairs_requests_49_repeat', function (Blueprint $table) {
            $table->enum('repair_scope', ['general', 'unit_specific'])
                  ->default('unit_specific')
                  ->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('sys_pm_properties_repairs_requests_49_repeat', function (Blueprint $table) {
            $table->dropColumn('repair_scope');
        });
    }
};
