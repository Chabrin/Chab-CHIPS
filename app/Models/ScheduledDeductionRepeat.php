<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One month's instalment of a scheduled deduction, as payroll took it (written when the payroll run is approved). */
class ScheduledDeductionRepeat extends Model
{
    protected $table = 'sys_hr_scheduled_deduction_repeats';

    protected $fillable = ['deduction_id', 'period_id', 'deduction_date', 'amount', 'balance'];

    protected $casts = [
        'deduction_date' => 'date',
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function deduction()
    {
        return $this->belongsTo(ScheduledDeduction::class, 'deduction_id');
    }
}
