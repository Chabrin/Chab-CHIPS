<?php
namespace App\Jobs;

use App\Models\Staff;
use App\Models\Position;
use App\Models\Communication;
use App\Models\CommunicationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class DispatchCommunication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $templateId;

    public function __construct($templateId)
    {
        $this->templateId = $templateId;
    }

    public function handle()
    {
        $template = CommunicationTemplate::find($this->templateId);
        
        // Only process approved general communication templates
        if (!$template || $template->template_type !== 'general' || $template->current_status_id != 5) {
            Log::warning("Template {$this->templateId} not found, not general type, or not approved");
            return;
        }

        $config = $template->general_config;
        
        if (!$config) {
            Log::error("No general_config found for template {$this->templateId}");
            return;
        }

        // Check if scheduled time has arrived
        $scheduledDateTime = null;
        if (isset($config['scheduled_date']) && isset($config['scheduled_time'])) {
            $scheduledDateTime = \Carbon\Carbon::parse($config['scheduled_date'] . ' ' . $config['scheduled_time']);
            
            if (now()->lt($scheduledDateTime)) {
                Log::info("Template {$this->templateId} scheduled for {$scheduledDateTime}, too early to send");
                return;
            }
        }

        try {
            // Get recipients based on configuration
            $recipients = $this->getRecipients($config);
            
            if (empty($recipients)) {
                Log::warning("No recipients found for template {$this->templateId}");
                return;
            }

            $successCount = 0;
            $failureCount = 0;
            $errors = [];

            foreach ($recipients as $recipient) {
                try {
                    // Replace placeholders with actual data
                    $subject = $this->replacePlaceholders($template->subject, $recipient);
                    $body = $this->replacePlaceholders($template->body, $recipient);
                    
                    if ($template->communication_method === 'email') {
                        $this->sendEmail($recipient, $subject, $body, $template->attachment_path);
                        $successCount++;
                    } elseif ($template->communication_method === 'sms') {
                        $this->sendSMS($recipient, $body);
                        $successCount++;
                    }
                    
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[] = "Failed to send to {$recipient['email']}: " . $e->getMessage();
                    Log::error("Failed to send communication to {$recipient['email']}: " . $e->getMessage());
                }
            }

            // Update template status after sending
            $template->current_status_id = 19; // Mark as sent/completed
            $template->save();

            // Create Communication record for audit trail
            Communication::create([
                'subject' => $template->subject,
                'message' => $template->body,
                'recipients' => json_encode(array_column($recipients, 'email')),
                'type' => $template->communication_method,
                'recipient_type' => $config['recipient_type'] ?? 'general',
                'status' => '5',
                'attachment' => $template->attachment_path,
                'created_by' => $template->created_by_id,
                'send_status' => $failureCount > 0 ? 'partial' : 'sent',
                'sent_at' => now(),
                'send_error' => empty($errors) ? null : implode('; ', $errors),
            ]);

            Log::info("Template {$this->templateId} dispatched: {$successCount} successful, {$failureCount} failed");
            
        } catch (\Exception $e) {
            Log::error('Failed to dispatch communication for template ' . $this->templateId . ': ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get recipients based on general_config
     */
    private function getRecipients(array $config): array
    {
        $recipients = [];
        $recipientType = $config['recipient_type'] ?? null;

        if ($recipientType === 'staff' && isset($config['staff_ids'])) {
            // Get specific staff members
            $staff = Staff::whereIn('id', $config['staff_ids'])
                ->where('current_status_id', 5) // Active only
                ->with('position', 'department')
                ->get();
                
            foreach ($staff as $employee) {
                $recipients[] = [
                    'email' => $employee->email_address,
                    'phone' => $employee->phone_number,
                    'employee_name' => $employee->full_names,
                    'staff_number' => $employee->staff_number,
                    'position' => $employee->position->position_name ?? '',
                    'department' => $employee->department->department_name ?? '',
                ];
            }
            
        } elseif ($recipientType === 'position' && isset($config['position_ids'])) {
            // Get staff by positions/departments
            $staff = Staff::whereIn('position_id', $config['position_ids'])
                ->where('current_status_id', 5) // Active only
                ->with('position', 'department')
                ->get();
                
            foreach ($staff as $employee) {
                $recipients[] = [
                    'email' => $employee->email_address,
                    'phone' => $employee->phone_number,
                    'employee_name' => $employee->full_names,
                    'staff_number' => $employee->staff_number,
                    'position' => $employee->position->position_name ?? '',
                    'department' => $employee->department->department_name ?? '',
                ];
            }
            
        } elseif ($recipientType === 'everyone') {
            // Get all active staff
            $staff = Staff::where('current_status_id', 5)
                ->with('position', 'department')
                ->get();
                
            foreach ($staff as $employee) {
                $recipients[] = [
                    'email' => $employee->email_address,
                    'phone' => $employee->phone_number,
                    'employee_name' => $employee->full_names,
                    'staff_number' => $employee->staff_number,
                    'position' => $employee->position->position_name ?? '',
                    'department' => $employee->department->department_name ?? '',
                ];
            }
        }

        return $recipients;
    }

    /**
     * Replace placeholders in template with actual data
     */
    private function replacePlaceholders(string $text, array $recipient): string
    {
        $placeholders = [
            '{employee_name}' => $recipient['employee_name'] ?? '',
            '{staff_number}' => $recipient['staff_number'] ?? '',
            '{position}' => $recipient['position'] ?? '',
            '{department}' => $recipient['department'] ?? '',
            '{date}' => now()->format('d M Y'),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $text);
    }

    /**
     * Send email
     */
    private function sendEmail(array $recipient, string $subject, string $body, ?string $attachmentPath)
    {
        Mail::html($body, function ($message) use ($recipient, $subject, $attachmentPath) {
            $message->to($recipient['email'])
                ->subject($subject);
                
            if ($attachmentPath && Storage::disk('public')->exists($attachmentPath)) {
                $message->attach(storage_path('app/public/' . $attachmentPath));
            }
        });
    }

    /**
     * Send SMS
     */
    private function sendSMS(array $recipient, string $body)
    {
        // Implement your SMS gateway logic here
        // Example: AfricasTalking, Twilio, etc.
        Log::info("SMS to {$recipient['phone']}: {$body}");
    }
}