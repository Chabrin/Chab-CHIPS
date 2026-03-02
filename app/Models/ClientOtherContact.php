<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientOtherContact extends Model
{
    protected $table = 'sys_pm_clients_19_repeat';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'contact_names',
        'other_email',
        'other_mobile_number',
    ];


    public function client()
    {
        return $this->belongsTo(Client::class, 'parent_id');
    }
}
