<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    protected $table = 'countries';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'short_name',
        'name',
        'phone_code',
        'risk_level',
    ];
}
