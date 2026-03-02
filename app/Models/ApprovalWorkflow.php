<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalWorkflow extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    protected $table = 'approval_workflows';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'workflow_name',
        'slug',
        'description',
        'active',
        'added_by',
        'deactivated_by',
        'deactivated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'active' => 'integer',
        'deactivated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user who added this workflow.
     */
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Get the user who deactivated this workflow.
     */
    public function deactivatedBy()
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * Scope to get only active workflows.
     */
    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    /**
     * Scope to get only inactive workflows.
     */
    public function scopeInactive($query)
    {
        return $query->where('active', 0);
    }

    /**
     * Check if the workflow is active.
     */
    public function isActive()
    {
        return $this->active === 1;
    }

    /**
     * Check if the workflow is inactive.
     */
    public function isInactive()
    {
        return $this->active === 0;
    }

    /**
     * Generate a slug from the workflow name.
     */
    public static function generateSlug($workflowName)
    {
        // Convert to lowercase and replace spaces with underscores
        $slug = strtolower(str_replace(' ', '_', trim($workflowName)));

        // Remove any characters that are not letters, numbers, or underscores
        $slug = preg_replace('/[^a-z0-9_]/', '', $slug);

        // Remove multiple consecutive underscores
        $slug = preg_replace('/_+/', '_', $slug);

        // Remove leading/trailing underscores
        $slug = trim($slug, '_');

        return $slug;
    }

    /**
     * Get the approvers for the workflow.
     */
    public function approvers()
    {
        return $this->hasMany(WorkflowApprover::class, 'workflow_id')
            ->orderBy('hierarchy_level')
            ->orderBy('sort_order');
    }

    /**
     * Get active approvers for the workflow.
     */
    public function activeApprovers()
    {
        return $this->approvers()->active();
    }
}
