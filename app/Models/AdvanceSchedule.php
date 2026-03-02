<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceSchedule extends Model
{
    protected $table = 'sys_pm_clients_advances_requisitions_89_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'payment_date',
        'principal_amount',
        'interest',
        'loan_balance',
        'current_status_id'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'principal_amount' => 'decimal:2',
        'interest' => 'decimal:2',
        'loan_balance' => 'decimal:2',
    ];

    /**
     * Get the advance that owns this schedule
     */
    public function advance()
    {
        return $this->belongsTo(ClientAdvance::class, 'parent_id');
    }

    /**
     * Get the status
     */
    public function status()
    {
        return $this->belongsTo(WorkflowStatus::class, 'current_status_id');
    }

    /**
     * Get the payment type name
     */
    public function getPaymentTypeNameAttribute()
    {
        $types = [
            1 => 'Principal Only',
            2 => 'Interest Only',
            3 => 'Principal + Interest'
        ];
        return $types[$this->payment_type_id] ?? 'Unknown';
    }

    /**
     * Check if this schedule is pending
     */
    public function isPending()
    {
        return $this->current_status_id == 1;
    }

    /**
     * Check if this schedule is cleared
     */
    public function isCleared()
    {
        return $this->current_status_id == 2;
    }
}
