<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Property extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('status_active', function ($query) {
            $query->where('active', 1);
        });

        static::addGlobalScope('user_properties', function (Builder $query) {
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

            // Visible if directly assigned on the property (sys_pm_properties, staff-based)
            // or assigned as the manager/supervisor of the property's zone (sys_set_zones,
            // josya_users-based) — assignment no longer flows through sys_pm_zones_assignments.
            $query->where(function ($q) use ($staffId, $userId) {
                $q->where('sys_pm_properties.field_officer_id', $staffId)
                    ->orWhere('sys_pm_properties.zone_manager_id', $staffId)
                    ->orWhere('sys_pm_properties.zone_supervisor_id', $staffId)
                    ->orWhereExists(function ($sub) use ($userId) {
                        $sub->selectRaw('1')
                            ->from('sys_set_zones as z')
                            ->whereColumn('z.id', 'sys_pm_properties.zone_id')
                            ->where(function ($zq) use ($userId) {
                                $zq->where('z.zone_manager_id', $userId)
                                    ->orWhere('z.zone_supervisor_id', $userId);
                            });
                    });
            });
        });

        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_pm_properties';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'client_id',
        'reference_number',
        'description',
        'lat_long',
        'photos_and_documents',
        'zone_id',
        'zone_area_id',
        'property_name',
        'lr_number',
        'usage_type_id',
        'current_status_id',
        'acquisition_date',
        'created_by',
        'bank_account_id',
        'field_officer_id',
        'zone_supervisor_id',
        'zone_manager_id',
        'parent_property_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'acquisition_date' => 'datetime',
    ];

    //relationships
    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function zoneArea()
    {
        return $this->belongsTo(ZoneArea::class, 'zone_area_id');
    }

    public function fieldOfficer()
    {
        return $this->belongsTo(Staff::class, 'field_officer_id');
    }

    public function zoneSupervisor()
    {
        return $this->belongsTo(Staff::class, 'zone_supervisor_id');
    }

    public function zoneManager()
    {
        return $this->belongsTo(Staff::class, 'zone_manager_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function units()
    {
        return $this->hasMany(Unit::class, 'property_id', 'id');
    }

    public function serviceAgreements()
    {
        return $this->hasMany(ServiceAgreement::class, 'property_id');
    }

    public function leaseAgreements()
    {
        return $this->hasMany(LeaseAgreement::class, 'property_id', 'id');
    }

    public function assignments()
    {
        return $this->hasMany(ZoneAssignment::class, 'property_id');
    }

    public function scopeWithInactive($query)
    {
        return $query->withoutGlobalScope('status_active');
    }

    public function scopeWithAll($query)
    {
        return $query->withoutGlobalScope('user_properties');
    }

    /**
     * Generate the next reference number for a zone, e.g. "146A". Locks the
     * matching rows for the duration of the caller's transaction so two
     * concurrent creations in the same zone can't collide, with a defensive
     * backstop that keeps bumping the number if it's somehow already taken
     * (e.g. irregular legacy data). Shared by PropertiesController::store()
     * and BulkUploadController::processProperties() so the two creation
     * paths can't drift apart again.
     */
    public static function generateReferenceNumber(Zone $zone): string
    {
        $zoneName = strtoupper($zone->zone_name);

        $lastProperty = static::where('reference_number', 'LIKE', "%{$zoneName}")
            ->orderByRaw("CAST(SUBSTRING(reference_number FROM '^[0-9]+') AS INTEGER) DESC")
            ->lockForUpdate()
            ->first();

        if ($lastProperty) {
            preg_match('/(\d+)([A-Z]+)$/', $lastProperty->reference_number, $matches);
            $newNumber = !empty($matches) ? intval($matches[1]) + 1 : 1;
        } else {
            $newNumber = 1;
        }

        $referenceNumber = $newNumber . $zoneName;

        while (static::where('reference_number', $referenceNumber)->exists()) {
            $newNumber++;
            $referenceNumber = $newNumber . $zoneName;
        }

        return $referenceNumber;
    }
}
