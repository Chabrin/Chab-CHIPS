<?php

namespace Tests\Unit;

use App\Traits\LeaseCreationTrait;
use PHPUnit\Framework\TestCase;

class LeaseTermTest extends TestCase
{
    private function term(array $data): array
    {
        $host = new class {
            use LeaseCreationTrait;
        };
        $method = new \ReflectionMethod($host, 'resolveLeaseTerm');

        return $method->invoke($host, $data);
    }

    public function test_years_still_give_start_plus_years(): void
    {
        $this->assertSame(['2028-01-15', 2, false], $this->term(['date_start' => '2026-01-15', 'years' => 2]));
    }

    public function test_open_ended_has_no_end_date_or_years(): void
    {
        $this->assertSame([null, null, true], $this->term(['date_start' => '2026-01-15', 'years' => 3, 'open_ended' => true]));
    }

    public function test_explicit_end_date_wins_and_years_are_derived(): void
    {
        $this->assertSame(['2027-01-15', '1', false], $this->term(['date_start' => '2026-01-15', 'date_end' => '2027-01-15']));
        $this->assertSame(['2026-07-15', '0.5', false], $this->term(['date_start' => '2026-01-15', 'date_end' => '2026-07-15']));
    }
}
