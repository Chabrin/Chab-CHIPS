<?php

namespace App\Services;

use App\Models\PayrollCalendar;
use App\Models\PayrollPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Opens the next payroll month once a month has been paid: a calendar month and one period from its 1st to its last day,
 * so HR never has to set them up by hand. Opening is safe to repeat. A month that already has a period is left alone.
 */
class PayrollMonthOpener
{
    /** Status used for an active calendar month or period. */
    private const ACTIVE = 5;

    /**
     * Open the month after the one the paid period belongs to.
     *
     * @return array{label: string, start: string, end: string, period: PayrollPeriod, calendar_created: bool}|null
     *         null when nothing needed opening because the month already has a period.
     *
     * @throws \DomainException when the paid period has no payroll month on the calendar, so there is nothing to count on from
     */
    public function openAfter(PayrollPeriod $paid, ?int $userId): ?array
    {
        // The live column holds the month as a number (202610) while other databases hand it back as text, so read it as text
        $monthDate = (string) ($paid->calendar?->payroll_month_date ?? '');
        if (!preg_match('/^\d{6}$/', $monthDate)) {
            throw new \DomainException('The paid period has no payroll month on the calendar, so the next month cannot be worked out.');
        }

        $next = CarbonImmutable::createFromFormat('Ymd', $monthDate . '01')->startOfDay()->addMonthNoOverflow();
        $yearMonth = $next->format('Ym');

        return DB::transaction(function () use ($next, $yearMonth, $userId) {
            // Older data can hold the same month twice; the month counts as open as soon as any of them has a period.
            $calendars = PayrollCalendar::where('payroll_month_date', $yearMonth)->orderBy('id')->get();
            if ($calendars->contains(fn (PayrollCalendar $calendar) => $calendar->periods()->exists())) {
                return null;
            }

            $created = $calendars->isEmpty();
            $calendar = $calendars->first() ?? PayrollCalendar::create([
                'payroll_month_date' => $yearMonth,
                'current_status_id' => self::ACTIVE,
                'created_by' => $userId,
                'date_time' => now(),
            ]);

            $start = $next->startOfMonth();
            $end = $next->endOfMonth()->startOfDay();
            $period = PayrollPeriod::create([
                'calendar_id' => $calendar->id,
                'period_number' => 1,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'period_month' => $next->format('F'),
                'current_status_id' => self::ACTIVE,
                'created_by' => $userId,
                'date_time' => now(),
            ]);

            return [
                'label' => $next->format('F Y'),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'period' => $period,
                'calendar_created' => $created,
            ];
        });
    }
}
