<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantRecord extends Model
{
    protected $table = 'cb_tenant_records';

    // No DB-level enum for id_document_type — validate application-side against this list.
    public const ID_DOCUMENT_TYPES = ['PASSPORT', 'NATIONAL_ID'];

    protected $fillable = [
        'id',
        'tenant_type',
        'company_registration_number',
        'salutation_id',
        'names',
        'gender_id',
        'phone',
        'email',
        'physical_address',
        'nationality',
        'id_document_type',
        'national_id',
        'passport_number',
        'kra_pin',
        'registered_date',
    ];

    protected $casts = [
        'registered_date' => 'date',
    ];

    public function salutation()
    {
        return $this->belongsTo(Salutation::class);
    }

    public function gender()
    {
        return $this->belongsTo(Gender::class);
    }

    // cb_tenant_records.id is the sys_pm_clients.id it was migrated from
    // (shared primary key — see the align-id migration), not a separate FK column.
    public function legacyClient()
    {
        return $this->belongsTo(Client::class, 'id', 'id');
    }

    public function bankDetails()
    {
        return $this->hasMany(TenantBankDetail::class, 'tenant_id');
    }

    public function otherContacts()
    {
        return $this->hasMany(TenantOtherContact::class, 'tenant_id');
    }

    public function nextOfKin()
    {
        return $this->hasMany(TenantNextOfKin::class, 'tenant_id');
    }
}
