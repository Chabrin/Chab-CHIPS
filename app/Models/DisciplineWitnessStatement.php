<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisciplineWitnessStatement extends Model
{
    protected $fillable = [
        'case_id',
        'witness_name',
        'witness_staff_id',
        'witness_contact',
        'witness_role',
        'statement_text',
        'statement_date',
        'is_sworn',
        'sworn_before',
        'recorded_by',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'is_sworn' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'recorded_at';

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function witnessStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'witness_staff_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DisciplineWitnessDocument::class, 'witness_statement_id');
    }
}