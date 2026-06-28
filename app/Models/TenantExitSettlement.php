<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantExitSettlement extends Model
{
    protected $table = 'cb_tenant_exit_settlements';

    protected $fillable = [
        'exit_id',
        'tenant_id',
        'deposit_on_record',
        'arrears_amount',
        'inspection_damage',
        'cleaning_cost',
        'deposit_as_rent_applied',
        'other_deductions',
        'other_deductions_notes',
        'settlement_balance',
        'settlement_type',
        'notes',
        'settled_by',
        'settled_at',
    ];

    protected $casts = [
        'deposit_on_record'       => 'decimal:2',
        'arrears_amount'          => 'decimal:2',
        'inspection_damage'       => 'decimal:2',
        'cleaning_cost'           => 'decimal:2',
        'deposit_as_rent_applied' => 'decimal:2',
        'other_deductions'        => 'decimal:2',
        'settlement_balance'      => 'decimal:2',
        'settled_at'              => 'datetime',
    ];

    public function exit(): BelongsTo
    {
        return $this->belongsTo(TenantExit::class, 'exit_id');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}
