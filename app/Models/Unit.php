<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_pm_units';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'client_id',
        'property_id',
        'unit_type_id',
        'usage_type_id',
        'unit_code',
        'description',
        'created_by_id',
        'unit_uploads',
        'zone_id',
        'occupancy_status_id',
        'unit_name',
        'unit_condition_id',
        'category_id',
        'rent_amount',
        'vat_able',
        'current_status_id',
        'unit_number',
        'initial_water_meter_reading',
        'topology_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'rent_amount' => 'decimal:2',
    ];

    //relationships
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function topology()
    {
        return $this->belongsTo(UnitTopology::class, 'topology_id');
    }

    public function category()
    {
        return $this->belongsTo(UnitCategory::class, 'category_id');
    }

    public function unit_documents()
    {
        return $this->hasMany(UnitDocument::class, 'parent_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * The lease currently occupying this unit.
     *
     * Conclusion this is built on (from auditing every "is this unit vacant"
     * check in the app): occupancy_status_id can't be trusted for this — it
     * carries two overlapping, conflicting numbering schemes (legacy-migrated
     * units: 1=Occupied/2=Vacant/3=unclassified; app-native units: 31=Vacant/
     * 38=Occupied) and different controllers already disagreed on which value
     * meant what. Lease state is the reliable signal instead, but with two
     * corrections found by checking it against real data:
     *   1. current_status_id follows this app's standard maker-checker
     *      convention (used identically across Clients/Properties/Units/etc.):
     *      1 = Pending, 5 = Approved. LeasesController::store() always creates
     *      Pending; PropertyConfirmationController's approval action bumps it
     *      to Approved. Both count as occupying the unit — the approval step
     *      is an internal audit control, not proof the unit is empty.
     *   2. No date_start/date_end window: a first attempt using one matched
     *      zero units against live data, because ~99% of real leases have a
     *      null date_end.
     */
    public function activeLeaseAgreement()
    {
        return $this->hasOne(LeaseAgreement::class, 'unit_id')
            ->whereIn('current_status_id', [1, 5])
            ->latest('date_start');
    }
    
    // Get all lease agreements (historical)
    public function leaseAgreements()
    {
        return $this->hasMany(LeaseAgreement::class, 'unit_id')
            ->orderBy('date_start', 'desc');
    }
    
    // Get the most recent lease (active or expired)
    public function latestLeaseAgreement()
    {
        return $this->hasOne(LeaseAgreement::class, 'unit_id')
            ->latest('date_start');
    }

    /**
     * The tenant currently pointing at this unit via Client.unit_id, regardless
     * of lease status. Display-only (e.g. showing who's assigned when picking a
     * unit for a lease) — NOT the vacancy check, which is lease-based (see
     * scopeVacant()/isVacant() below) per a deliberate decision that a tenant
     * assigned-but-not-yet-leased still reads as vacant elsewhere.
     */
    public function assignedTenant()
    {
        return $this->hasOne(Client::class, 'unit_id')->where('type_id', 2);
    }

    /**
     * Single source of truth for "is this unit vacant": no Pending/Approved
     * lease (see activeLeaseAgreement() above for why — occupancy_status_id
     * itself can't be trusted for this, it carries multiple conflicting
     * numbering schemes across the codebase). A unit with no lease at all, or
     * only Renewed-out/Terminated/Withdrawn/Cancelled ones, is vacant.
     */
    public function scopeVacant($query)
    {
        return $query->whereDoesntHave('activeLeaseAgreement');
    }

    /**
     * Single-unit version of scopeVacant(), for one-off re-checks (e.g.
     * assignUnit()'s "is it still free" guard) where a full query scope isn't
     * already in hand.
     */
    public function isVacant(): bool
    {
        return !$this->activeLeaseAgreement()->exists();
    }

    /**
     * Build a unit's code from the property it belongs to and its own unit
     * number, e.g. property "145A" + unit number "I2" -> "145A-I2". Shared by
     * UnitsController::store() and BulkUploadController::processUnits() so
     * the two creation paths can't drift apart again.
     */
    public static function generateUnitCode(Property $property, string $unitNumber): string
    {
        return $property->reference_number . '-' . trim($unitNumber);
    }
}
