<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\PerformanceCycle;
use App\Models\PerformanceMeeting;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewEvent;
use App\Models\PerformanceSetting;
use App\Services\Performance\PerformanceAccess;
use App\Services\Performance\PerformanceException;
use App\Services\Performance\PerformanceMeetingPresenter;
use App\Services\Performance\PerformanceMeetingService;
use App\Services\Performance\PerformanceNotifications;
use App\Services\Performance\PerformanceReviewPresenter;
use App\Services\Performance\PerformanceReviewService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsPerformanceOrg;
use Tests\TestCase;

/**
 * The joint review meeting between the self-rating and the supervisor's rating (HR's "Performance Review Process"): HR
 * schedules it and picks the committee, everyone invited is told, and it can only be recorded as held when the staff member,
 * the supervisor, an HR person and at least the least number of committee members were there. See BuildsPerformanceOrg for the
 * company these tests use: staff 10 is supervised by 2; HR is user 1060 (staff 60) and the director is user 2000.
 */
class PerformanceMeetingTest extends TestCase
{
    use BuildsPerformanceOrg;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        $this->buildPerformanceSchema();
        $this->seedPerformanceOrg();
    }

    private function meetings(): PerformanceMeetingService
    {
        return app(PerformanceMeetingService::class);
    }

    private function service(): PerformanceReviewService
    {
        return app(PerformanceReviewService::class);
    }

    private function reviewOf(int $staffId): PerformanceReview
    {
        return PerformanceReview::where('staff_id', $staffId)->firstOrFail();
    }

    private function ratings(PerformanceReview $review, array $values): array
    {
        $out = [];
        foreach ($review->items()->get() as $index => $item) {
            $out[$item->id] = ['rating' => $values[$index] ?? null, 'comment' => null];
        }

        return $out;
    }

    private function refusal(callable $do): PerformanceException
    {
        try {
            $do();
        } catch (PerformanceException $exception) {
            return $exception;
        }
        $this->fail('Expected the action to be refused.');
    }

    /** Staff 10's review (supervised by 2, or by nobody when $supervisor is null) after the self-rating, waiting for the meeting. */
    private function reviewAwaitingMeeting(int $staffId = 10, ?int $supervisor = 2): PerformanceReview
    {
        if ($supervisor) {
            $this->nameSupervisor($staffId, $supervisor);
        }
        if (! PerformanceCycle::exists()) {
            $this->publishedCycle();
        }
        $review = $this->reviewOf($staffId);

        return $this->service()->saveSelf($review, $this->userFor($staffId), $this->ratings($review, [8, 6]), null, true);
    }

    private function plan(array $override = []): array
    {
        return array_merge(['scheduled_at' => '2027-02-01 10:00', 'venue' => 'Boardroom', 'hr_user_id' => 2000, 'committee_staff_ids' => [3]], $override);
    }

    private function schedule(PerformanceReview $review, array $override = []): PerformanceMeeting
    {
        return $this->meetings()->schedule($review, $this->hrUser(), PerformanceMeeting::KIND_REVIEW, $this->plan($override));
    }

    /** Everyone invited marked as present, except the roles in $absent. */
    private function came(PerformanceMeeting $meeting, array $absent = []): array
    {
        return $meeting->attendees()->get()->mapWithKeys(fn ($attendee) => [$attendee->id => in_array($attendee->role, $absent, true) ? 0 : 1])->all();
    }

    private function sections(): array
    {
        return ['performance_feedback' => ['notes' => 'Steady work, good with tenants']];
    }

    private function alertsOf(int $userId, ?string $type = null): int
    {
        return Notification::where('notifiable_id', $userId)->when($type, fn ($q) => $q->where('type', $type))->count();
    }

    // ── scheduling ────────────────────────────────────────────────────────

    public function test_hr_schedules_the_meeting_and_everyone_invited_is_told(): void
    {
        $review = $this->reviewAwaitingMeeting();
        Notification::query()->delete();

        $meeting = $this->schedule($review);

        $this->assertSame(PerformanceMeeting::STATUS_SCHEDULED, $meeting->status);
        $this->assertSame('Boardroom', $meeting->venue);
        $this->assertSame(PerformanceReview::STATUS_AWAITING_MEETING, $review->refresh()->status, 'the review waits for the meeting to be held');
        $this->assertEqualsCanonicalizing(['staff', 'supervisor', 'hr', 'committee'], $meeting->attendees()->pluck('role')->all());

        // The staff member (1010), the supervisor (1002), the HR person (2000) and the committee member (1003) are each told once
        foreach ([1010, 1002, 2000, 1003] as $userId) {
            $this->assertSame(1, $this->alertsOf($userId, PerformanceNotifications::MEETING_SCHEDULED), "user {$userId}");
        }
        $this->assertSame(4, Notification::count(), 'nobody else');
        $alert = Notification::where('notifiable_id', 1010)->first();
        $this->assertSame('Joint review meeting scheduled', $alert->title);
        $this->assertStringContainsString('Your joint review meeting', $alert->message);
        $this->assertStringContainsString('01 Feb 2027 at 10:00', $alert->message);
        $this->assertStringContainsString('Boardroom', $alert->message);
        $this->assertStringContainsString('Felix Officer', Notification::where('notifiable_id', 1002)->first()->message);
        $this->assertSame('/hr/performance/reviews/'.$review->id.'/meeting', $alert->action_url);
        $this->assertContains('meeting_scheduled', PerformanceReviewEvent::where('review_id', $review->id)->pluck('action')->all());
    }

    public function test_when_there_is_no_supervisor_hr_stands_in_and_there_is_no_supervisor_seat(): void
    {
        $review = $this->reviewAwaitingMeeting(11, null);
        $this->assertSame('hr', $review->route);

        $meeting = $this->schedule($review);

        $this->assertEqualsCanonicalizing(['staff', 'hr', 'committee'], $meeting->attendees()->pluck('role')->all());
    }

    public function test_only_hr_can_schedule_and_only_while_the_review_waits_for_the_meeting(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $try = fn ($user) => $this->refusal(fn () => $this->meetings()->schedule($review, $user, 'review', $this->plan()));

        foreach ([$this->userFor(10), $this->userFor(2), $this->userFor(3), $this->userFor(21)] as $someone) {
            $this->assertSame(403, $try($someone)->status());
        }

        $other = $this->reviewOf(11);
        $this->assertSame(422, $this->refusal(fn () => $this->meetings()->schedule($other, $this->hrUser(), 'review', $this->plan()))->status(), 'staff 11 has not submitted a self-rating yet');
    }

    public function test_the_details_must_be_complete_and_the_people_must_be_allowed(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $says = fn (array $override) => $this->refusal(fn () => $this->meetings()->schedule($review, $this->hrUser(), 'review', $this->plan($override)))->getMessage();

        $this->assertStringContainsString('date and time', $says(['scheduled_at' => '']));
        $this->assertStringContainsString('date and time', $says(['scheduled_at' => 'not a date']));
        $this->assertStringContainsString('where', $says(['venue' => '  ']));
        $this->assertStringContainsString('at least one committee member', $says(['committee_staff_ids' => []]));
        $this->assertStringContainsString('HR person', $says(['hr_user_id' => null]));
        $this->assertStringContainsString('not on the committee list', $says(['committee_staff_ids' => [30]]), 'someone who is not on the committee list');
        $this->assertStringContainsString('not on the committee list', $says(['committee_staff_ids' => [10]]), 'the staff member cannot sit on their own committee');
        $this->assertStringContainsString('not on the committee list', $says(['committee_staff_ids' => [2]]), 'neither can the supervisor');
        $this->assertStringContainsString('HR person', $says(['hr_user_id' => 1010]), 'a login that is not HR');
        $this->assertSame(0, PerformanceMeeting::count(), 'nothing was saved by a refused request');
    }

    public function test_the_hr_person_cannot_also_be_a_committee_member(): void
    {
        DB::table('performance_committee_members')->insert(['staff_id' => 60]);
        $review = $this->reviewAwaitingMeeting();

        $message = $this->refusal(fn () => $this->meetings()->schedule($review, $this->hrUser(), 'review', $this->plan(['hr_user_id' => 1060, 'committee_staff_ids' => [60]])))->getMessage();

        $this->assertStringContainsString('Hana HRManager is the HR person for this meeting, so cannot also be a committee member', $message);
    }

    public function test_the_hr_person_cannot_be_the_supervisor_of_the_review(): void
    {
        // The HR manager (staff 60) is the only holder of the position the accountant reports to, so supervises staff 21
        DB::table('sys_set_staff_positions')->where('id', self::POS_ACCOUNTANT)->update(['reports_to_id' => self::POS_HR]);
        $this->criteria(self::POS_ACCOUNTANT);
        app(\App\Services\Performance\PerformanceCycleService::class)->publish($this->draftCycle(), $this->hrUser());
        $review = $this->service()->saveSelf($this->reviewOf(21), $this->userFor(21), $this->ratings($this->reviewOf(21), [6, 6]), null, true);
        $this->assertSame(60, (int) $review->supervisor_staff_id);

        $options = $this->meetings()->options($review);

        $this->assertSame([2000], array_column($options['hr'], 'user_id'), 'only the director can be the HR person');
    }

    public function test_the_options_leave_out_the_staff_member_and_the_supervisor(): void
    {
        $review = $this->reviewAwaitingMeeting();

        $options = $this->meetings()->options($review);

        $this->assertSame([21, 20, 3], array_column($options['committee'], 'staff_id'), 'by name: Alan, Cora, Zola; not Felix (the staff member) or Zane (the supervisor)');
        $this->assertSame(1, $options['min_committee']);
    }

    // ── recording ─────────────────────────────────────────────────────────

    public function test_the_supervisor_records_the_meeting_and_the_review_goes_on_to_the_supervisor(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $meeting = $this->schedule($review);
        Notification::query()->delete();

        $held = $this->meetings()->record($review, $this->userFor(2), $this->came($meeting), [
            'skill_gaps' => ['notes' => 'Excel'],
            'training_needs' => ['notes' => 'Customer care course', 'approved' => '1'],
            'performance_feedback' => ['notes' => 'Steady work'],
            'goals' => ['notes' => 'Collect 95%', 'approved' => '0'],
        ], [['title' => 'Workload', 'notes' => 'Covers two zones']]);

        $this->assertSame(PerformanceMeeting::STATUS_HELD, $held->status);
        $this->assertNotNull($held->held_at);
        $this->assertSame(PerformanceReview::STATUS_AWAITING_SUPERVISOR, $review->refresh()->status);
        $this->assertSame([1, 1, 1, 1], $held->attendees()->pluck('present')->map(fn ($p) => (int) $p)->all());

        $items = $held->items()->get();
        $this->assertSame(['skill_gaps', 'training_needs', 'performance_feedback', 'goals', 'other'], $items->pluck('section')->all());
        $this->assertSame([false, true, false, false, false], $items->pluck('approved')->map(fn ($a) => (bool) $a)->all(), 'the committee approved the training needs only');
        $this->assertSame('Workload', $items->last()->title, 'an extra item beyond the four areas');

        $this->assertSame(1, $this->alertsOf(1010, PerformanceNotifications::MEETING_HELD), 'the staff member can read the feedback');
        $this->assertSame(1, Notification::count(), 'the supervisor recorded it, so is not asked again');
        $this->assertSame(['created', 'self_submitted', 'meeting_scheduled', 'meeting_held'], PerformanceReviewEvent::where('review_id', $review->id)->pluck('action')->all());
    }

    public function test_when_there_is_no_supervisor_hr_records_it_and_the_review_goes_to_hr(): void
    {
        $review = $this->reviewAwaitingMeeting(11, null);
        $meeting = $this->schedule($review);
        Notification::query()->delete();

        $this->meetings()->record($review, $this->hrUser(), $this->came($meeting), $this->sections(), []);

        $this->assertSame(PerformanceReview::STATUS_AWAITING_HR, $review->refresh()->status);
        $this->assertSame(1, $this->alertsOf(2000, PerformanceNotifications::WAITING_FOR_HR), 'HR is asked for the final rating');
    }

    public function test_who_may_record_it(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $meeting = $this->schedule($review);
        $try = fn ($user) => $this->refusal(fn () => $this->meetings()->record($review, $user, $this->came($meeting), $this->sections(), []))->status();

        $this->assertSame(403, $try($this->userFor(10)), 'the staff member');
        $this->assertSame(403, $try($this->hrUser()), 'HR does not record it when there is a supervisor');
        $this->assertSame(403, $try($this->userFor(3)), 'a committee member');

        $unscheduled = $this->reviewAwaitingMeeting(11, null);
        $this->assertSame(422, $this->refusal(fn () => $this->meetings()->record($unscheduled, $this->hrUser(), [], $this->sections(), []))->status(), 'schedule it first');
    }

    public function test_it_is_only_held_when_the_staff_member_the_supervisor_hr_and_a_committee_member_came(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $meeting = $this->schedule($review);
        $record = fn (array $absent) => $this->refusal(fn () => $this->meetings()->record($review, $this->userFor(2), $this->came($meeting, $absent), $this->sections(), []))->getMessage();

        $this->assertStringContainsString('The staff member must be there', $record(['staff']));
        $this->assertStringContainsString('The supervisor must be there', $record(['supervisor']));
        $this->assertStringContainsString('The HR person must be there', $record(['hr']));
        $this->assertStringContainsString('At least one committee member must be there', $record(['committee']));
        $this->assertStringContainsString('reschedule', $record(['staff', 'hr']));

        $this->assertSame(PerformanceMeeting::STATUS_SCHEDULED, $meeting->refresh()->status, 'nothing was recorded');
        $this->assertSame(PerformanceReview::STATUS_AWAITING_MEETING, $review->refresh()->status);
        $this->assertSame(0, $meeting->items()->count());
    }

    public function test_with_no_supervisor_the_hr_person_must_be_there_standing_in(): void
    {
        $review = $this->reviewAwaitingMeeting(11, null);
        $meeting = $this->schedule($review);

        $message = $this->refusal(fn () => $this->meetings()->record($review, $this->hrUser(), $this->came($meeting, ['hr']), $this->sections(), []))->getMessage();

        $this->assertStringContainsString('HR must be there (HR stands in as the supervisor)', $message);
    }

    public function test_hr_can_raise_how_many_committee_members_must_attend(): void
    {
        PerformanceSetting::put(PerformanceSetting::MIN_COMMITTEE, 2);
        $review = $this->reviewAwaitingMeeting();

        $this->assertStringContainsString('at least 2 committee members', $this->refusal(fn () => $this->schedule($review, ['committee_staff_ids' => [3]]))->getMessage());

        $meeting = $this->schedule($review, ['committee_staff_ids' => [3, 20]]);
        $message = $this->refusal(fn () => $this->meetings()->record($review, $this->userFor(2), array_replace($this->came($meeting), [$meeting->attendees()->where('staff_id', 20)->value('id') => 0]), $this->sections(), []))->getMessage();
        $this->assertStringContainsString('At least 2 committee members must be there', $message);

        $this->meetings()->record($review, $this->userFor(2), $this->came($meeting), $this->sections(), []);
        $this->assertSame(PerformanceReview::STATUS_AWAITING_SUPERVISOR, $review->refresh()->status);
    }

    public function test_someone_who_was_not_invited_cannot_be_marked_present(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $meeting = $this->schedule($review);

        $message = $this->refusal(fn () => $this->meetings()->record($review, $this->userFor(2), $this->came($meeting) + [9999 => 1], $this->sections(), []))->getMessage();

        $this->assertStringContainsString('not invited', $message);
    }

    public function test_the_performance_feedback_is_required_and_extra_items_need_a_title_and_notes(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $meeting = $this->schedule($review);
        $record = fn (array $sections, array $extras = []) => $this->refusal(fn () => $this->meetings()->record($review, $this->userFor(2), $this->came($meeting), $sections, $extras))->getMessage();

        $this->assertStringContainsString('performance feedback', $record(['skill_gaps' => ['notes' => 'Excel']]));
        $this->assertStringContainsString('performance feedback', $record(['performance_feedback' => ['notes' => '   ']]));
        $this->assertStringContainsString('title', $record($this->sections(), [['title' => '', 'notes' => 'Something']]));
        $this->assertStringContainsString('Write what was discussed under "Workload"', $record($this->sections(), [['title' => 'Workload', 'notes' => '']]));
        $this->assertStringContainsString('At most 10', $record($this->sections(), array_fill(0, 11, ['title' => 'x', 'notes' => 'y'])));
        $this->assertStringContainsString('4000', $record(['performance_feedback' => ['notes' => str_repeat('x', 4001)]]));

        // Empty extra rows (a box left blank) are simply ignored
        $this->meetings()->record($review, $this->userFor(2), $this->came($meeting), $this->sections(), [['title' => '', 'notes' => ''], ['title' => 'Workload', 'notes' => 'Two zones']]);
        $this->assertSame(['performance_feedback', 'other'], $meeting->items()->pluck('section')->all());
    }

    public function test_the_supervisor_cannot_rate_before_the_meeting_is_held(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $this->schedule($review);

        $exception = $this->refusal(fn () => $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [5, 5]), null, true));
        $this->assertSame('This review is not waiting for the supervisor.', $exception->getMessage());

        $exception = $this->refusal(fn () => $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [5, 5]), null, true));
        $this->assertSame('This review is not waiting for HR.', $exception->getMessage());
    }

    // ── rescheduling ──────────────────────────────────────────────────────

    public function test_hr_reschedules_with_a_reason_and_everyone_is_told_again(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $meeting = $this->schedule($review);
        Notification::query()->delete();

        $moved = $this->meetings()->reschedule($review, $this->hrUser(), 'review', $this->plan(['scheduled_at' => '2027-02-08 14:30', 'venue' => 'Room 2', 'committee_staff_ids' => [20]]), 'The supervisor is travelling');

        $this->assertSame($meeting->id, $moved->id, 'the same meeting moved, not a second one');
        $this->assertSame(1, (int) $moved->reschedule_count);
        $this->assertSame('Room 2', $moved->venue);
        $this->assertSame([20], $moved->attendees()->where('role', 'committee')->pluck('staff_id')->map(fn ($id) => (int) $id)->all(), 'the committee was replaced');
        $this->assertSame(0, $moved->attendees()->where('present', true)->count());

        foreach ([1010, 1002, 2000, 1020] as $userId) {
            $this->assertSame(1, $this->alertsOf($userId, PerformanceNotifications::MEETING_SCHEDULED), "user {$userId}");
        }
        $this->assertSame(0, $this->alertsOf(1003), 'no longer on the committee for it');
        $alert = Notification::where('notifiable_id', 1010)->first();
        $this->assertSame('Joint review meeting rescheduled', $alert->title);
        $this->assertStringContainsString('now on 08 Feb 2027 at 14:30', $alert->message);

        $event = PerformanceReviewEvent::where('action', 'meeting_rescheduled')->first();
        $this->assertSame('The supervisor is travelling', $event->note);
    }

    public function test_rescheduling_needs_a_reason_and_is_not_possible_once_held(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $meeting = $this->schedule($review);

        $this->assertStringContainsString('Say why', $this->refusal(fn () => $this->meetings()->reschedule($review, $this->hrUser(), 'review', $this->plan(), '  '))->getMessage());
        $this->assertSame(403, $this->refusal(fn () => $this->meetings()->reschedule($review, $this->userFor(2), 'review', $this->plan(), 'x'))->status());

        $this->meetings()->record($review, $this->userFor(2), $this->came($meeting), $this->sections(), []);
        $this->assertSame(422, $this->refusal(fn () => $this->meetings()->reschedule($review->refresh(), $this->hrUser(), 'review', $this->plan(), 'x'))->status());
    }

    public function test_a_meeting_cannot_be_scheduled_twice(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $this->schedule($review);

        $this->assertStringContainsString('Reschedule it instead', $this->refusal(fn () => $this->schedule($review))->getMessage());
    }

    public function test_changing_the_supervisor_before_the_meeting_changes_who_is_invited(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $meeting = $this->schedule($review, ['committee_staff_ids' => [20]]);

        $this->service()->reassign($review, $this->hrUser(), 3, 'Zane is away');

        $this->assertSame([3], $meeting->attendees()->where('role', 'supervisor')->pluck('staff_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(1, $this->alertsOf(1003, PerformanceNotifications::MEETING_SCHEDULED), 'the new supervisor is told about the meeting');

        // When HR takes over there is no supervisor seat any more
        $this->service()->reassign($review->refresh(), $this->hrUser(), null, 'HR will rate it');
        $this->assertSame(0, $meeting->attendees()->where('role', 'supervisor')->count());
    }

    public function test_the_new_supervisor_cannot_already_be_invited_as_committee(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $this->schedule($review, ['committee_staff_ids' => [3]]);

        $message = $this->refusal(fn () => $this->service()->reassign($review, $this->hrUser(), 3, 'Zane is away'))->getMessage();

        $this->assertStringContainsString('already invited', $message);
        $this->assertSame(2, (int) $review->refresh()->supervisor_staff_id, 'nothing changed');
    }

    // ── who sees what ─────────────────────────────────────────────────────

    public function test_the_staff_member_reads_the_meeting_feedback_once_it_is_recorded(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $meeting = $this->schedule($review);
        $page = fn ($viewer) => app(PerformanceMeetingPresenter::class)->page($viewer, $review->refresh(), 'review');

        $this->assertSame([], $page($this->userFor(10))['meeting']['items'], 'nothing to read before it is held');

        $this->meetings()->record($review, $this->userFor(2), $this->came($meeting), ['performance_feedback' => ['notes' => 'MEETING WORDS'], 'training_needs' => ['notes' => 'Course', 'approved' => 1]], [['title' => 'Workload', 'notes' => 'EXTRA WORDS']]);

        $mine = $page($this->userFor(10));
        $this->assertSame('subject', $mine['role']);
        $this->assertEqualsCanonicalizing(['Training needs', 'Performance feedback', 'Workload'], array_column($mine['meeting']['items'], 'title'));
        $this->assertStringContainsString('MEETING WORDS', json_encode($mine));
        $this->assertStringContainsString('EXTRA WORDS', json_encode($mine));
        $this->assertFalse($mine['actions']['record'], 'they cannot change it');
        $this->assertArrayNotHasKey('options', $mine);
        $this->assertArrayNotHasKey('not_reached', $mine['meeting'], 'only HR sees who could not be told');
    }

    public function test_hr_sees_who_the_app_could_not_tell(): void
    {
        DB::table('performance_committee_members')->insert(['staff_id' => 1]); // Mary Director has no login
        $review = $this->reviewAwaitingMeeting();
        $this->schedule($review, ['committee_staff_ids' => [3, 1]]);

        $page = app(PerformanceMeetingPresenter::class)->page($this->hrUser(), $review->refresh(), 'review');

        $this->assertSame(['Mary Director'], $page['meeting']['not_reached']);
        $this->assertTrue($page['actions']['reschedule']);
        $this->assertArrayHasKey('options', $page);
    }

    public function test_a_committee_member_can_read_the_review_they_sit_on_and_cannot_rate_it(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $access = new PerformanceAccess;
        $this->assertSame('You do not have access to this review.', $access->denial($this->userFor(3), $review, 'view')['error'], 'not invited yet');

        $this->schedule($review, ['committee_staff_ids' => [3]]);

        $member = $this->userFor(3);
        $this->assertSame(PerformanceAccess::ROLE_COMMITTEE, $access->role($member, $review));
        $this->assertNull($access->denial($member, $review, 'view'));
        $this->assertSame([10], $access->visibleTo($member)->pluck('staff_id')->map(fn ($id) => (int) $id)->all());
        foreach (['self', 'supervisor', 'hr', 'schedule_meeting', 'record_meeting', 'return', 'correct'] as $action) {
            $this->assertNotNull($access->denial($member, $review, $action), $action);
        }

        $other = $this->userFor(20); // on the committee list but not invited to this one
        $this->assertNotNull($access->denial($other, $review, 'view'));
    }

    public function test_a_committee_member_sees_the_self_rating_once_submitted_but_never_a_draft(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8, 6]), null, true);
        $this->schedule($review);

        $payload = app(PerformanceReviewPresenter::class)->show($this->userFor(3), $review->refresh());

        $this->assertSame('committee', $payload['role']);
        $this->assertSame([8, 6], array_column($payload['items'], 'self_rating'));
        $this->assertSame([], $payload['actions']);
        $this->assertArrayNotHasKey('history', $payload);
        $this->assertArrayNotHasKey('supervisor_candidates', $payload);
    }

    public function test_the_meetings_list_shows_each_person_only_their_own_and_hr_what_is_still_to_schedule(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $waiting = $this->reviewAwaitingMeeting(11, null);
        $this->schedule($review);
        $rows = fn ($viewer) => app(PerformanceMeetingPresenter::class)->meetingRows($viewer);

        $this->assertSame(['scheduled'], array_column($rows($this->userFor(10)), 'status'), 'the staff member');
        $this->assertSame(['scheduled'], array_column($rows($this->userFor(2)), 'status'), 'the supervisor');
        $this->assertSame(['scheduled'], array_column($rows($this->userFor(3)), 'status'), 'the committee member');
        $this->assertSame([], $rows($this->userFor(21)), 'someone not invited');
        $this->assertSame([], $rows($this->userFor(11)), 'staff 11 has no meeting yet');

        $hr = $rows($this->hrUser());
        $this->assertSame(['to_schedule', 'scheduled'], array_column($hr, 'status'), 'HR sees what still has to be scheduled first');
        $this->assertSame($waiting->id, $hr[0]['review_id']);
    }

    public function test_a_review_that_has_not_reached_the_meeting_has_no_meeting_actions_for_anyone(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10); // still waiting for the self-rating
        $access = new PerformanceAccess;

        $this->assertSame(422, $access->denial($this->hrUser(), $review, 'schedule_meeting')['status']);
        $this->assertSame(422, $access->denial($this->userFor(2), $review, 'record_meeting')['status']);
    }
}
