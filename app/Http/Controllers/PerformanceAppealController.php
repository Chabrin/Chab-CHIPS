<?php

namespace App\Http\Controllers;

use App\Models\PerformanceMeeting;
use App\Models\PerformanceReview;
use App\Services\Performance\PerformanceAccess;
use App\Services\Performance\PerformanceAppealService;
use App\Services\Performance\PerformanceMeetingPresenter;
use App\Traits\RespondsToPerformanceErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * HR > Performance Review > Appeals, and the page of one review's appeal. The staff member appeals a finalised review (HR can
 * do it for them), HR schedules the hearing with the committee (the meeting routes do that), and HR records the committee's
 * decision. Every request is checked by PerformanceAccess on the server.
 */
class PerformanceAppealController extends Controller
{
    use RespondsToPerformanceErrors;

    public function __construct(
        private PerformanceAccess $access,
        private PerformanceMeetingPresenter $presenter,
        private PerformanceAppealService $service,
    ) {}

    /** The Appeals list, for HR. */
    public function index()
    {
        abort_unless($this->access->isHr(Auth::user()), 403, 'Only HR can see all appeals.');

        return view('hr.performance.appeals');
    }

    public function list(): JsonResponse
    {
        abort_unless($this->access->isHr(Auth::user()), 403, 'Only HR can see all appeals.');

        return response()->json(['success' => true, 'appeals' => $this->presenter->appealRows()]);
    }

    /** One review's appeal: the grounds, the hearing and the decision. */
    public function show(int $id)
    {
        $review = PerformanceReview::findOrFail($id);
        $denial = $this->access->denial(Auth::user(), $review, 'view');
        abort_if($denial !== null, $denial['status'] ?? 403, $denial['error'] ?? 'You do not have access to this review.');

        return view('hr.performance.appeal', ['page' => $this->presenter->page(Auth::user(), $review, PerformanceMeeting::KIND_APPEAL)]);
    }

    public function file(Request $request, int $id): JsonResponse
    {
        return $this->act($id, fn (PerformanceReview $review) => $this->service->file(
            $review, Auth::user(), $this->grounds($request), $this->text($request, 'reason')
        ));
    }

    public function fileOnBehalf(Request $request, int $id): JsonResponse
    {
        return $this->act($id, fn (PerformanceReview $review) => $this->service->file(
            $review, Auth::user(), $this->grounds($request), $this->text($request, 'reason'), true, $this->text($request, 'late_reason')
        ));
    }

    /** Records the hearing and the committee's decision. */
    public function decide(Request $request, int $id): JsonResponse
    {
        return $this->act($id, fn (PerformanceReview $review) => $this->service->decide(
            $review, Auth::user(),
            (array) $request->input('attendance', []),
            (string) $this->text($request, 'outcome'),
            (string) $this->text($request, 'decision_reason'),
            $this->ratings($request)
        ));
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function act(int $id, callable $do): JsonResponse
    {
        $review = PerformanceReview::findOrFail($id);

        // Someone with no part in the review is told 403 without learning anything about it
        if ($this->access->denial(Auth::user(), $review, 'view') !== null) {
            return response()->json(['success' => false, 'error' => 'You do not have access to this review.'], 403);
        }

        return $this->attempt(function () use ($review, $do) {
            $do($review);

            return response()->json(['success' => true, 'message' => 'Saved.', 'page' => $this->presenter->page(Auth::user(), $review->refresh(), PerformanceMeeting::KIND_APPEAL)]);
        });
    }

    /** The criteria disputed and the reason for each, as [review item id => reason]. */
    private function grounds(Request $request): array
    {
        $grounds = [];
        foreach ((array) $request->input('grounds', []) as $itemId => $reason) {
            $grounds[(int) $itemId] = is_scalar($reason) ? (string) $reason : '';
        }

        return $grounds;
    }

    /** The new final ratings in a decision, as [review item id => [rating, comment]]. */
    private function ratings(Request $request): array
    {
        $ratings = [];
        foreach ((array) $request->input('ratings', []) as $itemId => $entry) {
            $entry = (array) $entry;
            $ratings[(int) $itemId] = ['rating' => $entry['rating'] ?? null, 'comment' => $entry['comment'] ?? null];
        }

        return $ratings;
    }

    private function text(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : null;
    }
}
