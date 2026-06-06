<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestLog;
use App\Models\ApprovalWorkflow;
use App\Models\WorkflowApprover;
use App\Models\PaymentVoucherHeader;
use App\Models\PaymentVoucherLine;

class ApprovalService
{
    /**
     * Select the approver with the least pending workload at a given level.
     * If multiple approvers have the same count, select randomly.
     */
    protected function selectApproverWithLeastLoad(int $workflowId, int $level): ?int
    {
        $approvers = WorkflowApprover::where('workflow_id', $workflowId)
            ->where('approval_level', $level)
            ->where('is_active', 1)
            ->pluck('approver_id')
            ->toArray();

        if (empty($approvers)) {
            return null;
        }

        if (count($approvers) === 1) {
            return $approvers[0];
        }

        $pendingCounts = [];
        foreach ($approvers as $approverId) {
            $count = ApprovalRequest::where('current_approver_id', $approverId)
                ->whereIn('status', [
                    ApprovalRequest::STATUS_PENDING,
                    ApprovalRequest::STATUS_IN_PROGRESS,
                    ApprovalRequest::STATUS_CHANGES_REQUESTED
                ])
                ->count();
            $pendingCounts[$approverId] = $count;
        }

        $minCount = min($pendingCounts);
        $approversWithMinLoad = array_keys(array_filter($pendingCounts, fn($c) => $c === $minCount));

        return $approversWithMinLoad[array_rand($approversWithMinLoad)];
    }

