<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([AuditLogsObserver::class])]
class ApprovalRequest extends Model
{
    protected $table = 'approval_requests';

    public $timestamps = false;

    protected $fillable = [
        'request_type',
        'request_id',
        'workflow_id',
        'current_level',
        'current_approver_id',
        'status',
        'initiated_by',
        'initiated_on',
        'completed_on',
    ];

    protected $casts = [
        'workflow_id' => 'integer',
        'request_id' => 'integer',
        'current_level' => 'integer',
        'current_approver_id' => 'integer',
        'initiated_by' => 'integer',
        'initiated_on' => 'datetime',
        'completed_on' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CHANGES_REQUESTED = 'changes_requested';

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_approver_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApprovalRequestLog::class, 'approval_request_id')->orderBy('action_on', 'asc');
    }

    public function latestLog()
    {
        return $this->hasOne(ApprovalRequestLog::class, 'approval_request_id')->latestOfMany('action_on');
    }

    public function scopeForRequest($query, string $type, int $id)
    {
        return $query->where('request_type', $type)->where('request_id', $id);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_CHANGES_REQUESTED]);
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_REJECTED]);
    }

    public function getCurrentApproverRecord()
    {
        return WorkflowApprover::where('workflow_id', $this->workflow_id)
            ->where('approval_level', $this->current_level)
            ->where('approver_id', $this->current_approver_id)
            ->where('is_active', 1)
            ->first();
    }

    public function canUserApprove(int $userId): bool
    {
        return $this->current_approver_id === $userId;
    }

    public function isComplete(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED]);
    }

    public function isPending(): bool
    {
        return !$this->isComplete();
    }

    public function getMaxLevel(): int
    {
        return WorkflowApprover::where('workflow_id', $this->workflow_id)
            ->where('is_active', 1)
            ->max('approval_level') ?? 1;
    }

    public function getRequestable()
    {
        $modelClass = match($this->request_type) {
            'voucher' => \App\Models\PaymentVoucher::class,
            default => null,
        };

        if ($modelClass) {
            return $modelClass::find($this->request_id);
        }

        return null;
    }
}
