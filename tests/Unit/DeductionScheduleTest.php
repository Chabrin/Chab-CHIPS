<?php

namespace Tests\Unit;

use App\Services\DeductionLimits;
use App\Services\DeductionSchedule;
use PHPUnit\Framework\TestCase;

/**
 * A deduction that runs for a set period and then stops, and the legal limits on what can be taken from one month's pay.
 */
class DeductionScheduleTest extends TestCase
{
    // ---- the schedule -----------------------------------------------------------------------------------------------------

    public function test_the_example_in_the_brief_runs_eleven_months_with_the_rest_in_the_last(): void
    {
        $plan = DeductionSchedule::build(21500, 2000, '2026-12-15');

        $this->assertSame(11, $plan['months']);
        $this->assertSame([2000.0, 2000.0, 2000.0, 2000.0, 2000.0, 2000.0, 2000.0, 2000.0, 2000.0, 2000.0, 1500.0], array_column($plan['rows'], 'amount'));
        $this->assertSame(1500.0, $plan['last_amount']);
        $this->assertSame(0.0, $plan['rows'][10]['balance'], 'the balance ends at zero');
        $this->assertSame(19500.0, $plan['rows'][0]['balance']);
        $this->assertSame('2026-12', $plan['rows'][0]['month']);
        $this->assertSame('2027-10', $plan['end_month']);
        $this->assertSame([], $plan['problems']);
    }

    public function test_an_exact_multiple_has_no_short_last_month(): void
    {
        $plan = DeductionSchedule::build(12000, 3000, '2026-11-01');

        $this->assertSame(4, $plan['months']);
        $this->assertSame([3000.0, 3000.0, 3000.0, 3000.0], array_column($plan['rows'], 'amount'));
    }

    public function test_a_monthly_amount_bigger_than_the_total_is_one_month(): void
    {
        $plan = DeductionSchedule::build(500, 2000, '2026-11-01');

        $this->assertSame(1, $plan['months']);
        $this->assertSame([500.0], array_column($plan['rows'], 'amount'));
    }

    public function test_the_months_are_worked_out_from_the_total_and_the_monthly_amount(): void
    {
        $this->assertSame(11, DeductionSchedule::build(21500, 2000, '2026-12-01')['months']);
        $this->assertSame(1, DeductionSchedule::build(1999.99, 2000, '2026-12-01')['months']);
        $this->assertSame(2, DeductionSchedule::build(2000.01, 2000, '2026-12-01')['months']);
    }

    public function test_the_monthly_amount_is_worked_out_from_the_months_and_never_leaves_a_bigger_last_month(): void
    {
        $plan = DeductionSchedule::build(10000, null, '2026-11-01', 3);

        $this->assertSame(3, $plan['months']);
        $this->assertSame(3333.34, $plan['monthly']);
        $this->assertSame([3333.34, 3333.34, 3333.32], array_column($plan['rows'], 'amount'));
        $this->assertEqualsWithDelta(10000.0, array_sum(array_column($plan['rows'], 'amount')), 0.001);
    }

    public function test_the_end_month_gives_the_number_of_months(): void
    {
        $plan = DeductionSchedule::build(12000, null, '2026-11-20', null, '2027-04');

        $this->assertSame(6, $plan['months'], 'November to April is six months');
        $this->assertSame(2000.0, $plan['monthly']);
        $this->assertSame('2027-04', $plan['end_month']);
    }

    public function test_months_asked_for_that_the_monthly_amount_cannot_meet_are_a_problem_with_the_amount_that_would_work(): void
    {
        $plan = DeductionSchedule::build(21500, 2000, '2026-12-01', 6);

        $this->assertCount(1, $plan['problems']);
        $this->assertStringContainsString('takes 11 months, not 6', $plan['problems'][0]);
        $this->assertStringContainsString('at least KES 3,583.34', $plan['problems'][0]);
    }

    public function test_fewer_months_needed_than_asked_is_a_note_not_a_problem(): void
    {
        $plan = DeductionSchedule::build(6000, 3000, '2026-12-01', 5);

        $this->assertSame([], $plan['problems']);
        $this->assertSame(2, $plan['months']);
        $this->assertStringContainsString('finishes after 2 months', $plan['notes'][0]);
    }

