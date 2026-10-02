<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // cb_tenant_records.id (1..73625, auto-increment) and legacy_client_id
        // (sparse, 1214..74841) overlap heavily, so a plain `UPDATE ... SET id =
        // legacy_client_id` would hit a duplicate-key error mid-statement — row A's
        // new id can equal row B's still-unupdated current id. Flip through the
        // negative range first (negatives can never collide with the existing
        // positive ids, and legacy_client_id is already unique so the negated
        // values stay unique too), then flip back to positive in a second pass.
        DB::statement('UPDATE cb_tenant_records SET id = -legacy_client_id');
        DB::statement('UPDATE cb_tenant_records SET id = -id');

        DB::statement("SELECT setval(pg_get_serial_sequence('cb_tenant_records', 'id'), (SELECT MAX(id) FROM cb_tenant_records))");

        Schema::table('cb_tenant_records', function (Blueprint $table) {
            $table->dropForeign(['legacy_client_id']);
            $table->dropColumn('legacy_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('cb_tenant_records', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_client_id')->nullable()->after('id');
        });

        // Best-effort only: id equals the original legacy_client_id for every row
        // that existed when up() ran, so this recreates that mapping — it can't
        // distinguish rows inserted after this migration (none should exist, since
        // this table isn't wired into the app yet).
        DB::statement('UPDATE cb_tenant_records SET legacy_client_id = id');

        Schema::table('cb_tenant_records', function (Blueprint $table) {
            $table->foreign('legacy_client_id')->references('id')->on('sys_pm_clients')->nullOnDelete();
        });
    }
};
