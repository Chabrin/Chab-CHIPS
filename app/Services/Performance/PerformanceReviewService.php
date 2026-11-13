<?php

namespace App\Services\Performance;

use App\Models\PerformanceReview;
use App\Models\PerformanceReviewEvent;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Everything that changes a review: the self-rating, the supervisor's rating, HR's final rating, sending it back, skipping
 * a self-rating, changing the supervisor, and correcting a finalised review. Each one first asks PerformanceAccess whether
 * this person may, does its work in one transaction, and writes an event so the review keeps its history.
 */
class PerformanceReviewService
{
    private const COMMENT_MAX = 2000;

    public function __construct(private PerformanceAccess $access, private PerformanceMetrics $metrics, private PerformanceNotifications $notifications) {}

    /**
     * The staff member rates themselves, or HR does it for them ($onBehalf).
     *
     * @param  array<int, array{rating: mixed, comment?: mixed}>  $ratings  keyed by review item id
     */
    public function saveSelf(PerformanceReview $review, User $actor, array $ratings, ?string $summary, bool $submit, bool $onBehalf = false): PerformanceReview
    {
        $this->allow($actor, $review, $onBehalf ? 'self_on_behalf' : 'self');

        $saved = DB::transaction(function () use ($review, $actor, $ratings, $summary, $submit, $onBehalf) {
            $from = $review->status;
            $this->applyRatings($review, $ratings, 'self', $submit);
            $review->self_summary = $this->clean($summary);

            if ($submit) {
                $review->self_submitted_at = now();
                $review->status = $review->route === PerformanceReview::ROUTE_SUPERVISOR
                    ? PerformanceReview::STATUS_AWAITING_SUPERVISOR
                    : PerformanceReview::STATUS_AWAITING_HR;
            }
            $review->save();

            PerformanceReviewEvent::record($review, $submit ? 'self_submitted' : 'self_saved', $actor->id, [
                'from_status' => $from,
                'on_behalf_of_staff_id' => $onBehalf ? $review->staff_id : null,
            ]);

            return $review->refresh();
        });

        if ($submit) {
            $this->notifications->selfDone($saved);
        }

        return $saved;
    }

    /** HR gives up waiting for a self-rating (the staff member did not do one); the review moves on without it. */
    public function skipSelf(PerformanceReview $review, User $actor, string $reason): PerformanceReview
    {
        $this->allow($actor, $review, 'skip_self');
        $reason = $this->requiredReason($reason, 'Say why the self-rating is being skipped.');

        $saved = DB::transaction(function () use ($review, $actor, $reason) {
            $from = $review->status;
            $review->self_skipped = true;
            $review->status = $review->route === PerformanceReview::ROUTE_SUPERVISOR
                ? PerformanceReview::STATUS_AWAITING_SUPERVISOR
                : PerformanceReview::STATUS_AWAITING_HR;
            $review->save();

            PerformanceReviewEvent::record($review, 'self_skipped', $actor->id, ['from_status' => $from, 'note' => $reason, 'on_behalf_of_staff_id' => $review->staff_id]);

            return $review->refresh();
        });

        $this->notifications->selfDone($saved);

        return $saved;
    }

    /** The named supervisor rates the staff member. */
    public function saveSupervisor(PerformanceReview $review, User $actor, array $ratings, ?string $summary, bool $submit): PerformanceReview
    {
        $this->allow($actor, $review, 'supervisor');
        $records = $submit ? $this->records($review) : null;

        $saved = DB::transaction(function () use ($review, $actor, $ratings, $summary, $submit, $records) {
            $from = $review->status;
            $this->applyRatings($review, $ratings, 'supervisor', $submit);
            $review->supervisor_summary = $this->clean($summary);

            if ($submit) {
                $review->supervisor_submitted_at = now();
                $review->status = PerformanceReview::STATUS_AWAITING_HR;
                $this->keepRecords($review, $records);
            }
            $review->save();

            PerformanceReviewEvent::record($review, $submit ? 'supervisor_submitted' : 'supervisor_saved', $actor->id, ['from_status' => $from]);

            return $review->refresh();
        });

        if ($submit) {
            $this->notifications->supervisorDone($saved);
        }

        return $saved;
    }

