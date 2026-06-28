<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositApplication extends Model
{
    protected $table = 'cb_deposit_applications';

    protected $fillable = [
        'exit_id',
        'tenant_id',
        'type',
        'amount',
        'approved_by_landlord',
        'approved_by_agent',
        'notes',
    ];

    protected $casts = [
        'amount'               => 'decimal:2',
        'approved_by_landlord' => 'boolean',
    ];

    public function exit(): BelongsTo
    {
        return $this->belongsTo(TenantExit::class, 'exit_id');
    }

    public function approvedByAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_agent');
    }
}
