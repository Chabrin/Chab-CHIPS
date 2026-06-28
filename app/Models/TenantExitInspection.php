<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantExitInspection extends Model
{
    protected $table = 'cb_tenant_exit_inspections';

    protected $fillable = [
        'exit_id',
        'tenant_id',
        'unit_id',
        'inspection_date',
        'overall_condition',
        'walls_condition',
        'floors_condition',
        'ceiling_condition',
        'doors_condition',
        'windows_condition',
        'plumbing_condition',
        'electrical_condition',
        'keys_returned',
        'keys_notes',
        'damage_description',
        'damage_amount',
        'cleaning_required',
        'cleaning_cost',
        'general_notes',
        'inspected_by_name',
        'photo_paths',
        'video_paths',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'damage_amount'   => 'decimal:2',
        'cleaning_cost'   => 'decimal:2',
        'photo_paths'     => 'array',
        'video_paths'     => 'array',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function exit(): BelongsTo
    {
        return $this->belongsTo(TenantExit::class, 'exit_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
