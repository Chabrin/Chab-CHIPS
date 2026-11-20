<?php

namespace App\Services\Performance;

use App\Models\PerformanceAppeal;
use App\Models\PerformanceMeeting;
use App\Models\PerformanceMeetingAttendee;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The joint review meeting and the appeal turned into what a page can show, picked field by field for the person looking:
 *
 *  - everyone with a part in the review sees when and where the meeting is, who is invited and, once it was held, what was
 *    discussed (the feedback, training needs, goals and any extra items) and who came;
 *  - the staff member sees nothing from the appeal that is not theirs or the committee's decision, and never who "did not
 *    show" in a way that is not already on the attendance list;
 *  - only HR gets the forms' options (who can be invited) and the list of people who could not be told in the app.
 */
class PerformanceMeetingPresenter
{
    public function __construct(private PerformanceAccess $access, private PerformanceMeetingService $meetings) {}

    /** Everything one meeting page (or the appeal page) needs. */
    public function page(User $viewer, PerformanceReview $review, string $kind): array
    {
        $role = $this->access->role($viewer, $review);
        $review->loadMissing(['cycle', 'staff', 'items']);
        $isHr = $role === PerformanceAccess::ROLE_HR;
        $meeting = $this->access->meetingOf($review, $kind);
        $can = fn (string $action) => $this->access->denial($viewer, $review, $action) === null;

        $out = [
            'role' => $role,
            'kind' => $kind,
            'review' => [
                'id' => $review->id,
                'staff' => $review->staff?->full_names,
                'position' => $review->position_name,
                'cycle' => $review->cycle?->name,
                'status' => $review->status,
                'status_label' => $review->statusLabel(),
                'route' => $review->route,
                'is_finalised' => $review->isFinalised(),
            ],
            'meeting' => $meeting ? $this->meeting($meeting, $review, $isHr) : null,
            'sections' => PerformanceMeeting::SECTIONS,
            'approvable' => PerformanceMeeting::APPROVABLE,
            'min_committee' => $this->meetings->minCommittee(),
        ];

        if ($kind === PerformanceMeeting::KIND_REVIEW) {
            $out['actions'] = [
                'schedule' => $can('schedule_meeting') && ! $meeting,
                'reschedule' => $can('schedule_meeting') && $meeting && ! $meeting->isHeld(),
                'record' => $can('record_meeting'),
            ];
        } else {
            $appeal = $this->access->appealOf($review);
            $out['appeal'] = $appeal ? $this->appeal($appeal, $review, $isHr) : null;
            $out['criteria'] = $this->criteria($review);
            $out['appeal_deadline'] = $this->access->appealDeadline($review)?->format('d M Y');
            $out['appeal_window_open'] = $this->access->appealWindowOpen($review);
            $out['actions'] = [
                'file' => $can('file_appeal'),
                'file_on_behalf' => $can('file_appeal_on_behalf'),
                'schedule' => $can('schedule_hearing') && ! $meeting,
                'reschedule' => $can('schedule_hearing') && $meeting && ! $meeting->isHeld(),
                'record' => $can('record_hearing'),
            ];
        }

        if ($isHr && ($out['actions']['schedule'] || $out['actions']['reschedule'])) {
            $out['options'] = $this->meetings->options($review);
        }

        return $out;
    }

