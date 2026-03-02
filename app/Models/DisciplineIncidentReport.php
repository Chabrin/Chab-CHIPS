<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisciplineIncidentReport extends Model
{
    protected $fillable = [
        'case_id',
        'incident_date_time',
        'location',
        'incident_type_id',
        'incident_description',
        'immediate_action_taken',
        'reported_by',
        'witness_present',
        'police_involved',
    ];

    protected $casts = [
        'incident_date_time' => 'datetime',
        'witness_present' => 'boolean',
        'police_involved' => 'boolean',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function incidentType(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_type_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'reported_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DisciplineIncidentDocument::class, 'incident_report_id');
    }
}