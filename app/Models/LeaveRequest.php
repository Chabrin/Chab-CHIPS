<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_leave_requests';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'staff_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'days',
        'current_status_id',
        'status_changed_by',
        'position_id',
        'entitled_days',
        'days_balance',
        'used_days',
        'created_by_id',
        'requested_days',
        'allowance_type_id',
        'amount',
        'handover_staff_id',
        'handover_notes',
    ];

    protected $casts = [
        'handover_staff_id' => 'integer',
        'date_time' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'days' => 'float',
        'requested_days' => 'float',
    ];

    public const STATUS_PENDING = 1;
    public const STATUS_PARTIAL = 24;
    public const STATUS_APPROVED = 5;
    public const STATUS_REJECTED = 41;

    public function scopePendingHod($query)
    {
        return $query->where('current_status_id', self::STATUS_PENDING);
    }

    public function scopePendingHr($query)
    {
        return $query->where('current_status_id', self::STATUS_PARTIAL);
    }

    public function scopeApproved($query)
    {
        return $query->where('current_status_id', self::STATUS_APPROVED);
    }

    public function employee()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'id');
    }

    public function handoverEmployee()
    {
        return $this->belongsTo(Staff::class, 'handover_staff_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    // Helper method to check if it's a partial day leave
    public function isPartialDay()
    {
        if (!$this->start_date || !$this->end_date) {
            return false;
        }
        
        $start = $this->start_date;
        $end = $this->end_date;
        
        // Check if same date
        $isSameDate = $start->format('Y-m-d') === $end->format('Y-m-d');
        
        // Check if times are not default (08:00 and 17:00)
        $hasCustomTimes = !($start->format('H:i') === '08:00' && $end->format('H:i') === '17:00');
        
        return $isSameDate && $hasCustomTimes;
    }
    
    // Get hours requested
    public function getHoursRequested()
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }
        
        // Calculate the difference in seconds
        $diffInSeconds = $this->end_date->timestamp - $this->start_date->timestamp;
        
        // Convert to hours (absolute value to handle any negative cases)
        $hours = abs($diffInSeconds / 3600);
        
        return round($hours, 2);
    }
    
    // Get display format for days/hours
    public function getDisplayDuration()
    {
        if ($this->isPartialDay()) {
            $hours = $this->getHoursRequested();
            return $hours . ' hrs';
        }
        
        $hasCustomTimes = !($this->start_date->format('H:i') === '08:00' && $this->end_date->format('H:i') === '17:00');
        
        if ($hasCustomTimes && $this->start_date->format('Y-m-d') !== $this->end_date->format('Y-m-d')) {
            // Multi-day with custom times
            $totalHours = $this->getHoursRequested();
            $fullDays = floor($totalHours / 8);
            $remainingHours = round($totalHours - ($fullDays * 8), 2);
            
            if ($remainingHours > 0) {
                return $fullDays . ' days (' . $remainingHours . ' hrs)';
            }
            return $fullDays . ' days';
        }
        
        return $this->days . ' days';
    }


public function getStatusLabelAttribute(): string
{
    return match ((int) $this->current_status_id) {
        1  => 'Pending HOD Approval',
        24 => 'Pending HR Approval',
        5  => 'Approved',
        41 => 'Rejected',
        // add any other statuses you have
        default => 'Unknown Status (' . $this->current_status_id . ')',
    };
}

}
