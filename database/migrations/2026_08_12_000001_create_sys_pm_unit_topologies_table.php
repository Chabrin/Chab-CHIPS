<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_pm_unit_topologies', function (Blueprint $table) {
            $table->id();
            $table->string('topology_name');
        });

        DB::table('sys_pm_unit_topologies')->insert([
            ['topology_name' => 'Bedsitter'],
            ['topology_name' => 'Studio'],
            ['topology_name' => '1 Bedroom'],
            ['topology_name' => '2 Bedroom'],
            ['topology_name' => '3 Bedroom'],
            ['topology_name' => '4 Bedroom'],
            ['topology_name' => 'Open Plan'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_pm_unit_topologies');
    }
};
