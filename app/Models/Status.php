<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    protected $table = 'sys_set_status';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'status_name',
        'status_code'
    ];
}