    /** HR saves its ratings, or finalises the review with them. HR's rating is the final one. */
    public function saveHr(PerformanceReview $review, User $actor, array $ratings, ?string $comment, bool $finalise): PerformanceReview
    {
        $this->allow($actor, $review, 'hr');
        $records = $finalise ? $this->records($review) : null;

        $saved = DB::transaction(function () use ($review, $actor, $ratings, $comment, $finalise, $records) {
            $from = $review->status;
            $this->applyRatings($review, $ratings, 'hr', $finalise);
            $review->final_comment = $this->clean($comment);

            if ($finalise) {
                $review->final_score = PerformanceScorer::score($review->items()->get(), 'hr');
                $review->finalised_at = now();
                $review->finalised_by = $actor->id;
                $review->status = PerformanceReview::STATUS_FINALISED;
                $this->keepRecords($review, $records);
            }
            $review->save();

            PerformanceReviewEvent::record($review, $finalise ? 'finalised' : 'hr_saved', $actor->id, [
                'from_status' => $from,
                'details' => $finalise ? ['final_score' => $review->final_score] : null,
            ]);

            return $review->refresh();
        });

        if ($finalise) {
            $this->notifications->finalised($saved);
        }

        return $saved;
    }

    /** HR sends a review back to its supervisor, who rates it again. Their earlier ratings stay as the starting point. */
    public function returnToSupervisor(PerformanceReview $review, User $actor, string $reason): PerformanceReview
    {
        $this->allow($actor, $review, 'return');
        $reason = $this->requiredReason($reason, 'Say why the review is being sent back.');

        $saved = DB::transaction(function () use ($review, $actor, $reason) {
            $from = $review->status;
            $review->status = PerformanceReview::STATUS_AWAITING_SUPERVISOR;
            $review->supervisor_submitted_at = null;
            $review->save();

            PerformanceReviewEvent::record($review, 'returned_to_supervisor', $actor->id, ['from_status' => $from, 'note' => $reason]);

            return $review->refresh();
        });

        $this->notifications->returned($saved, $reason);

        return $saved;
    }

    /**
     * HR changes who the supervisor is, or takes the supervisor's part itself ($supervisorStaffId null). It can only be
     * someone who holds the position the staff member reports to and has a login.
     */
    public function reassign(PerformanceReview $review, User $actor, ?int $supervisorStaffId, string $reason): PerformanceReview
    {
        $this->allow($actor, $review, 'reassign');
        $reason = $this->requiredReason($reason, 'Say why the supervisor is being changed.');

        $subject = Staff::find($review->staff_id);
        if ($supervisorStaffId) {
            $candidate = $subject ? $this->access->supervisorCandidates($subject)->first(fn (Staff $c) => (int) $c->id === $supervisorStaffId) : null;
            if (! $candidate) {
                throw new PerformanceException('That person does not hold the position this staff member reports to.');
            }
            if (! $this->access->hasLogin($candidate)) {
                throw new PerformanceException($candidate->full_names.' has no system login, so cannot rate this review.');
            }
        }

        $saved = DB::transaction(function () use ($review, $actor, $supervisorStaffId, $reason) {
            $from = $review->status;
            $previous = $review->supervisor_staff_id;

            if ($supervisorStaffId) {
                $review->supervisor_staff_id = $supervisorStaffId;
                $review->route = PerformanceReview::ROUTE_SUPERVISOR;
                $review->route_reason = 'named';
                if ($review->status === PerformanceReview::STATUS_AWAITING_HR) {
                    $review->status = PerformanceReview::STATUS_AWAITING_SUPERVISOR;
                }
            } else {
                $review->supervisor_staff_id = null;
                $review->route = PerformanceReview::ROUTE_HR;
                $review->route_reason = 'hr_took_over';
                if ($review->status === PerformanceReview::STATUS_AWAITING_SUPERVISOR) {
                    $review->status = PerformanceReview::STATUS_AWAITING_HR;
                }
            }
            $review->save();

            PerformanceReviewEvent::record($review, 'supervisor_changed', $actor->id, [
                'from_status' => $from,
                'note' => $reason,
                'details' => ['from_supervisor_staff_id' => $previous, 'to_supervisor_staff_id' => $supervisorStaffId],
            ]);

            return $review->refresh();
        });

        if ($supervisorStaffId) {
            $this->notifications->supervisorChanged($saved);
        }

        return $saved;
    }

