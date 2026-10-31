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
}
