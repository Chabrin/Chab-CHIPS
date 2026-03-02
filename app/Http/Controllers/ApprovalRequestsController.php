<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MembersHost;
use App\Models\ApprovalItem;
use Illuminate\Http\Request;
use App\Models\ApprovalAction;
use App\Models\ApprovalWorkflow;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\Retirement\RetirementActions;
use App\Traits\Retirement\ProcessRetirementBalances;

class ApprovalRequestsController extends Controller
{
    use RetirementActions, ProcessRetirementBalances;

    public function index()
    {
        return view('approvals.requests');
    }


    public function list(Request $request)
    {
        try {
            $user = Auth::user();
            $userRoleIds = $user->roles->pluck('id')->toArray();

            $draw = $request->get('draw');
            $start = $request->get('start', 0);
            $length = $request->get('length', 10);

            $items = ApprovalItem::with(['workflow.approvers', 'actions.approver'])
                ->whereHas('workflow.approvers', function ($q) use ($user, $userRoleIds) {
                    $q->where('user_id', $user->id)
                        ->orWhereIn('role_id', $userRoleIds);
                })
                ->get();

            $filtered = $items->filter(function ($item) use ($user, $userRoleIds) {
                $workflow = $item->workflow;
                if (!$workflow) {
                    return false;
                }

                $approvers = $workflow->approvers->sortBy('hierarchy_level')->values();

                $userApprovers = $approvers->filter(function ($a) use ($user, $userRoleIds) {
                    return ($a->user_id === $user->id) ||
                        ($a->role_id && in_array($a->role_id, $userRoleIds));
                });

                if ($userApprovers->isEmpty()) {
                    return false;
                }

                $actionsWithLevels = DB::table('approval_actions')
                    ->join('workflow_approvers', 'approval_actions.approver_id', '=', 'workflow_approvers.user_id')
                    ->where('approval_actions.approval_item_id', $item->id)
                    ->where('workflow_approvers.workflow_id', $workflow->id)
                    ->select('approval_actions.*', 'workflow_approvers.hierarchy_level')
                    ->get();

                $approvedLevels = $actionsWithLevels
                    ->where('action_type', 'approve')
                    ->pluck('hierarchy_level')
                    ->unique()
                    ->toArray();

                foreach ($userApprovers as $ua) {
                    $currentLevel = $ua->hierarchy_level;

                    $currentLevelAction = $actionsWithLevels
                        ->where('hierarchy_level', $currentLevel)
                        ->where('approver_id', $user->id)
                        ->first();

                    if ($currentLevelAction && $currentLevelAction->action_type) {
                        continue;
                    }

                    $lowerLevels = $approvers
                        ->where('hierarchy_level', '<', $currentLevel)
                        ->pluck('hierarchy_level')
                        ->unique()
                        ->toArray();

                    $allLowerLevelsApproved = empty($lowerLevels) ||
                        count(array_intersect($lowerLevels, $approvedLevels)) === count($lowerLevels);

                    if ($allLowerLevelsApproved) {
                        return true;
                    }
                }

                return false;
            })->values();

            $recordsTotal = $filtered->count();
            $paged = $filtered->slice($start, $length);

            $data = $paged->map(function ($it) {
                return [
                    'id' => $it->id,
                    'encrypted_id' => encrypt($it->id),
                    'item_type' => $it->item_type,
                    'member_no' => $it->member_no,
                    'status' => $it->status,
                    'requested_by' => $it->requested_by,
                    'requested_on' => $it->requested_on,
                ];
            });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsTotal,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Approval list failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'error' => 'Failed to list approvals'], 500);
        }
    }



    public function action(Request $request)
    {
        try {
            Log::info('Approval action request received', [
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            $request->validate([
                'action_type' => 'required|in:approve,reject',
                'remarks' => 'nullable|string|max:1000',
                'approval_item_id' => 'required|exists:approval_items,id'
            ]);

            $action = ApprovalAction::where('approval_item_id', $request->approval_item_id)
                ->where('approver_id', Auth::id())
                ->first();

            if ($action) {
                // Check if action has already been taken
                if ($action->action_type !== null) {
                    return response()->json([
                        'success' => false,
                        'error' => 'You have already taken action on this approval request. Multiple actions are not allowed.'
                    ], 400);
                }

                // Start database transaction
                DB::beginTransaction();

                try {
                    $action->update([
                        'action_type' => $request->action_type,
                        'remarks' => $request->remarks,
                        'updated_at' => now()
                    ]);

                    // Get the approval item and workflow
                    $approvalItem = ApprovalItem::with('workflow.approvers')->find($request->approval_item_id);

                    if ($approvalItem && $approvalItem->workflow) {
                        // If action is reject, immediately set status to rejected
                        if ($request->action_type === 'reject') {
                            $approvalItem->update([
                                'status' => 'Rejected',
                                'updated_at' => now()
                            ]);

                            // If the workflow is for retire_members and member_no exists, unflag retirement
                            if ($approvalItem->workflow_name === 'retire_members' && $approvalItem->member_no) {
                                $this->unflagMemberRetirement($approvalItem->member_no);
                            }
                        }
                        // If action is approve, check if this is the last approval level
                        else if ($request->action_type === 'approve') {
                            $workflow = $approvalItem->workflow;

                            // Get all hierarchy levels in the workflow
                            $allLevels = $workflow->approvers->pluck('hierarchy_level')->unique()->sort()->values();

                            // Get all approved actions with their hierarchy levels
                            $actionsWithLevels = DB::table('approval_actions')
                                ->join('workflow_approvers', 'approval_actions.approver_id', '=', 'workflow_approvers.user_id')
                                ->where('approval_actions.approval_item_id', $approvalItem->id)
                                ->where('workflow_approvers.workflow_id', $workflow->id)
                                ->where('approval_actions.action_type', 'approve')
                                ->select('workflow_approvers.hierarchy_level')
                                ->distinct()
                                ->pluck('hierarchy_level')
                                ->toArray();

                            // Check if all levels have been approved
                            $allLevelsApproved = count($allLevels) === count($actionsWithLevels) &&
                                empty(array_diff($allLevels->toArray(), $actionsWithLevels));

                            if ($allLevelsApproved) {
                                // If workflow is for retirement, process it BEFORE updating to approved
                                if ($approvalItem->workflow_name === 'retire_members' && $approvalItem->member_no) {
                                    Log::info('Processing retirement after final approval', [
                                        'member_no' => $approvalItem->member_no,
                                        'approval_item_id' => $approvalItem->id,
                                        'approved_by' => Auth::id()
                                    ]);

                                    // Get company_id for the member
                                    $member = DB::table('members')
                                        ->where('member_no', $approvalItem->member_no)
                                        ->first();
                                    $memberHost = DB::table('members_host')
                                        ->where('member_no', $approvalItem->member_no)
                                        ->first();

                                    if (!$member && !$memberHost) {
                                        throw new \Exception("Member not found: {$approvalItem->member_no}");
                                    }

                                    $companyId = $member ? $member->company_id : $memberHost->company_id;
                                    $retirementResult = $this->processRetirement($approvalItem->member_no, $companyId);

                                    if (!$retirementResult['success']) {
                                        throw new \Exception('Retirement processing failed: ' . $retirementResult['message']);
                                    } else {
                                        // Update retired status using Eloquent
                                        $eloquentMember = \App\Models\Member::where('member_no', $approvalItem->member_no)->first();
                                        if ($eloquentMember) {
                                            $eloquentMember->retired = 1;
                                            $eloquentMember->save();
                                        }
                                        $eloquentHost = \App\Models\MembersHost::where('member_no', $approvalItem->member_no)->first();
                                        if ($eloquentHost) {
                                            $eloquentHost->retired = 1;
                                            $eloquentHost->save();
                                        }
                                    }

                                    Log::info('Retirement processed successfully', [
                                        'member_no' => $approvalItem->member_no,
                                        'retirement_account' => $retirementResult['retirement_account'] ?? null,
                                        'balances_inserted' => $retirementResult['balances_inserted'] ?? 0
                                    ]);
                                }

                                // Only update to approved if retirement succeeded (or not applicable)
                                $approvalItem->update([
                                    'status' => 'Approved',
                                    'updated_at' => now()
                                ]);
                            }
                        }
                    }

                    // Commit transaction if everything is successful
                    DB::commit();

                    return response()->json(['success' => true, 'message' => 'Action recorded successfully']);
                } catch (\Exception $e) {
                    // Rollback transaction on error
                    DB::rollBack();
                    ErrorDispatcher::dispatch($e);
                    Log::error('Approval action transaction failed: ' . $e->getMessage(), [
                        'user_id' => Auth::id(),
                        'approval_item_id' => $request->approval_item_id,
                        'action_type' => $request->action_type,
                        'trace' => $e->getTraceAsString()
                    ]);

                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to process approval action. Changes have been rolled back.'
                    ], 500);
                }
            } else {
                return response()->json(['success' => false, 'error' => 'No existing approval action found for user'], 400);
            }
        } catch (\Throwable $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Approval action failed: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'error' => 'Failed to record action'], 500);
        }
    }

    public function details($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $approvalItem = ApprovalItem::findOrFail($id);

            // Check if current user has already taken action
            $userAction = ApprovalAction::where('approval_item_id', $approvalItem->id)
                ->where('approver_id', Auth::id())
                ->first();

            $approvalChain = ApprovalAction::where('approval_item_id', $approvalItem->id)
                ->join('workflow_approvers', 'approval_actions.approver_id', '=', 'workflow_approvers.user_id')
                ->join('users', 'approval_actions.approver_id', '=', 'users.id')
                ->select('approval_actions.*', 'users.username as approver_name', 'workflow_approvers.hierarchy_level')
                ->orderBy('workflow_approvers.hierarchy_level')
                ->get();
            return view('approvals.details', [
                'approvalItem' => $approvalItem,
                'approvalChain' => $approvalChain,
                'userAction' => $userAction
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Failed to load approval request details: ' . $e->getMessage());
            return abort(404, 'Approval request not found.');
        }
    }

    public function logs()
    {
        return view('approvals.logs');
    }

    public function logsList(Request $request)
    {
        try {
            $user = Auth::user();

            $draw = $request->get('draw');
            $start = $request->get('start', 0);
            $length = $request->get('length', 10);

            // Fetch all items where user has taken an action
            $query = ApprovalItem::with(['workflow.approvers'])
                ->whereHas('actions', function ($q) use ($user) {
                    $q->where('approver_id', $user->id)
                        ->whereNotNull('action_type');
                });

            $recordsTotal = $query->count();

            $items = $query->skip($start)
                ->take($length)
                ->orderBy('updated_at', 'desc')
                ->get();

            $data = $items->map(function ($item) use ($user) {
                $userAction = $item->actions->where('approver_id', $user->id)->first();

                return [
                    'id' => $item->id,
                    'encrypted_id' => encrypt($item->id),
                    'item_type' => $item->item_type,
                    'member_no' => $item->member_no,
                    'status' => $item->status,
                    'requested_by' => $item->requested_by,
                    'requested_on' => $item->requested_on,
                    'action_type' => $userAction ? $userAction->action_type : null,
                    'action_date' => $userAction ? $userAction->updated_at : null,
                ];
            });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsTotal,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Approval logs list failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'error' => 'Failed to list approval logs'], 500);
        }
    }

    public function logsDetails($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $approvalItem = ApprovalItem::findOrFail($id);

            // Check if current user has acted on this item
            $userAction = ApprovalAction::where('approval_item_id', $approvalItem->id)
                ->where('approver_id', Auth::id())
                ->whereNotNull('action_type')
                ->first();

            if (!$userAction) {
                return abort(403, 'You have not acted on this approval request.');
            }

            $approvalChain = ApprovalAction::where('approval_item_id', $approvalItem->id)
                ->join('workflow_approvers', 'approval_actions.approver_id', '=', 'workflow_approvers.user_id')
                ->join('users', 'approval_actions.approver_id', '=', 'users.id')
                ->select('approval_actions.*', 'users.username as approver_name', 'workflow_approvers.hierarchy_level')
                ->orderBy('workflow_approvers.hierarchy_level')
                ->get();

            return view('approvals.logs_details', [
                'approvalItem' => $approvalItem,
                'approvalChain' => $approvalChain,
                'userAction' => $userAction
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Failed to load approval log details: ' . $e->getMessage());
            return abort(404, 'Approval log not found.');
        }
    }
}
