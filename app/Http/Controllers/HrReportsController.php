<?php

namespace App\Http\Controllers;

use Error;
use App\Models\Staff;
use Illuminate\Http\Request;
use App\Models\PayrollSummary;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StatutoryDeductionsExport;

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
}
