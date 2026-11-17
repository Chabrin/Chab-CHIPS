<?php

namespace App\Http\Controllers;

use App\Models\PerformanceMeeting;
use App\Models\PerformanceReview;
use App\Services\Performance\PerformanceAccess;
use App\Services\Performance\PerformanceMeetingPresenter;
use App\Services\Performance\PerformanceMeetingService;
use App\Traits\RespondsToPerformanceErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * HR > Performance Review > Meetings, and the page of one review's joint review meeting. HR schedules it and picks the
 * committee; the supervisor (or HR, when there is no supervisor) records it. What a person can open and do is decided here,
 * on the server, by PerformanceAccess for every request, never by the menu.
 */
class PerformanceMeetingController extends Controller
{
    use RespondsToPerformanceErrors;

    public function __construct(
        private PerformanceAccess $access,
        private PerformanceMeetingPresenter $presenter,
        private PerformanceMeetingService $service,
    ) {}

    /** The Meetings list: what the person is invited to, and for HR what still has to be scheduled. */
    public function index()
    {
        return view('hr.performance.meetings');
    }

    public function list(): JsonResponse
    {
        return response()->json(['success' => true, 'meetings' => $this->presenter->meetingRows(Auth::user())]);
    }

    /** One review's joint review meeting. */
    public function show(int $id)
    {
        $review = $this->find($id);
        $this->authorise($review, 'view');

        return view('hr.performance.meeting', ['page' => $this->presenter->page(Auth::user(), $review, PerformanceMeeting::KIND_REVIEW)]);
    }

    public function data(int $id, string $kind): JsonResponse
    {
        $review = $this->find($id);
        $this->authorise($review, 'view');
        abort_unless(in_array($kind, [PerformanceMeeting::KIND_REVIEW, PerformanceMeeting::KIND_APPEAL], true), 404);

        return response()->json(['success' => true, 'page' => $this->presenter->page(Auth::user(), $review, $kind)]);
    }

    public function schedule(Request $request, int $id, string $kind): JsonResponse
    {
        return $this->act($id, $kind, fn (PerformanceReview $review) => $this->service->schedule($review, Auth::user(), $kind, $this->plan($request)));
    }

    public function reschedule(Request $request, int $id, string $kind): JsonResponse
    {
        return $this->act($id, $kind, fn (PerformanceReview $review) => $this->service->reschedule($review, Auth::user(), $kind, $this->plan($request), (string) $this->text($request, 'reason')));
    }

    /** Records the joint review meeting: who came, the four areas, the extra items. */
    public function record(Request $request, int $id): JsonResponse
    {
        return $this->act($id, PerformanceMeeting::KIND_REVIEW, fn (PerformanceReview $review) => $this->service->record(
            $review, Auth::user(),
            (array) $request->input('attendance', []),
            (array) $request->input('sections', []),
            (array) $request->input('extras', [])
        ));
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function find(int $id): PerformanceReview
    {
        return PerformanceReview::findOrFail($id);
    }

    private function authorise(PerformanceReview $review, string $action): void
    {
        $denial = $this->access->denial(Auth::user(), $review, $action);
        abort_if($denial !== null, $denial['status'] ?? 403, $denial['error'] ?? 'You do not have access to this review.');
    }

    /** Runs one change and answers with the page as this person may see it. */
    private function act(int $id, string $kind, callable $do): JsonResponse
    {
        abort_unless(in_array($kind, [PerformanceMeeting::KIND_REVIEW, PerformanceMeeting::KIND_APPEAL], true), 404);
        $review = $this->find($id);

        // Someone with no part in the review is told 403 without learning anything about it
        if ($this->access->denial(Auth::user(), $review, 'view') !== null) {
            return response()->json(['success' => false, 'error' => 'You do not have access to this review.'], 403);
        }

        return $this->attempt(function () use ($review, $kind, $do) {
            $do($review);

            return response()->json(['success' => true, 'message' => 'Saved.', 'page' => $this->presenter->page(Auth::user(), $review->refresh(), $kind)]);
        });
    }

    /** What HR chose for the meeting, in the shape the service wants. */
    private function plan(Request $request): array
    {
        return [
            'scheduled_at' => $this->text($request, 'scheduled_at'),
            'venue' => $this->text($request, 'venue'),
            'hr_user_id' => $request->input('hr_user_id'),
            'committee_staff_ids' => (array) $request->input('committee_staff_ids', []),
        ];
    }

    private function text(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : null;
    }
}
