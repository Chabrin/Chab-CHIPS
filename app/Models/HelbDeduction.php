<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class HelbDeduction extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_helb_deductions';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'created_by_id',
        'staff_id',
        'loan_balance',
        'monthly_deduction',
        'start_deduction_date',
        'current_status_id',
        'cleared',
        'suspended',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'start_deduction_date' => 'datetime',
        'loan_balance' => 'decimal:2',
        'monthly_deduction' => 'decimal:2',
        'cleared' => 'integer',
        'suspended' => 'integer',
    ];

    // Relationships
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function repeats()
    {
        return $this->hasMany(HelbDeductionRepeat::class, 'parent_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('current_status_id', 5);
    }

    public function scopeNotCleared($query)
    {
        return $query->where('cleared', 0);
    }

    public function scopeNotSuspended($query)
    {
        return $query->where('suspended', 0);
    }
}
