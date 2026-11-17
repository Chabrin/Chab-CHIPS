<?php

namespace Tests\Feature;

use App\Models\PerformanceCycle;
use App\Models\PerformanceKpi;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewEvent;
use App\Models\Staff;
use App\Services\Performance\PerformanceAccess;
use App\Services\Performance\PerformanceCycleService;
use App\Services\Performance\PerformanceException;
use App\Services\Performance\PerformanceReviewPresenter;
use App\Services\Performance\PerformanceReviewService;
use App\Services\Performance\PerformanceScorer;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsPerformanceOrg;
use Tests\TestCase;

/**
 * HR > Performance Review: who a review goes to, how a cycle is published, how a review moves from the staff member's
 * self-rating to the supervisor to HR's final rating, and who can see what on the way. See BuildsPerformanceOrg for the
 * company these tests use.
 */
class PerformanceReviewFlowTest extends TestCase
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

    private function access(): PerformanceAccess
    {
        return new PerformanceAccess;
    }

    private function service(): PerformanceReviewService
    {
        return app(PerformanceReviewService::class);
    }

    private function reviewOf(int $staffId): PerformanceReview
    {
        return PerformanceReview::where('staff_id', $staffId)->firstOrFail();
    }

    /** [item id => [rating, comment]] giving each criterion of the review the next rating in the list. */
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

    // ── who a review goes to ──────────────────────────────────────────────

    public function test_with_several_holders_of_the_supervisor_position_and_none_named_it_goes_to_hr(): void
    {
        $route = $this->access()->route(Staff::find(10));

        $this->assertSame(['route' => 'hr', 'supervisor_staff_id' => null, 'reason' => 'not_named'], $route);
    }

    public function test_the_supervisor_hr_named_gets_it(): void
    {
        $route = $this->access()->route(Staff::find(10), 2);

        $this->assertSame(['route' => 'supervisor', 'supervisor_staff_id' => 2, 'reason' => 'named'], $route);
    }

    public function test_a_named_supervisor_who_does_not_hold_the_position_is_ignored_and_it_goes_to_hr(): void
    {
        // Staff 20 is the Chief Accountant, not a Zone Manager
        $route = $this->access()->route(Staff::find(10), 20);

        $this->assertSame('hr', $route['route']);
        $this->assertSame('assignment_invalid', $route['reason']);
    }

    public function test_a_named_supervisor_without_a_login_sends_it_to_hr(): void
    {
        DB::table('sys_staff')->insert(['id' => 4, 'full_names' => 'Zed NoLogin', 'position_id' => self::POS_ZONE_MANAGER, 'current_status_id' => 1]);

        $route = $this->access()->route(Staff::find(10), 4);

        $this->assertSame('hr', $route['route']);
        $this->assertSame('supervisor_has_no_login', $route['reason']);
    }

    public function test_a_blocked_login_does_not_count_as_a_login(): void
    {
        DB::table('josya_users')->where('id', 1002)->update(['block' => 1]);

        $this->assertSame('supervisor_has_no_login', $this->access()->route(Staff::find(10), 2)['reason']);
    }

    public function test_the_only_holder_is_the_supervisor_without_anyone_naming_them(): void
    {
        $route = $this->access()->route(Staff::find(21));

        $this->assertSame(['route' => 'supervisor', 'supervisor_staff_id' => 20, 'reason' => 'only_holder'], $route);
    }

    public function test_staff_with_no_reports_to_no_position_or_nobody_above_them_go_to_hr(): void
    {
        $this->assertSame('no_boss_position', $this->access()->route(Staff::find(30))['reason'], 'lone position, no reports-to');
        $this->assertSame('no_boss_position', $this->access()->route(Staff::find(1))['reason'], 'the top position');
        $this->assertSame('no_position', $this->access()->route(Staff::find(40))['reason']);

        DB::table('sys_staff')->where('id', 20)->update(['current_status_id' => 3]); // nobody active holds the Chief Accountant position
        $this->assertSame('no_holder', $this->access()->route(Staff::find(21))['reason']);
    }

    public function test_someone_who_supervises_others_still_has_a_supervisor_of_their_own(): void
    {
        // The leave rule sends supervisors straight to HR; for reviews a position with a "reports to" always has one
        $this->assertSame('supervisor_has_no_login', $this->access()->route(Staff::find(2))['reason'], 'the Managing Director has no login');

        DB::table('josya_users')->insert(['id' => 1001, 'name' => 'md']);
        DB::table('sys_user_staff_links')->insert(['user_id' => 1001, 'staff_id' => 1]);

        $this->assertSame(['route' => 'supervisor', 'supervisor_staff_id' => 1, 'reason' => 'only_holder'], $this->access()->route(Staff::find(2)));
    }

    public function test_the_candidates_for_a_staff_member_are_the_active_holders_of_the_position_above_them(): void
    {
        $this->assertSame([2, 3], $this->access()->supervisorCandidates(Staff::find(10))->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([], $this->access()->supervisorCandidates(Staff::find(30))->all());
    }

    // ── scoring ───────────────────────────────────────────────────────────

    public function test_the_score_is_the_weighted_average_of_the_ratings(): void
    {
        $items = [['weight' => 3, 'hr_rating' => 8], ['weight' => 1, 'hr_rating' => 4]];

        $this->assertSame(7.0, PerformanceScorer::score($items, 'hr'));
        $this->assertSame(6.0, PerformanceScorer::score([['weight' => 1, 'self_rating' => 8], ['weight' => 1, 'self_rating' => 4]], 'self'));
    }

    public function test_a_criterion_nobody_has_rated_is_left_out_not_counted_as_zero(): void
    {
        $this->assertSame(8.0, PerformanceScorer::score([['weight' => 1, 'hr_rating' => 8], ['weight' => 1, 'hr_rating' => null]], 'hr'));
        $this->assertNull(PerformanceScorer::score([['weight' => 1, 'hr_rating' => null]], 'hr'));
        $this->assertNull(PerformanceScorer::score([], 'hr'));
    }

    public function test_only_whole_numbers_from_one_to_ten_are_ratings(): void
    {
        foreach ([1, 5, 10] as $good) {
            $this->assertTrue(PerformanceScorer::isValidRating($good), (string) $good);
        }
        foreach ([0, 11, -1, 5.5, '5', null, 'x'] as $bad) {
            $this->assertFalse(PerformanceScorer::isValidRating($bad), var_export($bad, true));
        }
    }

    // ── publishing a cycle ────────────────────────────────────────────────

    public function test_the_preview_says_who_gets_a_review_who_it_goes_to_and_who_is_left_out(): void
    {
        $this->criteria(self::POS_FIELD_OFFICER);
        $this->criteria(self::POS_ACCOUNTANT);
        $this->nameSupervisor(10, 2);
        $cycle = $this->draftCycle();

        $preview = app(PerformanceCycleService::class)->preview($cycle);

        $included = collect($preview['include'])->keyBy('staff_id');
        $this->assertSame([10, 11, 12, 21], $included->keys()->sort()->values()->all(), 'staff 50 left the company, so is not listed at all');
        $this->assertSame('supervisor', $included[10]['route']);
        $this->assertSame('Zane Zonemanager', $included[10]['supervisor']);
        $this->assertSame('hr', $included[11]['route']);
        $this->assertSame('not_named', $included[11]['reason']);
        $this->assertSame('supervisor', $included[21]['route']);
        $this->assertSame(['reviews' => 4, 'with_supervisor' => 2, 'straight_to_hr' => 2, 'left_out' => 7, 'already_made' => 0], $preview['counts']);

        $left = collect($preview['exclude'])->keyBy('staff_id');
        $this->assertSame('No position', $left[40]['reason']);
        $this->assertSame('No criteria set up for this position', $left[2]['reason']);
    }

    public function test_publishing_makes_a_review_for_each_person_with_a_copy_of_the_criteria(): void
    {
        $cycle = $this->publishedCycle();

        $this->assertSame(PerformanceCycle::STATUS_PUBLISHED, $cycle->status);
        $this->assertNotNull($cycle->published_at);
        $this->assertSame(4, PerformanceReview::count());

        $review = $this->reviewOf(10);
        $this->assertSame(PerformanceReview::STATUS_AWAITING_SELF, $review->status);
        $this->assertSame('Field Officer', $review->position_name);
        $this->assertSame(self::POS_ZONE_MANAGER, (int) $review->reports_to_position_id);
        $this->assertSame(['Attendance', 'Quality of work'], $review->items()->pluck('name')->all());
        $this->assertSame(['created'], PerformanceReviewEvent::where('review_id', $review->id)->pluck('action')->all());
    }

    public function test_editing_or_deleting_a_criterion_afterwards_changes_no_published_review(): void
    {
        $this->publishedCycle([self::POS_FIELD_OFFICER]);
        $kpi = PerformanceKpi::where('position_id', self::POS_FIELD_OFFICER)->where('name', 'Attendance')->first();

        $kpi->update(['name' => 'Punctuality', 'weight' => 5]);
        PerformanceKpi::where('id', '!=', $kpi->id)->delete();

        $item = $this->reviewOf(10)->items()->first();
        $this->assertSame('Attendance', $item->name);
        $this->assertSame(1.0, $item->weight);
        $this->assertSame(2, $this->reviewOf(10)->items()->count());
    }

    public function test_a_cycle_is_published_once_and_only_when_somebody_gets_a_review(): void
    {
        $cycle = $this->draftCycle();
        $this->assertStringContainsString('Nobody would get a review', $this->refusal(fn () => app(PerformanceCycleService::class)->publish($cycle, $this->hrUser()))->getMessage());
        $this->assertTrue($cycle->refresh()->isDraft());

        $this->criteria(self::POS_FIELD_OFFICER);
        app(PerformanceCycleService::class)->publish($cycle, $this->hrUser());
        $this->assertStringContainsString('Only a draft cycle', $this->refusal(fn () => app(PerformanceCycleService::class)->publish($cycle->refresh(), $this->hrUser()))->getMessage());
        $this->assertSame(3, PerformanceReview::count());
    }

    public function test_adding_missing_staff_makes_reviews_only_for_those_who_have_none(): void
    {
        $cycle = $this->publishedCycle([self::POS_FIELD_OFFICER]);
        $this->assertSame(3, PerformanceReview::count());

        DB::table('sys_staff')->insert(['id' => 13, 'full_names' => 'Newman Officer', 'position_id' => self::POS_FIELD_OFFICER, 'current_status_id' => 1]);
        $made = app(PerformanceCycleService::class)->addMissing($cycle, $this->hrUser());

        $this->assertSame(1, $made);
        $this->assertSame(4, PerformanceReview::count());
        $this->assertSame(0, app(PerformanceCycleService::class)->addMissing($cycle, $this->hrUser()));
    }

    public function test_a_cycle_is_closed_only_when_published(): void
    {
        $draft = $this->draftCycle('Draft one');
        $this->assertStringContainsString('Only a published cycle', $this->refusal(fn () => app(PerformanceCycleService::class)->close($draft))->getMessage());

        $cycle = $this->publishedCycle([self::POS_FIELD_OFFICER]);
        app(PerformanceCycleService::class)->close($cycle);
        $this->assertTrue($cycle->refresh()->isClosed());
        $this->assertNotNull($cycle->closed_at);
    }

    // ── the path of a review ──────────────────────────────────────────────

    public function test_a_review_goes_from_the_staff_member_to_the_supervisor_to_hr(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $this->assertSame(2, (int) $review->supervisor_staff_id);

        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8, 6], 'I tried'), 'My year', true);
        $review = $this->holdMeeting($review);
        $this->assertSame(PerformanceReview::STATUS_AWAITING_SUPERVISOR, $review->status);
        $this->assertNotNull($review->self_submitted_at);

        $review = $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [7, 5]), 'Fair', true);
        $this->assertSame(PerformanceReview::STATUS_AWAITING_HR, $review->status);

        $review = $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [9, 7], 'Agreed'), 'Well done', true);
        $this->assertSame(PerformanceReview::STATUS_FINALISED, $review->status);
        $this->assertSame(8.0, $review->final_score);
        $this->assertSame('Well done', $review->final_comment);
        $this->assertNotNull($review->finalised_at);
        $this->assertSame(1060, (int) $review->finalised_by);

        $this->assertSame(['created', 'self_submitted', 'meeting_scheduled', 'meeting_held', 'supervisor_submitted', 'finalised'], PerformanceReviewEvent::where('review_id', $review->id)->pluck('action')->all());
    }

    public function test_the_final_score_counts_each_criterion_by_its_weight(): void
    {
        $this->criteria(self::POS_FIELD_OFFICER, [['Attendance', 3], ['Teamwork', 1]]);
        $this->nameSupervisor(10, 2);
        app(PerformanceCycleService::class)->publish($this->draftCycle(), $this->hrUser());
        $review = $this->reviewOf(10);
        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [5, 5]), null, true);
        $review = $this->holdMeeting($review);
        $review = $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [5, 5]), null, true);

        $review = $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [8, 4]), null, true);

        $this->assertSame(7.0, $review->final_score);
    }

    public function test_a_review_that_goes_straight_to_hr_skips_the_supervisor(): void
    {
        $this->publishedCycle(); // nobody named, so staff 11 goes to HR
        $review = $this->reviewOf(11);
        $this->assertSame('hr', $review->route);

        $review = $this->service()->saveSelf($review, $this->userFor(11), $this->ratings($review, [7, 7]), null, true);
        $review = $this->holdMeeting($review);
        $this->assertSame(PerformanceReview::STATUS_AWAITING_HR, $review->status);

        $review = $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [6, 8]), null, true);
        $this->assertSame(7.0, $review->final_score);
    }

    public function test_every_criterion_must_be_rated_to_submit_but_not_to_save(): void
    {
        $this->publishedCycle();
        $review = $this->reviewOf(10);

        $saved = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8]), 'draft', false);
        $this->assertSame(PerformanceReview::STATUS_AWAITING_SELF, $saved->status, 'saving keeps it open');
        $this->assertSame([8, null], $saved->items()->pluck('self_rating')->all());

        $exception = $this->refusal(fn () => $this->service()->saveSelf($saved, $this->userFor(10), $this->ratings($saved, [8]), null, true));
        $this->assertStringContainsString('Still to rate: Quality of work', $exception->getMessage());
        $this->assertSame(PerformanceReview::STATUS_AWAITING_SELF, $saved->refresh()->status);
    }

    public function test_a_rating_must_be_a_whole_number_from_one_to_ten(): void
    {
        $this->publishedCycle();
        $review = $this->reviewOf(10);

        foreach ([0, 11, -3, 5.5, 'abc'] as $bad) {
            $this->refusal(fn () => $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [$bad, 5]), null, false));
        }
        $this->assertSame([null, null], $review->items()->pluck('self_rating')->all(), 'a refused save changes nothing');

        $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, ['7', 10]), null, false);
        $this->assertSame([7, 10], $review->items()->pluck('self_rating')->all(), 'a rating sent as text is accepted when it is a whole number');
    }

    public function test_a_rating_for_something_not_in_the_review_is_refused(): void
    {
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $other = $this->reviewOf(11)->items()->first();

        $exception = $this->refusal(fn () => $this->service()->saveSelf($review, $this->userFor(10), [$other->id => ['rating' => 5]], null, false));

        $this->assertStringContainsString('not part of this review', $exception->getMessage());
        $this->assertNull($other->refresh()->self_rating);
    }

    public function test_hr_enters_the_self_rating_for_someone_and_the_history_says_so(): void
    {
        $this->publishedCycle();
        $review = $this->reviewOf(12); // Fred has no login

        $review = $this->service()->saveSelf($review, $this->hrUser(), $this->ratings($review, [6, 6]), null, true, true);
        $review = $this->holdMeeting($review);

        $this->assertSame(PerformanceReview::STATUS_AWAITING_HR, $review->status);
        $event = PerformanceReviewEvent::where('review_id', $review->id)->where('action', 'self_submitted')->first();
        $this->assertSame(1060, (int) $event->actor_user_id);
        $this->assertSame(12, (int) $event->on_behalf_of_staff_id);
    }

    public function test_only_hr_can_enter_a_self_rating_for_someone_else_and_staff_cannot_do_it_for_each_other(): void
    {
        $this->publishedCycle();
        $review = $this->reviewOf(10);

        $this->assertSame(403, $this->refusal(fn () => $this->service()->saveSelf($review, $this->userFor(11), $this->ratings($review, [5, 5]), null, true))->status());
        $this->assertSame(403, $this->refusal(fn () => $this->service()->saveSelf($review, $this->userFor(11), $this->ratings($review, [5, 5]), null, true, true))->status());
        $this->assertSame(403, $this->refusal(fn () => $this->service()->saveSelf($review, $this->userFor(2), $this->ratings($review, [5, 5]), null, true))->status());
        $this->assertSame([null, null], $review->items()->pluck('self_rating')->all());
    }

    public function test_hr_can_skip_a_self_rating_with_a_reason_and_the_review_moves_on(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);

        $this->assertStringContainsString('Say why', $this->refusal(fn () => $this->service()->skipSelf($review, $this->hrUser(), '  '))->getMessage());

        $review = $this->service()->skipSelf($review, $this->hrUser(), 'On long leave');
        $this->assertSame(PerformanceReview::STATUS_AWAITING_MEETING, $review->status);
        $this->assertTrue($review->self_skipped);
        $this->assertSame('On long leave', PerformanceReviewEvent::where('action', 'self_skipped')->value('note'));
    }

    public function test_only_the_named_supervisor_can_rate_and_only_when_it_is_their_turn(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);

        // Not yet: the staff member has not self-rated
        $this->assertSame(422, $this->refusal(fn () => $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [5, 5]), null, true))->status());

        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [5, 5]), null, true);
        $review = $this->holdMeeting($review);

        // Zola holds the same position but was not named; HR cannot rate as the supervisor; the staff member cannot rate themselves again
        foreach ([$this->userFor(3), $this->hrUser(), $this->userFor(10), $this->userFor(21)] as $someone) {
            $this->assertSame(403, $this->refusal(fn () => $this->service()->saveSupervisor($review, $someone, $this->ratings($review, [9, 9]), null, true))->status());
        }
        $this->assertSame([null, null], $review->items()->pluck('supervisor_rating')->all());

        $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [9, 9]), null, true);
        $this->assertSame([9, 9], $review->items()->pluck('supervisor_rating')->all());
    }

    public function test_only_hr_gives_the_final_rating_and_only_once_it_is_waiting_for_hr(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $this->assertSame(422, $this->refusal(fn () => $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [9, 9]), null, true))->status(), 'not waiting for HR yet');

        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [5, 5]), null, true);
        $review = $this->holdMeeting($review);
        $review = $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [5, 5]), null, true);

        foreach ([$this->userFor(2), $this->userFor(10), $this->userFor(21), $this->userFor(20)] as $someone) {
            $this->assertSame(403, $this->refusal(fn () => $this->service()->saveHr($review, $someone, $this->ratings($review, [9, 9]), null, true))->status());
        }
        $this->assertNull($review->refresh()->final_score);
    }

    public function test_hr_can_save_its_ratings_before_finalising(): void
    {
        $this->publishedCycle();
        $review = $this->reviewOf(11);
        $review = $this->service()->saveSelf($review, $this->userFor(11), $this->ratings($review, [5, 5]), null, true);
        $review = $this->holdMeeting($review);

        $review = $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [8]), 'thinking', false);

        $this->assertSame(PerformanceReview::STATUS_AWAITING_HR, $review->status);
        $this->assertNull($review->final_score);
        $this->assertSame([8, null], $review->items()->pluck('hr_rating')->all());
        $this->assertStringContainsString('Still to rate', $this->refusal(fn () => $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [8]), null, true))->getMessage());
    }

    public function test_nobody_finalises_their_own_review_even_when_they_are_hr(): void
    {
        $this->criteria(self::POS_HR);
        app(PerformanceCycleService::class)->publish($this->draftCycle(), $this->hrUser());
        $review = $this->reviewOf(60);
        $review = $this->service()->saveSelf($review, $this->userFor(60, ['hr_manager']), $this->ratings($review, [10, 10]), null, true);
        $review = $this->holdMeeting($review);
        $this->assertSame(PerformanceReview::STATUS_AWAITING_HR, $review->status, 'no supervisor login above them, so it waits for HR');

        $exception = $this->refusal(fn () => $this->service()->saveHr($review, $this->userFor(60, ['hr_manager']), $this->ratings($review, [10, 10]), null, true));
        $this->assertSame(403, $exception->status());

        // A director gives it
        $review = $this->service()->saveHr($review, $this->userFor(null, ['directors']), $this->ratings($review, [7, 7]), null, true);
        $this->assertSame(7.0, $review->final_score);
    }

    public function test_hr_who_is_also_the_supervisor_cannot_give_the_final_rating_too(): void
    {
        DB::table('sys_set_staff_positions')->where('id', self::POS_ACCOUNTANT)->update(['reports_to_id' => self::POS_HR]); // the HR manager supervises the accountant
        $this->criteria(self::POS_ACCOUNTANT);
        app(PerformanceCycleService::class)->publish($this->draftCycle(), $this->hrUser());
        $review = $this->reviewOf(21);
        $this->assertSame(60, (int) $review->supervisor_staff_id);

        $hrManager = $this->userFor(60, ['hr_manager']);
        $review = $this->service()->saveSelf($review, $this->userFor(21), $this->ratings($review, [6, 6]), null, true);
        $review = $this->holdMeeting($review);
        $review = $this->service()->saveSupervisor($review, $hrManager, $this->ratings($review, [7, 7]), null, true);

        $this->assertSame(403, $this->refusal(fn () => $this->service()->saveHr($review, $hrManager, $this->ratings($review, [7, 7]), null, true))->status());
        $this->assertSame(PerformanceReview::STATUS_FINALISED, $this->service()->saveHr($review, $this->userFor(null, ['directors']), $this->ratings($review, [7, 7]), null, true)->status);
    }

    public function test_hr_sends_a_review_back_to_the_supervisor_with_a_reason(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [5, 5]), null, true);
        $review = $this->holdMeeting($review);
        $review = $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [9, 9]), null, true);

        $this->assertStringContainsString('Say why', $this->refusal(fn () => $this->service()->returnToSupervisor($review, $this->hrUser(), ''))->getMessage());

        $review = $this->service()->returnToSupervisor($review, $this->hrUser(), 'Rating 9 needs an example');

        $this->assertSame(PerformanceReview::STATUS_AWAITING_SUPERVISOR, $review->status);
        $this->assertNull($review->supervisor_submitted_at);
        $this->assertSame([9, 9], $review->items()->pluck('supervisor_rating')->all(), 'their ratings stay as the starting point');
        $this->assertSame('Rating 9 needs an example', PerformanceReviewEvent::where('action', 'returned_to_supervisor')->value('note'));
    }

    public function test_a_review_with_no_supervisor_cannot_be_sent_back(): void
    {
        $this->publishedCycle();
        $review = $this->service()->saveSelf($this->reviewOf(11), $this->userFor(11), $this->ratings($this->reviewOf(11), [5, 5]), null, true);
        $review = $this->holdMeeting($review);

        $this->assertSame(422, $this->refusal(fn () => $this->service()->returnToSupervisor($review, $this->hrUser(), 'why'))->status());
    }

    // ── changing the supervisor ───────────────────────────────────────────

    public function test_hr_can_hand_a_waiting_review_to_another_holder_with_a_reason(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [5, 5]), null, true);
        $review = $this->holdMeeting($review);

        $this->assertStringContainsString('Say why', $this->refusal(fn () => $this->service()->reassign($review, $this->hrUser(), 3, ''))->getMessage());
        $review = $this->service()->reassign($review, $this->hrUser(), 3, 'Zane is on leave');

        $this->assertSame(3, (int) $review->supervisor_staff_id);
        $this->assertSame(PerformanceReview::STATUS_AWAITING_SUPERVISOR, $review->status);
        $event = PerformanceReviewEvent::where('action', 'supervisor_changed')->first();
        $this->assertSame('Zane is on leave', $event->note);
        $this->assertSame(['from_supervisor_staff_id' => 2, 'to_supervisor_staff_id' => 3], $event->details);

        // Zane can no longer rate it; Zola can
        $this->assertSame(403, $this->refusal(fn () => $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [8, 8]), null, true))->status());
        $this->service()->saveSupervisor($review, $this->userFor(3), $this->ratings($review, [8, 8]), null, true);
    }

    public function test_the_new_supervisor_must_hold_the_position_and_have_a_login(): void
    {
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        DB::table('sys_staff')->insert(['id' => 4, 'full_names' => 'Zed NoLogin', 'position_id' => self::POS_ZONE_MANAGER, 'current_status_id' => 1]);

        $this->assertStringContainsString('does not hold the position', $this->refusal(fn () => $this->service()->reassign($review, $this->hrUser(), 20, 'x'))->getMessage());
        $this->assertStringContainsString('no system login', $this->refusal(fn () => $this->service()->reassign($review, $this->hrUser(), 4, 'x'))->getMessage());
        $this->assertNull($review->refresh()->supervisor_staff_id);
    }

    public function test_hr_can_name_a_supervisor_on_a_review_that_went_to_hr_and_can_take_one_back(): void
    {
        $this->publishedCycle(); // nobody named: staff 10 goes to HR
        $review = $this->reviewOf(10);
        $this->assertSame('hr', $review->route);

        $review = $this->service()->reassign($review, $this->hrUser(), 2, 'Naming Zane');
        $this->assertSame(['supervisor', 2], [$review->route, (int) $review->supervisor_staff_id]);

        $review = $this->service()->reassign($review, $this->hrUser(), null, 'Zane is away; HR will rate');
        $this->assertSame(['hr', null, 'hr_took_over'], [$review->route, $review->supervisor_staff_id, $review->route_reason]);
    }

    public function test_taking_over_a_review_that_waits_for_the_supervisor_moves_it_to_hr(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->service()->saveSelf($this->reviewOf(10), $this->userFor(10), $this->ratings($this->reviewOf(10), [5, 5]), null, true);
        $review = $this->holdMeeting($review);
        $this->assertSame(PerformanceReview::STATUS_AWAITING_SUPERVISOR, $review->status);

        $review = $this->service()->reassign($review, $this->hrUser(), null, 'Supervisor has left');

        $this->assertSame(PerformanceReview::STATUS_AWAITING_HR, $review->status);
    }

    public function test_a_supervisor_who_already_rated_is_not_swapped_out_from_under_them(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->service()->saveSelf($this->reviewOf(10), $this->userFor(10), $this->ratings($this->reviewOf(10), [5, 5]), null, true);
        $review = $this->holdMeeting($review);
        $review = $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [6, 6]), null, true);

        $this->assertStringContainsString('Send it back to them instead', $this->refusal(fn () => $this->service()->reassign($review, $this->hrUser(), 3, 'x'))->getMessage());
    }

    public function test_a_finalised_review_cannot_change_supervisor(): void
    {
        $this->publishedCycle();
        $review = $this->service()->saveSelf($this->reviewOf(11), $this->userFor(11), $this->ratings($this->reviewOf(11), [5, 5]), null, true);
        $review = $this->holdMeeting($review);
        $review = $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [5, 5]), null, true);

        $this->assertSame(422, $this->refusal(fn () => $this->service()->reassign($review, $this->hrUser(), 2, 'x'))->status());
    }

    // ── correcting a finalised review ─────────────────────────────────────

    private function finalisedReview(): PerformanceReview
    {
        $this->publishedCycle();
        $review = $this->service()->saveSelf($this->reviewOf(11), $this->userFor(11), $this->ratings($this->reviewOf(11), [5, 5]), null, true);
        $review = $this->holdMeeting($review);

        return $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [6, 8], 'ok'), 'Good', true);
    }

    public function test_a_correction_keeps_the_old_values_and_the_reason(): void
    {
        $review = $this->finalisedReview();
        $this->assertSame(7.0, $review->final_score);

        $this->assertStringContainsString('Say why', $this->refusal(fn () => $this->service()->correct($review, $this->hrUser(), $this->ratings($review, [9, 9]), 'Better', ' '))->getMessage());
        $review = $this->service()->correct($review, $this->hrUser(), $this->ratings($review, [9, 7], 'fixed'), 'Better', 'Typed the wrong number');

        $this->assertSame(8.0, $review->final_score);
        $this->assertSame('Better', $review->final_comment);
        $this->assertSame(PerformanceReview::STATUS_FINALISED, $review->status);

        $event = PerformanceReviewEvent::where('action', 'corrected')->first();
        $this->assertSame('Typed the wrong number', $event->note);
        $this->assertEquals(7, $event->details['before']['final_score']); // JSON keeps 7.0 as 7
        $this->assertSame('Good', $event->details['before']['final_comment']);
        $this->assertSame([6, 8], array_values(array_map(fn ($row) => $row['hr_rating'], $event->details['before']['items'])));
    }

    public function test_only_hr_corrects_and_only_a_finalised_review(): void
    {
        $review = $this->finalisedReview();

        $this->assertSame(403, $this->refusal(fn () => $this->service()->correct($review, $this->userFor(11), $this->ratings($review, [10, 10]), null, 'x'))->status());
        $this->assertSame(403, $this->refusal(fn () => $this->service()->correct($review, $this->userFor(2), $this->ratings($review, [10, 10]), null, 'x'))->status());

        $open = $this->reviewOf(10);
        $this->assertSame(422, $this->refusal(fn () => $this->service()->correct($open, $this->hrUser(), $this->ratings($open, [10, 10]), null, 'x'))->status());
    }

    // ── a closed cycle ────────────────────────────────────────────────────

    public function test_a_closed_cycle_can_be_read_but_not_changed(): void
    {
        $cycle = $this->publishedCycle();
        $review = $this->reviewOf(11);
        app(PerformanceCycleService::class)->close($cycle);

        $this->assertSame(422, $this->refusal(fn () => $this->service()->saveSelf($review, $this->userFor(11), $this->ratings($review, [5, 5]), null, true))->status());
        $this->assertNull($this->access()->denial($this->userFor(11), $review->refresh(), 'view'));
    }

    // ── who can open a review ─────────────────────────────────────────────

    public function test_who_can_open_a_review(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);

        $this->assertNull($this->access()->denial($this->userFor(10), $review, 'view'), 'the staff member');
        $this->assertNull($this->access()->denial($this->userFor(2), $review, 'view'), 'the named supervisor');
        $this->assertNull($this->access()->denial($this->hrUser(), $review, 'view'), 'hr');
        $this->assertNull($this->access()->denial($this->userFor(null, ['directors']), $review, 'view'), 'a director');

        foreach ([
            'another holder of the supervisor position' => $this->userFor(3),
            'a colleague' => $this->userFor(11),
            'someone in another team' => $this->userFor(21),
            'someone with no staff record or role' => $this->userFor(null),
            'someone with an unrelated role' => $this->userFor(21, ['accountant']),
        ] as $who => $user) {
            $this->assertSame(403, $this->access()->denial($user, $review, 'view')['status'], $who);
        }
    }

    public function test_a_supervisor_who_has_left_loses_access(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        DB::table('sys_staff')->where('id', 2)->update(['current_status_id' => 3]);

        $this->assertSame(403, $this->access()->denial($this->userFor(2), $review, 'view')['status']);
    }

    public function test_each_person_sees_only_the_reviews_they_may_open(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();

        $ids = fn ($user) => $this->access()->visibleTo($user)->pluck('staff_id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        $this->assertSame([10], $ids($this->userFor(10)), 'a staff member sees their own');
        $this->assertSame([10], $ids($this->userFor(2)), 'the named supervisor sees who they supervise');
        $this->assertSame([], $ids($this->userFor(3)), 'another holder sees nothing');
        $this->assertSame([10, 11, 12, 21], $ids($this->hrUser()), 'hr sees all');
        $this->assertSame([], $ids($this->userFor(null)));
    }

    // ── what each person is shown ─────────────────────────────────────────

    /** A review that has been rated by everyone and finalised, with words that must not reach the staff member. */
    private function finalisedReviewWithSecrets(): PerformanceReview
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8, 8], 'my own words'), 'my summary', true);
        $review = $this->holdMeeting($review);
        $review = $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [3, 3], 'SECRET supervisor comment'), 'SECRET supervisor summary', true);

        return $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [6, 6], 'hr words'), 'final words', true);
    }

    private function present($viewer, PerformanceReview $review): array
    {
        return app(PerformanceReviewPresenter::class)->show($viewer, $review);
    }

    public function test_once_finalised_the_staff_member_sees_what_the_supervisor_wrote_but_not_who_wrote_it(): void
    {
        $review = $this->finalisedReviewWithSecrets();
        $review->update(['metrics' => ['absenteeism' => ['value' => 12]]]);

        $payload = $this->present($this->userFor(10), $review->refresh());
        $json = json_encode($payload);

        foreach (['Zane', 'metrics', 'history', 'route'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, $secret);
        }
        $this->assertSame([3, 3], array_column($payload['items'], 'supervisor_rating'));
        $this->assertSame(3.0, $payload['scores']['supervisor']);
        $this->assertSame('SECRET supervisor summary', $payload['supervisor_summary']);
        $this->assertStringContainsString('SECRET supervisor comment', $json);
        $this->assertStringContainsString('my own words', $json);
        $this->assertStringContainsString('final words', $json, 'once finalised they see the final comment');
    }

    public function test_before_it_is_finalised_the_staff_member_never_sees_what_the_supervisor_wrote(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8, 8]), null, true);
        $review = $this->holdMeeting($review);
        $review = $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [3, 3], 'SECRET supervisor comment'), 'SECRET supervisor summary', true);

        $payload = $this->present($this->userFor(10), $review->refresh());
        $json = json_encode($payload);

        foreach (['SECRET', 'supervisor_rating', 'supervisor_comment', 'supervisor_summary', 'Zane'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, $secret);
        }
        $this->assertArrayNotHasKey('supervisor', $payload['scores']);
    }

    public function test_before_it_is_finalised_the_staff_member_sees_their_own_ratings_and_no_final_one(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8, 8]), null, true);
        $review = $this->holdMeeting($review);
        $review = $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [3, 3], 'SECRET'), null, true);
        $review = $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [6]), 'draft hr comment', false);

        $payload = $this->present($this->userFor(10), $review->refresh());
        $json = json_encode($payload);

        $this->assertSame('subject', $payload['role']);
        $this->assertSame(8, $payload['items'][0]['self_rating']);
        $this->assertArrayNotHasKey('hr_rating', $payload['items'][0]);
        $this->assertArrayNotHasKey('final', $payload);
        $this->assertStringNotContainsString('SECRET', $json);
        $this->assertStringNotContainsString('draft hr comment', $json);
    }

    public function test_the_final_view_for_the_staff_member_is_their_own_the_supervisors_and_hrs_ratings(): void
    {
        $review = $this->finalisedReviewWithSecrets();

        $payload = $this->present($this->userFor(10), $review);

        $this->assertSame(6.0, $payload['final']['score']);
        $this->assertSame('final words', $payload['final']['comment']);
        $this->assertSame([6, 6], array_column($payload['items'], 'hr_rating'));
        $this->assertSame([3, 3], array_column($payload['items'], 'supervisor_rating'));
        $this->assertSame([8, 8], array_column($payload['items'], 'self_rating'));
        $this->assertSame([], array_keys(array_diff_key($payload['items'][0], array_flip(['id', 'name', 'description', 'weight', 'self_rating', 'self_comment', 'supervisor_rating', 'supervisor_comment', 'hr_rating', 'hr_comment']))));
    }

    public function test_a_staff_member_who_is_also_hr_still_gets_only_the_staff_view_of_their_own_review(): void
    {
        $this->criteria(self::POS_HR);
        app(PerformanceCycleService::class)->publish($this->draftCycle(), $this->hrUser());
        $review = $this->reviewOf(60);

        $payload = $this->present($this->userFor(60, ['hr_manager']), $review);

        $this->assertSame('subject', $payload['role']);
        $this->assertArrayNotHasKey('history', $payload);
        $this->assertArrayNotHasKey('supervisor_candidates', $payload);
        $this->assertSame(['self'], $payload['actions']);
    }

    public function test_the_supervisor_sees_the_self_rating_once_it_is_submitted_and_never_a_draft(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8, 4], 'draft words'), 'draft summary', false);

        $draft = json_encode($this->present($this->userFor(2), $review));
        $this->assertStringNotContainsString('draft words', $draft);
        $this->assertStringNotContainsString('draft summary', $draft);

        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8, 4], 'final self words'), 'self summary', true);
        $review = $this->holdMeeting($review);
        $payload = $this->present($this->userFor(2), $review);

        $this->assertSame('supervisor', $payload['role']);
        $this->assertSame([8, 4], array_column($payload['items'], 'self_rating'));
        $this->assertSame('self summary', $payload['self_summary']);
        $this->assertSame(6.0, $payload['scores']['self']);
        $this->assertSame(['supervisor'], $payload['actions']);
    }

    public function test_the_supervisor_does_not_see_hrs_working_ratings_only_the_final_result(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [5, 5]), null, true);
        $review = $this->holdMeeting($review);
        $review = $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [5, 5]), null, true);
        $review = $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [9]), 'HR WORKING', false);

        $this->assertStringNotContainsString('HR WORKING', json_encode($this->present($this->userFor(2), $review->refresh())));

        $review = $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [9, 9]), 'HR FINAL', true);
        $payload = $this->present($this->userFor(2), $review);
        $this->assertSame(9.0, $payload['final']['score']);
    }

    public function test_hr_sees_everything_including_the_history_and_who_could_be_supervisor(): void
    {
        $review = $this->finalisedReviewWithSecrets();

        $payload = $this->present($this->hrUser(), $review);

        $this->assertSame('hr', $payload['role']);
        $this->assertSame([3, 3], array_column($payload['items'], 'supervisor_rating'));
        $this->assertSame([8, 8], array_column($payload['items'], 'self_rating'));
        $this->assertSame([6, 6], array_column($payload['items'], 'hr_rating'));
        $this->assertSame('SECRET supervisor summary', $payload['supervisor_summary']);
        $this->assertSame('Zane Zonemanager', $payload['supervisor']);
        $this->assertSame(['created', 'self_submitted', 'meeting_scheduled', 'meeting_held', 'supervisor_submitted', 'finalised'], array_column($payload['history'], 'action'));
        $this->assertSame([2, 3], array_column($payload['supervisor_candidates'], 'id'));
        $this->assertSame(['correct'], $payload['actions']);
    }

    public function test_what_each_person_can_do_next_is_listed_for_the_page(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);

        $this->assertSame(['self'], $this->present($this->userFor(10), $review)['actions']);
        $this->assertSame([], $this->present($this->userFor(2), $review)['actions']);
        $this->assertSame(['self_on_behalf', 'skip_self', 'reassign'], $this->present($this->hrUser(), $review)['actions']);

        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [5, 5]), null, true);
        $review = $this->holdMeeting($review);
        $this->assertSame([], $this->present($this->userFor(10), $review)['actions']);
        $this->assertSame(['supervisor'], $this->present($this->userFor(2), $review)['actions']);
        $this->assertSame(['reassign'], $this->present($this->hrUser(), $review)['actions']);

        $review = $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [5, 5]), null, true);
        $this->assertSame(['hr', 'return'], $this->present($this->hrUser(), $review)['actions'], 'a supervisor who has rated is not swapped out; HR sends it back instead');
    }

    public function test_a_list_row_shows_the_final_score_only_when_the_review_is_finalised(): void
    {
        $this->publishedCycle();
        $review = $this->service()->saveSelf($this->reviewOf(11), $this->userFor(11), $this->ratings($this->reviewOf(11), [5, 5]), null, true);
        $review = $this->holdMeeting($review);
        $presenter = app(PerformanceReviewPresenter::class);

        $this->assertNull($presenter->row($review)['final_score']);

        $review = $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [8, 8]), null, true);
        $this->assertSame(8.0, $presenter->row($review)['final_score']);
        $this->assertSame('Finalised', $presenter->row($review)['status_label']);
    }
}
