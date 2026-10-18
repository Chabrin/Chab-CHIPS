<?php

namespace App\Services\Leave;

use App\Models\PublicHoliday;
use App\Models\WorkDay;
use App\Support\HireDate;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaveCalendarService
{
    /** How much of a day each ISO weekday is worth when HR settings don't say otherwise: Saturday is a half day, Sunday is off. */
    private const DEFAULT_DAY_WEIGHTS = [1 => 1.0, 2 => 1.0, 3 => 1.0, 4 => 1.0, 5 => 1.0, 6 => 0.5, 7 => 0.0];

    /** Work type ids in sys_hr_working_days mapped to the share of a day they are worth. */
    private const WORK_TYPE_WEIGHTS = [1 => 1.0, 2 => 0.5, 3 => 0.0];

    private ?Collection $holidays = null;
    private ?array $dayWeights = null;

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
     * The share of a day each ISO weekday (1=Mon..7=Sun) is worth for leave: 1 full day, 0.5 half day, 0 non-working.
     * Starts from the defaults (Saturday half day, Sunday off) and is overridden by the working days in HR settings.
     *
     * @return array
     */
    public function dayWeights(): array
    {
        if ($this->dayWeights !== null) {
            return $this->dayWeights;
        }

        $weights = self::DEFAULT_DAY_WEIGHTS;

        foreach (WorkDay::where('current_status_id', 5)->get() as $workDay) {
            $weekday = (int) $workDay->week_day;
            $type = (int) $workDay->work_type_id;

            if (isset($weights[$weekday]) && isset(self::WORK_TYPE_WEIGHTS[$type])) {
                $weights[$weekday] = self::WORK_TYPE_WEIGHTS[$type];
            }
        }

        return $this->dayWeights = $weights;
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
     * How much of a day is charged against the leave balance for a date: 1 for a full working day, 0.5 for a half day
     * (Saturday), 0 for a day that is not charged. Leave types that count weekends or public holidays charge them in full.
     *
     * @param Carbon $date
     * @param array $rules
     * @return float
     */
    public function dayWeight(Carbon $date, array $rules): float
    {
        if (!$rules['holidays'] && $this->holidayOn($date)) {
            return 0.0;
        }

        if ($rules['weekends']) {
            return 1.0;
        }

        return $this->dayWeights()[$date->dayOfWeekIso] ?? 1.0;
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
        return $this->dayWeight($date, $rules) > 0;
    }

    /**
     * What to charge for a day someone chose on purpose: its normal weight, or a full day when it is
     * normally not charged (an approver explicitly adding a Sunday or holiday).
     *
     * @param Carbon $date
     * @param int $leaveTypeId
     * @return float
     */
    public function chargeFor(Carbon $date, int $leaveTypeId): float
    {
        $weight = $this->dayWeight($date, $this->rulesFor($leaveTypeId));
        if ($weight > 0) {
            return $weight;
        }

        // Normally-charged day that falls on a holiday keeps its usual size (a holiday Saturday is still half a day)
        $usual = $this->dayWeights()[$date->dayOfWeekIso] ?? 1.0;

        return $usual > 0 ? $usual : 1.0;
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

        // No leave runs longer than a year; this keeps a bad range from looping for ages
        if ($end->gt($start->copy()->addDays(366))) {
            $end = $start->copy()->addDays(366);
        }

        $rules = $this->rulesFor($leaveTypeId);

        return collect(CarbonPeriod::create($start, $end))
            ->filter(fn($date) => $this->isCountableDay($date, $rules))
            ->values();
    }

    /**
     * The size of every chargeable day between two dates (1 for a full day, 0.5 for a Saturday), in date order.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $leaveTypeId
     * @return array
     */
    public function dayWeightsBetween(string $startDate, string $endDate, int $leaveTypeId = 0): array
    {
        $rules = $this->rulesFor($leaveTypeId);

        return $this->countableDates($startDate, $endDate, $leaveTypeId)
            ->map(fn($date) => $this->dayWeight($date, $rules))
            ->all();
    }

    /**
     * Given a Start Date and End Date, calculate the number of billable/chargeable leave days.
     * Use this when users pick date ranges (e.g., Annual Leave blocks or Maternity Leave). A Saturday is worth half a day.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $leaveTypeId
     * @return float
     */
    public function countDeductibleDays(string $startDate, string $endDate, int $leaveTypeId = 0): float
    {
        return (float) array_sum($this->dayWeightsBetween($startDate, $endDate, $leaveTypeId));
    }

    /**
     * Given a Start Date and a number of days to charge, find the date the leave ends on: the first date by which
     * that many days (Saturday counting as half) have been charged.
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
        $target = max(0.5, (float) $days);
        $charged = 0.0;

        // Failsafe counter to prevent execution timeouts
        $safetyLimit = 365 * 2;

        for ($i = 0; $i < $safetyLimit; $i++) {
            $charged += $this->dayWeight($cursor, $rules);

            if ($charged + 0.0001 >= $target) {
                break;
            }

            $cursor->addDay();
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
     * Read a staff hire date, which is stored in mixed formats (see HireDate::parse).
     * Returns null when the value is empty or unreadable.
     *
     * @param Carbon|string|null $hireDate
     * @return Carbon|null
     */
    public function parseHireDate($hireDate): ?Carbon {
        return HireDate::parse($hireDate);
    }
}
