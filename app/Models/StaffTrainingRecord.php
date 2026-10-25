<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTrainingRecord extends Model
{
    protected $fillable = [
        'staff_id',
        'training_material_id',
        'version_acknowledged',
        'completed_at',
        'due_date',
        'ip_address',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'due_date' => 'date',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(TrainingMaterial::class, 'training_material_id');
    }
}