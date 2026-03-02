<?php

namespace App\Notifications;

use App\Models\LeaseAgreement;
use App\Models\Notification as NotificationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaseExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $lease;
    protected $daysUntilExpiry;

    /**
     * Create a new notification instance.
     */
    public function __construct(LeaseAgreement $lease, $daysUntilExpiry)
    {
        $this->lease = $lease;
        $this->daysUntilExpiry = $daysUntilExpiry;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $leaseReference = $this->lease->lease_ref ?? 'N/A';
        $propertyName = $this->lease->property->property_name ?? 'N/A';
        $tenantName = $this->lease->tenant->full_name ?? $this->lease->tenant->tenant_name ?? 'N/A';
        
        return (new MailMessage)
            ->subject('Lease Expiring Soon - ' . $leaseReference)
            ->greeting('Hello ' . $notifiable->full_name . ',')
            ->line('This is a reminder that a lease agreement is expiring soon.')
            ->line('**Lease Reference:** ' . $leaseReference)
            ->line('**Property:** ' . $propertyName)
            ->line('**Tenant:** ' . $tenantName)
            ->line('**Expiry Date:** ' . \Carbon\Carbon::parse($this->lease->date_end)->format('d M Y'))
            ->line('**Days Until Expiry:** ' . $this->daysUntilExpiry . ' days')
            ->action('View Lease Details', url('/property/lease-agreement/' . encrypt($this->lease->id)))
            ->line('Please take necessary action to renew or terminate this lease agreement.');
    }

    /**
     * Get the array representation of the notification for database storage.
     */
    public function toArray($notifiable)
    {
        $leaseReference = $this->lease->lease_ref ?? 'N/A';
        $propertyName = $this->lease->property->property_name ?? 'N/A';
        $tenantName = $this->lease->tenant->full_name ?? $this->lease->tenant->tenant_name ?? 'N/A';

        return [
            'lease_id' => $this->lease->id,
            'lease_reference' => $leaseReference,
            'property_name' => $propertyName,
            'tenant_name' => $tenantName,
            'expiry_date' => $this->lease->date_end,
            'days_until_expiry' => $this->daysUntilExpiry,
        ];
    }

    /**
     * Store notification in custom notifications table
     */
    public function toDatabase($notifiable)
    {
        $data = $this->toArray($notifiable);
        $leaseReference = $data['lease_reference'];
        
        NotificationModel::create([
            'type' => 'LeaseExpiring',
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id,
            'data' => json_encode($data),
            'title' => 'Lease Expiring Soon',
            'message' => "Lease {$leaseReference} expires in {$this->daysUntilExpiry} days",
            'action_url' => url('/property/lease-agreement/' . encrypt($this->lease->id)),
        ]);

        return $data;
    }
}
