<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineSuspension extends Model
{
    protected $fillable = [
        'case_id',
        'suspended_by',
        'suspended_at',
        'reason',
        'pay_status',
        'lifted_by',
        'lifted_at',
        'notes',
    ];

    protected $casts = [
        'suspended_at' => 'datetime',
        'lifted_at' => 'datetime',
    ];

    public $timestamps = false;

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    public function isActive(): bool
    {
        return is_null($this->lifted_at);
    }
}
