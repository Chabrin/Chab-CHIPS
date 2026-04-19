<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceRescheduleHistory extends Model
{
    protected $table = 'cb_advance_reschedule_history';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'original_advance_id',
        'from_advance_id',
        'to_advance_id',
        'amount_paid_at_reschedule',
        'outstanding_balance_at_reschedule',
        'narration',
        'rescheduled_by',
        'rescheduled_at',
    ];

    protected $casts = [
        'rescheduled_at'                    => 'datetime',
        'amount_paid_at_reschedule'         => 'decimal:2',
        'outstanding_balance_at_reschedule' => 'decimal:2',
    ];

    public function originalAdvance()
    {
        return $this->belongsTo(ClientAdvance::class, 'original_advance_id');
    }

    public function fromAdvance()
    {
        return $this->belongsTo(ClientAdvance::class, 'from_advance_id');
    }

    public function toAdvance()
    {
        return $this->belongsTo(ClientAdvance::class, 'to_advance_id');
    }

    public function rescheduledBy()
    {
        return $this->belongsTo(User::class, 'rescheduled_by');
    }
}
