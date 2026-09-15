<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantExitLandlordConsent extends Model
{
    protected $table = 'cb_tenant_exit_landlord_consents';

    protected $fillable = [
        'exit_id',
        'settlement_id',
        'landlord_id',
        'tenant_id',
        'refund_amount',
        'current_status_id',
        'requested_by',
        'requested_at',
        'responded_at',
        'rejection_reason',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'requested_at'  => 'datetime',
        'responded_at'  => 'datetime',
    ];

    /**
     * The tenant exit this consent request belongs to.
     */
    public function exit(): BelongsTo
    {
        return $this->belongsTo(TenantExit::class, 'exit_id');
    }

    /**
     * The exit settlement whose refund amount this consent covers.
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(TenantExitSettlement::class, 'settlement_id');
    }

    /**
     * The landlord being asked to consent to the deduction.
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'landlord_id');
    }

    /**
     * The tenant whose refund this consent request relates to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    /**
     * The staff member who triggered this consent request.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Whether the landlord has not yet responded to this request.
     */
    public function isPending(): bool
    {
        return (int) $this->current_status_id === 1;
    }

    /**
     * Whether the landlord has approved this deduction.
     */
    public function isApproved(): bool
    {
        return (int) $this->current_status_id === 5;
    }
}
