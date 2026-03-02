<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineCaseRegistration extends Model
{
    protected $fillable = [
        'case_id',
        'registered_by',
        'registration_date',
        'hr_notes',
        'priority',
        'estimated_closure_date',
        'hr_notified',
        'notification_sent_at',
    ];

    protected $casts = [
        'registration_date' => 'datetime',
        'estimated_closure_date' => 'date',
        'hr_notified' => 'boolean',
        'notification_sent_at' => 'datetime',
    ];

    public $timestamps = false;

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}