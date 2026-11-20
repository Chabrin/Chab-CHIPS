<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One thing done to a scheduled deduction (created, consent added, paused, resumed, closed, instalment changed...), with who and why. */
class ScheduledDeductionEvent extends Model
{
    public const LABELS = [
        'created' => 'Created',
        'consent_added' => 'Signed consent form added',
        'consent_replaced' => 'Signed consent form replaced',
        'paused' => 'Paused',
        'resumed' => 'Resumed',
        'closed' => 'Closed early',
        'instalment_changed' => 'Monthly amount changed',
        'completed' => 'Paid off',
        'reversed' => 'Payroll reversed',
        'limit_override' => 'Legal limit overridden',
    ];

    public $timestamps = false;

    protected $table = 'sys_hr_scheduled_deduction_events';

    protected $fillable = ['deduction_id', 'event', 'reason', 'details', 'user_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getLabelAttribute(): string
    {
        return self::LABELS[$this->event] ?? ucfirst(str_replace('_', ' ', (string) $this->event));
    }
}
