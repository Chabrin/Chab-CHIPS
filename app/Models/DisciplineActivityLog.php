<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineActivityLog extends Model
{
    protected $table = 'discipline_activity_log';
    
    protected $fillable = [
        'case_id',
        'step_number',
        'step_name',
        'activity_type',
        'activity_description',
        'performed_by',
        'metadata',
    ];

    protected $casts = [
        'step_number' => 'integer',
        'performed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'performed_at';

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}