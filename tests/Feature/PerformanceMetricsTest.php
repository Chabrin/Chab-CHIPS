<?php

namespace Tests\Feature;

use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\Staff;
use App\Services\Performance\PerformanceCycleService;
use App\Services\Performance\PerformanceMetrics;
use App\Services\Performance\PerformanceReviewPresenter;
use App\Services\Performance\PerformanceReviewService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsPerformanceOrg;
use Tests\TestCase;

/**
 * The records shown beside the ratings, worked out with the formulas and thresholds in HR's metrics document.
 *
 * The week used throughout is Monday 4 January to Saturday 9 January 2027: five full days (8 to 5, 9 hours) and a half
 * Saturday (9 to 4, 7 hours), which is the 52-hour working week. Felix (staff 10, number S10) is the person measured.
 */
class PerformanceMetricsTest extends TestCase
{
    use BuildsPerformanceOrg;

    private const WEEK_START = '2027-01-04';

    private const WEEK_END = '2027-01-09';

    private const AFTER = '2027-02-01';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2027-02-01 09:00:00')); // after the weeks and months used below
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        $this->buildPerformanceSchema();
        $this->seedPerformanceOrg();
        DB::table('sys_hr_leave_types')->insert([['id' => 5, 'type_name' => 'Annual leave', 'entitled_days' => 21], ['id' => 1, 'type_name' => 'Sick Leave', 'entitled_days' => 7]]);
        DB::table('sys_staff')->where('id', 10)->update(['hire_date' => 'Jan  1 2010 12:00AM']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function metrics(int $staffId = 10, string $from = self::WEEK_START, string $to = self::WEEK_END, string $today = self::AFTER): array
    {
        $result = app(PerformanceMetrics::class)->forStaff(Staff::find($staffId), Carbon::parse($from), Carbon::parse($to), Carbon::parse($today));

        return collect($result['items'])->keyBy('key')->all();
    }

    /** Attendance for a person: [date => hours], one record per day, as the upload makes them. */
    private function attend(int $staffId, array $days): void
    {
        $number = 'S'.$staffId;
        $parent = DB::table('sys_hr_employees_attendance')->where('employee_id', $number)->value('id')
            ?? DB::table('sys_hr_employees_attendance')->insertGetId(['employee_id' => $number, 'month_year' => '2027-01']);
        foreach ($days as $date => $hours) {
            DB::table('sys_hr_employees_attendance_255_repeat')->insert(['parent_id' => $parent, 'date' => $date.' 00:00:00', 'time_in' => $date.' 08:00:00', 'time_out' => $date.' 17:00:00', 'hours' => $hours]);
        }
    }

    /** A normal week: nine hours Monday to Friday and seven on Saturday. */
    private function fullWeek(int $staffId = 10): void
    {
        $this->attend($staffId, ['2027-01-04' => 9, '2027-01-05' => 9, '2027-01-06' => 9, '2027-01-07' => 9, '2027-01-08' => 9, '2027-01-09' => 7]);
    }

    private function leave(int $staffId, string $from, string $to, float $days, int $type = 5, int $status = 5, ?string $recalledFrom = null, float $recalled = 0): void
    {
        DB::table('sys_hr_leave_requests')->insert([
            'staff_id' => $staffId, 'leave_type_id' => $type, 'start_date' => $from.' 08:00:00', 'end_date' => $to.' 17:00:00', 'days' => $days,
            'current_status_id' => $status, 'recall_effective_date' => $recalledFrom ? $recalledFrom.' 00:00:00' : null, 'recalled_days' => $recalled ?: null,
        ]);
    }

    // ── the working calendar ──────────────────────────────────────────────

    public function test_a_normal_week_is_52_hours_over_five_and_a_half_days(): void
    {
        $item = $this->metrics()['normal_hours'];

        $this->assertSame(52.0, $item['value']);
        $this->assertSame('52 h in 5.5 scheduled days', $item['display']);
    }

    public function test_a_four_week_month_has_208_hours_which_the_226_in_the_document_averages_out_to(): void
    {
        $this->assertSame(208.0, $this->metrics(10, '2027-02-01', '2027-02-28', '2027-03-15')['normal_hours']['value']);
        // 52 hours x 52.14 weeks / 12 months = 226
        $this->assertEquals(226, round(52 * 365 / 7 / 12));
    }

    public function test_a_public_holiday_is_not_a_scheduled_day(): void
    {
        DB::table('sys_hr_public_holidays')->insert(['title' => 'Midweek', 'start_date' => '2027-01-06 00:00:00', 'end_date' => '2027-01-06 00:00:00', 'recurrent' => '0', 'current_status_id' => 5]);

        $this->assertSame('43 h in 4.5 scheduled days', $this->metrics()['normal_hours']['display']);
    }

    public function test_hr_settings_can_make_saturday_a_full_day_or_a_day_off(): void
    {
        DB::table('sys_hr_working_days')->insert(['week_day' => 6, 'work_type_id' => 1, 'current_status_id' => 5]);
        $this->assertSame('54 h in 6 scheduled days', $this->metrics()['normal_hours']['display']);

        DB::table('sys_hr_working_days')->where('week_day', 6)->update(['work_type_id' => 3]);
        $this->assertSame('45 h in 5 scheduled days', $this->metrics()['normal_hours']['display']);
    }

    public function test_someone_who_joined_mid_period_is_measured_from_the_day_they_joined(): void
    {
        DB::table('sys_staff')->where('id', 10)->update(['hire_date' => '2027-01-06']);

        $this->assertSame('34 h in 3.5 scheduled days', $this->metrics()['normal_hours']['display']);
    }

    public function test_a_period_that_has_not_ended_is_measured_up_to_today(): void
    {
        $this->assertSame('27 h in 3 scheduled days', $this->metrics(10, '2027-01-04', '2027-01-31', '2027-01-06')['normal_hours']['display']);
    }

    public function test_a_period_that_has_not_started_is_not_measured(): void
    {
        $items = $this->metrics(10, '2027-03-01', '2027-03-31', '2027-02-01');

        foreach (['normal_hours', 'absenteeism', 'overtime', 'capacity'] as $key) {
            $this->assertNull($items[$key]['value'], $key);
            $this->assertSame('Not started', $items[$key]['display'], $key);
        }
    }

    // ── absenteeism ───────────────────────────────────────────────────────

    public function test_a_full_week_of_attendance_is_no_absence(): void
    {
        $this->fullWeek();

        $item = $this->metrics()['absenteeism'];

        $this->assertSame(0.0, $item['value']);
        $this->assertSame('good', $item['flag']);
        $this->assertSame('0% (0 of 5.5 scheduled days absent)', $item['display']);
    }

    public function test_the_absenteeism_rate_is_absent_days_over_scheduled_days(): void
    {
        $this->attend(10, ['2027-01-04' => 9, '2027-01-05' => 9, '2027-01-06' => 9, '2027-01-07' => 9, '2027-01-08' => 9]); // not in on Saturday

        $item = $this->metrics()['absenteeism'];

        $this->assertSame(9.1, $item['value'], '0.5 / 5.5 x 100');
        $this->assertSame('concern', $item['flag']);
        $this->assertStringContainsString('0.5 of 5.5', $item['display']);
    }

    public function test_a_missed_weekday_counts_a_whole_day_and_a_missed_saturday_half(): void
    {
        $this->attend(10, ['2027-01-04' => 9, '2027-01-06' => 9, '2027-01-07' => 9, '2027-01-08' => 9, '2027-01-09' => 7]); // missed Tuesday

        $this->assertSame(18.2, $this->metrics()['absenteeism']['value'], '1 / 5.5 x 100');
    }

    public function test_approved_leave_is_not_absence_but_pending_or_rejected_leave_is(): void
    {
        $this->attend(10, ['2027-01-06' => 9, '2027-01-07' => 9, '2027-01-08' => 9, '2027-01-09' => 7]);
        $this->leave(10, '2027-01-04', '2027-01-05', 2);                      // approved: Monday and Tuesday
        $this->assertSame(0.0, $this->metrics()['absenteeism']['value']);

        DB::table('sys_hr_leave_requests')->update(['current_status_id' => 1]); // still pending
        $this->assertSame(36.4, $this->metrics()['absenteeism']['value'], '2 / 5.5 x 100');
        DB::table('sys_hr_leave_requests')->update(['current_status_id' => 41]); // rejected
        $this->assertSame(36.4, $this->metrics()['absenteeism']['value']);
    }

    public function test_sick_leave_that_was_approved_is_not_absence_either(): void
    {
        $this->attend(10, ['2027-01-04' => 9, '2027-01-05' => 9, '2027-01-06' => 9, '2027-01-08' => 9, '2027-01-09' => 7]);
        $this->leave(10, '2027-01-07', '2027-01-07', 1, 1);

        $this->assertSame(0.0, $this->metrics()['absenteeism']['value']);
    }

    public function test_leave_cut_short_by_a_recall_stops_the_day_before_the_recall(): void
    {
        $this->attend(10, ['2027-01-04' => 9, '2027-01-07' => 9, '2027-01-08' => 9, '2027-01-09' => 7]);
        $this->leave(10, '2027-01-04', '2027-01-06', 3, 5, 5, '2027-01-05', 2); // recalled from Tuesday: only Monday was leave

        $this->assertSame(36.4, $this->metrics()['absenteeism']['value'], 'Tuesday and Wednesday were absent');
    }

    public function test_attendance_on_a_holiday_or_sunday_is_not_absence_and_counts_as_hours_worked(): void
    {
        $this->fullWeek();
        $this->attend(10, ['2027-01-10' => 5]); // a Sunday, outside the week
        $items = $this->metrics(10, '2027-01-04', '2027-01-10');

        $this->assertSame(0.0, $items['absenteeism']['value']);
        $this->assertSame(9.6, $items['overtime']['value'], '5 hours over 52 normal hours');
        $this->assertSame('watch', $items['overtime']['flag']);
        $this->assertSame('5 h, 9.6% of normal hours (57 h worked)', $items['overtime']['display']);
    }

    public function test_the_absenteeism_thresholds_are_under_3_good_3_to_5_watch_over_5_concern(): void
    {
        $this->assertSame('good', PerformanceMetrics::absenteeismFlag(0));
        $this->assertSame('good', PerformanceMetrics::absenteeismFlag(2.9));
        $this->assertSame('watch', PerformanceMetrics::absenteeismFlag(3));
        $this->assertSame('watch', PerformanceMetrics::absenteeismFlag(5));
        $this->assertSame('concern', PerformanceMetrics::absenteeismFlag(5.1));
    }

    // ── hours: overtime and capacity ──────────────────────────────────────

    public function test_working_exactly_the_normal_hours_is_no_overtime_and_full_capacity(): void
    {
        $this->fullWeek();
        $items = $this->metrics();

        $this->assertSame(0.0, $items['overtime']['value']);
        $this->assertSame('good', $items['overtime']['flag']);
        $this->assertSame(100.0, $items['capacity']['value']);
        $this->assertSame('100% (52 h of 52 h)', $items['capacity']['display']);
        $this->assertSame('concern', $items['capacity']['flag'], 'over 95% of the maximum is the document\'s over-use range');
    }

    public function test_overtime_is_hours_worked_beyond_normal_hours_as_a_share_of_normal_hours(): void
    {
        $this->attend(10, ['2027-01-04' => 12, '2027-01-05' => 12, '2027-01-06' => 12, '2027-01-07' => 12, '2027-01-08' => 9, '2027-01-09' => 7]);

        $item = $this->metrics()['overtime'];

        $this->assertSame(23.1, $item['value'], '64 worked - 52 normal = 12 over, 12 / 52 x 100');
        $this->assertSame('12 h, 23.1% of normal hours (64 h worked)', $item['display']);
        $this->assertSame('concern', $item['flag']);
    }

    public function test_working_fewer_hours_than_normal_is_never_negative_overtime(): void
    {
        $this->attend(10, ['2027-01-04' => 9, '2027-01-05' => 9, '2027-01-06' => 9, '2027-01-07' => 9, '2027-01-08' => 9, '2027-01-09' => 7]);
        DB::table('sys_hr_employees_attendance_255_repeat')->where('date', '2027-01-09 00:00:00')->update(['hours' => 0, 'time_in' => null, 'time_out' => null]);

        $items = $this->metrics();

        $this->assertSame(0.0, $items['overtime']['value']);
        $this->assertSame(86.5, $items['capacity']['value'], '45 / 52');
        $this->assertSame('good', $items['capacity']['flag']);
    }

    public function test_the_overtime_and_capacity_thresholds(): void
    {
        $this->assertSame('good', PerformanceMetrics::overtimeFlag(4.9));
        $this->assertSame('watch', PerformanceMetrics::overtimeFlag(5));
        $this->assertSame('watch', PerformanceMetrics::overtimeFlag(10));
        $this->assertSame('concern', PerformanceMetrics::overtimeFlag(10.1));

        $this->assertSame('watch', PerformanceMetrics::capacityFlag(79.9), 'under 80% is under-used');
        $this->assertSame('good', PerformanceMetrics::capacityFlag(80));
        $this->assertSame('good', PerformanceMetrics::capacityFlag(95));
        $this->assertSame('concern', PerformanceMetrics::capacityFlag(95.1));
    }

    public function test_hours_come_from_the_times_when_the_hours_were_not_stored_and_a_day_uploaded_twice_counts_once(): void
    {
        DB::table('sys_hr_employees_attendance')->insert(['id' => 50, 'employee_id' => 'S10']);
        DB::table('sys_hr_employees_attendance_255_repeat')->insert([
            ['parent_id' => 50, 'date' => '2027-01-04 00:00:00', 'time_in' => '2027-01-04 08:30:00', 'time_out' => '2027-01-04 17:30:00', 'hours' => null],
            ['parent_id' => 50, 'date' => '2027-01-04 00:00:00', 'time_in' => '2027-01-04 08:30:00', 'time_out' => '2027-01-04 17:30:00', 'hours' => 9],
            ['parent_id' => 50, 'date' => '2027-01-05 00:00:00', 'time_in' => '2027-01-05 17:00:00', 'time_out' => '2027-01-05 08:00:00', 'hours' => null],
        ]);

        $display = $this->metrics()['capacity']['display'];

        $this->assertStringContainsString('9 h of 52 h', $display, 'Monday once, 9 hours; Tuesday with the times the wrong way round counts nothing');
    }

    // ── no data ───────────────────────────────────────────────────────────

    public function test_no_attendance_records_means_no_data_never_100_percent_absent(): void
    {
        $items = $this->metrics();

        foreach (['absenteeism', 'overtime', 'capacity'] as $key) {
            $this->assertNull($items[$key]['value'], $key);
            $this->assertSame('No attendance data', $items[$key]['display'], $key);
            $this->assertSame('none', $items[$key]['flag'], $key);
        }
        $this->assertSame(52.0, $items['normal_hours']['value'], 'the baseline needs no attendance');
    }

    public function test_someone_with_no_staff_number_or_only_records_outside_the_period_has_no_data(): void
    {
        DB::table('sys_staff')->where('id', 11)->update(['staff_number' => null]);
        $this->attend(11, ['2027-01-04' => 9]);
        $this->attend(10, ['2026-12-01' => 9]);

        $this->assertSame('No attendance data', $this->metrics(11)['absenteeism']['display']);
        $this->assertSame('No attendance data', $this->metrics(10)['absenteeism']['display']);
    }

    public function test_one_persons_attendance_is_never_counted_for_another(): void
    {
        $this->fullWeek(11);

        $this->assertSame('No attendance data', $this->metrics(10)['absenteeism']['display']);
        $this->assertSame(0.0, $this->metrics(11)['absenteeism']['value']);
    }

    // ── length of service ─────────────────────────────────────────────────

    public function test_length_of_service_is_the_days_since_hire_over_365_to_the_end_of_the_period(): void
    {
        DB::table('sys_staff')->where('id', 10)->update(['hire_date' => '2020-01-09']);

        $item = $this->metrics()['tenure'];

        $this->assertSame(7.0, $item['value']);
        $this->assertSame('7 years (since 09 Jan 2020)', $item['display']);
        $this->assertSame('good', $item['flag']);
    }

    public function test_the_odd_hire_date_formats_in_the_staff_table_are_read(): void
    {
        $this->assertSame(17.0, $this->metrics()['tenure']['value'], 'Jan  1 2010 12:00AM');

        DB::table('sys_staff')->where('id', 10)->update(['hire_date' => '09/01/2020']);
        $this->assertSame('7 years (since 09 Jan 2020)', $this->metrics()['tenure']['display'], 'day first');
    }

    public function test_under_three_years_is_shown_without_a_flag_and_a_missing_hire_date_says_so(): void
    {
        DB::table('sys_staff')->where('id', 10)->update(['hire_date' => '2026-01-09']);
        $item = $this->metrics()['tenure'];
        $this->assertSame([1.0, 'none'], [$item['value'], $item['flag']]);

        DB::table('sys_staff')->where('id', 10)->update(['hire_date' => null]);
        $this->assertSame('No hire date on record', $this->metrics()['tenure']['display']);
        DB::table('sys_staff')->where('id', 10)->update(['hire_date' => '2028-01-01']);
        $this->assertSame('Joined after the period', $this->metrics()['tenure']['display']);
    }

    // ── annual leave ──────────────────────────────────────────────────────

    public function test_annual_leave_used_is_days_taken_over_days_allocated(): void
    {
        $this->leave(10, '2027-01-04', '2027-01-08', 5);
        $this->leave(10, '2027-01-25', '2027-01-28', 4);

        $item = $this->metrics(10, '2027-01-01', '2027-03-31', '2027-04-15')['annual_leave'];

        $this->assertSame(42.9, $item['value']);
        $this->assertSame('42.9% (9 of 21 days in 2027)', $item['display']);
        $this->assertSame('none', $item['flag'], 'too early in the year to judge');
    }

    public function test_only_approved_annual_leave_in_the_year_up_to_the_end_of_the_period_counts(): void
    {
        $this->leave(10, '2027-01-04', '2027-01-08', 5);                    // counts
        $this->leave(10, '2027-02-01', '2027-02-02', 2, 5, 1);              // pending
        $this->leave(10, '2027-02-03', '2027-02-04', 2, 5, 41);             // rejected
        $this->leave(10, '2027-02-08', '2027-02-09', 2, 1);                 // sick leave
        $this->leave(10, '2026-12-14', '2026-12-18', 5);                    // last year
        $this->leave(10, '2027-06-01', '2027-06-05', 5);                    // after the period
        $this->leave(11, '2027-01-11', '2027-01-15', 5);                    // someone else

        $this->assertSame(23.8, $this->metrics(10, '2027-01-01', '2027-03-31', '2027-04-15')['annual_leave']['value'], '5 / 21');
    }

    public function test_leave_that_was_recalled_only_counts_for_the_days_actually_taken(): void
    {
        $this->leave(10, '2027-01-04', '2027-01-08', 5, 5, 5, '2027-01-06', 3);

        $this->assertSame(9.5, $this->metrics(10, '2027-01-01', '2027-03-31', '2027-04-15')['annual_leave']['value'], '2 / 21');
    }

    public function test_someone_hired_this_year_has_a_part_year_allocation(): void
    {
        DB::table('sys_staff')->where('id', 10)->update(['hire_date' => '2027-10-10']);
        $this->leave(10, '2027-11-01', '2027-11-03', 3);

        $item = $this->metrics(10, '2027-10-01', '2027-12-31', '2028-01-15')['annual_leave'];

        $this->assertSame(57.1, $item['value']);
        $this->assertStringContainsString('3 of 5.25 days in 2027', $item['display']);
    }

    public function test_the_annual_leave_thresholds_depend_on_the_time_of_year(): void
    {
        $date = fn (string $day) => Carbon::parse($day);

        $this->assertSame('concern', PerformanceMetrics::leaveFlag(100.1, $date('2027-03-01')), 'over the allocation at any time');
        $this->assertSame('none', PerformanceMetrics::leaveFlag(10, $date('2027-06-30')));
        $this->assertSame('concern', PerformanceMetrics::leaveFlag(49.9, $date('2027-09-30')), 'under 50% by the end of the third quarter');
        $this->assertSame('good', PerformanceMetrics::leaveFlag(50, $date('2027-09-30')));
        $this->assertSame('concern', PerformanceMetrics::leaveFlag(30, $date('2027-10-15')));
        $this->assertSame('watch', PerformanceMetrics::leaveFlag(94.9, $date('2027-11-30')), 'the target is 95% by the end of November');
        $this->assertSame('good', PerformanceMetrics::leaveFlag(95, $date('2027-11-30')));
        $this->assertSame('good', PerformanceMetrics::leaveFlag(100, $date('2027-12-31')));
    }

    public function test_a_missing_annual_leave_type_is_said_not_guessed(): void
    {
        DB::table('sys_hr_leave_types')->where('id', 5)->delete();

        $this->assertSame('No annual leave type set up', $this->metrics()['annual_leave']['display']);
    }

    // ── on a review ───────────────────────────────────────────────────────

    private function reviewReadyForSupervisor(): PerformanceReview
    {
        $this->nameSupervisor(10, 2);
        $this->criteria(self::POS_FIELD_OFFICER);
        $cycle = PerformanceCycle::create(['name' => 'Week 1', 'period_start' => self::WEEK_START, 'period_end' => self::WEEK_END, 'status' => 'draft']);
        app(PerformanceCycleService::class)->publish($cycle, $this->hrUser());
        $review = PerformanceReview::where('staff_id', 10)->first();
        $ratings = fn ($r, $v) => $r->items()->get()->mapWithKeys(fn ($item, $i) => [$item->id => ['rating' => $v, 'comment' => null]])->all();

        $review = app(PerformanceReviewService::class)->saveSelf($review, $this->userFor(10), $ratings($review, 7), null, true);

        return $this->holdMeeting($review);
    }

    public function test_the_supervisor_and_hr_see_the_records_but_the_staff_member_does_not(): void
    {
        $this->fullWeek();
        $review = $this->reviewReadyForSupervisor();
        $present = fn ($viewer) => app(PerformanceReviewPresenter::class)->show($viewer, $review->refresh());

        $this->assertSame(['normal_hours', 'absenteeism', 'overtime', 'capacity', 'tenure', 'annual_leave'], array_column($present($this->userFor(2))['metrics']['items'], 'key'));
        $this->assertSame(0.0, collect($present($this->hrUser())['metrics']['items'])->firstWhere('key', 'absenteeism')['value']);
        $this->assertArrayNotHasKey('metrics', $present($this->userFor(10)));
    }

    public function test_the_records_are_kept_as_they_were_when_the_supervisor_rated_and_when_hr_finalised(): void
    {
        $this->fullWeek();
        $review = $this->reviewReadyForSupervisor();
        $ratings = fn ($v) => $review->items()->get()->mapWithKeys(fn ($item) => [$item->id => ['rating' => $v, 'comment' => null]])->all();
        $service = app(PerformanceReviewService::class);

        $review = $service->saveSupervisor($review, $this->userFor(2), $ratings(7), null, true);
        $this->assertSame('absenteeism', $review->metrics['items'][1]['key']);
        $this->assertEquals(0, $review->metrics['items'][1]['value'], 'a full week: nobody was absent');
        $this->assertNotNull($review->metrics_taken_at);

        // Attendance is corrected afterwards: the supervisor's view of the records was the earlier one, HR sees the latest
        DB::table('sys_hr_employees_attendance_255_repeat')->where('date', '2027-01-09 00:00:00')->delete();
        $live = app(PerformanceReviewPresenter::class)->show($this->hrUser(), $review->refresh());
        $this->assertSame(9.1, collect($live['metrics']['items'])->firstWhere('key', 'absenteeism')['value']);

        $review = $service->saveHr($review, $this->hrUser(), $ratings(7), null, true);
        $this->assertSame(9.1, collect($review->metrics['items'])->firstWhere('key', 'absenteeism')['value'], 'kept when HR finalised');

        // Once finalised the records stay as they were then, whatever happens to the attendance
        DB::table('sys_hr_employees_attendance_255_repeat')->delete();
        $after = app(PerformanceReviewPresenter::class)->show($this->hrUser(), $review->refresh());
        $this->assertSame(9.1, collect($after['metrics']['items'])->firstWhere('key', 'absenteeism')['value']);
    }

    public function test_a_fault_in_working_out_the_records_never_stops_a_review(): void
    {
        $review = $this->reviewReadyForSupervisor();
        Schema::drop('sys_hr_employees_attendance_255_repeat'); // the records can no longer be worked out
        $ratings = $review->items()->get()->mapWithKeys(fn ($item) => [$item->id => ['rating' => 6, 'comment' => null]])->all();

        $payload = app(PerformanceReviewPresenter::class)->show($this->userFor(2), $review->refresh());
        $this->assertNull($payload['metrics']);
        $this->assertSame(['supervisor'], $payload['actions']);

        $review = app(PerformanceReviewService::class)->saveSupervisor($review, $this->userFor(2), $ratings, null, true);
        $this->assertSame('awaiting_hr', $review->status);
        $this->assertNull($review->metrics);
    }
}
