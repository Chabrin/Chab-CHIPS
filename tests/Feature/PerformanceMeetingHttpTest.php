<?php

namespace Tests\Feature;

use App\Http\Middleware\AccessChecker;
use App\Http\Middleware\OneLogin;
use App\Models\PerformanceAppeal;
use App\Models\PerformanceCommitteeMember;
use App\Models\PerformanceMeeting;
use App\Models\PerformanceReview;
use App\Models\PerformanceSetting;
use App\Services\Performance\PerformanceReviewService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsPerformanceOrg;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * The joint review meeting, the committee and appeals over HTTP: the pages open for the right people and for nobody else, and
 * every request is checked on the server. staff 10 is supervised by 2; HR is user 1060 (staff 60) and the director is user 2000.
 */
class PerformanceMeetingHttpTest extends TestCase
{
    use BuildsPerformanceOrg;
    use RendersPagesWithPlainLayout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout(true);
        $this->buildPerformanceSchema();
        $this->seedPerformanceOrg();
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function actAs(?int $staffId, array $roles = []): void
    {
        $this->actingAs($this->userFor($staffId, $roles));
        $this->withoutMiddleware([AccessChecker::class, OneLogin::class]);
    }

    private function asHr(): void
    {
        $this->actAs(60, ['hr_manager']);
    }

    private function service(): PerformanceReviewService
    {
        return app(PerformanceReviewService::class);
    }

    private function ratings(PerformanceReview $review, array $values): array
    {
        $out = [];
        foreach ($review->items()->get() as $index => $item) {
            $out[$item->id] = ['rating' => $values[$index] ?? null, 'comment' => null];
        }

        return $out;
    }

    private function reviewAwaitingMeeting(): PerformanceReview
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = PerformanceReview::where('staff_id', 10)->firstOrFail();

