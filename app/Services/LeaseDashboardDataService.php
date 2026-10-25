<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lease;
use App\Models\Lawyer;
use App\Models\LeaseDocument;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Provides aggregated lease data for dashboard rendering.
 *
 * Returns plain arrays — no Filament, no framework-specific output.
 * Wire the return values into any blade view, controller, or JSON endpoint.
 *
 * Caching: all methods cache for 5 minutes. Call invalidate() after state changes.
 */
class LeaseDashboardDataService
{
    private const TTL = 300; // 5 minutes

    // -------------------------------------------------------------------------
    // KPI STATS
    // -------------------------------------------------------------------------

    /**
     * Returns top-level KPI counts for the company-wide dashboard card row.
     *
     * Example usage in controller:
     *   $stats = app(LeaseDashboardDataService::class)->kpis();
     *
     * Returns:
     *   [
     *     'active_leases'    => int,
     *     'monthly_revenue'  => string,   // formatted: "KES 1,250,000.00"
     *     'occupancy_rate'   => string,   // formatted: "78%"
     *     'pending_actions'  => int,      // leases awaiting any action
     *     'expiring_soon'    => int,      // expiring within 30 days
     *     'with_advocate'    => int,      // currently at lawyer
     *     'fully_signed'     => int,      // completed this month
     *   ]
     */
    public function kpis(?int $zoneId = null): array
    {
        $cacheKey = 'lease_dashboard_kpis' . ($zoneId ? "_zone_{$zoneId}" : '_all');

        return Cache::remember($cacheKey, self::TTL, function () use ($zoneId) {
            $base = Lease::query();
            if ($zoneId) {
                $base->where('zone_id', $zoneId);
            }

            $activeStates = [
                'pending_tenant_signature', 'pending_manager_countersign',
                'pending_advocate', 'with_lawyer', 'fully_signed', 'active',
                'approved', 'printed', 'checked_out', 'sent_digital', 'pending_otp',
            ];

            $pendingStates = [
                'draft', 'received', 'pending_landlord_approval',
                'pending_tenant_signature', 'pending_manager_countersign',
                'pending_advocate', 'with_lawyer',
            ];

            $activeLeases   = (clone $base)->whereIn('workflow_state', $activeStates)->count();
            $pendingActions = (clone $base)->whereIn('workflow_state', $pendingStates)->count();
            $withAdvocate   = (clone $base)->whereIn('workflow_state', ['pending_advocate', 'with_lawyer'])->count();
            $fullySignedMonth = (clone $base)
                ->where('workflow_state', 'fully_signed')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->count();

            $expiringSoon = (clone $base)
                ->whereIn('workflow_state', ['active', 'fully_signed'])
                ->whereBetween('end_date', [now(), now()->addDays(30)])
                ->count();

            $monthlyRevenue = (clone $base)
                ->whereIn('workflow_state', ['active', 'fully_signed'])
                ->sum('monthly_rent');

            $totalUnits  = DB::table('units')->count();
            $occupiedUnits = (clone $base)->whereIn('workflow_state', ['active', 'fully_signed'])->distinct('unit_id')->count('unit_id');
            $occupancyRate = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100) : 0;

            return [
                'active_leases'   => $activeLeases,
                'monthly_revenue' => 'KES ' . number_format((float) $monthlyRevenue, 2),
                'occupancy_rate'  => $occupancyRate . '%',
                'pending_actions' => $pendingActions,
                'expiring_soon'   => $expiringSoon,
                'with_advocate'   => $withAdvocate,
                'fully_signed'    => $fullySignedMonth,
            ];
        });
    }

    // -------------------------------------------------------------------------
    // LEASE STATUS DISTRIBUTION  (donut / pie chart)
    // -------------------------------------------------------------------------

    /**
     * Returns lease counts grouped by workflow state for a status chart.
     *
     * Returns:
     *   [
     *     ['label' => 'Draft',           'count' => 12, 'color' => '#94a3b8'],
     *     ['label' => 'Pending Signing', 'count' => 34, 'color' => '#f59e0b'],
     *     ...
     *   ]
     *
     * Pass directly to Chart.js:
     *   labels   = array_column($data, 'label')
     *   data     = array_column($data, 'count')
     *   colors   = array_column($data, 'color')
     */
    public function statusDistribution(?int $zoneId = null): array
    {
        $cacheKey = 'lease_dashboard_status' . ($zoneId ? "_zone_{$zoneId}" : '_all');

        return Cache::remember($cacheKey, self::TTL, function () use ($zoneId) {
            $query = Lease::query()->select('workflow_state', DB::raw('count(*) as total'));
            if ($zoneId) {
                $query->where('zone_id', $zoneId);
            }

            $rows = $query->groupBy('workflow_state')->get();

            $colorMap = [
                'draft'                      => '#94a3b8',
                'received'                   => '#60a5fa',
                'pending_landlord_approval'  => '#a78bfa',
                'approved'                   => '#34d399',
                'printed'                    => '#fbbf24',
                'checked_out'                => '#f97316',
                'sent_digital'               => '#38bdf8',
                'pending_otp'                => '#fb923c',
                'pending_tenant_signature'   => '#facc15',
                'pending_manager_countersign'=> '#4ade80',
                'pending_advocate'           => '#c084fc',
                'with_lawyer'                => '#818cf8',
                'fully_signed'               => '#22c55e',
                'active'                     => '#16a34a',
                'expired'                    => '#ef4444',
                'terminated'                 => '#dc2626',
                'disputed'                   => '#f87171',
                'cancelled'                  => '#6b7280',
            ];

            return $rows->map(function ($row) use ($colorMap) {
                $label = ucwords(str_replace('_', ' ', $row->workflow_state));
                return [
                    'label' => $label,
                    'count' => (int) $row->total,
                    'color' => $colorMap[$row->workflow_state] ?? '#94a3b8',
                ];
            })->values()->toArray();
        });
    }

    // -------------------------------------------------------------------------
    // REVENUE TREND  (bar / line chart — last 12 months)
    // -------------------------------------------------------------------------

    /**
     * Returns monthly revenue totals for the past N months.
     *
     * Returns:
     *   [
     *     ['month' => 'Mar 2025', 'revenue' => 1250000.00],
     *     ['month' => 'Apr 2025', 'revenue' => 1340000.00],
     *     ...
     *   ]
     *
     * Pass directly to Chart.js:
     *   labels = array_column($data, 'month')
     *   data   = array_column($data, 'revenue')
     */
    public function revenueTrend(int $months = 12, ?int $zoneId = null): array
    {
        $cacheKey = "lease_dashboard_revenue_{$months}" . ($zoneId ? "_zone_{$zoneId}" : '_all');

        return Cache::remember($cacheKey, self::TTL, function () use ($months, $zoneId) {
            $from = now()->subMonths($months - 1)->startOfMonth();

            $query = Lease::query()
                ->select(
                    DB::raw("to_char(created_at, 'Mon YYYY') as month"),
                    DB::raw("date_trunc('month', created_at) as month_start"),
                    DB::raw('SUM(monthly_rent) as revenue')
                )
                ->whereIn('workflow_state', ['active', 'fully_signed', 'expired'])
                ->where('created_at', '>=', $from)
                ->groupBy(DB::raw("date_trunc('month', created_at), to_char(created_at, 'Mon YYYY')"))
                ->orderBy('month_start');

            if ($zoneId) {
                $query->where('zone_id', $zoneId);
            }

            return $query->get()->map(fn ($row) => [
                'month'   => $row->month,
                'revenue' => (float) $row->revenue,
            ])->toArray();
        });
    }

    // -------------------------------------------------------------------------
    // ZONE PERFORMANCE  (table / grouped bar chart)
    // -------------------------------------------------------------------------

    /**
     * Returns per-zone lease counts and revenue for comparison.
     *
     * Returns:
     *   [
     *     [
     *       'zone'           => 'Nairobi CBD',
     *       'active_leases'  => 45,
     *       'monthly_revenue'=> 'KES 2,340,000.00',
     *       'pending'        => 8,
     *       'expiring_soon'  => 3,
     *     ],
     *     ...
     *   ]
     */
    public function zonePerformance(): array
    {
        return Cache::remember('lease_dashboard_zones', self::TTL, function () {
            $zones = DB::table('zones')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            return $zones->map(function ($zone) {
                $base = Lease::where('zone_id', $zone->id);

                $active = (clone $base)->whereIn('workflow_state', ['active', 'fully_signed'])->count();
                $pending = (clone $base)->whereIn('workflow_state', [
                    'draft', 'received', 'pending_landlord_approval',
                    'pending_tenant_signature', 'pending_manager_countersign',
                    'pending_advocate', 'with_lawyer',
                ])->count();
                $expiring = (clone $base)
                    ->whereIn('workflow_state', ['active', 'fully_signed'])
                    ->whereBetween('end_date', [now(), now()->addDays(30)])
                    ->count();
                $revenue = (clone $base)
                    ->whereIn('workflow_state', ['active', 'fully_signed'])
                    ->sum('monthly_rent');

                return [
                    'zone'            => $zone->name,
                    'active_leases'   => $active,
                    'monthly_revenue' => 'KES ' . number_format((float) $revenue, 2),
                    'pending'         => $pending,
                    'expiring_soon'   => $expiring,
                ];
            })->toArray();
        });
    }

    // -------------------------------------------------------------------------
    // ADVOCATE WORKLOAD
    // -------------------------------------------------------------------------

    /**
     * Returns active advocate engagements and average turnaround.
     *
     * Returns:
     *   [
     *     'total_advocates'       => int,
     *     'currently_engaged'     => int,
     *     'avg_turnaround_days'   => float,
     *     'overdue'               => int,   // with lawyer > 7 days
     *   ]
     */
    public function advocateWorkload(): array
    {
        return Cache::remember('lease_dashboard_advocates', self::TTL, function () {
            $total = Lawyer::where('is_active', true)->count();

            $engaged = DB::table('lease_lawyer_tracking')
                ->whereIn('status', ['sent', 'with_lawyer'])
                ->distinct('lawyer_id')
                ->count('lawyer_id');

            $avgTurnaround = DB::table('lease_lawyer_tracking')
                ->whereNotNull('returned_at')
                ->whereNotNull('sent_at')
                ->avg(DB::raw("EXTRACT(EPOCH FROM (returned_at - sent_at)) / 86400"));

            $overdue = DB::table('lease_lawyer_tracking')
                ->whereIn('status', ['sent', 'with_lawyer'])
                ->where('sent_at', '<', now()->subDays(7))
                ->count();

            return [
                'total_advocates'     => $total,
                'currently_engaged'   => $engaged,
                'avg_turnaround_days' => round((float) $avgTurnaround, 1),
                'overdue'             => $overdue,
            ];
        });
    }

    // -------------------------------------------------------------------------
    // CACHE INVALIDATION
    // -------------------------------------------------------------------------

    /**
     * Call this after any lease workflow_state change to flush stale dashboard data.
     */
    public function invalidate(?int $zoneId = null): void
    {
        Cache::forget('lease_dashboard_kpis_all');
        Cache::forget('lease_dashboard_status_all');
        Cache::forget('lease_dashboard_revenue_12_all');
        Cache::forget('lease_dashboard_zones');
        Cache::forget('lease_dashboard_advocates');

        if ($zoneId) {
            Cache::forget("lease_dashboard_kpis_zone_{$zoneId}");
            Cache::forget("lease_dashboard_status_zone_{$zoneId}");
            Cache::forget("lease_dashboard_revenue_12_zone_{$zoneId}");
        }
    }
}
