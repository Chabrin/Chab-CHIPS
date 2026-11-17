<?php

namespace App\Services\Performance;

use App\Models\PerformanceCommitteeMember;
use App\Models\PerformanceMeeting;
use App\Models\PerformanceMeetingAttendee;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewEvent;
use App\Models\PerformanceSetting;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The joint review meeting (and the appeal hearing, which works the same way): HR schedules it and picks the committee, the
 * people are told, and once it has happened the supervisor (or HR when the review goes straight to HR) records who came and
 * what was discussed. It can only be recorded as held when the people it needs were there:
 *
 *   - the staff member,
 *   - the supervisor (HR stands in when the review goes straight to HR),
 *   - an HR person,
 *   - and at least the least number of committee members HR has set (one until HR says otherwise).
 *
 * If someone did not come, HR reschedules it. Each step first asks PerformanceAccess whether this person may, does its work in
 * one transaction, and writes an event so the review keeps its history.
 */
class PerformanceMeetingService
{
    private const NOTES_MAX = 4000;

    private const TITLE_MAX = 150;

    private const VENUE_MAX = 150;

    private const EXTRA_MAX = 10;

    public function __construct(private PerformanceAccess $access, private PerformanceNotifications $notifications) {}

    // ── scheduling ────────────────────────────────────────────────────────

    /**
     * HR sets the date, venue, HR person and committee. $data: scheduled_at (date and time), venue, hr_user_id and
     * committee_staff_ids.
     */
    public function schedule(PerformanceReview $review, User $actor, string $kind, array $data): PerformanceMeeting
    {
        $this->allow($actor, $review, $this->scheduleAction($kind));
        if ($this->access->meetingOf($review, $kind)) {
            throw new PerformanceException('This is already scheduled. Reschedule it instead.', 422);
        }
        $plan = $this->plan($review, $data);

        $meeting = DB::transaction(function () use ($review, $actor, $kind, $plan) {
            $meeting = PerformanceMeeting::create([
                'review_id' => $review->id,
                'kind' => $kind,
                'appeal_id' => $kind === PerformanceMeeting::KIND_APPEAL ? $this->access->appealOf($review)?->id : null,
                'status' => PerformanceMeeting::STATUS_SCHEDULED,
                'scheduled_at' => $plan['when'],
                'venue' => $plan['venue'],
                'hr_user_id' => $plan['hr']->id,
                'scheduled_by' => $actor->id,
            ]);
            $this->invite($meeting, $review, $plan);

            PerformanceReviewEvent::record($review, $kind === PerformanceMeeting::KIND_REVIEW ? 'meeting_scheduled' : 'hearing_scheduled', $actor->id, [
                'from_status' => $review->status,
                'details' => ['meeting_id' => $meeting->id, 'scheduled_at' => $plan['when']->format('Y-m-d H:i'), 'venue' => $plan['venue']],
            ]);

            return $meeting;
        });

        $this->notifications->meetingScheduled($meeting->refresh(), false);

        return $meeting;
    }

