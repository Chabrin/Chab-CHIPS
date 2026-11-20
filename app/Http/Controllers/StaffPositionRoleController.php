<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\Role;
use App\Models\Staff;
use App\Models\UserStaffLink;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a staff member changes position, the system role that goes with the new position is offered for their login. Nothing changes
 * unless someone with the right to change users says yes.
 */
class StaffPositionRoleController extends Controller
{
    /**
     * What to offer after a staff member's position changed: the role of the new position, if they have a login that does not already hold it.
     *
     * @return array{name: string, role_name: string, url: string}|null
     */
    public static function offerFor(Staff $staff, ?int $oldPositionId): ?array
    {
        if ((int) $staff->position_id === (int) $oldPositionId || !$staff->position_id || !Schema::hasColumn('sys_set_staff_positions', 'role_id')) {
            return null;
        }
        $roleId = Position::whereKey($staff->position_id)->value('role_id');
        $userId = UserStaffLink::where('staff_id', $staff->id)->value('user_id');
        if (!$roleId || !$userId) {
            return null;
        }
        if (DB::table('cb_role_user')->where('user_id', $userId)->where('role_id', $roleId)->exists()) {
            return null;
        }

        return [
            'name' => $staff->full_names,
            'role_name' => (string) Role::whereKey($roleId)->value('display_name'),
            'url' => route('staff.apply-position-role', Crypt::encrypt($staff->id)),
        ];
    }

    /**
     * Give the staff member's login the role of their position (replacing the role it had).
     */
    public function apply(string $encryptedId)
    {
        abort_unless(auth()->user()->hasPermission('update_users'), 403);
        $staff = Staff::findOrFail((int) Crypt::decrypt($encryptedId));
        $back = redirect()->route('staff.show', Crypt::encrypt($staff->id));

        $roleId = Schema::hasColumn('sys_set_staff_positions', 'role_id') ? Position::whereKey($staff->position_id)->value('role_id') : null;
        $userId = UserStaffLink::where('staff_id', $staff->id)->value('user_id');
        if (!$roleId || !$userId) {
            return $back->with('error', 'There is no login or no role for this position, so system access was not changed.');
        }

        DB::transaction(function () use ($userId, $roleId) {
            DB::table('cb_role_user')->where('user_id', $userId)->delete();
            DB::table('cb_role_user')->insert(['user_id' => $userId, 'role_id' => $roleId]);
        });

        return $back->with('success', $staff->full_names . '\'s system access now follows their position (' . Role::whereKey($roleId)->value('display_name') . ').');
    }
}
