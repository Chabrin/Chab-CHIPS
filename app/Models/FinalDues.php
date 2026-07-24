<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalDues extends Model
{
    protected $table = 'hr_final_dues';

    protected $fillable = [
        'termination_id',
        'unpaid_salary',
        'leave_payout',
        'other_earnings',
        'deductions',
        'severance_payment',
        'total_amount',
        'notes',
        'computed_by',
        'computed_at',
    ];

    protected $casts = [
        'unpaid_salary' => 'decimal:2',
        'leave_payout' => 'decimal:2',
        'other_earnings' => 'decimal:2',
        'deductions' => 'decimal:2',
        'severance_payment' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'computed_at' => 'datetime',
    ];

    public $timestamps = false;

    public function termination(): BelongsTo
    {
        return $this->belongsTo(EmployeeTermination::class, 'termination_id');
    }

    public function computedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by');
    }
}
