<?php

namespace Tests\Unit;

use App\Services\ReturnListService;
use PHPUnit\Framework\TestCase;

class ReturnListApprovalStateTest extends TestCase
{
    private function state(array $return = [], ?array $item = null, ?array $action = null, ?int $levels = 3): array
    {
        return ReturnListService::approvalState((object) ($return + ['approved_status' => 0, 'paid_status' => 0]),
            $item ? (object) ($item + ['status' => 'InProgress', 'current_level' => 2, 'total_levels' => 3]) : null,
            $action ? (object) $action : null, $levels);
    }

    public function test_drafts_and_approved_records_have_distinct_states(): void
    {
        $this->assertTrue($this->state()['is_draft']);
        $this->assertSame('Draft', $this->state()['stage']);
        $this->assertSame('Approved', $this->state(['approved_status' => 1])['stage']);
        $this->assertSame('Paid', $this->state(['approved_status' => 1, 'paid_status' => 1])['stage']);
        $this->assertFalse($this->state(['approved_status' => 1])['is_draft']);
    }

    public function test_pending_returns_show_the_actual_level_and_final_approval(): void
    {
        $this->assertSame('Awaiting level 2 of 3', $this->state([], ['current_level' => 2])['stage']);
        $this->assertSame('Awaiting final approval — level 3 of 3', $this->state([], ['current_level' => 3])['stage']);
        // The approval service allows a pending old level 4 to proceed at the last level of a shortened workflow.
        $this->assertSame('Awaiting final approval — level 2 of 2', $this->state([], ['current_level' => 4], null, 2)['stage']);
    }

    public function test_returned_and_rejected_records_preserve_the_reason(): void
    {
        $state = $this->state([], ['current_level' => 1], ['action' => 'Returned', 'comments' => 'Check the deduction']);
        $this->assertSame('returned', $state['status_key']);
        $this->assertSame('Returned to level 1 of 3', $state['stage']);
        $this->assertSame('Check the deduction', $state['status_note']);
        $this->assertSame('rejected', $this->state([], ['status' => 'Rejected'])['status_key']);
        $this->assertSame('awaiting', $this->state([], ['current_level' => 2], ['action' => 'Approved'])['status_key']);
    }

    public function test_historical_levels_are_used_if_the_current_workflow_is_unavailable(): void
    {
        $this->assertSame('Awaiting level 2 of 4', $this->state([], ['total_levels' => 4], null, null)['stage']);
        $this->assertSame('review', $this->state(['approved_status' => 2])['status_key']);
        $this->assertSame('processing', $this->state([], ['status' => 'Approved'])['status_key']);
    }
}
