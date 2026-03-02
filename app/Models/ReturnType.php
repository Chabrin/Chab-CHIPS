<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnType extends Model
{
    protected $table = 'sys_pm_clients_returns_types';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'type_name',
    ];


}
