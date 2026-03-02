<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyElement extends Model
{
    protected $table = 'sys_pm_properties_elements';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'element_name',
        'current_status_id',
        'created_by_id',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function repairTypes()
    {
        return $this->hasMany(RepairType::class, 'element_id');
    }
}
