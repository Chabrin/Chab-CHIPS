<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('discipline_case_registrations')) {
            return;
        }

        // priority is meaningless for a case rejected at registration (decision=Reject);
        // was NOT NULL because every registration used to proceed.
        DB::statement('ALTER TABLE discipline_case_registrations ALTER COLUMN priority DROP NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('discipline_case_registrations')) {
            return;
        }

        DB::statement("UPDATE discipline_case_registrations SET priority = 'Medium' WHERE priority IS NULL");
        DB::statement('ALTER TABLE discipline_case_registrations ALTER COLUMN priority SET NOT NULL');
    }
};