    /** HR moves a meeting that has not been held yet, because someone could not come or the details changed. Everyone is told. */
    public function reschedule(PerformanceReview $review, User $actor, string $kind, array $data, string $reason): PerformanceMeeting
    {
        $this->allow($actor, $review, $this->scheduleAction($kind));
        $meeting = $this->access->meetingOf($review, $kind);
        if (! $meeting) {
            throw new PerformanceException('Nothing is scheduled yet. Schedule it first.', 422);
        }
        if ($meeting->isHeld()) {
            throw new PerformanceException('This has already been held, so it cannot be moved.', 422);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new PerformanceException('Say why it is being rescheduled.', 422);
        }
        $plan = $this->plan($review, $data);

        $saved = DB::transaction(function () use ($review, $actor, $kind, $meeting, $plan, $reason) {
            $before = ['scheduled_at' => $meeting->scheduled_at?->format('Y-m-d H:i'), 'venue' => $meeting->venue];

            $meeting->update([
                'scheduled_at' => $plan['when'],
                'venue' => $plan['venue'],
                'hr_user_id' => $plan['hr']->id,
                'scheduled_by' => $actor->id,
                'reschedule_count' => (int) $meeting->reschedule_count + 1,
            ]);
            $meeting->attendees()->delete();
            $this->invite($meeting, $review, $plan);

            PerformanceReviewEvent::record($review, $kind === PerformanceMeeting::KIND_REVIEW ? 'meeting_rescheduled' : 'hearing_rescheduled', $actor->id, [
                'from_status' => $review->status,
                'note' => $reason,
                'details' => ['meeting_id' => $meeting->id, 'from' => $before, 'to' => ['scheduled_at' => $plan['when']->format('Y-m-d H:i'), 'venue' => $plan['venue']]],
            ]);

            return $meeting->refresh();
        });

        $this->notifications->meetingScheduled($saved, true);

        return $saved;
    }

    // ── recording ─────────────────────────────────────────────────────────

    /**
     * Records the joint review meeting: who came, the four standard areas (the committee approves the training needs and the
     * goals) and any extra items. The review then goes on to the supervisor, or to HR when it has no supervisor.
     *
     * @param  array<int, mixed>  $attendance  attendee id => came or not
     * @param  array<string, array{notes?: mixed, approved?: mixed}>  $sections  keyed by skill_gaps, training_needs, performance_feedback, goals
     * @param  array<int, array{title?: mixed, notes?: mixed}>  $extras  anything else that was discussed
     */
    public function record(PerformanceReview $review, User $actor, array $attendance, array $sections, array $extras): PerformanceMeeting
    {
        $this->allow($actor, $review, 'record_meeting');
        $meeting = $this->access->meetingOf($review, PerformanceMeeting::KIND_REVIEW);
        $present = $this->checkAttendance($meeting, $attendance);
        $items = $this->cleanItems($sections, $extras);

        $saved = DB::transaction(function () use ($review, $actor, $meeting, $present, $items) {
            $from = $review->status;
            $this->markHeld($meeting, $present, $actor);

            $meeting->items()->delete();
            foreach ($items as $index => $item) {
                $meeting->items()->create($item + ['sort_order' => $index + 1]);
            }

            $review->status = $review->route === PerformanceReview::ROUTE_SUPERVISOR
                ? PerformanceReview::STATUS_AWAITING_SUPERVISOR
                : PerformanceReview::STATUS_AWAITING_HR;
            $review->save();

            PerformanceReviewEvent::record($review, 'meeting_held', $actor->id, [
                'from_status' => $from,
                'details' => ['meeting_id' => $meeting->id, 'attended' => $meeting->attendees()->where('present', true)->pluck('name')->all()],
            ]);

            return $meeting->refresh();
        });

        $this->notifications->meetingHeld($saved);

        return $saved;
    }

    /**
     * Whether the people it needs were there. Returns the attendee ids that came; throws, saying who is missing, when the
     * meeting cannot be recorded as held.
     *
     * @param  array<int, mixed>  $attendance  attendee id => came or not
     * @return int[]
     */
    public function checkAttendance(PerformanceMeeting $meeting, array $attendance): array
    {
        $attendees = $meeting->attendees()->get()->keyBy('id');
        $present = [];
        foreach ($attendance as $attendeeId => $came) {
            if (! $attendees->has((int) $attendeeId)) {
                throw new PerformanceException('Someone who was not invited was marked as present.', 422);
            }
            if (filter_var($came, FILTER_VALIDATE_BOOLEAN)) {
                $present[] = (int) $attendeeId;
            }
        }

        $missing = $this->missing($meeting, $attendees->filter(fn ($attendee) => in_array((int) $attendee->id, $present, true)));
        if ($missing !== []) {
            throw new PerformanceException('This cannot be recorded as held yet. '.implode(' ', $missing).' If someone could not come, reschedule it.', 422);
        }

        return $present;
    }

