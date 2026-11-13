<?php

namespace App\Services\Performance;

use App\Models\LeaveRequest;
use App\Models\PerformanceReview;
use App\Models\Staff;
use App\Services\Leave\LeaveCalendarService;
use App\Support\HireDate;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * The records shown beside the ratings: figures worked out from what the system already holds about the person for the
 * cycle's period (attendance, leave, hire date), with the formulas and thresholds from HR's metrics document. They are for
 * the supervisor and HR to look at while rating; they never change a score by themselves.
 *
 *  - Absenteeism rate = absent days / (headcount x scheduled workdays) x 100; headcount is 1 for one person.
 *  - Overtime hours = hours worked - normal hours; overtime percentage = overtime hours / normal hours x 100.
 *  - Capacity utilisation = hours worked / contractual maximum hours x 100.
 *  - Length of service = (assessment date - hire date) / 365.
 *  - Annual leave utilisation = leave days taken / leave days allocated x 100.
 *
 * Working days and holidays come from the same HR settings leave uses (Monday to Friday full days, Saturday a half day,
 * Sunday off, unless HR's Work Days setting says otherwise; public holidays are not scheduled). Normal hours follow the
 * working week of 8 to 5 on weekdays (9 hours) and 9 to 4 on Saturday (7 hours), which is the 52 hours a week and about
 * 226 hours a month in the document. A person with no attendance records in the period gets "no attendance data", never
 * 100% absent. The contractual maximum is taken as the normal hours until HR sets something else.
 */
class PerformanceMetrics
{
    /** Hours in a full scheduled day (8 to 5) and in a half day such as Saturday (9 to 4). */
    public const FULL_DAY_HOURS = 9.0;

    public const HALF_DAY_HOURS = 7.0;

    public const FLAG_GOOD = 'good';

    public const FLAG_WATCH = 'watch';

    public const FLAG_CONCERN = 'concern';

    public const FLAG_NONE = 'none';

    public function __construct(private LeaveCalendarService $calendar) {}

    /** The records for a review: its staff member over its cycle's period. */
    public function forReview(PerformanceReview $review, ?Carbon $today = null): array
    {
        $review->loadMissing(['cycle', 'staff']);

        return $this->forStaff($review->staff, Carbon::parse($review->cycle->period_start), Carbon::parse($review->cycle->period_end), $today);
    }

    /**
     * @return array{period: string, taken_at: string, items: array<int, array{key: string, label: string, value: float|null, display: string, flag: string, note: string|null}>}
     */
    public function forStaff(Staff $staff, Carbon $periodStart, Carbon $periodEnd, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $hired = HireDate::parse($staff->getRawOriginal('hire_date'));

        // From the later of the period's start and the hire date, to the earlier of the period's end and today
        $from = $periodStart->copy()->startOfDay();
        if ($hired && $hired->copy()->startOfDay()->gt($from)) {
            $from = $hired->copy()->startOfDay();
        }
        $to = $periodEnd->copy()->startOfDay();
        if ($to->gt($today)) {
            $to = $today->copy();
        }

        $out = [
            'period' => $periodStart->format('d M Y').' to '.$periodEnd->format('d M Y'),
            'taken_at' => now()->format('d M Y H:i'),
            'items' => [],
        ];

        $schedule = $to->gte($from) ? $this->schedule($from, $to) : ['days' => 0.0, 'hours' => 0.0, 'dates' => []];
        $attendance = $to->gte($from) ? $this->attendance($staff, $from, $to) : null;

        $out['items'][] = $this->normalHours($schedule, $to->gte($from));
        foreach ($this->attendanceItems($schedule, $attendance, $this->leaveDates($staff, $from, $to), $to->gte($from)) as $item) {
            $out['items'][] = $item;
        }
        $out['items'][] = $this->tenure($hired, $to->gte($from) ? $to : $periodEnd->copy()->startOfDay());
        $out['items'][] = $this->annualLeave($staff, $hired, $to->gte($from) ? $to : $periodEnd->copy()->startOfDay());

        return $out;
    }

    // ── the working calendar ──────────────────────────────────────────────

    /**
     * The scheduled days between two dates: how many (a Saturday is half a day), how many normal hours, and which dates.
     *
     * @return array{days: float, hours: float, dates: array<string, float>}
     */
    private function schedule(Carbon $from, Carbon $to): array
    {
        $weights = $this->calendar->dayWeights();
        $days = 0.0;
        $hours = 0.0;
        $dates = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $weight = $weights[$date->dayOfWeekIso] ?? 0.0;
            if ($weight <= 0 || $this->calendar->holidayOn($date)) {
                continue;
            }
            $days += $weight;
            $hours += $weight >= 1.0 ? self::FULL_DAY_HOURS : self::HALF_DAY_HOURS;
            $dates[$date->toDateString()] = $weight;
        }

        return ['days' => $days, 'hours' => $hours, 'dates' => $dates];
    }

    /**
     * The hours worked on each date the person has an attendance record for, or null when they have none in the period.
     *
     * @return array<string, float>|null
     */
    private function attendance(Staff $staff, Carbon $from, Carbon $to): ?array
    {
        if ($staff->staff_number === null || $staff->staff_number === '') {
            return null;
        }

        $rows = DB::table('sys_hr_employees_attendance_255_repeat as r')
            ->join('sys_hr_employees_attendance as a', 'a.id', '=', 'r.parent_id')
            ->where('a.employee_id', (string) $staff->staff_number)
            ->whereBetween('r.date', [$from->toDateString().' 00:00:00', $to->toDateString().' 23:59:59'])
            ->get(['r.date', 'r.time_in', 'r.time_out', 'r.hours']);

        if ($rows->isEmpty()) {
            return null;
        }

        $perDate = [];
        foreach ($rows as $row) {
            $date = substr((string) $row->date, 0, 10);
            // A day uploaded twice counts once, at its longest
            $perDate[$date] = max($perDate[$date] ?? 0.0, $this->hoursOf($row));
        }

        return $perDate;
    }

    private function hoursOf(object $row): float
    {
        if ($row->hours !== null && (float) $row->hours > 0) {
            return (float) $row->hours;
        }
        if ($row->time_in && $row->time_out) {
            $in = Carbon::parse($row->time_in);
            $out = Carbon::parse($row->time_out);

            return $out->gt($in) ? round($in->diffInMinutes($out) / 60, 2) : 0.0;
        }

        return 0.0;
    }

    /**
     * Dates covered by approved leave of any kind. Leave cut short by a recall stops the day before the recall.
     *
     * @return array<string, true>
     */
    private function leaveDates(Staff $staff, Carbon $from, Carbon $to): array
    {
        if ($to->lt($from)) {
            return [];
        }

        $dates = [];
        $requests = DB::table('sys_hr_leave_requests')
            ->where('staff_id', $staff->id)
            ->where('current_status_id', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get(['start_date', 'end_date', 'recall_effective_date']);

        foreach ($requests as $request) {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $end = Carbon::parse($request->end_date)->startOfDay();
            if ($request->recall_effective_date) {
                $end = $end->min(Carbon::parse($request->recall_effective_date)->startOfDay()->subDay());
            }
            $start = $start->max($from);
            $end = $end->min($to);
            if ($end->lt($start)) {
                continue;
            }
            foreach (CarbonPeriod::create($start, $end) as $date) {
                $dates[$date->toDateString()] = true;
            }
        }

        return $dates;
    }

    // ── the records ───────────────────────────────────────────────────────

    private function item(string $key, string $label, ?float $value, string $display, string $flag = self::FLAG_NONE, ?string $note = null): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value, 'display' => $display, 'flag' => $flag, 'note' => $note];
    }

    private function normalHours(array $schedule, bool $started): array
    {
        if (! $started) {
            return $this->item('normal_hours', 'Normal work hours', null, 'Not started', self::FLAG_NONE, 'The period has not started for this person yet.');
        }

        return $this->item('normal_hours', 'Normal work hours', round($schedule['hours'], 1), $this->number($schedule['hours']).' h in '.$this->number($schedule['days']).' scheduled days', self::FLAG_NONE,
            'Weekdays 8 to 5 (9 h), Saturday 9 to 4 (7 h); public holidays and days before they joined are not counted.');
    }

    /** Absenteeism, overtime and capacity utilisation, which all come from attendance. */
    private function attendanceItems(array $schedule, ?array $attendance, array $leave, bool $started): array
    {
        $none = fn (string $key, string $label, string $text) => $this->item($key, $label, null, $text, self::FLAG_NONE, $text === 'No attendance data' ? 'There are no attendance records for this person in the period.' : null);
        $text = $started ? 'No attendance data' : 'Not started';

        if (! $started || $attendance === null || $schedule['hours'] <= 0) {
            return [
                $none('absenteeism', 'Absenteeism rate', $text),
                $none('overtime', 'Overtime', $text),
                $none('capacity', 'Capacity utilisation', $text),
            ];
        }

        $absent = 0.0;
        foreach ($schedule['dates'] as $date => $weight) {
            if (! isset($attendance[$date]) && ! isset($leave[$date])) {
                $absent += $weight;
            }
        }
        $worked = array_sum($attendance);

        $absenteeism = $schedule['days'] > 0 ? round($absent / $schedule['days'] * 100, 1) : 0.0;
        $overtimeHours = max(0.0, $worked - $schedule['hours']);
        $overtime = round($overtimeHours / $schedule['hours'] * 100, 1);
        $capacity = round($worked / $schedule['hours'] * 100, 1);

        return [
            $this->item('absenteeism', 'Absenteeism rate', $absenteeism,
                $this->number($absenteeism).'% ('.$this->number($absent).' of '.$this->number($schedule['days']).' scheduled days absent)',
                self::absenteeismFlag($absenteeism), 'Days with no attendance and no approved leave. Target under 3%; over 5% needs attention.'),
            $this->item('overtime', 'Overtime', $overtime,
                $this->number($overtimeHours).' h, '.$this->number($overtime).'% of normal hours ('.$this->number($worked).' h worked)',
                self::overtimeFlag($overtime), 'Hours worked beyond normal hours. Under 5% is fine; over 10% needs a look.'),
            $this->item('capacity', 'Capacity utilisation', $capacity,
                $this->number($capacity).'% ('.$this->number($worked).' h of '.$this->number($schedule['hours']).' h)',
                self::capacityFlag($capacity), 'Hours worked against the maximum hours, taken as the normal hours. 80 to 95% is healthy; under 80% is under-used; over 95% risks burnout.'),
        ];
    }

    private function tenure(?Carbon $hired, Carbon $assessed): array
    {
        if (! $hired) {
            return $this->item('tenure', 'Length of service', null, 'No hire date on record', self::FLAG_NONE);
        }
        if ($hired->gt($assessed)) {
            return $this->item('tenure', 'Length of service', 0.0, 'Joined after the period', self::FLAG_NONE);
        }

        $years = round($hired->copy()->startOfDay()->diffInDays($assessed) / 365, 1);

        return $this->item('tenure', 'Length of service', $years, $this->number($years).' years (since '.$hired->format('d M Y').')',
            $years >= 3 ? self::FLAG_GOOD : self::FLAG_NONE, 'Counted to '.$assessed->format('d M Y').'.');
    }

    private function annualLeave(Staff $staff, ?Carbon $hired, Carbon $assessed): array
    {
        $year = (int) $assessed->year;
        $annualTypes = DB::table('sys_hr_leave_types')->get(['id', 'type_name'])
            ->filter(fn ($type) => $this->calendar->isAnnualLeaveType($type->type_name))->pluck('id')->all();
        if (! $annualTypes) {
            return $this->item('annual_leave', 'Annual leave used', null, 'No annual leave type set up', self::FLAG_NONE);
        }

        $allocated = $this->calendar->calculateAnnualEntitlement($hired, $year);
        $taken = (float) DB::table('sys_hr_leave_requests')
            ->where('staff_id', $staff->id)
            ->where('current_status_id', LeaveRequest::STATUS_APPROVED)
            ->whereIn('leave_type_id', $annualTypes)
            ->whereDate('start_date', '>=', $year.'-01-01')
            ->whereDate('start_date', '<=', $assessed->toDateString())
            ->selectRaw('coalesce(sum(days - coalesce(recalled_days, 0)), 0) as taken')->value('taken');

        if ($allocated <= 0) {
            return $this->item('annual_leave', 'Annual leave used', null, 'No annual leave allocated for '.$year, self::FLAG_NONE);
        }

        $utilisation = round($taken / $allocated * 100, 1);

        return $this->item('annual_leave', 'Annual leave used', $utilisation,
            $this->number($utilisation).'% ('.$this->number($taken, 2).' of '.$this->number($allocated, 2).' days in '.$year.')',
            self::leaveFlag($utilisation, $assessed), 'Target is to use at least 95% by the end of November; under 50% by the end of September means leave should be scheduled.');
    }

    // ── the thresholds in HR's document ───────────────────────────────────

    public static function absenteeismFlag(float $percent): string
    {
        return $percent < 3 ? self::FLAG_GOOD : ($percent <= 5 ? self::FLAG_WATCH : self::FLAG_CONCERN);
    }

    public static function overtimeFlag(float $percent): string
    {
        return $percent < 5 ? self::FLAG_GOOD : ($percent <= 10 ? self::FLAG_WATCH : self::FLAG_CONCERN);
    }

    /** Under 80% is under-used, 80 to 95% is healthy, over 95% is over-used. */
    public static function capacityFlag(float $percent): string
    {
        return $percent > 95 ? self::FLAG_CONCERN : ($percent >= 80 ? self::FLAG_GOOD : self::FLAG_WATCH);
    }

    /** Over 100% needs an audit; by the end of November 95% or more is the target; by the end of September under 50% needs scheduling. */
    public static function leaveFlag(float $percent, Carbon $on): string
    {
        if ($percent > 100) {
            return self::FLAG_CONCERN;
        }
        if ($on->month >= 12 || ($on->month === 11 && $on->day >= 30)) {
            return $percent >= 95 ? self::FLAG_GOOD : self::FLAG_WATCH;
        }
        if ($on->month >= 10 || ($on->month === 9 && $on->day >= 30)) {
            return $percent < 50 ? self::FLAG_CONCERN : self::FLAG_GOOD;
        }

        return self::FLAG_NONE;
    }

    /** A figure with up to $decimals places and no needless trailing zeros (leave days are kept to two, as the system accrues them). */
    private function number(float $value, int $decimals = 1): string
    {
        $text = number_format($value, $decimals, '.', '');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
