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
        'status',
        'created_by_id',
        'notes',
        'current_status_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'effective_date' => 'date',
        'escalation_value' => 'decimal:2',
        'previous_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
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

    public function isPendingZm()
    {
        return $this->status === 'pending_zm';
    }

    public function isPendingPm()
    {
        return $this->status === 'pending_pm';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'pending_zm' => 'Pending ZM Approval',
            'pending_pm' => 'Pending PM Approval',
            'approved'   => 'Approved',
            'rejected'   => 'Rejected',
            default      => ucfirst($this->status),
        };
    }

    public function getStatusBadgeAttribute()
    {
        return match ($this->status) {
            'pending_zm' => 'warning',
            'pending_pm' => 'info',
            'approved'   => 'success',
            'rejected'   => 'danger',
            default      => 'secondary',
        };
    }
}
