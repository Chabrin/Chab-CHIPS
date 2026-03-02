<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected static function boot()
    {
        parent::boot();
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

    public function leaseAgreements()
    {
        return $this->hasMany(LeaseAgreement::class, 'property_id', 'id');
    }
}