    public function test_nothing_to_build_from_is_a_problem(): void
    {
        $this->assertNotEmpty(DeductionSchedule::build(0, 1000, '2026-12-01')['problems']);
        $this->assertNotEmpty(DeductionSchedule::build(-5, 1000, '2026-12-01')['problems']);
        $this->assertNotEmpty(DeductionSchedule::build(1000, null, '2026-12-01')['problems'], 'no monthly amount, no months, no end month');
        $this->assertNotEmpty(DeductionSchedule::build(1000, 100, 'not a date')['problems']);
        $this->assertNotEmpty(DeductionSchedule::build(1000, null, '2026-12-01', null, '2026-10')['problems'], 'the end before the start');
        $this->assertNotEmpty(DeductionSchedule::build(1000, null, '2026-12-01', 0)['problems']);
        $this->assertSame([], DeductionSchedule::build(1000, 100, 'not a date')['rows']);
    }

    public function test_months_roll_over_the_year_end(): void
    {
        $plan = DeductionSchedule::build(5000, 1000, '2026-11-30');

        $this->assertSame(['2026-11', '2026-12', '2027-01', '2027-02', '2027-03'], array_column($plan['rows'], 'month'));
        $this->assertSame('Nov 2026', $plan['rows'][0]['label']);
    }

    public function test_cents_do_not_drift_over_a_long_schedule(): void
    {
        $plan = DeductionSchedule::build(33.33, 0.30, '2026-01-01');

        $this->assertSame(112, $plan['months']);
        $this->assertSame(0.03, $plan['last_amount']);
        $this->assertSame(0.0, end($plan['rows'])['balance']);
        $this->assertEqualsWithDelta(33.33, array_sum(array_column($plan['rows'], 'amount')), 0.0001);
    }

    public function test_a_schedule_longer_than_ten_years_is_refused_instead_of_built(): void
    {
        $plan = DeductionSchedule::build(1000000, 1, '2026-01-01');

        $this->assertSame([], $plan['rows']);
        $this->assertStringContainsString('120 months', $plan['problems'][0]);
        $this->assertCount(120, DeductionSchedule::build(120000, 1000, '2026-01-01')['rows'], 'exactly ten years is allowed');
        $this->assertNotEmpty(DeductionSchedule::build(121000, 1000, '2026-01-01')['problems'], 'one month more is not');
    }

    // ---- the legal limits -------------------------------------------------------------------------------------------------

    private function item(string $name, string $kind, float $amount): array
    {
        return ['id' => $name, 'name' => $name, 'kind' => $kind, 'amount' => $amount];
    }

    public function test_deductions_within_two_thirds_are_fine(): void
    {
        $result = DeductionLimits::check(90000, 30000, [$this->item('HELB', 'helb', 5000), $this->item('Akiba', 'akiba', 10000)]);

        $this->assertTrue($result['ok']);
        $this->assertSame(45000.0, $result['deductions']);
        $this->assertSame(45000.0, $result['take_home']);
        $this->assertSame(30000.0, $result['minimum_take_home']);
        $this->assertSame([], $result['breaches']);
    }

    public function test_leaving_exactly_one_third_is_allowed_and_one_cent_less_is_not(): void
    {
        $this->assertTrue(DeductionLimits::check(90000, 50000, [$this->item('Akiba', 'akiba', 10000)])['ok'], 'exactly two-thirds');

        $over = DeductionLimits::check(90000, 50000, [$this->item('Akiba', 'akiba', 10000.01)]);
        $this->assertFalse($over['ok']);
        $this->assertSame('two_thirds', $over['breaches'][0]['rule']);
        $this->assertSame(0.01, $over['breaches'][0]['excess']);
    }

    public function test_a_payroll_that_leaves_less_than_a_third_is_a_breach_that_says_what_to_reduce_largest_first(): void
    {
        $result = DeductionLimits::check(60000, 25000, [$this->item('Akiba', 'akiba', 12000), $this->item('Damage', 'loss_damage', 6000), $this->item('HELB', 'helb', 3000)]);

        $this->assertFalse($result['ok']);
        $this->assertSame(46000.0, $result['deductions']);
        $this->assertSame(20000.0, $result['minimum_take_home']);
        $this->assertSame(6000.0, $result['breaches'][0]['excess']);
        $this->assertSame([['name' => 'Akiba', 'amount' => 12000.0, 'reduce_by' => 6000.0]], $result['reduce']);
    }

