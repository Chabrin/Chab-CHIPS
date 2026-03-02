<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisciplineEmployeeResponse extends Model
{
    protected $fillable = [
        'case_id',
        'response_date',
        'response_text',
        'admits_allegations',
        'mitigating_circumstances',
        'submitted_by',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'response_date' => 'date',
        'admits_allegations' => 'boolean',
        'submitted_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'submitted_at';

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DisciplineResponseDocument::class, 'response_id');
    }
}