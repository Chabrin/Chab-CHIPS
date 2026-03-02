<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineDeadlineExtension extends Model
{
    public $table = 'cb_discipline_deadline_extensions';
    public $timestamps = false;
    
    protected $fillable = [
        'case_id',
        'original_deadline',
        'new_deadline',
        'extension_reason',
        'requested_days',
        'extended_by',
        'requested_by',
        'extended_at',
        'requested_at',
        'deadline_lapsed_before_extension',
        'request_status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'original_deadline' => 'date',
        'new_deadline' => 'date',
        'extended_at' => 'datetime',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'deadline_lapsed_before_extension' => 'boolean',
    ];
    
    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function extendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'extended_by');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}