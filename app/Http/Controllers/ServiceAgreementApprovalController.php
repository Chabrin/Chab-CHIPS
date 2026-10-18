<?php

namespace App\Http\Controllers;

use App\Services\ClientAccountService;
use App\Services\ServiceAgreementApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ServiceAgreementApprovalController extends Controller
{
    protected ServiceAgreementApprovalService $approvals;

    /**
     * Inject the SLA approval service.
     */
    public function __construct(ServiceAgreementApprovalService $approvals) {
        $this->approvals = $approvals;
    }

    /**
     * Show the Approve SLA page.
     */
    public function index() {
        if (!$this->canApprove()) {
            return redirect('/dashboard')->with('error', 'You do not have permission to approve service agreements.');
        }

        return view('approvals.sla.index', [
            'counts' => $this->approvals->statusCounts(),
            'options' => $this->approvals->filterOptions(),
            'registration' => $this->approvals->registrationOptions(),
        ]);
    }

    /**
     * Server-side data for the SLA table.
     */
    public function list(Request $request): JsonResponse {
        if (!$this->canApprove()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $length = min(max((int) $request->input('length', 25), 1), 100);
        $start = max((int) $request->input('start', 0), 0);

        $result = $this->approvals->paginate(
            $request->only(['status', 'search', 'zone_id', 'created_by', 'date_from', 'date_to']),
            $start,
            $length,
            (string) $request->input('order_key', 'created'),
            (string) $request->input('order_dir', 'desc')
        );

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data' => $result['rows'],
            'counts' => $this->approvals->statusCounts(),
        ]);
    }

    /**
     * Client, property and SLA details for the review modal.
     */
    public function show(int $id): JsonResponse {
        if (!$this->canApprove()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $details = $this->approvals->details($id);

        return $details
            ? response()->json(['success' => true, 'data' => $details])
            : response()->json(['success' => false, 'error' => 'Service agreement not found.'], 404);
    }

    /**
     * Approve one or more SLAs.
     */
    public function approve(Request $request): JsonResponse {
        if (!$this->canApprove()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'paid' => 'nullable|boolean',
            'payment_method' => ['required_if:paid,1', 'nullable', Rule::in(array_keys(ClientAccountService::PAYMENT_METHODS))],
            'payment_reference' => 'required_if:paid,1|nullable|string|max:100',
        ]);

        $payment = null;
        if (!empty($validated['paid'])) {
            if (count($validated['ids']) > 1) {
                return response()->json(['success' => false, 'error' => 'Settle the registration fee on one service agreement at a time.'], 422);
            }
            $payment = ['method' => $validated['payment_method'], 'reference' => trim($validated['payment_reference'])];
        }

        try {
            $result = $this->approvals->approve($validated['ids'], (int) Auth::id(), $payment);

            return response()->json($this->actionResponse($result, 'approved'));
        } catch (\Throwable $e) {
            Log::error('Error approving service agreements: ' . $e->getMessage());

            return response()->json(['success' => false, 'error' => 'Failed to approve. Nothing was changed.'], 500);
        }
    }

    /**
     * Reject one or more SLAs. A reason is required.
     */
    public function reject(Request $request): JsonResponse {
        if (!$this->canApprove()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'rejection_reason' => 'required|string|max:1000',
        ]);

        try {
            $result = $this->approvals->reject($validated['ids'], $validated['rejection_reason'], (int) Auth::id());

            return response()->json($this->actionResponse($result, 'rejected'));
        } catch (\Throwable $e) {
            Log::error('Error rejecting service agreements: ' . $e->getMessage());

            return response()->json(['success' => false, 'error' => 'Failed to reject. Nothing was changed.'], 500);
        }
    }

    /**
     * Build the JSON reply for an approve or reject action.
     *
     * @param array $result
     * @param string $verb
     */
    protected function actionResponse(array $result, string $verb): array {
        $count = count($result['done']);
        $message = $count . ' service agreement' . ($count === 1 ? '' : 's') . ' ' . $verb . '.';

        $posted = array_count_values($result['posted'] ?? []);
        if (!empty($posted[ClientAccountService::RESULT_CHARGED])) {
            $message .= ' Registration fee charged on ' . $posted[ClientAccountService::RESULT_CHARGED] . '.';
        }
        if (!empty($posted[ClientAccountService::RESULT_SETTLED])) {
            $message .= ' Registration fee charged and paid on ' . $posted[ClientAccountService::RESULT_SETTLED] . '.';
        }
        if (!empty($posted[ClientAccountService::RESULT_OPENED])) {
            $message .= ' Client account opened on ' . $posted[ClientAccountService::RESULT_OPENED] . '.';
        }

        $reasons = [];
        foreach (array_count_values(array_values($result['skipped'])) as $reason => $n) {
            $reasons[] = $n . ' x ' . $reason;
        }
        if ($reasons) {
            $message .= ' Skipped ' . count($result['skipped']) . ': ' . implode(' | ', array_slice($reasons, 0, 2)) . (count($reasons) > 2 ? ' ...' : '');
        }

        return [
            'success' => $count > 0,
            'message' => $message,
            'error' => $count > 0 ? null : ($reasons ? 'Not ' . $verb . '. ' . $reasons[0] : 'Nothing to do: the selected items are not pending.'),
            'skipped' => $result['skipped'],
            'skipped_items' => collect($result['skipped'])->map(fn($reason, $id) => ['label' => $result['labels'][$id] ?? ('#' . $id), 'reason' => $reason])->values()->all(),
            'flipped' => $result['flipped'],
        ];
    }

    /**
     * Whether the signed-in user may approve service agreements.
     */
    protected function canApprove(): bool {
        $user = Auth::user();

        return $user && $user->hasPermission('update_service_agreements');
    }
}
