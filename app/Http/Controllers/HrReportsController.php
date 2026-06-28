<?php

namespace App\Http\Controllers;

use Error;
use Carbon\Carbon;
use App\Models\Staff;
use App\Models\Department;
use App\Models\Position;
use App\Models\PayrollSummary;
use App\Models\HrLeaveType;
use App\Models\LeaveRequest;
use App\Models\LeaveCalendarPlan;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeContract;
use App\Models\EmployeeTermination;
use App\Models\DisciplineCase;
use App\Models\HelbDeduction;
use App\Models\ProratedDeduction;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StatutoryDeductionsExport;
use App\Exports\GenericReportExport;

class HrReportsController extends Controller
{
    /**
     * Display statutory deductions report page
     */
    public function statutoryDeductions()
    {
        return view('reports.statutory-deductions');
    }

    /**
     * Get statutory deductions data via AJAX
     */
    public function getStatutoryDeductions(Request $request)
    {
        try {
            $request->validate([
                'deduction_type' => 'required|in:NSSF,SHIF,Housing Levy',
                'period' => 'required|date_format:Y-m'
            ]);

            $deductionType = $request->input('deduction_type');
            $period = $request->input('period') . '-01';

            // Map deduction type to column name
            $columnMap = [
                'NSSF' => 'nssf',
                'SHIF' => 'nhif', // SHIF is NHIF in the database
                'Housing Levy' => 'housing_levy'
            ];

            $column = $columnMap[$deductionType];

            // Fetch payroll summaries with staff details
            $deductions = PayrollSummary::select(
                'sys_hr_payroll_summary.id',
                'sys_hr_payroll_summary.staff_number_id',
                'sys_hr_payroll_summary.staff_id',
                'sys_hr_payroll_summary.period',
                'sys_hr_payroll_summary.' . $column . ' as amount',
                'sys_staff.full_names',
                'sys_staff.national_id',
                'sys_staff.staff_number'
            )
                ->join('sys_staff', 'sys_hr_payroll_summary.staff_id', '=', 'sys_staff.id')
                ->whereYear('sys_hr_payroll_summary.period', '=', date('Y', strtotime($period)))
                ->whereMonth('sys_hr_payroll_summary.period', '=', date('m', strtotime($period)))
                ->where('sys_hr_payroll_summary.' . $column, '>', 0)
                ->orderBy('sys_staff.full_names', 'asc')
                ->get();

            // Calculate total
            $total = $deductions->sum('amount');

            // Format data for DataTables
            $data = $deductions->map(function ($item, $index) {
                return [
                    'no' => $index + 1,
                    'staff_number' => $item->staff_number ?? 'N/A',
                    'full_names' => $item->full_names,
                    'national_id' => $item->national_id ?? 'N/A',
                    'amount' => number_format($item->amount, 2)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => number_format($total, 2),
                'count' => $deductions->count()
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching statutory deductions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download statutory deductions as Excel
     */
    public function downloadStatutoryDeductions(Request $request)
    {
        try {
            $request->validate([
                'deduction_type' => 'required|in:NSSF,SHIF,Housing Levy',
                'period' => 'required|date_format:Y-m'
            ]);

            $deductionType = $request->input('deduction_type');
            $period = $request->input('period') . '-01';

            // Map deduction type to column name
            $columnMap = [
                'NSSF' => 'nssf',
                'SHIF' => 'nhif',
                'Housing Levy' => 'housing_levy'
            ];

            $column = $columnMap[$deductionType];

            // Fetch payroll summaries with staff details
            $deductions = PayrollSummary::select(
                'sys_hr_payroll_summary.staff_number_id',
                'sys_hr_payroll_summary.staff_id',
                'sys_hr_payroll_summary.period',
                'sys_hr_payroll_summary.' . $column . ' as amount',
                'sys_staff.full_names',
                'sys_staff.national_id',
                'sys_staff.staff_number'
            )
                ->join('sys_staff', 'sys_hr_payroll_summary.staff_id', '=', 'sys_staff.id')
                ->whereYear('sys_hr_payroll_summary.period', '=', date('Y', strtotime($period)))
                ->whereMonth('sys_hr_payroll_summary.period', '=', date('m', strtotime($period)))
                ->where('sys_hr_payroll_summary.' . $column, '>', 0)
                ->orderBy('sys_staff.full_names', 'asc')
                ->get();

            // Prepare data for export
            $exportData = [];
            $exportData[] = [$deductionType . ' Deductions Report'];
            $exportData[] = ['Period: ' . date('F Y', strtotime($period))];
            $exportData[] = ['Generated: ' . date('d/m/Y H:i:s')];
            $exportData[] = []; // Empty row
            $exportData[] = ['No.', 'Staff Number', 'Full Names', 'National ID', 'Amount (KSh)'];

            $total = 0;
            foreach ($deductions as $index => $item) {
                $exportData[] = [
                    $index + 1,
                    $item->staff_number ?? 'N/A',
                    $item->full_names,
                    $item->national_id ?? 'N/A',
                    number_format($item->amount, 2)
                ];
                $total += $item->amount;
            }

            $exportData[] = []; // Empty row
            $exportData[] = ['', '', '', 'Total:', number_format($total, 2)];
            $exportData[] = ['', '', '', 'Count:', $deductions->count()];

            // Create Excel file
            return Excel::download(
                new StatutoryDeductionsExport($exportData),
                $deductionType . '_Deductions_' . date('F_Y', strtotime($period)) . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error downloading statutory deductions: ' . $e->getMessage());
            return back()->with('error', 'Failed to download report: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 1. STAFF DIRECTORY
    // =========================================================================

    public function staffDirectory(): \Illuminate\View\View
    {
        $departments = Department::orderBy('department_name')->get(['id', 'department_name']);
        $positions   = Position::orderBy('position_name')->get(['id', 'position_name']);
        return view('reports.hr.staff-directory', compact('departments', 'positions'));
    }

    public function getStaffDirectory(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $query = Staff::query()
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->leftJoin('sys_set_staff_positions', 'sys_staff.position_id', '=', 'sys_set_staff_positions.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names', 'sys_staff.national_id',
                    'sys_staff.email_address', 'sys_staff.mobile_number', 'sys_staff.hire_date',
                    'sys_staff.current_status_id',
                    'sys_set_departments.department_name', 'sys_set_staff_positions.position_name'
                )
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id))
                ->when($request->position_id,   fn($q) => $q->where('sys_staff.position_id',   $request->position_id))
                ->when($request->status,        fn($q) => $q->where('sys_staff.current_status_id', $request->status))
                ->when($request->search, fn($q) => $q->where(fn($qq) =>
                    $qq->where('sys_staff.full_names',    'ILIKE', "%{$request->search}%")
                       ->orWhere('sys_staff.staff_number', 'ILIKE', "%{$request->search}%")
                       ->orWhere('sys_staff.national_id',  'ILIKE', "%{$request->search}%")
                ))
                ->orderBy('sys_staff.full_names');

            $records = $query->get();
            $data = $records->map(fn($r, $i) => [
                'no'           => $i + 1,
                'staff_number' => $r->staff_number   ?? '-',
                'full_names'   => $r->full_names,
                'national_id'  => $r->national_id    ?? '-',
                'department'   => $r->department_name ?? '-',
                'position'     => $r->position_name  ?? '-',
                'mobile'       => $r->mobile_number  ?? '-',
                'email'        => $r->email_address  ?? '-',
                'hire_date'    => $r->hire_date ? Carbon::parse($r->hire_date)->format('d-m-Y') : '-',
                'status'       => $r->current_status_id == 1 ? 'Active' : 'Inactive',
            ]);
            return response()->json(['success' => true, 'data' => $data, 'count' => $records->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report staffDirectory: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportStaffDirectory(Request $request)
    {
        try {
            $query = Staff::query()
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->leftJoin('sys_set_staff_positions', 'sys_staff.position_id', '=', 'sys_set_staff_positions.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names', 'sys_staff.national_id',
                    'sys_staff.email_address', 'sys_staff.mobile_number', 'sys_staff.hire_date',
                    'sys_staff.current_status_id',
                    'sys_set_departments.department_name', 'sys_set_staff_positions.position_name'
                )
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id))
                ->when($request->position_id,   fn($q) => $q->where('sys_staff.position_id',   $request->position_id))
                ->when($request->status,        fn($q) => $q->where('sys_staff.current_status_id', $request->status))
                ->when($request->search, fn($q) => $q->where(fn($qq) =>
                    $qq->where('sys_staff.full_names',    'ILIKE', "%{$request->search}%")
                       ->orWhere('sys_staff.staff_number', 'ILIKE', "%{$request->search}%")
                       ->orWhere('sys_staff.national_id',  'ILIKE', "%{$request->search}%")
                ))
                ->orderBy('sys_staff.full_names')
                ->get();

            $rows = $query->map(fn($r, $i) => [
                $i + 1,
                $r->staff_number   ?? '-',
                $r->full_names,
                $r->national_id    ?? '-',
                $r->department_name ?? '-',
                $r->position_name  ?? '-',
                $r->mobile_number  ?? '-',
                $r->email_address  ?? '-',
                $r->hire_date ? Carbon::parse($r->hire_date)->format('d-m-Y') : '-',
                $r->current_status_id == 1 ? 'Active' : 'Inactive',
            ])->values()->all();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Staff Directory',
                    headings:  ['#', 'Staff No', 'Name', 'National ID', 'Department', 'Position', 'Mobile', 'Email', 'Hire Date', 'Status'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 14, 'C' => 28, 'D' => 16, 'E' => 20, 'F' => 22, 'G' => 16, 'H' => 28, 'I' => 14, 'J' => 10],
                    hasTotals: false,
                ),
                'staff-directory-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report exportStaffDirectory: ' . $e->getMessage());
            return back()->with('error', 'Failed to export report.');
        }
    }

    // =========================================================================
    // 2. HEADCOUNT BY DEPARTMENT
    // =========================================================================

    public function headcount(): \Illuminate\View\View
    {
        $departments = Department::orderBy('department_name')->get(['id', 'department_name']);
        return view('reports.hr.headcount', compact('departments'));
    }

    public function getHeadcount(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $query = DB::table('sys_staff')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->select(
                    'sys_set_departments.department_name',
                    DB::raw("SUM(CASE WHEN sys_staff.current_status_id = 1 THEN 1 ELSE 0 END) as active"),
                    DB::raw("SUM(CASE WHEN sys_staff.current_status_id != 1 THEN 1 ELSE 0 END) as inactive"),
                    DB::raw("COUNT(sys_staff.id) as total")
                )
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id))
                ->groupBy('sys_set_departments.department_name', 'sys_staff.department_id')
                ->orderBy('sys_set_departments.department_name');

            $records = $query->get();
            $data = $records->map(fn($r, $i) => [
                'no'         => $i + 1,
                'department' => $r->department_name ?? 'Unassigned',
                'active'     => $r->active,
                'inactive'   => $r->inactive,
                'total'      => $r->total,
            ]);

            $totals = [
                'department' => 'TOTAL',
                'active'     => $data->sum('active'),
                'inactive'   => $data->sum('inactive'),
                'total'      => $data->sum('total'),
            ];

            return response()->json(['success' => true, 'data' => $data, 'totals' => $totals, 'count' => $records->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report headcount: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    // =========================================================================
    // 3. PAYROLL REGISTER
    // =========================================================================

    public function payrollRegister(): \Illuminate\View\View
    {
        $departments = Department::orderBy('department_name')->get(['id', 'department_name']);
        return view('reports.hr.payroll-register', compact('departments'));
    }

    public function getPayrollRegister(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate(['period' => 'required|date_format:Y-m']);
            [$year, $month] = explode('-', $request->period);

            $records = PayrollSummary::query()
                ->join('sys_staff', 'sys_hr_payroll_summary.staff_id', '=', 'sys_staff.id')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_hr_payroll_summary.basic_salary', 'sys_hr_payroll_summary.acting_allowance',
                    'sys_hr_payroll_summary.tax_payable', 'sys_hr_payroll_summary.nssf',
                    'sys_hr_payroll_summary.nhif', 'sys_hr_payroll_summary.housing_levy',
                    'sys_hr_payroll_summary.total_deductions', 'sys_hr_payroll_summary.net_pay',
                    'sys_set_departments.department_name'
                )
                ->whereYear('sys_hr_payroll_summary.period', $year)
                ->whereMonth('sys_hr_payroll_summary.period', $month)
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id))
                ->orderBy('sys_staff.full_names')
                ->get();

            $data = $records->map(fn($r, $i) => [
                'no'               => $i + 1,
                'staff_number'     => $r->staff_number ?? '-',
                'full_names'       => $r->full_names,
                'department'       => $r->department_name ?? '-',
                'basic_salary'     => number_format($r->basic_salary, 2),
                'allowances'       => number_format($r->acting_allowance, 2),
                'gross'            => number_format($r->basic_salary + $r->acting_allowance, 2),
                'paye'             => number_format($r->tax_payable, 2),
                'nssf'             => number_format($r->nssf, 2),
                'shif'             => number_format($r->nhif, 2),
                'housing_levy'     => number_format($r->housing_levy, 2),
                'total_deductions' => number_format($r->total_deductions, 2),
                'net_pay'          => number_format($r->net_pay, 2),
            ]);

            $totals = [
                'basic_salary'     => number_format($records->sum('basic_salary'), 2),
                'allowances'       => number_format($records->sum('acting_allowance'), 2),
                'gross'            => number_format($records->sum(fn($r) => $r->basic_salary + $r->acting_allowance), 2),
                'paye'             => number_format($records->sum('tax_payable'), 2),
                'nssf'             => number_format($records->sum('nssf'), 2),
                'shif'             => number_format($records->sum('nhif'), 2),
                'housing_levy'     => number_format($records->sum('housing_levy'), 2),
                'total_deductions' => number_format($records->sum('total_deductions'), 2),
                'net_pay'          => number_format($records->sum('net_pay'), 2),
            ];

            return response()->json(['success' => true, 'data' => $data, 'totals' => $totals, 'count' => $records->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report payrollRegister: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportPayrollRegister(Request $request)
    {
        try {
            $request->validate(['period' => 'required|date_format:Y-m']);
            [$year, $month] = explode('-', $request->period);

            $records = PayrollSummary::query()
                ->join('sys_staff', 'sys_hr_payroll_summary.staff_id', '=', 'sys_staff.id')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_hr_payroll_summary.basic_salary', 'sys_hr_payroll_summary.acting_allowance',
                    'sys_hr_payroll_summary.tax_payable', 'sys_hr_payroll_summary.nssf',
                    'sys_hr_payroll_summary.nhif', 'sys_hr_payroll_summary.housing_levy',
                    'sys_hr_payroll_summary.total_deductions', 'sys_hr_payroll_summary.net_pay',
                    'sys_set_departments.department_name'
                )
                ->whereYear('sys_hr_payroll_summary.period', $year)
                ->whereMonth('sys_hr_payroll_summary.period', $month)
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id))
                ->orderBy('sys_staff.full_names')
                ->get();

            $rows = $records->map(fn($r, $i) => [
                $i + 1,
                $r->staff_number ?? '-',
                $r->full_names,
                $r->department_name ?? '-',
                number_format($r->basic_salary, 2),
                number_format($r->acting_allowance, 2),
                number_format($r->basic_salary + $r->acting_allowance, 2),
                number_format($r->tax_payable, 2),
                number_format($r->nssf, 2),
                number_format($r->nhif, 2),
                number_format($r->housing_levy, 2),
                number_format($r->total_deductions, 2),
                number_format($r->net_pay, 2),
            ])->values()->all();

            // Totals row
            $rows[] = [
                '', 'TOTALS', '', '',
                number_format($records->sum('basic_salary'), 2),
                number_format($records->sum('acting_allowance'), 2),
                number_format($records->sum(fn($r) => $r->basic_salary + $r->acting_allowance), 2),
                number_format($records->sum('tax_payable'), 2),
                number_format($records->sum('nssf'), 2),
                number_format($records->sum('nhif'), 2),
                number_format($records->sum('housing_levy'), 2),
                number_format($records->sum('total_deductions'), 2),
                number_format($records->sum('net_pay'), 2),
            ];

            $periodLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');

            return Excel::download(
                GenericReportExport::build(
                    title:     'Payroll Register — ' . $periodLabel,
                    headings:  ['#', 'Staff No', 'Name', 'Department', 'Basic Salary', 'Allowances', 'Gross', 'PAYE', 'NSSF', 'SHIF', 'Housing Levy', 'Total Deductions', 'Net Pay'],
                    dataRows:  $rows,
                    meta:      ['Period: ' . $periodLabel, 'Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 13, 'C' => 26, 'D' => 20, 'E' => 14, 'F' => 13, 'G' => 13, 'H' => 12, 'I' => 12, 'J' => 12, 'K' => 14, 'L' => 16, 'M' => 13],
                    hasTotals: true,
                ),
                'payroll-register-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report exportPayrollRegister: ' . $e->getMessage());
            return back()->with('error', 'Failed to export report.');
        }
    }

    // =========================================================================
    // 4. PAYROLL SUMMARY
    // =========================================================================

    public function payrollSummary(): \Illuminate\View\View
    {
        return view('reports.hr.payroll-summary');
    }

    public function getPayrollSummary(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate(['period' => 'required|date_format:Y-m']);
            [$year, $month] = explode('-', $request->period);

            $records = PayrollSummary::query()
                ->join('sys_staff', 'sys_hr_payroll_summary.staff_id', '=', 'sys_staff.id')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->select(
                    'sys_set_departments.department_name',
                    DB::raw("COUNT(sys_hr_payroll_summary.id) as staff_count"),
                    DB::raw("SUM(sys_hr_payroll_summary.basic_salary) as total_basic"),
                    DB::raw("SUM(sys_hr_payroll_summary.basic_salary + sys_hr_payroll_summary.acting_allowance) as total_gross"),
                    DB::raw("SUM(sys_hr_payroll_summary.tax_payable) as total_paye"),
                    DB::raw("SUM(sys_hr_payroll_summary.nssf) as total_nssf"),
                    DB::raw("SUM(sys_hr_payroll_summary.nhif) as total_shif"),
                    DB::raw("SUM(sys_hr_payroll_summary.housing_levy) as total_housing_levy"),
                    DB::raw("SUM(sys_hr_payroll_summary.total_deductions) as total_deductions"),
                    DB::raw("SUM(sys_hr_payroll_summary.net_pay) as total_net_pay")
                )
                ->whereYear('sys_hr_payroll_summary.period', $year)
                ->whereMonth('sys_hr_payroll_summary.period', $month)
                ->groupBy('sys_set_departments.department_name', 'sys_staff.department_id')
                ->orderBy('sys_set_departments.department_name')
                ->get();

            $data = $records->map(fn($r, $i) => [
                'no'               => $i + 1,
                'department'       => $r->department_name ?? 'Unassigned',
                'staff_count'      => $r->staff_count,
                'total_basic'      => number_format($r->total_basic, 2),
                'total_gross'      => number_format($r->total_gross, 2),
                'total_paye'       => number_format($r->total_paye, 2),
                'total_nssf'       => number_format($r->total_nssf, 2),
                'total_shif'       => number_format($r->total_shif, 2),
                'total_housing'    => number_format($r->total_housing_levy, 2),
                'total_deductions' => number_format($r->total_deductions, 2),
                'total_net_pay'    => number_format($r->total_net_pay, 2),
            ]);

            $totals = [
                'staff_count'      => $records->sum('staff_count'),
                'total_basic'      => number_format($records->sum('total_basic'), 2),
                'total_gross'      => number_format($records->sum('total_gross'), 2),
                'total_paye'       => number_format($records->sum('total_paye'), 2),
                'total_nssf'       => number_format($records->sum('total_nssf'), 2),
                'total_shif'       => number_format($records->sum('total_shif'), 2),
                'total_housing'    => number_format($records->sum('total_housing_levy'), 2),
                'total_deductions' => number_format($records->sum('total_deductions'), 2),
                'total_net_pay'    => number_format($records->sum('total_net_pay'), 2),
            ];

            return response()->json(['success' => true, 'data' => $data, 'totals' => $totals, 'count' => $records->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report payrollSummary: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportPayrollSummary(Request $request)
    {
        try {
            $request->validate(['period' => 'required|date_format:Y-m']);
            [$year, $month] = explode('-', $request->period);

            $records = PayrollSummary::query()
                ->join('sys_staff', 'sys_hr_payroll_summary.staff_id', '=', 'sys_staff.id')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->select(
                    'sys_set_departments.department_name',
                    DB::raw("COUNT(sys_hr_payroll_summary.id) as staff_count"),
                    DB::raw("SUM(sys_hr_payroll_summary.basic_salary) as total_basic"),
                    DB::raw("SUM(sys_hr_payroll_summary.basic_salary + sys_hr_payroll_summary.acting_allowance) as total_gross"),
                    DB::raw("SUM(sys_hr_payroll_summary.tax_payable) as total_paye"),
                    DB::raw("SUM(sys_hr_payroll_summary.nssf) as total_nssf"),
                    DB::raw("SUM(sys_hr_payroll_summary.nhif) as total_shif"),
                    DB::raw("SUM(sys_hr_payroll_summary.housing_levy) as total_housing_levy"),
                    DB::raw("SUM(sys_hr_payroll_summary.total_deductions) as total_deductions"),
                    DB::raw("SUM(sys_hr_payroll_summary.net_pay) as total_net_pay")
                )
                ->whereYear('sys_hr_payroll_summary.period', $year)
                ->whereMonth('sys_hr_payroll_summary.period', $month)
                ->groupBy('sys_set_departments.department_name', 'sys_staff.department_id')
                ->orderBy('sys_set_departments.department_name')
                ->get();

            $rows = $records->map(fn($r, $i) => [
                $i + 1,
                $r->department_name ?? 'Unassigned',
                $r->staff_count,
                number_format($r->total_basic, 2),
                number_format($r->total_gross, 2),
                number_format($r->total_paye, 2),
                number_format($r->total_nssf, 2),
                number_format($r->total_shif, 2),
                number_format($r->total_housing_levy, 2),
                number_format($r->total_deductions, 2),
                number_format($r->total_net_pay, 2),
            ])->values()->all();

            $rows[] = [
                '', 'GRAND TOTAL',
                $records->sum('staff_count'),
                number_format($records->sum('total_basic'), 2),
                number_format($records->sum('total_gross'), 2),
                number_format($records->sum('total_paye'), 2),
                number_format($records->sum('total_nssf'), 2),
                number_format($records->sum('total_shif'), 2),
                number_format($records->sum('total_housing_levy'), 2),
                number_format($records->sum('total_deductions'), 2),
                number_format($records->sum('total_net_pay'), 2),
            ];

            $periodLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');

            return Excel::download(
                GenericReportExport::build(
                    title:     'Payroll Summary — ' . $periodLabel,
                    headings:  ['#', 'Department', 'Staff Count', 'Total Basic', 'Total Gross', 'Total PAYE', 'Total NSSF', 'Total SHIF', 'Total Housing Levy', 'Total Deductions', 'Total Net Pay'],
                    dataRows:  $rows,
                    meta:      ['Period: ' . $periodLabel, 'Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 22, 'C' => 12, 'D' => 15, 'E' => 14, 'F' => 13, 'G' => 12, 'H' => 12, 'I' => 18, 'J' => 16, 'K' => 14],
                    hasTotals: true,
                ),
                'payroll-summary-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report exportPayrollSummary: ' . $e->getMessage());
            return back()->with('error', 'Failed to export report.');
        }
    }

    // =========================================================================
    // 5. HELB SCHEDULE
    // =========================================================================

    public function helbSchedule(): \Illuminate\View\View
    {
        return view('reports.hr.helb-schedule');
    }

    public function getHelbSchedule(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $query = HelbDeduction::query()
                ->join('sys_staff', 'sys_helb_deductions.staff_id', '=', 'sys_staff.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_helb_deductions.monthly_deduction', 'sys_helb_deductions.loan_balance',
                    'sys_helb_deductions.start_deduction_date', 'sys_helb_deductions.cleared'
                )
                ->when($request->status === 'active',  fn($q) => $q->where('sys_helb_deductions.cleared', 0))
                ->when($request->status === 'cleared', fn($q) => $q->where('sys_helb_deductions.cleared', 1))
                ->orderBy('sys_staff.full_names');

            $records = $query->get();
            $data = $records->map(fn($r, $i) => [
                'no'               => $i + 1,
                'staff_number'     => $r->staff_number ?? '-',
                'full_names'       => $r->full_names,
                'monthly_deduction'=> number_format($r->monthly_deduction, 2),
                'loan_balance'     => number_format($r->loan_balance, 2),
                'start_date'       => $r->start_deduction_date ? Carbon::parse($r->start_deduction_date)->format('d-m-Y') : '-',
                'cleared'          => $r->cleared ? 'Yes' : 'No',
            ]);

            return response()->json(['success' => true, 'data' => $data, 'count' => $records->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report helbSchedule: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportHelbSchedule(Request $request)
    {
        try {
            $records = HelbDeduction::query()
                ->join('sys_staff', 'sys_helb_deductions.staff_id', '=', 'sys_staff.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_helb_deductions.monthly_deduction', 'sys_helb_deductions.loan_balance',
                    'sys_helb_deductions.start_deduction_date', 'sys_helb_deductions.cleared'
                )
                ->when($request->status === 'active',  fn($q) => $q->where('sys_helb_deductions.cleared', 0))
                ->when($request->status === 'cleared', fn($q) => $q->where('sys_helb_deductions.cleared', 1))
                ->orderBy('sys_staff.full_names')
                ->get();

            $rows = $records->map(fn($r, $i) => [
                $i + 1,
                $r->staff_number ?? '-',
                $r->full_names,
                number_format($r->monthly_deduction, 2),
                number_format($r->loan_balance, 2),
                $r->start_deduction_date ? Carbon::parse($r->start_deduction_date)->format('d-m-Y') : '-',
                $r->cleared ? 'Yes' : 'No',
            ])->values()->all();

            return Excel::download(
                GenericReportExport::build(
                    title:     'HELB Deductions Schedule',
                    headings:  ['#', 'Staff No', 'Name', 'Monthly Deduction', 'Loan Balance', 'Start Date', 'Cleared'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 13, 'C' => 28, 'D' => 18, 'E' => 15, 'F' => 13, 'G' => 10],
                    hasTotals: false,
                ),
                'helb-schedule-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report exportHelbSchedule: ' . $e->getMessage());
            return back()->with('error', 'Failed to export report.');
        }
    }

    // =========================================================================
    // 6. LOAN / ADVANCE SCHEDULE
    // =========================================================================

    public function loanSchedule(): \Illuminate\View\View
    {
        return view('reports.hr.loan-schedule');
    }

    public function getLoanSchedule(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $query = ProratedDeduction::query()
                ->join('sys_staff', 'sys_hr_employee_amortized_deductions.staff_id', '=', 'sys_staff.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_hr_employee_amortized_deductions.principal_amount',
                    'sys_hr_employee_amortized_deductions.interest_rate',
                    'sys_hr_employee_amortized_deductions.monthly_deduction',
                    'sys_hr_employee_amortized_deductions.total_amount',
                    'sys_hr_employee_amortized_deductions.repayment_months',
                    'sys_hr_employee_amortized_deductions.current_status_id'
                )
                ->when($request->status === 'active',  fn($q) => $q->where('sys_hr_employee_amortized_deductions.current_status_id', 1))
                ->when($request->status === 'cleared', fn($q) => $q->where('sys_hr_employee_amortized_deductions.current_status_id', '!=', 1))
                ->when($request->search, fn($q) => $q->where(fn($qq) =>
                    $qq->where('sys_staff.full_names',    'ILIKE', "%{$request->search}%")
                       ->orWhere('sys_staff.staff_number', 'ILIKE', "%{$request->search}%")
                ))
                ->orderBy('sys_staff.full_names');

            $records = $query->get();
            $data = $records->map(fn($r, $i) => [
                'no'               => $i + 1,
                'staff_number'     => $r->staff_number ?? '-',
                'full_names'       => $r->full_names,
                'principal'        => number_format($r->principal_amount, 2),
                'interest_rate'    => number_format($r->interest_rate, 2) . '%',
                'monthly_deduction'=> number_format($r->monthly_deduction, 2),
                'total_amount'     => number_format($r->total_amount, 2),
                'months'           => $r->repayment_months ?? '-',
                'cleared'          => $r->current_status_id == 1 ? 'Active' : 'Cleared',
            ]);

            return response()->json(['success' => true, 'data' => $data, 'count' => $records->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report loanSchedule: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportLoanSchedule(Request $request)
    {
        try {
            $records = ProratedDeduction::query()
                ->join('sys_staff', 'sys_hr_employee_amortized_deductions.staff_id', '=', 'sys_staff.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_hr_employee_amortized_deductions.principal_amount',
                    'sys_hr_employee_amortized_deductions.interest_rate',
                    'sys_hr_employee_amortized_deductions.monthly_deduction',
                    'sys_hr_employee_amortized_deductions.total_amount',
                    'sys_hr_employee_amortized_deductions.repayment_months',
                    'sys_hr_employee_amortized_deductions.current_status_id'
                )
                ->when($request->status === 'active',  fn($q) => $q->where('sys_hr_employee_amortized_deductions.current_status_id', 1))
                ->when($request->status === 'cleared', fn($q) => $q->where('sys_hr_employee_amortized_deductions.current_status_id', '!=', 1))
                ->when($request->search, fn($q) => $q->where(fn($qq) =>
                    $qq->where('sys_staff.full_names',    'ILIKE', "%{$request->search}%")
                       ->orWhere('sys_staff.staff_number', 'ILIKE', "%{$request->search}%")
                ))
                ->orderBy('sys_staff.full_names')
                ->get();

            $rows = $records->map(fn($r, $i) => [
                $i + 1,
                $r->staff_number ?? '-',
                $r->full_names,
                number_format($r->principal_amount, 2),
                number_format($r->interest_rate, 2) . '%',
                number_format($r->monthly_deduction, 2),
                number_format($r->total_amount, 2),
                $r->repayment_months ?? '-',
                $r->current_status_id == 1 ? 'Active' : 'Cleared',
            ])->values()->all();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Loan & Advance Schedule',
                    headings:  ['#', 'Staff No', 'Name', 'Principal', 'Interest Rate', 'Monthly Deduction', 'Total Amount', 'Months', 'Status'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 13, 'C' => 28, 'D' => 14, 'E' => 13, 'F' => 18, 'G' => 14, 'H' => 9, 'I' => 10],
                    hasTotals: false,
                ),
                'loan-schedule-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report exportLoanSchedule: ' . $e->getMessage());
            return back()->with('error', 'Failed to export report.');
        }
    }

    // =========================================================================
    // 7. LEAVE SUMMARY
    // =========================================================================

    public function leaveSummary(): \Illuminate\View\View
    {
        $departments = Department::orderBy('department_name')->get(['id', 'department_name']);
        $leaveTypes  = HrLeaveType::orderBy('type_name')->get(['id', 'type_name']);
        return view('reports.hr.leave-summary', compact('departments', 'leaveTypes'));
    }

    public function getLeaveSummary(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $year = $request->year ?? now()->year;

            $records = LeaveRequest::query()
                ->join('sys_staff', 'sys_hr_leave_requests.staff_id', '=', 'sys_staff.id')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->leftJoin('sys_hr_leave_types', 'sys_hr_leave_requests.leave_type_id', '=', 'sys_hr_leave_types.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_set_departments.department_name',
                    'sys_hr_leave_types.type_name as leave_type',
                    DB::raw("SUM(sys_hr_leave_requests.days) as total_used"),
                    DB::raw("MAX(sys_hr_leave_requests.entitled_days) as entitled_days"),
                    DB::raw("MIN(sys_hr_leave_requests.days_balance) as days_balance")
                )
                ->whereYear('sys_hr_leave_requests.start_date', $year)
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id))
                ->when($request->leave_type_id, fn($q) => $q->where('sys_hr_leave_requests.leave_type_id', $request->leave_type_id))
                ->groupBy(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_set_departments.department_name',
                    'sys_hr_leave_types.type_name',
                    'sys_hr_leave_requests.staff_id', 'sys_hr_leave_requests.leave_type_id'
                )
                ->orderBy('sys_staff.full_names')
                ->get();

            $data = $records->map(fn($r, $i) => [
                'no'            => $i + 1,
                'staff_number'  => $r->staff_number  ?? '-',
                'full_names'    => $r->full_names,
                'department'    => $r->department_name ?? '-',
                'leave_type'    => $r->leave_type      ?? '-',
                'entitled_days' => $r->entitled_days   ?? 0,
                'days_used'     => $r->total_used      ?? 0,
                'balance'       => $r->days_balance    ?? 0,
            ]);

            return response()->json(['success' => true, 'data' => $data, 'count' => $records->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report leaveSummary: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportLeaveSummary(Request $request)
    {
        try {
            $year = $request->year ?? now()->year;

            $records = LeaveRequest::query()
                ->join('sys_staff', 'sys_hr_leave_requests.staff_id', '=', 'sys_staff.id')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->leftJoin('sys_hr_leave_types', 'sys_hr_leave_requests.leave_type_id', '=', 'sys_hr_leave_types.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_set_departments.department_name',
                    'sys_hr_leave_types.type_name as leave_type',
                    DB::raw("SUM(sys_hr_leave_requests.days) as total_used"),
                    DB::raw("MAX(sys_hr_leave_requests.entitled_days) as entitled_days"),
                    DB::raw("MIN(sys_hr_leave_requests.days_balance) as days_balance")
                )
                ->whereYear('sys_hr_leave_requests.start_date', $year)
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id))
                ->when($request->leave_type_id, fn($q) => $q->where('sys_hr_leave_requests.leave_type_id', $request->leave_type_id))
                ->groupBy(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_set_departments.department_name',
                    'sys_hr_leave_types.type_name',
                    'sys_hr_leave_requests.staff_id', 'sys_hr_leave_requests.leave_type_id'
                )
                ->orderBy('sys_staff.full_names')
                ->get();

            $rows = $records->map(fn($r, $i) => [
                $i + 1,
                $r->staff_number  ?? '-',
                $r->full_names,
                $r->department_name ?? '-',
                $r->leave_type      ?? '-',
                $r->entitled_days   ?? 0,
                $r->total_used      ?? 0,
                $r->days_balance    ?? 0,
            ])->values()->all();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Leave Summary — ' . $year,
                    headings:  ['#', 'Staff No', 'Name', 'Department', 'Leave Type', 'Entitled Days', 'Days Used', 'Balance'],
                    dataRows:  $rows,
                    meta:      ['Year: ' . $year, 'Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 13, 'C' => 28, 'D' => 20, 'E' => 18, 'F' => 14, 'G' => 12, 'H' => 10],
                    hasTotals: false,
                ),
                'leave-summary-' . $year . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report exportLeaveSummary: ' . $e->getMessage());
            return back()->with('error', 'Failed to export report.');
        }
    }

    // =========================================================================
    // 8. LEAVE CALENDAR
    // =========================================================================

    public function leaveCalendar(): \Illuminate\View\View
    {
        $departments = Department::orderBy('department_name')->get(['id', 'department_name']);
        return view('reports.hr.leave-calendar', compact('departments'));
    }

    public function getLeaveCalendar(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $year = $request->year ?? now()->year;

            $records = LeaveCalendarPlan::query()
                ->join('sys_staff', 'sys_hr_leave_calendar.employee_id', '=', 'sys_staff.id')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->leftJoin('sys_hr_leave_types', 'sys_hr_leave_calendar.leave_type_id', '=', 'sys_hr_leave_types.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_set_departments.department_name',
                    'sys_hr_leave_types.type_name as leave_type',
                    'sys_hr_leave_calendar.start_date', 'sys_hr_leave_calendar.end_date',
                    'sys_hr_leave_calendar.entitled_days', 'sys_hr_leave_calendar.balance',
                    'sys_hr_leave_calendar.current_status_id'
                )
                ->whereYear('sys_hr_leave_calendar.start_date', $year)
                ->when($request->month,         fn($q) => $q->whereMonth('sys_hr_leave_calendar.start_date', $request->month))
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id))
                ->orderBy('sys_hr_leave_calendar.start_date')
                ->get();

            $data = $records->map(fn($r, $i) => [
                'no'            => $i + 1,
                'staff_number'  => $r->staff_number    ?? '-',
                'full_names'    => $r->full_names,
                'department'    => $r->department_name  ?? '-',
                'leave_type'    => $r->leave_type        ?? '-',
                'start_date'    => $r->start_date ? Carbon::parse($r->start_date)->format('d-m-Y') : '-',
                'end_date'      => $r->end_date   ? Carbon::parse($r->end_date)->format('d-m-Y')   : '-',
                'days'          => $r->entitled_days ?? '-',
                'balance'       => $r->balance       ?? '-',
                'status'        => $r->current_status_id == 1 ? 'Active' : ($r->current_status_id == 2 ? 'Completed' : 'Pending'),
            ]);

            return response()->json(['success' => true, 'data' => $data, 'count' => $records->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report leaveCalendar: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    // =========================================================================
    // 9. ATTENDANCE REPORT
    // =========================================================================

    public function attendance(): \Illuminate\View\View
    {
        $departments = Department::orderBy('department_name')->get(['id', 'department_name']);
        return view('reports.hr.attendance', compact('departments'));
    }

    public function getAttendance(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate(['month_year' => 'required|date_format:Y-m']);
            $monthYear = $request->month_year . '-01';

            $records = EmployeeAttendance::query()
                ->join('sys_staff', 'sys_hr_employees_attendance.employee_id', '=', 'sys_staff.staff_number')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_set_departments.department_name',
                    'sys_hr_employees_attendance.current_status_id',
                    DB::raw("COUNT(sys_hr_employees_attendance.id) as days_present")
                )
                ->whereYear('sys_hr_employees_attendance.date_time', date('Y', strtotime($monthYear)))
                ->whereMonth('sys_hr_employees_attendance.date_time', date('m', strtotime($monthYear)))
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id))
                ->groupBy(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_set_departments.department_name',
                    'sys_hr_employees_attendance.current_status_id',
                    'sys_hr_employees_attendance.employee_id'
                )
                ->orderBy('sys_staff.full_names')
                ->get();

            $data = $records->map(fn($r, $i) => [
                'no'           => $i + 1,
                'staff_number' => $r->staff_number    ?? '-',
                'full_names'   => $r->full_names,
                'department'   => $r->department_name  ?? '-',
                'days_present' => $r->days_present,
                'status'       => $r->current_status_id == 1 ? 'Active' : 'Inactive',
            ]);

            return response()->json(['success' => true, 'data' => $data, 'count' => $records->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report attendance: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportAttendance(Request $request)
    {
        try {
            $request->validate(['month_year' => 'required|date_format:Y-m']);
            $monthYear = $request->month_year . '-01';

            $records = EmployeeAttendance::query()
                ->join('sys_staff', 'sys_hr_employees_attendance.employee_id', '=', 'sys_staff.staff_number')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_set_departments.department_name',
                    'sys_hr_employees_attendance.current_status_id',
                    DB::raw("COUNT(sys_hr_employees_attendance.id) as days_present")
                )
                ->whereYear('sys_hr_employees_attendance.date_time', date('Y', strtotime($monthYear)))
                ->whereMonth('sys_hr_employees_attendance.date_time', date('m', strtotime($monthYear)))
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id))
                ->groupBy(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_set_departments.department_name',
                    'sys_hr_employees_attendance.current_status_id',
                    'sys_hr_employees_attendance.employee_id'
                )
                ->orderBy('sys_staff.full_names')
                ->get();

            $periodLabel = Carbon::parse($monthYear)->format('F Y');
            $rows = $records->map(fn($r, $i) => [
                $i + 1,
                $r->staff_number   ?? '-',
                $r->full_names,
                $r->department_name ?? '-',
                $r->days_present,
                $r->current_status_id == 1 ? 'Active' : 'Inactive',
            ])->values()->all();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Attendance Report — ' . $periodLabel,
                    headings:  ['#', 'Staff No', 'Name', 'Department', 'Days Present', 'Status'],
                    dataRows:  $rows,
                    meta:      ['Period: ' . $periodLabel, 'Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 13, 'C' => 28, 'D' => 20, 'E' => 14, 'F' => 10],
                    hasTotals: false,
                ),
                'attendance-' . str_replace('-', '', $request->month_year) . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report exportAttendance: ' . $e->getMessage());
            return back()->with('error', 'Failed to export report.');
        }
    }

    // =========================================================================
    // 10. EMPLOYEE CONTRACTS
    // =========================================================================

    public function contracts(): \Illuminate\View\View
    {
        $departments = Department::orderBy('department_name')->get(['id', 'department_name']);
        return view('reports.hr.contracts', compact('departments'));
    }

    public function getContracts(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $query = EmployeeContract::query()
                ->join('sys_staff', 'sys_hr_employee_contracts.staff_id', '=', 'sys_staff.id')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->leftJoin('sys_set_staff_positions', 'sys_hr_employee_contracts.position_id', '=', 'sys_set_staff_positions.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_set_departments.department_name',
                    'sys_set_staff_positions.position_name',
                    'sys_hr_employee_contracts.date_start', 'sys_hr_employee_contracts.date_end',
                    'sys_hr_employee_contracts.basic_salary', 'sys_hr_employee_contracts.current_status_id'
                )
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id));

            if ($request->status === 'Active') {
                $query->where('sys_hr_employee_contracts.current_status_id', 21);
            } elseif ($request->status === 'Expiring') {
                $query->where('sys_hr_employee_contracts.current_status_id', 21)
                      ->where('sys_hr_employee_contracts.date_end', '<=', now()->addDays(90))
                      ->where('sys_hr_employee_contracts.date_end', '>=', now());
            } elseif ($request->status === 'Expired') {
                $query->where('sys_hr_employee_contracts.date_end', '<', now());
            }

            $records = $query->orderBy('sys_staff.full_names')->get();

            $data = $records->map(fn($r, $i) => [
                'no'            => $i + 1,
                'staff_number'  => $r->staff_number    ?? '-',
                'full_names'    => $r->full_names,
                'position'      => $r->position_name   ?? '-',
                'start_date'    => $r->date_start ? Carbon::parse($r->date_start)->format('d-m-Y') : '-',
                'end_date'      => $r->date_end   ? Carbon::parse($r->date_end)->format('d-m-Y')   : '-',
                'salary'        => number_format($r->basic_salary, 2),
                'status'        => $r->current_status_id == 21 ? 'Active' : 'Inactive',
                'days_remaining'=> $r->date_end ? max(0, (int) now()->diffInDays(Carbon::parse($r->date_end), false)) : '-',
            ]);

            return response()->json(['success' => true, 'data' => $data, 'count' => $records->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report contracts: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportContracts(Request $request)
    {
        try {
            $query = EmployeeContract::query()
                ->join('sys_staff', 'sys_hr_employee_contracts.staff_id', '=', 'sys_staff.id')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->leftJoin('sys_set_staff_positions', 'sys_hr_employee_contracts.position_id', '=', 'sys_set_staff_positions.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names',
                    'sys_set_departments.department_name',
                    'sys_set_staff_positions.position_name',
                    'sys_hr_employee_contracts.date_start', 'sys_hr_employee_contracts.date_end',
                    'sys_hr_employee_contracts.basic_salary', 'sys_hr_employee_contracts.current_status_id'
                )
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id));

            if ($request->status === 'Active') {
                $query->where('sys_hr_employee_contracts.current_status_id', 21);
            } elseif ($request->status === 'Expiring') {
                $query->where('sys_hr_employee_contracts.current_status_id', 21)
                      ->where('sys_hr_employee_contracts.date_end', '<=', now()->addDays(90))
                      ->where('sys_hr_employee_contracts.date_end', '>=', now());
            } elseif ($request->status === 'Expired') {
                $query->where('sys_hr_employee_contracts.date_end', '<', now());
            }

            $records = $query->orderBy('sys_staff.full_names')->get();

            $rows = $records->map(fn($r, $i) => [
                $i + 1,
                $r->staff_number  ?? '-',
                $r->full_names,
                $r->position_name ?? '-',
                $r->date_start ? Carbon::parse($r->date_start)->format('d-m-Y') : '-',
                $r->date_end   ? Carbon::parse($r->date_end)->format('d-m-Y')   : '-',
                number_format($r->basic_salary, 2),
                $r->current_status_id == 21 ? 'Active' : 'Inactive',
                $r->date_end ? max(0, (int) now()->diffInDays(Carbon::parse($r->date_end), false)) : '-',
            ])->values()->all();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Employee Contracts',
                    headings:  ['#', 'Staff No', 'Name', 'Position', 'Start Date', 'End Date', 'Salary', 'Status', 'Days Remaining'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 13, 'C' => 28, 'D' => 22, 'E' => 13, 'F' => 13, 'G' => 14, 'H' => 10, 'I' => 14],
                    hasTotals: false,
                ),
                'employee-contracts-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report exportContracts: ' . $e->getMessage());
            return back()->with('error', 'Failed to export report.');
        }
    }

    // =========================================================================
    // 11. SEPARATIONS / TERMINATIONS
    // =========================================================================

    public function terminations(): \Illuminate\View\View
    {
        $departments = Department::orderBy('department_name')->get(['id', 'department_name']);
        return view('reports.hr.terminations', compact('departments'));
    }

    public function getTerminations(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $query = EmployeeTermination::query()
                ->join('sys_staff', 'hr_employment_termination.staff_id', '=', 'sys_staff.id')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->leftJoin('sys_set_staff_positions', 'sys_staff.position_id', '=', 'sys_set_staff_positions.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names', 'sys_staff.hire_date',
                    'sys_set_departments.department_name',
                    'sys_set_staff_positions.position_name',
                    'hr_employment_termination.date_time as termination_date',
                    'hr_employment_termination.termination_reason_id',
                    'hr_employment_termination.severerance_payment'
                )
                ->when($request->date_from, fn($q) => $q->whereDate('hr_employment_termination.date_time', '>=', $request->date_from))
                ->when($request->date_to,   fn($q) => $q->whereDate('hr_employment_termination.date_time', '<=', $request->date_to))
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id))
                ->orderBy('hr_employment_termination.date_time', 'desc');

            $records = $query->get();
            $data = $records->map(fn($r, $i) => [
                'no'               => $i + 1,
                'staff_number'     => $r->staff_number    ?? '-',
                'full_names'       => $r->full_names,
                'department'       => $r->department_name  ?? '-',
                'position'         => $r->position_name    ?? '-',
                'hire_date'        => $r->hire_date ? Carbon::parse($r->hire_date)->format('d-m-Y') : '-',
                'termination_date' => $r->termination_date ? Carbon::parse($r->termination_date)->format('d-m-Y') : '-',
                'reason'           => $r->termination_reason_id ?? '-',
                'severance_pay'    => number_format($r->severerance_payment ?? 0, 2),
            ]);

            return response()->json(['success' => true, 'data' => $data, 'count' => $records->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report terminations: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportTerminations(Request $request)
    {
        try {
            $records = EmployeeTermination::query()
                ->join('sys_staff', 'hr_employment_termination.staff_id', '=', 'sys_staff.id')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->leftJoin('sys_set_staff_positions', 'sys_staff.position_id', '=', 'sys_set_staff_positions.id')
                ->select(
                    'sys_staff.staff_number', 'sys_staff.full_names', 'sys_staff.hire_date',
                    'sys_set_departments.department_name',
                    'sys_set_staff_positions.position_name',
                    'hr_employment_termination.date_time as termination_date',
                    'hr_employment_termination.termination_reason_id',
                    'hr_employment_termination.severerance_payment'
                )
                ->when($request->date_from, fn($q) => $q->whereDate('hr_employment_termination.date_time', '>=', $request->date_from))
                ->when($request->date_to,   fn($q) => $q->whereDate('hr_employment_termination.date_time', '<=', $request->date_to))
                ->when($request->department_id, fn($q) => $q->where('sys_staff.department_id', $request->department_id))
                ->orderBy('hr_employment_termination.date_time', 'desc')
                ->get();

            $rows = $records->map(fn($r, $i) => [
                $i + 1,
                $r->staff_number   ?? '-',
                $r->full_names,
                $r->department_name ?? '-',
                $r->position_name   ?? '-',
                $r->hire_date ? Carbon::parse($r->hire_date)->format('d-m-Y') : '-',
                $r->termination_date ? Carbon::parse($r->termination_date)->format('d-m-Y') : '-',
                $r->termination_reason_id ?? '-',
                number_format($r->severerance_payment ?? 0, 2),
            ])->values()->all();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Staff Separations & Terminations',
                    headings:  ['#', 'Staff No', 'Name', 'Department', 'Position', 'Hire Date', 'Termination Date', 'Reason', 'Severance Pay'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 13, 'C' => 28, 'D' => 20, 'E' => 22, 'F' => 13, 'G' => 17, 'H' => 12, 'I' => 14],
                    hasTotals: false,
                ),
                'terminations-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report exportTerminations: ' . $e->getMessage());
            return back()->with('error', 'Failed to export report.');
        }
    }

    // =========================================================================
    // 13. OVERTIME & HOURS ANALYSIS
    // =========================================================================

    public function overtime(): \Illuminate\View\View
    {
        $departments = Department::orderBy('department_name')->get(['id','department_name']);
        $positions   = Position::orderBy('position_name')->get(['id','position_name']);
        return view('reports.hr.overtime', compact('departments','positions'));
    }

    public function getOvertime(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $query = DB::table('sys_hr_employees_attendance as a')
                ->join('sys_staff as s', 's.staff_number', '=', 'a.employee_id')
                ->leftJoin('sys_hr_departments as d', 'd.id', '=', 's.department_id')
                ->leftJoin('sys_hr_positions as p', 'p.id', '=', 's.position_id')
                ->select([
                    's.staff_number', 's.full_names',
                    DB::raw("COALESCE(d.department_name, '-') as department"),
                    DB::raw("COALESCE(p.position_name, '-') as position"),
                    DB::raw('COUNT(a.id) as days_recorded'),
                    DB::raw('SUM(COALESCE(a.hours_worked, 0)) as total_hours'),
                    DB::raw('SUM(CASE WHEN COALESCE(a.hours_worked,0) > 8 THEN COALESCE(a.hours_worked,0)-8 ELSE 0 END) as overtime_hours'),
                    DB::raw('SUM(CASE WHEN COALESCE(a.hours_worked,0) < 8 AND COALESCE(a.hours_worked,0) > 0 THEN 1 ELSE 0 END) as short_days'),
                ])
                ->when($request->department_id, fn($q) => $q->where('s.department_id', $request->department_id))
                ->when($request->position_id,   fn($q) => $q->where('s.position_id',   $request->position_id))
                ->when($request->date_from,     fn($q) => $q->where('a.attendance_date', '>=', $request->date_from))
                ->when($request->date_to,       fn($q) => $q->where('a.attendance_date', '<=', $request->date_to))
                ->groupBy('s.staff_number','s.full_names','d.department_name','p.position_name')
                ->orderBy('s.full_names');
            $rows = $query->get();
            $data = $rows->map(function($r, $i) {
                return [
                    'no'             => $i + 1,
                    'staff_number'   => $r->staff_number,
                    'full_names'     => $r->full_names,
                    'department'     => $r->department,
                    'position'       => $r->position,
                    'days_recorded'  => $r->days_recorded,
                    'total_hours'    => number_format($r->total_hours, 1),
                    'overtime_hours' => number_format($r->overtime_hours, 1),
                    'short_days'     => $r->short_days,
                ];
            });
            // chart: top departments by overtime
            $chart = $rows->groupBy('department')->map(fn($g) => round($g->sum('overtime_hours'), 1));
            return response()->json(['success' => true, 'data' => $data, 'count' => $data->count(), 'chart' => $chart]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('getOvertime: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report']);
        }
    }

    public function exportOvertime(Request $request)
    {
        $rows = DB::table('sys_hr_employees_attendance as a')
            ->join('sys_staff as s', 's.staff_number', '=', 'a.employee_id')
            ->leftJoin('sys_hr_departments as d', 'd.id', '=', 's.department_id')
            ->leftJoin('sys_hr_positions as p', 'p.id', '=', 's.position_id')
            ->select([
                's.staff_number', 's.full_names',
                DB::raw("COALESCE(d.department_name,'-') as department"),
                DB::raw("COALESCE(p.position_name,'-') as position"),
                DB::raw('COUNT(a.id) as days_recorded'),
                DB::raw('SUM(COALESCE(a.hours_worked,0)) as total_hours'),
                DB::raw('SUM(CASE WHEN COALESCE(a.hours_worked,0)>8 THEN COALESCE(a.hours_worked,0)-8 ELSE 0 END) as overtime_hours'),
                DB::raw('SUM(CASE WHEN COALESCE(a.hours_worked,0)<8 AND COALESCE(a.hours_worked,0)>0 THEN 1 ELSE 0 END) as short_days'),
            ])
            ->when($request->department_id, fn($q) => $q->where('s.department_id', $request->department_id))
            ->when($request->date_from,     fn($q) => $q->where('a.attendance_date', '>=', $request->date_from))
            ->when($request->date_to,       fn($q) => $q->where('a.attendance_date', '<=', $request->date_to))
            ->groupBy('s.staff_number','s.full_names','d.department_name','p.position_name')
            ->orderBy('s.full_names')->get();
        $dataRows = $rows->map(fn($r,$i) => [
            $i+1, $r->staff_number, $r->full_names, $r->department, $r->position,
            $r->days_recorded, number_format($r->total_hours,1), number_format($r->overtime_hours,1), $r->short_days,
        ])->values()->all();
        $export = GenericReportExport::build(
            'Overtime & Hours Analysis',
            ['#','Staff No','Full Names','Department','Position','Days Recorded','Total Hours','Overtime Hours','Short Days'],
            $dataRows,
            ['Date Range: '.($request->date_from ?? 'All').' to '.($request->date_to ?? 'All')]
        );
        return Excel::download($export, 'overtime-analysis.xlsx');
    }

    // =========================================================================
    // 14. LEAVE ADJUSTMENTS REGISTER
    // =========================================================================

    public function leaveAdjustments(): \Illuminate\View\View
    {
        $departments = Department::orderBy('department_name')->get(['id','department_name']);
        $leaveTypes  = HrLeaveType::orderBy('type_name')->get(['id','type_name']);
        return view('reports.hr.leave-adjustments', compact('departments','leaveTypes'));
    }

    public function getLeaveAdjustments(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $rows = DB::table('sys_hr_leave_adjustments as la')
                ->join('sys_staff as s', 's.id', '=', 'la.staff_id')
                ->leftJoin('sys_hr_leave_types as lt', 'lt.id', '=', 'la.leave_type_id')
                ->leftJoin('josya_users as u', 'u.id', '=', 'la.created_by')
                ->select([
                    's.staff_number', 's.full_names',
                    DB::raw("COALESCE(lt.type_name,'-') as leave_type"),
                    'la.adjustment_days', 'la.reason',
                    DB::raw("COALESCE(u.name,'-') as adjusted_by"),
                    'la.created_at as adjustment_date',
                ])
                ->when($request->department_id, fn($q) => $q->where('s.department_id', $request->department_id))
                ->when($request->leave_type_id, fn($q) => $q->where('la.leave_type_id', $request->leave_type_id))
                ->when($request->date_from,     fn($q) => $q->whereDate('la.created_at', '>=', $request->date_from))
                ->when($request->date_to,       fn($q) => $q->whereDate('la.created_at', '<=', $request->date_to))
                ->orderByDesc('la.created_at')->get();
            $data = $rows->values()->map(fn($r, $i) => [
                'no'              => $i + 1,
                'staff_number'    => $r->staff_number,
                'full_names'      => $r->full_names,
                'leave_type'      => $r->leave_type,
                'adjustment_days' => $r->adjustment_days,
                'reason'          => $r->reason ?? '-',
                'adjusted_by'     => $r->adjusted_by,
                'adjustment_date' => $r->adjustment_date ? substr($r->adjustment_date, 0, 10) : '-',
            ]);
            return response()->json(['success' => true, 'data' => $data, 'count' => $data->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('getLeaveAdjustments: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report']);
        }
    }

    public function exportLeaveAdjustments(Request $request)
    {
        $rows = DB::table('sys_hr_leave_adjustments as la')
            ->join('sys_staff as s', 's.id', '=', 'la.staff_id')
            ->leftJoin('sys_hr_leave_types as lt', 'lt.id', '=', 'la.leave_type_id')
            ->leftJoin('josya_users as u', 'u.id', '=', 'la.created_by')
            ->select(['s.staff_number','s.full_names',DB::raw("COALESCE(lt.type_name,'-') as leave_type"),'la.adjustment_days','la.reason',DB::raw("COALESCE(u.name,'-') as adjusted_by"),'la.created_at'])
            ->when($request->date_from, fn($q) => $q->whereDate('la.created_at','>=',$request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('la.created_at','<=',$request->date_to))
            ->orderByDesc('la.created_at')->get();
        $dataRows = $rows->values()->map(fn($r,$i) => [$i+1,$r->staff_number,$r->full_names,$r->leave_type,$r->adjustment_days,$r->reason??'-',$r->adjusted_by,substr($r->created_at??'',0,10)])->all();
        return Excel::download(GenericReportExport::build('Leave Adjustments Register',['#','Staff No','Name','Leave Type','Days','Reason','Adjusted By','Date'],$dataRows), 'leave-adjustments.xlsx');
    }

    // =========================================================================
    // 15. DISCIPLINE PROCESS AUDIT
    // =========================================================================

    public function disciplineAudit(): \Illuminate\View\View
    {
        $departments = Department::orderBy('department_name')->get(['id','department_name']);
        return view('reports.hr.discipline-audit', compact('departments'));
    }

    public function getDisciplineAudit(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $rows = DB::table('discipline_cases as dc')
                ->join('sys_staff as s', 's.id', '=', 'dc.staff_id')
                ->leftJoin('sys_hr_departments as d', 'd.id', '=', 's.department_id')
                ->leftJoin('discipline_hearings as dh', 'dh.case_id', '=', 'dc.id')
                ->leftJoin('discipline_outcomes as dout', 'dout.case_id', '=', 'dc.id')
                ->leftJoin('josya_users as u', 'u.id', '=', 'dc.created_by')
                ->select([
                    'dc.id as case_id', 'dc.case_number',
                    's.staff_number', 's.full_names',
                    DB::raw("COALESCE(d.department_name,'-') as department"),
                    'dc.created_at as opened_date',
                    'dh.hearing_date',
                    'dout.decision as outcome',
                    'dc.current_status_id',
                    'dc.closed_at as closed_date',
                    DB::raw("COALESCE(u.name,'-') as opened_by"),
                ])
                ->when($request->department_id, fn($q) => $q->where('s.department_id', $request->department_id))
                ->when($request->date_from,     fn($q) => $q->whereDate('dc.created_at', '>=', $request->date_from))
                ->when($request->date_to,       fn($q) => $q->whereDate('dc.created_at', '<=', $request->date_to))
                ->when($request->status,        fn($q) => $q->where('dc.current_status_id', $request->status))
                ->groupBy('dc.id','dc.case_number','s.staff_number','s.full_names','d.department_name','dc.created_at','dh.hearing_date','dout.decision','dc.current_status_id','dc.closed_at','u.name')
                ->orderByDesc('dc.created_at')->get();
            $data = $rows->values()->map(fn($r,$i) => [
                'no'          => $i+1,
                'case_number' => $r->case_number ?? 'DC-'.$r->case_id,
                'staff_number'=> $r->staff_number,
                'full_names'  => $r->full_names,
                'department'  => $r->department,
                'opened_date' => $r->opened_date ? substr($r->opened_date,0,10) : '-',
                'opened_by'   => $r->opened_by,
                'hearing_date'=> $r->hearing_date ? substr($r->hearing_date,0,10) : 'Pending',
                'outcome'     => $r->outcome ?? 'Pending',
                'closed_date' => $r->closed_date ? substr($r->closed_date,0,10) : 'Open',
            ]);
            return response()->json(['success'=>true,'data'=>$data,'count'=>$data->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('getDisciplineAudit: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Failed to load report']);
        }
    }

    public function exportDisciplineAudit(Request $request)
    {
        $rows = DB::table('discipline_cases as dc')
            ->join('sys_staff as s','s.id','=','dc.staff_id')
            ->leftJoin('sys_hr_departments as d','d.id','=','s.department_id')
            ->leftJoin('discipline_hearings as dh','dh.case_id','=','dc.id')
            ->leftJoin('discipline_outcomes as dout','dout.case_id','=','dc.id')
            ->leftJoin('josya_users as u','u.id','=','dc.created_by')
            ->select(['dc.case_number','s.staff_number','s.full_names',DB::raw("COALESCE(d.department_name,'-') as department"),'dc.created_at',DB::raw("COALESCE(u.name,'-') as opened_by"),'dh.hearing_date','dout.decision','dc.closed_at'])
            ->when($request->date_from,fn($q)=>$q->whereDate('dc.created_at','>=',$request->date_from))
            ->when($request->date_to,fn($q)=>$q->whereDate('dc.created_at','<=',$request->date_to))
            ->groupBy('dc.case_number','s.staff_number','s.full_names','d.department_name','dc.created_at','u.name','dh.hearing_date','dout.decision','dc.closed_at')
            ->orderByDesc('dc.created_at')->get();
        $dataRows = $rows->values()->map(fn($r,$i)=>[$i+1,$r->case_number??'-',$r->staff_number,$r->full_names,$r->department,substr($r->created_at??'',0,10),$r->opened_by,$r->hearing_date?substr($r->hearing_date,0,10):'Pending',$r->decision??'Pending',$r->closed_at?substr($r->closed_at,0,10):'Open'])->all();
        return Excel::download(GenericReportExport::build('Discipline Process Audit',['#','Case No','Staff No','Name','Department','Opened','Opened By','Hearing Date','Outcome','Closed Date'],$dataRows),'discipline-audit.xlsx');
    }

    // =========================================================================
    // 16. PAYROLL ELEMENTS REGISTER
    // =========================================================================

    public function payrollElements(): \Illuminate\View\View
    {
        $positions = Position::orderBy('position_name')->get(['id','position_name']);
        return view('reports.hr.payroll-elements', compact('positions'));
    }

    public function getPayrollElements(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $rows = DB::table('sys_hr_payroll_elements as pe')
                ->leftJoin('sys_hr_positions as p','p.id','=','pe.position_id')
                ->select([
                    'pe.id', 'pe.element_code', 'pe.element_name',
                    DB::raw("COALESCE(pe.element_type,'—') as element_type"),
                    DB::raw("COALESCE(p.position_name,'All Positions') as position"),
                    DB::raw("COALESCE(CAST(pe.amount AS TEXT), pe.formula, '—') as amount_formula"),
                    'pe.is_active',
                ])
                ->when($request->position_id, fn($q) => $q->where('pe.position_id', $request->position_id))
                ->when($request->type,        fn($q) => $q->where('pe.element_type', $request->type))
                ->when($request->date_from,   fn($q) => $q->whereDate('pe.created_at', '>=', $request->date_from))
                ->when($request->date_to,     fn($q) => $q->whereDate('pe.created_at', '<=', $request->date_to))
                ->orderBy('pe.element_type')->orderBy('pe.element_name')
                ->get();
            $data = $rows->values()->map(fn($r,$i) => [
                'no'             => $i+1,
                'element_code'   => $r->element_code ?? '-',
                'element_name'   => $r->element_name,
                'element_type'   => $r->element_type,
                'position'       => $r->position,
                'amount_formula' => $r->amount_formula,
                'is_active'      => $r->is_active ? 'Active' : 'Inactive',
            ]);
            return response()->json(['success'=>true,'data'=>$data,'count'=>$data->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('getPayrollElements: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Failed to load report']);
        }
    }

    public function exportPayrollElements(Request $request)
    {
        $rows = DB::table('sys_hr_payroll_elements as pe')
            ->leftJoin('sys_hr_positions as p','p.id','=','pe.position_id')
            ->select(['pe.element_code','pe.element_name',DB::raw("COALESCE(pe.element_type,'—') as element_type"),DB::raw("COALESCE(p.position_name,'All') as position"),DB::raw("COALESCE(CAST(pe.amount AS TEXT),pe.formula,'—') as amount_formula"),'pe.is_active'])
            ->when($request->position_id,fn($q)=>$q->where('pe.position_id',$request->position_id))
            ->orderBy('pe.element_type')->orderBy('pe.element_name')->get();
        $dataRows = $rows->values()->map(fn($r,$i)=>[$i+1,$r->element_code??'-',$r->element_name,$r->element_type,$r->position,$r->amount_formula,$r->is_active?'Active':'Inactive'])->all();
        return Excel::download(GenericReportExport::build('Payroll Elements Register',['#','Code','Element Name','Type','Position','Amount/Formula','Status'],$dataRows),'payroll-elements.xlsx');
    }

    // =========================================================================
    // 17. STAFF BANK ACCOUNT REGISTER
    // =========================================================================

    public function bankAccounts(): \Illuminate\View\View
    {
        $departments = Department::orderBy('department_name')->get(['id','department_name']);
        return view('reports.hr.bank-accounts', compact('departments'));
    }

    public function getBankAccounts(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $rows = DB::table('sys_staff as s')
                ->leftJoin('sys_hr_departments as d','d.id','=','s.department_id')
                ->select([
                    's.staff_number','s.full_names',
                    DB::raw("COALESCE(d.department_name,'-') as department"),
                    DB::raw("COALESCE(s.bank_name,'-') as bank_name"),
                    DB::raw("COALESCE(s.account_name,'-') as account_name"),
                    DB::raw("COALESCE(s.account_number,'-') as account_number"),
                    's.employment_status_id',
                ])
                ->when($request->department_id, fn($q) => $q->where('s.department_id', $request->department_id))
                ->when($request->date_from,     fn($q) => $q->whereDate('s.created_at', '>=', $request->date_from))
                ->when($request->date_to,       fn($q) => $q->whereDate('s.created_at', '<=', $request->date_to))
                ->where('s.employment_status_id', 1)
                ->orderBy('s.full_names')->get();
            $data = $rows->values()->map(fn($r,$i) => [
                'no'             => $i+1,
                'staff_number'   => $r->staff_number,
                'full_names'     => $r->full_names,
                'department'     => $r->department,
                'bank_name'      => $r->bank_name,
                'account_name'   => $r->account_name,
                'account_number' => $r->account_number,
            ]);
            return response()->json(['success'=>true,'data'=>$data,'count'=>$data->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('getBankAccounts: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Failed to load report']);
        }
    }

    public function exportBankAccounts(Request $request)
    {
        $rows = DB::table('sys_staff as s')
            ->leftJoin('sys_hr_departments as d','d.id','=','s.department_id')
            ->select(['s.staff_number','s.full_names',DB::raw("COALESCE(d.department_name,'-') as department"),DB::raw("COALESCE(s.bank_name,'-') as bank_name"),DB::raw("COALESCE(s.account_name,'-') as account_name"),DB::raw("COALESCE(s.account_number,'-') as account_number")])
            ->where('s.employment_status_id',1)->orderBy('s.full_names')->get();
        $dataRows = $rows->values()->map(fn($r,$i)=>[$i+1,$r->staff_number,$r->full_names,$r->department,$r->bank_name,$r->account_name,$r->account_number])->all();
        return Excel::download(GenericReportExport::build('Staff Bank Account Register',['#','Staff No','Full Names','Department','Bank','Account Name','Account Number'],$dataRows),'staff-bank-accounts.xlsx');
    }

    // =========================================================================
    // 18. PAYROLL PERIOD STATUS
    // =========================================================================

    public function payrollPeriods(): \Illuminate\View\View
    {
        return view('reports.hr.payroll-periods');
    }

    public function getPayrollPeriods(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $rows = DB::table('sys_hr_payroll_summary')
                ->select([
                    DB::raw("CONCAT(period_year, '-', LPAD(period_month::text, 2, '0')) as period"),
                    DB::raw('MIN(created_at) as period_start'),
                    DB::raw('MAX(updated_at) as period_end'),
                    DB::raw('COUNT(DISTINCT staff_id) as staff_count'),
                    DB::raw("'Processed' as status"),
                ])
                ->when($request->date_from, fn($q) => $q->whereRaw("CONCAT(period_year,'-',LPAD(period_month::text,2,'0')) >= ?", [$request->date_from]))
                ->when($request->date_to,   fn($q) => $q->whereRaw("CONCAT(period_year,'-',LPAD(period_month::text,2,'0')) <= ?", [$request->date_to]))
                ->groupBy('period_year','period_month')
                ->orderByDesc('period_year')->orderByDesc('period_month')
                ->get();
            $data = $rows->values()->map(fn($r,$i) => [
                'no'          => $i+1,
                'period'      => $r->period,
                'period_start'=> $r->period_start ? substr($r->period_start,0,10) : '-',
                'period_end'  => $r->period_end   ? substr($r->period_end,0,10)   : '-',
                'staff_count' => $r->staff_count,
                'status'      => $r->status,
            ]);
            return response()->json(['success'=>true,'data'=>$data,'count'=>$data->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('getPayrollPeriods: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Failed to load report']);
        }
    }

    public function exportPayrollPeriods(Request $request)
    {
        $rows = DB::table('sys_hr_payroll_summary')
            ->select([DB::raw("CONCAT(period_year,'-',LPAD(period_month::text,2,'0')) as period"),DB::raw('MIN(created_at) as period_start'),DB::raw('MAX(updated_at) as period_end'),DB::raw('COUNT(DISTINCT staff_id) as staff_count')])
            ->groupBy('period_year','period_month')->orderByDesc('period_year')->orderByDesc('period_month')->get();
        $dataRows = $rows->values()->map(fn($r,$i)=>[$i+1,$r->period,substr($r->period_start??'',0,10),substr($r->period_end??'',0,10),$r->staff_count,'Processed'])->all();
        return Excel::download(GenericReportExport::build('Payroll Period Status',['#','Period','Start','End','Staff Count','Status'],$dataRows),'payroll-periods.xlsx');
    }

    // =========================================================================
    // 12. DISCIPLINE CASE SUMMARY
    // =========================================================================

    public function disciplineSummary(): \Illuminate\View\View
    {
        $departments = Department::orderBy('department_name')->get(['id', 'department_name']);
        return view('reports.hr.discipline-summary', compact('departments'));
    }

    public function getDisciplineSummary(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $query = DisciplineCase::query()
                ->join('sys_staff', 'discipline_cases.staff_id', '=', 'sys_staff.id')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->select(
                    'discipline_cases.case_number',
                    'sys_staff.full_names',
                    'sys_set_departments.department_name',
                    'discipline_cases.severity_level',
                    'discipline_cases.current_step',
                    'discipline_cases.current_status_id',
                    'discipline_cases.date_time as opened_at',
                    'discipline_cases.closed_at'
                )
                ->when($request->date_from, fn($q) => $q->whereDate('discipline_cases.date_time', '>=', $request->date_from))
                ->when($request->date_to,   fn($q) => $q->whereDate('discipline_cases.date_time', '<=', $request->date_to))
                ->when($request->severity_level,   fn($q) => $q->where('discipline_cases.severity_level',    $request->severity_level))
                ->when($request->current_status_id, fn($q) => $q->where('discipline_cases.current_status_id', $request->current_status_id))
                ->when($request->department_id,    fn($q) => $q->where('sys_staff.department_id',             $request->department_id))
                ->orderBy('discipline_cases.date_time', 'desc');

            $records = $query->get();
            $data = $records->map(fn($r, $i) => [
                'no'          => $i + 1,
                'case_number' => $r->case_number ?? '-',
                'full_names'  => $r->full_names,
                'department'  => $r->department_name  ?? '-',
                'severity'    => $r->severity_level    ?? '-',
                'current_step'=> $r->current_step      ?? '-',
                'status'      => $r->current_status_id ?? '-',
                'opened'      => $r->opened_at ? Carbon::parse($r->opened_at)->format('d-m-Y') : '-',
                'closed'      => $r->closed_at ? Carbon::parse($r->closed_at)->format('d-m-Y') : '-',
            ]);

            return response()->json(['success' => true, 'data' => $data, 'count' => $records->count()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report disciplineSummary: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportDisciplineSummary(Request $request)
    {
        try {
            $records = DisciplineCase::query()
                ->join('sys_staff', 'discipline_cases.staff_id', '=', 'sys_staff.id')
                ->leftJoin('sys_set_departments', 'sys_staff.department_id', '=', 'sys_set_departments.id')
                ->select(
                    'discipline_cases.case_number',
                    'sys_staff.full_names',
                    'sys_set_departments.department_name',
                    'discipline_cases.severity_level',
                    'discipline_cases.current_step',
                    'discipline_cases.current_status_id',
                    'discipline_cases.date_time as opened_at',
                    'discipline_cases.closed_at'
                )
                ->when($request->date_from, fn($q) => $q->whereDate('discipline_cases.date_time', '>=', $request->date_from))
                ->when($request->date_to,   fn($q) => $q->whereDate('discipline_cases.date_time', '<=', $request->date_to))
                ->when($request->severity_level,    fn($q) => $q->where('discipline_cases.severity_level',    $request->severity_level))
                ->when($request->current_status_id, fn($q) => $q->where('discipline_cases.current_status_id', $request->current_status_id))
                ->when($request->department_id,     fn($q) => $q->where('sys_staff.department_id',            $request->department_id))
                ->orderBy('discipline_cases.date_time', 'desc')
                ->get();

            $rows = $records->map(fn($r, $i) => [
                $i + 1,
                $r->case_number ?? '-',
                $r->full_names,
                $r->department_name  ?? '-',
                $r->severity_level    ?? '-',
                $r->current_step      ?? '-',
                $r->current_status_id ?? '-',
                $r->opened_at ? Carbon::parse($r->opened_at)->format('d-m-Y') : '-',
                $r->closed_at ? Carbon::parse($r->closed_at)->format('d-m-Y') : '-',
            ])->values()->all();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Discipline Case Summary',
                    headings:  ['#', 'Case No', 'Staff Name', 'Department', 'Severity', 'Current Step', 'Status', 'Opened', 'Closed'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 14, 'C' => 28, 'D' => 20, 'E' => 12, 'F' => 14, 'G' => 10, 'H' => 13, 'I' => 13],
                    hasTotals: false,
                ),
                'discipline-summary-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Report exportDisciplineSummary: ' . $e->getMessage());
            return back()->with('error', 'Failed to export report.');
        }
    }
}
