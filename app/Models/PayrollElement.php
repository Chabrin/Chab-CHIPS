<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class PayrollElement extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_payroll_elements';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'element_name',
        'element_key',
        'account_id',
        'element_type',
        'current_status_id',
        'created_by_id',
        'formula_id',
        'amount',
        'staff_positions_id',
        'payslip_position',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'amount' => 'decimal:4',
        'current_status_id' => 'integer',
        'created_by_id' => 'integer',
        'staff_positions_id' => 'integer',
        'payslip_position' => 'integer',
    ];

    // Relationships
    public function account()
    {
        return $this->belongsTo(ChartAccount::class, 'account_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'staff_positions_id');
    }

    // Accessors
    public function getElementTypeNameAttribute()
    {
        return match ($this->element_type) {
            '1' => 'Deduction',
            '2' => 'Allowance',
            default => 'Unknown'
        };
    }

    public function getStatusNameAttribute()
    {
        return match ($this->current_status_id) {
            5 => 'Approved',
            default => 'Pending'
        };
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2);
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('current_status_id', 5);
    }

    public function scopeDeductions($query)
    {
        return $query->where('element_type', '1');
    }

    public function scopeAllowances($query)
    {
        return $query->where('element_type', '2');
    }
}
