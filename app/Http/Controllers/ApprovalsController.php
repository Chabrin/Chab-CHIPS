<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\ApprovalWorkflow;
use App\Models\WorkflowApprover;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ApprovalsController extends Controller
{
    /**
     * Display the approval workflows index page.
     */
    public function index()
    {
        return view('approvals.workflows');
    }

    /**
     * Get list of approval workflows for DataTable.
     */
    public function list()
    {
        try {
            $workflows = ApprovalWorkflow::with(['addedBy', 'deactivatedBy'])
                ->select('id', 'workflow_name', 'slug', 'description', 'active', 'added_by', 'deactivated_by', 'created_at', 'deactivated_at')
                ->get()
                ->map(function ($workflow) {
                    return [
                        'id' => $workflow->id,
                        'encrypted_id' => encrypt($workflow->id),
                        'workflow_name' => $workflow->workflow_name,
                        'slug' => $workflow->slug,
                        'description' => $workflow->description,
                        'active' => $workflow->active,
                        'status' => $workflow->active ? 'Active' : 'Inactive',
                        'added_by' => $workflow->addedBy ? $workflow->addedBy->username : 'System',
                        'deactivated_by' => $workflow->deactivatedBy ? $workflow->deactivatedBy->username : null,
                        'created_at' => $workflow->created_at ? $workflow->created_at->format('Y-m-d H:i:s') : null,
                        'deactivated_at' => $workflow->deactivated_at ? $workflow->deactivated_at->format('Y-m-d H:i:s') : null,
                    ];
                });
            return response()->json(['workflows' => $workflows]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading approval workflows: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load approval workflows'], 500);
        }
    }

    /**
     * Store a new approval workflow.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'workflow_name' => 'required|string|max:255|unique:approval_workflows,workflow_name',
                'description' => 'nullable|string|max:1000',
                'active' => 'nullable|integer|in:0,1',
            ]);

            // Generate slug from workflow name
            $slug = ApprovalWorkflow::generateSlug($request->workflow_name);

            // Check if slug already exists
            $slugExists = ApprovalWorkflow::where('slug', $slug)->exists();
            if ($slugExists) {
                return response()->json(['success' => false, 'error' => 'A workflow with this name already exists (slug conflict)'], 422);
            }

            $workflow = new ApprovalWorkflow();
            $workflow->workflow_name = $request->workflow_name;
            $workflow->slug = $slug;
            $workflow->description = $request->description;
            $workflow->active = $request->has('active') ? (int)$request->active : 1;
            $workflow->added_by = Auth::id();
            $workflow->save();

            return response()->json(['success' => true, 'message' => 'Approval workflow created successfully']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating approval workflow: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to create approval workflow'], 500);
        }
    }

    /**
     * Update an existing approval workflow.
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'workflow_name' => 'required|string|max:255|unique:approval_workflows,workflow_name,' . $id,
                'description' => 'nullable|string|max:1000',
                'active' => 'nullable|integer|in:0,1',
            ]);

            $workflow = ApprovalWorkflow::findOrFail($id);

            // Generate new slug if workflow name changed
            $newSlug = ApprovalWorkflow::generateSlug($request->workflow_name);
            if ($workflow->slug !== $newSlug) {
                // Check if new slug already exists
                $slugExists = ApprovalWorkflow::where('slug', $newSlug)->where('id', '!=', $id)->exists();
                if ($slugExists) {
                    return response()->json(['success' => false, 'error' => 'A workflow with this name already exists (slug conflict)'], 422);
                }
                $workflow->slug = $newSlug;
            }

            $workflow->workflow_name = $request->workflow_name;
            $workflow->description = $request->description;

            // Handle status change
            $newActive = $request->has('active') ? (int)$request->active : 1;
            if ($workflow->active == 1 && $newActive == 0) {
                // Deactivating workflow
                $workflow->deactivated_by = Auth::id();
                $workflow->deactivated_at = now();
            } elseif ($workflow->active == 0 && $newActive == 1) {
                // Reactivating workflow
                $workflow->deactivated_by = null;
                $workflow->deactivated_at = null;
            }

            $workflow->active = $newActive;
            $workflow->save();

            return response()->json(['success' => true, 'message' => 'Approval workflow updated successfully']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating approval workflow: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update approval workflow'], 500);
        }
    }

    /**
     * Delete an approval workflow (soft delete).
     */
    public function destroy($id)
    {
        try {
            $workflow = ApprovalWorkflow::findOrFail($id);
            $workflow->delete();

            return response()->json(['success' => true, 'message' => 'Approval workflow deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting approval workflow: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to delete approval workflow'], 500);
        }
    }

    /**
     * Show workflow management page.
     */
    public function manage($encryptedId)
    {
        try {
            $workflowId = decrypt($encryptedId);
            $workflow = ApprovalWorkflow::with(['approvers.user', 'approvers.role'])->findOrFail($workflowId);

            return view('approvals.manage', compact('workflow'));
        } catch (\Exception $e) {
            return redirect()->route('approval.workflows.index')->with('error', 'Workflow not found');
        }
    }

    /**
     * Get workflow approvers for DataTable.
     */
    public function getApprovers($encryptedId)
    {
        try {
            $workflowId = decrypt($encryptedId);
            $approvers = WorkflowApprover::with(['user.employee', 'role', 'addedBy'])
                ->where('workflow_id', $workflowId)
                ->orderBy('hierarchy_level')
                ->orderBy('sort_order')
                ->get()
                ->map(function ($approver) {
                    // Build approver name based on type
                    $approverName = 'Unknown';
                    if ($approver->approver_type === 'user' && $approver->user) {
                        if ($approver->user->employee) {
                            $employee = $approver->user->employee;
                            $nameParts = array_filter([
                                $employee->first_name,
                                $employee->other_name,
                                $employee->surname
                            ]);
                            $approverName = implode(' ', $nameParts) ?: $approver->user->username;
                        } else {
                            $approverName = $approver->user->username;
                        }
                    } elseif ($approver->approver_type === 'role' && $approver->role) {
                        $approverName = $approver->role->display_name ?? $approver->role->name;
                    }

                    return [
                        'id' => $approver->id,
                        'approver_type' => $approver->approver_type,
                        'approver_name' => $approverName,
                        'hierarchy_level' => $approver->hierarchy_level,
                        'sort_order' => $approver->sort_order,
                        'is_required' => $approver->is_required,
                        'active' => $approver->active,
                        'notes' => $approver->notes,
                        'added_by' => $approver->addedBy ? $approver->addedBy->username : 'System',
                        'created_at' => $approver->created_at ? $approver->created_at->format('Y-m-d H:i:s') : null,
                    ];
                });

            return response()->json(['success' => true, 'approvers' => $approvers]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching workflow approvers: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to fetch approvers'], 500);
        }
    }

    /**
     * Get available users and roles for adding approvers.
     */
    public function getAvailableApprovers($encryptedId)
    {
        try {
            $workflowId = decrypt($encryptedId);

            // Get users with employee details and roles
            $users = User::with('roles')
                ->select('users.id', 'users.username', 'employees.first_name', 'employees.surname', 'employees.other_name')
                ->leftJoin('employees', 'users.employee_id', '=', 'employees.id')
                ->where('users.is_active', 1)
                ->where('users.user_activated', 1)
                ->orderBy('employees.first_name')
                ->orderBy('employees.surname')
                ->get()
                ->map(function ($user) {
                    // Build full name from employee details
                    $fullName = '';
                    if ($user->first_name || $user->surname) {
                        $nameParts = array_filter([
                            $user->first_name,
                            $user->other_name,
                            $user->surname
                        ]);
                        $fullName = implode(' ', $nameParts);
                    }

                    $displayName = $fullName ?: $user->username;

                    // Add roles in brackets if user has roles
                    if ($user->roles && $user->roles->isNotEmpty()) {
                        $roleNames = $user->roles->pluck('display_name')->filter()->toArray();
                        if (empty($roleNames)) {
                            $roleNames = $user->roles->pluck('name')->toArray();
                        }
                        if (!empty($roleNames)) {
                            $displayName .= ' (' . implode(', ', $roleNames) . ')';
                        }
                    }

                    return [
                        'id' => $user->id,
                        'name' => $displayName,
                        'type' => 'user'
                    ];
                });

            // Get roles
            $roles = Role::select('id', 'name', 'display_name')
                ->orderBy('display_name')
                ->get()
                ->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->display_name ?? $role->name,
                        'type' => 'role'
                    ];
                });

            return response()->json([
                'success' => true,
                'users' => $users,
                'roles' => $roles
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching available approvers: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to fetch available approvers'], 500);
        }
    }

    /**
     * Add a new approver to the workflow.
     */
    public function addApprover(Request $request, $encryptedId)
    {
        try {
            $workflowId = decrypt($encryptedId);

            $request->validate([
                'approver_type' => 'required|in:user,role',
                'approver_id' => 'required|integer',
                'hierarchy_level' => 'required|integer|min:1|max:5',
                'is_required' => 'nullable|boolean',
                'notes' => 'nullable|string|max:500'
            ]);

            // Check if approver already exists anywhere in this workflow
            $duplicateQuery = WorkflowApprover::where('workflow_id', $workflowId);

            if ($request->approver_type === 'user') {
                $duplicateQuery->where('user_id', $request->approver_id);
            } else {
                $duplicateQuery->where('role_id', $request->approver_id);
            }

            if ($duplicateQuery->exists()) {
                $existingApprover = $duplicateQuery->first();
                return response()->json([
                    'success' => false,
                    'error' => 'This approver is already assigned to Level ' . $existingApprover->hierarchy_level . ' in this workflow'
                ], 422);
            }

            // Check if the hierarchy level already has an approver (only 1 per level)
            $levelExistsQuery = WorkflowApprover::where('workflow_id', $workflowId)
                ->where('hierarchy_level', $request->hierarchy_level);

            if ($levelExistsQuery->exists()) {
                $existingLevelApprover = $levelExistsQuery->first();
                $approverName = 'Unknown';

                if ($existingLevelApprover->approver_type === 'user' && $existingLevelApprover->user) {
                    if ($existingLevelApprover->user->employee) {
                        $employee = $existingLevelApprover->user->employee;
                        $nameParts = array_filter([
                            $employee->first_name,
                            $employee->other_name,
                            $employee->surname
                        ]);
                        $approverName = implode(' ', $nameParts) ?: $existingLevelApprover->user->username;
                    } else {
                        $approverName = $existingLevelApprover->user->username;
                    }
                } elseif ($existingLevelApprover->approver_type === 'role' && $existingLevelApprover->role) {
                    $approverName = $existingLevelApprover->role->display_name ?? $existingLevelApprover->role->name;
                }

                return response()->json([
                    'success' => false,
                    'error' => 'Level ' . $request->hierarchy_level . ' already has an approver: ' . $approverName . '. Only one approver per level is allowed.'
                ], 422);
            }

            // Check if lower hierarchy levels exist (sequential level validation)
            $requestedLevel = $request->hierarchy_level;
            if ($requestedLevel > 1) {
                // Get existing levels for this workflow
                $existingLevels = WorkflowApprover::where('workflow_id', $workflowId)
                    ->distinct()
                    ->pluck('hierarchy_level')
                    ->sort()
                    ->values();

                // Check if all previous levels exist
                for ($level = 1; $level < $requestedLevel; $level++) {
                    if (!$existingLevels->contains($level)) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Cannot add Level ' . $requestedLevel . '. Please add Level ' . $level . ' first.'
                        ], 422);
                    }
                }
            }

            // Auto-assign sort order based on hierarchy level
            $sortOrder = WorkflowApprover::where('workflow_id', $workflowId)
                ->where('hierarchy_level', $request->hierarchy_level)
                ->max('sort_order') + 1;

            $approver = new WorkflowApprover();
            $approver->workflow_id = $workflowId;
            $approver->approver_type = $request->approver_type;
            $approver->hierarchy_level = $request->hierarchy_level;
            $approver->sort_order = $sortOrder;
            $approver->is_required = $request->boolean('is_required', true);
            $approver->notes = $request->notes;
            $approver->added_by = Auth::id();

            if ($request->approver_type === 'user') {
                $approver->user_id = $request->approver_id;
            } else {
                $approver->role_id = $request->approver_id;
            }

            $approver->save();

            return response()->json([
                'success' => true,
                'message' => 'Approver added successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error adding workflow approver: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to add approver'], 500);
        }
    }

    /**
     * Remove an approver from the workflow.
     */
    public function removeApprover($encryptedId, $approverId)
    {
        try {
            $workflowId = decrypt($encryptedId);

            $approver = WorkflowApprover::where('workflow_id', $workflowId)
                ->findOrFail($approverId);

            $approver->delete();

            return response()->json([
                'success' => true,
                'message' => 'Approver removed successfully'
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error removing workflow approver: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to remove approver'], 500);
        }
    }

    /**
     * Update approver details.
     */
    public function updateApprover(Request $request, $encryptedId, $approverId)
    {
        try {
            $workflowId = decrypt($encryptedId);

            $request->validate([
                'hierarchy_level' => 'required|integer|min:1|max:5',
                'is_required' => 'nullable|boolean',
                'notes' => 'nullable|string|max:500'
            ]);

            $approver = WorkflowApprover::where('workflow_id', $workflowId)
                ->findOrFail($approverId);

            // Check sequential level validation if level is being changed
            $requestedLevel = $request->hierarchy_level;
            if ($approver->hierarchy_level != $requestedLevel && $requestedLevel > 1) {
                // Get existing levels for this workflow (excluding current approver)
                $existingLevels = WorkflowApprover::where('workflow_id', $workflowId)
                    ->where('id', '!=', $approverId)
                    ->distinct()
                    ->pluck('hierarchy_level')
                    ->sort()
                    ->values();

                // Check if all previous levels exist
                for ($level = 1; $level < $requestedLevel; $level++) {
                    if (!$existingLevels->contains($level)) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Cannot change to Level ' . $requestedLevel . '. Please ensure Level ' . $level . ' exists first.'
                        ], 422);
                    }
                }
            }

            // Check if the hierarchy level already has another approver (only 1 per level)
            if ($approver->hierarchy_level != $requestedLevel) {
                $levelExistsQuery = WorkflowApprover::where('workflow_id', $workflowId)
                    ->where('hierarchy_level', $requestedLevel)
                    ->where('id', '!=', $approverId);

                if ($levelExistsQuery->exists()) {
                    $existingLevelApprover = $levelExistsQuery->first();
                    $approverName = 'Unknown';

                    if ($existingLevelApprover->approver_type === 'user' && $existingLevelApprover->user) {
                        if ($existingLevelApprover->user->employee) {
                            $employee = $existingLevelApprover->user->employee;
                            $nameParts = array_filter([
                                $employee->first_name,
                                $employee->other_name,
                                $employee->surname
                            ]);
                            $approverName = implode(' ', $nameParts) ?: $existingLevelApprover->user->username;
                        } else {
                            $approverName = $existingLevelApprover->user->username;
                        }
                    } elseif ($existingLevelApprover->approver_type === 'role' && $existingLevelApprover->role) {
                        $approverName = $existingLevelApprover->role->display_name ?? $existingLevelApprover->role->name;
                    }

                    return response()->json([
                        'success' => false,
                        'error' => 'Level ' . $requestedLevel . ' already has an approver: ' . $approverName . '. Only one approver per level is allowed.'
                    ], 422);
                }
            }

            // Auto-assign sort order based on hierarchy level if level changed
            if ($approver->hierarchy_level != $request->hierarchy_level) {
                $sortOrder = WorkflowApprover::where('workflow_id', $workflowId)
                    ->where('hierarchy_level', $request->hierarchy_level)
                    ->max('sort_order') + 1;
                $approver->sort_order = $sortOrder;
            }

            $approver->hierarchy_level = $request->hierarchy_level;
            $approver->is_required = $request->boolean('is_required', true);
            $approver->notes = $request->notes;
            $approver->save();

            return response()->json([
                'success' => true,
                'message' => 'Approver updated successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating workflow approver: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update approver'], 500);
        }
    }
}
