<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ApprovalItem extends Model
{
    protected $table = 'cb_approval_items';
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $fillable = [
        'item_type',
        'workflow_name',
        'item_id',
        'member_no',
        'status',
        'requested_by',
        'requested_on',
        'approved_by',
        'approved_on',
        'remarks'
    ];

    public $timestamps = true;

    public function actions()
    {
        return $this->hasMany(ApprovalAction::class);
    }

    public function itemApprovers()
    {
        return $this->hasMany(\App\Models\ItemApprover::class, 'approval_item_id', 'id');
    }

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_name', 'slug');
    }

    public function nextApprover()
    {
        // Ensure the workflow relationship exists
        if (!$this->workflow) {
            return $this->newQuery()->whereRaw('1 = 0'); // Return an empty query
        }

        // Find next level approver in this item's workflow
        return $this->workflow->approvers()
            ->whereNotIn('hierarchy_level', function ($sub) {
                $sub->select('hierarchy_level')
                    ->from('approval_actions')
                    ->whereColumn('approval_actions.approval_item_id', 'approval_items.id')
                    ->where('action_type', 'approve');
            })
            ->orderBy('hierarchy_level')
            ->limit(1);
    }

    public function allPrecedingLevelsApproved($currentLevel)
    {
        // Ensure the workflow relationship exists
        if (!$this->workflow) {
            return false;
        }

        // Check if all preceding levels have approved
        return !$this->workflow->approvers()
            ->where('hierarchy_level', '<', $currentLevel)
            ->whereNotIn('hierarchy_level', function ($sub) {
                $sub->select('hierarchy_level')
                    ->from('approval_actions')
                    ->whereColumn('approval_actions.approval_item_id', 'approval_items.id')
                    ->where('action_type', 'approve');
            })
            ->exists();
    }
}
