<?php

namespace App\Services\Leave;

/** Recipient-specific wording shared by stored alerts and older applicant alerts on display. */
class LeaveNotificationMessage
{
    public static function format(string $type, string $name, string $dates, string $next, bool $applicant, bool $relief): ?array
    {
        if ($applicant) {
            $acknowledgement = str_replace('the relief notes and files', 'your relief notes and files', $next);
            return match ($type) {
                'LeaveApplicationReceived' => ['Your leave application was submitted', "Your leave application ({$dates}) has been submitted. {$next}"],
                'LeaveSupervisorApproved', 'LeaveReadyForHrApproval' => ['Your leave is pending HR approval', "Your supervisor has approved your leave application ({$dates}). It is now pending HR final approval; it is not yet fully approved."],
                'LeaveApproved' => ['Your leave application is approved', "Your leave application ({$dates}) has been fully approved. No further supervisor approval is needed."],
                'LeaveRejected' => ['Your leave application was rejected', "Your leave application ({$dates}) has been rejected. Your request is closed and your relief arrangements are no longer active."],
                'LeaveHandoverAcknowledged' => ['Your relief arrangements were acknowledged', "{$acknowledgement} Your leave dates: {$dates}."],
                default => null,
            };
        }

        if ($relief) {
            return match ($type) {
                'LeaveHandoverAssigned' => ['Relief arrangements assigned to you', "You have been selected to cover {$name}'s duties ({$dates}). {$next}"],
                'LeaveSupervisorApproved', 'LeaveReadyForHrApproval' => ['Your relief assignment is pending HR approval', "Supervisor approval is complete for {$name}'s leave ({$dates}). Your relief assignment is still pending HR final approval; the leave is not yet fully approved."],
                'LeaveApproved' => ['Your relief assignment is confirmed', "Your relief assignment for {$name}'s leave ({$dates}) is confirmed. Their leave has been fully approved. Review the relief notes and files for the work assigned to you."],
                'LeaveRejected' => ['Your relief assignment is no longer active', "Your relief assignment for {$name}'s leave ({$dates}) is no longer active because their leave application was rejected."],
                default => null,
            };
        }

        return match ($type) {
            'LeaveApplicationSubmitted' => ['Leave application for your review', "{$name} has submitted a leave application ({$dates}). {$next}"],
            'LeaveSupervisorApproved', 'LeaveReadyForHrApproval' => ['Leave application pending HR approval', "{$name}'s leave application ({$dates}) has supervisor approval. {$next}"],
            'LeaveApproved' => ['Leave application fully approved', "{$name}'s leave application ({$dates}) has been fully approved. No further supervisor approval is needed."],
            'LeaveRejected' => ['Leave application rejected', "{$name}'s leave application ({$dates}) has been rejected. The request is closed and the relief arrangements are no longer active."],
            'LeaveHandoverAcknowledged' => ['Relief arrangements acknowledged', "The relief arrangements for {$name}'s leave ({$dates}) have been acknowledged. {$next}"],
            default => null,
        };
    }

    /** Update old wording for display only; do not rewrite or re-send saved notifications. */
    public static function legacyDisplay(string $type, string $message, bool $applicant, bool $relief): ?array
    {
        if (!preg_match('/^(.+?) applied for leave \(([^)]+)\)\. (.*)$/s', $message, $parts)) return null;
        return self::format($type, $parts[1], $parts[2], $parts[3], $applicant, $relief);
    }
}
