<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ProratedDeductionRepeat extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_employee_amortized_deductions_252_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'deduction_date',
        'amount',
        'interest_amount',
        'current_status_id',
        'amount_balance',
        'total_amount',
        'period_id',
    ];

    protected $casts = [
        'deduction_date' => 'datetime',
        'amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'amount_balance' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // Relationships
    public function parent()
    {
        return $this->belongsTo(ProratedDeduction::class, 'parent_id');
    }
}