    /** The short version shown on the review page itself: where the meeting and the appeal have got to. */
    public function summary(User $viewer, PerformanceReview $review): array
    {
        $role = $this->access->role($viewer, $review);
        $meeting = $this->access->meetingOf($review, PerformanceMeeting::KIND_REVIEW);
        $hearing = $this->access->meetingOf($review, PerformanceMeeting::KIND_APPEAL);
        $appeal = $this->access->appealOf($review);
        $can = fn (string $action) => $this->access->denial($viewer, $review, $action) === null;

        return [
            'meeting' => $meeting ? [
                'status' => $meeting->status,
                'status_label' => $meeting->isHeld() ? 'Held' : 'Scheduled',
                'when' => $meeting->scheduled_at?->format('d M Y H:i'),
                'venue' => $meeting->venue,
                'items' => $meeting->isHeld() ? $this->items($meeting) : [],
            ] : null,
            'appeal' => $appeal ? [
                'status' => $appeal->status,
                'status_label' => $appeal->isDecided() ? 'Decided' : ($hearing ? 'Hearing scheduled' : 'Filed'),
                'outcome_label' => $appeal->isDecided() ? PerformanceAppeal::OUTCOME_LABELS[$appeal->outcome] ?? null : null,
            ] : null,
            'can' => [
                'meeting' => $meeting !== null || $can('schedule_meeting') || $can('record_meeting'),
                'appeal' => $appeal !== null || $can('file_appeal') || $can('file_appeal_on_behalf'),
                'file_appeal' => $can('file_appeal'),
            ],
            'role' => $role,
        ];
    }

    /** One meeting list for the Meetings page: what the person is invited to, and for HR also what still has to be scheduled. */
    public function meetingRows(User $viewer): array
    {
        $isHr = $this->access->isHr($viewer);
        $reviewIds = $isHr ? null : $this->access->visibleTo($viewer)->pluck('id');

        $rows = PerformanceMeeting::with(['review.staff', 'review.cycle'])
            ->when($reviewIds !== null, fn ($query) => $query->whereIn('review_id', $reviewIds))
            ->get()
            ->filter(fn (PerformanceMeeting $meeting) => $meeting->review !== null)
            // Plain rows, not models: an empty model collection would otherwise stay a model collection and refuse the merge below
            ->map(fn (PerformanceMeeting $meeting) => $this->meetingRow($meeting))->toBase();

        if ($isHr) {
            $rows = $rows->merge(
                PerformanceReview::with(['staff', 'cycle'])->where('status', PerformanceReview::STATUS_AWAITING_MEETING)
                    ->whereNotIn('id', PerformanceMeeting::where('kind', PerformanceMeeting::KIND_REVIEW)->select('review_id'))->get()
                    ->map(fn (PerformanceReview $review) => $this->pendingRow($review, PerformanceMeeting::KIND_REVIEW))->toBase()
            )->merge(
                PerformanceAppeal::with(['review.staff', 'review.cycle'])->where('status', PerformanceAppeal::STATUS_FILED)
                    ->whereNotIn('review_id', PerformanceMeeting::where('kind', PerformanceMeeting::KIND_APPEAL)->select('review_id'))->get()
                    ->filter(fn (PerformanceAppeal $appeal) => $appeal->review !== null)
                    ->map(fn (PerformanceAppeal $appeal) => $this->pendingRow($appeal->review, PerformanceMeeting::KIND_APPEAL))->toBase()
            );
        }

        $order = ['to_schedule' => 0, 'scheduled' => 1, 'held' => 2];

        return $rows->sortBy(fn ($row) => [$order[$row['status']] ?? 3, $row['status'] === 'held' ? -strtotime($row['sort']) : strtotime($row['sort'])])
            ->map(fn ($row) => array_diff_key($row, ['sort' => 1]))->values()->all();
    }

    /** The appeals HR looks after, newest first. */
    public function appealRows(): array
    {
        return PerformanceAppeal::with(['review.staff', 'review.cycle'])->orderByDesc('id')->get()
            ->filter(fn (PerformanceAppeal $appeal) => $appeal->review !== null)
            ->map(function (PerformanceAppeal $appeal) {
                $hearing = $this->access->meetingOf($appeal->review, PerformanceMeeting::KIND_APPEAL);
                $state = $appeal->isDecided() ? 'decided' : ($hearing ? 'hearing_scheduled' : 'filed');

                return [
                    'review_id' => $appeal->review_id,
                    'staff' => $appeal->review->staff?->full_names,
                    'position' => $appeal->review->position_name,
                    'cycle' => $appeal->review->cycle?->name,
                    'filed_at' => $appeal->created_at?->format('d M Y'),
                    'on_behalf' => $appeal->on_behalf,
                    'state' => $state,
                    'state_label' => ['filed' => 'Waiting for a hearing', 'hearing_scheduled' => 'Hearing scheduled', 'decided' => 'Decided'][$state],
                    'hearing_at' => $hearing?->scheduled_at?->format('d M Y H:i'),
                    'outcome_label' => $appeal->isDecided() ? PerformanceAppeal::OUTCOME_LABELS[$appeal->outcome] ?? null : null,
                ];
            })->values()->all();
    }

