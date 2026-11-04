<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Http\Request;
use App\Jobs\PayslipEmailJob;
use App\Models\PayrollElement;
use App\Models\PayrollSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Auth;
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
        if (!Auth::user()?->isAbleTo('read_all_payslips')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to search employee payslips.',
            ], 403);
        }
        try {
            $search = trim((string) $request->input('search', ''));
            if (mb_strlen($search) < 2) {
                return response()->json(['success' => true, 'employees' => []]);
            }

            $employees = Staff::where(function ($query) use ($search) {
                $query->where('staff_number', 'ILIKE', "%{$search}%")
                    ->orWhere('full_names', 'ILIKE', "%{$search}%")
                    ->orWhere('email_address', 'ILIKE', "%{$search}%")
                    ->orWhere('national_id', 'ILIKE', "%{$search}%");
            })
                ->orderBy('full_names')
                ->limit(20)
                ->get([
                    'id as staff_id',
                    'staff_number',
                    'full_names as full_name',
                    'national_id',
                    'email_address',
                    'mobile_number'
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
            if (!$this->canViewPayslipForStaff((int) $staffId)) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to view this payslip.'], 403);
            }

            // Get payroll summary
            $payrollSummary = PayrollSummary::with(['staff.bank', 'staff.position', 'staff.department', 'position', 'department', 'payrollPeriod'])
                ->where('current_status_id', 5)
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
            $elements = $this->getPayslipElements($payrollSummary->payslip_elements, $payrollSummary);

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

    /** Payslip for the payroll review page, including runs not yet approved. */
    public function reviewPayslip($summaryId)
    {
        abort_unless(Auth::user()?->isAbleTo('read_payroll_processing'), 403);

        $payrollSummary = PayrollSummary::with(['staff.bank', 'staff.position', 'staff.department', 'position', 'department', 'payrollPeriod'])
            ->findOrFail($summaryId);

        return response()->json([
            'html' => view('payroll.partials.payslip-preview', [
                'payroll' => $payrollSummary,
                'elements' => $this->getPayslipElements($payrollSummary->payslip_elements, $payrollSummary),
            ])->render(),
        ]);
    }

    /**
     * Download payslip as PDF
     */
    public function downloadPayslip($staffId, $month)
    {
        // Returned directly (not abort()) — the try/catch below catches
        // \Exception broadly, which would swallow an aborted HttpException
        // and mask this 403 behind a generic 500 "Failed to generate payslip".
        if (!$this->canViewPayslipForStaff((int) $staffId)) {
            return response('You do not have permission to download payslips.', 403);
        }

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
            if (!Auth::user()->isAbleTo('read_all_payslips')) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to email payslips.'], 403);
            }

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
        return PayrollSummary::with(['staff.bank', 'staff.position', 'staff.department', 'position', 'department', 'payrollPeriod'])
            ->where('current_status_id', 5)
            ->where('staff_id', $staffId)
            ->whereYear('period', substr($month, 0, 4))
            ->whereMonth('period', substr($month, 5, 2))
            ->first();
    }

    /** HR may view any payslip; a linked employee may view only their own. */
    private function canViewPayslipForStaff(int $staffId): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($user->isAbleTo('read_all_payslips')) {
            return true;
        }

        return (int) ($user->linkedStaff()?->id ?? 0) === $staffId;
    }

    /**
     * Generate PDF for payslip
     */
    private function generatePayslipPdf($payrollSummary)
    {
        $elements = $this->getPayslipElements($payrollSummary->payslip_elements, $payrollSummary);

        return Pdf::loadView('payroll.partials.payslip-pdf', [
            'payroll' => $payrollSummary,
            'elements' => $elements
        ]);
    }

    /**
     * Get payslip elements with details from PayrollElement.
     * Supports two JSON formats:
     *   - Old: [{'element_id': X, 'amount': Y}, ...]  — requires DB lookup
     *   - New: [{'element_id': X, 'element_name': '...', 'element_type': '1', 'amount': Y, ...}]
     * Falls back to building from PayrollSummary columns when array is empty.
     */
    private function getPayslipElements($payslipElementsJson, $payrollSummary = null)
    {
        $payslipElements = [];

        if (!empty($payslipElementsJson)) {
            $payslipElements = is_array($payslipElementsJson) ? $payslipElementsJson : json_decode($payslipElementsJson, true);
        }

        // Older processed runs persisted the contract's NET SALARY target as a
        // type-1 deduction. Remove that invalid row and repair the displayed
        // totals in memory, so preview/PDF/email agree without rewriting an
        // already-approved payroll record.
        $netSalaryElementId = (int) PayrollElement::where('element_key', 'net_salary')->value('id');
        $legacyNetSalaryDeduction = 0.0;
        if (is_array($payslipElements) && $netSalaryElementId > 0) {
            $payslipElements = array_values(array_filter($payslipElements, function ($item) use ($netSalaryElementId, &$legacyNetSalaryDeduction) {
                $isNetSalaryDeduction = (string) ($item['element_type'] ?? '1') === '1'
                    && (int) ($item['element_id'] ?? 0) === $netSalaryElementId;
                if ($isNetSalaryDeduction) {
                    $legacyNetSalaryDeduction += (float) ($item['amount'] ?? 0);
                }
                return !$isNetSalaryDeduction;
            }));
        }
        if ($legacyNetSalaryDeduction > 0 && $payrollSummary) {
            $payrollSummary->total_deductions = max(0, (float) $payrollSummary->total_deductions - $legacyNetSalaryDeduction);
            $payrollSummary->net_pay = (float) $payrollSummary->net_pay + $legacyNetSalaryDeduction;
        }

        if (!empty($payslipElements)) {
            // Check if new inline format (has element_name stored) or old format (ID-only)
            $needsLookup = collect($payslipElements)->contains(fn($item) => !isset($item['element_name']));

            $elementDetails = collect();
            if ($needsLookup) {
                $elementIds = array_filter(array_column($payslipElements, 'element_id'));
                if (!empty($elementIds)) {
                    $elementDetails = PayrollElement::whereIn('id', $elementIds)->get()->keyBy('id');
                }
            }

            $result = collect($payslipElements)->map(function ($item) use ($elementDetails) {
                // New format: element details already stored inline
                if (isset($item['element_name'])) {
                    return [
                        'element_id'       => $item['element_id'],
                        'element_name'     => $item['element_name'],
                        'element_type'     => $item['element_type'] ?? '1',
                        'payslip_position' => $item['payslip_position'] ?? 999,
                        'amount'           => $item['amount'],
                    ];
                }
                // Old format: look up by element_id
                $element = $elementDetails->get($item['element_id']);
                return [
                    'element_id'       => $item['element_id'],
                    'element_name'     => $element?->element_name ?? 'Unknown',
                    'element_type'     => $element?->element_type ?? '1',
                    'payslip_position' => $element?->payslip_position ?? 999,
                    'amount'           => $item['amount'],
                ];
            })->sortBy('payslip_position');

            if ($result->isNotEmpty()) {
                return $result;
            }
        }

        // Fallback: reconstruct from PayrollSummary individual columns (handles older processed payrolls)
        if ($payrollSummary) {
            return $this->buildElementsFromSummaryColumns($payrollSummary);
        }

        return collect();
    }

    /**
     * Build element list from stored PayrollSummary columns when payslip_elements is empty.
     */
    private function buildElementsFromSummaryColumns($payroll): \Illuminate\Support\Collection
    {
        $byKey = PayrollElement::whereIn('element_key', ['nssf', 'nhif', 'housing_levy', 'payee', 'helb_loans'])
            ->get()->keyBy('element_key');

        // Only statutory items that the Blade doesn't check directly.
        // Akiba/Social Welfare are read directly from columns in the Blade to avoid duplicates.
        $map = [
            'nssf'         => ['col' => 'nssf',        'name' => 'NSSF',         'pos' => 10],
            'nhif'         => ['col' => 'nhif',         'name' => 'SHA',         'pos' => 20],
            'housing_levy' => ['col' => 'housing_levy', 'name' => 'Housing Levy', 'pos' => 30],
            'payee'        => ['col' => 'tax_payable',  'name' => 'PAYE',         'pos' => 40],
            'helb_loans'   => ['col' => 'helb_loans',   'name' => 'HELB Loan',   'pos' => 50],
        ];

        $result = collect();
        foreach ($map as $key => $info) {
            $amount = (float) ($payroll->{$info['col']} ?? 0);
            if ($amount > 0) {
                $el = $byKey->get($key);
                $result->push([
                    'element_id'       => $el?->id ?? 0,
                    'element_name'     => $el?->element_name ?? $info['name'],
                    'element_type'     => '1',
                    'payslip_position' => $el?->payslip_position ?? $info['pos'],
                    'amount'           => $amount,
                ]);
            }
        }
        return $result->sortBy('payslip_position')->values();
    }
}
