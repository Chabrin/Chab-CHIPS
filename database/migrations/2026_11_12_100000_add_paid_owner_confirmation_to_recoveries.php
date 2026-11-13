<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paid Owner confirmation. A recovery now records who confirmed or rejected it, when, why it was rejected and the proof
 * the field officer gave (the landlord's acknowledgement or receipt number). Also adds the approval workflow that says
 * who may confirm a paid-owner recovery: it is created empty, the role is chosen under Accounting > Approvals.
 * Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sys_pm_recoveries')) {
            $columns = [
                'actioned_by' => 'BIGINT NULL',
                'actioned_at' => 'TIMESTAMP(0) WITHOUT TIME ZONE NULL',
                'rejection_reason' => 'VARCHAR(500) NULL',
                'proof_reference' => 'VARCHAR(100) NULL',
            ];

            foreach ($columns as $name => $definition) {
                if (!Schema::hasColumn('sys_pm_recoveries', $name)) {
                    DB::statement("ALTER TABLE sys_pm_recoveries ADD COLUMN {$name} {$definition}");
                }
            }
        }

        if (Schema::hasTable('approval_workflows')
            && !DB::table('approval_workflows')->where('workflow_name', 'paid_owner_confirmation')->exists()) {
            DB::table('approval_workflows')->insert([
                'workflow_name' => 'paid_owner_confirmation',
                'description' => 'Who confirms a paid-owner recovery (a tenant paid the landlord directly). Add one approver at level 1, a role or a named user.',
                'status' => 1,
                'added_on' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('approval_workflows')) {
            $id = DB::table('approval_workflows')->where('workflow_name', 'paid_owner_confirmation')->value('id');

            if ($id && !DB::table('approval_requests')->where('workflow_id', $id)->exists()) {
                DB::table('approval_workflows')->where('id', $id)->delete();
            }
        }

        foreach (['actioned_by', 'actioned_at', 'rejection_reason', 'proof_reference'] as $name) {
            if (Schema::hasColumn('sys_pm_recoveries', $name)) {
                DB::statement("ALTER TABLE sys_pm_recoveries DROP COLUMN {$name}");
            }
        }
    }
};
