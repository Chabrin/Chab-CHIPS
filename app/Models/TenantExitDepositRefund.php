<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantExitDepositRefund extends Model
{
    protected $table = 'cb_tenant_exit_deposit_refunds';

    protected $fillable = [
        'exit_id',
        'tenant_id',
        'refund_amount',
        'refund_notes',
        'refunded_by',
        'refunded_at',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'refunded_at'   => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function exit(): BelongsTo
    {
        return $this->belongsTo(TenantExit::class, 'exit_id');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'refunded_by');
    }
}
