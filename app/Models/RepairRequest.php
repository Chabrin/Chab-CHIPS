<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairRequest extends Model
{
    protected $table = 'sys_pm_properties_repairs_requests';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'visited_date_time',
        'created_by_id',
        'zone_id',
        'property_id',
        'client_id',
        'duration',
        'current_status_id',
        'ref_number',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'visited_date_time' => 'datetime',
    ];

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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function repairItems()
    {
        return $this->hasMany(RepairRequestItem::class, 'parent_id');
    }

    public function repairElement()
    {
        return $this->hasManyThrough(
            PropertyElement::class,
            RepairRequestItem::class,
            'parent_id', // Foreign key on RepairRequestItem table...
            'id', // Foreign key on PropertyElement table...
            'id', // Local key on RepairRequest table...
            'property_element_id' // Local key on RepairRequestItem table...
        );
    }
}
