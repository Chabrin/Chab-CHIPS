<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Staff;
use Illuminate\Bus\Queueable;
use App\Models\DisciplineCase;
use App\Models\Communication;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use App\Models\CommunicationTemplate;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\DisciplineNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendDisciplineNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $case;
    protected $stepNumber;
    protected $notificationType;

    /**
     * Create a new job instance.
     *
     * @param DisciplineCase $case
     * @param int $stepNumber
     * @param string $notificationType
     */
    public function __construct($case, $stepNumber, $notificationType)
    {
        $this->case = $case;
        $this->stepNumber = $stepNumber;
        $this->notificationType = $notificationType;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            // Determine recipients based on step and notification type
            $recipients = $this->getRecipients();
            
            if (empty($recipients)) {
                Log::info("No recipients for discipline notification: {$this->notificationType}");
                return;
            }

            // Get communication template
            $template = $this->getTemplate();
            
            if (!$template) {
                // Fallback to default messages if no template exists
                $this->sendDefaultNotifications($recipients);
                return;
            }

            // Send notifications to each recipient
            foreach ($recipients as $recipient) {
                $this->sendNotification($recipient, $template);
            }

            Log::info("Discipline notifications sent for case {$this->case->case_number}, step {$this->stepNumber}");

        } catch (\Exception $e) {
            Log::error("Error sending discipline notification for case {$this->case->case_number}: " . $e->getMessage());
        }
    }

    /**
     * Get recipients based on notification type and step
     */
    private function getRecipients()
    {
        $recipients = [];

        switch ($this->notificationType) {
            case 'incident_reported':
                // Notify HR team and manager
                $recipients = $this->getHRTeam();
                // Add employee's manager if exists
                if ($this->case->staff->manager_id) {
                    $manager = User::find($this->case->staff->manager_id);
                    if ($manager && $manager->email) {
                        $recipients[] = [
                            'user_id' => $manager->id,
                            'email' => $manager->email,
                            'name' => $manager->name,
                        ];
                    }
                }
                break;

            case 'case_registered':
                // Notify the person who reported and the investigator (if assigned)
                $reporter = User::find($this->case->createdBy->id);
                if ($reporter && $reporter->email) {
                    $recipients[] = [
                        'user_id' => $reporter->id,
                        'email' => $reporter->email,
                        'name' => $reporter->name,
                    ];
                }
                break;

            case 'investigator_assigned':
                // Notify the assigned investigator
                $investigator = $this->case->investigators()->latest()->first();
                if ($investigator && $investigator->investigator && $investigator->investigator->email_address) {
                    $recipients[] = [
                        'user_id' => $investigator->investigator->user_id ?? null,
                        'email' => $investigator->investigator->email_address,
                        'name' => $investigator->investigator->full_names,
                    ];
                }
                break;

            case 'show_cause_issued':
                // Notify the employee (subject of discipline)
                if ($this->case->staff && $this->case->staff->email_address) {
                    $recipients[] = [
                        'user_id' => $this->case->staff->user_id ?? null,
                        'email' => $this->case->staff->email_address,
                        'name' => $this->case->staff->full_names,
                    ];
                }
                break;

            case 'employee_response_received':
                // Notify HR and investigator
                $recipients = array_merge($recipients, $this->getHRTeam());
                $investigator = $this->case->investigators()->latest()->first();
                if ($investigator && $investigator->investigator && $investigator->investigator->email_address) {
                    $recipients[] = [
                        'user_id' => $investigator->investigator->user_id ?? null,
                        'email' => $investigator->investigator->email_address,
                        'name' => $investigator->investigator->full_names,
                    ];
                }
                break;

            case 'hearing_scheduled':
                // Notify employee, panel members, and chairperson
                if ($this->case->staff && $this->case->staff->email_address) {
                    $recipients[] = [
                        'user_id' => $this->case->staff->user_id ?? null,
                        'email' => $this->case->staff->email_address,
                        'name' => $this->case->staff->full_names,
                    ];
                }

                $hearing = $this->case->hearing;
                if ($hearing) {
                    // Chairperson
                    if ($hearing->chairperson) {
                        $recipients[] = [
                            'user_id' => $hearing->chairperson->id,
                            'email' => $hearing->chairperson->email,
                            'name' => $hearing->chairperson->name,
                        ];
                    }

                    // Panel members
                    if ($hearing->panel_members) {
                        $panelIds = json_decode($hearing->panel_members, true);
                        if (is_array($panelIds)) {
                            foreach ($panelIds as $userId) {
                                $user = User::find($userId);
                                if ($user && $user->email) {
                                    $recipients[] = [
                                        'user_id' => $user->id,
                                        'email' => $user->email,
                                        'name' => $user->name,
                                    ];
                                }
                            }
                        }
                    }
                }
                break;

            case 'outcome_decided':
                // Notify employee
                if ($this->case->staff && $this->case->staff->email_address) {
                    $recipients[] = [
                        'user_id' => $this->case->staff->user_id ?? null,
                        'email' => $this->case->staff->email_address,
                        'name' => $this->case->staff->full_names,
                    ];
                }
                break;

            case 'training_assigned':
                // Notify employee and training coordinator
                if ($this->case->staff && $this->case->staff->email_address) {
                    $recipients[] = [
                        'user_id' => $this->case->staff->user_id ?? null,
                        'email' => $this->case->staff->email_address,
                        'name' => $this->case->staff->full_names,
                    ];
                }
                break;

            case 'case_closed':
                // Notify employee and HR
                if ($this->case->staff && $this->case->staff->email_address) {
                    $recipients[] = [
                        'user_id' => $this->case->staff->user_id ?? null,
                        'email' => $this->case->staff->email_address,
                        'name' => $this->case->staff->full_names,
                    ];
                }
                $recipients = array_merge($recipients, $this->getHRTeam());
                break;
        }

        return $recipients;
    }

    /**
     * Get HR team members
     */
    private function getHRTeam()
    {
        // Get users with HR permissions (you can customize this query)
        $hrUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'HR Manager')
                  ->orWhere('name', 'HR Admin')
                  ->orWhere('name', 'Super Admin');
        })
        ->where('email', '!=', null)
        ->get(['id', 'email', 'name'])
        ->map(function ($user) {
            return [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ];
        })
        ->toArray();

        return $hrUsers;
    }

    /**
     * Get communication template for this notification type
     */
    private function getTemplate()
    {
        $templateMap = [
            'incident_reported' => 'discipline_incident_reported',
            'case_registered' => 'discipline_case_registered',
            'investigator_assigned' => 'discipline_investigator_assigned',
            'show_cause_issued' => 'discipline_show_cause',
            'employee_response_received' => 'discipline_response_received',
            'hearing_scheduled' => 'discipline_hearing_scheduled',
            'outcome_decided' => 'discipline_outcome',
            'training_assigned' => 'discipline_training_assigned',
            'case_closed' => 'discipline_case_closed',
        ];

        $templateType = $templateMap[$this->notificationType] ?? null;

        if (!$templateType) {
            return null;
        }

        return CommunicationTemplate::where('template_type', $templateType)
            ->where('current_status_id', 5) // Approved templates only
            ->first();
    }

    /**
     * Send notification to a recipient
     */
    private function sendNotification($recipient, $template)
    {
        try {
            // Prepare data for placeholder replacement
            $data = $this->prepareNotificationData();

            // Replace placeholders in template
            $subject = $this->replacePlaceholders($template->subject, $data);
            $body = $this->replacePlaceholders($template->body, $data);

            // Create communication record
            $communication = Communication::create([
                'type' => $template->communication_method ?? 'Email',
                'subject' => $subject,
                'body' => $body,
                'recipients' => json_encode([$recipient['email']]),
                'attachment' => $template->attachment_path,
                'status' => 1, // Pending
                'created_by' => $this->case->created_by,
                'created_at' => now(),
            ]);

            // Create discipline notification record for tracking
            DisciplineNotification::create([
                'case_id' => $this->case->id,
                'notification_type' => $this->notificationType,
                'recipient_user_id' => $recipient['user_id'],
                'recipient_email' => $recipient['email'],
                'subject' => $subject,
                'message' => $body,
                'sent_via' => $template->communication_method ?? 'Email',
                'sent_at' => now(),
            ]);

            // Dispatch the actual email/sms sending
            DispatchCommunication::dispatch($communication->id);

            Log::info("Discipline notification sent to {$recipient['email']} for case {$this->case->case_number}");

        } catch (\Exception $e) {
            Log::error("Error sending individual notification to {$recipient['email']}: " . $e->getMessage());
        }
    }

    /**
     * Send default notifications without templates
     */
    private function sendDefaultNotifications($recipients)
    {
        foreach ($recipients as $recipient) {
            try {
                $data = $this->prepareNotificationData();
                
                // Generate default subject and body
                $subject = $this->getDefaultSubject();
                $body = $this->getDefaultBody($data);

                // Create communication record
                $communication = Communication::create([
                    'type' => 'Email',
                    'subject' => $subject,
                    'body' => $body,
                    'recipients' => json_encode([$recipient['email']]),
                    'status' => 1, // Pending
                    'created_by' => $this->case->created_by,
                    'created_at' => now(),
                ]);

                // Create discipline notification record
                DisciplineNotification::create([
                    'case_id' => $this->case->id,
                    'notification_type' => $this->notificationType,
                    'recipient_user_id' => $recipient['user_id'],
                    'recipient_email' => $recipient['email'],
                    'subject' => $subject,
                    'message' => $body,
                    'sent_via' => 'Email',
                    'sent_at' => now(),
                ]);

                // Dispatch
                DispatchCommunication::dispatch($communication->id);

            } catch (\Exception $e) {
                Log::error("Error sending default notification: " . $e->getMessage());
            }
        }
    }

    /**
     * Prepare notification data with case details
     */
    private function prepareNotificationData()
    {
        $case = $this->case;

        // Base data - always available
        $data = [
            'case_number' => $case->case_number,
            'employee_name' => $case->staff->full_names ?? '-',
            'staff_number' => $case->staff->staff_number ?? '-',
            'position' => $case->position->position_name ?? '-',
            'department' => $case->staff->department->department_name ?? '-',
            'severity_level' => $case->severity_level,
            'current_step' => $this->getStepName($case->current_step),
            'date' => now()->format('d M Y'),
            'time' => now()->format('H:i A'),
        ];

        // Incident Report data (Step 1)
        $incident = $case->incidentReports->first();
        if ($incident) {
            $data['incident_date'] = $incident->incident_date_time ? date('d M Y', strtotime($incident->incident_date_time)) : '-';
            $data['incident_description'] = $incident->incident_description ?? '-';
            $data['location'] = $incident->location ?? '-';
        } else {
            $data['incident_date'] = '-';
            $data['incident_description'] = '-';
            $data['location'] = '-';
        }

        // Show Cause Notice data (Step 7)
        $showCause = $case->showCauseNotice;
        if ($showCause) {
            $data['notice_number'] = $showCause->notice_number ?? '-';
            $data['allegations'] = $showCause->allegations ?? '-';
            $data['response_deadline'] = $showCause->response_deadline ? date('d M Y', strtotime($showCause->response_deadline)) : '-';
            $data['potential_consequences'] = $showCause->potential_consequences ?? '-';
        } else {
            $data['notice_number'] = '-';
            $data['allegations'] = '-';
            $data['response_deadline'] = '-';
            $data['potential_consequences'] = '-';
        }

        // Hearing data (Step 9)
        $hearing = $case->hearings->first();
        if ($hearing) {
            $data['hearing_date'] = $hearing->hearing_date ? date('d M Y', strtotime($hearing->hearing_date)) : '-';
            $data['hearing_time'] = $hearing->hearing_date ? date('H:i A', strtotime($hearing->hearing_date)) : '-';
            $data['hearing_location'] = $hearing->hearing_location ?? '-';
            $data['chairperson'] = $hearing->chairperson->name ?? '-';
            $data['hearing_type'] = $hearing->hearing_type ?? '-';
        } else {
            $data['hearing_date'] = '-';
            $data['hearing_time'] = '-';
            $data['hearing_location'] = '-';
            $data['chairperson'] = '-';
            $data['hearing_type'] = '-';
        }

        // Outcome data (Step 11)
        $outcome = $case->outcome;
        if ($outcome) {
            $data['decision'] = $outcome->decision ?? '-';
            $data['findings'] = $outcome->findings ?? '-';
            $data['sanctions'] = $outcome->sanctions ?? '-';
            $data['suspension_days'] = $outcome->suspension_days ?? '0';
            $data['appeal_deadline'] = $outcome->appeal_deadline ? date('d M Y', strtotime($outcome->appeal_deadline)) : '-';
        } else {
            $data['decision'] = '-';
            $data['findings'] = '-';
            $data['sanctions'] = '-';
            $data['suspension_days'] = '-';
            $data['appeal_deadline'] = '-';
        }

        // Training Assignment data (Step 12)
        $training = $case->trainingAssignments->first();
        if ($training) {
            $data['training_course'] = $training->training_course ?? '-';
            $data['training_provider'] = $training->training_provider ?? '-';
            $data['scheduled_date'] = $training->scheduled_date ? date('d M Y', strtotime($training->scheduled_date)) : '-';
            $data['completion_deadline'] = $training->completion_deadline ? date('d M Y', strtotime($training->completion_deadline)) : '-';
        } else {
            $data['training_course'] = '-';
            $data['training_provider'] = '-';
            $data['scheduled_date'] = '-';
            $data['completion_deadline'] = '-';
        }

        // Case Closure data (Step 14)
        $closure = $case->closure;
        if ($closure) {
            $data['closure_reason'] = $closure->closure_reason ?? '-';
            $data['closure_date'] = $closure->closure_date ? date('d M Y', strtotime($closure->closure_date)) : date('d M Y');
            $data['lessons_learned'] = $closure->lessons_learned ?? '-';
            $data['final_notes'] = $closure->final_notes ?? '-';
        } else {
            $data['closure_reason'] = '-';
            $data['closure_date'] = '-';
            $data['lessons_learned'] = '-';
            $data['final_notes'] = '-';
        }

        return $data;
    }

    /**
     * Replace placeholders in text
     */
    private function replacePlaceholders($text, $data)
    {
        if (!$text) return '';

        foreach ($data as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }

        return $text;
    }

    /**
     * Get default subject when no template exists
     */
    private function getDefaultSubject()
    {
        $subjects = [
            'incident_reported' => 'Discipline Incident Reported - Case #' . $this->case->case_number,
            'case_registered' => 'Discipline Case Registered - Case #' . $this->case->case_number,
            'investigator_assigned' => 'You Have Been Assigned as Investigator - Case #' . $this->case->case_number,
            'show_cause_issued' => 'Show Cause Notice Issued - Case #' . $this->case->case_number,
            'employee_response_received' => 'Employee Response Received - Case #' . $this->case->case_number,
            'hearing_scheduled' => 'Disciplinary Hearing Scheduled - Case #' . $this->case->case_number,
            'outcome_decided' => 'Disciplinary Case Outcome - Case #' . $this->case->case_number,
            'training_assigned' => 'Remedial Training Assigned - Case #' . $this->case->case_number,
            'case_closed' => 'Discipline Case Closed - Case #' . $this->case->case_number,
        ];

        return $subjects[$this->notificationType] ?? 'Discipline Case Update - Case #' . $this->case->case_number;
    }

    /**
     * Get default body when no template exists
     */
    private function getDefaultBody($data)
    {
        $body = "Dear {$data['employee_name']},\n\n";
        $body .= "This is to notify you regarding Discipline Case #{$data['case_number']}.\n\n";
        $body .= "Current Step: {$data['current_step']}\n";
        $body .= "Severity Level: {$data['severity_level']}\n";
        $body .= "Date: {$data['date']}\n\n";
        $body .= "Please log in to the system for more details.\n\n";
        $body .= "Best regards,\n";
        $body .= "Human Resources Department";

        return $body;
    }

    /**
     * Get step name from number
     */
    private function getStepName($stepNumber)
    {
        $steps = [
            1 => 'Create Incident Report',
            2 => 'Register Case & Notify HR',
            3 => 'Assign Investigator',
            4 => 'Upload Evidence',
            5 => 'Add Witness Statements',
            6 => 'Record Investigation Notes',
            7 => 'Issue Show Cause Notice',
            8 => 'Employee Submits Response',
            9 => 'Schedule Hearing',
            10 => 'Record Hearing Minutes',
            11 => 'Save Final Outcome',
            12 => 'Assign Training (Optional)',
            13 => 'Update Employee Disciplinary History',
            14 => 'Close Case',
        ];

        return $steps[$stepNumber] ?? 'Unknown Step';
    }
}