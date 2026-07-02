<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Models\Department;

class ReportsController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }

    public function hubSummary(): JsonResponse
    {
        try {
            // Total active properties
            $totalProperties = DB::table('sys_pm_properties')->where('status_id', '!=', 0)->count();

            // Occupancy
            $units = DB::table('sys_pm_units')
                ->select('occupancy_status_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('occupancy_status_id')
                ->get()->keyBy('occupancy_status_id');
            $occupied = ($units[30]->cnt ?? 0) + ($units[32]->cnt ?? 0); // 30=occupied, 32=partial — adjust to actual IDs
            $vacant   = $units[31]->cnt ?? 0; // 31=vacant
            $totalUnits = collect($units)->sum('cnt');
            $occupancyPct = $totalUnits > 0 ? round(($occupied / $totalUnits) * 100, 1) : 0;

            // Active staff
            $activeStaff = DB::table('sys_staff')->where('employment_status_id', 1)->count();

            // Monthly rent roll (sum of active lease rents)
            $rentRoll = DB::table('sys_pm_lease_agreements')
                ->where('lease_status_id', 1) // active
                ->sum('rent_amount');

            // Headcount by department (for bar chart)
            $headcount = DB::table('sys_staff as s')
                ->leftJoin('sys_hr_departments as d', 'd.id', '=', 's.department_id')
                ->select([DB::raw("COALESCE(d.department_name,'Unknown') as department"), DB::raw('COUNT(s.id) as count')])
                ->where('s.employment_status_id', 1)
                ->groupBy('d.department_name')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            return response()->json([
                'success'        => true,
                'total_properties' => $totalProperties,
                'occupancy_pct'  => $occupancyPct,
                'occupied_units' => $occupied,
                'vacant_units'   => $vacant,
                'total_units'    => $totalUnits,
                'active_staff'   => $activeStaff,
                'rent_roll'      => number_format($rentRoll, 2),
                'headcount'      => $headcount,
            ]);
        } catch (\Exception $e) {
            \Log::error('hubSummary: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load summary']);
        }
    }
}
