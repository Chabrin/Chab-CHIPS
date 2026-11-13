<?php

namespace Tests\Unit;

use App\Services\ContractPeriod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The last day of a contract from its start and period. The cases are shared with the browser script
 * (tests/Frontend/contract-period.test.mjs), so the two cannot drift apart.
 */
class ContractPeriodTest extends TestCase
{
    public static function cases(): array
    {
        $rows = json_decode(file_get_contents(__DIR__ . '/../fixtures/contract-period-cases.json'), true, 512, JSON_THROW_ON_ERROR);

        $named = [];
        foreach ($rows as $row) {
            $named["{$row['value']} {$row['unit']} from {$row['start']}"] = [$row['start'], $row['value'], $row['unit'], $row['end']];
        }

        return $named;
    }

    #[DataProvider('cases')]
    public function test_the_end_date_is_the_day_before_the_same_date_one_period_later(string $start, int $value, string $unit, string $end): void
    {
        $this->assertSame($end, ContractPeriod::endDate($start, $value, $unit)->toDateString());
    }

    public function test_a_start_with_a_time_still_gives_a_date(): void
    {
        $this->assertSame('2027-12-31', ContractPeriod::endDate('2027-01-01 00:00:00', 12, 'months')->toDateString());
        $this->assertSame('00:00:00', ContractPeriod::endDate('2027-01-01 15:30:00', 12, 'months')->format('H:i:s'));
    }

    public function test_probation_is_counted_in_months(): void
    {
        $this->assertSame('2027-03-31', ContractPeriod::probationEnd('2027-01-01', 3)->toDateString());
        $this->assertSame('2027-12-14', ContractPeriod::probationEnd('2027-06-15', 6)->toDateString());
    }

    public function test_an_unknown_unit_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ContractPeriod::endDate('2027-01-01', 1, 'fortnights');
    }

    public function test_the_four_units_are_the_ones_the_form_offers(): void
    {
        $this->assertSame(['days', 'weeks', 'months', 'years'], ContractPeriod::UNITS);
    }
}
