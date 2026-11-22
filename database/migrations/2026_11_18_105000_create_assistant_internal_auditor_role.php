<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The tenant approval workflow (2026_11_18_110000) uses the Assistant Internal Auditor role as its final step and stops if the role is missing.
 * This runs just before it so every environment has the role. The role starts with no access; the later tenant onboarding access migration
 * grants it the New Tenant Approvals menu and the view/review permissions because it is a workflow step.
 */
return new class extends Migration
{
    /**
     * Create the Assistant Internal Auditor role if no role with that name or display name exists.
     */
    public function up(): void {
        $label = config('tenant_review.roles.assistant_internal_auditor', 'Assistant Internal Auditor');
        $exists = DB::table('cb_roles')->where('name', 'assistant_internal_auditor')->orWhere('display_name', $label)->exists();
        if ($exists) return;
        DB::table('cb_roles')->insert([
            'name' => 'assistant_internal_auditor',
            'display_name' => $label,
            'description' => $label,
            'is_global' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Remove the role only while nobody is assigned to it and no permission or menu grant uses it.
     */
    public function down(): void {
        $id = DB::table('cb_roles')->where('name', 'assistant_internal_auditor')->value('id');
        if (!$id) return;
        $inUse = DB::table('cb_role_user')->where('role_id', $id)->exists()
            || DB::table('cb_permission_role')->where('role_id', $id)->exists()
            || DB::table('cb_sub_module_permissions')->where('role_id', $id)->exists();
        if ($inUse) return;
        DB::table('cb_roles')->where('id', $id)->delete();
    }
};
