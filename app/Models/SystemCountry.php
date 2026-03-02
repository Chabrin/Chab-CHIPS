<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Observers\AuditLogsObserver;

class SystemCountry extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    protected $table = 'sys_set_countries';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'country_code',
        'country_name',
    ];
}
