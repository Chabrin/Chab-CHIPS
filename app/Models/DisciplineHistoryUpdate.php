<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineHistoryUpdate extends Model
{
    protected $fillable = [
        'case_id',
        'update_type',
        'details',
        'expiry_date',
        'updated_by',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'updated_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'updated_at';

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}