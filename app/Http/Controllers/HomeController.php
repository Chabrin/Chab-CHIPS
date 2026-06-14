<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\Position;
use App\Models\Gender;
use App\Models\Unit;
use App\Models\ZoneAssignment;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    public function Dashboard()
    {
        $user = Auth::user();

        if ($user->hasRole('field_officer')) {
            Log::info('Loading dashboard for field officer: ' . $user->name);
            return view('dashboards.field-officer');
        }
        Log::info('Loading dashboard for user: ' . $user->name);

        return view('home.dashboard');
    }

    /**
     * Field officer dashboard stats (AJAX).
     */
    public function getFieldOfficerStats()
    {
        try {
            $staffId = Auth::user()->staff_id;

            $propertyIds = ZoneAssignment::where('staff_id', $staffId)
                ->distinct()
                ->pluck('property_id')
                ->filter()
                ->values();

            $totalUnits    = Unit::whereIn('property_id', $propertyIds)->count();
            $occupiedUnits = Unit::whereIn('property_id', $propertyIds)
                ->whereHas('activeLeaseAgreement')
                ->count();
            $vacantUnits   = max(0, $totalUnits - $occupiedUnits);

            return response()->json([
                'success' => true,
                'stats'   => [
                    'properties'    => $propertyIds->count(),
                    'total_units'   => $totalUnits,
                    'occupied_units' => $occupiedUnits,
                    'vacant_units'  => $vacantUnits,
                ],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading field officer stats: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load stats'], 500);
        }
    }

    /**
     * Get HR dashboard statistics
     */
    public function getDashboardStats(Request $request)
    {
        try {
            $stats = [
                'total_employees' => Staff::count(),
                'total_positions' => Position::count(),
                'male_count' => $this->getStaffCountByGender('Male'),
                'female_count' => $this->getStaffCountByGender('Female'),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading dashboard stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to load dashboard statistics'
            ], 500);
        }
    }

    /**
     * Get staff by gender distribution
     */
    public function getStaffByGender(Request $request)
    {
        try {
            $genderData = DB::table('sys_staff as s')
                ->select('s.gender', DB::raw('COUNT(*) as count'))
                ->groupBy('s.gender')
                ->get();

            $data = [
                'labels' => [],
                'counts' => [],
                'colors' => ['#3b82f6', '#ec4899', '#10b981']
            ];

            foreach ($genderData as $item) {
                $data['labels'][] = $item->gender ?: 'Unknown';
                $data['counts'][] = (int) $item->count;
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading gender distribution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to load gender distribution'
            ], 500);
        }
    }

    /**
     * Get staff distribution per position
     */
    public function getStaffByPosition(Request $request)
    {
        try {
            $positionData = DB::table('sys_staff as s')
                ->leftJoin('sys_set_staff_positions as p', 's.position_id', '=', 'p.id')
                ->select('p.position_name', DB::raw('COUNT(s.id) as count'))
                ->groupBy('p.id', 'p.position_name')
                ->orderBy('count', 'DESC')
                ->limit(10)
                ->get();

            $data = [
                'labels' => [],
                'counts' => []
            ];

            foreach ($positionData as $item) {
                $data['labels'][] = $item->position_name ?: 'Unassigned';
                $data['counts'][] = (int) $item->count;
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading position distribution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to load position distribution'
            ], 500);
        }
    }

    /**
     * Get staff count by gender (private helper)
     */
    private function getStaffCountByGender(string $gender): int
    {
        try {
            return Staff::where('gender', $gender)->count();
        } catch (\Exception $e) {
            Log::warning('Error counting staff by gender: ' . $e->getMessage());
            return 0;
        }
    }
}
