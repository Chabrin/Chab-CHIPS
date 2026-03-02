<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Laratrust\Models\Permission as PermissionModel;

class Permission extends PermissionModel
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    public $guarded = [];
    protected $table = 'permissions';
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'module_id',
        'action',
    ];
}
