<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Module visibility and action permissions are required in addition to workflow assignment. */
class TenantReviewAccess
{
    public const REVIEWS = '/approvals/new-tenants';
    public const SETUP = '/approvals/setup';
    public const ACCOUNTS = '/approvals/new-tenants/reconciliation';

    public static function allows(User $user, string $permission, string $page = self::REVIEWS): bool
    {
        if ($user->block || !self::permissionGranted($user, $permission)) return false;
        // Use the same active role as the standard menu and AccessChecker.
        $role = DB::table('cb_role_user')->where('user_id', $user->id)->value('role_id');
        return self::roleHasPage((int) $role, $page);
    }

    public static function permissionGranted(User $user, string $name): bool
    {
        // Read grants afresh so revoking an onboarding permission also stops direct POSTs immediately.
        return DB::table('cb_permissions as p')->where('p.name', $name)->where(function ($query) use ($user) {
            $query->whereExists(function ($roles) use ($user) {
                $roles->selectRaw('1')->from('cb_permission_role as pr')->join('cb_role_user as ru', 'ru.role_id', '=', 'pr.role_id')
                    ->whereColumn('pr.permission_id', 'p.id')->where('ru.user_id', $user->id);
            })->orWhereExists(function ($direct) use ($user) {
                $direct->selectRaw('1')->from('cb_permission_user as pu')->whereColumn('pu.permission_id', 'p.id')
                    ->where('pu.user_id', $user->id)->whereIn('pu.user_type', [User::class, $user->getMorphClass()]);
            });
        })->exists();
    }

    public static function roleHasPage(int $roleId, string $page): bool
    {
        return DB::table('cb_sub_module_permissions as grant')
            ->join('cb_sub_modules as page', 'page.id', '=', 'grant.sub_module_id')
            ->where('grant.role_id', $roleId)->where('page.sub_module_url', $page)->exists();
    }

    public static function roleCanReview(int $roleId): bool
    {
        $permissions = DB::table('cb_permission_role as grant')->join('cb_permissions as permission', 'permission.id', '=', 'grant.permission_id')
            ->where('grant.role_id', $roleId)->whereIn('permission.name', ['view_tenant_onboarding', 'review_tenant_onboarding'])->distinct()->count('permission.name');
        return $permissions === 2 && self::roleHasPage($roleId, self::REVIEWS);
    }
}
