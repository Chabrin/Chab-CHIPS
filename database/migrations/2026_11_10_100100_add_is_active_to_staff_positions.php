<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // A position can be switched off so it is no longer offered for new staff, without deleting it from the staff who hold it.
    // current_status_id is not used for this: it already answers a different question (approved or pending).
    public function up(): void
    {
        if (Schema::hasTable('sys_set_staff_positions') && !Schema::hasColumn('sys_set_staff_positions', 'is_active')) {
            Schema::table('sys_set_staff_positions', function (Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sys_set_staff_positions', 'is_active')) {
            Schema::table('sys_set_staff_positions', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
