<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class PropertyMerge extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'cb_property_merges';
    protected $primaryKey = 'id';

    protected $fillable = [
        'merge_reference',
        'client_id',
        'merge_type',
        'merged_property_name',
        'target_property_id',
        'bank_account_id',
        'notes',
        'current_status_id',
        'created_by',
        'approved_by',
        'approved_at',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
        'date_time',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'approved_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    // Relationships

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function targetProperty()
    {
        return $this->belongsTo(Property::class, 'target_property_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(ClientBankAccount::class, 'bank_account_id');
    }

    public function items()
    {
        return $this->hasMany(PropertyMergeItem::class, 'property_merge_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reversedBy()
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    // Helpers

    /**
     * Generate a unique merge reference: MRG-YYYYMMDD-XXXX
     */
    public static function generateReference(): string
    {
        $prefix = 'MRG';
        $date = now()->format('Ymd');
        $lastMerge = self::whereDate('date_time', today())->orderBy('id', 'desc')->first();
        $sequence = 1;

        if ($lastMerge && preg_match('/(\d{4})$/', $lastMerge->merge_reference, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix . '-' . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Status label accessor
     */
    public function getStatusLabelAttribute(): string
    {
        return match ((int) $this->current_status_id) {
            1 => 'Pending',
            2 => 'Completed',
            3 => 'Reversed',
            default => 'Unknown',
        };
    }

    /**
     * Get encrypted ID for URL routing
     */
    public function getEncryptedIdAttribute()
    {
        return \Illuminate\Support\Facades\Crypt::encrypt($this->id);
    }
}
