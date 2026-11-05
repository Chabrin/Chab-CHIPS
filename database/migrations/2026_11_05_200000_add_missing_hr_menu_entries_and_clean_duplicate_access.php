<?php

use App\Models\Staff;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The menu lives in the database, and nothing in the code ever created these Human Resource entries, so they exist
 * only on servers where someone added them by hand. This makes sure each one exists once, with the right URL, and is
 * given to the same roles it has on the development server.
 *
 * An entry someone already added by hand is reused: a URL typed slightly differently is corrected, and a second
 * entry with the same name in the Human Resource module (the double Training Materials) is merged into the right
 * one, with its roles moved across. Leave Pending Approvals also goes to the role of every person who holds a
 * Reports To position, because once the entry exists the page sends anyone without that role back to the dashboard.
 *
 * Finally, a role granted the same menu item more than once is cleaned up, since each copy showed as its own entry.
 * No role gains access it did not already have, apart from the roles named below. Safe to run more than once.
 */
return new class extends Migration
{
    /** URL => [name, order, icon, roles that get it] */
    private const MENUS = [
        '/hr/communication' => ['HR Communication', 13, null, ['directors']],
        '/hr/contracts/bulk-edit' => ['Bulk Edit Contracts', 14, null, ['directors']],
        '/discipline/tracker/appeals' => ['Discipline Appeals', 15, null, ['directors']],
        '/hr/leave-management/pending-approvals' => ['Leave Pending Approvals', 16, 'pending_actions', ['directors', 'hr_manager', 'property_manager', 'field_officer']],
        '/hr/training-materials' => ['Training Materials', 17, null, ['directors', 'hr_manager', 'field_officer', 'senior_field_officer', 'accountant', 'it_department', 'property_manager', 'internal_auditor']],
    ];

    public function up(): void
    {
        $moduleId = $this->humanResourceModuleId();

        if ($moduleId) {
            foreach (self::MENUS as $url => [$name, $order, $icon, $roles]) {
                $entryId = $this->ensureEntry($moduleId, $url, $name, $order, $icon);

                $roleIds = DB::table('cb_roles')->whereIn('name', $roles)->pluck('id');
                if ($url === '/hr/leave-management/pending-approvals') {
                    $roleIds = $roleIds->merge($this->supervisorRoleIds());
                }

                foreach ($roleIds->unique() as $roleId) {
                    $this->grant((int) $roleId, $entryId);
                }
            }
        }

        $this->removeDuplicateAccess();
    }

    public function down(): void
    {
        // Not reversed: the entries may have existed before this ran, and removing them would hide working pages.
    }

    /**
     * The Human Resource module, found through its Employees page, or by name when that page is missing.
     */
    private function humanResourceModuleId(): ?int
    {
        $id = DB::table('cb_sub_modules')->where('sub_module_url', '/hr/employees')->value('module_id')
            ?? DB::table('cb_modules')->whereRaw('LOWER(module_name) = ?', ['human resource'])->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * The one entry for a page: created when missing, otherwise the existing one with its URL corrected, and any
     * other entry for the same page (the same URL written differently, or the same name in this module) merged in.
     */
    private function ensureEntry(int $moduleId, string $url, string $name, int $order, ?string $icon): int
    {
        $matches = DB::table('cb_sub_modules')->orderBy('id')->get()->filter(
            fn ($row) => $this->canonicalUrl($row->sub_module_url) === $url
                || ((int) $row->module_id === $moduleId && mb_strtolower(trim((string) $row->sub_module_name)) === mb_strtolower($name))
        )->values();

        if ($matches->isEmpty()) {
            $now = now();

            return (int) DB::table('cb_sub_modules')->insertGetId([
                'sub_module_name' => $name,
                'sub_module_url' => $url,
                'icon' => $icon,
                'order' => $order,
                'added_by' => 'migration',
                'added_on' => $now,
                'module_id' => $moduleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $keep = $matches->firstWhere('sub_module_url', $url) ?? $matches->first();
        DB::table('cb_sub_modules')->where('id', $keep->id)->update(['sub_module_url' => $url, 'updated_at' => now()]);

        foreach ($matches->where('id', '!=', $keep->id) as $duplicate) {
            foreach (DB::table('cb_sub_module_permissions')->where('sub_module_id', $duplicate->id)->pluck('role_id')->unique() as $roleId) {
                $this->grant((int) $roleId, (int) $keep->id);
            }
            DB::table('cb_sub_module_permissions')->where('sub_module_id', $duplicate->id)->delete();
            DB::table('cb_sub_modules')->where('id', $duplicate->id)->delete();
        }

        return (int) $keep->id;
    }

    /**
     * A URL as a page address only, so "hr/training-materials/", "/HR/Training-Materials" and a full link all
     * compare equal to "/hr/training-materials".
     */
    private function canonicalUrl(?string $url): string
    {
        $path = parse_url(trim((string) $url), PHP_URL_PATH) ?: '';

        return '/' . trim(mb_strtolower($path), '/');
    }

    /**
     * Give a role a menu entry unless it already has it.
     */
    private function grant(int $roleId, int $entryId): void
    {
        if (DB::table('cb_sub_module_permissions')->where('role_id', $roleId)->where('sub_module_id', $entryId)->exists()) {
            return;
        }

        $now = now();
        DB::table('cb_sub_module_permissions')->insert([
            'role_id' => $roleId,
            'sub_module_id' => $entryId,
            'added_by' => 'migration',
            'added_on' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * The roles held by active staff in a position someone else reports to.
     */
    private function supervisorRoleIds(): Collection
    {
        $supervisorPositions = DB::table('sys_set_staff_positions')->whereNotNull('reports_to_id')->pluck('reports_to_id');

        $userIds = Staff::with('userLink.user')
            ->whereIn('position_id', $supervisorPositions)
            ->where('current_status_id', 1)
            ->get()
            ->map(fn ($staff) => $staff->userLink?->user?->id)
            ->filter();

        return DB::table('cb_role_user')->whereIn('user_id', $userIds)->pluck('role_id');
    }

    /**
     * Keep the first access row for each role and menu item, and delete the repeats.
     */
    private function removeDuplicateAccess(): void
    {
        $seen = [];
        $repeats = [];

        foreach (DB::table('cb_sub_module_permissions')->orderBy('id')->get(['id', 'role_id', 'sub_module_id']) as $row) {
            $key = $row->role_id . ':' . $row->sub_module_id;

            if (isset($seen[$key])) {
                $repeats[] = $row->id;
            }
            $seen[$key] = true;
        }

        foreach (array_chunk($repeats, 500) as $ids) {
            DB::table('cb_sub_module_permissions')->whereIn('id', $ids)->delete();
        }
    }
};