    /** What is still missing for the meeting to count: the seats that were not filled, as sentences. */
    public function missing(PerformanceMeeting $meeting, $presentAttendees): array
    {
        $review = $meeting->review ?? PerformanceReview::find($meeting->review_id);
        $all = $meeting->attendees()->get();
        $came = fn (string $role) => $presentAttendees->contains(fn ($attendee) => $attendee->role === $role);
        $missing = [];

        if (! $came(PerformanceMeetingAttendee::ROLE_STAFF)) {
            $missing[] = 'The staff member must be there.';
        }
        if ($all->contains(fn ($attendee) => $attendee->role === PerformanceMeetingAttendee::ROLE_SUPERVISOR) && ! $came(PerformanceMeetingAttendee::ROLE_SUPERVISOR)) {
            $missing[] = 'The supervisor must be there.';
        }
        if (! $came(PerformanceMeetingAttendee::ROLE_HR)) {
            $missing[] = $review && $review->route === PerformanceReview::ROUTE_HR ? 'HR must be there (HR stands in as the supervisor).' : 'The HR person must be there.';
        }
        $needed = $this->minCommittee();
        $committee = $presentAttendees->filter(fn ($attendee) => $attendee->role === PerformanceMeetingAttendee::ROLE_COMMITTEE)->count();
        if ($committee < $needed) {
            $missing[] = $needed === 1 ? 'At least one committee member must be there.' : "At least {$needed} committee members must be there.";
        }

        return $missing;
    }

    /** Marks who came and flips the meeting to held. The caller has already checked the attendance and runs this in a transaction. */
    public function markHeld(PerformanceMeeting $meeting, array $presentIds, User $actor): void
    {
        foreach ($meeting->attendees()->get() as $attendee) {
            $attendee->update(['present' => in_array((int) $attendee->id, $presentIds, true)]);
        }
        $meeting->update(['status' => PerformanceMeeting::STATUS_HELD, 'held_at' => now(), 'recorded_by' => $actor->id]);
    }

    // ── a supervisor change while a meeting is waiting ────────────────────

    /** Keeps the invited supervisor in step when HR changes the supervisor before the meeting has been held. */
    public function syncSupervisor(PerformanceReview $review): void
    {
        $meeting = $this->access->meetingOf($review, PerformanceMeeting::KIND_REVIEW);
        if (! $meeting || $meeting->isHeld()) {
            return;
        }

        $meeting->attendees()->where('role', PerformanceMeetingAttendee::ROLE_SUPERVISOR)->delete();
        if ($review->route !== PerformanceReview::ROUTE_SUPERVISOR || ! $review->supervisor_staff_id) {
            return;
        }

        $supervisor = Staff::find($review->supervisor_staff_id);
        $clash = $meeting->attendees()->whereIn('role', [PerformanceMeetingAttendee::ROLE_HR, PerformanceMeetingAttendee::ROLE_COMMITTEE])
            ->where('staff_id', $review->supervisor_staff_id)->exists();
        if ($clash) {
            throw new PerformanceException(($supervisor?->full_names ?? 'The new supervisor').' is already invited to the meeting as HR or committee. Reschedule the meeting with someone else first.', 422);
        }

        $meeting->attendees()->create([
            'role' => PerformanceMeetingAttendee::ROLE_SUPERVISOR,
            'staff_id' => $review->supervisor_staff_id,
            'name' => $supervisor?->full_names ?? 'Supervisor',
        ]);
    }

    // ── what HR can pick from ─────────────────────────────────────────────

