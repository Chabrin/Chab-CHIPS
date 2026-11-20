<?php

namespace Tests\Feature;

use App\Http\Middleware\AccessChecker;
use App\Http\Middleware\OneLogin;
use App\Models\PerformanceCycle;
use App\Models\PerformanceKpi;
use App\Models\PerformanceReview;
use App\Models\PerformanceSupervisorAssignment;
use App\Services\Performance\PerformanceCycleService;
use App\Services\Performance\PerformanceReviewService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsPerformanceOrg;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * HR > Performance Review over HTTP: the pages open for the right people and for nobody else, HR's set-up screens (KPIs,
 * cycles, supervisors) validate and save, and the review screens answer each person with only what they may see.
 */
class PerformanceReviewHttpTest extends TestCase
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

    private function service(): PerformanceReviewService
    {
        return app(PerformanceReviewService::class);
    }

    // ── the set-up pages are for HR only ──────────────────────────────────

    public function test_the_setup_pages_open_for_hr_and_for_nobody_else(): void
    {
        foreach (['performance.kpis', 'performance.cycles', 'performance.supervisors', 'performance.all'] as $route) {
            $this->asHr();
            $this->get(route($route))->assertOk();

            foreach ([$this->userFor(10), $this->userFor(2), $this->userFor(null)] as $someone) {
                $this->actingAs($someone);
                $this->get(route($route))->assertForbidden();
            }
        }
    }

    public function test_the_setup_requests_are_refused_to_staff_and_supervisors(): void
    {
        $this->publishedCycle();
        $this->actAs(2);

        $this->getJson(route('ajax.performance.positions'))->assertForbidden();
        $this->postJson(route('ajax.performance.kpis.store'), ['position_id' => 3, 'name' => 'Hack'])->assertForbidden();
        $this->postJson(route('ajax.performance.cycles.store'), ['name' => 'X', 'period_start' => '2027-01-01', 'period_end' => '2027-02-01'])->assertForbidden();
        $this->postJson(route('ajax.performance.supervisors.assign'), ['staff_ids' => [10], 'supervisor_staff_id' => 2])->assertForbidden();
        $this->getJson(route('ajax.performance.reviews.list', 'all'))->assertForbidden();
        $this->assertSame(0, PerformanceSupervisorAssignment::count());
        $this->assertSame(0, PerformanceKpi::where('name', 'Hack')->count());
    }

    // ── KPIs ──────────────────────────────────────────────────────────────

    public function test_the_positions_list_shows_who_each_reports_to_and_how_many_criteria_it_has(): void
    {
        $this->criteria(self::POS_FIELD_OFFICER);
        DB::table('sys_set_staff_positions')->where('id', self::POS_LONE)->update(['is_active' => false]);
        $this->asHr();

        $positions = collect($this->getJson(route('ajax.performance.positions'))->assertOk()->json('positions'))->keyBy('id');

        $this->assertSame('Zone Manager', $positions[self::POS_FIELD_OFFICER]['reports_to']);
        $this->assertSame(2, $positions[self::POS_FIELD_OFFICER]['kpi_count']);
        $this->assertSame(3, $positions[self::POS_FIELD_OFFICER]['staff_count'], 'the field officer who left is not counted');
        $this->assertNull($positions[self::POS_MD]['reports_to']);
        $this->assertFalse($positions->has(self::POS_LONE), 'a switched-off position is not offered');
    }

    public function test_hr_adds_a_criterion_to_a_position(): void
    {
        $this->asHr();

        $this->postJson(route('ajax.performance.kpis.store'), ['position_id' => self::POS_FIELD_OFFICER, 'name' => ' Attendance ', 'description' => 'Turns up on time', 'weight' => 2])
            ->assertOk()->assertJsonPath('success', true);

        $kpi = PerformanceKpi::firstOrFail();
        $this->assertSame('Attendance', $kpi->name);
        $this->assertSame(2.0, $kpi->weight);
        $this->assertSame(10, $kpi->sort_order);
        $this->assertTrue($kpi->is_active);

        $this->postJson(route('ajax.performance.kpis.store'), ['position_id' => self::POS_FIELD_OFFICER, 'name' => 'Teamwork'])->assertOk();
        $this->assertSame(1.0, PerformanceKpi::where('name', 'Teamwork')->value('weight'), 'weight defaults to 1');
        $this->assertSame(20, (int) PerformanceKpi::where('name', 'Teamwork')->value('sort_order'), 'it goes last');
    }

    public function test_a_criterion_needs_a_name_a_real_position_and_a_sensible_weight(): void
    {
        $this->asHr();

        $this->postJson(route('ajax.performance.kpis.store'), ['position_id' => 3, 'name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson(route('ajax.performance.kpis.store'), ['position_id' => 999, 'name' => 'X'])->assertStatus(422)->assertJsonValidationErrors('position_id');
        $this->postJson(route('ajax.performance.kpis.store'), ['name' => 'X'])->assertStatus(422)->assertJsonValidationErrors('position_id');
        foreach ([0, -1, 101, 'abc'] as $weight) {
            $this->postJson(route('ajax.performance.kpis.store'), ['position_id' => 3, 'name' => 'X', 'weight' => $weight])->assertStatus(422)->assertJsonValidationErrors('weight');
        }
        $this->assertSame(0, PerformanceKpi::count());
    }

    public function test_a_position_cannot_have_two_criteria_with_the_same_name_but_another_position_can(): void
    {
        $this->asHr();
        $this->postJson(route('ajax.performance.kpis.store'), ['position_id' => 3, 'name' => 'Attendance'])->assertOk();

        $this->postJson(route('ajax.performance.kpis.store'), ['position_id' => 3, 'name' => 'attendance'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson(route('ajax.performance.kpis.store'), ['position_id' => 4, 'name' => 'Attendance'])->assertOk();
        $this->assertSame(2, PerformanceKpi::count());
    }

    public function test_hr_edits_a_criterion_and_it_stays_with_its_position(): void
    {
        [$kpi] = $this->criteria(3, [['Attendance', 1]]);
        $this->asHr();

        $this->postJson(route('ajax.performance.kpis.update', $kpi->id), ['position_id' => 4, 'name' => 'Punctuality', 'weight' => 3, 'description' => 'On time', 'sort_order' => 50])->assertOk();

        $kpi->refresh();
        $this->assertSame(['Punctuality', 3.0, 50, 3], [$kpi->name, $kpi->weight, $kpi->sort_order, (int) $kpi->position_id]);
        $this->postJson(route('ajax.performance.kpis.update', $kpi->id), ['name' => 'Punctuality'])->assertOk()->assertJsonPath('success', true);
    }

    public function test_renaming_a_criterion_to_the_name_of_another_on_the_same_position_is_refused(): void
    {
        [$first, $second] = $this->criteria(3);
        $this->asHr();

        $this->postJson(route('ajax.performance.kpis.update', $second->id), ['name' => 'ATTENDANCE'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->assertSame('Quality of work', $second->refresh()->name);
    }

    public function test_a_criterion_is_switched_off_and_on(): void
    {
        [$kpi] = $this->criteria(3, [['Attendance', 1]]);
        $this->asHr();

        $this->postJson(route('ajax.performance.kpis.toggle', $kpi->id))->assertOk()->assertJsonPath('is_active', false);
        $this->assertFalse($kpi->refresh()->is_active);
        $this->postJson(route('ajax.performance.kpis.toggle', $kpi->id))->assertOk()->assertJsonPath('is_active', true);
    }

    public function test_a_switched_off_criterion_is_not_in_the_next_cycle(): void
    {
        [$attendance] = $this->criteria(3);
        $attendance->update(['is_active' => false]);
        app(PerformanceCycleService::class)->publish($this->draftCycle(), $this->hrUser());

        $this->assertSame(['Quality of work'], $this->reviewOf(10)->items()->pluck('name')->all());
    }

    public function test_a_criterion_nobody_was_reviewed_on_can_be_deleted_but_one_in_a_review_cannot(): void
    {
        [$unused] = $this->criteria(4, [['Spare', 1]]);
        $unused->update(['is_active' => false]); // switched off, so the cycle does not use it
        $this->publishedCycle([3]);
        $used = PerformanceKpi::where('position_id', 3)->first();
        $this->asHr();

        $this->deleteJson(route('ajax.performance.kpis.destroy', $unused->id))->assertOk();
        $this->assertNull(PerformanceKpi::find($unused->id));

        $this->deleteJson(route('ajax.performance.kpis.destroy', $used->id))->assertStatus(422)->assertJsonPath('success', false);
        $this->assertNotNull(PerformanceKpi::find($used->id));
        $this->assertStringContainsString('Switch it off instead', $this->deleteJson(route('ajax.performance.kpis.destroy', $used->id))->json('error'));
    }

    public function test_the_list_for_a_position_says_which_criteria_were_used(): void
    {
        $this->publishedCycle([3]);
        $this->criteria(4, [['Spare', 1]]);
        $this->asHr();

        $res = $this->getJson(route('ajax.performance.kpis.list', ['position_id' => 3]))->assertOk();

        $this->assertSame('Zone Manager', $res->json('position.reports_to'));
        $this->assertSame([true, true], array_column($res->json('kpis'), 'used'));
        $this->assertSame([false], array_column($this->getJson(route('ajax.performance.kpis.list', ['position_id' => 4]))->json('kpis'), 'used'));
        $this->getJson(route('ajax.performance.kpis.list'))->assertStatus(422);
    }

    public function test_criteria_are_copied_between_positions_skipping_the_ones_already_there(): void
    {
        $this->criteria(3, [['Attendance', 2], ['Teamwork', 1], ['Quality of work', 1]]);
        $this->criteria(4, [['attendance', 1]]);
        PerformanceKpi::where('name', 'Quality of work')->update(['is_active' => false]);
        $this->asHr();

        $this->postJson(route('ajax.performance.kpis.copy'), ['from_position_id' => 3, 'to_position_id' => 4])->assertOk()->assertJsonPath('copied', 1);

        $this->assertSame(['attendance', 'Teamwork'], PerformanceKpi::where('position_id', 4)->ordered()->pluck('name')->all(), 'the switched-off one and the one it had are not copied');
        $this->postJson(route('ajax.performance.kpis.copy'), ['from_position_id' => 3, 'to_position_id' => 4])->assertOk()->assertJsonPath('copied', 0);
        $this->postJson(route('ajax.performance.kpis.copy'), ['from_position_id' => 3, 'to_position_id' => 3])->assertStatus(422);
    }

    // ── cycles ────────────────────────────────────────────────────────────

    public function test_hr_creates_a_cycle_as_a_draft_and_edits_it_until_it_is_published(): void
    {
        $this->asHr();

        $this->postJson(route('ajax.performance.cycles.store'), ['name' => ' Q1 2027 ', 'period_start' => '2027-01-01', 'period_end' => '2027-03-31', 'open_date' => '2027-04-01', 'deadline' => '2027-04-30'])->assertOk();
        $cycle = PerformanceCycle::firstOrFail();
        $this->assertSame(['Q1 2027', 'draft', 1060], [$cycle->name, $cycle->status, (int) $cycle->created_by]);

        $this->postJson(route('ajax.performance.cycles.update', $cycle->id), ['name' => 'Q1 FY27', 'period_start' => '2027-01-01', 'period_end' => '2027-03-31'])->assertOk();
        $this->assertSame('Q1 FY27', $cycle->refresh()->name);

        $this->criteria(3);
        $this->postJson(route('ajax.performance.cycles.publish', $cycle->id))->assertOk();
        $this->postJson(route('ajax.performance.cycles.update', $cycle->id), ['name' => 'Changed', 'period_start' => '2027-01-01', 'period_end' => '2027-03-31'])->assertStatus(422);
        $this->deleteJson(route('ajax.performance.cycles.destroy', $cycle->id))->assertStatus(422);
        $this->assertSame('Q1 FY27', $cycle->refresh()->name);
    }

    public function test_a_cycle_needs_a_name_and_sensible_dates(): void
    {
        $this->asHr();
        $this->draftCycle('Existing');

        $this->postJson(route('ajax.performance.cycles.store'), ['name' => '', 'period_start' => '2027-01-01', 'period_end' => '2027-03-31'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson(route('ajax.performance.cycles.store'), ['name' => 'existing', 'period_start' => '2027-01-01', 'period_end' => '2027-03-31'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson(route('ajax.performance.cycles.store'), ['name' => 'A', 'period_start' => '2027-03-31', 'period_end' => '2027-01-01'])->assertStatus(422)->assertJsonValidationErrors('period_end');
        $this->postJson(route('ajax.performance.cycles.store'), ['name' => 'B', 'period_start' => 'soon', 'period_end' => '2027-01-01'])->assertStatus(422)->assertJsonValidationErrors('period_start');
        $this->postJson(route('ajax.performance.cycles.store'), ['name' => 'C', 'period_start' => '2027-01-01', 'period_end' => '2027-03-31', 'open_date' => '2027-04-10', 'deadline' => '2027-04-01'])->assertStatus(422)->assertJsonValidationErrors('deadline');
        $this->postJson(route('ajax.performance.cycles.store'), ['name' => 'D', 'period_start' => '2027-01-01', 'period_end' => '2027-03-31', 'deadline' => '2027-04-01'])->assertOk();
        $this->assertSame(2, PerformanceCycle::count());
    }

    public function test_the_cycle_list_counts_reviews_and_finalised_ones(): void
    {
        $this->publishedCycle([3]);
        $review = $this->service()->saveSelf($this->reviewOf(11), $this->userFor(11), $this->ratings($this->reviewOf(11), [5, 5]), null, true);
        $review = $this->holdMeeting($review);
        $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [5, 5]), null, true);
        $this->asHr();

        $cycle = $this->getJson(route('ajax.performance.cycles.list'))->assertOk()->json('cycles.0');

        $this->assertSame(['published', 3, 1], [$cycle['status'], $cycle['reviews'], $cycle['finalised']]);
    }

    public function test_the_preview_publish_add_missing_and_close_work_over_http(): void
    {
        $this->criteria(3);
        $cycle = $this->draftCycle();
        $this->asHr();

        $preview = $this->getJson(route('ajax.performance.cycles.preview', $cycle->id))->assertOk();
        $this->assertSame(3, $preview->json('counts.reviews'));
        $this->assertSame(3, $preview->json('counts.straight_to_hr'), 'nobody is named yet');

        $this->postJson(route('ajax.performance.cycles.publish', $cycle->id))->assertOk()->assertJsonPath('message', 'Q1 2027 is published. 3 reviews were made.');
        $this->postJson(route('ajax.performance.cycles.publish', $cycle->id))->assertStatus(422);

        DB::table('sys_staff')->insert(['id' => 13, 'full_names' => 'Newman', 'position_id' => 3, 'current_status_id' => 1]);
        $this->postJson(route('ajax.performance.cycles.add_missing', $cycle->id))->assertOk()->assertJsonPath('message', '1 review was added.');
        $this->postJson(route('ajax.performance.cycles.add_missing', $cycle->id))->assertOk()->assertJsonPath('message', 'Everyone who can be reviewed already has a review.');

        $this->postJson(route('ajax.performance.cycles.close', $cycle->id))->assertOk();
        $this->postJson(route('ajax.performance.cycles.close', $cycle->id))->assertStatus(422);
        $this->postJson(route('ajax.performance.cycles.add_missing', $cycle->id))->assertStatus(422);
    }

    public function test_publishing_a_cycle_nobody_would_be_reviewed_in_says_so(): void
    {
        $cycle = $this->draftCycle();
        $this->asHr();

        $this->postJson(route('ajax.performance.cycles.publish', $cycle->id))->assertStatus(422)->assertJsonPath('success', false);
        $this->assertTrue($cycle->refresh()->isDraft());
    }

    // ── supervisors ───────────────────────────────────────────────────────

    public function test_the_supervisors_page_lists_those_who_report_to_a_position_with_who_could_supervise_them(): void
    {
        $this->asHr();

        $rows = collect($this->getJson(route('ajax.performance.supervisors.list'))->assertOk()->json('rows'))->keyBy('staff_id');

        $this->assertFalse($rows->has(1), 'the top position reports to nobody');
        $this->assertFalse($rows->has(30), 'neither does the lone position');
        $this->assertFalse($rows->has(50), 'someone who left is not listed');
        $this->assertSame('not_named', $rows[10]['reason']);
        $this->assertSame([2, 3], array_column($rows[10]['candidates'], 'id'));
        $this->assertSame('Zone Manager', $rows[10]['reports_to']);
        $this->assertSame(['supervisor', 'only_holder', 'Cora Chief'], [$rows[21]['route'], $rows[21]['reason'], $rows[21]['supervisor']]);
        $this->assertSame('supervisor_has_no_login', $rows[2]['reason'], 'the Managing Director has no login');
    }

    public function test_hr_names_a_supervisor_for_several_staff_at_once_and_it_is_used_by_the_cycle(): void
    {
        $this->asHr();

        $this->postJson(route('ajax.performance.supervisors.assign'), ['staff_ids' => [10, 11], 'supervisor_staff_id' => 2])->assertOk();

        $this->assertSame([2, 2], PerformanceSupervisorAssignment::whereIn('staff_id', [10, 11])->orderBy('staff_id')->pluck('supervisor_staff_id')->map(fn ($id) => (int) $id)->all());
        $rows = collect($this->getJson(route('ajax.performance.supervisors.list'))->json('rows'))->keyBy('staff_id');
        $this->assertSame(['named', 'Zane Zonemanager'], [$rows[10]['reason'], $rows[10]['supervisor']]);

        $this->postJson(route('ajax.performance.supervisors.assign'), ['staff_ids' => [10], 'supervisor_staff_id' => 3])->assertOk();
        $this->assertSame(1, PerformanceSupervisorAssignment::where('staff_id', 10)->count(), 'naming again replaces the name');
        $this->assertSame(3, (int) PerformanceSupervisorAssignment::where('staff_id', 10)->value('supervisor_staff_id'));

        $this->criteria(3);
        app(PerformanceCycleService::class)->publish($this->draftCycle(), $this->hrUser());
        $this->assertSame(3, (int) $this->reviewOf(10)->supervisor_staff_id);
        $this->assertSame(2, (int) $this->reviewOf(11)->supervisor_staff_id);
        $this->assertSame('hr', $this->reviewOf(12)->route);
    }

    public function test_only_someone_who_holds_the_position_above_can_be_named_and_nothing_is_saved_otherwise(): void
    {
        $this->asHr();

        $res = $this->postJson(route('ajax.performance.supervisors.assign'), ['staff_ids' => [10, 21], 'supervisor_staff_id' => 2])->assertStatus(422);

        $this->assertStringContainsString('Alan Accountant', $res->json('error'));
        $this->assertStringContainsString('Nothing was changed', $res->json('error'));
        $this->assertSame(0, PerformanceSupervisorAssignment::count(), 'staff 10 was fine but the request is all or nothing');

        $this->postJson(route('ajax.performance.supervisors.assign'), ['staff_ids' => [], 'supervisor_staff_id' => 2])->assertStatus(422);
        $this->postJson(route('ajax.performance.supervisors.assign'), ['staff_ids' => [10], 'supervisor_staff_id' => 9999])->assertStatus(422);
    }

    public function test_the_name_can_be_cleared(): void
    {
        PerformanceSupervisorAssignment::create(['staff_id' => 10, 'supervisor_staff_id' => 2]);
        PerformanceSupervisorAssignment::create(['staff_id' => 11, 'supervisor_staff_id' => 2]);
        $this->asHr();

        $this->postJson(route('ajax.performance.supervisors.assign'), ['staff_ids' => [10], 'supervisor_staff_id' => null])->assertOk();

        $this->assertSame([11], PerformanceSupervisorAssignment::pluck('staff_id')->map(fn ($id) => (int) $id)->all());
    }

    // ── the three lists ───────────────────────────────────────────────────

    private function rows(string $mode, array $query = []): array
    {
        return $this->getJson(route('ajax.performance.reviews.list', ['mode' => $mode] + $query))->assertOk()->json('reviews');
    }

    public function test_each_list_has_only_the_reviews_that_person_may_open(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();

        $this->actAs(10);
        $this->assertSame([$this->reviewOf(10)->id], array_column($this->rows('my'), 'id'));
        $this->assertCount(0, $this->rows('team'));

        $this->actAs(2);
        $this->assertCount(0, $this->rows('my'), 'no review of their own');
        $this->assertSame([$this->reviewOf(10)->id], array_column($this->rows('team'), 'id'));

        $this->actAs(3);
        $this->assertCount(0, $this->rows('team'), 'another holder of the position sees nothing');

        $this->asHr();
        $this->assertCount(4, $this->rows('all'));
        $this->assertCount(0, $this->rows('my'), 'hr has no review of their own in this cycle');
    }

    public function test_the_all_list_can_be_filtered_by_cycle_and_status(): void
    {
        $cycle = $this->publishedCycle();
        $review = $this->service()->saveSelf($this->reviewOf(11), $this->userFor(11), $this->ratings($this->reviewOf(11), [5, 5]), null, true);
        $review = $this->holdMeeting($review);
        $this->asHr();

        $this->assertCount(1, $this->rows('all', ['status' => 'awaiting_hr']));
        $this->assertCount(3, $this->rows('all', ['status' => 'awaiting_self']));
        $this->assertCount(4, $this->rows('all', ['cycle_id' => $cycle->id]));
        $this->assertCount(0, $this->rows('all', ['cycle_id' => 999]));
        $this->assertSame($review->id, $this->rows('all', ['status' => 'awaiting_hr'])[0]['id']);
    }

    public function test_a_login_not_linked_to_staff_is_told_so_and_an_unknown_list_is_not_found(): void
    {
        $this->actAs(null);

        $this->getJson(route('ajax.performance.reviews.list', 'my'))->assertOk()->assertJsonPath('linked', false)->assertJsonPath('reviews', []);
        $this->getJson('/ajax/hr/performance/reviews/list/everything')->assertNotFound();
    }

    public function test_a_list_row_never_carries_an_appraisal_rating_except_the_final_score(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8, 8], 'SECRET self'), null, true);
        $review = $this->holdMeeting($review);
        $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [2, 2], 'SECRET sup'), null, true);
        $this->actAs(10);

        $json = json_encode($this->rows('my'));

        $this->assertStringNotContainsString('SECRET', $json);
        $this->assertStringNotContainsString('rating', $json);
        $this->assertSame(['id', 'appeal', 'cycle', 'cycle_id', 'staff', 'position', 'supervisor', 'route', 'status', 'status_label', 'final_score', 'deadline'], array_keys($this->rows('my')[0]));
    }

    // ── one review ────────────────────────────────────────────────────────

    public function test_the_review_page_opens_for_its_people_and_for_nobody_else(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $id = $this->reviewOf(10)->id;

        foreach ([[10, []], [2, []], [60, ['hr_manager']], [null, ['directors']]] as [$staffId, $roles]) {
            $this->actAs($staffId, $roles);
            $this->get(route('performance.review', $id))->assertOk()->assertSee('prRoot', false);
            $this->getJson(route('ajax.performance.reviews.data', $id))->assertOk();
        }
        foreach ([3, 11, 21, null] as $staffId) {
            $this->actAs($staffId);
            $this->get(route('performance.review', $id))->assertForbidden();
            $this->getJson(route('ajax.performance.reviews.data', $id))->assertForbidden();
        }
        $this->actAs(10);
        $this->get(route('performance.review', 9999))->assertNotFound();
    }

    public function test_what_a_person_is_sent_never_includes_what_they_may_not_see(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $review = $this->service()->saveSelf($review, $this->userFor(10), $this->ratings($review, [8, 8], 'my words'), null, true);
        $review = $this->holdMeeting($review);
        $review = $this->service()->saveSupervisor($review, $this->userFor(2), $this->ratings($review, [2, 2], 'SECRET sup'), 'SECRET summary', true);
        $this->actAs(10);

        // Both the page (which embeds the review) and the data call, before and after the review is finalised. The page's own
        // script mentions field names, so the field-name check is made on the data the person is sent.
        $page = fn () => $this->get(route('performance.review', $review->id))->getContent();
        $data = fn () => json_encode($this->getJson(route('ajax.performance.reviews.data', $review->id))->json());

        $before = [$page(), $data()];
        $this->service()->saveHr($review, $this->hrUser(), $this->ratings($review, [6, 6], 'hr words'), 'final words', true);
        $after = [$page(), $data()];

        foreach (array_merge($before, $after) as $content) {
            $this->assertStringContainsString('my words', $content);
        }
        // The supervisor's rating and words stay hidden until the review is finalised, then the staff member is sent them
        foreach ($before as $content) {
            $this->assertStringNotContainsString('SECRET', $content);
        }
        $this->assertStringNotContainsString('supervisor_rating', $before[1]);
        $this->assertStringNotContainsString('supervisor_comment', $before[1]);
        foreach ($after as $content) {
            $this->assertStringContainsString('SECRET sup', $content);
            $this->assertStringContainsString('SECRET summary', $content);
        }
        $this->assertStringContainsString('supervisor_rating', $after[1]);
        $this->assertStringNotContainsString('final words', $before[0].$before[1]);
        $this->assertStringContainsString('final words', $after[0]);
        $this->assertStringContainsString('final words', $after[1]);
    }

    // ── acting on a review over HTTP ──────────────────────────────────────

    public function test_the_whole_review_can_be_done_over_http(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);

        $this->actAs(10);
        $res = $this->postJson(route('ajax.performance.reviews.self', $review->id), ['ratings' => $this->ratings($review, [8, 6], 'mine'), 'summary' => 'my summary', 'submit' => true])->assertOk();
        $this->assertSame('awaiting_meeting', $res->json('review.status'), 'the joint review meeting comes next');
        $this->assertSame([], $res->json('review.actions'));

        $this->postJson(route('ajax.performance.reviews.supervisor', $review->id), ['ratings' => $this->ratings($review, [7, 5]), 'submit' => true])->assertForbidden();
        $this->holdMeeting($review);

        $this->actAs(2);
        $res = $this->postJson(route('ajax.performance.reviews.supervisor', $review->id), ['ratings' => $this->ratings($review, [7, 5]), 'summary' => 'sup summary', 'submit' => true])->assertOk();
        $this->assertSame('awaiting_hr', $res->json('review.status'));
        $this->assertSame('supervisor', $res->json('review.role'));

        $this->asHr();
        $res = $this->postJson(route('ajax.performance.reviews.hr', $review->id), ['ratings' => $this->ratings($review, [9, 7]), 'comment' => 'well done', 'finalise' => true])->assertOk();
        $this->assertEquals(['finalised', 8], [$res->json('review.status'), $res->json('review.final.score')]); // JSON turns 8.0 into 8
        $this->assertSame(['correct'], $res->json('review.actions'));
    }

    public function test_someone_with_no_part_in_a_review_cannot_change_it_and_learns_nothing_about_it(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $payload = ['ratings' => $this->ratings($review, [9, 9]), 'submit' => true, 'reason' => 'x', 'comment' => 'x', 'finalise' => true];

        foreach ([3, 11, 21, null] as $staffId) {
            $this->actAs($staffId);
            foreach (['self', 'self_on_behalf', 'skip_self', 'supervisor', 'hr', 'return', 'reassign', 'correct'] as $action) {
                $res = $this->postJson(route('ajax.performance.reviews.'.$action, $review->id), $payload);
                $res->assertForbidden();
                $this->assertSame('You do not have access to this review.', $res->json('error'));
            }
        }
        $this->assertSame([null, null], $review->items()->pluck('self_rating')->all());
        $this->assertSame('awaiting_self', $review->refresh()->status);
    }

    public function test_a_refused_step_says_why_with_the_right_status(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);

        $this->actAs(2);
        $this->postJson(route('ajax.performance.reviews.supervisor', $review->id), ['ratings' => $this->ratings($review, [5, 5]), 'submit' => true])->assertStatus(422)->assertJsonPath('error', 'This review is not waiting for the supervisor.');

        $this->actAs(10);
        $this->postJson(route('ajax.performance.reviews.hr', $review->id), [])->assertForbidden();
        $this->postJson(route('ajax.performance.reviews.self', $review->id), ['ratings' => $this->ratings($review, [11, 5]), 'submit' => false])->assertStatus(422);
        $this->postJson(route('ajax.performance.reviews.self', $review->id), ['ratings' => $this->ratings($review, [5]), 'submit' => true])->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame([null, null], $review->items()->pluck('self_rating')->all());
    }

    public function test_hr_steps_work_over_http_and_need_a_reason(): void
    {
        $this->nameSupervisor(10, 2);
        $this->publishedCycle();
        $review = $this->reviewOf(10);
        $this->asHr();

        $this->postJson(route('ajax.performance.reviews.skip_self', $review->id), ['reason' => ''])->assertStatus(422);
        $this->postJson(route('ajax.performance.reviews.skip_self', $review->id), ['reason' => 'On leave'])->assertOk()->assertJsonPath('review.status', 'awaiting_meeting');
        $this->holdMeeting($review);
        $this->postJson(route('ajax.performance.reviews.reassign', $review->id), ['supervisor_staff_id' => 3, 'reason' => ''])->assertStatus(422);
        $this->postJson(route('ajax.performance.reviews.reassign', $review->id), ['supervisor_staff_id' => 3, 'reason' => 'Away'])->assertOk()->assertJsonPath('review.supervisor', 'Zola Zonemanager');
        $this->postJson(route('ajax.performance.reviews.reassign', $review->id), ['supervisor_staff_id' => '', 'reason' => 'HR takes over'])->assertOk()->assertJsonPath('review.status', 'awaiting_hr');
    }

    public function test_text_that_is_not_text_does_not_break_a_save(): void
    {
        $this->publishedCycle();
        $review = $this->reviewOf(11);
        $this->actAs(11);

        $this->postJson(route('ajax.performance.reviews.self', $review->id), ['ratings' => $this->ratings($review, [5]), 'summary' => ['a' => 'b'], 'submit' => false])->assertOk();
        $this->postJson(route('ajax.performance.reviews.self', $review->id), ['ratings' => 'nonsense', 'summary' => str_repeat('x', 2001), 'submit' => false])->assertStatus(422);
    }
}
