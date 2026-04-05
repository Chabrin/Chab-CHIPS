<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class PayrollPeriod extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_payroll_periods';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'period',
        'current_status_id',
        'start_date',
        'end_date',
        'calendar_id',
        'period_month',
        'period_number',
        'created_by',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'period' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // Relationships
    public function calendar()
    {
        return $this->belongsTo(PayrollCalendar::class, 'calendar_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('current_status_id', 5);
    }

    public function scopeForCalendar($query, $calendarId)
    {
        return $query->where('calendar_id', $calendarId);
    }

    // Helper Methods
    public function getFormattedPeriodAttribute()
    {
        if ($this->start_date && $this->end_date) {
            return date('d M Y', strtotime($this->start_date)) . ' - ' . date('d M Y', strtotime($this->end_date));
        }
        return '-';
    }

    public function getDurationDaysAttribute()
    {
        if ($this->start_date && $this->end_date) {
            $start = new \DateTime($this->start_date);
            $end = new \DateTime($this->end_date);
            return $start->diff($end)->days + 1;
        }
        return 0;
    }
}
