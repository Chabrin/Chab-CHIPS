<?php

namespace App\Services\Performance;

use App\Models\PerformanceReview;
use App\Models\PerformanceReviewEvent;
use App\Models\User;

/**
 * The only place a review is turned into something a page or an ajax call can show. Each person gets exactly the fields
 * their part allows, picked one by one (never by dumping the model), so nothing written about a person can leak to them:
 *
 *  - the staff member: their own self-rating, and once the review is finalised the supervisor's ratings and comments and
 *    HR's final rating and comment (never who the supervisor is, the records or the history);
 *  - the supervisor: the self-rating (once submitted), their own rating, the records, and the final result once finalised;
 *  - HR: everything, including the history.
 */
class PerformanceReviewPresenter
{
    private const ACTIONS = ['self', 'self_on_behalf', 'skip_self', 'supervisor', 'hr', 'return', 'reassign', 'correct'];

    public function __construct(private PerformanceAccess $access, private PerformanceMetrics $metrics, private PerformanceMeetingPresenter $meetings) {}

    public function show(User $viewer, PerformanceReview $review): array
    {
        $role = $this->access->role($viewer, $review);
        $review->loadMissing(['cycle', 'staff', 'supervisor', 'items']);
        $items = $review->items;
        $final = $review->isFinalised();
        // The supervisor and the committee read the self-rating once it is submitted, never a draft
        $selfVisible = ! in_array($role, [PerformanceAccess::ROLE_SUPERVISOR, PerformanceAccess::ROLE_COMMITTEE], true) || $review->self_submitted_at || $review->self_skipped;

        $out = [
            'id' => $review->id,
            'role' => $role,
            'status' => $review->status,
            'status_label' => $review->statusLabel(),
            'is_finalised' => $final,
            // Whether a supervisor takes part (so the process tracker can say so); never who they are
            'has_supervisor' => $review->route === PerformanceReview::ROUTE_SUPERVISOR,
            'self_skipped' => $selfVisible ? $review->self_skipped : false,
            'cycle' => [
                'id' => $review->cycle->id,
                'name' => $review->cycle->name,
                'period' => $this->date($review->cycle->period_start).' to '.$this->date($review->cycle->period_end),
                'deadline' => $this->date($review->cycle->deadline),
                'status' => $review->cycle->status,
            ],
            'staff' => ['id' => $review->staff_id, 'name' => $review->staff?->full_names, 'position' => $review->position_name],
            'scale' => ['min' => PerformanceScorer::MIN_RATING, 'max' => PerformanceScorer::MAX_RATING],
            'actions' => $this->actions($viewer, $review),
            // Where the joint review meeting and the appeal have got to (the pages for them show the rest)
            'stages' => $this->meetings->summary($viewer, $review),
            'items' => [],
        ];

        foreach ($items as $item) {
            $row = ['id' => $item->id, 'name' => $item->name, 'description' => $item->description, 'weight' => $item->weight];

            if ($role === PerformanceAccess::ROLE_SUBJECT) {
                $row += ['self_rating' => $item->self_rating, 'self_comment' => $item->self_comment];
                // The supervisor's rating is shared with the staff member only once the review is finalised
                if ($final) {
                    $row += ['supervisor_rating' => $item->supervisor_rating, 'supervisor_comment' => $item->supervisor_comment];
                }
            } else {
                if ($selfVisible) {
                    $row += ['self_rating' => $item->self_rating, 'self_comment' => $item->self_comment];
                }
                $row += ['supervisor_rating' => $item->supervisor_rating, 'supervisor_comment' => $item->supervisor_comment];
            }
            if ($final || $role === PerformanceAccess::ROLE_HR) {
                $row += ['hr_rating' => $item->hr_rating, 'hr_comment' => $item->hr_comment];
            }

            $out['items'][] = $row;
        }

        $out['self_summary'] = $selfVisible ? $review->self_summary : null;
        $out['scores'] = ['self' => $selfVisible ? PerformanceScorer::score($items, 'self') : null];

        if ($role === PerformanceAccess::ROLE_SUBJECT) {
            // Once finalised: the supervisor's rating, score and overall comment. Never who the supervisor is, the records or the history
            if ($final) {
                $out['supervisor_summary'] = $review->supervisor_summary;
                $out['scores']['supervisor'] = PerformanceScorer::score($items, 'supervisor');
            }

            return $this->withFinal($out, $review, $final);
        }

        $out['supervisor_summary'] = $review->supervisor_summary;
        $out['scores']['supervisor'] = PerformanceScorer::score($items, 'supervisor');
        $out['supervisor'] = $review->supervisor?->full_names;
        $out['metrics'] = $this->metricsFor($review);

        if ($role === PerformanceAccess::ROLE_HR) {
            $out['scores']['hr'] = PerformanceScorer::score($items, 'hr');
            $out['route'] = $review->route;
            $out['route_reason'] = PerformanceAccess::REASONS[$review->route_reason] ?? $review->route_reason;
            $out['supervisor_candidates'] = $this->candidates($review);
            $out['final_comment'] = $review->final_comment;
            $out['history'] = $this->history($review);
        }

        return $this->withFinal($out, $review, $final);
    }

