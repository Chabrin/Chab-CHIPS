<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class Industry extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    protected $table = 'industries';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $fillable = [
        'industry_name',
        'description',
        'added_by',
        'added_on',
        'other'
    ];
}
