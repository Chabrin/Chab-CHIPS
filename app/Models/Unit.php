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
}
