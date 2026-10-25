<?php

namespace Tests\Unit;

use App\Models\PublicHoliday;
use App\Services\Leave\LeaveCalendarService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LeaveCalendarServiceTest extends TestCase
{
    private function service(array $holidays = [], bool $countsWeekends = false, bool $countsHolidays = false): LeaveCalendarService
    {
        return new class($holidays, $countsWeekends, $countsHolidays) extends LeaveCalendarService {
            public function __construct(private array $rows, private bool $weekends, private bool $holidaysCount)
            {
            }

            public function holidays(): Collection
            {
                return collect($this->rows)->map(function ($row) {
                    $holiday = new PublicHoliday();
                    $holiday->setRawAttributes($row);

                    return $holiday;
                });
            }

            public function dayWeights(): array
            {
                return [1 => 1.0, 2 => 1.0, 3 => 1.0, 4 => 1.0, 5 => 1.0, 6 => 0.5, 7 => 0.0];
            }

            public function rulesFor(int $leaveTypeId): array
            {
                return ['weekends' => $this->weekends, 'holidays' => $this->holidaysCount];
            }
        };
    }

    public function test_recurrent_holiday_matches_in_any_year(): void
    {
        $service = $this->service([['title' => 'Christmas', 'start_date' => '2020-12-25 00:00:00', 'end_date' => '2020-12-25 00:00:00', 'recurrent' => '1']]);

        $this->assertSame('Christmas', $service->holidayOn(Carbon::parse('2026-12-25'))->title);
        $this->assertNull($service->holidayOn(Carbon::parse('2026-12-26')));
    }

    public function test_one_off_holiday_only_matches_its_own_year(): void
    {
        $service = $this->service([['title' => 'Gazetted day', 'start_date' => '2026-05-01 00:00:00', 'end_date' => '2026-05-01 00:00:00', 'recurrent' => '2']]);

        $this->assertNotNull($service->holidayOn(Carbon::parse('2026-05-01')));
        $this->assertNull($service->holidayOn(Carbon::parse('2027-05-01')));
    }

    public function test_year_spanning_recurrent_holiday_matches_both_sides_of_new_year(): void
    {
        $service = $this->service([['title' => 'Shutdown', 'start_date' => '2025-12-31 00:00:00', 'end_date' => '2026-01-02 00:00:00', 'recurrent' => '1']]);

        $this->assertNotNull($service->holidayOn(Carbon::parse('2027-12-31')));
        $this->assertNotNull($service->holidayOn(Carbon::parse('2027-01-01')));
        $this->assertNull($service->holidayOn(Carbon::parse('2027-06-01')));
    }

    public function test_weekends_and_holidays_are_not_charged_by_default(): void
    {
        $service = $this->service([['title' => 'Mid-week', 'start_date' => '2026-10-21 00:00:00', 'end_date' => '2026-10-21 00:00:00', 'recurrent' => '2']]);

        // Mon 19 Oct - Sun 25 Oct 2026: Mon, Tue, Thu, Fri = 4, the Wednesday holiday and Sunday are free, Saturday is half
        $this->assertSame(4.5, $service->countDeductibleDays('2026-10-19', '2026-10-25', 1));
    }

    public function test_leave_types_that_count_weekends_and_holidays_charge_every_day(): void
    {
        $service = $this->service([['title' => 'Mid-week', 'start_date' => '2026-10-21 00:00:00', 'end_date' => '2026-10-21 00:00:00', 'recurrent' => '2']], true, true);

        $this->assertSame(7.0, $service->countDeductibleDays('2026-10-19', '2026-10-25', 1));
    }

    public function test_end_date_skips_non_chargeable_days(): void
    {
        $service = $this->service();

        // Friday 1 + Saturday 0.5 + Monday 1 = 2.5, Tuesday reaches 3.5, so 3 days ends on Tuesday
        $this->assertSame('2026-10-27', $service->calculateEndDate('2026-10-23', 3, 1));
        // Friday + Saturday + Monday is exactly 2.5 days
        $this->assertSame('2026-10-26', $service->calculateEndDate('2026-10-23', 2.5, 1));
    }

    public function test_saturday_is_half_a_day_and_sunday_is_free(): void
    {
        $service = $this->service();

        $this->assertSame(0.5, $service->countDeductibleDays('2026-10-24', '2026-10-24', 1));
        $this->assertSame(0.0, $service->countDeductibleDays('2026-10-25', '2026-10-25', 1));
        $this->assertSame(1.5, $service->countDeductibleDays('2026-10-23', '2026-10-25', 1));
    }

    public function test_a_saturday_request_ends_on_that_saturday(): void
    {
        // Half a day requested starting on a Saturday is already covered by the Saturday itself
        $this->assertSame('2026-10-24', $this->service()->calculateEndDate('2026-10-24', 0.5, 1));
    }

    public function test_a_holiday_saturday_chosen_by_an_approver_is_still_half_a_day(): void
    {
        $service = $this->service([['title' => 'Holiday Saturday', 'start_date' => '2026-10-24 00:00:00', 'end_date' => '2026-10-24 00:00:00', 'recurrent' => '2']]);

        $this->assertSame(0.5, $service->chargeFor(Carbon::parse('2026-10-24'), 1));
    }

    public function test_day_sizes_between_two_dates_are_listed_in_order(): void
    {
        $this->assertSame([1.0, 0.5, 1.0], $this->service()->dayWeightsBetween('2026-10-23', '2026-10-26', 1));
    }

    public function test_an_absurdly_long_range_is_capped_to_a_year(): void
    {
        $this->assertLessThanOrEqual(366.0, $this->service()->countDeductibleDays('2026-01-01', '2999-12-31', 1));
    }

    public function test_an_approver_choosing_a_sunday_charges_a_full_day_and_a_saturday_half(): void
    {
        $service = $this->service();

        $this->assertSame(0.5, $service->chargeFor(Carbon::parse('2026-10-24'), 1));
        $this->assertSame(1.0, $service->chargeFor(Carbon::parse('2026-10-25'), 1));
        $this->assertSame(1.0, $service->chargeFor(Carbon::parse('2026-10-26'), 1));
    }

    public function test_end_date_before_start_date_counts_zero_days(): void
    {
        $this->assertSame(0.0, $this->service()->countDeductibleDays('2026-10-25', '2026-10-19', 1));
    }

    public function test_pro_rata_entitlement_for_mid_year_hires(): void
    {
        $service = $this->service();

        $this->assertSame(21.0, $service->calculateAnnualEntitlement('2024-03-01', 2026));
        $this->assertSame(0.0, $service->calculateAnnualEntitlement('2027-01-01', 2026));
        $this->assertSame(10.5, $service->calculateAnnualEntitlement('2026-07-01', 2026));
        $this->assertSame(21.0, $service->calculateAnnualEntitlement('01/01/2026', 2026));
    }

    public function test_october_hire_gets_5_25_days_not_rounded_up(): void
    {
        $service = $this->service();

        $this->assertSame(5.25, $service->calculateAnnualEntitlement('2026-10-04', 2026));
        $this->assertSame(1.75, $service->calculateAnnualEntitlement('2026-12-15', 2026));
    }

    public function test_hire_dates_in_every_stored_format_are_read_correctly(): void
    {
        $service = $this->service();

        // ISO, legacy "Mon d Y", day-first slash, month-first slash (second part above 12)
        $this->assertSame('2026-10-04', $service->parseHireDate('2026-10-04 00:00:00')->toDateString());
        $this->assertSame('2010-01-01', $service->parseHireDate('Jan  1 2010 12:00AM')->toDateString());
        $this->assertSame('2026-10-04', $service->parseHireDate('04/10/2026')->toDateString());
        $this->assertSame('2026-10-04', $service->parseHireDate('4/10/2026')->toDateString());
        $this->assertSame('2025-02-25', $service->parseHireDate('25/02/2025')->toDateString());
        $this->assertSame('2026-03-25', $service->parseHireDate('03/25/2026')->toDateString());
        $this->assertNull($service->parseHireDate(null));
        $this->assertNull($service->parseHireDate('not a date'));

        // A month-first date used to roll over into 2028 and give 0 days; March to December is 10 months
        $this->assertSame(17.5, $service->calculateAnnualEntitlement('03/25/2026', 2026));
    }

    public function test_annual_leave_type_names_are_recognised(): void
    {
        $service = $this->service();

        $this->assertTrue($service->isAnnualLeaveType('Normal'));
        $this->assertTrue($service->isAnnualLeaveType('Annual Leave'));
        $this->assertFalse($service->isAnnualLeaveType('Sick Leave'));
    }
}
