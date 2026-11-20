<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\PerformanceAppeal;
use App\Models\PerformanceCycle;
use App\Models\PerformanceMeeting;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewEvent;
use App\Models\PerformanceSetting;
use App\Services\Performance\PerformanceAccess;
use App\Services\Performance\PerformanceAppealService;
use App\Services\Performance\PerformanceException;
use App\Services\Performance\PerformanceMeetingPresenter;
use App\Services\Performance\PerformanceMeetingService;
use App\Services\Performance\PerformanceNotifications;
use App\Services\Performance\PerformanceReviewService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsPerformanceOrg;
use Tests\TestCase;

/**
 * The appeal against a finalised review: the staff member files it (HR can for them), HR schedules a hearing with the review
 * committee, and HR records the committee's decision, which is final. See BuildsPerformanceOrg for the company these tests use:
 * staff 10 is supervised by 2; HR is user 1060 (staff 60) and the director is user 2000.
 */
class PerformanceAppealTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function appeals(): PerformanceAppealService
    {
        return app(PerformanceAppealService::class);
    }

    private function meetings(): PerformanceMeetingService
    {
        return app(PerformanceMeetingService::class);
    }

    private function reviews(): PerformanceReviewService
    {
        return app(PerformanceReviewService::class);
    }

    private function ratings(PerformanceReview $review, array $values, ?string $comment = null): array
    {
        $out = [];
        foreach ($review->items()->get() as $index => $item) {
            $out[$item->id] = ['rating' => $values[$index] ?? null, 'comment' => $comment];
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

    /** A finalised review: the staff member rated 9 and 9, the supervisor 7 and 5, HR 8 and 6 (a final score of 7). */
    private function finalisedReview(int $staffId = 10, ?int $supervisor = 2): PerformanceReview
    {
        if ($supervisor) {
            $this->nameSupervisor($staffId, $supervisor);
        }
        $this->publishedCycle();
        $review = PerformanceReview::where('staff_id', $staffId)->firstOrFail();
        $review = $this->reviews()->saveSelf($review, $this->userFor($staffId), $this->ratings($review, [9, 9]), null, true);
        $review = $this->holdMeeting($review);
        if ($review->route === PerformanceReview::ROUTE_SUPERVISOR) {
            $review = $this->reviews()->saveSupervisor($review, $this->userFor((int) $review->supervisor_staff_id), $this->ratings($review, [7, 5], 'SECRET sup'), 'SECRET sup summary', true);
        }

        return $this->reviews()->saveHr($review, $this->hrUser(), $this->ratings($review, [8, 6], 'hr words'), 'final words', true);
    }

    /** What the staff member disputes: the first criterion, with a reason. */
    private function grounds(PerformanceReview $review, int $count = 1): array
    {
        return $review->items()->limit($count)->pluck('id')->mapWithKeys(fn ($id) => [$id => 'I did exceed the target'])->all();
    }

    private function file(PerformanceReview $review, int $count = 1): PerformanceAppeal
    {
        return $this->appeals()->file($review, $this->userFor((int) $review->staff_id), $this->grounds($review, $count), 'Please look again');
    }

    private function plan(array $override = []): array
    {
        return array_merge(['scheduled_at' => '2027-05-10 09:00', 'venue' => 'Boardroom', 'hr_user_id' => 2000, 'committee_staff_ids' => [3]], $override);
    }

    private function scheduleHearing(PerformanceReview $review, array $override = []): PerformanceMeeting
    {
        return $this->meetings()->schedule($review, $this->hrUser(), PerformanceMeeting::KIND_APPEAL, $this->plan($override));
    }

    private function came(PerformanceMeeting $meeting, array $absent = []): array
    {
        return $meeting->attendees()->get()->mapWithKeys(fn ($attendee) => [$attendee->id => in_array($attendee->role, $absent, true) ? 0 : 1])->all();
    }

    private function alertsOf(int $userId, ?string $type = null): int
    {
        return Notification::where('notifiable_id', $userId)->when($type, fn ($q) => $q->where('type', $type))->count();
    }

    // ── filing ────────────────────────────────────────────────────────────

    public function test_the_staff_member_appeals_a_finalised_review_and_everyone_involved_is_told(): void
    {
        $review = $this->finalisedReview();
        Notification::query()->delete();

        $appeal = $this->appeals()->file($review, $this->userFor(10), $this->grounds($review, 2), 'Please look again');

        $this->assertSame(PerformanceAppeal::STATUS_FILED, $appeal->status);
        $this->assertFalse($appeal->on_behalf);
        $this->assertSame(2, $appeal->items()->count());
        $this->assertEquals(7.0, $appeal->score_before);
        $this->assertSame(PerformanceReview::STATUS_FINALISED, $review->refresh()->status, 'the review stays finalised, its score stands while under appeal');
        $this->assertContains('appeal_filed', PerformanceReviewEvent::where('review_id', $review->id)->pluck('action')->all());

        $this->assertSame(1, $this->alertsOf(1010, PerformanceNotifications::APPEAL_FILED), 'the staff member: it was received');
        $this->assertSame(1, $this->alertsOf(1060, PerformanceNotifications::APPEAL_FILED), 'HR');
        $this->assertSame(1, $this->alertsOf(2000, PerformanceNotifications::APPEAL_FILED), 'HR');
        $this->assertSame(1, $this->alertsOf(1002, PerformanceNotifications::APPEAL_FILED), 'the supervisor');
        $this->assertSame(4, Notification::count());
        $this->assertSame('/hr/performance/reviews/'.$review->id.'/appeal', Notification::first()->action_url);
    }

    public function test_a_review_can_only_be_appealed_once_it_is_finalised_and_only_once(): void
    {
        $review = $this->finalisedReview();
        $open = PerformanceReview::where('staff_id', 11)->firstOrFail(); // still waiting for the self-rating
        $this->assertSame(422, $this->refusal(fn () => $this->appeals()->file($open, $this->userFor(11), $this->grounds($open), null))->status(), 'not finalised yet');

        $this->file($review);

        $exception = $this->refusal(fn () => $this->file($review));
        $this->assertSame(422, $exception->status());
        $this->assertStringContainsString('already been appealed', $exception->getMessage());
        $this->assertSame(1, PerformanceAppeal::count());
    }

    public function test_only_the_staff_member_files_it_for_themselves_and_only_hr_files_it_for_them(): void
    {
        $review = $this->finalisedReview();
        $grounds = $this->grounds($review);

        foreach ([$this->userFor(2), $this->userFor(3), $this->userFor(21), $this->hrUser()] as $someone) {
            $this->assertSame(403, $this->refusal(fn () => $this->appeals()->file($review, $someone, $grounds, null))->status());
        }
        foreach ([$this->userFor(10), $this->userFor(2), $this->userFor(3)] as $someone) {
            $this->assertSame(403, $this->refusal(fn () => $this->appeals()->file($review, $someone, $grounds, null, true))->status(), 'on behalf is HR only');
        }
    }

    public function test_an_appeal_names_the_criteria_and_a_reason_for_each(): void
    {
        $review = $this->finalisedReview();
        $items = $review->items()->pluck('id');
        $says = fn (array $grounds, ?string $reason = null) => $this->refusal(fn () => $this->appeals()->file($review, $this->userFor(10), $grounds, $reason))->getMessage();

        $this->assertStringContainsString('at least one criterion', $says([]));
        $this->assertStringContainsString('Say why you dispute "Attendance"', $says([$items[0] => '  ']));
        $this->assertStringContainsString('not part of this review', $says([9999 => 'x']));
        $this->assertStringContainsString('2000 characters', $says([$items[0] => str_repeat('x', 2001)]));
        $this->assertSame(0, PerformanceAppeal::count());
    }

    public function test_the_staff_member_can_appeal_within_the_appeal_period_and_not_after(): void
    {
        $review = $this->finalisedReview();

        Carbon::setTestNow($review->finalised_at->copy()->addDays(14)->subMinute());
        $this->assertNull((new PerformanceAccess)->denial($this->userFor(10), $review, 'file_appeal'), 'the last day is still open');

        Carbon::setTestNow($review->finalised_at->copy()->addDays(15));
        $exception = $this->refusal(fn () => $this->file($review));
        $this->assertSame(422, $exception->status());
        $this->assertStringContainsString('appeal period for this review has ended', $exception->getMessage());
    }

    public function test_hr_can_change_how_many_days_staff_have_to_appeal(): void
    {
        PerformanceSetting::put(PerformanceSetting::APPEAL_DAYS, 30);
        $review = $this->finalisedReview();

        Carbon::setTestNow($review->finalised_at->copy()->addDays(20));

        $this->assertSame(PerformanceAppeal::STATUS_FILED, $this->file($review)->status);
    }

    public function test_hr_can_file_it_for_someone_and_after_the_period_needs_a_reason(): void
    {
        $review = $this->finalisedReview();
        $grounds = $this->grounds($review);

        // Within the period no reason is needed (the staff member has no login, say)
        $appeal = $this->appeals()->file($review, $this->hrUser(), $grounds, 'On their behalf', true);
        $this->assertTrue($appeal->on_behalf);
        $this->assertNull($appeal->late_reason);
        $appeal->items()->delete();
        $appeal->delete();

        Carbon::setTestNow($review->finalised_at->copy()->addDays(40));
        $this->assertStringContainsString('Say why HR is accepting this appeal late', $this->refusal(fn () => $this->appeals()->file($review, $this->hrUser(), $grounds, null, true))->getMessage());

        $late = $this->appeals()->file($review, $this->hrUser(), $grounds, null, true, 'They were on long leave');
        $this->assertSame('They were on long leave', $late->late_reason);
        $this->assertSame($review->staff_id, PerformanceReviewEvent::where('action', 'appeal_filed')->latest('id')->value('on_behalf_of_staff_id'));
    }

    public function test_an_appeal_can_still_be_filed_after_the_cycle_is_closed(): void
    {
        $review = $this->finalisedReview();
        PerformanceCycle::query()->update(['status' => PerformanceCycle::STATUS_CLOSED]);

        $this->assertSame(PerformanceAppeal::STATUS_FILED, $this->file($review->refresh())->status);
    }

    // ── the hearing ───────────────────────────────────────────────────────

    public function test_hr_schedules_the_hearing_with_the_committee_and_everyone_invited_is_told(): void
    {
        $review = $this->finalisedReview();
        $this->assertSame(422, $this->refusal(fn () => $this->scheduleHearing($review))->status(), 'no appeal yet');
        $this->file($review);
        Notification::query()->delete();

        $hearing = $this->scheduleHearing($review);

        $this->assertSame(PerformanceMeeting::KIND_APPEAL, $hearing->kind);
        $this->assertNotNull($hearing->appeal_id);
        $this->assertEqualsCanonicalizing(['staff', 'supervisor', 'hr', 'committee'], $hearing->attendees()->pluck('role')->all());
        foreach ([1010, 1002, 2000, 1003] as $userId) {
            $this->assertSame(1, $this->alertsOf($userId, PerformanceNotifications::MEETING_SCHEDULED), "user {$userId}");
        }
        $alert = Notification::where('notifiable_id', 1010)->first();
        $this->assertSame('Appeal hearing scheduled', $alert->title);
        $this->assertStringContainsString('Your appeal hearing', $alert->message);
        $this->assertSame('/hr/performance/reviews/'.$review->id.'/appeal', $alert->action_url);
        $this->assertContains('hearing_scheduled', PerformanceReviewEvent::where('review_id', $review->id)->pluck('action')->all());
    }

    public function test_the_hearing_follows_the_same_rule_about_who_must_come_and_can_be_rescheduled(): void
    {
        $review = $this->finalisedReview();
        $this->file($review);
        $hearing = $this->scheduleHearing($review);

        $message = $this->refusal(fn () => $this->appeals()->decide($review, $this->hrUser(), $this->came($hearing, ['staff', 'committee']), 'rejected', 'The ratings stand', []))->getMessage();
        $this->assertStringContainsString('The staff member must be there', $message);
        $this->assertStringContainsString('At least one committee member must be there', $message);
        $this->assertSame(PerformanceAppeal::STATUS_FILED, PerformanceAppeal::first()->status, 'nothing was decided');

        $moved = $this->meetings()->reschedule($review, $this->hrUser(), PerformanceMeeting::KIND_APPEAL, $this->plan(['scheduled_at' => '2027-05-20 11:00']), 'The staff member was ill');
        $this->assertSame(1, (int) $moved->reschedule_count);
        $this->assertContains('hearing_rescheduled', PerformanceReviewEvent::where('review_id', $review->id)->pluck('action')->all());
    }

    public function test_the_supervisor_of_the_review_cannot_run_the_appeal_as_hr(): void
    {
        // The HR manager supervises the accountant (staff 21): a different HR person must handle that appeal
        DB::table('sys_set_staff_positions')->where('id', self::POS_ACCOUNTANT)->update(['reports_to_id' => self::POS_HR]);
        $this->criteria(self::POS_ACCOUNTANT);
        app(\App\Services\Performance\PerformanceCycleService::class)->publish($this->draftCycle(), $this->hrUser());
        $review = PerformanceReview::where('staff_id', 21)->firstOrFail();
        $review = $this->reviews()->saveSelf($review, $this->userFor(21), $this->ratings($review, [8, 8]), null, true);
        $review = $this->holdMeeting($review);
        $review = $this->reviews()->saveSupervisor($review, $this->userFor(60, ['hr_manager']), $this->ratings($review, [7, 7]), null, true);
        $director = $this->userFor(null, ['directors']);
        $review = $this->reviews()->saveHr($review, $director, $this->ratings($review, [7, 7]), null, true);
        $this->appeals()->file($review, $this->userFor(21), $this->grounds($review), null);

        $supervisorAsHr = $this->userFor(60, ['hr_manager']);
        $this->assertSame(403, $this->refusal(fn () => $this->meetings()->schedule($review, $supervisorAsHr, 'appeal', $this->plan()))->status());
        $this->assertSame(PerformanceMeeting::KIND_APPEAL, $this->meetings()->schedule($review, $director, 'appeal', $this->plan(['hr_user_id' => 2000]))->kind);
    }

    // ── the decision ──────────────────────────────────────────────────────

    public function test_a_rejected_appeal_leaves_the_ratings_and_tells_everyone_the_committee_decided(): void
    {
        $review = $this->finalisedReview();
        $this->file($review);
        $hearing = $this->scheduleHearing($review);
        Notification::query()->delete();

        $appeal = $this->appeals()->decide($review, $this->hrUser(), $this->came($hearing), 'rejected', 'The records support the ratings', []);

        $this->assertSame(PerformanceAppeal::STATUS_DECIDED, $appeal->status);
        $this->assertSame('rejected', $appeal->outcome);
        $this->assertSame('The records support the ratings', $appeal->decision_reason);
        $this->assertEquals(7.0, $appeal->score_before);
        $this->assertEquals(7.0, $appeal->score_after);
        $this->assertSame([8, 6], $review->items()->pluck('hr_rating')->all());
        $this->assertEquals(7.0, $review->refresh()->final_score);
        $this->assertSame(PerformanceMeeting::STATUS_HELD, $hearing->refresh()->status);
        $this->assertSame(4, $hearing->attendees()->where('present', true)->count());
        $this->assertContains('appeal_decided', PerformanceReviewEvent::where('review_id', $review->id)->pluck('action')->all());

        // The staff member, supervisor, HR person, committee member and the HR logins are each told once
        foreach ([1010, 1002, 2000, 1003, 1060] as $userId) {
            $this->assertSame(1, $this->alertsOf($userId, PerformanceNotifications::APPEAL_DECIDED), "user {$userId}");
        }
        $this->assertSame(5, Notification::count());
        $this->assertStringContainsString('has decided your appeal', Notification::where('notifiable_id', 1010)->first()->message);
        $this->assertStringNotContainsString('rejected', strtolower(Notification::all()->pluck('message')->implode(' ')), 'the outcome is read on the appeal page, not in the bell');
    }

    public function test_an_upheld_appeal_changes_the_final_rating_through_the_correction_and_keeps_the_history(): void
    {
        $review = $this->finalisedReview();
        $this->file($review);
        $hearing = $this->scheduleHearing($review);
        $first = $review->items()->first();
        Notification::query()->delete();

        $appeal = $this->appeals()->decide($review, $this->hrUser(), $this->came($hearing), 'upheld', 'Their collection was above target', [$first->id => ['rating' => 9, 'comment' => null]]);

        $this->assertSame('upheld', $appeal->outcome);
        $this->assertEquals(7.0, $appeal->score_before);
        $this->assertEquals(7.5, $appeal->score_after);
        $review->refresh();
        $this->assertEquals(7.5, $review->final_score);
        $this->assertSame([9, 6], $review->items()->pluck('hr_rating')->all());
        $this->assertSame('hr words', $review->items()->first()->hr_comment, 'a comment left empty keeps the existing one');
        $this->assertSame('final words', $review->final_comment);

        $corrected = PerformanceReviewEvent::where('action', 'corrected')->first();
        $this->assertStringContainsString('Appeal decided by the review committee: Their collection was above target', $corrected->note);
        $this->assertSame(8, $corrected->details['before']['items'][$first->id]['hr_rating'], 'the old value is kept');

        $this->assertSame(0, $this->alertsOf(1010, PerformanceNotifications::CORRECTED), 'the decision notice is the one the staff member gets');
        $this->assertSame(1, $this->alertsOf(1010, PerformanceNotifications::APPEAL_DECIDED));
    }

    public function test_a_partly_upheld_appeal_changes_only_what_was_named(): void
    {
        $review = $this->finalisedReview();
        $this->file($review, 2);
        $hearing = $this->scheduleHearing($review);
        [$first, $second] = $review->items()->get()->all();

        $appeal = $this->appeals()->decide($review, $this->hrUser(), $this->came($hearing), 'partly_upheld', 'One criterion changes', [
            $first->id => ['rating' => 8, 'comment' => ''],   // unchanged, so left alone
            $second->id => ['rating' => 8, 'comment' => 'Raised after the hearing'],
        ]);

        $this->assertSame('partly_upheld', $appeal->outcome);
        $this->assertSame([8, 8], $review->items()->pluck('hr_rating')->all());
        $this->assertSame('Raised after the hearing', $second->refresh()->hr_comment);
        $this->assertSame('hr words', $first->refresh()->hr_comment);
        $this->assertEquals(8.0, $review->refresh()->final_score);
    }

    public function test_an_upheld_appeal_has_to_change_something_and_only_what_the_appeal_was_about(): void
    {
        $review = $this->finalisedReview();
        $this->file($review, 1);
        $hearing = $this->scheduleHearing($review);
        [$first, $second] = $review->items()->get()->all();
        $decide = fn (string $outcome, array $ratings) => $this->refusal(fn () => $this->appeals()->decide($review, $this->hrUser(), $this->came($hearing), $outcome, 'Reasons', $ratings))->getMessage();

        $this->assertStringContainsString('changes at least one rating', $decide('upheld', []));
        $this->assertStringContainsString('changes at least one rating', $decide('upheld', [$first->id => ['rating' => 8]]), 'the same rating is no change');
        $this->assertStringContainsString('only be changed on a criterion the appeal was about', $decide('upheld', [$second->id => ['rating' => 9]]));
        $this->assertStringContainsString('1 to 10', $decide('upheld', [$first->id => ['rating' => 11]]));

        $this->assertSame([8, 6], $review->items()->pluck('hr_rating')->all(), 'nothing changed by a refused decision');
        $this->assertSame(PerformanceAppeal::STATUS_FILED, PerformanceAppeal::first()->status);
        $this->assertSame(PerformanceMeeting::STATUS_SCHEDULED, $hearing->refresh()->status);
    }

    public function test_a_decision_needs_an_outcome_and_the_committees_reasons(): void
    {
        $review = $this->finalisedReview();
        $this->file($review);
        $hearing = $this->scheduleHearing($review);
        $decide = fn (string $outcome, string $reason) => $this->refusal(fn () => $this->appeals()->decide($review, $this->hrUser(), $this->came($hearing), $outcome, $reason, []))->getMessage();

        $this->assertStringContainsString('Choose the committee', $decide('', 'Reasons'));
        $this->assertStringContainsString('Choose the committee', $decide('maybe', 'Reasons'));
        $this->assertStringContainsString('reasons for the decision', $decide('rejected', '   '));
    }

    public function test_the_decision_is_final(): void
    {
        $review = $this->finalisedReview();
        $this->file($review);
        $hearing = $this->scheduleHearing($review);
        $this->appeals()->decide($review, $this->hrUser(), $this->came($hearing), 'rejected', 'The ratings stand', []);

        $again = $this->refusal(fn () => $this->appeals()->decide($review->refresh(), $this->hrUser(), $this->came($hearing), 'upheld', 'Changed our mind', []));
        $this->assertSame(422, $again->status());
        $this->assertStringContainsString('already been decided', $again->getMessage());

        $this->assertStringContainsString('already been appealed', $this->refusal(fn () => $this->file($review))->getMessage(), 'and it cannot be appealed again');
        $this->assertSame(PerformanceMeeting::STATUS_HELD, $hearing->refresh()->status);
    }

    public function test_only_hr_records_the_decision(): void
    {
        $review = $this->finalisedReview();
        $this->file($review);
        $hearing = $this->scheduleHearing($review);

        foreach ([$this->userFor(10), $this->userFor(2), $this->userFor(3)] as $someone) {
            $this->assertSame(403, $this->refusal(fn () => $this->appeals()->decide($review, $someone, $this->came($hearing), 'rejected', 'Reasons', []))->status());
        }
    }

    public function test_an_appeal_with_no_supervisor_is_decided_by_the_committee_and_hr(): void
    {
        $review = $this->finalisedReview(11, null);
        $this->assertSame('hr', $review->route);
        $this->file($review);
        $hearing = $this->scheduleHearing($review);

        $this->assertEqualsCanonicalizing(['staff', 'hr', 'committee'], $hearing->attendees()->pluck('role')->all());
        $appeal = $this->appeals()->decide($review, $this->hrUser(), $this->came($hearing), 'rejected', 'The ratings stand', []);

        $this->assertSame('rejected', $appeal->outcome);
    }

    // ── what each person sees ─────────────────────────────────────────────

    public function test_the_staff_member_reads_the_decision_and_its_reasons_and_hr_alone_sees_the_late_reason(): void
    {
        $review = $this->finalisedReview();
        Carbon::setTestNow($review->finalised_at->copy()->addDays(40));
        $this->appeals()->file($review, $this->hrUser(), $this->grounds($review), 'For them', true, 'They were on leave');
        $hearing = $this->scheduleHearing($review);
        $page = fn ($viewer) => app(PerformanceMeetingPresenter::class)->page($viewer, $review->refresh(), 'appeal');

        $before = $page($this->userFor(10));
        $this->assertSame('filed', $before['appeal']['status']);
        $this->assertArrayNotHasKey('outcome', $before['appeal'], 'nothing decided yet');
        $this->assertArrayNotHasKey('late_reason', $before['appeal']);
        $this->assertArrayHasKey('late_reason', $page($this->hrUser())['appeal']);

        $this->appeals()->decide($review, $this->hrUser(), $this->came($hearing), 'rejected', 'The records support the ratings', []);

        foreach ([$this->userFor(10), $this->userFor(2), $this->userFor(3)] as $viewer) {
            $appeal = $page($viewer)['appeal'];
            $this->assertSame('Rejected', $appeal['outcome_label']);
            $this->assertSame('The records support the ratings', $appeal['decision_reason']);
        }
        $this->assertFalse($page($this->userFor(10))['actions']['record'], 'the staff member cannot record it');
    }

    public function test_the_review_page_tells_each_person_what_they_can_do_about_an_appeal(): void
    {
        $review = $this->finalisedReview();
        $summary = fn ($viewer) => app(PerformanceMeetingPresenter::class)->summary($viewer, $review->refresh());

        $this->assertTrue($summary($this->userFor(10))['can']['file_appeal']);
        $this->assertFalse($summary($this->userFor(2))['can']['file_appeal'], 'the supervisor cannot appeal');
        $this->assertTrue($summary($this->hrUser())['can']['appeal'], 'HR can file one for the staff member');
        $this->assertNull($summary($this->userFor(10))['appeal']);

        $this->file($review);

        $this->assertSame('Filed', $summary($this->userFor(10))['appeal']['status_label']);
        $this->assertFalse($summary($this->userFor(10))['can']['file_appeal'], 'only once');
    }

    public function test_no_alert_about_an_appeal_carries_a_rating_a_reason_or_a_comment(): void
    {
        $review = $this->finalisedReview();
        $this->appeals()->file($review, $this->userFor(10), $this->grounds($review), 'SECRET grounds');
        $hearing = $this->scheduleHearing($review);
        $first = $review->items()->first();
        $this->appeals()->decide($review, $this->hrUser(), $this->came($hearing), 'upheld', 'SECRET decision reasons', [$first->id => ['rating' => 9]]);

        $text = Notification::all()->map(fn ($alert) => $alert->title.' '.$alert->message.' '.json_encode($alert->data))->implode(' ');

        $this->assertStringNotContainsString('SECRET', $text);
        $this->assertStringNotContainsString('I did exceed the target', $text);
        $this->assertDoesNotMatchRegularExpression('/\b(rated|rating of|score)\b.*\b\d\b/i', $text);
    }

    public function test_the_appeal_changes_nothing_for_reviews_still_in_progress(): void
    {
        $this->finalisedReview();
        $other = PerformanceReview::where('staff_id', 11)->firstOrFail();

        $denial = (new PerformanceAccess)->denial($this->userFor(11), $other, 'file_appeal');

        $this->assertSame(422, $denial['status']);
        $this->assertSame(PerformanceReview::STATUS_AWAITING_SELF, $other->status);
    }
}
