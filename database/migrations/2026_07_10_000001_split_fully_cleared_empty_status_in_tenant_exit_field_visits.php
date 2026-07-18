<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cb_tenant_exit_field_visits')) {
            return;
        }

        $constraint = DB::selectOne("
            SELECT con.conname
            FROM pg_constraint con
            JOIN pg_class rel ON rel.oid = con.conrelid
            JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
            WHERE rel.relname = 'cb_tenant_exit_field_visits'
              AND con.contype = 'c'
              AND pg_get_constraintdef(con.oid) LIKE '%unit_status_found%'
        ");

        if ($constraint) {
            DB::statement("ALTER TABLE cb_tenant_exit_field_visits DROP CONSTRAINT \"{$constraint->conname}\"");
        }

        DB::statement("
            ALTER TABLE cb_tenant_exit_field_visits
            ADD CONSTRAINT cb_tenant_exit_field_visits_unit_status_found_check
            CHECK (unit_status_found IN ('Occupied', 'Partially Cleared', 'Fully Cleared/Empty', 'Fully Cleared', 'Empty'))
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('cb_tenant_exit_field_visits')) {
            return;
        }

        DB::statement('ALTER TABLE cb_tenant_exit_field_visits DROP CONSTRAINT IF EXISTS cb_tenant_exit_field_visits_unit_status_found_check');
        DB::statement("
            ALTER TABLE cb_tenant_exit_field_visits
            ADD CONSTRAINT cb_tenant_exit_field_visits_unit_status_found_check
            CHECK (unit_status_found IN ('Occupied', 'Partially Cleared', 'Fully Cleared/Empty'))
        ");
    }
};