    /** The one-line row a list shows for a review. Nothing in it is an appraisal rating except the final score. */
    public function row(PerformanceReview $review): array
    {
        $review->loadMissing(['cycle', 'staff', 'supervisor', 'appeal']);

        return [
            'id' => $review->id,
            'appeal' => $review->appeal ? ($review->appeal->isDecided() ? 'decided' : 'filed') : null,
            'cycle' => $review->cycle?->name,
            'cycle_id' => $review->cycle_id,
            'staff' => $review->staff?->full_names,
            'position' => $review->position_name,
            'supervisor' => $review->supervisor?->full_names,
            'route' => $review->route,
            'status' => $review->status,
            'status_label' => $review->statusLabel(),
            'final_score' => $review->isFinalised() ? $review->final_score : null,
            'deadline' => $this->date($review->cycle?->deadline),
        ];
    }

    private function withFinal(array $out, PerformanceReview $review, bool $final): array
    {
        if ($final) {
            $out['final'] = [
                'score' => $review->final_score,
                'comment' => $review->final_comment,
                'finalised_at' => $this->date($review->finalised_at),
            ];
        }

        return $out;
    }

    private function actions(User $viewer, PerformanceReview $review): array
    {
        return array_values(array_filter(self::ACTIONS, fn ($action) => $this->access->denial($viewer, $review, $action) === null));
    }

    /**
     * The records beside the ratings: as they were when the review was finalised, otherwise worked out now so the supervisor
     * and HR see the latest attendance and leave. A fault in working them out never stops a review being opened.
     */
    private function metricsFor(PerformanceReview $review): ?array
    {
        if ($review->isFinalised() && $review->metrics) {
            return $review->metrics;
        }

        try {
            return $this->metrics->forReview($review);
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /** The people HR may hand a review to: holders of the position the staff member reports to who can log in. */
    private function candidates(PerformanceReview $review): array
    {
        $staff = $review->staff;
        if (! $staff) {
            return [];
        }

        return $this->access->supervisorCandidates($staff)
            ->map(fn ($candidate) => ['id' => (int) $candidate->id, 'name' => $candidate->full_names, 'has_login' => $this->access->hasLogin($candidate)])
            ->values()->all();
    }

    private function history(PerformanceReview $review): array
    {
        return $review->events()->get()->map(fn (PerformanceReviewEvent $event) => [
            'action' => $event->action,
            'from_status' => $event->from_status,
            'to_status' => $event->to_status,
            'by' => $event->actor?->name,
            'on_behalf_of' => $event->on_behalf_of_staff_id ? $review->staff?->full_names : null,
            'note' => $event->note,
            'at' => $event->created_at?->format('d M Y H:i'),
        ])->all();
    }

    private function date($value): ?string
    {
        return $value ? $value->format('d M Y') : null;
    }
}
