<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One thing done to a HELB loan (added, changed, paused, resumed, closed with HELB's clearance, reopened), with who and why. */
class HelbDeductionEvent extends Model
{
    public const LABELS = [
        'created' => 'Added',
        'changed' => 'Changed',
        'paused' => 'Paused',
        'resumed' => 'Resumed',
        'cleared' => 'Closed with HELB clearance',
        'reopened' => 'Reopened',
    ];

    public $timestamps = false;

    protected $table = 'sys_helb_deduction_events';

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
