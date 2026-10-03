<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Lookup table for cb_tenant_records.tenant_type, which keeps storing the name.
 */
return new class extends Migration
{
    /** id => name. */
    private const VALUES = [
        1 => 'INDIVIDUAL',
        2 => 'COMPANY',
        3 => 'CARETAKER',
        4 => 'OWNERS',
    ];

    /**
     * Create the table if missing, then insert any agreed rows that are not there yet.
     */
    public function up(): void
    {
        if (!Schema::hasTable('cb_tenant_types')) {
            Schema::create('cb_tenant_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 20)->unique();
                $table->string('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->string('deleted_by')->nullable();
            });
        }

        foreach (self::VALUES as $id => $name) {
            if (!DB::table('cb_tenant_types')->where('id', $id)->exists()) {
                DB::table('cb_tenant_types')->insert(['id' => $id, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // Move the sequence past the explicit ids.
        if (DB::getDriverName() === 'pgsql') {
            $sequence = DB::selectOne("SELECT pg_get_serial_sequence('cb_tenant_types', 'id') AS seq")->seq ?? null;
            if ($sequence) {
                DB::statement("SELECT setval(?, COALESCE((SELECT MAX(id) FROM cb_tenant_types), 0) + 1, false)", [$sequence]);
            }
        }
    }

    /**
     * Drop the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_tenant_types');
    }
};