    /**
     * The committee members and HR people who may be invited to a meeting about this review: never the staff member, never the
     * supervisor (they come as themselves), and committee members must be active.
     *
     * @return array{committee: array<int, array>, hr: array<int, array>, min_committee: int}
     */
    public function options(PerformanceReview $review): array
    {
        $excluded = array_filter([(int) $review->staff_id, $review->route === PerformanceReview::ROUTE_SUPERVISOR ? (int) $review->supervisor_staff_id : 0]);

        $committee = PerformanceCommitteeMember::with('staff')->get()
            ->filter(fn ($member) => $member->staff && (int) $member->staff->current_status_id === 1 && ! in_array((int) $member->staff_id, $excluded, true))
            ->map(fn ($member) => ['staff_id' => (int) $member->staff_id, 'name' => $member->staff->full_names, 'has_login' => $this->access->hasLogin($member->staff)])
            ->sortBy('name')->values()->all();

        $hr = $this->access->hrUsers()
            ->filter(fn (User $user) => ! in_array((int) ($user->linkedStaff()?->id ?? 0), $excluded, true))
            ->map(fn (User $user) => ['user_id' => (int) $user->id, 'name' => $this->nameOfUser($user)])
            ->values()->all();

        return ['committee' => $committee, 'hr' => $hr, 'min_committee' => $this->minCommittee()];
    }

