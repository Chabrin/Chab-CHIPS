<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WorkflowApprover extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'workflow_approvers';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'workflow_id',
        'approver_id',
        'position_type',
        'role_id',
        'approval_level',
        'is_final',
        'is_active',
        'added_by',
        'added_on',
    ];

    protected $casts = [
        'added_on' => 'datetime',
        'approval_level' => 'integer',
        'is_final' => 'integer',
        'is_active' => 'integer',
    ];

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Who the row stands for: the named user, the manager of the return's zone, or everyone holding a role.
     */
    public function getDisplayNameAttribute(): string {
        if ($this->position_type === 'zone_manager') {
            return "Zone Manager (of the return's zone)";
        }
        if ($this->position_type === 'role') {
            return (string) (DB::table('cb_roles')->where('id', $this->role_id)->value('name') ?? 'Role') . ' (role)';
        }

        return $this->approver->name ?? 'Unknown';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeForWorkflow($query, $workflowId)
    {
        return $query->where('workflow_id', $workflowId);
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('approval_level', $level);
    }

    public function isFinal()
    {
        return $this->is_final == 1;
    }

    public function isActive()
    {
        return $this->is_active == 1;
    }
}
