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
    private ?Collection $holidays = null;
    private ?array $nonWorkingWeekdays = null;

    /**
     * Active public holidays.
     */
    public function holidays(): Collection
    {
        return $this->holidays ??= PublicHoliday::where('current_status_id', 5)->get();
    }

    /**
     * ISO weekday numbers (1=Mon..7=Sun) configured as non-working (work_type_id 3).
     */
    public function nonWorkingWeekdays(): array
    {
        return $this->nonWorkingWeekdays ??= WorkDay::where('work_type_id', 3)
            ->pluck('week_day')
            ->map(fn($d) => (int) $d)
            ->all();
    }

    /**
     * Check if a specific date falls on a public holiday.
     * Handles both recurring (annual) holidays and one-off/impromptu gazetted holidays.
     */
    public function holidayOn(Carbon $date): ?PublicHoliday
    {
        $targetMonthDay = $date->month * 100 + $date->day;
        $targetYmd = $date->toDateString();

        return $this->holidays()->first(function ($h) use ($date, $targetMonthDay, $targetYmd) {
            $startDate = Carbon::parse($h->start_date)->startOfDay();
            $endDate = Carbon::parse($h->end_date)->endOfDay();

            // 1. One-off / impromptu holidays (check exact date match)
            if (empty($h->recurrent) || $h->recurrent == 0) {
                return $date->betweenIncluded($startDate, $endDate);
            }

            // 2. Recurring holidays (check month and day window)
            $sMonthDay = $startDate->month * 100 + $startDate->day;
            $eMonthDay = $endDate->month * 100 + $endDate->day;

            return $sMonthDay <= $eMonthDay
                ? ($targetMonthDay >= $sMonthDay && $targetMonthDay <= $eMonthDay)
                : ($targetMonthDay >= $sMonthDay || $targetMonthDay <= $eMonthDay);
        });
    }

    /**
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
     */
    public function isCountableDay(Carbon $date, array $rules): bool
    {
        // If the leave type does not count weekends, skip non-working days
        if (!$rules['weekends'] && in_array($date->dayOfWeekIso, $this->nonWorkingWeekdays(), true)) {
            return false;
        }

        // If the leave type does not count holidays, skip public holidays
        if (!$rules['holidays'] && $this->holidayOn($date)) {
            return false;
        }

        return true;
    }

    /**
     * Given a Start Date and End Date, calculate the number of billable/chargeable leave days.
     * Use this when users pick date ranges (e.g., Annual Leave blocks or Maternity Leave).
     */
   public function countDeductibleDays(string $startDate, string $endDate, int $leaveTypeId = 0): int
    {
        $start = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end   = \Carbon\Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        // Fetch all active holiday date strings within this window
        $holidays = \App\Models\PublicHoliday::where('current_status_id', 5)
            ->where(function ($query) use ($start, $end) {
                $query->whereDate('start_date', '<=', $end->format('Y-m-d'))
                      ->whereDate('end_date', '>=', $start->format('Y-m-d'));
            })
            ->get();

        $count = 0;
        $period = \Carbon\CarbonPeriod::create($start, $end);

        foreach ($period as $date) {
            // 1. Skip weekends
            if ($date->isSaturday() || $date->isSunday()) {
                continue;
            }

            // 2. Skip public holidays
            $currentDateStr = $date->format('Y-m-d');
            $isHoliday = $holidays->contains(function ($h) use ($currentDateStr) {
                $hStart = \Carbon\Carbon::parse($h->start_date)->format('Y-m-d');
                $hEnd   = \Carbon\Carbon::parse($h->end_date ?? $h->start_date)->format('Y-m-d');
                return $currentDateStr >= $hStart && $currentDateStr <= $hEnd;
            });

            if ($isHoliday) {
                continue;
            }

            $count++;
        }

        return $count;
    }

   /**
     * Given a Start Date and a number of requested days, determine the exact End Date.
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
     * Calculate entitled annual leave days, applying statutory pro-rata rules
     * (1.75 days per month of service) for staff hired mid-year.
     *
     * @param  \Carbon\Carbon|string|null  $hireDate
     * @param  int  $year
     * @return int
     */
    public function calculateAnnualEntitlement($hireDate, int $year): int
    {
        if (empty($hireDate)) {
            return 21;
        }

        try {
            // Handle date formats like '25/02/2025' or '2025-02-25'
            if (is_string($hireDate) && str_contains($hireDate, '/')) {
                $hireCarbon = \Carbon\Carbon::createFromFormat('d/m/Y', trim($hireDate));
            } else {
                $hireCarbon = \Carbon\Carbon::parse($hireDate);
            }
        } catch (\Throwable $e) {
            // Fallback in case of an unexpected date string
            return 21;
        }

        // Hired in a future year: 0 days for this year
        if ($hireCarbon->year > $year) {
            return 0;
        }

        // Hired in a prior year: full 21 working days
        if ($hireCarbon->year < $year) {
            return 21;
        }

        // Hired in the current year: statutory pro-rata calculation
        $monthsServed = 12 - (int) $hireCarbon->month + 1;
        $proRata = $monthsServed * 1.75;

        // Round up using ceil() to guarantee statutory minimum compliance
        return (int) min(21, max(1, (int) ceil($proRata)));
    }
}