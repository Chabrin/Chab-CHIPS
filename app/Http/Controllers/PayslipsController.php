<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Http\Request;
use App\Jobs\PayslipEmailJob;
use App\Models\PayrollElement;
use App\Models\PayrollSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayslipsController extends Controller
{
    /**
     * Display payslips index pagep
     */
    public function index()
    {
        return view('payroll.payslips');
    }

    /**
     * Search employees for autocomplete
     */
    public function searchEmployees(Request $request)
    {
        try {
            $search = $request->input('search');

            $employees = Staff::where(function ($query) use ($search) {
                $query->where('staff_number', 'LIKE', "%{$search}%")
                    ->orWhere('email_address', 'LIKE', "%{$search}%")
                    ->orWhere('national_id', 'LIKE', "%{$search}%")
                    ->orWhere('full_names', 'LIKE', "%{$search}%");
            })
                ->get([
                    'id as staff_id',
                    'staff_number',
                    'email_address',
                    'national_id',
                    'full_names as full_name'
                ]);

            return response()->json(['success' => true, 'employees' => $employees]);
        } catch (\Exception $e) {
            Log::error('Error searching employees: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Search failed'], 500);
        }
    }

    /**
     * View payslip
     */
    public function viewPayslip(Request $request)
    {
        try {
            $validated = $request->validate([
                'staff_id' => 'required|integer',
                'payroll_month' => 'required|date_format:Y-m',
            ]);

            $staffId = $validated['staff_id'];
            $month = $validated['payroll_month'];

            // Get payroll summary
            $payrollSummary = PayrollSummary::with(['staff', 'position', 'department'])
                ->where('staff_id', $staffId)
                ->whereYear('period', substr($month, 0, 4))
                ->whereMonth('period', substr($month, 5, 2))
                ->first();

            if (!$payrollSummary) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payslip found for the selected period'
                ]);
            }

            // Get payslip elements with details
            $elements = $this->getPayslipElements($payrollSummary->payslip_elements);

            // Generate HTML
            $html = view('payroll.partials.payslip-preview', [
                'payroll' => $payrollSummary,
                'elements' => $elements
            ])->render();

            return response()->json([
                'success' => true,
                'html' => $html,
                'employee_email' => $payrollSummary->staff->email_address ?? '',
                'employee_name' => $payrollSummary->staff->full_names ?? 'N/A',
                'payroll_month' => date('F Y', strtotime($payrollSummary->period))
            ]);
        } catch (\Exception $e) {
            Log::error('Error viewing payslip: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load payslip'], 500);
        }
    }

    /**
     * Download payslip as PDF
     */
    public function downloadPayslip($staffId, $month)
    {
        try {
            $payrollSummary = $this->getPayrollSummary($staffId, $month);

            if (!$payrollSummary) {
                abort(404, 'Payslip not found');
            }

            $pdf = $this->generatePayslipPdf($payrollSummary);
            $filename = 'payslip_' . $payrollSummary->staff_number_id . '_' . $month . '.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error downloading payslip: ' . $e->getMessage());
            abort(500, 'Failed to generate payslip');
        }
    }

    /**
     * Email payslip
     */
    public function emailPayslip(Request $request)
    {
        try {
            $validated = $request->validate([
                'staff_id' => 'required|integer',
                'payroll_month' => 'required|date_format:Y-m',
            ]);

            $staffId = $validated['staff_id'];
            $month = $validated['payroll_month'];

            $payrollSummary = $this->getPayrollSummary($staffId, $month);

            if (!$payrollSummary) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payslip not found'
                ]);
            }

            // Dispatch email job
            PayslipEmailJob::dispatch($payrollSummary->id);

            return response()->json([
                'success' => true,
                'message' => 'Payslip email has been queued and will be sent shortly'
            ]);
        } catch (\Exception $e) {
            Log::error('Error queuing payslip email: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to queue email'], 500);
        }
    }

    /**
     * Get payroll summary by staff and month
     */
    private function getPayrollSummary($staffId, $month)
    {
        return PayrollSummary::with(['staff', 'position', 'department'])
            ->where('staff_id', $staffId)
            ->whereYear('period', substr($month, 0, 4))
            ->whereMonth('period', substr($month, 5, 2))
            ->first();
    }

    /**
     * Generate PDF for payslip
     */
    private function generatePayslipPdf($payrollSummary)
    {
        $elements = $this->getPayslipElements($payrollSummary->payslip_elements);

        return Pdf::loadView('payroll.partials.payslip-pdf', [
            'payroll' => $payrollSummary,
            'elements' => $elements
        ]);
    }

    /**
     * Get payslip elements with details from PayrollElement
     */
    private function getPayslipElements($payslipElementsJson)
    {
        if (empty($payslipElementsJson)) {
            return collect();
        }

        $payslipElements = is_array($payslipElementsJson) ? $payslipElementsJson : json_decode($payslipElementsJson, true);

        if (empty($payslipElements)) {
            return collect();
        }

        $elementIds = array_column($payslipElements, 'element_id');

        // Get element details
        $elementDetails = PayrollElement::whereIn('id', $elementIds)
            ->get()
            ->keyBy('id');

        // Merge amount with element details
        $result = collect($payslipElements)->map(function ($item) use ($elementDetails) {
            $element = $elementDetails->get($item['element_id']);
            return [
                'element_id' => $item['element_id'],
                'element_name' => $element->element_name ?? 'Unknown',
                'element_type' => $element->element_type ?? '1',
                'payslip_position' => $element->payslip_position ?? 999,
                'amount' => $item['amount']
            ];
        })->sortBy('payslip_position');

        return $result;
    }
}
