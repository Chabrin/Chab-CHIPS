<?php

namespace App\Notifications;

use App\Models\LeaseEscalation;
use App\Models\Notification as NotificationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaseEscalationDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected LeaseEscalation $escalation;
    protected string $notificationType; // 'upcoming' | 'applied' | 'overdue_applied'

    public function __construct(LeaseEscalation $escalation, string $notificationType = 'upcoming')
    {
        $this->escalation       = $escalation;
        $this->notificationType = $notificationType;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $esc      = $this->escalation;
        $ref      = $esc->reference_number ?? 'N/A';
        $property = $esc->property->property_name ?? 'N/A';
        $unit     = $esc->unit->unit_code ?? 'All Units';
        $effDate  = $esc->effective_date?->format('d M Y') ?? 'N/A';
        $value    = $esc->escalation_type === 'percentage'
            ? $esc->escalation_value . '%'
            : number_format($esc->escalation_value, 2);

        [$subject, $headline, $body] = match ($this->notificationType) {
            'upcoming' => [
                "Lease Escalation Due – {$ref}",
                'A lease escalation is approaching its effective date.',
                "Escalation **{$ref}** for **{$property} / {$unit}** is scheduled to be applied on **{$effDate}** ({$value}). " .
                "If you wish to stop this escalation, please cancel it before the effective date.",
            ],
            'applied' => [
                "Lease Escalation Applied – {$ref}",
                'A lease escalation has been automatically applied.',
                "Escalation **{$ref}** for **{$property} / {$unit}** was applied on **{$effDate}** ({$value}). " .
                "Lease invoiceable amounts have been updated.",
            ],
            'overdue_applied' => [
                "Lease Escalation Applied (Overdue) – {$ref}",
                'A lease escalation was applied past its effective date.',
                "Escalation **{$ref}** for **{$property} / {$unit}** had an effective date of **{$effDate}** ({$value}) " .
                "and has been applied now.",
            ],
            default => ['Lease Escalation Update', 'Lease escalation update.', ''],
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . ($notifiable->name ?? 'Team') . ',')
            ->line($headline)
            ->line($body)
            ->action('View Escalation', url('/property/escalations'))
            ->line('This is an automated message from the Property Management System.');
    }

    public function toArray($notifiable): array
    {
        $esc = $this->escalation;
        return [
            'escalation_id'    => $esc->id,
            'reference_number' => $esc->reference_number,
            'property_name'    => $esc->property->property_name ?? 'N/A',
            'unit_code'        => $esc->unit->unit_code ?? 'All Units',
            'effective_date'   => $esc->effective_date?->toDateString(),
            'escalation_type'  => $esc->escalation_type,
            'escalation_value' => $esc->escalation_value,
            'notification_type'=> $this->notificationType,
        ];
    }

    public function toDatabase($notifiable): array
    {
        $data = $this->toArray($notifiable);
        $esc  = $this->escalation;
        $ref  = $esc->reference_number ?? 'N/A';

        $title = match ($this->notificationType) {
            'upcoming'        => 'Lease Escalation Due Soon',
            'applied'         => 'Lease Escalation Applied',
            'overdue_applied' => 'Lease Escalation Applied (Overdue)',
            default           => 'Lease Escalation Update',
        };

        $message = match ($this->notificationType) {
            'upcoming'        => "Escalation {$ref} is due on {$esc->effective_date?->format('d M Y')} — cancel before effective date if needed.",
            'applied'         => "Escalation {$ref} has been automatically applied to the lease.",
            'overdue_applied' => "Escalation {$ref} was overdue and has now been applied.",
            default           => "Escalation {$ref} updated.",
        };

        NotificationModel::create([
            'type'            => 'LeaseEscalation',
            'notifiable_type' => get_class($notifiable),
            'notifiable_id'   => $notifiable->id,
            'data'            => json_encode($data),
            'title'           => $title,
            'message'         => $message,
            'action_url'      => url('/property/escalations'),
        ]);

        return $data;
    }
}
