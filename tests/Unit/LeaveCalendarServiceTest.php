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

            public function nonWorkingWeekdays(): array
            {
                return [6, 7];
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

        // Mon 19 Oct - Sun 25 Oct 2026: 7 days, minus Sat/Sun and the Wednesday holiday
        $this->assertSame(4, $service->countDeductibleDays('2026-10-19', '2026-10-25', 1));
    }

    public function test_leave_types_that_count_weekends_and_holidays_charge_every_day(): void
    {
        $service = $this->service([['title' => 'Mid-week', 'start_date' => '2026-10-21 00:00:00', 'end_date' => '2026-10-21 00:00:00', 'recurrent' => '2']], true, true);

        $this->assertSame(7, $service->countDeductibleDays('2026-10-19', '2026-10-25', 1));
    }

    public function test_end_date_skips_non_chargeable_days(): void
    {
        $service = $this->service();

        // Friday + 3 chargeable days = Fri, Mon, Tue
        $this->assertSame('2026-10-27', $service->calculateEndDate('2026-10-23', 3, 1));
    }

    public function test_end_date_before_start_date_counts_zero_days(): void
    {
        $this->assertSame(0, $this->service()->countDeductibleDays('2026-10-25', '2026-10-19', 1));
    }

    public function test_pro_rata_entitlement_for_mid_year_hires(): void
    {
        $service = $this->service();

        $this->assertSame(21, $service->calculateAnnualEntitlement('2024-03-01', 2026));
        $this->assertSame(0, $service->calculateAnnualEntitlement('2027-01-01', 2026));
        $this->assertSame(11, $service->calculateAnnualEntitlement('2026-07-01', 2026));
        $this->assertSame(21, $service->calculateAnnualEntitlement('01/01/2026', 2026));
    }

    public function test_annual_leave_type_names_are_recognised(): void
    {
        $service = $this->service();

        $this->assertTrue($service->isAnnualLeaveType('Normal'));
        $this->assertTrue($service->isAnnualLeaveType('Annual Leave'));
        $this->assertFalse($service->isAnnualLeaveType('Sick Leave'));
    }
}