    // ── pieces ────────────────────────────────────────────────────────────

    private function meeting(PerformanceMeeting $meeting, PerformanceReview $review, bool $isHr): array
    {
        $attendees = $meeting->attendees()->get();
        $out = [
            'id' => $meeting->id,
            'kind' => $meeting->kind,
            'status' => $meeting->status,
            'status_label' => $meeting->isHeld() ? 'Held' : 'Scheduled',
            'scheduled_at' => $meeting->scheduled_at?->format('d M Y H:i'),
            'venue' => $meeting->venue,
            'held_at' => $meeting->held_at?->format('d M Y H:i'),
            'reschedule_count' => (int) $meeting->reschedule_count,
            'attendees' => $attendees->map(fn (PerformanceMeetingAttendee $attendee) => [
                'id' => $attendee->id,
                'role' => $attendee->role,
                'role_label' => PerformanceMeetingAttendee::ROLE_LABELS[$attendee->role] ?? $attendee->role,
                'name' => $attendee->name,
                'present' => $meeting->isHeld() ? $attendee->present : null,
            ])->values()->all(),
            'items' => $meeting->isHeld() ? $this->items($meeting) : [],
            'needs' => $this->needs($review, $attendees),
        ];

        if ($isHr) {
            $out['scheduled_input'] = $meeting->scheduled_at?->format('Y-m-d\TH:i');
            $out['hr_user_id'] = (int) $meeting->hr_user_id;
            $out['committee_staff_ids'] = $attendees->where('role', PerformanceMeetingAttendee::ROLE_COMMITTEE)->pluck('staff_id')->map(fn ($id) => (int) $id)->values()->all();
            // Who the app cannot reach, so HR can tell them in person
            $out['not_reached'] = $attendees->filter(fn ($attendee) => ! $this->canBeTold($attendee))->pluck('name')->values()->all();
        }

        return $out;
    }

    /** @return array<int, array{section: string, title: string, notes: ?string, approved: bool}> */
    private function items(PerformanceMeeting $meeting): array
    {
        return $meeting->items()->get()->map(fn ($item) => [
            'section' => $item->section,
            'title' => $item->title,
            'notes' => $item->notes,
            'approved' => (bool) $item->approved,
        ])->values()->all();
    }

    /** In words, who has to be there for this meeting to be recorded as held. */
    private function needs(PerformanceReview $review, Collection $attendees): string
    {
        $min = $this->meetings->minCommittee();
        $who = ['the staff member'];
        if ($attendees->contains(fn ($attendee) => $attendee->role === PerformanceMeetingAttendee::ROLE_SUPERVISOR)) {
            $who[] = 'the supervisor';
        }
        $who[] = $review->route === PerformanceReview::ROUTE_HR ? 'HR (standing in as the supervisor)' : 'the HR person';
        $who[] = $min === 1 ? 'at least one committee member' : "at least {$min} committee members";

        return 'To be recorded as held, these must be there: '.implode(', ', $who).'.';
    }

    private function canBeTold(PerformanceMeetingAttendee $attendee): bool
    {
        if ($attendee->user_id) {
            $user = User::find($attendee->user_id);

            return $user !== null && (int) $user->block === 0 && $user->activation != 0;
        }
        $staff = $attendee->staff_id ? \App\Models\Staff::find($attendee->staff_id) : null;

        return $staff !== null && $this->access->hasLogin($staff);
    }

