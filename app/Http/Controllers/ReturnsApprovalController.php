<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ClientReturnsBatch;
use App\Traits\HandlesApprovalWorkflow;
use App\Traits\HandlesReturnApprovalWorkflow;
use App\Jobs\ProcessReturnsFinalApprovalJob;

class ReturnsApprovalController extends Controller
{
    use HandlesApprovalWorkflow;
    use HandlesReturnApprovalWorkflow;

    /**
     * Get batch approvals data for timeline display (AJAX)
     */
    public function getBatchApprovals($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $batch = ClientReturnsBatch::findOrFail($id);

            // Get approval item for this batch
            $approvalItem = DB::table('cb_approval_items')
                ->where('item_type', 'returns')
                ->where('item_id', $batch->id)
                ->first();

            if (!$approvalItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'No approval workflow found for this batch'
                ]);
            }

            // Get all approvers for this item
            $approvers = DB::table('cb_item_approvers as ia')
                ->leftJoin('josya_users as u', 'u.id', '=', 'ia.approver_id')
                ->where('ia.approval_item_id', $approvalItem->id)
                ->select(
                    'ia.*',
                    'u.name as approver_name',
                    'u.email as approver_email'
                )
                ->orderBy('ia.approver_level', 'asc')
                ->get();

            // Get all actions for each approver
            foreach ($approvers as $approver) {
                $approver->actions = DB::table('cb_approval_actions')
                    ->where('item_approver_id', $approver->id)
                    ->orderBy('sequence_number', 'asc')
                    ->get();
            }

            // Get current user info for permission checks
            $currentUser = auth()->user();
            $currentUserId = $currentUser->id;

            // Determine if current user can approve
            $canApprove = false;
            $canReject = false;
            $userApproverLevel = null;
            $isFirstLevel = false;
            $availableRejectLevels = [];

            foreach ($approvers as $approver) {
                if ($approver->approver_id == $currentUserId) {
                    $userApproverLevel = $approver->approver_level;
                    $isFirstLevel = ($userApproverLevel == 1);

                    // User can approve if:
                    // 1. It's their turn (current_level matches their level)
                    // 2. Status is Pending or InProgress
                    // 3. They haven't already approved
                    if (
                        $approvalItem->current_level == $userApproverLevel
                        && in_array($approvalItem->status, ['Pending', 'InProgress'])
                        && $approver->approval_status == 'Pending'
                    ) {
                        $canApprove = true;

                        // Can reject only if level 2 or higher
                        if ($userApproverLevel >= 2) {
                            $canReject = true;

                            // Get available levels to reject to (all levels below current)
                            for ($i = 1; $i < $userApproverLevel; $i++) {
                                $lowerApprover = collect($approvers)->firstWhere('approver_level', $i);
                                if ($lowerApprover) {
                                    $availableRejectLevels[] = [
                                        'level' => $i,
                                        'approver_name' => $lowerApprover->approver_name ?? 'Level ' . $i
                                    ];
                                }
                            }
                        }
                    }
                    break;
                }
            }

            // Format approvers for timeline display
            $timeline = [];
            foreach ($approvers as $approver) {
                // Format actions for this approver
                $actions = [];
                foreach ($approver->actions as $action) {
                    $actions[] = [
                        'action' => $action->action,
                        'comments' => $action->comments ?? '',
                        'action_timestamp' => $action->action_timestamp,
                        'sequence_number' => $action->sequence_number,
                        'returned_to_level' => $action->returned_to_level ?? null
                    ];
                }

                $timeline[] = [
                    'level' => $approver->approver_level,
                    'approver_name' => $approver->approver_name ?? 'Unknown',
                    'approver_email' => $approver->approver_email ?? '',
                    'status' => $approver->approval_status,
                    'assigned_at' => $approver->assigned_at,
                    'actioned_at' => $approver->actioned_at,
                    'actions' => $actions,
                    'is_current' => ($approver->approver_level == $approvalItem->current_level),
                    'is_user' => ($approver->approver_id == $currentUserId)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'approval_item' => $approvalItem,
                    'timeline' => $timeline,
                    'can_approve' => $canApprove,
                    'can_reject' => $canReject,
                    'available_reject_levels' => $availableRejectLevels,
                    'user_level' => $userApproverLevel,
                    'is_first_level' => $isFirstLevel,
                    'current_user_id' => $currentUserId
                ]
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading batch approvals: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load approval data'
            ], 500);
        }
    }

    /**
     * Add a comment to batch approval
     */
    public function addApprovalComment($encryptedId, Request $request)
    {
        try {
            $id = decrypt($encryptedId);
            $batch = ClientReturnsBatch::findOrFail($id);

            $request->validate([
                'comment' => 'required|string|max:1000'
            ]);

            // Get approval item
            $approvalItem = DB::table('cb_approval_items')
                ->where('item_type', 'returns')
                ->where('item_id', $batch->id)
                ->first();

            if (!$approvalItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'No approval workflow found'
                ], 404);
            }

            // Get user's item_approver record
            $itemApprover = DB::table('cb_item_approvers')
                ->where('approval_item_id', $approvalItem->id)
                ->where('approver_id', auth()->id())
                ->first();

            if (!$itemApprover) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not an approver for this batch'
                ], 403);
            }

            // Insert comment action
            DB::table('cb_approval_actions')->insert([
                'item_approver_id' => $itemApprover->id,
                'approval_item_id' => $approvalItem->id,
                'actor_id' => auth()->id(),
                'from_level' => $itemApprover->approver_level,
                'to_level' => null,
                'action' => 'Commented',
                'comments' => $request->comment,
                'action_timestamp' => now(),
                'ip_address' => request()->ip(),
                'sequence_number' => DB::table('cb_approval_actions')
                    ->where('approval_item_id', $approvalItem->id)
                    ->max('sequence_number') + 1,
                'is_current' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully',
                'data' => [
                    'comment' => $request->comment,
                    'user_name' => auth()->user()->name,
                    'timestamp' => now()->format('d M Y, H:i')
                ]
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error adding approval comment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment'
            ], 500);
        }
    }

    /**
     * Approve a batch
     */
    public function approveBatch($encryptedId, Request $request)
    {
        try {
            $id = decrypt($encryptedId);
            $batch = ClientReturnsBatch::findOrFail($id);

            $request->validate([
                'comments' => 'nullable|string|max:1000'
            ]);

            // Get approval item
            $approvalItem = DB::table('cb_approval_items')
                ->where('item_type', 'returns')
                ->where('item_id', $batch->id)
                ->first();

            if (!$approvalItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'No approval workflow found'
                ], 404);
            }

            // Check if overall status is Pending or InProgress
            if (!in_array($approvalItem->status, ['Pending', 'InProgress'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'This batch has already been processed'
                ], 400);
            }

            // Get user's item_approver record
            $itemApprover = DB::table('cb_item_approvers')
                ->where('approval_item_id', $approvalItem->id)
                ->where('approver_id', auth()->id())
                ->where('approver_level', $approvalItem->current_level)
                ->first();

            if (!$itemApprover) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to approve at this level'
                ], 403);
            }

            // Check if already approved
            if ($itemApprover->approval_status === 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already approved this batch'
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Update item approver status
                DB::table('cb_item_approvers')
                    ->where('id', $itemApprover->id)
                    ->update([
                        'approval_status' => 'Approved',
                        'actioned_at' => now(),
                        'updated_at' => now()
                    ]);

                // Insert approval action
                DB::table('cb_approval_actions')->insert([
                    'item_approver_id' => $itemApprover->id,
                    'approval_item_id' => $approvalItem->id,
                    'actor_id' => auth()->id(),
                    'from_level' => $itemApprover->approver_level,
                    'to_level' => $itemApprover->approver_level + 1,
                    'action' => 'Approved',
                    'comments' => $request->comments,
                    'action_timestamp' => now(),
                    'ip_address' => request()->ip(),
                    'sequence_number' => DB::table('cb_approval_actions')
                        ->where('approval_item_id', $approvalItem->id)
                        ->max('sequence_number') + 1,
                    'is_current' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Check if current user is an accountant (final approver)
                $isAccountant = DB::table('cb_role_user as ru')
                    ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                    ->where('ru.user_id', auth()->id())
                    ->where('r.name', 'accountants')
                    ->exists();

                // Check if this was the final approval (accountant approving)
                if ($isAccountant) {
                    // Validate batch before processing
                    $validation = $this->validateBatchForApproval($batch);
                    if (!$validation['valid']) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => $validation['message']
                        ], 400);
                    }

                    // Final approval - update approval item to Approved
                    DB::table('cb_approval_items')
                        ->where('id', $approvalItem->id)
                        ->update([
                            'status' => 'Approved',
                            'current_level' => $itemApprover->approver_level,
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                            'updated_at' => now()
                        ]);

                    // Update batch approval_status to final approved
                    DB::table('sys_pm_clients_returns_batches')
                        ->where('id', $batch->id)
                        ->update([
                            'approval_status' => 102 // Final approval
                        ]);

                    // Dispatch background job for final approval workflow
                    // This handles:
                    // - Update returns to Approved status (current_status_id = 5)
                    // - Create landlord ledger credit entries
                    // - Create commission debit entries
                    // - Create GL entries for commission revenue
                    // - Record workflow activity
                    ProcessReturnsFinalApprovalJob::dispatch($batch->id, auth()->id(), $request->comments);

                    Log::info('Final approval job dispatched', [
                        'batch_id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'approved_by' => auth()->id(),
                        'approver_level' => $itemApprover->approver_level
                    ]);

                    $message = 'Batch approved successfully (Final Approval). Processing landlord ledger entries in background.';
                } else {
                    // Move to next level
                    DB::table('cb_approval_items')
                        ->where('id', $approvalItem->id)
                        ->update([
                            'current_level' => $itemApprover->approver_level + 1,
                            'status' => 'InProgress',
                            'updated_at' => now()
                        ]);

                    // If this is level 1 (first approval), update batch status to in progress
                    if ($itemApprover->approver_level == 1) {
                        DB::table('sys_pm_clients_returns_batches')
                            ->where('id', $batch->id)
                            ->update([
                                'approval_status' => 101 // In progress
                            ]);
                    }

                    // Add next level approver automatically
                    $this->addNextLevelApprover($approvalItem, $batch, $itemApprover->approver_level);

                    $message = 'Batch approved successfully. Moved to Level ' . ($itemApprover->approver_level + 1);
                }
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error approving batch: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve batch'
            ], 500);
        }
    }

    /**
     * Reject a batch
     */
    public function rejectBatch($encryptedId, Request $request)
    {
        try {
            $id = decrypt($encryptedId);
            $batch = ClientReturnsBatch::findOrFail($id);

            $request->validate([
                'reason' => 'required|string|max:1000',
                'return_to_level' => 'required|integer|min:1'
            ]);

            // Get approval item
            $approvalItem = DB::table('cb_approval_items')
                ->where('item_type', 'returns')
                ->where('item_id', $batch->id)
                ->first();

            if (!$approvalItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'No approval workflow found'
                ], 404);
            }

            // Check if overall status is Pending or InProgress
            if (!in_array($approvalItem->status, ['Pending', 'InProgress'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'This batch has already been processed'
                ], 400);
            }

            // Get user's item_approver record
            $itemApprover = DB::table('cb_item_approvers')
                ->where('approval_item_id', $approvalItem->id)
                ->where('approver_id', auth()->id())
                ->where('approver_level', $approvalItem->current_level)
                ->first();

            if (!$itemApprover) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to reject at this level'
                ], 403);
            }

            // Validate user is level 2 or higher
            if ($itemApprover->approver_level < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only level 2 and above can reject'
                ], 403);
            }

            // Validate return_to_level is below current level
            if ($request->return_to_level >= $itemApprover->approver_level) {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only return to a lower level'
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Update item approver status to Rejected
                DB::table('cb_item_approvers')
                    ->where('id', $itemApprover->id)
                    ->update([
                        'approval_status' => 'Rejected',
                        'actioned_at' => now(),
                        'updated_at' => now()
                    ]);

                // Get the approver being returned to
                $returnToApprover = DB::table('cb_item_approvers')
                    ->where('approval_item_id', $approvalItem->id)
                    ->where('approver_level', $request->return_to_level)
                    ->first();

                if ($returnToApprover) {
                    // Reset the returned-to approver status to Pending
                    DB::table('cb_item_approvers')
                        ->where('id', $returnToApprover->id)
                        ->update([
                            'approval_status' => 'Pending',
                            'actioned_at' => null,
                            'updated_at' => now()
                        ]);
                }

                // Insert rejection action
                DB::table('cb_approval_actions')->insert([
                    'item_approver_id' => $itemApprover->id,
                    'approval_item_id' => $approvalItem->id,
                    'actor_id' => auth()->id(),
                    'from_level' => $itemApprover->approver_level,
                    'to_level' => $request->return_to_level,
                    'action' => 'Returned',
                    'returned_to_approver_id' => $returnToApprover ? $returnToApprover->approver_id : null,
                    'returned_to_level' => $request->return_to_level,
                    'comments' => $request->reason,
                    'action_timestamp' => now(),
                    'ip_address' => request()->ip(),
                    'sequence_number' => DB::table('cb_approval_actions')
                        ->where('approval_item_id', $approvalItem->id)
                        ->max('sequence_number') + 1,
                    'is_current' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Update approval item to return to specified level
                DB::table('cb_approval_items')
                    ->where('id', $approvalItem->id)
                    ->update([
                        'status' => 'InProgress',
                        'current_level' => $request->return_to_level,
                        'updated_at' => now()
                    ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Batch returned to Level ' . $request->return_to_level . ' for revision'
                ]);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error rejecting batch: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject batch'
            ], 500);
        }
    }
}
