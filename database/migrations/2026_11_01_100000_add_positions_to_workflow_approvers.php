<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A level of an approval workflow can be a position instead of a named person: the manager of the return's zone
     * (position_type zone_manager) or everyone holding a role (position_type role, role_id). Such a row has no approver_id.
     */
    public function up(): void
    {
        if (!Schema::hasTable('workflow_approvers')) {
            return;
        }

        if (!Schema::hasColumn('workflow_approvers', 'position_type')) {
            Schema::table('workflow_approvers', function ($table) {
                $table->string('position_type', 20)->nullable();
            });
        }

        if (!Schema::hasColumn('workflow_approvers', 'role_id')) {
            Schema::table('workflow_approvers', function ($table) {
                $table->unsignedBigInteger('role_id')->nullable();
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE workflow_approvers ALTER COLUMN approver_id DROP NOT NULL');
        } else {
            Schema::table('workflow_approvers', function ($table) {
                $table->unsignedBigInteger('approver_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('workflow_approvers')) {
            return;
        }

        if (Schema::hasColumn('workflow_approvers', 'position_type')) {
            Schema::table('workflow_approvers', function ($table) {
                $table->dropColumn('position_type');
            });
        }

        if (Schema::hasColumn('workflow_approvers', 'role_id')) {
            Schema::table('workflow_approvers', function ($table) {
                $table->dropColumn('role_id');
            });
        }
    }
};
