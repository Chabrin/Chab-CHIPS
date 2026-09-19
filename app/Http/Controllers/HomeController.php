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

        if ($user->hasRole('hr_manager')) {
            Log::info('Loading dashboard for HR manager: ' . $user->name);
            return view('dashboards.hr-manager');
        }

        if ($user->hasRole('accountant')) {
            Log::info('Loading dashboard for accountant: ' . $user->name);
            return view('dashboards.accountant');
        }

        if ($user->hasRole('directors')) {
            Log::info('Loading dashboard for director: ' . $user->name);
            return view('dashboards.director');
        }

        Log::info('Loading dashboard for user: ' . $user->name);

        return view('dashboards.generic');
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
            $occupiedUnits = Unit::whereIn('property_id', $propertyIds)->where('occupancy_status_id', 1)->count();
            $vacantUnits   = Unit::whereIn('property_id', $propertyIds)->where('occupancy_status_id', 3)->count();

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
     * Field officer collection vs arrears stats (AJAX).
     */
    public function getFieldOfficerCollection()
    {
        try {
            $staffId = Auth::user()->staff_id;

            $propertyIds = ZoneAssignment::where('staff_id', $staffId)
                ->distinct()
                ->pluck('property_id')
                ->filter()
                ->toArray();

            $overallRow = DB::table('sys_pm_debtors_invoices')
                ->whereIn('property_id', $propertyIds)
                ->selectRaw('COALESCE(SUM(total_amount), 0) as total_invoiced, COALESCE(SUM(current_balance), 0) as total_arrears')
                ->first();

            $monthRow = DB::table('sys_pm_debtors_invoices')
                ->whereIn('property_id', $propertyIds)
                ->whereYear('date_time', now()->year)
                ->whereMonth('date_time', now()->month)
                ->selectRaw('COALESCE(SUM(total_amount), 0) as total_invoiced, COALESCE(SUM(current_balance), 0) as total_arrears')
                ->first();

            return response()->json([
                'success'       => true,
                'overall'       => $this->formatCollectionRow($overallRow),
                'current_month' => $this->formatCollectionRow($monthRow),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading field officer collection stats: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load collection stats'], 500);
        }
    }

    /**
     * Generic dashboard data (AJAX) — leave balance + roles for logged-in user.
     */
    public function getGenericDashboardData()
    {
        try {
            $user    = Auth::user();
            $staffId = $user->staff_id;

            $roles = DB::table('cb_roles as r')
                ->join('cb_role_user as ru', 'r.id', '=', 'ru.role_id')
                ->where('ru.user_id', $user->id)
                ->select('r.name', 'r.display_name')
                ->get();

            $leaveBalances = collect();

            if ($staffId) {
                $leaveBalances = DB::table('sys_hr_leave_calendar as lc')
                    ->join('sys_hr_leave_types as lt', 'lt.id', '=', 'lc.leave_type_id')
                    ->where('lc.employee_id', $staffId)
                    ->where('lc.start_date', '<=', now())
                    ->where('lc.end_date', '>=', now())
                    ->select(
                        'lt.type_name',
                        'lc.entitled_days',
                        'lc.balance',
                        DB::raw('(CAST(lc.entitled_days AS NUMERIC) - CAST(lc.balance AS NUMERIC)) as used_days')
                    )
                    ->orderBy('lt.type_name')
                    ->get();
            }

            return response()->json([
                'success'        => true,
                'name'           => $user->name,
                'roles'          => $roles,
                'leave_balances' => $leaveBalances,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading generic dashboard data: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load dashboard data'], 500);
        }
    }

    /**
     * Director dashboard stats (AJAX) — combined HR + Finance + Portfolio.
     */
    public function getDirectorStats()
    {
        try {
            // People
            $totalEmployees = DB::table('sys_staff')->where('current_status_id', 1)->count();

            $totalFieldOfficers = DB::table('josya_users as u')
                ->join('cb_role_user as ru', 'u.id', '=', 'ru.user_id')
                ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                ->where('r.name', 'field_officer')
                ->count();

            $onLeaveToday = DB::table('sys_hr_leave_requests')
                ->whereIn('current_status_id', [5, 49])
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->distinct()
                ->count('staff_id');

            // Portfolio
            $totalClients    = DB::table('sys_pm_clients')->where('type_id', 1)->count();
            $totalProperties = DB::table('sys_pm_properties')->where('active', 1)->count();
            $totalUnits      = DB::table('sys_pm_units')->count();
            $totalTenants    = DB::table('sys_pm_clients')->where('type_id', 2)->count();
            $occupiedUnits   = DB::table('sys_pm_units')->where('occupancy_status_id', 1)->count();
            $vacantUnits     = DB::table('sys_pm_units')->where('occupancy_status_id', 3)->count();

            // Finance
            $leavesInOpenBooks = DB::table('sys_fin_cheque_books_261_repeat as cl')
                ->join('sys_fin_cheque_books as cb', 'cl.parent_id', '=', 'cb.id')
                ->where('cl.current_status_id', 1)
                ->where('cb.current_status_id', 1)
                ->count();

            $leavesInClosedBooks = DB::table('sys_fin_cheque_books_261_repeat as cl')
                ->join('sys_fin_cheque_books as cb', 'cl.parent_id', '=', 'cb.id')
                ->where('cl.current_status_id', 1)
                ->where('cb.current_status_id', 2)
                ->count();

            $returnsAdvance = (float) DB::table('cb_landlord_account')
                ->where('entry_type_code', '0009')
                ->where('is_recovered', 0)
                ->sum('debit_amount');

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_employees'        => $totalEmployees,
                    'field_officers'         => $totalFieldOfficers,
                    'on_leave_today'         => $onLeaveToday,
                    'total_clients'          => $totalClients,
                    'total_properties'       => $totalProperties,
                    'total_units'            => $totalUnits,
                    'total_tenants'          => $totalTenants,
                    'occupied_units'         => $occupiedUnits,
                    'vacant_units'           => $vacantUnits,
                    'leaves_in_open_books'   => $leavesInOpenBooks,
                    'leaves_in_closed_books' => $leavesInClosedBooks,
                    'returns_advance'        => round($returnsAdvance, 2),
                ],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading director stats: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load stats'], 500);
        }
    }

    /**
     * Director zone managers list (AJAX).
     */
    public function getDirectorZoneManagers()
    {
        try {
            $count = DB::table('josya_users as u')
                ->join('cb_role_user as ru', 'u.id', '=', 'ru.user_id')
                ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                ->where('r.name', 'zone_manager')
                ->count();

            $zones = DB::table('sys_pm_zones_assignments as za')
                ->join('sys_set_zones as z', 'z.id', '=', 'za.zone_id')
                ->join('josya_users as u', 'u.staff_id', '=', 'za.staff_id')
                ->join('cb_role_user as ru', 'ru.user_id', '=', 'u.id')
                ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                ->where('r.name', 'zone_manager')
                ->select('z.zone_name', 'u.name as manager_name')
                ->distinct()
                ->orderBy('z.zone_name')
                ->get();

            return response()->json([
                'success' => true,
                'count'   => $count,
                'zones'   => $zones,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading director zone managers: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load zone managers'], 500);
        }
    }

    /**
     * Director collection vs arrears (AJAX) — company-wide.
     */
    public function getDirectorCollection()
    {
        try {
            $overallRow = DB::table('sys_pm_debtors_invoices')
                ->selectRaw('COALESCE(SUM(total_amount), 0) as total_invoiced, COALESCE(SUM(current_balance), 0) as total_arrears')
                ->first();

            $monthRow = DB::table('sys_pm_debtors_invoices')
                ->whereYear('date_time', now()->year)
                ->whereMonth('date_time', now()->month)
                ->selectRaw('COALESCE(SUM(total_amount), 0) as total_invoiced, COALESCE(SUM(current_balance), 0) as total_arrears')
                ->first();

            return response()->json([
                'success'       => true,
                'overall'       => $this->formatCollectionRow($overallRow),
                'current_month' => $this->formatCollectionRow($monthRow),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading director collection: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load collection'], 500);
        }
    }

    /**
     * Accountant dashboard stats (AJAX).
     */
    public function getAccountantStats()
    {
        try {
            // Cheque leaves: available in open (active) books
            $leavesInOpenBooks = DB::table('sys_fin_cheque_books_261_repeat as cl')
                ->join('sys_fin_cheque_books as cb', 'cl.parent_id', '=', 'cb.id')
                ->where('cl.current_status_id', 1)
                ->where('cb.current_status_id', 1)
                ->count();

            // Cheque leaves: available in closed (exhausted) books
            $leavesInClosedBooks = DB::table('sys_fin_cheque_books_261_repeat as cl')
                ->join('sys_fin_cheque_books as cb', 'cl.parent_id', '=', 'cb.id')
                ->where('cl.current_status_id', 1)
                ->where('cb.current_status_id', 2)
                ->count();

            // Returns advance: unrecovered landlord advances
            $returnsAdvance = (float) DB::table('cb_landlord_account')
                ->where('entry_type_code', '0009')
                ->where('is_recovered', 0)
                ->sum('debit_amount');

            // Portfolio
            $totalProperties = DB::table('sys_pm_properties')->where('active', 1)->count();
            $totalUnits      = DB::table('sys_pm_units')->count();
            $totalTenants    = DB::table('sys_pm_clients')->where('type_id', 2)->count();

            // Occupancy for chart
            $occupiedUnits = DB::table('sys_pm_units')->where('occupancy_status_id', 1)->count();
            $vacantUnits   = DB::table('sys_pm_units')->where('occupancy_status_id', 3)->count();

            return response()->json([
                'success' => true,
                'stats' => [
                    'leaves_in_open_books'   => $leavesInOpenBooks,
                    'leaves_in_closed_books' => $leavesInClosedBooks,
                    'returns_advance'        => round($returnsAdvance, 2),
                    'total_properties'       => $totalProperties,
                    'total_units'            => $totalUnits,
                    'total_tenants'          => $totalTenants,
                    'occupied_units'         => $occupiedUnits,
                    'vacant_units'           => $vacantUnits,
                ],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading accountant stats: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load stats'], 500);
        }
    }

    /**
     * Accountant collection vs arrears (AJAX) — company-wide.
     */
    public function getAccountantCollection()
    {
        try {
            $overallRow = DB::table('sys_pm_debtors_invoices')
                ->selectRaw('COALESCE(SUM(total_amount), 0) as total_invoiced, COALESCE(SUM(current_balance), 0) as total_arrears')
                ->first();

            $monthRow = DB::table('sys_pm_debtors_invoices')
                ->whereYear('date_time', now()->year)
                ->whereMonth('date_time', now()->month)
                ->selectRaw('COALESCE(SUM(total_amount), 0) as total_invoiced, COALESCE(SUM(current_balance), 0) as total_arrears')
                ->first();

            return response()->json([
                'success'       => true,
                'overall'       => $this->formatCollectionRow($overallRow),
                'current_month' => $this->formatCollectionRow($monthRow),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading accountant collection: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load collection'], 500);
        }
    }

    /**
     * HR Manager dashboard stats (AJAX).
     */
    public function getHrManagerStats()
    {
        try {
            $totalEmployees = DB::table('sys_staff')->where('current_status_id', 1)->count();

            $totalFieldOfficers = DB::table('josya_users as u')
                ->join('cb_role_user as ru', 'u.id', '=', 'ru.user_id')
                ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                ->where('r.name', 'field_officer')
                ->count();

            $onLeaveToday = DB::table('sys_hr_leave_requests')
                ->whereIn('current_status_id', [5, 49])
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->distinct()
                ->count('staff_id');

            $totalClients    = DB::table('sys_pm_clients')->where('type_id', 1)->count();
            $totalProperties = DB::table('sys_pm_properties')->where('active', 1)->count();
            $totalTenants    = DB::table('sys_pm_clients')->where('type_id', 2)->count();

            $totalUnits    = DB::table('sys_pm_units')->count();
            $occupiedUnits = DB::table('sys_pm_units')->where('occupancy_status_id', 1)->count();
            $vacantUnits   = DB::table('sys_pm_units')->where('occupancy_status_id', 3)->count();

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_employees'  => $totalEmployees,
                    'field_officers'   => $totalFieldOfficers,
                    'on_leave_today'   => $onLeaveToday,
                    'total_clients'    => $totalClients,
                    'total_properties' => $totalProperties,
                    'total_tenants'    => $totalTenants,
                    'total_units'      => $totalUnits,
                    'occupied_units'   => $occupiedUnits,
                    'vacant_units'     => $vacantUnits,
                ],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading HR manager stats: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load stats'], 500);
        }
    }

    /**
     * HR Manager collection vs arrears (AJAX) — company-wide.
     */
    public function getHrManagerCollection()
    {
        try {
            $overallRow = DB::table('sys_pm_debtors_invoices')
                ->selectRaw('COALESCE(SUM(total_amount), 0) as total_invoiced, COALESCE(SUM(current_balance), 0) as total_arrears')
                ->first();

            $monthRow = DB::table('sys_pm_debtors_invoices')
                ->whereYear('date_time', now()->year)
                ->whereMonth('date_time', now()->month)
                ->selectRaw('COALESCE(SUM(total_amount), 0) as total_invoiced, COALESCE(SUM(current_balance), 0) as total_arrears')
                ->first();

            return response()->json([
                'success'       => true,
                'overall'       => $this->formatCollectionRow($overallRow),
                'current_month' => $this->formatCollectionRow($monthRow),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading HR manager collection: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load collection'], 500);
        }
    }

    /**
     * Field officer zone list (AJAX).
     */
    public function getFieldOfficerZones()
    {
        try {
            $staffId = Auth::user()->staff_id;

            $propertyIds = ZoneAssignment::where('staff_id', $staffId)
                ->distinct()
                ->pluck('property_id')
                ->filter()
                ->toArray();

            $zones = DB::table('sys_pm_properties as p')
                ->join('sys_set_zones as z', 'p.zone_id', '=', 'z.id')
                ->whereIn('p.id', $propertyIds)
                ->whereNotNull('p.zone_id')
                ->select('z.id', 'z.zone_name')
                ->distinct()
                ->orderBy('z.zone_name')
                ->get();

            return response()->json([
                'success' => true,
                'count'   => $zones->count(),
                'zones'   => $zones,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading field officer zones: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load zones'], 500);
        }
    }

    private function formatCollectionRow(object $row): array
    {
        $invoiced  = (float) ($row->total_invoiced ?? 0);
        $arrears   = (float) ($row->total_arrears  ?? 0);
        $collected = max(0, $invoiced - $arrears);

        return [
            'collected' => round($collected, 2),
            'arrears'   => round($arrears,   2),
            'total'     => round($invoiced,   2),
        ];
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
