<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class LeaveAdjustment extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_leave_adjustments';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'leave_request_id',
        'days_refunded',
        'reason',
        'requested_by',
        'requested_at',
        'current_status_id',
        'approved_by',
        'approved_at',
        'confirmed_by',
        'confirmed_at',
        'rejection_reason',
        'day_ids',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'day_ids' => 'array',
    ];

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'confirmed_by');
    }

}