    /**
     * Initiate an approval request for a given item.
     */
    public function initiateApproval(string $requestType, int $requestId, string $workflowName, int $initiatedBy): ?ApprovalRequest
    {
        try {
            $workflow = ApprovalWorkflow::where('workflow_name', $workflowName)
                ->where('status', 1)
                ->first();

            if (!$workflow) {
                Log::warning("Approval workflow '{$workflowName}' not found or inactive");
                return null;
            }

            $approvers = WorkflowApprover::where('workflow_id', $workflow->id)
                ->where('is_active', 1)
                ->orderBy('approval_level')
                ->get();

            if ($approvers->isEmpty()) {
                Log::warning("No active approvers found for workflow '{$workflowName}'");
                return null;
            }

            return DB::transaction(function () use ($requestType, $requestId, $workflow, $initiatedBy) {
                $assignedApproverId = $this->selectApproverWithLeastLoad($workflow->id, 1);

                $approvalRequest = ApprovalRequest::create([
                    'request_type' => $requestType,
                    'request_id' => $requestId,
                    'workflow_id' => $workflow->id,
                    'current_level' => 1,
                    'current_approver_id' => $assignedApproverId,
                    'status' => ApprovalRequest::STATUS_PENDING,
                    'initiated_by' => $initiatedBy,
                    'initiated_on' => now(),
                ]);

                ApprovalRequestLog::logAction(
                    $approvalRequest->id,
                    $initiatedBy,
                    0,
                    ApprovalRequestLog::ACTION_SUBMITTED,
                    'Submitted for approval'
                );

                return $approvalRequest;
            });
        } catch (Exception $e) {
            Log::error("Error initiating approval: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Approve the current level.
     */
    public function approve(ApprovalRequest $request, int $approverId, ?string $comment = null): array
    {
        if (!$request->canUserApprove($approverId)) {
            return ['success' => false, 'message' => 'You are not authorized to approve at this level'];
        }

        if ($request->isComplete()) {
            return ['success' => false, 'message' => 'This request has already been completed'];
        }

        return DB::transaction(function () use ($request, $approverId, $comment) {
            $currentLevel = $request->current_level;
            $maxLevel = $request->getMaxLevel();

            ApprovalRequestLog::logAction(
                $request->id,
                $approverId,
                $currentLevel,
                ApprovalRequestLog::ACTION_APPROVED,
                $comment
            );

            if ($currentLevel >= $maxLevel) {
                $request->update([
                    'status' => ApprovalRequest::STATUS_APPROVED,
                    'current_approver_id' => null,
                    'completed_on' => now(),
                ]);

                $this->updateRelatedItemStatus($request, 'approved');

                return [
                    'success' => true,
                    'message' => 'Final approval granted. Request is now approved.',
                    'is_final' => true,
                ];
            }

            $nextLevel = $currentLevel + 1;
            $nextApproverId = $this->selectApproverWithLeastLoad($request->workflow_id, $nextLevel);

            $request->update([
                'current_level' => $nextLevel,
                'current_approver_id' => $nextApproverId,
                'status' => ApprovalRequest::STATUS_IN_PROGRESS,
            ]);

            return [
                'success' => true,
                'message' => 'Approved. Moved to next level for approval.',
                'is_final' => false,
            ];
        });
    }

    /**
     * Reject the request.
     */
    public function reject(ApprovalRequest $request, int $approverId, ?string $comment = null): array
    {
        if (!$request->canUserApprove($approverId)) {
            return ['success' => false, 'message' => 'You are not authorized to reject at this level'];
        }

        if ($request->isComplete()) {
            return ['success' => false, 'message' => 'This request has already been completed'];
        }

        return DB::transaction(function () use ($request, $approverId, $comment) {
            ApprovalRequestLog::logAction(
                $request->id,
                $approverId,
                $request->current_level,
                ApprovalRequestLog::ACTION_REJECTED,
                $comment
            );

            $request->update([
                'status' => ApprovalRequest::STATUS_REJECTED,
                'current_approver_id' => null,
                'completed_on' => now(),
            ]);

            $this->updateRelatedItemStatus($request, 'rejected');

            return [
                'success' => true,
                'message' => 'Request has been rejected.',
            ];
        });
    }

    /**
     * Request changes from the initiator.
     */
    public function requestChanges(ApprovalRequest $request, int $approverId, ?string $comment = null): array
    {
        if (!$request->canUserApprove($approverId)) {
            return ['success' => false, 'message' => 'You are not authorized to request changes at this level'];
        }

        if ($request->isComplete()) {
            return ['success' => false, 'message' => 'This request has already been completed'];
        }

        return DB::transaction(function () use ($request, $approverId, $comment) {
            ApprovalRequestLog::logAction(
                $request->id,
                $approverId,
                $request->current_level,
                ApprovalRequestLog::ACTION_CHANGES_REQUESTED,
                $comment
            );

            $request->update([
                'status' => ApprovalRequest::STATUS_CHANGES_REQUESTED,
            ]);

            return [
                'success' => true,
                'message' => 'Changes have been requested.',
            ];
        });
    }

    /**
     * Return to a previous approval level.
     */
    public function returnToPreviousLevel(ApprovalRequest $request, int $approverId, int $targetLevel, ?string $comment = null): array
    {
        if (!$request->canUserApprove($approverId)) {
            return ['success' => false, 'message' => 'You are not authorized to return this request'];
        }

        if ($request->isComplete()) {
            return ['success' => false, 'message' => 'This request has already been completed'];
        }

        if ($targetLevel >= $request->current_level) {
            return ['success' => false, 'message' => 'Target level must be lower than current level'];
        }

        if ($targetLevel < 1) {
            return ['success' => false, 'message' => 'Invalid target level'];
        }

        if ($targetLevel !== $request->current_level - 1) {
            return ['success' => false, 'message' => 'Can only return to the immediate previous level'];
        }

        $targetApproverId = $this->selectApproverWithLeastLoad($request->workflow_id, $targetLevel);

        if (!$targetApproverId) {
            return ['success' => false, 'message' => 'No active approver found at target level'];
        }

        return DB::transaction(function () use ($request, $approverId, $targetLevel, $targetApproverId, $comment) {
            ApprovalRequestLog::logAction(
                $request->id,
                $approverId,
                $request->current_level,
                ApprovalRequestLog::ACTION_RETURNED,
                $comment,
                $targetLevel
            );

            $request->update([
                'current_level' => $targetLevel,
                'current_approver_id' => $targetApproverId,
                'status' => ApprovalRequest::STATUS_IN_PROGRESS,
            ]);

            return [
                'success' => true,
                'message' => "Request returned to Level {$targetLevel} for review.",
            ];
        });
    }

    /**
     * Resubmit after changes requested.
     */
    public function resubmit(ApprovalRequest $request, int $userId, ?string $comment = null): array
    {
        if ($request->status !== ApprovalRequest::STATUS_CHANGES_REQUESTED) {
            return ['success' => false, 'message' => 'This request is not awaiting changes'];
        }

        if ($request->initiated_by !== $userId) {
            return ['success' => false, 'message' => 'Only the initiator can resubmit'];
        }

        return DB::transaction(function () use ($request, $userId, $comment) {
            ApprovalRequestLog::logAction(
                $request->id,
                $userId,
                0,
                ApprovalRequestLog::ACTION_RESUBMITTED,
                $comment
            );

            $newApproverId = $this->selectApproverWithLeastLoad($request->workflow_id, $request->current_level);

            $request->update([
                'current_approver_id' => $newApproverId,
                'status' => ApprovalRequest::STATUS_IN_PROGRESS,
            ]);

            return [
                'success' => true,
                'message' => 'Request has been resubmitted for approval.',
            ];
        });
    }

    /**
     * Get approval timeline for display.
     */
    public function getTimeline(ApprovalRequest $request): array
    {
        $logs = $request->logs()->with('approver')->orderBy('action_on', 'asc')->get();

        return $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'approver_name' => $log->approver?->name ?? 'System',
                'approver_email' => $log->approver?->email ?? null,
                'level' => $log->approval_level,
                'level_name' => self::getLevelName($log->approval_level),
                'action' => $log->action,
                'action_label' => $log->getActionLabel(),
                'action_badge_class' => $log->getActionBadgeClass(),
                'comment' => $log->comment,
                'returned_to_level' => $log->returned_to_level,
                'returned_to_level_name' => $log->returned_to_level ? self::getLevelName($log->returned_to_level) : null,
                'action_on' => $log->action_on?->format('d M Y, h:i A'),
                'action_on_relative' => $log->action_on?->diffForHumans(),
            ];
        })->toArray();
    }

