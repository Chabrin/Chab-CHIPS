<?php

namespace Tests\Unit;

use App\Models\ClientAdvance;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientAdvanceEditRuleTest extends TestCase
{
    public static function statuses(): array
    {
        return [
            'Pending can be edited' => [1, true],
            'Cleared is closed' => [2, false],
            'Cancelled is closed' => [3, false],
            'Suspended is closed' => [4, false],
            'Approved is signed off' => [5, false],
            'Posted uses Restructure or Reschedule' => [6, false],
            'Rescheduled is closed' => [8, false],
        ];
    }

    #[DataProvider('statuses')]
    public function test_only_a_pending_advance_can_be_edited(int $status, bool $expected): void
    {
        $this->assertSame($expected, (new ClientAdvance(['current_status_id' => $status]))->canBeEdited());
    }
}
