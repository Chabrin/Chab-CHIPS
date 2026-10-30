<?php

namespace Tests\Unit;

use App\Services\ClientAdvanceService;
use Tests\TestCase;

class ClientAdvanceServiceTest extends TestCase
{
    public function test_limit_is_eighty_percent_of_a_year_of_average_returns(): void
    {
        $this->assertEquals(960000.0, (new ClientAdvanceService())->availableLimit(100000, 0));
    }

    public function test_outstanding_advances_are_taken_off_the_limit(): void
    {
        $this->assertEquals(930000.0, (new ClientAdvanceService())->availableLimit(100000, 30000));
    }

    public function test_limit_is_zero_when_outstanding_uses_it_all_up(): void
    {
        $this->assertEquals(0.0, (new ClientAdvanceService())->availableLimit(100000, 960000));
    }

    public function test_limit_is_never_negative(): void
    {
        $this->assertEquals(0.0, (new ClientAdvanceService())->availableLimit(100000, 2000000));
    }

    public function test_a_client_with_no_returns_has_no_limit(): void
    {
        $this->assertEquals(0.0, (new ClientAdvanceService())->availableLimit(0, 0));
    }

    public function test_limit_is_rounded_to_cents(): void
    {
        $this->assertEquals(319999.97, (new ClientAdvanceService())->availableLimit(33333.33, 0));
    }

    public function test_no_interest_adjustment_when_the_total_is_unchanged(): void
    {
        $this->assertNull((new ClientAdvanceService())->interestAdjustment(95500, 95500));
    }

    public function test_a_change_below_a_cent_is_ignored(): void
    {
        $this->assertNull((new ClientAdvanceService())->interestAdjustment(95500, 95500.004));
    }

    public function test_lower_total_after_a_restructure_reduces_the_interest(): void
    {
        $this->assertEquals(['increase' => false, 'amount' => 900.0], (new ClientAdvanceService())->interestAdjustment(95500, 94600));
    }

    public function test_longer_term_at_the_same_rate_increases_the_interest(): void
    {
        $this->assertEquals(['increase' => true, 'amount' => 4050.0], (new ClientAdvanceService())->interestAdjustment(95500, 99550));
    }

    public function test_adjustment_amount_is_rounded_to_cents(): void
    {
        $this->assertEquals(['increase' => true, 'amount' => 0.1], (new ClientAdvanceService())->interestAdjustment(100.10, 100.20));
    }
}
