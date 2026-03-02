<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowApprover extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'workflow_approvers';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'workflow_id',
        'approver_type',
        'user_id',
        'role_id',
        'hierarchy_level',
        'sort_order',
        'is_required',
        'notes',
        'active',
        'added_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_required' => 'boolean',
        'active' => 'boolean',
        'hierarchy_level' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Get the workflow that owns the approver.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    /**
     * Get the user associated with the approver.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the role associated with the approver.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Get the user who added this approver.
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Scope to get active approvers.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get approvers by hierarchy level.
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('hierarchy_level', $level);
    }

    /**
     * Scope to get user approvers.
     */
    public function scopeUsers($query)
    {
        return $query->where('approver_type', 'user');
    }

    /**
     * Scope to get role approvers.
     */
    public function scopeRoles($query)
    {
        return $query->where('approver_type', 'role');
    }

    /**
     * Get the approver name (user or role name).
     */
    public function getApproverNameAttribute()
    {
        if ($this->approver_type === 'user' && $this->user) {
            if ($this->user->employee) {
                $employee = $this->user->employee;
                $nameParts = array_filter([
                    $employee->first_name,
                    $employee->other_name,
                    $employee->surname
                ]);
                return implode(' ', $nameParts) ?: $this->user->username;
            }
            return $this->user->username;
        } elseif ($this->approver_type === 'role' && $this->role) {
            return $this->role->display_name ?? $this->role->name;
        }
        return 'Unknown';
    }
}
