<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Shared zone/property access-scoping for field officers, zone managers,
 * zone supervisors and zone auditors. A user holding one of these staff
 * assignments only sees clients/tenants/leases/properties tied to their own
 * zone(s) or directly-assigned properties. Users whose role is marked
 * cb_roles.is_global, or who aren't authenticated at all, are unrestricted
 * (see everything — matches behavior from before this scoping existed).
 */
trait HasZoneAccessScope
{
    /**
     * Resolves the current zone-access restriction state:
     *  - null => unrestricted (unauthenticated, or cb_roles.is_global) — caller must not filter.
     *  - 0    => restricted, but not linked to any sys_staff record — caller should return zero rows.
     *  - >0   => restricted to this sys_staff.id — caller should filter via addZoneAccessPredicate().
     */
    public static function zoneAccessState(): ?int
    {
        if (!Auth::check()) {
            return null;
        }

        $userId = Auth::id();

        $isGlobal = DB::table('cb_role_user as ru')
            ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
            ->where('ru.user_id', $userId)
            ->where('r.is_global', true)
            ->exists();

        if ($isGlobal) {
            return null;
        }

        $staffId = DB::table('sys_user_staff_links')
            ->where('user_id', $userId)
            ->value('staff_id');

        return $staffId ? (int) $staffId : 0;
    }

    /**
     * Appends the "does this property/zone row belong to $staffId" predicate
     * to $query — matches field officer or zone manager/supervisor on the
     * property row itself, or zone manager/supervisor/auditor on its zone
     * row. Call inside a whereExists()/join() callback once the caller has
     * already correlated $propertyAlias back to its own query.
     */
    public static function addZoneAccessPredicate($query, int $staffId, string $propertyAlias = 'spp', string $zoneAlias = 'spz')
    {
        return $query->where(function ($q) use ($staffId, $propertyAlias, $zoneAlias) {
            $q->where("$propertyAlias.field_officer_id", $staffId)
                ->orWhere("$propertyAlias.zone_manager_id", $staffId)
                ->orWhere("$propertyAlias.zone_supervisor_id", $staffId)
                ->orWhere("$zoneAlias.zone_manager_id", $staffId)
                ->orWhere("$zoneAlias.zone_supervisor_id", $staffId)
                ->orWhere("$zoneAlias.zone_auditor_id", $staffId);
        });
    }
}
