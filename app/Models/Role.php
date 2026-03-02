<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Laratrust\Models\Role as RoleModel;

class Role extends RoleModel
{
    protected $table = 'cb_roles';
    /**
     * Relationship: moduleChildren (many-to-many)
     */
    public function moduleChildren()
    {
        return $this->belongsToMany(
            Submodule::class,
            'cb_sub_module_permissions',
            'role_id',
            'sub_module_id'
        );
    }

    /**
     * Relationship: workflowApprovers (one-to-many)
     */
    public function workflowApprovers()
    {
        return $this->hasMany(WorkflowApprover::class, 'role_id');
    }

    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    public $guarded = [];


    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];
}
