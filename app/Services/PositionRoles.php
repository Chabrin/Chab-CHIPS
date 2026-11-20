<?php

namespace App\Services;

use App\Models\Position;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Positions and system roles go together: every position has one role, and a new position gets its role when it is created, so the
 * Position and Role lists match. A role made this way has no menus or permissions until an administrator grants them on the Roles
 * page, so it is safe: someone in a new position can sign in but sees nothing until access is given.
 *
 * Positions that existed before are linked with plan() and apply() (the hr:link-position-roles command). Nobody's access changes:
 * people keep the roles they have.
 */
class PositionRoles
{
    /**
     * Give a new position its role (made now, with no access), unless it already has one.
     */
    public function createFor(Position $position): Role
    {
        $existing = $position->role_id ? Role::find($position->role_id) : null;
        if ($existing) {
            return $existing;
        }

        $role = new Role();
        $role->name = $this->uniqueRoleName($position);
        $role->display_name = $position->position_name;
        $role->description = 'The role for the position ' . $position->position_name . '. It has no access until it is given menus and permissions on the Roles page.';
        $role->save();

        $position->role_id = $role->id;
        $position->save();

        return $role;
    }

    /**
     * The role a staff member in this position gets. Add Staff has no role box: the role always comes from the position.
     * A position that has its role gives it; one that has none yet (the positions have not been linked) is matched to an existing role
     * the same way hr:link-position-roles does, and only when nothing matches is a new role (with no access) made.
     */
    public function roleFor(Position $position): Role
    {
        $linked = $position->role_id ? Role::find($position->role_id) : null;
        if ($linked) {
            return $linked;
        }

        $match = $this->existingRoleFor($position, Role::all());
        if ($match) {
            $position->role_id = $match->id;
            $position->save();
            $this->syncName($position);

            return $match;
        }

        return $this->createFor($position);
    }

    /**
     * The role that already exists for a position: the pair in config/position_roles.php, then a role with the position's key or name.
     */
    private function existingRoleFor(Position $position, Collection $roles): ?Role
    {
        $byName = $roles->keyBy(fn (Role $role) => strtolower((string) $role->name));
        $byDisplay = $roles->keyBy(fn (Role $role) => strtolower((string) $role->display_name));
        $pairs = (array) config('position_roles.existing', []);

        $slug = $position->slug_name ?: Str::slug($position->position_name, '_');
        $match = isset($pairs[$slug]) ? $byName->get(strtolower($pairs[$slug])) : null;

        return $match ?? $byName->get(strtolower($slug)) ?? $byDisplay->get(strtolower($position->position_name));
    }

    /**
     * A position renamed keeps its role, shown under the new name. The role's key (what permissions and settings refer to) is not changed.
     */
    public function syncName(Position $position): void
    {
        if (!$position->role_id) {
            return;
        }
        // A role several positions share (Directors is the role of the Managing Director and of Board Members) keeps its own name
        $shared = Position::where('role_id', $position->role_id)->where('id', '!=', $position->id)->exists();
        if ($shared) {
            return;
        }
        Role::whereKey($position->role_id)->update(['display_name' => $position->position_name]);
    }

    /**
     * What linking the positions that have no role yet would do: for each, an existing role (by name, or by the pairs in
     * config/position_roles.php) or a new one. Nothing is changed.
     *
     * @return Collection<int, array{position: Position, staff: int, action: string, role: ?Role, role_users: int}>
     */
    public function plan(): Collection
    {
        $roles = Role::all();
        $staffCounts = DB::table('sys_staff')->select('position_id', DB::raw('count(*) as n'))->groupBy('position_id')->pluck('n', 'position_id');

        return Position::whereNull('role_id')->orderBy('position_name')->get()->map(function (Position $position) use ($roles, $staffCounts) {
            $match = $this->existingRoleFor($position, $roles);

            return [
                'position' => $position,
                'staff' => (int) ($staffCounts[$position->id] ?? 0),
                'action' => $match ? 'use_existing' : 'create',
                'role' => $match,
                'role_users' => $match ? DB::table('cb_role_user')->where('role_id', $match->id)->count() : 0,
            ];
        });
    }

    /**
     * Do what plan() says. An existing role takes the position's name for display (its key is unchanged); a position with no
     * matching role gets a new one with no access. Returns how many of each were done.
     *
     * @return array{linked: int, created: int}
     */
    public function apply(): array
    {
        $done = ['linked' => 0, 'created' => 0];
        DB::transaction(function () use (&$done) {
            $linked = [];
            foreach ($this->plan() as $item) {
                /** @var Position $position */
                $position = $item['position'];
                if ($item['action'] === 'use_existing') {
                    $position->role_id = $item['role']->id;
                    $position->save();
                    $linked[] = $position;
                    $done['linked']++;
                } else {
                    $this->createFor($position);
                    $done['created']++;
                }
            }
            // Names are matched once every link is made, so a role that two positions share is seen to be shared and keeps its name
            foreach ($linked as $position) {
                $this->syncName($position);
            }
        });

        return $done;
    }

    /**
     * A role key for this position that nothing else uses: its slug, then slug_2, slug_3...
     */
    private function uniqueRoleName(Position $position): string
    {
        $base = $position->slug_name ?: Str::slug($position->position_name, '_');
        $base = $base !== '' ? $base : 'position';
        $name = $base;
        $counter = 1;
        while (Role::where('name', $name)->exists()) {
            $name = $base . '_' . (++$counter);
        }

        return $name;
    }
}
