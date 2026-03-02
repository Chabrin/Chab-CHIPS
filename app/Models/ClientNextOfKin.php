<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientNextOfKin extends Model
{
    protected $table = 'sys_pm_clients_66_repeat';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'kin_first_name',
        'mobile_number',
        'email_address',
        'relationship',
        'national_id',
        'kin_second_name',
        'kin_last_name',
        'names',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'parent_id');
    }
}
