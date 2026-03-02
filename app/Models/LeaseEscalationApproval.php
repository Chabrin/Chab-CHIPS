<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseEscalationApproval extends Model
{
    protected $table = 'cb_lease_escalation_approvals';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'escalation_id',
        'approval_step',
        'user_id',
        'action',
        'comments',
        'documents',
        'date_time',
        ''
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function escalation()
    {
        return $this->belongsTo(LeaseEscalation::class, 'escalation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getStepLabelAttribute()
    {
        return match ($this->approval_step) {
            1 => 'Field Officer',
            2 => 'Zone Manager',
            3 => 'Property Manager',
            default => 'Step ' . $this->approval_step,
        };
    }
}
