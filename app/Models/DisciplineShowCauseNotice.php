<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisciplineShowCauseNotice extends Model
{
    protected $fillable = [
        'case_id',
        'notice_number',
        'issue_date',
        'response_deadline',
        'allegations',
        'potential_consequences',
        'issued_by',
        'delivered_method',
        'delivered_at',
        'acknowledgment_received',
        'acknowledgment_date',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'response_deadline' => 'date',
        'delivered_at' => 'datetime',
        'acknowledgment_received' => 'boolean',
        'acknowledgment_date' => 'datetime',
    ];

    public $timestamps = false;

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DisciplineShowCauseDocument::class, 'show_cause_id');
    }
}