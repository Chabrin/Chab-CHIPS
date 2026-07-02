<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TenantExit extends Model
{
    protected $table = 'cb_tenant_exits';

    protected $fillable = [
        'tenant_id',
        'property_id',
        'unit_id',
        'vacate_type',
        'vacate_notice_date',
        'intended_exit_date',
        'actual_exit_date',
        'exit_reason',
        'exit_notes',
        'final_notes',
        'current_status_id',
        'created_by',
        'updated_by',
        'finalised_by',
        'finalised_at',
        'process_1_completed_at',
        'deposit_as_rent',
        'deposit_as_rent_approved',
        'deposit_as_rent_approved_by',
        'deposit_as_rent_amount',
    ];

    protected $casts = [
        'vacate_notice_date'      => 'date',
        'intended_exit_date'      => 'date',
        'actual_exit_date'        => 'date',
        'finalised_at'            => 'datetime',
        'process_1_completed_at'  => 'datetime',
        'deposit_as_rent'         => 'boolean',
        'deposit_as_rent_approved'=> 'boolean',
        'deposit_as_rent_amount'  => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

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

    public function inspection(): HasOne
    {
        return $this->hasOne(TenantExitInspection::class, 'exit_id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(TenantExitCommunication::class, 'exit_id');
    }

    public function depositRefunds(): HasMany
    {
        return $this->hasMany(TenantExitDepositRefund::class, 'exit_id');
    }

    public function history(): HasOne
    {
        return $this->hasOne(TenantHistory::class, 'exit_id');
    }

    public function dateChanges(): HasMany
    {
        return $this->hasMany(TenantExitDateChange::class, 'exit_id');
    }

    public function fieldVisits(): HasMany
    {
        return $this->hasMany(TenantExitFieldVisit::class, 'exit_id');
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(TenantExitSettlement::class, 'exit_id');
    }

    public function depositApplications(): HasMany
    {
        return $this->hasMany(DepositApplication::class, 'exit_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeInProgress($query)
    {
        return $query->where('current_status_id', 1);
    }

    public function scopeCompleted($query)
    {
        return $query->where('current_status_id', 2);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->current_status_id === 2;
    }
}
