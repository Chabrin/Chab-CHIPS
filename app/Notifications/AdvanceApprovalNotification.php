<?php

namespace App\Notifications;

use App\Models\ClientAdvance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdvanceApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries = 3;

    protected ClientAdvance $advance;
    protected string $encryptedId;
    protected array $schedule;

    public function __construct(ClientAdvance $advance, string $encryptedId, array $schedule)
    {
        $this->advance     = $advance;
        $this->encryptedId = $encryptedId;
        $this->schedule    = $schedule;
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
            1 => 'Expense Advance',
            2 => 'Client Payment Advance',
            3 => 'Overpayment Advance',
        ];
        $typeName = $typeNames[$advance->advance_type_id] ?? 'Advance';

        $disbursement = $advance->disbursement_date ? $advance->disbursement_date->format('d M Y') : 'N/A';

        $approvalUrl = route('property.advances.approve', ['encryptedId' => $this->encryptedId]);

        $pdfContent = Pdf::loadView('property.advances.schedule-pdf', [
            'advance'  => $advance,
            'schedule' => $this->schedule,
        ])->output();

        $mail = (new MailMessage)
            ->subject("Advance Requisition Pending Your Approval – {$ref}")
            ->greeting("Dear {$name},")
            ->line("A new advance has been requisitioned under your account and is pending your approval.")
            ->line("**Reference:** {$ref}")
            ->line("**Type:** {$typeName}")
            ->line("**Property:** {$property}")
            ->line("**Principal Amount:** KSh " . number_format($advance->amount, 2))
            ->line("**Total Amount (inc. interest & charges):** KSh " . number_format($advance->total_amount, 2))
            ->line("**Disbursement Date:** {$disbursement}")
            ->line('Please review the attached repayment schedule and click the button below to approve this advance.')
            ->action('Approve This Advance', $approvalUrl)
            ->line('By clicking Approve, you authorise this advance to be processed. If you did not request this, please contact us immediately.')
            ->line('This approval link expires in 7 days.')
            ->salutation('Regards, Property Management Team');

        $mail->attachData($pdfContent, "AdvanceSchedule_{$ref}.pdf", ['mime' => 'application/pdf']);

        return $mail;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'       => 'AdvanceApprovalRequested',
            'advance_id' => $this->advance->id,
            'reference'  => $this->advance->reference_number,
            'client_id'  => $this->advance->client_id,
            'amount'     => $this->advance->total_amount,
            'sent_at'    => now()->toDateTimeString(),
            'message'    => "Advance approval requested: {$this->advance->reference_number}",
        ];
    }
}
