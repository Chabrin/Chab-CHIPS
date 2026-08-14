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

    // Get the currently active lease
    public function activeLeaseAgreement()
    {
        return $this->hasOne(LeaseAgreement::class, 'unit_id')
            ->where('current_status_id', 1)
            ->whereDate('date_start', '<=', now())
            ->whereDate('date_end', '>=', now())
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
