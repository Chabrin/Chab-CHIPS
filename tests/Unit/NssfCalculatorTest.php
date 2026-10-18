<?php

namespace Tests\Unit;

use App\Services\NssfCalculator;
use Tests\TestCase;

class NssfCalculatorTest extends TestCase
{
    public function test_gross_inside_tier_one_only_pays_tier_one(): void
    {
        $result = (new NssfCalculator())->calculate(5000);

        $this->assertEquals(300.0, $result['tiers'][0]['amount']);
        $this->assertEquals(0.0, $result['tiers'][1]['amount']);
        $this->assertEquals(300.0, $result['total']);
    }

    public function test_gross_between_the_limits_splits_into_both_tiers(): void
    {
        $result = (new NssfCalculator())->calculate(50000);

        $this->assertEquals(540.0, $result['tiers'][0]['amount']);
        $this->assertEquals(2460.0, $result['tiers'][1]['amount']);
        $this->assertEquals(3000.0, $result['total']);
    }

    public function test_gross_above_the_upper_limit_is_capped(): void
    {
        $result = (new NssfCalculator())->calculate(250000);

        $this->assertEquals(6480.0, $result['total']);
    }

    public function test_negative_gross_pays_nothing(): void
    {
        $this->assertEquals(0.0, (new NssfCalculator())->calculate(-100)['total']);
    }
}
