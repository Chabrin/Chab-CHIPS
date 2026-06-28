<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoveInInspection extends Model
{
    protected $table = 'cb_move_in_inspections';

    protected $fillable = [
        'tenant_id',
        'property_id',
        'unit_id',
        'sla_id',
        'inspection_date',
        'inspector_name',
        'walls_condition',
        'floors_condition',
        'ceiling_condition',
        'doors_condition',
        'windows_condition',
        'plumbing_condition',
        'electrical_condition',
        'overall_condition',
        'notes',
        'photo_paths',
        'video_paths',
        'conducted_by',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'photo_paths'     => 'array',
        'video_paths'     => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function sla(): BelongsTo
    {
        return $this->belongsTo(ServiceAgreement::class, 'sla_id');
    }

    public function conductedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }
}
