<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE SEQUENCE IF NOT EXISTS chips_db_v3.sys_set_bank_accounts_id_seq");
        DB::statement("ALTER TABLE sys_set_bank_accounts ALTER COLUMN id SET DEFAULT nextval('chips_db_v3.sys_set_bank_accounts_id_seq'::regclass)");
        DB::statement("ALTER SEQUENCE chips_db_v3.sys_set_bank_accounts_id_seq OWNED BY chips_db_v3.sys_set_bank_accounts.id");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sys_set_bank_accounts ALTER COLUMN id DROP DEFAULT");
        DB::statement("DROP SEQUENCE IF EXISTS chips_db_v3.sys_set_bank_accounts_id_seq");
    }
};
