<?php

namespace App\Notifications;

use App\Models\LeaseAgreement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class LeaseContractNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries = 3;

    protected LeaseAgreement $lease;
    protected string $rawToken;
    protected string $pdfPath;

    public function __construct(LeaseAgreement $lease, string $rawToken, string $pdfPath)
    {
        $this->lease = $lease;
        $this->rawToken = $rawToken;
        $this->pdfPath = $pdfPath;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $refNumber = $this->lease->ref_number ?? 'N/A';
        $propertyName = $this->lease->property->property_name ?? 'N/A';
        $unitCode = $this->lease->unit->unit_code ?? 'N/A';
        $dateStart = $this->lease->date_start ? $this->lease->date_start->format('d M Y') : 'N/A';
        $dateEnd = $this->lease->date_end ? $this->lease->date_end->format('d M Y') : 'N/A';

        $signingUrl = route('lease.contract.sign', ['token' => $this->rawToken]);

        $mail = (new MailMessage)
            ->subject("Lease Agreement Ready for Signing – {$refNumber}")
            ->greeting('Dear ' . ($notifiable->names ?? 'Tenant') . ',')
            ->line("Your lease agreement has been prepared and is ready for your digital signature.")
            ->line("**Lease Reference:** {$refNumber}")
            ->line("**Property:** {$propertyName} — Unit {$unitCode}")
            ->line("**Lease Period:** {$dateStart} to {$dateEnd}")
            ->line('Please review the attached lease agreement document and then click the button below to sign it digitally.')
            ->action('Review & Sign Lease Agreement', $signingUrl)
            ->line('By signing, you confirm that you have read and agree to the terms of the lease agreement.')
            ->line('If you have any questions, please contact your property manager.')
            ->salutation('Regards, Property Management Team');

        // Attach the unsigned PDF
        if (Storage::disk('public')->exists($this->pdfPath)) {
            $mail->attachData(
                Storage::disk('public')->get($this->pdfPath),
                "LeaseAgreement_{$refNumber}.pdf",
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'           => 'LeaseContractSent',
            'lease_id'       => $this->lease->id,
            'ref_number'     => $this->lease->ref_number,
            'property_name'  => $this->lease->property->property_name ?? 'N/A',
            'unit_code'      => $this->lease->unit->unit_code ?? 'N/A',
            'sent_at'        => now()->toDateTimeString(),
            'message'        => "Lease contract sent for signing: {$this->lease->ref_number}",
        ];
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
