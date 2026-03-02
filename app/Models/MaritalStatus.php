<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class MaritalStatus extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    protected $table = 'marital_status';
    protected $primaryKey = 'id';
    //public $timestamps = false;

    protected $fillable = [
        'name',
        'added_by',
        'deleted_by',
        'created_at',
        'updated_at',
    ];
}
