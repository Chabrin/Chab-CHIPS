<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\ApprovalWorkflow;
use App\Models\WorkflowApprover;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Models\WorkflowApproverChangeLog;

class WorkflowsController extends Controller
{
    /**
     * Display the approval workflows listing page.
     */
    public function index()
    {
        return view('accounting.approvals.index');
    }

    /**
     * Get workflows data for DataTable (AJAX).
     */
    public function getWorkflowsData(Request $request)
    {
        try {
            $workflows = ApprovalWorkflow::with(['addedBy', 'approvers'])
                ->orderByDesc('id')
                ->get();

            $data = $workflows->map(function ($workflow) {
                return [
                    'id' => $workflow->id,
                    'encrypted_id' => Crypt::encrypt($workflow->id),
                    'workflow_name' => $workflow->workflow_name,
                    'description' => $workflow->description ?? '-',
                    'status' => $workflow->status,
                    'status_label' => $workflow->status == 1 ? 'Active' : 'Inactive',
                    'approvers_count' => $workflow->approvers->where('is_active', 1)->count(),
                    'levels_count' => $workflow->approvers->where('is_active', 1)->pluck('approval_level')->unique()->count(),
                    'added_by_name' => $workflow->addedBy->name ?? '-',
                    'added_on' => $workflow->added_on ? $workflow->added_on->format('d-m-Y H:i') : '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching workflows: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workflows',
            ], 500);
        }
    }

    /**
     * Store a new workflow (AJAX).
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'workflow_name' => 'required|string|max:255|unique:approval_workflows,workflow_name',
                'description' => 'nullable|string|max:500',
                'status' => 'nullable|in:0,1',
            ]);

            $workflow = ApprovalWorkflow::create([
                'workflow_name' => $validated['workflow_name'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 1,
                'added_by' => Auth::id(),
                'added_on' => now(),
            ]);

            WorkflowApproverChangeLog::logChange(
                $workflow->id,
                null,
                'WORKFLOW_CREATED',
                'Workflow "' . $workflow->workflow_name . '" created'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Workflow created successfully',
                'data' => [
                    'id' => $workflow->id,
                    'encrypted_id' => Crypt::encrypt($workflow->id),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating workflow: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create workflow: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a workflow (AJAX).
     */
    public function update(Request $request, $encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $workflow = ApprovalWorkflow::find($id);

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], 404);
            }

            $validated = $request->validate([
                'workflow_name' => 'required|string|max:255|unique:approval_workflows,workflow_name,' . $id,
                'description' => 'nullable|string|max:500',
                'status' => 'nullable|in:0,1',
            ]);

            $oldName = $workflow->workflow_name;
            $oldStatus = $workflow->status;

            $workflow->update([
                'workflow_name' => $validated['workflow_name'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? $workflow->status,
            ]);

            $changes = [];
            if ($oldName !== $validated['workflow_name']) {
                $changes[] = "Name changed from '$oldName' to '{$validated['workflow_name']}'";
            }
            if ($oldStatus != ($validated['status'] ?? $oldStatus)) {
                $changes[] = 'Status changed from ' . ($oldStatus == 1 ? 'Active' : 'Inactive') . ' to ' . (($validated['status'] ?? $oldStatus) == 1 ? 'Active' : 'Inactive');
            }

            if (!empty($changes)) {
                WorkflowApproverChangeLog::logChange(
                    $workflow->id,
                    null,
                    'WORKFLOW_UPDATED',
                    implode('; ', $changes)
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Workflow updated successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating workflow: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update workflow',
            ], 500);
        }
    }

    /**
     * Delete a workflow (AJAX).
     */
    public function destroy($encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $workflow = ApprovalWorkflow::find($id);

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], 404);
            }

            $workflowName = $workflow->workflow_name;
            $workflow->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Workflow "' . $workflowName . '" deleted successfully',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting workflow: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete workflow',
            ], 500);
        }
    }

    /**
     * Show workflow details page.
     */
    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $workflow = ApprovalWorkflow::with(['approvers.approver', 'addedBy'])->find($id);

            if (!$workflow) {
                return redirect()->route('accounting.approvals.index')
                    ->with('error', 'Workflow not found');
            }

            return view('accounting.approvals.details', compact('workflow'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading workflow details: ' . $e->getMessage());
            return redirect()->route('accounting.approvals.index')
                ->with('error', 'Failed to load workflow details');
        }
    }

    /**
     * Get workflow approvers (AJAX).
     */
    public function getApprovers($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $approvers = WorkflowApprover::with(['approver', 'addedBy'])
                ->where('workflow_id', $id)
                ->orderBy('approval_level')
                ->orderBy('id')
                ->get();

            $data = $approvers->map(function ($item) {
                return [
                    'id' => $item->id,
                    'encrypted_id' => Crypt::encrypt($item->id),
                    'approver_id' => $item->approver_id,
                    'approver_name' => $item->approver->name ?? '-',
                    'approver_email' => $item->approver->email ?? '-',
                    'approval_level' => $item->approval_level,
                    'is_final' => $item->is_final,
                    'is_active' => $item->is_active,
                    'added_by_name' => $item->addedBy->name ?? '-',
                    'added_on' => $item->added_on ? $item->added_on->format('d-m-Y H:i') : '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching approvers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch approvers',
            ], 500);
        }
    }

    /**
     * Add approver to workflow (AJAX).
     */
    public function addApprover(Request $request, $encryptedWorkflowId)
    {
        DB::beginTransaction();
        try {
            $workflowId = Crypt::decrypt($encryptedWorkflowId);
            $workflow = ApprovalWorkflow::find($workflowId);

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], 404);
            }

            $validated = $request->validate([
                'approver_id' => 'required|exists:josya_users,id',
                'approval_level' => 'required|integer|min:1|max:10',
                'is_final' => 'nullable|in:0,1',
            ]);

            $exists = WorkflowApprover::where('workflow_id', $workflowId)
                ->where('approver_id', $validated['approver_id'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This approver is already added to this workflow',
                ], 422);
            }

            $isFinal = $validated['is_final'] ?? 0;

            if ($isFinal == 1) {
                WorkflowApprover::where('workflow_id', $workflowId)
                    ->where('is_final', 1)
                    ->update(['is_final' => 0]);
            }

            $approver = WorkflowApprover::create([
                'workflow_id' => $workflowId,
                'approver_id' => $validated['approver_id'],
                'approval_level' => $validated['approval_level'],
                'is_final' => $isFinal,
                'is_active' => 1,
                'added_by' => Auth::id(),
                'added_on' => now(),
            ]);

            $user = User::find($validated['approver_id']);
            WorkflowApproverChangeLog::logChange(
                $workflowId,
                $validated['approver_id'],
                'APPROVER_ADDED',
                'Added approver "' . ($user->name ?? 'Unknown') . '" at level ' . $validated['approval_level'],
                null,
                $validated['approval_level'],
                null,
                $isFinal
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Approver added successfully',
                'data' => [
                    'id' => $approver->id,
                    'encrypted_id' => Crypt::encrypt($approver->id),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error adding approver: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add approver: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update approver (AJAX).
     */
    public function updateApprover(Request $request, $encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $approver = WorkflowApprover::with('approver')->find($id);

            if (!$approver) {
                return response()->json([
                    'success' => false,
                    'message' => 'Approver not found',
                ], 404);
            }

            $validated = $request->validate([
                'approval_level' => 'required|integer|min:1|max:10',
                'is_final' => 'nullable|in:0,1',
                'is_active' => 'nullable|in:0,1',
            ]);

            $oldLevel = $approver->approval_level;
            $oldIsFinal = $approver->is_final;
            $oldIsActive = $approver->is_active;

            $isFinal = $validated['is_final'] ?? 0;
            $isActive = $validated['is_active'] ?? $approver->is_active;

            if ($isFinal == 1 && $oldIsFinal != 1) {
                WorkflowApprover::where('workflow_id', $approver->workflow_id)
                    ->where('is_final', 1)
                    ->where('id', '!=', $id)
                    ->update(['is_final' => 0]);
            }

            $approver->update([
                'approval_level' => $validated['approval_level'],
                'is_final' => $isFinal,
                'is_active' => $isActive,
            ]);

            $changes = [];
            if ($oldLevel != $validated['approval_level']) {
                $changes[] = "Level changed from $oldLevel to {$validated['approval_level']}";
            }
            if ($oldIsFinal != $isFinal) {
                $changes[] = 'Is Final changed from ' . ($oldIsFinal ? 'Yes' : 'No') . ' to ' . ($isFinal ? 'Yes' : 'No');
            }
            if ($oldIsActive != $isActive) {
                $changes[] = 'Status changed from ' . ($oldIsActive ? 'Active' : 'Inactive') . ' to ' . ($isActive ? 'Active' : 'Inactive');
            }

            if (!empty($changes)) {
                WorkflowApproverChangeLog::logChange(
                    $approver->workflow_id,
                    $approver->approver_id,
                    'APPROVER_UPDATED',
                    'Updated approver "' . ($approver->approver->name ?? 'Unknown') . '": ' . implode('; ', $changes),
                    $oldLevel,
                    $validated['approval_level'],
                    $oldIsFinal,
                    $isFinal
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Approver updated successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating approver: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update approver',
            ], 500);
        }
    }

    /**
     * Remove approver from workflow (AJAX).
     */
    public function removeApprover($encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $approver = WorkflowApprover::with('approver')->find($id);

            if (!$approver) {
                return response()->json([
                    'success' => false,
                    'message' => 'Approver not found',
                ], 404);
            }

            $workflowId = $approver->workflow_id;
            $approverId = $approver->approver_id;
            $approverName = $approver->approver->name ?? 'Unknown';
            $level = $approver->approval_level;

            $approver->delete();

            WorkflowApproverChangeLog::logChange(
                $workflowId,
                $approverId,
                'APPROVER_REMOVED',
                'Removed approver "' . $approverName . '" from level ' . $level,
                $level,
                null
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Approver removed successfully',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error removing approver: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove approver',
            ], 500);
        }
    }

    /**
     * Get change logs for a workflow (AJAX).
     */
    public function getChangeLogs($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $logs = WorkflowApproverChangeLog::with(['approver', 'changedBy'])
                ->where('workflow_id', $id)
                ->orderByDesc('changed_on')
                ->limit(100)
                ->get();

            $data = $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'approver_name' => $log->approver->name ?? '-',
                    'old_level' => $log->old_level,
                    'new_level' => $log->new_level,
                    'details' => $log->details,
                    'changed_by_name' => $log->changedBy->name ?? '-',
                    'changed_on' => $log->changed_on ? $log->changed_on->format('d-m-Y H:i') : '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching change logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch change logs',
            ], 500);
        }
    }

    /**
     * Search users for approver selection (AJAX).
     */
    public function searchUsers(Request $request)
    {
        try {
            $search = $request->input('search', '');

            $query = User::select(['id', 'name', 'email'])
                ->where('current_status_id', 1);

            if (strlen($search) >= 2) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%");
                });
            }

            $users = $query->orderBy('name')->limit(50)->get();

            return response()->json([
                'success' => true,
                'data' => $users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'text' => $user->name . ' (' . $user->email . ')',
                    ];
                }),
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching users: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to search users',
            ], 500);
        }
    }
}
