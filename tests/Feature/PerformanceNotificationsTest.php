<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Performance\PerformanceCycleService;
use App\Services\Performance\PerformanceNotifications;
use App\Services\Performance\PerformanceReviewService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsPerformanceOrg;
use Tests\TestCase;

/**
 * In-app alerts for Performance Review: whoever has to do the next thing is told, the staff member is told when their review
 * opens and when it is finalised, and no alert ever carries a rating or a comment.
 *
 * Logins in the test company: staff 2, 3, 10, 11, 20, 21 and 60 (the HR manager) have one, user id = 1000 + staff id; user 2000
 * is a director with no staff record. Staff 12 has no login.
 */
class PerformanceNotificationsTest extends TestCase
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

    private function service(): PerformanceReviewService
    {
        return app(PerformanceReviewService::class);
    }

    private function reviewOf(int $staffId): PerformanceReview
    {
        return PerformanceReview::where('staff_id', $staffId)->firstOrFail();
    }

    private function ratings(PerformanceReview $review, array $values, ?string $comment = null): array
    {
        $out = [];
        foreach ($review->items()->get() as $index => $item) {
            $out[$item->id] = ['rating' => $values[$index] ?? null, 'comment' => $comment];
        }

        return $out;
    }

    /** The alerts a login has, as [type => count]. */
    private function alertsOf(int $userId): array
    {
        return Notification::where('notifiable_id', $userId)->pluck('type')->countBy()->all();
    }

    private function allAlerts(): Collection
    {
        return Notification::orderBy('id')->get();
    }

    // ── a cycle opens ─────────────────────────────────────────────────────

    public function test_publishing_tells_each_reviewed_staff_member_with_a_login_that_their_review_is_open(): void
    {
        $this->publishedCycle();

        $this->assertSame([PerformanceNotifications::OPENED => 1], $this->alertsOf(1010));
        $this->assertSame([PerformanceNotifications::OPENED => 1], $this->alertsOf(1011));
        $this->assertSame([PerformanceNotifications::OPENED => 1], $this->alertsOf(1021));
        $this->assertSame(3, Notification::count(), 'staff 12 has no login, so nobody is told for them; nobody else is told');
    }

    public function test_the_opening_alert_names_the_cycle_and_deadline_and_opens_that_persons_review(): void
    {
        $this->publishedCycle();

        $alert = Notification::where('notifiable_id', 1010)->first();

        $this->assertSame('Your performance review is open', $alert->title);
        $this->assertStringContainsString('Q1 2027', $alert->message);
        $this->assertStringContainsString('30 Apr 2027', $alert->message);
        $this->assertSame('/hr/performance/reviews/'.$this->reviewOf(10)->id, $alert->action_url);
        $this->assertSame(User::class, $alert->notifiable_type);
        $this->assertSame($this->reviewOf(10)->id, $alert->data['review_id']);
        $this->assertNull($alert->read_at);
    }

    public function test_staff_added_to_a_published_cycle_are_told_and_the_others_are_not_told_again(): void
    {
        $cycle = $this->publishedCycle([self::POS_FIELD_OFFICER]);
        $before = Notification::count();
        DB::table('sys_staff')->insert(['id' => 13, 'full_names' => 'Newman', 'position_id' => self::POS_FIELD_OFFICER, 'current_status_id' => 1, 'staff_number' => 'S13']);
        DB::table('josya_users')->insert(['id' => 1013, 'name' => 'user13']);
        DB::table('sys_user_staff_links')->insert(['user_id' => 1013, 'staff_id' => 13]);

        app(PerformanceCycleService::class)->addMissing($cycle, $this->hrUser());

        $this->assertSame($before + 1, Notification::count());
        $this->assertSame([PerformanceNotifications::OPENED => 1], $this->alertsOf(1013));
    }

    // ── the self-rating is in ─────────────────────────────────────────────

    public function test_when_the_self_rating_is_submitted_the_named_supervisor_is_asked_and_nobody_else(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        Notification::query()->delete();
        $review = $this->reviewOf(10);

        $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8, 6]), null, true);

        $this->assertSame([PerformanceNotifications::WAITING_FOR_SUPERVISOR => 1], $this->alertsOf(1002));
        $this->assertSame(1, Notification::count(), 'not Zola (same position, not named), not HR, not the staff member');
        $alert = Notification::first();
        $this->assertSame('A review is waiting for your rating', $alert->title);
        $this->assertStringContainsString('Felix Officer', $alert->message);
        $this->assertStringContainsString('Q1 2027', $alert->message);
    }

    public function test_saving_a_draft_tells_nobody(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        Notification::query()->delete();
        $review = $this->reviewOf(10);

        $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8]), null, false);

        $this->assertSame(0, Notification::count());
    }

    public function test_a_review_that_goes_straight_to_hr_asks_every_hr_login_except_the_staff_member(): void
    {
        $this->publishedCycle(); // staff 11 is not named, so goes to HR
        Notification::query()->delete();
        $review = $this->reviewOf(11);

        $this->service()->saveSelf($review, $this->userFor(11), $this->ratings($review, [8, 6]), null, true);

        $this->assertSame([PerformanceNotifications::WAITING_FOR_HR => 1], $this->alertsOf(1060), 'the HR manager');
        $this->assertSame([PerformanceNotifications::WAITING_FOR_HR => 1], $this->alertsOf(2000), 'the director');
        $this->assertSame(2, Notification::count());
    }

    public function test_an_hr_login_is_not_asked_to_finalise_its_own_review(): void
    {
        $this->criteria(self::POS_HR);
        app(PerformanceCycleService::class)->publish($this->draftCycle(), $this->hrUser());
        Notification::query()->delete();
        $review = $this->reviewOf(60);

        $this->service()->saveSelf($review, $this->userFor(60, ['hr_manager']), $this->ratings($review, [9, 9]), null, true);

        $this->assertSame([], $this->alertsOf(1060), 'it is their own review');
        $this->assertSame([PerformanceNotifications::WAITING_FOR_HR => 1], $this->alertsOf(2000), 'the director gives the final rating');
    }

    public function test_when_hr_enters_the_self_rating_for_someone_or_skips_it_the_next_person_is_asked(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        Notification::query()->delete();

        $review = $this->reviewOf(10);
        $this->service()->skipSelf($review, $this->hrUser(), 'On leave');
        $this->assertSame([PerformanceNotifications::WAITING_FOR_SUPERVISOR => 1], $this->alertsOf(1002));

        $review = $this->reviewOf(12); // no login, goes to HR
        $this->service()->saveSelf($review, $this->hrUser(), $this->ratings($review, [5, 5]), null, true, true);
        $this->assertSame([PerformanceNotifications::WAITING_FOR_HR => 1], $this->alertsOf(2000));
    }

    // ── the supervisor has rated ──────────────────────────────────────────

    private function reviewWaitingForHr(): PerformanceReview
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->service()->saveSelf($this->reviewOf(10), $this->userFor(10), $this->ratings($this->reviewOf(10), [8, 6]), null, true);

        return $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [7, 5], 'SECRET comment'), 'SECRET summary', true);
    }

    public function test_when_the_supervisor_has_rated_hr_is_asked(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->service()->saveSelf($this->reviewOf(10), $this->userFor(10), $this->ratings($this->reviewOf(10), [8, 6]), null, true);
        Notification::query()->delete();

        $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [7, 5]), null, true);

        $this->assertSame([PerformanceNotifications::WAITING_FOR_HR => 1], $this->alertsOf(1060));
        $this->assertSame([PerformanceNotifications::WAITING_FOR_HR => 1], $this->alertsOf(2000));
        $this->assertSame([], $this->alertsOf(1002), 'the supervisor has just done their part');
        $this->assertSame([], $this->alertsOf(1010), 'the staff member is not told about the supervisor\'s rating');
    }

    public function test_hr_who_is_also_the_supervisor_is_not_asked_for_the_final_rating(): void
    {
        DB::table('sys_set_staff_positions')->where('id', self::POS_ACCOUNTANT)->update(['reports_to_id' => self::POS_HR]);
        $this->criteria(self::POS_ACCOUNTANT);
        app(PerformanceCycleService::class)->publish($this->draftCycle(), $this->hrUser());
        $review = $this->service()->saveSelf($this->reviewOf(21), $this->userFor(21), $this->ratings($this->reviewOf(21), [6, 6]), null, true);
        $this->assertSame([PerformanceNotifications::WAITING_FOR_SUPERVISOR => 1], $this->alertsOf(1060), 'the HR manager is the supervisor, so is asked to rate');
        Notification::query()->delete();

        $this->service()->saveSupervisor($review, $this->userFor(60, ['hr_manager']), $this->ratings($review, [7, 7]), null, true);

        $this->assertSame([], $this->alertsOf(1060), 'they rated it, so a different HR person gives the final rating');
        $this->assertSame([PerformanceNotifications::WAITING_FOR_HR => 1], $this->alertsOf(2000));
    }

    // ── sent back, changed, finalised, corrected ──────────────────────────

    public function test_sending_a_review_back_tells_the_supervisor_with_the_reason(): void
    {
        $review = $this->reviewWaitingForHr();
        Notification::query()->delete();

        $this->service()->returnToSupervisor($review, $this->hrUser(), 'Rating 7 needs an example');

        $this->assertSame([PerformanceNotifications::RETURNED => 1], $this->alertsOf(1002));
        $this->assertSame(1, Notification::count());
        $this->assertStringContainsString('Rating 7 needs an example', Notification::first()->message);
        $this->assertStringContainsString('Felix Officer', Notification::first()->message);
    }

    public function test_changing_the_supervisor_asks_the_new_one_and_taking_over_asks_nobody(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->service()->saveSelf($this->reviewOf(10), $this->userFor(10), $this->ratings($this->reviewOf(10), [8, 6]), null, true);
        Notification::query()->delete();

        $review = $this->service()->reassign($review, $this->hrUser(), 3, 'Zane is away');
        $this->assertSame([PerformanceNotifications::WAITING_FOR_SUPERVISOR => 1], $this->alertsOf(1003));
        $this->assertSame([], $this->alertsOf(1002), 'the old supervisor is not told');

        Notification::query()->delete();
        $this->service()->reassign($review, $this->hrUser(), null, 'HR will rate it');
        $this->assertSame(0, Notification::count());
    }

    public function test_finalising_tells_the_staff_member_without_saying_the_score(): void
    {
        $review = $this->reviewWaitingForHr();
        Notification::query()->delete();

        $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [9, 9], 'hr words'), 'final words', true);

        $this->assertSame([PerformanceNotifications::FINALISED => 1], $this->alertsOf(1010));
        $this->assertSame(1, Notification::count());
        $alert = Notification::first();
        $this->assertSame('Your performance review is finalised', $alert->title);
        $this->assertStringNotContainsString('9', $alert->message);
        $this->assertStringNotContainsString('final words', $alert->message);
    }

    public function test_a_correction_tells_the_staff_member(): void
    {
        $review = $this->reviewWaitingForHr();
        $review = $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [9, 9]), null, true);
        Notification::query()->delete();

        $this->service()->correct($review, $this->userFor(null, ['directors']), $this->ratings($review, [8, 8]), null, 'Typo');

        $this->assertSame([PerformanceNotifications::CORRECTED => 1], $this->alertsOf(1010));
        $this->assertStringNotContainsString('Typo', Notification::first()->message);
    }

    // ── what an alert may carry, and who may get one ──────────────────────

    public function test_no_alert_in_the_whole_flow_carries_a_rating_or_a_comment(): void
    {
        $review = $this->reviewWaitingForHr();
        $review = $this->service()->returnToSupervisor($review, $this->hrUser(), 'look again');
        $review = $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [4, 4], 'SECRET comment'), 'SECRET summary', true);
        $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [3, 3], 'SECRET hr'), 'SECRET final', true);

        $text = $this->allAlerts()->map(fn ($alert) => $alert->title.' '.$alert->message.' '.json_encode($alert->data))->implode(' ');

        $this->assertStringNotContainsString('SECRET', $text);
        $this->assertDoesNotMatchRegularExpression('/\b(rated|rating of|score)\b.*\b\d\b/i', $text);
        $this->assertGreaterThan(5, $this->allAlerts()->count());
    }

    public function test_a_blocked_or_unactivated_login_is_not_told(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        Notification::query()->delete();
        DB::table('josya_users')->where('id', 1002)->update(['block' => 1]);
        DB::table('josya_users')->where('id', 2000)->update(['activation' => 0]);
        $review = $this->reviewOf(10);
        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8, 6]), null, true);

        $this->assertSame(0, Notification::count(), 'the supervisor\'s login is blocked');

        $this->service()->reassign($review, $this->hrUser(), null, 'HR takes over');
        $this->service()->saveHr($review->refresh(), $this->hrUser(), $this->ratings($review, [5, 5]), null, true);
        $this->assertSame([PerformanceNotifications::FINALISED => 1], $this->alertsOf(1010));
    }

    public function test_someone_who_has_left_is_not_told(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8, 6]), null, true);
        $this->assertSame([PerformanceNotifications::WAITING_FOR_SUPERVISOR => 1], $this->alertsOf(1002));
        Notification::query()->delete();

        DB::table('sys_staff')->where('id', 10)->update(['current_status_id' => 3]);
        $review = $this->service()->reassign($review, $this->hrUser(), null, 'HR takes over');
        $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [5, 5]), null, true);

        $this->assertSame([], $this->alertsOf(1010), 'the staff member has left');
    }

    public function test_a_fault_in_sending_an_alert_never_stops_a_rating(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        Schema::drop('notifications');
        $review = $this->reviewOf(10);

        $saved = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8, 6]), null, true);

        $this->assertSame(PerformanceReview::STATUS_AWAITING_SUPERVISOR, $saved->status);
        $this->assertSame([8, 6], $saved->items()->pluck('self_rating')->all());
    }

    public function test_a_fault_in_sending_the_opening_alerts_never_stops_a_cycle_being_published(): void
    {
        $this->criteria(self::POS_FIELD_OFFICER);
        Schema::drop('notifications');

        $made = app(PerformanceCycleService::class)->publish($this->draftCycle(), $this->hrUser());

        $this->assertSame(3, $made);
        $this->assertSame(3, PerformanceReview::count());
    }

    public function test_the_alerts_are_the_kind_the_bell_already_reads(): void
    {
        $this->publishedCycle([self::POS_FIELD_OFFICER]);

        $alert = Notification::where('notifiable_id', 1010)->first();

        // The bell reads type, title, message and action_url for the signed-in user, as it does for leave
        $this->assertSame(User::class, $alert->notifiable_type);
        $this->assertNotEmpty($alert->title);
        $this->assertNotEmpty($alert->message);
        $this->assertStringStartsWith('/hr/performance/reviews/', $alert->action_url);
    }
}
