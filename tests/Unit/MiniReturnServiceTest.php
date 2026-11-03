<?php

namespace Tests\Unit;

use App\Services\MiniReturnService;
use Tests\TestCase;

class MiniReturnServiceTest extends TestCase
{
    public function test_return_code_is_r_sequence_period_landlord_property(): void
    {
        $this->assertSame('R2202604LAN-0073465G', MiniReturnService::returnCode(2, '202604', 'LAN-00734', '65G'));
        $this->assertSame('R1202604LAN-0073465G', MiniReturnService::returnCode(1, '202604', 'LAN-00734', '65G'));
    }

    public function test_first_mini_return_of_a_month_is_sequence_2(): void
    {
        $this->assertSame(2, MiniReturnService::nextSequence([], '202604', 'LAN-00734', '65G'));
    }

    public function test_next_mini_return_takes_the_first_free_sequence(): void
    {
        $used = ['R2202604LAN-0073465G', 'R3202604LAN-0073465G'];

        $this->assertSame(4, MiniReturnService::nextSequence($used, '202604', 'LAN-00734', '65G'));
    }

    public function test_a_freed_sequence_is_reused(): void
    {
        $used = ['R3202604LAN-0073465G'];

        $this->assertSame(2, MiniReturnService::nextSequence($used, '202604', 'LAN-00734', '65G'));
    }

    public function test_codes_of_other_properties_or_months_do_not_count(): void
    {
        $used = ['R2202604LAN-0073466G', 'R2202605LAN-0073465G', 'R2202604LAN-0073565G'];

        $this->assertSame(2, MiniReturnService::nextSequence($used, '202604', 'LAN-00734', '65G'));
    }

    public function test_there_is_no_next_sequence_when_all_eight_are_used(): void
    {
        $used = [];
        for ($sequence = 2; $sequence <= 9; $sequence++) {
            $used[] = MiniReturnService::returnCode($sequence, '202604', 'LAN-00734', '65G');
        }

        $this->assertNull(MiniReturnService::nextSequence($used, '202604', 'LAN-00734', '65G'));
    }
}
