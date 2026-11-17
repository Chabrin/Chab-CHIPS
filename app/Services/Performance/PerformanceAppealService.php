<?php

namespace App\Services\Performance;

use App\Models\PerformanceAppeal;
use App\Models\PerformanceMeeting;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The appeal: a staff member who does not accept their finalised review appeals it, naming the criteria they dispute and why.
 * HR schedules a hearing with the review committee (the meeting service does that, with the same rules about who must come),
 * and after the hearing HR records the committee's decision. The decision is final. When it changes a rating, the change is
 * made through the same correction a finalised review always uses, so the old values and the reason stay in its history.
 */
class PerformanceAppealService
{
    private const TEXT_MAX = 2000;

    public function __construct(
        private PerformanceAccess $access,
        private PerformanceReviewService $reviews,
        private PerformanceMeetingService $meetings,
        private PerformanceNotifications $notifications,
    ) {}

    /**
     * The staff member files the appeal, or HR does it for them ($onBehalf: they have no login, or the appeal period has ended,
     * which then needs the reason HR accepted it late).
     *
     * @param  array<int, mixed>  $grounds  review item id => the staff member's reason for disputing it
     */
    public function file(PerformanceReview $review, User $actor, array $grounds, ?string $reason, bool $onBehalf = false, ?string $lateReason = null): PerformanceAppeal
    {
        $this->allow($actor, $review, $onBehalf ? 'file_appeal_on_behalf' : 'file_appeal');

        $items = $review->items()->get()->keyBy('id');
        $clean = [];
        foreach ($grounds as $itemId => $why) {
            $text = $this->text($why);
            if (! $items->has((int) $itemId)) {
                throw new PerformanceException('An appeal was sent for something that is not part of this review.', 422);
            }
            if ($text === null) {
                throw new PerformanceException('Say why you dispute "'.$items->get((int) $itemId)->name.'".', 422);
            }
            $clean[(int) $itemId] = $text;
        }
        if ($clean === []) {
            throw new PerformanceException('Pick at least one criterion you dispute and say why.', 422);
        }

        $late = null;
        if ($onBehalf && ! $this->access->appealWindowOpen($review)) {
            $late = $this->text($lateReason);
            if ($late === null) {
                throw new PerformanceException('The appeal period has ended. Say why HR is accepting this appeal late.', 422);
            }
        }

        $appeal = DB::transaction(function () use ($review, $actor, $clean, $reason, $onBehalf, $late) {
            $appeal = PerformanceAppeal::create([
                'review_id' => $review->id,
                'filed_by_user_id' => $actor->id,
                'on_behalf' => $onBehalf,
                'late_reason' => $late,
                'reason' => $this->text($reason),
                'status' => PerformanceAppeal::STATUS_FILED,
                'score_before' => $review->final_score,
            ]);
            foreach ($clean as $itemId => $why) {
                $appeal->items()->create(['review_item_id' => $itemId, 'reason' => $why]);
            }

            PerformanceReviewEvent::record($review, 'appeal_filed', $actor->id, [
                'from_status' => $review->status,
                'on_behalf_of_staff_id' => $onBehalf ? $review->staff_id : null,
                'note' => $late,
                'details' => ['appeal_id' => $appeal->id, 'criteria' => array_keys($clean)],
            ]);

            return $appeal;
        });

        $this->notifications->appealFiled($appeal->refresh());

        return $appeal;
    }

