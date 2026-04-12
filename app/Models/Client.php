<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Client extends Model
{
    use Notifiable;
    protected $table = 'sys_pm_clients';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'names',
        'address',
        'vat_number',
        'pin_number',
        'mobile_number',
        'email_address',
        'bank_id',
        'account_name',
        'account_number',
        'username',
        'client_password',
        'uid',
        'group_id',
        'registered_date',
        'reference_number',
        'created_by',
        'nationality_id',
        'national_id',
        'passport_number',
        'client_type_id',
        'photo',
        'documents',
        'current_status_id',
        'client_status_id',
        'lead_id',
        'type_id',
        'client_id',
        'second_name',
        'last_name',
        'title',
        'gender',
        'prefered_messages_language_id',
        'property_id',
        'sla_id',
        'unit_id',
        'lease_start_date',
        'lease_years',
        'rent_amount',
        'escalation_rate',
        'frequency',
        'address_2',
        'address_3',
        'promas_id',
        'properties',
        'overdraft_penalty'
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'registered_date' => 'datetime',
        'lease_start_date' => 'datetime',
        'rent_amount' => 'decimal:2',
    ];

    public function routeNotificationForMail(): string
    {
        return $this->email_address;
    }

    /**
     * Scope a query to only include clients with type_id = 1
     */
    public function scopeTypeOne($query)
    {
        return $query->where('type_id', 1);
    }

    /**
     * Scope a query to only include tenants (reference_number starts with TEN)
     */
    public function scopeTenants($query)
    {
        return $query->where('reference_number', 'LIKE', 'TEN%');
    }

    /**
     * Scope a query to only include clients/landlords (reference_number starts with LAN)
     */
    public function scopeClients($query)
    {
        return $query->where('reference_number', 'LIKE', 'LAN%');
    }

    /**
     * Scope a query to only include landlords (alias for clients)
     */
    public function scopeLandlords($query)
    {
        return $query->where('reference_number', 'LIKE', 'LAN%');
    }

    /**
     * Get encrypted ID for URL routing
     */
    public function getEncryptedIdAttribute()
    {
        return \Illuminate\Support\Facades\Crypt::encrypt($this->id);
    }

     /**
     * Check if this record is a tenant
     */
    public function isTenant()
    {
        return strpos($this->reference_number, 'TEN') === 0;
    }

    /**
     * Check if this record is a client/landlord
     */
    public function isClient()
    {
        return strpos($this->reference_number, 'LAN') === 0;
    }

    // Relationships
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function bankAccounts()
    {
        return $this->hasMany(ClientBankAccount::class, 'parent_id', 'id');
    }

    public function documents()
    {
        return $this->hasMany(ClientDocument::class, 'parent_id', 'id');
    }

    public function nextOfKins()
    {
        return $this->hasMany(ClientNextOfKin::class, 'parent_id', 'id');
    }

    public function otherContacts()
    {
        return $this->hasMany(ClientOtherContact::class, 'parent_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lease()
    {
        return $this->hasOne(LeaseAgreement::class, 'tenant_id', 'id');
    }

    public function sla()
    {
        return $this->belongsTo(ServiceAgreement::class, 'sla_id');
    }

}
