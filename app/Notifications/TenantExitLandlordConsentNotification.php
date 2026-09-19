<?php

namespace App\Notifications;

use App\Models\TenantExitLandlordConsent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Asks a landlord to approve or reject having a departing tenant's refund
 * deducted from their next payout — a signed, no-login link (mirrors
 * AdvanceApprovalNotification's pattern for advance approvals).
 */
class TenantExitLandlordConsentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries = 3;

    protected TenantExitLandlordConsent $consent;
    protected string $encryptedId;

    /**
     * @param TenantExitLandlordConsent $consent
     * @param string $encryptedId
     */
    public function __construct(TenantExitLandlordConsent $consent, string $encryptedId) {
        $this->consent = $consent;
        $this->encryptedId = $encryptedId;
    }

    /**
     * Deliver this notification by email only — landlords have no in-app account.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the emailed consent request, with a signed link to the review page.
     */
    public function toMail($notifiable): MailMessage
    {
        $consent  = $this->consent;
        $exit     = $consent->exit;
        $property = $exit?->property?->property_name ?? 'N/A';
        $unit     = $exit?->unit?->unit_name ?? 'N/A';
        $name     = $consent->landlord->names ?? 'Landlord';
        $tenant   = $consent->tenant->names ?? 'N/A';

        $reviewUrl = URL::temporarySignedRoute(
            'property.exit-consents.show',
            now()->addDays(7),
            ['encryptedId' => $this->encryptedId]
        );

        return (new MailMessage)
            ->subject("Tenant Refund Requires Your Approval — {$property}")
            ->greeting("Dear {$name},")
            ->line("A tenant has vacated one of your units, and the exit settlement includes a refund that would be deducted from your next payout.")
            ->line("**Property:** {$property}")
            ->line("**Unit:** {$unit}")
            ->line("**Tenant:** {$tenant}")
            ->line("**Refund Amount:** KSh " . number_format($consent->refund_amount, 2))
            ->line('Please review the details and approve or reject this deduction.')
            ->action('Review This Refund', $reviewUrl)
            ->line('This link expires in 7 days. The exit settlement cannot be finalised until you respond.')
            ->salutation('Regards, Property Management Team');
    }

    /**
     * Record this request in the in-app notifications table for audit purposes.
     */
    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'TenantExitLandlordConsentRequested',
            'consent_id' => $this->consent->id,
            'exit_id' => $this->consent->exit_id,
            'amount' => $this->consent->refund_amount,
            'sent_at' => now()->toDateTimeString(),
            'message' => "Tenant exit refund consent requested for exit #{$this->consent->exit_id}",
        ];
    }
}