    /**
     * Get approval request details including current state.
     */
    public function getRequestDetails(ApprovalRequest $request): array
    {
        $currentApprover = $request->getCurrentApprover();
        $currentApproverRecord = $request->getCurrentApproverRecord();
        $workflow = $request->workflow;

        return [
            'id' => $request->id,
            'request_type' => $request->request_type,
            'request_id' => $request->request_id,
            'workflow_name' => $workflow?->workflow_name,
            'current_level' => $request->current_level,
            'current_level_name' => $request->current_level ? self::getLevelName($request->current_level) : null,
            'max_level' => $request->getMaxLevel(),
            'status' => $request->status,
            'status_label' => ucwords(str_replace('_', ' ', $request->status)),
            'initiated_by' => $request->initiator?->name ?? 'Unknown',
            'initiated_on' => $request->initiated_on?->format('d M Y, h:i A'),
            'completed_on' => $request->completed_on?->format('d M Y, h:i A'),
            'current_approver' => $currentApprover ? [
                'id' => $currentApprover->id,
                'name' => $currentApprover->name,
                'email' => $currentApprover->email,
                'level' => $request->current_level,
                'level_name' => $request->current_level ? self::getLevelName($request->current_level) : null,
                'is_final' => $currentApproverRecord ? (bool) $currentApproverRecord->is_final : false,
            ] : null,
            'is_complete' => $request->isComplete(),
            'timeline' => $this->getTimeline($request),
        ];
    }

    /**
     * Convert approval level number to stage name.
     */
    public static function getLevelName(?int $level): ?string
    {
        if ($level === null) {
            return null;
        }
        
        return match($level) {
            1 => 'Checking Stage',
            2 => 'Verification Stage',
            3 => 'Approval Stage',
            default => "Level $level",
        };
    }

