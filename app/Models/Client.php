<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        'overdraft_penalty',
        'company_name',
        'company_registration_number',
        'contact_person_name',
        'contact_person_title',
        'contact_person_mobile',
        'contact_person_email',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'registered_date' => 'datetime',
        'lease_start_date' => 'datetime',
        'rent_amount' => 'decimal:2',
    ];

    /**
     * GLOBAL SCOPE: Restrict clients by assigned properties
     */
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('user_clients_properties', function ($query) {

            if (!Auth::check()) {
                return;
            }

            $userId = Auth::id();

            $isGlobal = DB::table('cb_role_user as ru')
                ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                ->where('ru.user_id', $userId)
                ->where('r.is_global', true)
                ->exists();

            if ($isGlobal) {
                return;
            }

            $staffId = DB::table('sys_user_staff_links')
                ->where('user_id', $userId)
                ->value('staff_id');

            if (!$staffId) {
                $query->whereRaw('1 = 0');
                return;
            }

            // Matches the tenant's own property or a landlord's owned property;
            // sys_set_zones IDs are staff-based (not user-based), unlike sys_pm_zones_assignments (dead).
            $query->whereExists(function ($sub) use ($staffId) {
                $sub->selectRaw('1')
                    ->from('sys_pm_properties as spp')
                    ->leftJoin('sys_set_zones as spz', 'spz.id', '=', 'spp.zone_id')
                    ->where(function ($clientMatch) {
                        $clientMatch->whereColumn('spp.client_id', 'sys_pm_clients.id')
                            ->orWhereColumn('spp.id', 'sys_pm_clients.property_id');
                    })
                    ->where(function ($q) use ($staffId) {
                        $q->where('spp.field_officer_id', $staffId)
                            ->orWhere('spp.zone_manager_id', $staffId)
                            ->orWhere('spp.zone_supervisor_id', $staffId)
                            ->orWhere('spz.zone_manager_id', $staffId)
                            ->orWhere('spz.zone_supervisor_id', $staffId);
                    });
            });
        });
    }

    /**
     * OPTIONAL: bypass scope when needed
     */
    public function scopeWithAll($query)
    {
        return $query->withoutGlobalScope('user_clients_properties');
    }

    public function routeNotificationForMail(): string
    {
        return $this->email_address;
    }

    public function scopeTypeOne($query)
    {
        return $query->where('type_id', 1);
    }

    public function scopeTenants($query)
    {
        return $query->where('type_id', 2);
    }

    public function scopeClients($query)
    {
        return $query->where('type_id', 1);
    }

    public function scopeLandlords($query)
    {
        return $query->where('type_id', 1);
    }

    public function getEncryptedIdAttribute()
    {
        return \Illuminate\Support\Facades\Crypt::encrypt($this->id);
    }

    public function isTenant()
    {
        return strpos($this->reference_number, 'TEN') === 0;
    }

    public function isClient()
    {
        return strpos($this->reference_number, 'LAN') === 0;
    }

    // Relationships

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    // Landlord-side: properties this client owns (inverse of Property::client()).
    // Distinct from property() above, which is a tenant's single assigned property.
    public function properties()
    {
        return $this->hasMany(Property::class, 'client_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function bankAccounts()
    {
        return $this->hasMany(ClientBankDetail::class, 'client_id', 'id');
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

    // Tenant-only normalized tables (cb_tenant_records shares this row's id) —
    // additive alongside the above, which stay in place for landlord records.
    public function tenantBankDetails()
    {
        return $this->hasMany(TenantBankDetail::class, 'tenant_id', 'id');
    }

    public function tenantOtherContacts()
    {
        return $this->hasMany(TenantOtherContact::class, 'tenant_id', 'id');
    }

    public function tenantNextOfKin()
    {
        return $this->hasMany(TenantNextOfKin::class, 'tenant_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lease()
    {
        return $this->hasOne(LeaseAgreement::class, 'tenant_id', 'id');
    }

    public function deposits()
    {
        return $this->hasMany(Deposit::class, 'tenant_id', 'id');
    }

    public function sla()
    {
        return $this->belongsTo(ServiceAgreement::class, 'sla_id');
    }

    public function advances()
    {
        return $this->hasMany(ClientAdvance::class, 'client_id', 'id');
    }

    public function bankDetails()
    {
        return $this->hasMany(ClientBankDetail::class, 'client_id', 'id');
    }

    public function defaultBankDetail()
    {
        return $this->hasOne(ClientBankDetail::class, 'client_id', 'id')->where('is_default', 1);
    }

    public function stayHistory()
    {
        return $this->hasMany(TenantHistory::class, 'tenant_id');
    }

    public function propertiesOwned()
    {
        return $this->hasMany(TenantHistory::class, 'landlord_id');
    }
}
