<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds a unique `slug` (the legacy unit_type_id code, in caps) to sys_pm_unit_topologies and
 * upserts the known topologies. Never deletes; unlisted legacy codes are left to a later cleanup.
 */
return new class extends Migration
{
    /** Slug (legacy unit_type_id code, caps) => topology name. */
    private const KNOWN_NAMES = [
        'BS' => 'Bedsitter',
        'STUDIO' => 'Studio',
        'BR' => '1 Bedroom',
        '2 BR' => '2 Bedroom',
        '3BR' => '3 Bedroom',
        '4BR' => '4 Bedroom',
        'OPEN PLAN' => 'Open Plan',
        'SR' => 'Single Room',
        'SH' => 'Shop',
        'DR' => 'Double Room',
        'BNG' => 'Bungalow',
    ];

    /**
     * Add the slug column, then update-or-insert every topology in KNOWN_NAMES.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('sys_pm_unit_topologies', 'slug')) {
            Schema::table('sys_pm_unit_topologies', function (Blueprint $table) {
                $table->string('slug', 50)->nullable()->unique();
            });
        }

        // Keep the id sequence ahead of the rows so inserts can't collide.
        if (DB::getDriverName() === 'pgsql') {
            $sequence = DB::selectOne("SELECT pg_get_serial_sequence('sys_pm_unit_topologies', 'id') AS seq")->seq ?? null;
            if ($sequence) {
                DB::statement("SELECT setval(?, COALESCE((SELECT MAX(id) FROM sys_pm_unit_topologies), 0) + 1, false)", [$sequence]);
            }
        }

        foreach (self::KNOWN_NAMES as $slug => $name) {
            $this->upsertTopology($slug, $name);
        }
    }

    /**
     * Skip if the slug exists, slug a same-named row that has none, otherwise insert.
     */
    private function upsertTopology($slug, $name): void
    {
        if (DB::table('sys_pm_unit_topologies')->whereRaw('UPPER(slug) = ?', [strtoupper($slug)])->exists()) {
            return;
        }

        $unslugged = DB::table('sys_pm_unit_topologies')
            ->whereRaw('LOWER(topology_name) = ?', [strtolower($name)])
            ->whereNull('slug')
            ->value('id');

        if ($unslugged) {
            DB::table('sys_pm_unit_topologies')->where('id', $unslugged)->update(['slug' => $slug]);
        } else {
            DB::table('sys_pm_unit_topologies')->insert(['topology_name' => $name, 'slug' => $slug]);
        }
    }

    /**
     * Drop the slug column; added topologies are kept since units may point at them.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('sys_pm_unit_topologies', 'slug')) {
            return;
        }

        Schema::table('sys_pm_unit_topologies', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