    /** HR changes the final ratings of a finalised review. The old values and the reason are kept in its history. */
    public function correct(PerformanceReview $review, User $actor, array $ratings, ?string $comment, string $reason): PerformanceReview
    {
        $this->allow($actor, $review, 'correct');
        $reason = $this->requiredReason($reason, 'Say why the final rating is being corrected.');

        $saved = DB::transaction(function () use ($review, $actor, $ratings, $comment, $reason) {
            $before = [
                'final_score' => $review->final_score,
                'final_comment' => $review->final_comment,
                'items' => $review->items()->get()->mapWithKeys(fn ($item) => [$item->id => ['hr_rating' => $item->hr_rating, 'hr_comment' => $item->hr_comment]])->all(),
            ];

            $this->applyRatings($review, $ratings, 'hr', true);
            $review->final_comment = $this->clean($comment);
            $review->final_score = PerformanceScorer::score($review->items()->get(), 'hr');
            $review->save();

            PerformanceReviewEvent::record($review, 'corrected', $actor->id, [
                'from_status' => $review->status,
                'note' => $reason,
                'details' => ['before' => $before, 'after_score' => $review->final_score],
            ]);

            return $review->refresh();
        });

        $this->notifications->corrected($saved);

        return $saved;
    }

    /**
     * The records (attendance, leave and so on) as they are now, or null when they cannot be worked out. A fault in them
     * never stops a rating, and they are worked out before the transaction opens so a failed query cannot spoil it.
     */
    private function records(PerformanceReview $review): ?array
    {
        try {
            return $this->metrics->forReview($review);
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /** Keeps the records as they were when the rating was given, for the history. */
    private function keepRecords(PerformanceReview $review, ?array $records): void
    {
        if ($records !== null) {
            $review->metrics = $records;
            $review->metrics_taken_at = now();
        }
    }

    private function allow(User $actor, PerformanceReview $review, string $action): void
    {
        $denial = $this->access->denial($actor, $review, $action);
        if ($denial) {
            throw new PerformanceException($denial['error'], $denial['status']);
        }
    }

    /**
     * Writes the ratings and comments for one person ($who: self, supervisor or hr) onto the review's items. A rating is a
     * whole number from 1 to 10; one left empty stays unrated, unless the person is submitting, which needs every one.
     */
    private function applyRatings(PerformanceReview $review, array $ratings, string $who, bool $requireAll): void
    {
        $items = $review->items()->get()->keyBy('id');

        foreach ($ratings as $itemId => $entry) {
            $item = $items->get((int) $itemId);
            if (! $item) {
                throw new PerformanceException('A rating was sent for something that is not part of this review.');
            }

            $rating = $entry['rating'] ?? null;
            if ($rating === '' || $rating === null) {
                $rating = null;
            } elseif (is_numeric($rating) && (string) (int) $rating === (string) $rating) {
                $rating = (int) $rating;
            }
            if ($rating !== null && ! PerformanceScorer::isValidRating($rating)) {
                throw new PerformanceException('A rating must be a whole number from '.PerformanceScorer::MIN_RATING.' to '.PerformanceScorer::MAX_RATING." ({$item->name}).");
            }

            $item->{$who.'_rating'} = $rating;
            $item->{$who.'_comment'} = $this->clean($entry['comment'] ?? null);
            $item->save();
        }

        if ($requireAll) {
            $missing = $review->items()->get()->filter(fn ($item) => $item->{$who.'_rating'} === null)->pluck('name');
            if ($missing->isNotEmpty()) {
                throw new PerformanceException('Rate every criterion before submitting. Still to rate: '.$missing->implode(', ').'.');
            }
        }
    }

    private function clean(?string $text): ?string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > self::COMMENT_MAX) {
            throw new PerformanceException('A comment can be at most '.self::COMMENT_MAX.' characters.');
        }

        return $text;
    }

    private function requiredReason(string $reason, string $message): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new PerformanceException($message);
        }

        return $reason;
    }
}
