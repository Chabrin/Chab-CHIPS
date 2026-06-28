<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantHistory extends Model
{
    protected $table = 'cb_tenant_history';

    protected $fillable = [
        'tenant_id',
        'landlord_id',
        'property_id',
        'unit_id',
        'exit_id',
        'lease_start_date',
        'exit_date',
        'duration_days',
        'exit_reason',
        'recorded_by',
    ];

    protected $casts = [
        'lease_start_date' => 'date',
        'exit_date'        => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'landlord_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function exit(): BelongsTo
    {
        return $this->belongsTo(TenantExit::class, 'exit_id');
    }

    /** Human-readable duration, e.g. "2 yrs 3 mths". */
    public function getDurationLabelAttribute(): string
    {
        if (!$this->duration_days) {
            return '-';
        }
        $years  = intdiv($this->duration_days, 365);
        $months = intdiv($this->duration_days % 365, 30);
        $days   = $this->duration_days % 30;

        $parts = [];
        if ($years)  $parts[] = "{$years} " . ($years  === 1 ? 'yr'  : 'yrs');
        if ($months) $parts[] = "{$months} " . ($months === 1 ? 'mth' : 'mths');
        if (!$years && !$months) $parts[] = "{$days} " . ($days === 1 ? 'day' : 'days');

        return implode(' ', $parts);
    }
}
