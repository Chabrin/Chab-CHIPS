<?php

namespace Tests\Feature;

use App\Models\PayrollCalendar;
use App\Models\PayrollPeriod;
use App\Services\PayrollMonthOpener;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsPayrollRunTables;
use Tests\TestCase;

/**
 * Paying a month opens the next one: a calendar month and one period from its 1st to its last day.
 */
class PayrollMonthOpenerTest extends TestCase
{
    use BuildsPayrollRunTables;

    protected function setUp(): void
    {
        parent::setUp();
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables
        $this->buildPayrollRunTables();
    }

    /** A paid month: its calendar row and its period, returned as the period. */
    private function paidMonth(string $yearMonth, ?string $start = null, ?string $end = null): PayrollPeriod
    {
        $calendar = PayrollCalendar::create(['payroll_month_date' => $yearMonth, 'current_status_id' => 5, 'date_time' => now()]);

        return PayrollPeriod::create([
            'calendar_id' => $calendar->id,
            'period_number' => 1,
            'start_date' => $start ?? substr($yearMonth, 0, 4) . '-' . substr($yearMonth, 4, 2) . '-01',
            'end_date' => $end ?? date('Y-m-t', strtotime(substr($yearMonth, 0, 4) . '-' . substr($yearMonth, 4, 2) . '-01')),
            'current_status_id' => 5,
        ]);
    }

    private function open(PayrollPeriod $paid): ?array
    {
        return app(PayrollMonthOpener::class)->openAfter($paid, 8);
    }

    public function test_the_next_month_opens_with_one_period_from_its_first_to_its_last_day(): void
    {
        $opened = $this->open($this->paidMonth('202610'));

        $this->assertSame('November 2026', $opened['label']);
        $this->assertTrue($opened['calendar_created']);

        $calendar = PayrollCalendar::where('payroll_month_date', '202611')->firstOrFail();
        $this->assertSame(5, (int) $calendar->current_status_id);
        $this->assertSame(8, (int) $calendar->created_by);

        $period = PayrollPeriod::where('calendar_id', $calendar->id)->firstOrFail();
        $this->assertSame('2026-11-01', $period->start_date->toDateString());
        $this->assertSame('2026-11-30', $period->end_date->toDateString());
        $this->assertSame(1, (int) $period->period_number);
        $this->assertSame('November', $period->period_month);
        $this->assertSame(5, (int) $period->current_status_id);
        $this->assertSame(8, (int) $period->created_by);
        $this->assertSame(30, $period->duration_days);
    }

    public function test_december_rolls_over_to_january_of_the_next_year(): void
    {
        $opened = $this->open($this->paidMonth('202612'));

        $this->assertSame('January 2027', $opened['label']);
        $this->assertNotNull(PayrollCalendar::where('payroll_month_date', '202701')->first());
        $this->assertSame('2027-01-31', $opened['end']);
    }

    public function test_february_has_its_own_number_of_days(): void
    {
        $this->assertSame('2027-02-28', $this->open($this->paidMonth('202701'))['end']);
        $this->assertSame('2028-02-29', $this->open($this->paidMonth('202801'))['end']);
    }

    public function test_opening_the_same_month_twice_changes_nothing(): void
    {
        $paid = $this->paidMonth('202610');

        $this->assertNotNull($this->open($paid));
        $this->assertNull($this->open($paid));

        $this->assertSame(1, PayrollCalendar::where('payroll_month_date', '202611')->count());
        $this->assertSame(2, PayrollPeriod::count());
    }

    public function test_a_month_that_already_has_a_period_is_left_alone(): void
    {
        $paid = $this->paidMonth('202610');
        $existing = $this->paidMonth('202611', '2026-11-05', '2026-12-04');

        $this->assertNull($this->open($paid));

        $this->assertSame(1, PayrollPeriod::where('calendar_id', $existing->calendar_id)->count());
        $this->assertSame('2026-11-05', $existing->fresh()->start_date->toDateString());
    }

    public function test_a_calendar_month_added_by_hand_without_a_period_gets_one(): void
    {
        $paid = $this->paidMonth('202610');
        $calendar = PayrollCalendar::create(['payroll_month_date' => '202611', 'current_status_id' => 5, 'date_time' => now()]);

        $opened = $this->open($paid);

        $this->assertFalse($opened['calendar_created']);
        $this->assertSame(1, PayrollCalendar::where('payroll_month_date', '202611')->count());
        $period = PayrollPeriod::where('calendar_id', $calendar->id)->firstOrFail();
        $this->assertSame('2026-11-01', $period->start_date->toDateString());
        $this->assertSame('2026-11-30', $period->end_date->toDateString());
    }

    public function test_older_data_holding_the_same_month_twice_counts_as_open_when_either_has_a_period(): void
    {
        $paid = $this->paidMonth('202611');
        PayrollCalendar::create(['payroll_month_date' => '202612', 'current_status_id' => 6, 'date_time' => now()]);
        $this->paidMonth('202612');

        $this->assertNull($this->open($paid));
        $this->assertSame(1, PayrollPeriod::whereDate('start_date', '2026-12-01')->count());
    }

    public function test_paying_an_older_month_does_not_disturb_months_that_are_already_open(): void
    {
        $this->paidMonth('202610');
        $this->paidMonth('202611');
        $this->paidMonth('202612');
        $before = PayrollPeriod::count();

        $this->assertNull($this->open(PayrollPeriod::whereHas('calendar', fn ($q) => $q->where('payroll_month_date', '202610'))->first()));

        $this->assertSame($before, PayrollPeriod::count());
    }

    public function test_a_period_with_no_calendar_month_is_reported_and_opens_nothing(): void
    {
        $orphan = PayrollPeriod::create(['calendar_id' => null, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'current_status_id' => 5]);

        $this->expectException(\DomainException::class);
        try {
            $this->open($orphan);
        } finally {
            $this->assertSame(0, DB::table('sys_hr_payroll_calendar')->count());
        }
    }

    public function test_the_month_is_read_correctly_when_the_database_returns_it_as_a_number(): void
    {
        $paid = $this->paidMonth('202610');

        // The live column is an integer, so the month comes back as 202610 and not '202610'
        $this->assertTrue(is_int($paid->calendar->payroll_month_date), 'the test column should be an integer like the live one');
        $this->assertSame('November 2026', $this->open($paid)['label']);
    }
}
