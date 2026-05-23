<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the default first — PostgreSQL cannot cast it automatically
        DB::statement('ALTER TABLE sys_set_transactions_types ALTER COLUMN is_recovery DROP DEFAULT');
        DB::statement('ALTER TABLE sys_set_transactions_types ALTER COLUMN is_recovery TYPE integer USING is_recovery::integer');
        DB::statement('ALTER TABLE sys_set_transactions_types ALTER COLUMN is_recovery SET DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sys_set_transactions_types ALTER COLUMN is_recovery DROP DEFAULT');
        DB::statement('ALTER TABLE sys_set_transactions_types ALTER COLUMN is_recovery TYPE boolean USING is_recovery::boolean');
        DB::statement('ALTER TABLE sys_set_transactions_types ALTER COLUMN is_recovery SET DEFAULT false');
    }
};
