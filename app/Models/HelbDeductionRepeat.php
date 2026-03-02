<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class HelbDeductionRepeat extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_helb_deductions_250_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'deduction_date',
        'amount',
        'loan_balance',
        'current_status_id',
    ];

    protected $casts = [
        'deduction_date' => 'datetime',
        'amount' => 'decimal:2',
        'loan_balance' => 'decimal:2',
    ];

    // Relationships
    public function parent()
    {
        return $this->belongsTo(HelbDeduction::class, 'parent_id');
    }
}
