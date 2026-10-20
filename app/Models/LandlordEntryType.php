<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * One kind of landlord ledger entry and whether it counts toward the landlord's balance.
 * Changes are audited because they change every landlord's balance.
 */
class LandlordEntryType extends Model
{
    /**
     * Audit every change.
     */
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'cb_landlord_entry_types';

    protected $fillable = [
        'code',
        'description_pattern',
        'name',
        'kind',
        'counts_toward_balance',
        'notes',
    ];

    protected $casts = [
        'counts_toward_balance' => 'boolean',
    ];
}
