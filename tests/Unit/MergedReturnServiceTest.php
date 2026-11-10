<?php

namespace Tests\Unit;

use App\Services\MergedReturnService;
use Tests\TestCase;

class MergedReturnServiceTest extends TestCase
{
    public function test_first_batch_of_a_landlord_keeps_the_plain_code(): void
    {
        $this->assertSame('R1202611LAN-01072', MergedReturnService::masterCode('202611', 'LAN-01072', []));
    }

    public function test_second_batch_of_the_same_landlord_and_period_gets_suffix_2(): void
    {
        $used = ['R1202611LAN-01072'];

        $this->assertSame('R1202611LAN-01072-2', MergedReturnService::masterCode('202611', 'LAN-01072', $used));
    }

    public function test_third_batch_gets_suffix_3(): void
    {
        $used = ['R1202611LAN-01072', 'R1202611LAN-01072-2'];

        $this->assertSame('R1202611LAN-01072-3', MergedReturnService::masterCode('202611', 'LAN-01072', $used));
    }

    public function test_a_freed_suffix_is_taken_again(): void
    {
        $used = ['R1202611LAN-01072', 'R1202611LAN-01072-3'];

        $this->assertSame('R1202611LAN-01072-2', MergedReturnService::masterCode('202611', 'LAN-01072', $used));
    }

    public function test_codes_of_other_landlords_or_periods_do_not_count(): void
    {
        $used = ['R1202611LAN-01073', 'R1202610LAN-01072', 'R1202611LAN-010720'];

        $this->assertSame('R1202611LAN-01072', MergedReturnService::masterCode('202611', 'LAN-01072', $used));
    }

    public function test_suffixed_code_still_starts_with_r1_so_the_returns_queries_find_it(): void
    {
        $code = MergedReturnService::masterCode('202611', 'LAN-01072', ['R1202611LAN-01072']);

        $this->assertStringStartsWith('R1', $code);
        $this->assertLessThanOrEqual(50, strlen($code));
    }
}
