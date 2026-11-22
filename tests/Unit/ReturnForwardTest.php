<?php

namespace Tests\Unit;

use App\Services\ReturnApprovalWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReturnForwardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cb_approval_items', function ($table) {
            $table->increments('id');
            $table->string('item_type')->nullable();
            $table->integer('item_id')->nullable();
        });
    }

    private function row(array $over = []): object
    {
        return (object) array_merge(['id' => 11, 'return_type' => 'MAINRETURN', 'property_code' => '594A', 'approved_status' => 0, 'paid_status' => 0], $over);
    }

    public function test_a_draft_property_return_can_be_forwarded(): void
    {
        $this->assertNull((new ReturnApprovalWorkflowService())->forwardRefusal($this->row()));
    }

    public function test_a_return_that_was_already_forwarded_is_refused(): void
    {
        DB::table('cb_approval_items')->insert(['item_type' => 'return_summary', 'item_id' => 11]);

        $service = new ReturnApprovalWorkflowService();
        $this->assertTrue($service->isForwarded(11));
        $this->assertStringContainsString('already been forwarded', $service->forwardRefusal($this->row()));
        // another return, or another kind of item with the same id, does not count
        DB::table('cb_approval_items')->insert(['item_type' => 'voucher', 'item_id' => 12]);
        $this->assertNull($service->forwardRefusal($this->row(['id' => 12])));
    }

    public function test_approved_or_paid_returns_are_not_forwarded(): void
    {
        $service = new ReturnApprovalWorkflowService();

        $this->assertStringContainsString('already approved', $service->forwardRefusal($this->row(['approved_status' => 1])));
        $this->assertStringContainsString('already approved', $service->forwardRefusal($this->row(['paid_status' => 1])));
    }

    public function test_a_merged_return_is_not_forwarded_because_it_follows_its_property_returns(): void
    {
        $service = new ReturnApprovalWorkflowService();

        $this->assertStringContainsString('merged return', $service->forwardRefusal($this->row(['return_type' => 'MASTERRETURN'])));
        $this->assertStringContainsString('merged return', $service->forwardRefusal($this->row(['property_code' => null])));
    }

    public function test_the_levels_before_the_last_verify_and_the_last_approves(): void
    {
        $this->assertSame('Verify', ReturnApprovalWorkflowService::labelFor(1, 2));
        $this->assertSame('Approve', ReturnApprovalWorkflowService::labelFor(2, 2));
        $this->assertSame('Verify', ReturnApprovalWorkflowService::labelFor(2, 3));
        // a workflow with one level has only the approval
        $this->assertSame('Approve', ReturnApprovalWorkflowService::labelFor(1, 1));
    }
}
