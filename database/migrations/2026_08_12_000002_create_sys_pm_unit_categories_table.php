<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_pm_unit_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_name');
        });

        DB::table('sys_pm_unit_categories')->insert([
            ['category_name' => 'Standard'],
            ['category_name' => 'Premium'],
            ['category_name' => 'Economy'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_pm_unit_categories');
    }
};