    public function test_the_reduction_spreads_over_several_deductions_when_one_is_not_enough(): void
    {
        $result = DeductionLimits::check(30000, 14000, [$this->item('A', 'other', 4000), $this->item('B', 'other', 3000), $this->item('C', 'other', 1000)]);

        $this->assertSame(2000.0, $result['breaches'][0]['excess']);
        $this->assertSame([['name' => 'A', 'amount' => 4000.0, 'reduce_by' => 2000.0]], $result['reduce']);

        // 18,000 + 4,200 = 22,200, which is 2,200 over the 20,000 limit
        $bigger = DeductionLimits::check(30000, 18000, [$this->item('A', 'other', 1500), $this->item('B', 'other', 1400), $this->item('C', 'other', 1300)]);
        $this->assertSame(2200.0, $bigger['breaches'][0]['excess']);
        $this->assertSame(['A', 'B'], array_column($bigger['reduce'], 'name'));
        $this->assertSame([1500.0, 700.0], array_column($bigger['reduce'], 'reduce_by'), 'A is taken to nothing, then B is reduced by what is still over');
    }

    public function test_a_company_loan_may_be_at_most_half_of_what_is_left_after_the_other_deductions(): void
    {
        // Wages 60,000, other deductions 10,000, so 50,000 is left: the loan may be 25,000 (and the total, 35,000, is inside two-thirds)
        $fine = DeductionLimits::check(60000, 10000, [$this->item('Advance', 'salary_advance', 25000)]);
        $this->assertTrue($fine['ok']);

        $over = DeductionLimits::check(60000, 10000, [$this->item('Advance', 'salary_advance', 25000.01)]);
        $this->assertFalse($over['ok']);
        $this->assertSame(['loan_half'], array_column($over['breaches'], 'rule'));
        $this->assertSame(0.01, $over['breaches'][0]['excess']);
        $this->assertSame([['name' => 'Advance', 'amount' => 25000.01, 'reduce_by' => 0.01]], $over['reduce']);
    }

    public function test_only_a_company_loan_is_held_to_the_half_rule(): void
    {
        // 26,000 is more than half of the 50,000 left after the other deductions, but a Sacco loan is not a company loan
        $akiba = DeductionLimits::check(60000, 10000, [$this->item('Akiba', 'akiba', 26000)]);

        $this->assertTrue($akiba['ok'], 'a Sacco loan is only held to the two-thirds rule');
    }

    public function test_when_the_loan_rule_is_broken_only_the_loan_is_asked_to_come_down(): void
    {
        $result = DeductionLimits::check(60000, 20000, [$this->item('Akiba', 'akiba', 10000), $this->item('Advance', 'salary_advance', 15000)]);

        // The loan, 15,000, is exactly half of the 30,000 left after the other deductions, so that rule is kept; the total, 45,000, is over the 40,000 limit.
        $this->assertFalse($result['ok']);
        $this->assertSame('two_thirds', $result['breaches'][0]['rule']);
        $this->assertSame(5000.0, $result['breaches'][0]['excess']);

        // Left after the other deductions (11,000): 49,000, so the loan may be 24,500. It is 26,000, and the total, 37,000, is inside two-thirds.
        $loanOnly = DeductionLimits::check(60000, 10000, [$this->item('Akiba', 'akiba', 1000), $this->item('Advance', 'salary_advance', 26000)]);
        $this->assertSame(['loan_half'], array_column($loanOnly['breaches'], 'rule'));
        $this->assertSame(1500.0, $loanOnly['breaches'][0]['excess']);
        $this->assertSame(['Advance'], array_column($loanOnly['reduce'], 'name'), 'only the company loan can be reduced for the loan rule');
    }

    public function test_no_scheduled_deductions_and_no_wages_do_not_break_anything(): void
    {
        $this->assertTrue(DeductionLimits::check(50000, 12000, [])['ok']);
        $this->assertFalse(DeductionLimits::check(0, 100, [])['ok'], 'any deduction from no wages is over the limit');
    }
}
