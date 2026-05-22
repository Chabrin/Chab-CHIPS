<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_set_providers';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'provider_name',
        'provider_code',
        'address',
        'phone',
        'email',
        'current_status_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];
}
