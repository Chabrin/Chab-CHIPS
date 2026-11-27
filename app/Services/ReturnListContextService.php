<?php

namespace App\Services;

use App\Exceptions\ReturnApprovalException;
use App\Models\ReturnSummary;
use Illuminate\Support\Facades\DB;

/** Adds read-only workflow context only to rows already authorised by ReturnListService. */
class ReturnListContextService
{
    public function __construct(private ReturnApprovalWorkflowService $workflow) {}

    public function enrich(array $result, ?int $configuredLevels): array
    {
        $visible = collect($result['data'])->flatMap(fn ($row) => [$row, ...$row['children']])->keyBy('id');
        $returns = ReturnSummary::whereIn('id', $visible->keys())->get()->keyBy('id');
        try {
            $levels = $configuredLevels ? $this->workflow->levels() : [];
        } catch (ReturnApprovalException $e) {
            $levels = [];
        }
        $labels = $this->approverLabels($returns, $levels);
        $decorate = function (array $row) use ($labels): array {
            $row['next_approver'] = in_array($row['status_key'], ['awaiting', 'returned'], true) && $row['current_level']
                ? ($labels[$row['return_code']][$row['current_level']] ?? 'Approver configuration needs review') : null;
            return $row;
        };
        foreach ($result['data'] as &$row) {
            $row = $decorate($row);
            $row['children'] = array_map($decorate, $row['children']);
        }
        unset($row);
        $result['approval_levels'] = array_keys($levels);
        // Keep historical levels available when the active workflow has not been configured.
        if (!$levels) {
            $max = (int) $visible->max('total_levels');
            $result['approval_levels'] = $max > 0 ? range(1, $max) : [];
        }
        $result['workflow_configured'] = !empty($levels);
        return $result;
    }

    /** Resolve named users, role names and each property's zone managers in bulk. */
    private function approverLabels($returns, array $levels): array
    {
        if (!$levels || $returns->isEmpty()) return [];
        $seats = collect($levels)->flatten();
        $names = DB::table('josya_users')->whereIn('id', $seats->filter(fn ($seat) => is_int($seat)))->pluck('name', 'id');
        $roleIds = $seats->filter(fn ($seat) => is_string($seat) && str_starts_with($seat, 'role:'))->map(fn ($seat) => (int) substr($seat, 5));
        $roles = DB::table('cb_roles')->whereIn('id', $roleIds)->pluck('name', 'id');
        $managers = collect();
        if ($seats->contains('zone_manager')) {
            $zones = DB::table('cb_returns_clients')->whereIn('return_code', $returns->pluck('return_code'))->whereNotNull('zone_id')->get(['return_code', 'zone_id']);
            $properties = DB::table('sys_pm_properties')->whereIn('reference_number', $returns->pluck('property_code'))->pluck('zone_id', 'reference_number');
            foreach ($returns as $return) {
                if ($zone = $properties->get($return->property_code)) $zones->push((object) ['return_code' => $return->return_code, 'zone_id' => $zone]);
            }
            $zoneNames = DB::table('sys_set_zones as z')->join('sys_user_staff_links as l', 'l.staff_id', '=', 'z.zone_manager_id')
                ->join('josya_users as u', 'u.id', '=', 'l.user_id')->whereIn('z.id', $zones->pluck('zone_id'))->get(['z.id', 'u.name'])->groupBy('id');
            $managers = $zones->groupBy('return_code')->map(fn ($rows) => $rows->flatMap(fn ($zone) => $zoneNames->get($zone->zone_id, collect())->pluck('name'))->unique()->implode(', '));
        }
        $labels = [];
        foreach ($returns as $return) {
            foreach ($levels as $level => $levelSeats) {
                $labels[$return->return_code][$level] = collect($levelSeats)->map(function ($seat) use ($names, $roles, $managers, $return) {
                    if (is_int($seat)) return $names->get($seat, 'Assigned approver');
                    if ($seat === 'zone_manager') return 'Zone Manager' . ($managers->get($return->return_code) ? ' - ' . $managers->get($return->return_code) : ' (none set)');
                    return $roles->get((int) substr($seat, 5), 'Assigned role') . ' (role)';
                })->implode(' / ');
            }
        }
        return $labels;
    }
}
