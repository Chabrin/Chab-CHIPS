<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ApprovalAction extends Model
{
    protected $table = 'approval_actions';
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $fillable = [
        'approval_item_id',
        'approver_id',
        'action_type',
        'remarks'
    ];

    public $timestamps = true;

    public function item()
    {
        return $this->belongsTo(ApprovalItem::class, 'approval_item_id');
    }

    public function approverRole()
    {
        return $this->belongsTo(Role::class, 'approver_id')->whereHas('workflowApprovers', function ($query) {
            $query->where('approver_type', 'role');
        });
    }

    public function approver()
    {
        return $this->belongsTo(WorkflowApprover::class, 'approver_id');
    }
}
