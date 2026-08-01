<?php

namespace App\Notifications;

use App\Models\ClientAdvance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdvanceScheduleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries = 3;

    protected ClientAdvance $advance;
    protected array $schedule;

    public function __construct(ClientAdvance $advance, array $schedule)
    {
        $this->advance = $advance;
        $this->schedule = $schedule;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $advance  = $this->advance;
        $ref      = $advance->reference_number ?? 'N/A';
        $client   = $advance->client;
        $name     = $client->names ?? 'Landlord';
        $property = $advance->property->property_name ?? 'N/A';

        $typeNames = [
            2 => 'Client Payment Advance',
            3 => 'Overpayment Advance',
        ];
        $typeName = $typeNames[$advance->advance_type_id] ?? 'Advance';

        $disbursement = $advance->disbursement_date ? $advance->disbursement_date->format('d M Y') : 'N/A';

        $pdfContent = Pdf::loadView('property.advances.schedule-pdf', [
            'advance'  => $advance,
            'schedule' => $this->schedule,
        ])->output();

        $mail = (new MailMessage)
            ->subject("Advance Repayment Schedule – {$ref}")
            ->greeting("Dear {$name},")
            ->line("Please find attached the repayment schedule for your advance requisition.")
            ->line("**Reference:** {$ref}")
            ->line("**Type:** {$typeName}")
            ->line("**Property:** {$property}")
            ->line("**Principal Amount:** KSh " . number_format($advance->amount, 2))
            ->line("**Total Amount:** KSh " . number_format($advance->total_amount, 2))
            ->line("**Disbursement Date:** {$disbursement}")
            ->line('The attached PDF contains the full payment schedule showing amounts due on each date.')
            ->line('Please review and contact us if you have any questions.')
            ->salutation('Regards, Property Management Team');

        $mail->attachData($pdfContent, "AdvanceSchedule_{$ref}.pdf", ['mime' => 'application/pdf']);

        return $mail;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'       => 'AdvanceScheduleSent',
            'advance_id' => $this->advance->id,
            'reference'  => $this->advance->reference_number,
            'client_id'  => $this->advance->client_id,
            'sent_at'    => now()->toDateTimeString(),
            'message'    => "Advance repayment schedule sent: {$this->advance->reference_number}",
        ];
    }
}
