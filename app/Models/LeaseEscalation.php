<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseEscalation extends Model
{
    protected $table = 'cb_lease_escalations';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'client_id',
        'reference_number',
        'property_id',
        'unit_id',
        'lease_agreement_id',
        'scope',
        'escalation_type',
        'escalation_value',
        'previous_amount',
        'new_amount',
        'effective_date',
        'notification_sent_at',
        'applied_at',
        'status',
        'created_by_id',
        'notes',
        'current_status_id',
    ];

    protected $casts = [
        'date_time'            => 'datetime',
        'effective_date'       => 'date',
        'notification_sent_at' => 'datetime',
        'applied_at'           => 'datetime',
        'escalation_value'     => 'decimal:2',
        'previous_amount'      => 'decimal:2',
        'new_amount'           => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function leaseAgreement()
    {
        return $this->belongsTo(LeaseAgreement::class, 'lease_agreement_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function approvals()
    {
        return $this->hasMany(LeaseEscalationApproval::class, 'escalation_id')->orderBy('approval_step');
    }

    // ── Helpers ────────────────────────────────────────────────────

    /*
     * Status constants for clarity.
     * pending_zm                → awaiting ZM approval
     * pending_pm                → awaiting PM approval
     * approved_pending_effective → fully approved, waiting for effective date
     * applied                   → escalation applied to the lease
     * cancelled                 → cancelled by an authorised role before effective date
     * rejected                  → rejected during the approval workflow
     */

    public function isPendingZm(): bool
    {
        return $this->status === 'pending_zm';
    }

    public function isPendingPm(): bool
    {
        return $this->status === 'pending_pm';
    }

    public function isApprovedPendingEffective(): bool
    {
        return $this->status === 'approved_pending_effective';
    }

    public function isApplied(): bool
    {
        return $this->status === 'applied';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /** True if the escalation still needs action (in the approval workflow). */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending_zm', 'pending_pm']);
    }

    /**
     * Determine the notification lead-time based on the gap between creation
     * date and effective date:
     *   gap > 30 days  → notify 30 days before effective date
     *   gap ≤ 30 days  → notify 10 days before effective date
     */
    public function notificationLeadDays(): int
    {
        if (!$this->effective_date || !$this->date_time) {
            return 30;
        }

        $gap = $this->date_time->diffInDays($this->effective_date);

        return $gap > 30 ? 30 : 10;
    }

    /**
     * Returns true when today falls within the notification window and
     * a notification has not been sent yet.
     */
    public function isDueForNotification(): bool
    {
        if (!$this->isApprovedPendingEffective() || $this->notification_sent_at) {
            return false;
        }

        $lead  = $this->notificationLeadDays();
        $today = now()->startOfDay();

        return $today->greaterThanOrEqualTo($this->effective_date->subDays($lead)->startOfDay());
    }

    /** True when the effective date has passed and escalation has not been applied yet. */
    public function isOverdueForApplication(): bool
    {
        return $this->isApprovedPendingEffective()
            && $this->effective_date
            && now()->startOfDay()->greaterThanOrEqualTo($this->effective_date->startOfDay());
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending_zm'                 => 'Pending ZM Approval',
            'pending_pm'                 => 'Pending PM Approval',
            'approved_pending_effective' => 'Approved – Pending Effective Date',
            'applied'                    => 'Applied',
            'cancelled'                  => 'Cancelled',
            'rejected'                   => 'Rejected',
            default                      => ucfirst($this->status),
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending_zm'                 => 'warning',
            'pending_pm'                 => 'info',
            'approved_pending_effective' => 'primary',
            'applied'                    => 'success',
            'cancelled'                  => 'secondary',
            'rejected'                   => 'danger',
            default                      => 'secondary',
        };
    }
}
