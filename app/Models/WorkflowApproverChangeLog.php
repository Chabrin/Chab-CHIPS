<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowApproverChangeLog extends Model
{
    protected $table = 'workflow_approvers_changes_log';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'workflow_id',
        'approver_id',
        'action',
        'old_level',
        'new_level',
        'old_is_final',
        'new_is_final',
        'details',
        'changed_by',
        'changed_on',
    ];

    protected $casts = [
        'changed_on' => 'datetime',
        'old_level' => 'integer',
        'new_level' => 'integer',
        'old_is_final' => 'integer',
        'new_is_final' => 'integer',
    ];

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function scopeForWorkflow($query, $workflowId)
    {
        return $query->where('workflow_id', $workflowId);
    }

    public static function logChange($workflowId, $approverId, $action, $details = null, $oldLevel = null, $newLevel = null, $oldIsFinal = null, $newIsFinal = null)
    {
        return self::create([
            'workflow_id' => $workflowId,
            'approver_id' => $approverId,
            'action' => $action,
            'old_level' => $oldLevel,
            'new_level' => $newLevel,
            'old_is_final' => $oldIsFinal,
            'new_is_final' => $newIsFinal,
            'details' => $details,
            'changed_by' => auth()->id(),
            'changed_on' => now(),
        ]);
    }
}
