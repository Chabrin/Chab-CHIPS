<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PropertyTransferAccess
{
    public const PAGE = '/property/ownership-transfers';

    public static function allows(User $user, string $action): bool
    {
        return TenantReviewAccess::allows($user, $action.'_property_transfers', self::PAGE);
    }

    /** The Director exception uses the active role, as menu visibility does. */
    public static function isDirector(User $user): bool
    {
        if ($user->block) {
            return false;
        }
        $roleId = DB::table('cb_role_user')->where('user_id', $user->id)->value('role_id');
        $role = DB::table('cb_roles')->where('id', $roleId)->first(['name', 'display_name']);

        return $role && count(array_intersect(
            array_map(fn ($name) => strtolower(trim((string) $name)), [$role->name, $role->display_name]),
            ['director', 'directors']
        )) > 0;
    }
}
