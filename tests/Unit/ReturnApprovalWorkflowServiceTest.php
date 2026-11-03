<?php

namespace Tests\Unit;

use App\Exceptions\ReturnApprovalException;
use App\Services\ReturnApprovalWorkflowService;
use Tests\TestCase;

class ReturnApprovalWorkflowServiceTest extends TestCase
{
    public function test_levels_are_grouped_in_order_with_every_approver_of_a_level(): void
    {
        $levels = ReturnApprovalWorkflowService::validateLevels([[2, 1598], [1, 1597], [3, 1600], [2, 1601]]);

        $this->assertSame([1, 2, 3], array_keys($levels));
        $this->assertSame([1597], $levels[1]);
        $this->assertSame([1598, 1601], $levels[2]);
        $this->assertSame([1600], $levels[3]);
    }

    public function test_a_workflow_with_no_approvers_is_refused(): void
    {
        $this->expectException(ReturnApprovalException::class);
        $this->expectExceptionMessage('no active approvers');

        ReturnApprovalWorkflowService::validateLevels([]);
    }

    public function test_a_workflow_that_skips_a_level_is_refused_and_names_the_missing_level(): void
    {
        $this->expectException(ReturnApprovalException::class);
        $this->expectExceptionMessage('Level 2 ');

        ReturnApprovalWorkflowService::validateLevels([[1, 1597], [3, 1600]]);
    }

    public function test_a_workflow_that_does_not_start_at_level_one_is_refused(): void
    {
        $this->expectException(ReturnApprovalException::class);
        $this->expectExceptionMessage('Level 1 ');

        ReturnApprovalWorkflowService::validateLevels([[2, 1598]]);
    }

    public function test_rows_without_a_real_level_or_approver_are_ignored(): void
    {
        $levels = ReturnApprovalWorkflowService::validateLevels([[1, 1597], [0, 1598], [2, 0]]);

        $this->assertSame([1 => [1597]], $levels);
    }

    public function test_positions_become_seats_next_to_named_users(): void
    {
        $levels = ReturnApprovalWorkflowService::validateLevels([[1, 0, 'zone_manager', 0], [2, 0, 'role', 39], [2, 1600, null, 0]]);

        $this->assertSame([1 => ['zone_manager'], 2 => ['role:39', 1600]], $levels);
    }

    public function test_a_position_row_without_a_role_or_the_same_seat_twice_is_ignored(): void
    {
        $levels = ReturnApprovalWorkflowService::validateLevels([[1, 0, 'zone_manager', 0], [1, 0, 'zone_manager', 0], [1, 0, 'role', 0]]);

        $this->assertSame([1 => ['zone_manager']], $levels);
    }

    public function test_seat_of_a_row(): void
    {
        $this->assertSame(1597, ReturnApprovalWorkflowService::seatOf(1597, null, 0));
        $this->assertSame('zone_manager', ReturnApprovalWorkflowService::seatOf(0, 'zone_manager', 0));
        $this->assertSame('role:44', ReturnApprovalWorkflowService::seatOf(0, 'role', 44));
        $this->assertNull(ReturnApprovalWorkflowService::seatOf(0, null, 0));
    }

    public function test_someone_who_holds_the_level_and_took_no_earlier_part_may_act(): void
    {
        $this->assertNull(ReturnApprovalWorkflowService::refusal(7, 3, [5], true));
    }

    public function test_someone_who_does_not_hold_the_level_may_not_act(): void
    {
        $this->assertStringContainsString('not authorized', ReturnApprovalWorkflowService::refusal(7, 3, [], false));
    }

    public function test_whoever_generated_the_return_may_not_check_or_approve_it(): void
    {
        $this->assertStringContainsString('generated this return', ReturnApprovalWorkflowService::refusal(3, 3, [], true));
    }

    public function test_whoever_checked_the_return_may_not_give_the_final_approval(): void
    {
        $this->assertStringContainsString('already checked', ReturnApprovalWorkflowService::refusal(5, 3, [5], true));
    }
}
