<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantType extends Model
{
    use SoftDeletes;

    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'cb_tenant_types';

    protected $fillable = [
        'name',
        'description',
        'deleted_by',
    ];
}