    public function minCommittee(): int
    {
        return max(1, PerformanceSetting::number(PerformanceSetting::MIN_COMMITTEE));
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function scheduleAction(string $kind): string
    {
        return $kind === PerformanceMeeting::KIND_APPEAL ? 'schedule_hearing' : 'schedule_meeting';
    }

    private function allow(User $actor, PerformanceReview $review, string $action): void
    {
        $denial = $this->access->denial($actor, $review, $action);
        if ($denial) {
            throw new PerformanceException($denial['error'], $denial['status']);
        }
    }

    /**
     * Checks what HR chose and turns it into what to save.
     *
     * @return array{when: Carbon, venue: string, hr: User, hr_name: string, committee: array<int, Staff>}
     */
    private function plan(PerformanceReview $review, array $data): array
    {
        try {
            $when = Carbon::parse((string) ($data['scheduled_at'] ?? ''));
        } catch (\Throwable) {
            throw new PerformanceException('Give the date and time of the meeting.', 422);
        }
        if (trim((string) ($data['scheduled_at'] ?? '')) === '') {
            throw new PerformanceException('Give the date and time of the meeting.', 422);
        }

        $venue = trim((string) ($data['venue'] ?? ''));
        if ($venue === '') {
            throw new PerformanceException('Say where the meeting will be held.', 422);
        }
        if (mb_strlen($venue) > self::VENUE_MAX) {
            throw new PerformanceException('The venue can be at most '.self::VENUE_MAX.' characters.', 422);
        }

        $options = $this->options($review);

        $committeeIds = collect((array) ($data['committee_staff_ids'] ?? []))->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $needed = $options['min_committee'];
        if ($committeeIds->count() < $needed) {
            throw new PerformanceException($needed === 1 ? 'Pick at least one committee member.' : "Pick at least {$needed} committee members.", 422);
        }
        $allowed = collect($options['committee'])->keyBy('staff_id');
        foreach ($committeeIds as $id) {
            if (! $allowed->has($id)) {
                throw new PerformanceException('Someone picked is not on the committee list, or is the staff member or supervisor of this review.', 422);
            }
        }

        $hrId = (int) ($data['hr_user_id'] ?? 0);
        $hrOption = collect($options['hr'])->firstWhere('user_id', $hrId);
        if (! $hrOption) {
            throw new PerformanceException('Pick the HR person who will be at the meeting. They cannot be the staff member or the supervisor.', 422);
        }
        $hr = User::find($hrId);
        $hrStaffId = (int) ($hr->linkedStaff()?->id ?? 0);
        if ($hrStaffId && $committeeIds->contains($hrStaffId)) {
            throw new PerformanceException($hrOption['name'].' is the HR person for this meeting, so cannot also be a committee member.', 422);
        }

        return [
            'when' => $when,
            'venue' => $venue,
            'hr' => $hr,
            'hr_name' => $hrOption['name'],
            'committee' => Staff::whereIn('id', $committeeIds->all())->orderBy('full_names')->get()->all(),
        ];
    }

    /** Writes the invitations: the staff member, the supervisor, the HR person and each committee member. */
    private function invite(PerformanceMeeting $meeting, PerformanceReview $review, array $plan): void
    {
        $review->loadMissing(['staff', 'supervisor']);
        $rows = [['role' => PerformanceMeetingAttendee::ROLE_STAFF, 'staff_id' => $review->staff_id, 'name' => $review->staff?->full_names ?? 'Staff member']];
        if ($review->route === PerformanceReview::ROUTE_SUPERVISOR && $review->supervisor_staff_id) {
            $rows[] = ['role' => PerformanceMeetingAttendee::ROLE_SUPERVISOR, 'staff_id' => $review->supervisor_staff_id, 'name' => $review->supervisor?->full_names ?? 'Supervisor'];
        }
        $rows[] = ['role' => PerformanceMeetingAttendee::ROLE_HR, 'user_id' => $plan['hr']->id, 'staff_id' => $plan['hr']->linkedStaff()?->id, 'name' => $plan['hr_name']];
        foreach ($plan['committee'] as $member) {
            $rows[] = ['role' => PerformanceMeetingAttendee::ROLE_COMMITTEE, 'staff_id' => $member->id, 'name' => $member->full_names];
        }

        foreach ($rows as $row) {
            $meeting->attendees()->create($row);
        }
    }

    /**
     * The four standard areas and the extra items, ready to save. The performance feedback must be written; the other areas
     * can be left empty when nothing was discussed under them.
     *
     * @return array<int, array{section: string, title: string, notes: string, approved: bool}>
     */
    private function cleanItems(array $sections, array $extras): array
    {
        $items = [];
        foreach (PerformanceMeeting::SECTIONS as $key => $label) {
            $entry = (array) ($sections[$key] ?? []);
            $notes = $this->text($entry['notes'] ?? null, $label);
            if ($key === 'performance_feedback' && $notes === null) {
                throw new PerformanceException('Write the performance feedback that was given at the meeting.', 422);
            }
            if ($notes === null) {
                continue;
            }
            $approved = in_array($key, PerformanceMeeting::APPROVABLE, true) && filter_var($entry['approved'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $items[] = ['section' => $key, 'title' => $label, 'notes' => $notes, 'approved' => $approved];
        }

        $extras = array_values(array_filter($extras, fn ($extra) => is_array($extra) && (trim((string) ($extra['title'] ?? '')) !== '' || trim((string) ($extra['notes'] ?? '')) !== '')));
        if (count($extras) > self::EXTRA_MAX) {
            throw new PerformanceException('At most '.self::EXTRA_MAX.' extra items can be added.', 422);
        }
        foreach ($extras as $extra) {
            $title = trim((string) ($extra['title'] ?? ''));
            if ($title === '') {
                throw new PerformanceException('Give each extra item a title.', 422);
            }
            if (mb_strlen($title) > self::TITLE_MAX) {
                throw new PerformanceException('An extra item\'s title can be at most '.self::TITLE_MAX.' characters.', 422);
            }
            $notes = $this->text($extra['notes'] ?? null, $title);
            if ($notes === null) {
                throw new PerformanceException("Write what was discussed under \"{$title}\".", 422);
            }
            $items[] = ['section' => PerformanceMeeting::OTHER, 'title' => $title, 'notes' => $notes, 'approved' => false];
        }

        return $items;
    }

    private function text($value, string $label): ?string
    {
        $text = trim(is_scalar($value) ? (string) $value : '');
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > self::NOTES_MAX) {
            throw new PerformanceException("{$label} can be at most ".self::NOTES_MAX.' characters.', 422);
        }

        return $text;
    }

    public function nameOfUser(User $user): string
    {
        return $user->linkedStaff()?->full_names ?? ($user->name ?: 'HR');
    }
}
