<?php

namespace Tests\Unit;

use App\Models\Staff;
use App\Support\HireDate;
use Tests\TestCase;

class HireDateTest extends TestCase
{
    public function test_every_stored_format_is_read(): void
    {
        $this->assertSame('2026-10-04', HireDate::parse('2026-10-04 00:00:00')->toDateString());
        $this->assertSame('2010-01-01', HireDate::parse('Jan  1 2010 12:00AM')->toDateString());
        $this->assertSame('2025-02-25', HireDate::parse('25/02/2025')->toDateString());
        $this->assertSame('2023-05-23', HireDate::parse('5/23/2023')->toDateString());
        $this->assertNull(HireDate::parse(null));
        $this->assertNull(HireDate::parse('not a date'));
    }

    public function test_a_day_first_hire_date_no_longer_crashes_the_staff_model(): void
    {
        $staff = (new Staff())->setRawAttributes(['hire_date' => '25/02/2025']);

        $this->assertSame('25 Feb 2025', $staff->hire_date->format('d M Y'));
        $this->assertNull((new Staff())->setRawAttributes(['hire_date' => null])->hire_date);
    }
}
