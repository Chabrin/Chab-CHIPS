<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class EmployeeContract extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_employee_contracts';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'staff_id',
        'date_start',
        'date_end',
        'current_status_id',
        'position_id',
        'created_by',
        'basic_salary',
        'staff_number',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'date_start' => 'datetime',
        'date_end' => 'datetime',
        'basic_salary' => 'decimal:2',
    ];

    /**
     * Get the staff member
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * Get the position
     */
    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /**
     * Get the creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for active contracts
     */
    public function scopeActive($query)
    {
        // HR contract creation and updates use status 5 for Active.
        return $query->where('current_status_id', 5);
    }

    /**
     * Scope for approved contracts
     */
    public function scopeApproved($query)
    {
        return $query->where('current_status_id', 5);
    }
}
