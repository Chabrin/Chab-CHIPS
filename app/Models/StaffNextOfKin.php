<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffNextOfKin extends Model
{
    protected $table = 'sys_staff_next_of_kin';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'staff_id',
        'staff_number',
        'kin_name',
        'relationship',
        'kin_type',
        'kin_address',
        'kin_city',
        'kin_phone',
        'date_time',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
