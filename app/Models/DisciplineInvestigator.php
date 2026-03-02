<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineInvestigator extends Model
{
    protected $fillable = [
        'case_id',
        'investigator_id',
        'assigned_by',
        'assigned_at',
        'investigation_start_date',
        'investigation_deadline',
        'status',
        'reassigned_to',
        'reassignment_reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'investigation_start_date' => 'date',
        'investigation_deadline' => 'date',
    ];

    public $timestamps = false;

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'investigator_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function reassignedTo(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'reassigned_to');
    }
}