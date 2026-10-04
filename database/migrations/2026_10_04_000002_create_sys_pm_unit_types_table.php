<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Lookup table for sys_pm_units.unit_type_id; ids match what the Add/Edit Unit form already saves.
 */
return new class extends Migration
{
    /** id => name. */
    private const VALUES = [
        1 => 'Normal',
        2 => 'Owner Occupied',
        3 => 'Caretaker',
    ];

    /**
     * Create the table if missing, then insert any agreed rows that are not there yet.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sys_pm_unit_types')) {
            Schema::create('sys_pm_unit_types', function (Blueprint $table) {
                $table->id();
                $table->string('type_name');
            });
        }

        foreach (self::VALUES as $id => $name) {
            if (!DB::table('sys_pm_unit_types')->where('id', $id)->exists()) {
                DB::table('sys_pm_unit_types')->insert(['id' => $id, 'type_name' => $name]);
            }
        }

        // Move the sequence past the explicit ids.
        if (DB::getDriverName() === 'pgsql') {
            $sequence = DB::selectOne("SELECT pg_get_serial_sequence('sys_pm_unit_types', 'id') AS seq")->seq ?? null;
            if ($sequence) {
                DB::statement("SELECT setval(?, COALESCE((SELECT MAX(id) FROM sys_pm_unit_types), 0) + 1, false)", [$sequence]);
            }
        }
    }

    /**
     * Drop the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_pm_unit_types');
    }
};