    private function appeal(PerformanceAppeal $appeal, PerformanceReview $review, bool $isHr): array
    {
        $items = $review->items;
        $out = [
            'id' => $appeal->id,
            'status' => $appeal->status,
            'status_label' => $appeal->isDecided() ? 'Decided' : 'Filed',
            'filed_at' => $appeal->created_at?->format('d M Y'),
            'on_behalf' => $appeal->on_behalf,
            'reason' => $appeal->reason,
            'criteria' => $appeal->items()->get()->map(function ($appealed) use ($items) {
                $item = $items->firstWhere('id', $appealed->review_item_id);

                return [
                    'item_id' => (int) $appealed->review_item_id,
                    'name' => $item?->name,
                    'reason' => $appealed->reason,
                    'self_rating' => $item?->self_rating,
                    'supervisor_rating' => $item?->supervisor_rating,
                    'hr_rating' => $item?->hr_rating,
                    'hr_comment' => $item?->hr_comment,
                ];
            })->values()->all(),
        ];

        if ($isHr && $appeal->late_reason) {
            $out['late_reason'] = $appeal->late_reason;
        }
        if ($appeal->isDecided()) {
            $out += [
                'outcome' => $appeal->outcome,
                'outcome_label' => PerformanceAppeal::OUTCOME_LABELS[$appeal->outcome] ?? $appeal->outcome,
                'decision_reason' => $appeal->decision_reason,
                'score_before' => $appeal->score_before,
                'score_after' => $appeal->score_after,
                'decided_at' => $appeal->decided_at?->format('d M Y'),
            ];
        }

        return $out;
    }

    /** Every criterion with the ratings it got: what the staff member picks from when they appeal. Only once finalised. */
    private function criteria(PerformanceReview $review): array
    {
        if (! $review->isFinalised()) {
            return [];
        }

        return $review->items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'self_rating' => $item->self_rating,
            'supervisor_rating' => $item->supervisor_rating,
            'hr_rating' => $item->hr_rating,
        ])->values()->all();
    }

    private function meetingRow(PerformanceMeeting $meeting): array
    {
        $review = $meeting->review;

        return [
            'review_id' => $review->id,
            'kind' => $meeting->kind,
            'kind_label' => $meeting->kind === PerformanceMeeting::KIND_APPEAL ? 'Appeal hearing' : 'Joint review meeting',
            'staff' => $review->staff?->full_names,
            'position' => $review->position_name,
            'cycle' => $review->cycle?->name,
            'status' => $meeting->isHeld() ? 'held' : 'scheduled',
            'status_label' => $meeting->isHeld() ? 'Held' : 'Scheduled',
            'when' => $meeting->scheduled_at?->format('d M Y H:i'),
            'venue' => $meeting->venue,
            'url' => $this->url($review->id, $meeting->kind),
            'sort' => (string) $meeting->scheduled_at,
        ];
    }

    private function pendingRow(PerformanceReview $review, string $kind): array
    {
        return [
            'review_id' => $review->id,
            'kind' => $kind,
            'kind_label' => $kind === PerformanceMeeting::KIND_APPEAL ? 'Appeal hearing' : 'Joint review meeting',
            'staff' => $review->staff?->full_names,
            'position' => $review->position_name,
            'cycle' => $review->cycle?->name,
            'status' => 'to_schedule',
            'status_label' => 'Not scheduled yet',
            'when' => null,
            'venue' => null,
            'url' => $this->url($review->id, $kind),
            'sort' => (string) ($review->updated_at ?? now()),
        ];
    }

    private function url(int $reviewId, string $kind): string
    {
        return $kind === PerformanceMeeting::KIND_APPEAL ? route('performance.appeal', $reviewId, false) : route('performance.meeting', $reviewId, false);
    }
}
