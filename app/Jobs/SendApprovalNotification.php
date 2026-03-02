<?php

namespace App\Jobs;

use App\Models\Staff;
use App\Models\Contract;
use App\Models\LeaveRequest;
use App\Models\StaffDiscipline;
use App\Models\Communication;
use App\Models\EmployeeTermination;
use App\Models\CommunicationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendApprovalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $section;
    protected $itemId;
    protected $approvedById;

    public function __construct($section, $itemId, $approvedById)
    {
        $this->section = $section;
        $this->itemId = $itemId;
        $this->approvedById = $approvedById;
    }

    public function handle()
    {
        try {
            $templateType = $this->getTemplateType();
            if (!$templateType) {
                Log::info("No template type mapped for section: {$this->section}");
                return;
            }

            // Get the approved template for this type
            $template = CommunicationTemplate::where('template_type', $templateType)
                ->where('current_status_id', 5) // Approved templates only
                ->first();

            if (!$template) {
                Log::warning("No approved template found for type: {$templateType}");
                
                // Create a communication record to alert about missing template
                try {
                    Communication::create([
                        'subject' => "Missing Template: {$templateType}",
                        'message' => "Failed to send approval notification for {$this->section} (ID: {$this->itemId}). No approved communication template found for type '{$templateType}'. Please create and approve a template for this type in Communication Templates settings.",
                        'recipients' => json_encode(['system']),
                        'type' => 'email',
                        'recipient_type' => 'system',
                        'status' => '26', // Failed
                        'created_by' => $this->approvedById,
                        'send_status' => 'failed',
                        'sent_at' => now(),
                        'send_error' => "Missing approved template for type: {$templateType}",
                    ]);
                } catch (\Exception $ex) {
                    Log::error("Failed to create missing template notification: " . $ex->getMessage());
                }
                
                return;
            }

            // Get item details and staff
            $data = $this->getItemData();
            if (!$data || !$data['staff']) {
                Log::warning("No staff found for {$this->section} ID: {$this->itemId}");
                return;
            }

            $staff = $data['staff'];
            
            // Check if staff has email
            if (!$staff->email_address) {
                Log::warning("Staff {$staff->full_names} has no email address");
                return;
            }

            // Replace placeholders
            $subject = $this->replacePlaceholders($template->subject ?? '', $data);
            $body = $this->replacePlaceholders($template->body, $data);

            // Send based on communication method
            if ($template->communication_method === 'email') {
                $this->sendEmail($staff->email_address, $subject, $body, $template->attachment_path);
            } elseif ($template->communication_method === 'sms') {
                $this->sendSMS($staff->phone_number, $body);
            }

            // Create communication record for audit trail
            Communication::create([
                'subject' => $subject,
                'message' => $body,
                'recipients' => json_encode([$staff->email_address]),
                'type' => $template->communication_method,
                'recipient_type' => 'staff',
                'status' => '5',
                'attachment' => $template->attachment_path,
                'created_by' => $this->approvedById,
                'send_status' => 'sent',
                'sent_at' => now(),
                'send_error' => null,
            ]);

            Log::info("Approval notification sent for {$this->section} ID: {$this->itemId} to {$staff->email_address}");

        } catch (\Exception $e) {
            Log::error("Error sending approval notification for {$this->section} ID {$this->itemId}: " . $e->getMessage());
            
            // Create failed communication record
            try {
                Communication::create([
                    'subject' => $template->subject ?? 'Approval Notification',
                    'message' => $template->body ?? '',
                    'recipients' => json_encode([$staff->email_address ?? 'unknown']),
                    'type' => $template->communication_method ?? 'email',
                    'recipient_type' => 'staff',
                    'status' => '26',
                    'created_by' => $this->approvedById,
                    'send_status' => 'failed',
                    'sent_at' => now(),
                    'send_error' => $e->getMessage(),
                ]);
            } catch (\Exception $ex) {
                Log::error("Failed to create communication error record: " . $ex->getMessage());
            }
        }
    }

    private function getTemplateType()
    {
        $map = [
            'Staff Contracts' => 'contract',
            'Leave Application' => 'leave_application',
            'Employee Termination' => 'contract_termination',
            'Staff Discipline Tracker' => 'disciplinary_action',
        ];

        return $map[$this->section] ?? null;
    }

    private function getItemData()
    {
        switch ($this->section) {
            case 'Staff Contracts':
                $contract = Contract::with(['employee.department', 'position'])->find($this->itemId);
                if (!$contract) return null;

                return [
                    'staff' => $contract->employee,
                    'employee_name' => $contract->employee->full_names ?? '-',
                    'staff_number' => $contract->staff_number ?? '-',
                    'position' => $contract->position->position_name ?? '-',
                    'basic_salary' => number_format($contract->basic_salary, 2),
                    'contract_start' => $contract->date_start ? date('d M Y', strtotime($contract->date_start)) : '-',
                    'contract_end' => $contract->date_end ? date('d M Y', strtotime($contract->date_end)) : '-',
                    'contract_duration' => $this->calculateDuration($contract->date_start, $contract->date_end),
                    'date' => date('d M Y'),
                    'department' => $contract->employee->department->department_name ?? '-',
                ];

            case 'Leave Application':
                $leave = LeaveRequest::with(['employee.department', 'employee.position', 'leaveType'])->find($this->itemId);
                if (!$leave) return null;

                return [
                    'staff' => $leave->employee,
                    'employee_name' => $leave->employee->full_names ?? '-',
                    'staff_number' => $leave->employee->staff_number ?? '-',
                    'position' => $leave->employee->position->position_name ?? '-',
                    'leave_type' => $leave->leaveType->type_name ?? '-',
                    'start_date' => date('d M Y', strtotime($leave->start_date)),
                    'end_date' => date('d M Y', strtotime($leave->end_date)),
                    'days' => $leave->used_days ?? $leave->requested_days,
                    'days_balance' => $leave->days_balance ?? '-',
                    'entitled_days' => $leave->entitled_days ?? '-',
                    'date' => date('d M Y'),
                    'department' => $leave->employee->department->department_name ?? '-',
                ];

            case 'Employee Termination':
                $termination = EmployeeTermination::with(['staff.department', 'staff.position', 'contract'])->find($this->itemId);
                if (!$termination) return null;

                return [
                    'staff' => $termination->staff,
                    'employee_name' => $termination->staff->full_names ?? '-',
                    'staff_number' => $termination->contract->staff_number ?? '-',
                    'position' => $termination->staff->position->position_name ?? '-',
                    'severance_payment' => number_format($termination->severerance_payment, 2),
                    'years_served' => $termination->years_served ?? '-',
                    'termination_date' => $termination->termination_date ? date('d M Y', strtotime($termination->termination_date)) : '-',
                    'termination_reason' => $termination->reason ?? '-',
                    'last_working_day' => $termination->last_working_day ? date('d M Y', strtotime($termination->last_working_day)) : '-',
                    'date' => date('d M Y'),
                    'department' => $termination->staff->department->department_name ?? '-',
                ];

            case 'Staff Discipline Tracker':
                $discipline = StaffDiscipline::with(['staff.department', 'staff.position', 'incident', 'actionTaken', 'position'])->find($this->itemId);
                if (!$discipline) return null;

                return [
                    'staff' => $discipline->staff,
                    'employee_name' => $discipline->staff->full_names ?? '-',
                    'staff_number' => $discipline->staff->staff_number ?? '-',
                    'position' => $discipline->position->position_name ?? '-',
                    'incident' => $discipline->incident->name ?? '-',
                    'action_taken' => $discipline->actionTaken->name ?? '-',
                    'incident_date' => $discipline->incident_date_time ? date('d M Y', strtotime($discipline->incident_date_time)) : '-',
                    'description' => $discipline->description ?? '-',
                    'date' => date('d M Y'),
                    'department' => $discipline->staff->department->department_name ?? '-',
                ];

            default:
                return null;
        }
    }

    private function replacePlaceholders($text, $data)
    {
        if (!$text) return '';

        foreach ($data as $key => $value) {
            if ($key !== 'staff') { // Don't replace staff object
                $text = str_replace('{' . $key . '}', $value, $text);
            }
        }

        return $text;
    }

    private function calculateDuration($start, $end)
    {
        if (!$start || !$end) return '-';
        
        $startDate = new \DateTime($start);
        $endDate = new \DateTime($end);
        $interval = $startDate->diff($endDate);
        
        $years = $interval->y;
        $months = $interval->m;
        
        if ($years > 0) {
            return $years . ' year' . ($years > 1 ? 's' : '') . ($months > 0 ? ' and ' . $months . ' month' . ($months > 1 ? 's' : '') : '');
        } elseif ($months > 0) {
            return $months . ' month' . ($months > 1 ? 's' : '');
        } else {
            return $interval->days . ' day' . ($interval->days > 1 ? 's' : '');
        }
    }

    /**
     * Send email
     */
    private function sendEmail(string $email, string $subject, string $body, ?string $attachmentPath)
    {
        Mail::html($body, function ($message) use ($email, $subject, $attachmentPath) {
            $message->to($email)
                ->subject($subject);
                
            if ($attachmentPath && Storage::disk('public')->exists($attachmentPath)) {
                $message->attach(storage_path('app/public/' . $attachmentPath));
            }
        });
    }

    /**
     * Send SMS
     */
    private function sendSMS(string $phone, string $body)
    {
        // Implement your SMS gateway logic here
        Log::info("SMS to {$phone}: {$body}");
    }
}