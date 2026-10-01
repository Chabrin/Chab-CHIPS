<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    protected $table = 'currencies';
    protected $fillable = ['currency_code', 'currency_name', 'country_id', 'added_by'];

    //add relationship to country
    public function country()
    {
        return $this->belongsTo(SystemCountry::class, 'country_id');
    }
}
