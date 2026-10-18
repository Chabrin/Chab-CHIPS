<?php

namespace App\Services\Leave;

use App\Models\PublicHoliday;
use App\Models\WorkDay;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaveCalendarService
{
    /** Weekdays treated as non-working when none are configured in HR settings (ISO: 6=Sat, 7=Sun). */
    private const DEFAULT_NON_WORKING_WEEKDAYS = [6, 7];

    private ?Collection $holidays = null;
    private ?array $nonWorkingWeekdays = null;

    /**
     * Active public holidays.
     *
     * @return Collection
     */
    public function holidays(): Collection
    {
        return $this->holidays ??= PublicHoliday::where('current_status_id', 5)->get();
    }

    /**
     * ISO weekday numbers (1=Mon..7=Sun) configured as non-working (work_type_id 3).
     * Falls back to Saturday and Sunday when HR settings define no non-working days.
     *
     * @return array
     */
    public function nonWorkingWeekdays(): array
    {
        if ($this->nonWorkingWeekdays !== null) {
            return $this->nonWorkingWeekdays;
        }

        $configured = WorkDay::where('work_type_id', 3)
            ->pluck('week_day')
            ->map(fn($d) => (int) $d)
            ->all();

        return $this->nonWorkingWeekdays = $configured ?: self::DEFAULT_NON_WORKING_WEEKDAYS;
    }

    /**
     * Find the public holiday falling on a date. Holidays marked recurrent (1) match on month and day
     * in any year; one-off holidays (2) only match inside their own start/end dates.
     *
     * @param Carbon $date
     * @return PublicHoliday|null
     */
    public function holidayOn(Carbon $date): ?PublicHoliday
    {
        $targetMonthDay = $date->month * 100 + $date->day;

        return $this->holidays()->first(function ($h) use ($date, $targetMonthDay) {
            $startDate = Carbon::parse($h->start_date)->startOfDay();
            $endDate = Carbon::parse($h->end_date ?? $h->start_date)->endOfDay();

            if (!in_array(strtolower((string) $h->recurrent), ['1', 'yes'], true)) {
                return $date->betweenIncluded($startDate, $endDate);
            }

            $sMonthDay = $startDate->month * 100 + $startDate->day;
            $eMonthDay = $endDate->month * 100 + $endDate->day;

            return $sMonthDay <= $eMonthDay
                ? ($targetMonthDay >= $sMonthDay && $targetMonthDay <= $eMonthDay)
                : ($targetMonthDay >= $sMonthDay || $targetMonthDay <= $eMonthDay);
        });
    }

    /**
     * Day-counting rules for a leave type: whether weekends and public holidays are charged.
     *
     * @param int $leaveTypeId
     * @return array{weekends: bool, holidays: bool}
     */
    public function rulesFor(int $leaveTypeId): array
    {
        $t = DB::table('sys_hr_leave_types')->where('id', $leaveTypeId)->first();

        return [
            'weekends' => (bool) ($t->counts_weekends ?? false),
            'holidays' => (bool) ($t->counts_public_holidays ?? false),
        ];
    }

    /**
     * Determines whether a given day is chargeable against the leave balance.
     *
     * @param Carbon $date
     * @param array $rules
     * @return bool
     */
    public function isCountableDay(Carbon $date, array $rules): bool
    {
        if (!$rules['weekends'] && in_array($date->dayOfWeekIso, $this->nonWorkingWeekdays(), true)) {
            return false;
        }

        if (!$rules['holidays'] && $this->holidayOn($date)) {
            return false;
        }

        return true;
    }

    /**
     * Every chargeable date between two dates (inclusive) for a leave type.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $leaveTypeId
     * @return Collection
     */
    public function countableDates(string $startDate, string $endDate, int $leaveTypeId = 0): Collection
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            return collect();
        }

        $rules = $this->rulesFor($leaveTypeId);

        return collect(CarbonPeriod::create($start, $end))
            ->filter(fn($date) => $this->isCountableDay($date, $rules))
            ->values();
    }

    /**
     * Given a Start Date and End Date, calculate the number of billable/chargeable leave days.
     * Use this when users pick date ranges (e.g., Annual Leave blocks or Maternity Leave).
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $leaveTypeId
     * @return int
     */
    public function countDeductibleDays(string $startDate, string $endDate, int $leaveTypeId = 0): int
    {
        return $this->countableDates($startDate, $endDate, $leaveTypeId)->count();
    }

    /**
     * Given a Start Date and a number of requested days, determine the exact End Date.
     *
     * @param string $start
     * @param float|int $days
     * @param int $leaveTypeId
     * @return string
     */
    public function calculateEndDate(string $start, float|int $days, int $leaveTypeId): string
    {
        $rules = $this->rulesFor($leaveTypeId);
        $cursor = Carbon::parse($start)->startOfDay();
        $target = max(1, (int) ceil($days));
        $counted = 0;

        // Failsafe counter to prevent execution timeouts
        $safetyLimit = 365 * 2;
        $iterations = 0;

        while ($counted < $target && $iterations < $safetyLimit) {
            $iterations++;

            if ($this->isCountableDay($cursor, $rules)) {
                $counted++;
            }

            // Only advance if we still have days left to count
            if ($counted < $target) {
                $cursor->addDay();
            } else {
                break;
            }
        }

        return $cursor->toDateString();
    }

    /**
     * Whether a leave type name denotes annual leave (Annual / Normal), which is pro-rated for mid-year hires.
     *
     * @param string|null $typeName
     * @return bool
     */
    public function isAnnualLeaveType(?string $typeName): bool {
        $name = strtolower((string) $typeName);

        return str_contains($name, 'annual') || str_contains($name, 'normal');
    }

    /**
     * Calculate entitled annual leave days. Staff hired in an earlier year get the full 21 days; staff hired in the
     * year get 1.75 days per month of service, the hire month counting in full, kept to two decimals
     * (hired in October = 3 months = 5.25 days). No rounding up.
     *
     * @param Carbon|string|null $hireDate
     * @param int $year
     * @return float
     */
    public function calculateAnnualEntitlement($hireDate, int $year): float {
        $hired = $this->parseHireDate($hireDate);

        if (!$hired) {
            return 21.0;
        }

        // Hired in a future year: 0 days for this year
        if ($hired->year > $year) {
            return 0.0;
        }

        // Hired in a prior year: full 21 working days
        if ($hired->year < $year) {
            return 21.0;
        }

        $monthsServed = 12 - $hired->month + 1;

        return round(min(21, $monthsServed * 1.75), 2);
    }

    /**
     * Read a staff hire date, which is stored in mixed formats: ISO, legacy "Jan  1 2010 12:00AM", and slash dates
     * in both orders. Slash dates are day-first unless the second part cannot be a month (05/23/2023).
     * Returns null when the value is empty or unreadable.
     *
     * @param Carbon|string|null $hireDate
     * @return Carbon|null
     */
    public function parseHireDate($hireDate): ?Carbon {
        if (empty($hireDate)) {
            return null;
        }

        if ($hireDate instanceof \DateTimeInterface) {
            return Carbon::instance($hireDate);
        }

        $value = trim((string) $hireDate);

        try {
            if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $value, $m)) {
                $format = ((int) $m[2] > 12) ? 'm/d/Y' : 'd/m/Y';

                return Carbon::createFromFormat($format, $m[1] . '/' . $m[2] . '/' . $m[3])->startOfDay();
            }

            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