        return $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [9, 9]), null, true);
    }

    private function finalisedReview(): PerformanceReview
    {
        $review = $this->holdMeeting($this->reviewAwaitingMeeting());
        $review = $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [7, 5]), null, true);

        return $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [8, 6]), 'final words', true);
    }

    private function plan(array $override = []): array
    {
        return array_merge(['scheduled_at' => '2027-02-01T10:00', 'venue' => 'Boardroom', 'hr_user_id' => 2000, 'committee_staff_ids' => [3]], $override);
    }

    private function scheduleUrl(PerformanceReview $review, string $kind = 'review'): string
    {
        return route('ajax.performance.meeting.schedule', [$review->id, $kind]);
    }

    // ── the pages ─────────────────────────────────────────────────────────

    public function test_the_committee_and_appeals_pages_are_for_hr_only_and_the_meetings_page_is_for_everyone(): void
    {
        foreach (['performance.committee', 'performance.appeals'] as $route) {
            $this->asHr();
            $this->get(route($route))->assertOk();
            foreach ([$this->userFor(10), $this->userFor(2), $this->userFor(null)] as $someone) {
                $this->actingAs($someone);
                $this->get(route($route))->assertForbidden();
            }
        }
        foreach (['ajax.performance.committee.list', 'ajax.performance.appeals.list'] as $route) {
            $this->actAs(10);
            $this->getJson(route($route))->assertForbidden();
        }

        $this->actAs(10);
        $this->get(route('performance.meetings'))->assertOk()->assertSee('pmTable', false);
        $this->getJson(route('ajax.performance.meetings.list'))->assertOk()->assertJsonPath('meetings', []);
    }

    public function test_the_meeting_page_opens_for_those_with_a_part_in_the_review_and_for_nobody_else(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $this->asHr();
        $this->postJson($this->scheduleUrl($review), $this->plan())->assertOk();

        foreach ([[10, []], [2, []], [3, []], [60, ['hr_manager']]] as [$staffId, $roles]) {
            $this->actAs($staffId, $roles);
            $this->get(route('performance.meeting', $review->id))->assertOk()->assertSee('pmRoot', false)->assertSee('Felix Officer', false);
        }
        foreach ([21, 11, 20, null] as $staffId) {
            $this->actAs($staffId);
            $this->get(route('performance.meeting', $review->id))->assertForbidden();
            $this->get(route('performance.appeal', $review->id))->assertForbidden();
            $this->getJson(route('ajax.performance.meeting.data', [$review->id, 'review']))->assertForbidden();
        }
        $this->actAs(10);
        $this->get('/hr/performance/reviews/9999/meeting')->assertNotFound();
        $this->getJson('/ajax/hr/performance/reviews/'.$review->id.'/page/other')->assertNotFound();
    }

    // ── scheduling and recording over HTTP ────────────────────────────────

    public function test_hr_schedules_the_meeting_over_http_and_the_details_are_checked(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $this->asHr();

        $this->postJson($this->scheduleUrl($review), $this->plan(['venue' => '']))->assertStatus(422)->assertJsonPath('success', false);
        $this->postJson($this->scheduleUrl($review), $this->plan(['committee_staff_ids' => []]))->assertStatus(422);
        $this->postJson($this->scheduleUrl($review), $this->plan(['committee_staff_ids' => 'nonsense', 'venue' => ['a' => 'b']]))->assertStatus(422);
        $this->assertSame(0, PerformanceMeeting::count());

        $res = $this->postJson($this->scheduleUrl($review), $this->plan())->assertOk();
        $this->assertSame('scheduled', $res->json('page.meeting.status'));
        $this->assertSame(['staff', 'supervisor', 'hr', 'committee'], array_column($res->json('page.meeting.attendees'), 'role'));
        $this->assertTrue($res->json('page.actions.reschedule'));
        $this->assertFalse($res->json('page.actions.schedule'));

        $moved = $this->postJson(route('ajax.performance.meeting.reschedule', [$review->id, 'review']), $this->plan(['scheduled_at' => '2027-02-08T14:30']) + ['reason' => ''])->assertStatus(422);
        $this->postJson(route('ajax.performance.meeting.reschedule', [$review->id, 'review']), $this->plan(['scheduled_at' => '2027-02-08T14:30']) + ['reason' => 'Clash'])->assertOk()->assertJsonPath('page.meeting.scheduled_at', '08 Feb 2027 14:30');
    }

    public function test_nobody_else_can_schedule_or_record_over_http(): void
    {
        $review = $this->reviewAwaitingMeeting();

        foreach ([10, 2, 3, 21, null] as $staffId) {
            $this->actAs($staffId);
            $this->postJson($this->scheduleUrl($review), $this->plan())->assertForbidden();
            $this->postJson(route('ajax.performance.meeting.reschedule', [$review->id, 'review']), $this->plan() + ['reason' => 'x'])->assertForbidden();
        }
        $this->assertSame(0, PerformanceMeeting::count());
    }

    public function test_the_supervisor_records_the_meeting_over_http_with_the_four_areas_and_an_extra_item(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $this->asHr();
        $meeting = $this->postJson($this->scheduleUrl($review), $this->plan())->assertOk()->json('page.meeting');
        $attendance = collect($meeting['attendees'])->mapWithKeys(fn ($attendee) => [$attendee['id'] => 1])->all();

        // Someone else cannot record it, and a partial attendance is refused with who is missing
        $this->actAs(10);
        $this->postJson(route('ajax.performance.meeting.record', $review->id), ['attendance' => $attendance, 'sections' => ['performance_feedback' => ['notes' => 'x']]])->assertForbidden();

        $this->actAs(2);
        $absent = $attendance;
        $absent[$meeting['attendees'][0]['id']] = 0;
        $this->postJson(route('ajax.performance.meeting.record', $review->id), ['attendance' => $absent, 'sections' => ['performance_feedback' => ['notes' => 'x']]])
            ->assertStatus(422)->assertJsonPath('error', 'This cannot be recorded as held yet. The staff member must be there. If someone could not come, reschedule it.');

        $res = $this->postJson(route('ajax.performance.meeting.record', $review->id), [
            'attendance' => $attendance,
            'sections' => [
                'skill_gaps' => ['notes' => 'Excel', 'approved' => 0],
                'training_needs' => ['notes' => 'Customer care', 'approved' => 1],
                'performance_feedback' => ['notes' => 'Steady work'],
                'goals' => ['notes' => 'Collect 95%', 'approved' => 1],
            ],
            'extras' => [['title' => 'Workload', 'notes' => 'Covers two zones']],
        ])->assertOk();

        $this->assertSame('held', $res->json('page.meeting.status'));
        $this->assertSame('awaiting_supervisor', PerformanceReview::find($review->id)->status);
        $this->assertSame(['Skill gaps', 'Training needs', 'Performance feedback', 'Goals and targets going forward', 'Workload'], array_column($res->json('page.meeting.items'), 'title'));

        // The staff member reads the feedback over HTTP, and cannot change it
        $this->actAs(10);
        $mine = $this->getJson(route('ajax.performance.meeting.data', [$review->id, 'review']))->assertOk();
        $this->assertSame('Steady work', collect($mine->json('page.meeting.items'))->firstWhere('section', 'performance_feedback')['notes']);
        $this->assertSame('Covers two zones', collect($mine->json('page.meeting.items'))->firstWhere('section', 'other')['notes']);
        $this->assertFalse($mine->json('page.actions.record'));
        $this->postJson(route('ajax.performance.meeting.record', $review->id), ['attendance' => $attendance, 'sections' => ['performance_feedback' => ['notes' => 'x']]])->assertForbidden();
    }

    public function test_the_review_page_shows_where_the_meeting_has_got_to(): void
    {
        $review = $this->reviewAwaitingMeeting();
        $this->actAs(10);
        $this->getJson(route('ajax.performance.reviews.data', $review->id))->assertOk()->assertJsonPath('review.stages.meeting', null)->assertJsonPath('review.status_label', 'Waiting for the joint review meeting');

        $this->asHr();
        $this->postJson($this->scheduleUrl($review), $this->plan())->assertOk();

        $this->actAs(10);
        $this->getJson(route('ajax.performance.reviews.data', $review->id))->assertOk()
            ->assertJsonPath('review.stages.meeting.status', 'scheduled')
            ->assertJsonPath('review.stages.meeting.venue', 'Boardroom');
        $this->get(route('performance.review', $review->id))->assertOk()->assertSee('Joint review meeting', false);
    }

    // ── appeals over HTTP ─────────────────────────────────────────────────

    public function test_the_whole_appeal_can_be_done_over_http(): void
    {
        $review = $this->finalisedReview();
        $first = $review->items()->first();

        $this->actAs(10);
        $page = $this->getJson(route('ajax.performance.meeting.data', [$review->id, 'appeal']))->assertOk();
        $this->assertTrue($page->json('page.actions.file'));
        $this->assertSame(['Attendance', 'Quality of work'], array_column($page->json('page.criteria'), 'name'));
        $this->get(route('performance.appeal', $review->id))->assertOk()->assertSee('paRoot', false);

        $this->postJson(route('ajax.performance.appeal.file', $review->id), ['grounds' => [], 'reason' => 'x'])->assertStatus(422);
        $filed = $this->postJson(route('ajax.performance.appeal.file', $review->id), ['grounds' => [$first->id => 'I exceeded the target'], 'reason' => 'Please look again'])->assertOk();
        $this->assertSame('filed', $filed->json('page.appeal.status'));
        $this->assertFalse($filed->json('page.actions.file'));
        $this->postJson(route('ajax.performance.appeal.file', $review->id), ['grounds' => [$first->id => 'Again']])->assertStatus(422);

        // Only HR schedules the hearing and records the decision
        foreach ([10, 2, 3] as $staffId) {
            $this->actAs($staffId);
            $this->postJson($this->scheduleUrl($review, 'appeal'), $this->plan())->assertForbidden();
            $this->postJson(route('ajax.performance.appeal.decide', $review->id), ['outcome' => 'rejected', 'decision_reason' => 'x'])->assertForbidden();
        }

        $this->asHr();
        $hearing = $this->postJson($this->scheduleUrl($review, 'appeal'), $this->plan(['scheduled_at' => '2027-05-10T09:00']))->assertOk()->json('page.meeting');
        $attendance = collect($hearing['attendees'])->mapWithKeys(fn ($attendee) => [$attendee['id'] => 1])->all();

        $this->postJson(route('ajax.performance.appeal.decide', $review->id), ['attendance' => $attendance, 'outcome' => 'upheld', 'decision_reason' => 'Above target', 'ratings' => [$first->id => ['rating' => 8]]])
            ->assertStatus(422)->assertJsonPath('error', 'An appeal that is upheld changes at least one rating. Enter the new rating for the criteria that change, or choose "Rejected".');

        $decided = $this->postJson(route('ajax.performance.appeal.decide', $review->id), ['attendance' => $attendance, 'outcome' => 'upheld', 'decision_reason' => 'Above target', 'ratings' => [$first->id => ['rating' => 9, 'comment' => '']]])->assertOk();
        $this->assertSame('Upheld', $decided->json('page.appeal.outcome_label'));
        $this->assertEquals(7.5, $decided->json('page.appeal.score_after'));
        $this->assertEquals(7.5, PerformanceReview::find($review->id)->final_score);

        // The staff member reads the decision and its reasons
        $this->actAs(10);
        $mine = $this->getJson(route('ajax.performance.meeting.data', [$review->id, 'appeal']))->assertOk();
        $this->assertSame('Above target', $mine->json('page.appeal.decision_reason'));
        $this->assertFalse($mine->json('page.actions.record'));
        $this->postJson(route('ajax.performance.appeal.decide', $review->id), ['outcome' => 'rejected', 'decision_reason' => 'x', 'attendance' => $attendance])->assertForbidden();

        $this->asHr();
        $this->getJson(route('ajax.performance.appeals.list'))->assertOk()
            ->assertJsonPath('appeals.0.state', 'decided')
            ->assertJsonPath('appeals.0.outcome_label', 'Upheld')
            ->assertJsonPath('appeals.0.staff', 'Felix Officer');
    }

    public function test_hr_can_file_an_appeal_for_the_staff_member_over_http(): void
    {
        $review = $this->finalisedReview();
        $first = $review->items()->first();

        $this->actAs(10);
        $this->postJson(route('ajax.performance.appeal.on_behalf', $review->id), ['grounds' => [$first->id => 'x']])->assertForbidden();

        $this->asHr();
        $res = $this->postJson(route('ajax.performance.appeal.on_behalf', $review->id), ['grounds' => [$first->id => 'They told HR in person'], 'reason' => ''])->assertOk();
        $this->assertTrue($res->json('page.appeal.on_behalf'));
        $this->assertTrue(PerformanceAppeal::first()->on_behalf);
    }

    public function test_the_meetings_list_over_http_shows_hr_what_is_still_to_schedule(): void
    {
        $review = $this->reviewAwaitingMeeting();

        $this->asHr();
        $this->getJson(route('ajax.performance.meetings.list'))->assertOk()
            ->assertJsonPath('meetings.0.status', 'to_schedule')
            ->assertJsonPath('meetings.0.staff', 'Felix Officer')
            ->assertJsonPath('meetings.0.url', '/hr/performance/reviews/'.$review->id.'/meeting');

        $this->postJson($this->scheduleUrl($review), $this->plan())->assertOk();
        $this->getJson(route('ajax.performance.meetings.list'))->assertOk()->assertJsonPath('meetings.0.status', 'scheduled');

        $this->actAs(3);
        $this->getJson(route('ajax.performance.meetings.list'))->assertOk()->assertJsonPath('meetings.0.status', 'scheduled');
        $this->actAs(21);
        $this->getJson(route('ajax.performance.meetings.list'))->assertOk()->assertJsonPath('meetings', []);
    }

    // ── the committee page ────────────────────────────────────────────────

    public function test_hr_manages_the_committee_and_its_two_settings(): void
    {
        $this->asHr();

        $list = $this->getJson(route('ajax.performance.committee.list'))->assertOk();
        $this->assertSame([21, 20, 2, 3], collect($list->json('members'))->pluck('staff_id')->sortBy(fn ($id) => ['Alan' => 0, 'Cora' => 1, 'Zane' => 2, 'Zola' => 3][explode(' ', \App\Models\Staff::find($id)->full_names)[0]])->values()->all());
        $this->assertSame(1, $list->json('settings.min_committee_present'));
        $this->assertSame(14, $list->json('settings.appeal_window_days'));
        $this->assertNotContains(21, collect($list->json('candidates'))->pluck('staff_id')->all(), 'already on it');

        $this->postJson(route('ajax.performance.committee.add'), ['staff_id' => 30])->assertOk();
        $this->assertTrue(PerformanceCommitteeMember::where('staff_id', 30)->exists());
        $this->postJson(route('ajax.performance.committee.add'), ['staff_id' => 30])->assertOk();
        $this->assertSame(1, PerformanceCommitteeMember::where('staff_id', 30)->count(), 'adding twice does not duplicate');
        $this->postJson(route('ajax.performance.committee.add'), ['staff_id' => 50])->assertStatus(422); // has left
        $this->postJson(route('ajax.performance.committee.add'), ['staff_id' => 9999])->assertStatus(422);

        $memberId = PerformanceCommitteeMember::where('staff_id', 30)->value('id');
        $this->deleteJson(route('ajax.performance.committee.remove', $memberId))->assertOk();
        $this->assertFalse(PerformanceCommitteeMember::where('staff_id', 30)->exists());

        $this->postJson(route('ajax.performance.committee.settings'), ['min_committee_present' => 3, 'appeal_window_days' => 21])->assertOk();
        $this->assertSame(3, PerformanceSetting::number(PerformanceSetting::MIN_COMMITTEE));
        $this->assertSame(21, PerformanceSetting::number(PerformanceSetting::APPEAL_DAYS));
    }

    public function test_the_settings_are_validated(): void
    {
        $this->asHr();

        foreach ([['min_committee_present' => 0, 'appeal_window_days' => 14], ['min_committee_present' => 'x', 'appeal_window_days' => 14], ['min_committee_present' => 1.5, 'appeal_window_days' => 14], ['min_committee_present' => 51, 'appeal_window_days' => 14],
            ['min_committee_present' => 1, 'appeal_window_days' => 0], ['min_committee_present' => 1, 'appeal_window_days' => 366], ['min_committee_present' => 1, 'appeal_window_days' => null]] as $bad) {
            $this->postJson(route('ajax.performance.committee.settings'), $bad)->assertStatus(422);
        }
        $this->assertSame(1, PerformanceSetting::number(PerformanceSetting::MIN_COMMITTEE), 'nothing was saved');
    }

    public function test_the_committee_requests_are_refused_to_everyone_but_hr(): void
    {
        foreach ([10, 2, 3, null] as $staffId) {
            $this->actAs($staffId);
            $this->postJson(route('ajax.performance.committee.add'), ['staff_id' => 30])->assertForbidden();
            $this->postJson(route('ajax.performance.committee.settings'), ['min_committee_present' => 1, 'appeal_window_days' => 14])->assertForbidden();
            $this->deleteJson(route('ajax.performance.committee.remove', 1))->assertForbidden();
        }
        $this->assertFalse(PerformanceCommitteeMember::where('staff_id', 30)->exists());
        $this->assertSame(4, PerformanceCommitteeMember::count());
        $this->assertTrue(DB::table('performance_committee_members')->exists());
    }
}
