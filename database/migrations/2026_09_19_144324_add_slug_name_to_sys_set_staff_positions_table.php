<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sys_set_staff_positions', function (Blueprint $table) {
            $table->string('slug_name')->nullable()->after('position_name');
        });

        DB::table('sys_set_staff_positions')->select('id', 'position_name')->orderBy('id')->each(function ($position) {
            DB::table('sys_set_staff_positions')
                ->where('id', $position->id)
                ->update(['slug_name' => Str::slug($position->position_name, '_')]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_set_staff_positions', function (Blueprint $table) {
            $table->dropColumn('slug_name');
        });
    }
};
