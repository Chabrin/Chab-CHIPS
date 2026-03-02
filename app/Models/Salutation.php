<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class Salutation extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    protected $table = 'salutations';
    public $timestamps = false;
    protected $fillable = ['name', 'created_at', 'updated_at', 'description', 'deleted_at', 'deleted_by'];
}