    /**
     * HR records the hearing and the committee's decision. Who came is checked the same way as for the joint review meeting.
     * An upheld or partly upheld appeal comes with the new rating for each criterion that changes (only criteria the appeal
     * was about); a rejected one leaves the ratings as they are.
     *
     * @param  array<int, mixed>  $attendance  attendee id => came or not
     * @param  array<int, array{rating?: mixed, comment?: mixed}>  $ratings  review item id => the new final rating (and comment)
     */
    public function decide(PerformanceReview $review, User $actor, array $attendance, string $outcome, string $reason, array $ratings = []): PerformanceAppeal
    {
        $this->allow($actor, $review, 'record_hearing');
        $appeal = $this->access->appealOf($review);
        $hearing = $this->access->meetingOf($review, PerformanceMeeting::KIND_APPEAL);
        $present = $this->meetings->checkAttendance($hearing, $attendance);

        if (! array_key_exists($outcome, PerformanceAppeal::OUTCOME_LABELS)) {
            throw new PerformanceException('Choose the committee\'s decision: upheld, partly upheld or rejected.', 422);
        }
        $reason = $this->text($reason);
        if ($reason === null) {
            throw new PerformanceException('Write the committee\'s reasons for the decision. The staff member reads them.', 422);
        }

        $corrections = [];
        if ($outcome !== PerformanceAppeal::OUTCOME_REJECTED) {
            $corrections = $this->corrections($review, $appeal, $ratings);
            if ($corrections === []) {
                throw new PerformanceException('An appeal that is upheld changes at least one rating. Enter the new rating for the criteria that change, or choose "Rejected".', 422);
            }
        }

        $saved = DB::transaction(function () use ($review, $actor, $appeal, $hearing, $present, $outcome, $reason, $corrections) {
            $before = $review->final_score;
            if ($corrections !== []) {
                $this->reviews->correct($review, $actor, $corrections, $review->final_comment, 'Appeal decided by the review committee: '.$reason, false);
                $review->refresh();
            }

            $this->meetings->markHeld($hearing, $present, $actor);
            $appeal->update([
                'status' => PerformanceAppeal::STATUS_DECIDED,
                'outcome' => $outcome,
                'decision_reason' => $reason,
                'score_before' => $before,
                'score_after' => $review->final_score,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            PerformanceReviewEvent::record($review, 'appeal_decided', $actor->id, [
                'from_status' => $review->status,
                'note' => $reason,
                'details' => [
                    'appeal_id' => $appeal->id,
                    'outcome' => $outcome,
                    'score_before' => $before,
                    'score_after' => $review->final_score,
                    'attended' => $hearing->attendees()->where('present', true)->pluck('name')->all(),
                ],
            ]);

            return $appeal->refresh();
        });

        $this->notifications->appealDecided($saved);

        return $saved;
    }

    /**
     * The corrections to make, keyed by review item id: only criteria the appeal was about, and only where the rating really
     * changes. A comment left empty keeps the existing one.
     *
     * @return array<int, array{rating: int, comment: ?string}>
     */
    private function corrections(PerformanceReview $review, PerformanceAppeal $appeal, array $ratings): array
    {
        $appealed = $appeal->items()->pluck('review_item_id')->map(fn ($id) => (int) $id)->all();
        $items = $review->items()->get()->keyBy('id');
        $out = [];

        foreach ($ratings as $itemId => $entry) {
            $entry = (array) $entry;
            $rating = $entry['rating'] ?? null;
            if ($rating === null || $rating === '') {
                continue;
            }
            if (! in_array((int) $itemId, $appealed, true) || ! $items->has((int) $itemId)) {
                throw new PerformanceException('A rating can only be changed on a criterion the appeal was about.', 422);
            }
            $item = $items->get((int) $itemId);
            if (is_numeric($rating) && (int) $rating === (int) $item->hr_rating) {
                continue;
            }
            $comment = $this->text($entry['comment'] ?? null);
            $out[(int) $itemId] = ['rating' => $rating, 'comment' => $comment ?? $item->hr_comment];
        }

        return $out;
    }

    private function allow(User $actor, PerformanceReview $review, string $action): void
    {
        $denial = $this->access->denial($actor, $review, $action);
        if ($denial) {
            throw new PerformanceException($denial['error'], $denial['status']);
        }
    }

    private function text($value): ?string
    {
        $text = trim(is_scalar($value) ? (string) $value : '');
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > self::TEXT_MAX) {
            throw new PerformanceException('Each reason can be at most '.self::TEXT_MAX.' characters.', 422);
        }

        return $text;
    }
}
