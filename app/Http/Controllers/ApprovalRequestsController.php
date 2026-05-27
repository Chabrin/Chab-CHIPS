<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\ErrorDispatcher;
use App\Services\ApprovalService;
use App\Models\ApprovalRequest;

class ApprovalRequestsController extends Controller
{
    protected ApprovalService $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * Display the pending approvals page.
     */
    public function index()
    {
        try {
            return view('accounting.approvals.pending');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading pending approvals page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load pending approvals page');
        }
    }

    /**
     * Display approval request detail page.
     */
    public function show($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $approvalRequest = ApprovalRequest::with(['workflow', 'initiator'])->findOrFail($id);
            $details = $this->approvalService->getRequestDetails($approvalRequest);

            $canApprove = $approvalRequest->canUserApprove(Auth::id());
            $canResubmit = $approvalRequest->status === ApprovalRequest::STATUS_CHANGES_REQUESTED
                && $approvalRequest->initiated_by === Auth::id();

            return view('accounting.approvals.request-details', [
                'approvalRequest' => $approvalRequest,
                'details' => $details,
                'canApprove' => $canApprove,
                'canResubmit' => $canResubmit,
                'encryptedId' => $encryptedId,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading approval request details: ' . $e->getMessage());
            return redirect()->route('accounting.pending.approvals')->with('error', 'Failed to load approval details');
        }
    }

    /**
     * Get pending approvals for current user (AJAX).
     */
    public function getPendingApprovals()
    {
        try {
            $userId = Auth::id();
            $pending = $this->approvalService->getPendingForUser($userId);

            return response()->json([
                'success' => true,
                'count' => count($pending),
                'data' => $pending,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching pending approvals: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch pending approvals'], 500);
        }
    }

    /**
     * Get historical approvals for current user (AJAX).
     */
    public function getHistoricalApprovals()
    {
        try {
            $userId = Auth::id();
            $historical = $this->approvalService->getHistoricalForUser($userId);

            return response()->json([
                'success' => true,
                'count' => count($historical),
                'data' => $historical,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching historical approvals: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch historical approvals'], 500);
        }
    }

    /**
     * Get approval request details (AJAX).
     */
    public function getRequestDetails($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $request = ApprovalRequest::findOrFail($id);
            $details = $this->approvalService->getRequestDetails($request);

            $canApprove = $request->canUserApprove(Auth::id());
            $canResubmit = $request->status === ApprovalRequest::STATUS_CHANGES_REQUESTED
                && $request->initiated_by === Auth::id();

            return response()->json([
                'success' => true,
                'data' => array_merge($details, [
                    'can_approve' => $canApprove,
                    'can_resubmit' => $canResubmit,
                    'is_initiator' => $request->initiated_by === Auth::id(),
                ]),
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching approval request details: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch approval details'], 500);
        }
    }

    /**
     * Get approval timeline (AJAX).
     */
    public function getTimeline($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $request = ApprovalRequest::findOrFail($id);
            $timeline = $this->approvalService->getTimeline($request);

            return response()->json([
                'success' => true,
                'count' => count($timeline),
                'data' => $timeline,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching approval timeline: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch timeline'], 500);
        }
    }

    /**
     * Approve request (AJAX).
     */
    public function approve(Request $request, $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $approvalRequest = ApprovalRequest::findOrFail($id);

            $validated = $request->validate([
                'comment' => ['nullable', 'string', 'max:1000'],
            ]);

            $result = $this->approvalService->approve(
                $approvalRequest,
                Auth::id(),
                $validated['comment'] ?? null
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error approving request: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to approve request'], 500);
        }
    }

    /**
     * Reject request (AJAX).
     */
    public function reject(Request $request, $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $approvalRequest = ApprovalRequest::findOrFail($id);

            $validated = $request->validate([
                'comment' => ['required', 'string', 'max:1000'],
            ]);

            $result = $this->approvalService->reject(
                $approvalRequest,
                Auth::id(),
                $validated['comment']
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error rejecting request: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to reject request'], 500);
        }
    }

    /**
     * Request changes (AJAX).
     */
    public function requestChanges(Request $request, $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $approvalRequest = ApprovalRequest::findOrFail($id);

            $validated = $request->validate([
                'comment' => ['required', 'string', 'max:1000'],
            ]);

            $result = $this->approvalService->requestChanges(
                $approvalRequest,
                Auth::id(),
                $validated['comment']
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error requesting changes: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to request changes'], 500);
        }
    }

    /**
     * Return to previous level (AJAX).
     */
    public function returnToPrevious(Request $request, $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $approvalRequest = ApprovalRequest::findOrFail($id);

            $validated = $request->validate([
                'target_level' => ['required', 'integer', 'min:1'],
                'comment' => ['nullable', 'string', 'max:1000'],
            ]);

            $result = $this->approvalService->returnToPreviousLevel(
                $approvalRequest,
                Auth::id(),
                $validated['target_level'],
                $validated['comment'] ?? null
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error returning request: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to return request'], 500);
        }
    }

    /**
     * Resubmit after changes (AJAX).
     */
    public function resubmit(Request $request, $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $approvalRequest = ApprovalRequest::findOrFail($id);

            $validated = $request->validate([
                'comment' => ['nullable', 'string', 'max:1000'],
            ]);

            $result = $this->approvalService->resubmit(
                $approvalRequest,
                Auth::id(),
                $validated['comment'] ?? null
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error resubmitting request: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to resubmit request'], 500);
        }
    }

    /**
     * Get approval request for a specific item (AJAX).
     */
    public function getForItem(Request $request)
    {
        try {
            $validated = $request->validate([
                'request_type' => ['required', 'string'],
                'request_id' => ['required', 'integer'],
            ]);

            $approvalRequest = ApprovalRequest::forRequest(
                $validated['request_type'],
                $validated['request_id']
            )->first();

            if (!$approvalRequest) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'has_approval' => false,
                ]);
            }

            $details = $this->approvalService->getRequestDetails($approvalRequest);
            $canApprove = $approvalRequest->canUserApprove(Auth::id());
            $canResubmit = $approvalRequest->status === ApprovalRequest::STATUS_CHANGES_REQUESTED
                && $approvalRequest->initiated_by === Auth::id();

            return response()->json([
                'success' => true,
                'has_approval' => true,
                'encrypted_id' => encrypt($approvalRequest->id),
                'data' => array_merge($details, [
                    'can_approve' => $canApprove,
                    'can_resubmit' => $canResubmit,
                    'is_initiator' => $approvalRequest->initiated_by === Auth::id(),
                ]),
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching approval for item: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch approval info'], 500);
        }
    }
}
