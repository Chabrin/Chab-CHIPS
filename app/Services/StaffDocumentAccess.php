<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who may open, download or remove a staff member's documents: the HR role and the Directors (config/staff_documents.php), and only
 * with the usual staff permission as well. Everyone else can still see that a document exists, but not its contents.
 */
class StaffDocumentAccess
{
    /**
     * Whether this user's role is one of the roles allowed near staff documents.
     */
    public function roleAllowed(?User $user): bool
    {
        if (!$user || !$user->id) {
            return false;
        }
        $allowed = array_map('strtolower', (array) config('staff_documents.roles', []));
        if (!$allowed) {
            return false;
        }

        return $this->roleNames($user)->intersect($allowed)->isNotEmpty();
    }

    /**
     * May this user record an exception to the mandatory HELB compliance certificate: the HR Manager only (config exception_roles).
     */
    public function canWaiveHelbCertificate(?User $user): bool
    {
        if (!$user || !$user->id) {
            return false;
        }
        $allowed = array_map('strtolower', (array) config('staff_documents.exception_roles', []));
        if (!$allowed) {
            return false;
        }

        return $this->roleNames($user)->intersect($allowed)->isNotEmpty();
    }

    /**
     * The names (lower case) of the roles this user holds; none when the role tables are not there.
     */
    private function roleNames(User $user): Collection
    {
        if (!Schema::hasTable('cb_role_user') || !Schema::hasTable('cb_roles')) {
            return collect();
        }

        return DB::table('cb_role_user')
            ->join('cb_roles', 'cb_roles.id', '=', 'cb_role_user.role_id')
            ->where('cb_role_user.user_id', $user->id)
            ->pluck('cb_roles.name')
            ->map(fn ($name) => strtolower((string) $name));
    }

    /**
     * May this user open or download documents: an allowed role, and permission to read staff.
     */
    public function allows(?User $user): bool
    {
        return $user !== null && $this->roleAllowed($user) && $user->hasPermission('read_staff');
    }

    /**
     * May this user remove or replace documents: an allowed role, and permission to change staff or contracts.
     */
    public function allowsChanges(?User $user): bool
    {
        return $user !== null && $this->roleAllowed($user)
            && ($user->hasPermission('update_staff') || $user->hasPermission('update_contracts'));
    }
}
