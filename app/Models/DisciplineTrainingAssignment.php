<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineTrainingAssignment extends Model
{
    protected $fillable = [
        'case_id',
        'training_type',
        'training_provider',
        'training_description',
        'scheduled_date',
        'completion_deadline',
        'completed',
        'completion_date',
        'completion_certificate_path',
        'assigned_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completion_deadline' => 'date',
        'completed' => 'boolean',
        'completion_date' => 'date',
        'assigned_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'assigned_at';

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}