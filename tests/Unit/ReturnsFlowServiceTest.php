<?php

namespace Tests\Unit;

use App\Services\ReturnsFlowService;
use PHPUnit\Framework\TestCase;

class ReturnsFlowServiceTest extends TestCase
{
    public function test_a_return_with_no_approval_item_is_only_generated(): void
    {
        $this->assertSame('Generated, not submitted', ReturnsFlowService::stageOf(['approved_status' => 0, 'paid_status' => 0]));
    }

    public function test_a_waiting_return_says_which_level_of_how_many(): void
    {
        $row = ['item_id' => 5, 'item_status' => 'Pending', 'current_level' => 2, 'total_levels' => 3, 'approved_status' => 0, 'paid_status' => 0];

        $this->assertSame('Awaiting approval level 2 of 3', ReturnsFlowService::stageOf($row));
    }

    public function test_a_rejected_return_is_rejected_unless_it_was_approved_after(): void
    {
        $this->assertSame('Rejected', ReturnsFlowService::stageOf(['item_id' => 5, 'item_status' => 'Rejected', 'approved_status' => 0, 'paid_status' => 0]));
        $this->assertSame('Approved, no voucher yet', ReturnsFlowService::stageOf(['item_id' => 5, 'item_status' => 'Rejected', 'approved_status' => 1, 'paid_status' => 0]));
    }

    public function test_an_approved_return_moves_on_through_its_voucher_to_paid(): void
    {
        $approved = ['item_id' => 5, 'item_status' => 'Approved', 'approved_status' => 1, 'paid_status' => 0];

        $this->assertSame('Approved, no voucher yet', ReturnsFlowService::stageOf($approved));
        $this->assertSame('Voucher awaiting approval', ReturnsFlowService::stageOf($approved + ['voucher_status' => 'Pending']));
        $this->assertSame('Voucher approved', ReturnsFlowService::stageOf($approved + ['voucher_status' => 'Approved']));
        // A cancelled or rejected voucher frees the return, so it is back to approved with no voucher
        $this->assertSame('Approved, no voucher yet', ReturnsFlowService::stageOf($approved + ['voucher_status' => 'Cancelled']));
        $this->assertSame('Approved, no voucher yet', ReturnsFlowService::stageOf($approved + ['voucher_status' => 'Rejected']));
        $this->assertSame('Paid', ReturnsFlowService::stageOf(array_merge($approved, ['voucher_status' => 'Approved', 'paid_status' => 1])));
    }

    public function test_every_stage_a_return_can_reach_can_be_filtered_on(): void
    {
        $stages = array_keys((new ReturnsFlowService())->stages());

        foreach (['Generated, not submitted', 'Rejected', 'Approved, no voucher yet', 'Voucher awaiting approval', 'Voucher approved', 'Paid', 'awaiting'] as $stage) {
            $this->assertContains($stage, $stages);
        }
    }
}
