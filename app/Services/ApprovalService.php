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

class ApprovalService
{
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
                $approvalRequest = ApprovalRequest::create([
                    'request_type' => $requestType,
                    'request_id' => $requestId,
                    'workflow_id' => $workflow->id,
                    'current_level' => 1,
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
                    'completed_on' => now(),
                ]);

                $this->updateRelatedItemStatus($request, 'approved');

                return [
                    'success' => true,
                    'message' => 'Final approval granted. Request is now approved.',
                    'is_final' => true,
                ];
            }

            $request->update([
                'current_level' => $currentLevel + 1,
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

        $targetApprover = WorkflowApprover::where('workflow_id', $request->workflow_id)
            ->where('approval_level', $targetLevel)
            ->where('is_active', 1)
            ->first();

        if (!$targetApprover) {
            return ['success' => false, 'message' => 'No active approver found at target level'];
        }

        return DB::transaction(function () use ($request, $approverId, $targetLevel, $comment) {
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

            $request->update([
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
                'action' => $log->action,
                'action_label' => $log->getActionLabel(),
                'action_badge_class' => $log->getActionBadgeClass(),
                'comment' => $log->comment,
                'returned_to_level' => $log->returned_to_level,
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
        $workflow = $request->workflow;

        return [
            'id' => $request->id,
            'request_type' => $request->request_type,
            'request_id' => $request->request_id,
            'workflow_name' => $workflow?->workflow_name,
            'current_level' => $request->current_level,
            'max_level' => $request->getMaxLevel(),
            'status' => $request->status,
            'status_label' => ucwords(str_replace('_', ' ', $request->status)),
            'initiated_by' => $request->initiator?->name ?? 'Unknown',
            'initiated_on' => $request->initiated_on?->format('d M Y, h:i A'),
            'completed_on' => $request->completed_on?->format('d M Y, h:i A'),
            'current_approver' => $currentApprover ? [
                'id' => $currentApprover->approver_id,
                'name' => $currentApprover->approver?->name,
                'email' => $currentApprover->approver?->email,
                'level' => $currentApprover->approval_level,
                'is_final' => (bool) $currentApprover->is_final,
            ] : null,
            'is_complete' => $request->isComplete(),
            'timeline' => $this->getTimeline($request),
        ];
    }

    /**
     * Get all pending approval requests for a user.
     */
    public function getPendingForUser(int $userId): array
    {
        $approverLevels = WorkflowApprover::where('approver_id', $userId)
            ->where('is_active', 1)
            ->get(['workflow_id', 'approval_level']);

        if ($approverLevels->isEmpty()) {
            return [];
        }

        $requests = ApprovalRequest::pending()
            ->with(['workflow', 'initiator'])
            ->get()
            ->filter(function ($request) use ($approverLevels) {
                return $approverLevels->contains(function ($level) use ($request) {
                    return $level->workflow_id === $request->workflow_id
                        && $level->approval_level === $request->current_level;
                });
            });

        return $requests->map(function ($request) {
            return [
                'id' => $request->id,
                'encrypted_id' => encrypt($request->id),
                'request_type' => $request->request_type,
                'request_id' => $request->request_id,
                'encrypted_request_id' => encrypt($request->request_id),
                'workflow_name' => $request->workflow?->workflow_name,
                'current_level' => $request->current_level,
                'status' => $request->status,
                'initiated_by' => $request->initiator?->name,
                'initiated_on' => $request->initiated_on?->format('d M Y'),
            ];
        })->values()->toArray();
    }

    /**
     * Get historical approval requests for a user (not at their stage, already passed, or completed).
     */
    public function getHistoricalForUser(int $userId): array
    {
        $approverLevels = WorkflowApprover::where('approver_id', $userId)
            ->where('is_active', 1)
            ->get(['workflow_id', 'approval_level']);

        if ($approverLevels->isEmpty()) {
            return [];
        }

        $workflowIds = $approverLevels->pluck('workflow_id')->unique()->toArray();

        $requests = ApprovalRequest::whereIn('workflow_id', $workflowIds)
            ->with(['workflow', 'initiator'])
            ->orderBy('initiated_on', 'desc')
            ->get()
            ->filter(function ($request) use ($approverLevels, $userId) {
                $userLevel = $approverLevels->first(function ($level) use ($request) {
                    return $level->workflow_id === $request->workflow_id;
                });

                if (!$userLevel) {
                    return false;
                }

                if ($request->isComplete()) {
                    return true;
                }

                return $request->current_level !== $userLevel->approval_level;
            });

        return $requests->map(function ($request) use ($approverLevels) {
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
                }
            }
        } catch (Exception $e) {
            Log::error("Failed to update related item status: " . $e->getMessage());
        }
    }
}
