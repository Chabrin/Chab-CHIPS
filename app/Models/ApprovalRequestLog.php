<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequestLog extends Model
{
    protected $table = 'approval_requests_logs';

    public $timestamps = false;

    protected $fillable = [
        'approval_request_id',
        'approver_id',
        'approval_level',
        'action',
        'comment',
        'returned_to_level',
        'action_on',
    ];

    protected $casts = [
        'approval_request_id' => 'integer',
        'approver_id' => 'integer',
        'approval_level' => 'integer',
        'returned_to_level' => 'integer',
        'action_on' => 'datetime',
    ];

    const ACTION_SUBMITTED = 'submitted';
    const ACTION_APPROVED = 'approved';
    const ACTION_REJECTED = 'rejected';
    const ACTION_CHANGES_REQUESTED = 'changes_requested';
    const ACTION_RETURNED = 'returned';
    const ACTION_RESUBMITTED = 'resubmitted';

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function scopeForRequest($query, int $requestId)
    {
        return $query->where('approval_request_id', $requestId);
    }

    public static function logAction(
        int $requestId,
        ?int $approverId,
        int $level,
        string $action,
        ?string $comment = null,
        ?int $returnedToLevel = null
    ): self {
        return self::create([
            'approval_request_id' => $requestId,
            'approver_id' => $approverId,
            'approval_level' => $level,
            'action' => $action,
            'comment' => $comment,
            'returned_to_level' => $returnedToLevel,
            'action_on' => now(),
        ]);
    }

    public function getActionLabel(): string
    {
        return match($this->action) {
            self::ACTION_SUBMITTED => 'Submitted for Approval',
            self::ACTION_APPROVED => 'Approved',
            self::ACTION_REJECTED => 'Rejected',
            self::ACTION_CHANGES_REQUESTED => 'Changes Requested',
            self::ACTION_RETURNED => 'Returned to Level ' . $this->returned_to_level,
            self::ACTION_RESUBMITTED => 'Resubmitted',
            default => ucfirst($this->action),
        };
    }

    public function getActionBadgeClass(): string
    {
        return match($this->action) {
            self::ACTION_SUBMITTED, self::ACTION_RESUBMITTED => 'bg-info',
            self::ACTION_APPROVED => 'bg-success',
            self::ACTION_REJECTED => 'bg-danger',
            self::ACTION_CHANGES_REQUESTED => 'bg-warning',
            self::ACTION_RETURNED => 'bg-secondary',
            default => 'bg-light',
        };
    }
}
