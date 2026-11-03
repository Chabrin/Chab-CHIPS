<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\PayrollElement;
use App\Models\PayrollSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class PayslipEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payrollSummaryId;

    /**
     * Create a new job instance.
     */
    public function __construct($payrollSummaryId)
    {
        $this->payrollSummaryId = $payrollSummaryId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get payroll summary with relationships
            $payrollSummary = PayrollSummary::with(['staff', 'position', 'department'])
                ->find($this->payrollSummaryId);

            if (!$payrollSummary || !$payrollSummary->staff) {
                Log::error('Payroll summary or staff not found for ID: ' . $this->payrollSummaryId);
                return;
            }

            $staff = $payrollSummary->staff;
            $email = $staff->email_address;

            if (!$email) {
                Log::error('No email address found for staff ID: ' . $staff->id);
                return;
            }

            // Get payslip elements
            $elements = $this->getPayslipElements($payrollSummary->payslip_elements, $payrollSummary);

            // Check if encryption is enabled
            $encryptPayslips = (int)(DB::table('cb_system_configs')
                ->where('setting', 'encrypt_emailed_payslips')
                ->value('value') ?? 1);
            Log::info('Encrypt emailed payslips setting: ' . $encryptPayslips);

            // Determine if encryption should be applied
            $password = ($encryptPayslips == 1 && !empty($staff->national_id)) ? $staff->national_id : null;

            Log::info('Generating payslip PDF for staff ID: ' . $staff->id . ' with encryption: ' . ($password ? 'Yes' : 'No'));
            // Generate PDF with optional encryption
            $pdf = Pdf::loadView('payroll.partials.payslip-pdf', [
                'payroll' => $payrollSummary,
                'elements' => $elements
            ]);

            // Set encryption if password is provided (must be done before output)
            if ($password) {
                try {
                    // Apply strongest PDF protection possible in Dompdf
                    $pdf->setEncryption($password, $password, ['print']);

                    Log::info('PDF encryption applied successfully');
                } catch (\Exception $e) {
                    Log::error('Failed to apply PDF encryption: ' . $e->getMessage());
                }
            }

            $pdfOutput = $pdf->output();
            $filename = 'payslip_' . $payrollSummary->staff_number_id . '_' . date('Y-m', strtotime($payrollSummary->period)) . '.pdf';
            $month = date('F Y', strtotime($payrollSummary->period));
            $fullName = $staff->full_names;

            // Prepare email body
            $emailBody = "Dear {$fullName},<br><br>Please find attached your payslip for {$month}.";

            if ($password) {
                $emailBody .= "<br><br><strong>Note:</strong> The attached payslip is password protected. Please use your National ID number as the password to open the document.";
            }

            $emailBody .= "<br><br>Regards,<br>HR Department";

            // Send email
            Mail::send([], [], function ($message) use ($email, $fullName, $month, $pdfOutput, $filename, $emailBody) {
                $message->to($email)
                    ->subject('Payslip for ' . $month)
                    ->html($emailBody)
                    ->attachData($pdfOutput, $filename, [
                        'mime' => 'application/pdf',
                    ]);
            });

            Log::info('Payslip email sent successfully to: ' . $email);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error sending payslip email: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get payslip elements with details from PayrollElement
     */
    private function getPayslipElements($payslipElementsJson, PayrollSummary $payrollSummary)
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

        // Legacy contracts stored NET SALARY as a type-1 deduction. Exclude it
        // and correct the values shown on this payslip without changing the
        // approved payroll summary in the database.
        $legacyNetSalaryDeduction = 0.0;
        $payslipElements = array_values(array_filter($payslipElements, function ($item) use ($elementDetails, &$legacyNetSalaryDeduction) {
            $element = $elementDetails->get($item['element_id'] ?? null);
            $isNetSalaryDeduction = (string) ($item['element_type'] ?? $element?->element_type ?? '1') === '1'
                && strtolower((string) $element?->element_key) === 'net_salary';
            if ($isNetSalaryDeduction) {
                $legacyNetSalaryDeduction += (float) ($item['amount'] ?? 0);
            }
            return !$isNetSalaryDeduction;
        }));
        if ($legacyNetSalaryDeduction > 0) {
            $payrollSummary->total_deductions = max(0, (float) $payrollSummary->total_deductions - $legacyNetSalaryDeduction);
            $payrollSummary->net_pay = (float) $payrollSummary->net_pay + $legacyNetSalaryDeduction;
        }

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