    /**
     * Get all pending approval requests assigned to a user.
     */
    public function getPendingForUser(int $userId): array
    {
        $requests = ApprovalRequest::pending()
            ->where('current_approver_id', $userId)
            ->with(['workflow', 'initiator'])
            ->orderBy('initiated_on', 'desc')
            ->get();

        return $requests->map(function ($request) {
            return [
                'id' => $request->id,
                'encrypted_id' => encrypt($request->id),
                'request_type' => $request->request_type,
                'request_id' => $request->request_id,
                'encrypted_request_id' => encrypt($request->request_id),
                'workflow_name' => $request->workflow?->workflow_name,
                'current_level' => $request->current_level,
                'current_level_name' => self::getLevelName($request->current_level),
                'status' => $request->status,
                'initiated_by' => $request->initiator?->name,
                'initiated_on' => $request->initiated_on?->format('d M Y'),
            ];
        })->values()->toArray();
    }

    /**
     * Get historical approval requests for a user (requests they've acted on, or in their workflow but not assigned to them).
     */
    public function getHistoricalForUser(int $userId): array
    {
        $approverLevels = WorkflowApprover::where('approver_id', $userId)
            ->where('is_active', 1)
            ->get(['workflow_id', 'approval_level']);

        $workflowIds = $approverLevels->pluck('workflow_id')->unique()->toArray();

        $requestIdsUserActedOn = ApprovalRequestLog::where('approver_id', $userId)
            ->pluck('approval_request_id')
            ->unique()
            ->toArray();

        $requests = ApprovalRequest::where(function ($query) use ($workflowIds, $requestIdsUserActedOn, $userId) {
                $query->whereIn('workflow_id', $workflowIds)
                    ->where(function ($q) use ($userId) {
                        $q->where('current_approver_id', '!=', $userId)
                            ->orWhereNull('current_approver_id');
                    });
            })
            ->orWhereIn('id', $requestIdsUserActedOn)
            ->where('current_approver_id', '!=', $userId)
            ->with(['workflow', 'initiator'])
            ->orderBy('initiated_on', 'desc')
            ->get();

        return $requests->map(function ($request) use ($approverLevels, $userId) {
            $userLevel = $approverLevels->first(function ($level) use ($request) {
                return $level->workflow_id === $request->workflow_id;
            });

            $stage = 'waiting';
            if ($request->status === ApprovalRequest::STATUS_APPROVED) {
                $stage = 'approved';
            } elseif ($request->status === ApprovalRequest::STATUS_REJECTED) {
                $stage = 'rejected';
            } elseif ($userLevel && $request->current_level > $userLevel->approval_level) {
                $stage = 'passed';
            }

            return [
                'id' => $request->id,
                'encrypted_id' => encrypt($request->id),
                'request_type' => $request->request_type,
                'request_id' => $request->request_id,
                'encrypted_request_id' => encrypt($request->request_id),
                'workflow_name' => $request->workflow?->workflow_name,
                'current_level' => $request->current_level,
                'current_level_name' => self::getLevelName($request->current_level),
                'stage' => $stage,
                'status' => $request->status,
                'initiated_by' => $request->initiator?->name,
                'initiated_on' => $request->initiated_on?->format('d M Y'),
            ];
        })->values()->toArray();
    }

    /**
     * Update the status of the related item (e.g., voucher) based on approval outcome.
     */
    protected function updateRelatedItemStatus(ApprovalRequest $request, string $status): void
    {
        try {
            if ($request->request_type === 'voucher') {
                $voucher = PaymentVoucherHeader::find($request->request_id);
                if ($voucher) {
                    $voucherStatus = match($status) {
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => $voucher->voucher_status,
                    };
                    $voucher->update(['voucher_status' => $voucherStatus]);

                    if ($status === 'approved') {
                        PaymentVoucherLine::where('entry_id', $voucher->id)
                            ->update(['is_approved' => 'Yes']);
                    }
                }
            }
        } catch (Exception $e) {
            Log::error("Failed to update related item status: " . $e->getMessage());
        }
    }
}
