<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'approval_workflows';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'workflow_name',
        'description',
        'status',
        'added_by',
        'added_on',
    ];

    protected $casts = [
        'added_on' => 'datetime',
        'status' => 'integer',
    ];

    public function approvers()
    {
        return $this->hasMany(WorkflowApprover::class, 'workflow_id')
            ->orderBy('approval_level');
    }

    public function activeApprovers()
    {
        return $this->hasMany(WorkflowApprover::class, 'workflow_id')
            ->where('is_active', 1)
            ->orderBy('approval_level');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function changeLogs()
    {
        return $this->hasMany(WorkflowApproverChangeLog::class, 'workflow_id')
            ->orderByDesc('changed_on');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function isActive()
    {
        return $this->status == 1;
    }
}
