<?php

use App\Services\TenantReviewAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $module = DB::table('cb_modules')->where('module_name', 'Approval Control')->value('id');
            if (!$module) throw new RuntimeException('Approval Control must exist before registering tenant onboarding access.');
            $menus = [];
            foreach ([TenantReviewAccess::REVIEWS => 'New Tenant Approvals', TenantReviewAccess::SETUP => 'Approval Setup',
                TenantReviewAccess::ACCOUNTS => 'Rejected Tenant Reconciliation'] as $url => $name) {
                $id = DB::table('cb_sub_modules')->where('sub_module_url', $url)->value('id');
                if (!$id) $id = DB::table('cb_sub_modules')->insertGetId([
                    'sub_module_name' => $name, 'sub_module_url' => $url, 'module_id' => $module,
                    'icon' => null, 'order' => (int) DB::table('cb_sub_modules')->where('module_id', $module)->max('order') + 1,
                    'added_by' => 'migration', 'added_on' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $menus[$url] = $id;
            }
            $permissions = [];
            foreach (['view_tenant_onboarding' => 'View tenant onboarding reviews within the existing record scope.',
                'review_tenant_onboarding' => 'Sign, return or reject only the tenant reviews assigned to this approver.',
                'reconcile_tenant_onboarding' => 'Record reconciliation for rejected tenant onboarding; Accounts role is still required.'] as $name => $description) {
                $id = DB::table('cb_permissions')->where('name', $name)->value('id');
                if (!$id) $id = DB::table('cb_permissions')->insertGetId(['name' => $name, 'display_name' => $name,
                    'description' => $description, 'module_id' => $module, 'created_at' => now(), 'updated_at' => now()]);
                $permissions[$name] = $id;
            }
            // This existing permission was incorrectly grouped under Accounting Settings.
            DB::table('cb_permissions')->where('name', 'update_approval_workflows')->update(['module_id' => $module]);
            $managerPermission = DB::table('cb_permissions')->where('name', 'update_approval_workflows')->value('id');
            if (!$managerPermission) $managerPermission = DB::table('cb_permissions')->insertGetId([
                'name' => 'update_approval_workflows', 'display_name' => 'update_approval_workflows',
                'description' => 'Manage and publish tenant approval setup versions.', 'module_id' => $module,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $managers = DB::table('cb_permission_role')->where('permission_id', $managerPermission)->pluck('role_id')->all();
            $directManagers = DB::table('cb_permission_user')->where('permission_id', $managerPermission)->pluck('user_id');
            $managers = array_merge($managers, DB::table('cb_role_user')->whereIn('user_id', $directManagers)->pluck('role_id')->all());
            $rolesFor = fn ($names) => DB::table('cb_roles')->whereIn('name', (array) $names)->orWhereIn('display_name', (array) $names)->pluck('id')->all();
            $preparers = $rolesFor(config('tenant_review.roles.field_officer'));
            $accounts = $rolesFor(config('tenant_review.roles.accounts'));
            $reviewers = [];
            foreach (DB::table('tenant_review_workflow_versions')->pluck('steps') as $json) {
                foreach (array_slice(json_decode($json, true, 512, JSON_THROW_ON_ERROR), 1) as $step) $reviewers[] = $step['role_id'];
            }
            $pastPreparers = DB::table('cb_role_user')->whereIn('user_id', DB::table('tenant_reviews')->select('prepared_by'))->pluck('role_id')->all();
            $viewers = array_unique(array_merge($preparers, $accounts, $reviewers, $managers, $pastPreparers));
            $grantMenu = function ($role, $url) use ($menus) {
                if (!DB::table('cb_sub_module_permissions')->where('role_id', $role)->where('sub_module_id', $menus[$url])->exists()) {
                    DB::table('cb_sub_module_permissions')->insert(['role_id' => $role, 'sub_module_id' => $menus[$url], 'added_by' => 'migration', 'added_on' => now()]);
                }
            };
            $grantPermission = function ($role, $name) use ($permissions) {
                if (!DB::table('cb_permission_role')->where('role_id', $role)->where('permission_id', $permissions[$name])->exists()) {
                    DB::table('cb_permission_role')->insert(['role_id' => $role, 'permission_id' => $permissions[$name]]);
                }
            };
            foreach ($viewers as $role) { $grantMenu($role, TenantReviewAccess::REVIEWS); $grantPermission($role, 'view_tenant_onboarding'); }
            foreach (array_unique($reviewers) as $role) $grantPermission($role, 'review_tenant_onboarding');
            foreach (array_unique($managers) as $role) $grantMenu($role, TenantReviewAccess::SETUP);
            foreach ($accounts as $role) { $grantMenu($role, TenantReviewAccess::ACCOUNTS); $grantPermission($role, 'reconcile_tenant_onboarding'); }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $menus = DB::table('cb_sub_modules')->whereIn('sub_module_url', [TenantReviewAccess::REVIEWS, TenantReviewAccess::SETUP, TenantReviewAccess::ACCOUNTS])->pluck('id');
            DB::table('cb_sub_module_permissions')->whereIn('sub_module_id', $menus)->delete();
            DB::table('cb_sub_modules')->whereIn('id', $menus)->delete();
            $permissions = DB::table('cb_permissions')->whereIn('name', ['view_tenant_onboarding', 'review_tenant_onboarding', 'reconcile_tenant_onboarding'])->pluck('id');
            DB::table('cb_permission_role')->whereIn('permission_id', $permissions)->delete();
            DB::table('cb_permission_user')->whereIn('permission_id', $permissions)->delete();
            DB::table('cb_permissions')->whereIn('id', $permissions)->delete();
            // Keep the pre-existing workflow permission and its grants in Approval Control.
        });
    }
};
