<?php

namespace App\Http\Controllers;

use App\Models\PerformanceReview;
use App\Services\Performance\PerformanceAccess;
use App\Services\Performance\PerformanceReviewPresenter;
use App\Services\Performance\PerformanceReviewService;
use App\Traits\RespondsToPerformanceErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * HR > Performance Review > My Review, Team Reviews and All Reviews, and the review form they open. The menu pages are
 * given to roles; what a person can open and do is decided here, on the server, by PerformanceAccess for every request:
 * the staff member sees their own reviews, a supervisor the reviews they were named on, HR all of them. Staff never get a
 * supervisor's rating: the presenter picks the fields each person may see.
 */
class PerformanceReviewController extends Controller
{
    use RespondsToPerformanceErrors;

    public function __construct(
        private PerformanceAccess $access,
        private PerformanceReviewPresenter $presenter,
        private PerformanceReviewService $service,
    ) {}

    // ── the three list pages ──────────────────────────────────────────────

    public function my()
    {
        return view('hr.performance.reviews', ['mode' => 'my', 'title' => 'My Review', 'hint' => 'Your reviews. Rate yourself when one is waiting for you; once HR finalises it you see the final rating here.']);
    }

    public function team()
    {
        return view('hr.performance.reviews', ['mode' => 'team', 'title' => 'Team Reviews', 'hint' => 'The reviews of the staff you are the supervisor of. Open one to see their self-rating and give yours.']);
    }

    public function all()
    {
        abort_unless($this->access->isHr(Auth::user()), 403, 'Only HR can see all reviews.');

        return view('hr.performance.reviews', ['mode' => 'all', 'title' => 'All Reviews', 'hint' => 'Every review in every cycle. Open one to rate on behalf of someone, send it back, change its supervisor or give the final rating.']);
    }

    /** The rows of one of the list pages: mode my, team or all. */
    public function list(Request $request, string $mode): JsonResponse
    {
        $user = Auth::user();
        $staff = $this->access->staffOf($user);

        if ($mode === 'all') {
            abort_unless($this->access->isHr($user), 403, 'Only HR can see all reviews.');
            $query = PerformanceReview::query();
        } elseif ($mode === 'team') {
            $query = PerformanceReview::query()->where('supervisor_staff_id', $staff && (int) $staff->current_status_id === 1 ? $staff->id : 0)
                ->where('staff_id', '!=', $staff?->id ?? 0);
        } elseif ($mode === 'my') {
            $query = PerformanceReview::query()->where('staff_id', $staff?->id ?? 0);
        } else {
            abort(404);
        }

        $query->with(['cycle', 'staff', 'supervisor'])->orderByDesc('cycle_id')->orderBy('id');
        if ($mode === 'all') {
            $query->when($request->query('cycle_id'), fn ($q, $id) => $q->where('cycle_id', (int) $id))
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status));
        }

        return response()->json([
            'success' => true,
            'linked' => $staff !== null,
            'reviews' => $query->get()->map(fn (PerformanceReview $review) => $this->presenter->row($review))->values(),
        ]);
    }

    // ── one review ────────────────────────────────────────────────────────

    public function show(int $id)
    {
        $review = $this->find($id);
        $this->authorise($review, 'view');

        return view('hr.performance.review', ['review' => $this->presenter->show(Auth::user(), $review)]);
    }

    public function data(int $id): JsonResponse
    {
        $review = $this->find($id);
        $this->authorise($review, 'view');

        return response()->json(['success' => true, 'review' => $this->presenter->show(Auth::user(), $review)]);
    }

    public function saveSelf(Request $request, int $id): JsonResponse
    {
        return $this->act($id, fn (PerformanceReview $review) => $this->service->saveSelf(
            $review, Auth::user(), $this->ratings($request), $this->text($request, 'summary'), $request->boolean('submit')
        ));
    }

    public function saveSelfOnBehalf(Request $request, int $id): JsonResponse
    {
        return $this->act($id, fn (PerformanceReview $review) => $this->service->saveSelf(
            $review, Auth::user(), $this->ratings($request), $this->text($request, 'summary'), $request->boolean('submit'), true
        ));
    }

    public function skipSelf(Request $request, int $id): JsonResponse
    {
        return $this->act($id, fn (PerformanceReview $review) => $this->service->skipSelf($review, Auth::user(), (string) $this->text($request, 'reason')));
    }

    public function saveSupervisor(Request $request, int $id): JsonResponse
    {
        return $this->act($id, fn (PerformanceReview $review) => $this->service->saveSupervisor(
            $review, Auth::user(), $this->ratings($request), $this->text($request, 'summary'), $request->boolean('submit')
        ));
    }

    public function saveHr(Request $request, int $id): JsonResponse
    {
        return $this->act($id, fn (PerformanceReview $review) => $this->service->saveHr(
            $review, Auth::user(), $this->ratings($request), $this->text($request, 'comment'), $request->boolean('finalise')
        ));
    }

    public function returnToSupervisor(Request $request, int $id): JsonResponse
    {
        return $this->act($id, fn (PerformanceReview $review) => $this->service->returnToSupervisor($review, Auth::user(), (string) $this->text($request, 'reason')));
    }

    public function reassign(Request $request, int $id): JsonResponse
    {
        return $this->act($id, fn (PerformanceReview $review) => $this->service->reassign(
            $review, Auth::user(), $request->filled('supervisor_staff_id') ? (int) $request->input('supervisor_staff_id') : null, (string) $this->text($request, 'reason')
        ));
    }

    public function correct(Request $request, int $id): JsonResponse
    {
        return $this->act($id, fn (PerformanceReview $review) => $this->service->correct(
            $review, Auth::user(), $this->ratings($request), $this->text($request, 'comment'), (string) $this->text($request, 'reason')
        ));
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function find(int $id): PerformanceReview
    {
        return PerformanceReview::findOrFail($id);
    }

    /** Aborts with 403 when the person has no access to the review, and with the reason when it is not their turn. */
    private function authorise(PerformanceReview $review, string $action): void
    {
        $denial = $this->access->denial(Auth::user(), $review, $action);
        abort_if($denial !== null, $denial['status'] ?? 403, $denial['error'] ?? 'You do not have access to this review.');
    }

    /** Runs one change to a review and answers with the review as this person is allowed to see it. */
    private function act(int $id, callable $do): JsonResponse
    {
        $review = $this->find($id);

        // Someone with no part in the review is told 403 without learning anything about it
        if ($this->access->denial(Auth::user(), $review, 'view') !== null) {
            return response()->json(['success' => false, 'error' => 'You do not have access to this review.'], 403);
        }

        return $this->attempt(function () use ($review, $do) {
            $updated = $do($review);

            return response()->json(['success' => true, 'message' => 'Saved.', 'review' => $this->presenter->show(Auth::user(), $updated)]);
        });
    }

    /** A text field from the request; anything that is not plain text counts as empty. */
    private function text(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : null;
    }

    /** The ratings in a request, as [item id => [rating, comment]]. */
    private function ratings(Request $request): array
    {
        $ratings = [];
        foreach ((array) $request->input('ratings', []) as $itemId => $entry) {
            $entry = (array) $entry;
            $ratings[(int) $itemId] = ['rating' => $entry['rating'] ?? null, 'comment' => $entry['comment'] ?? null];
        }

        return $ratings;
    }
}
