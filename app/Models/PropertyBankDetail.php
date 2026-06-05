<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class PropertyBankDetail extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'property_bank_details';
    protected $primaryKey = 'id';

    protected $fillable = [
        'property_id',
        'client_id',
        'client_bank_id',
        'added_by',
        'added_on',
    ];

    protected $casts = [
        'added_on' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function clientBankDetail()
    {
        return $this->belongsTo(ClientBankDetail::class, 'client_bank_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function scopeForProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }
}
