<?php

namespace Tests\Integration;

use App\Exceptions\ClientAccountException;
use App\Exceptions\ReturnApprovalException;
use App\Http\Controllers\ReturnsApprovalController;
use App\Http\Controllers\ClientReturnsController;
use App\Models\ReturnSummary;
use App\Services\DirectReturnApprovalService;
use App\Services\ReturnAccessService;
use App\Services\ReturnApprovalWorkflowService;
use App\Services\ReturnPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\PostgresReturnFixtures;
use Tests\TestCase;

/** Permission checks and approval writes run only in connection-local temporary tables. */
class ReturnPermissionsPostgresTest extends TestCase
{
    use PostgresReturnFixtures { setUp as fixtureSetUp; }

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        DB::statement('CREATE TEMP TABLE cb_roles (id bigint, name text, is_global boolean) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_role_user (user_id bigint, role_id bigint) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE sys_user_staff_links (user_id bigint, staff_id bigint) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE sys_set_zones (id bigint, zone_manager_id bigint) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE josya_users (id bigint, name text, email text) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE approval_workflows (id bigint, workflow_name text, status int) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE workflow_approvers (id bigserial, workflow_id bigint, approval_level int, approver_id bigint, position_type text, role_id bigint, is_active int, is_final int DEFAULT 0, added_on timestamp) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE workflow_approvers_changes_log (workflow_id bigint, action text, changed_on timestamp, details text) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_approval_items (id bigserial PRIMARY KEY, item_type text, item_id bigint, item_reference text, status text,
            current_level int, total_levels int, initiated_by bigint, initiated_at timestamp, approved_by bigint, approved_at timestamp, created_at timestamp, updated_at timestamp) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_item_approvers (id bigserial PRIMARY KEY, approval_item_id bigint, approver_id bigint, approver_level int,
            is_optional int, approval_status text, assigned_at timestamp, actioned_at timestamp, created_at timestamp, updated_at timestamp) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_approval_actions (id bigserial PRIMARY KEY, approval_item_id bigint, item_approver_id bigint, actor_id bigint,
            action text, from_level int, to_level int, comments text, action_timestamp timestamp, sequence_number int, is_current int, ip_address text,
            created_at timestamp, updated_at timestamp) ON COMMIT DROP');
        foreach ([1 => 'directors', 2 => 'accountant', 3 => 'field_officer', 4 => 'property_manager', 5 => 'zone_manager', 6 => 'internal_auditor', 7 => 'assistant_accountant'] as $id => $name) {
            DB::table('cb_roles')->insert(['id' => $id, 'name' => $name, 'is_global' => in_array($id, [1, 2, 4])]);
            DB::table('cb_role_user')->insert(['user_id' => $id, 'role_id' => $id]);
            DB::table('josya_users')->insert(['id' => $id, 'name' => 'Actor ' . $id]);
        }
        DB::table('sys_user_staff_links')->insert([['user_id' => 3, 'staff_id' => 30], ['user_id' => 5, 'staff_id' => 50]]);
        DB::table('sys_pm_properties')->where('id', 1)->update(['field_officer_id' => 30, 'zone_id' => 10]);
        DB::table('sys_set_zones')->insert(['id' => 10, 'zone_manager_id' => 50]);
        DB::table('approval_workflows')->insert(['id' => 1, 'workflow_name' => 'returns_approval', 'status' => 1]);
        DB::table('workflow_approvers')->insert([
            ['id' => 1, 'workflow_id' => 1, 'approval_level' => 1, 'approver_id' => null, 'position_type' => 'zone_manager', 'role_id' => null, 'is_active' => 1],
            ['id' => 2, 'workflow_id' => 1, 'approval_level' => 2, 'approver_id' => null, 'position_type' => 'role', 'role_id' => 6, 'is_active' => 1],
            ['id' => 3, 'workflow_id' => 1, 'approval_level' => 3, 'approver_id' => null, 'position_type' => 'role', 'role_id' => 1, 'is_active' => 1],
        ]);
        DB::statement("SELECT setval(pg_get_serial_sequence('workflow_approvers', 'id'), 3)");
        DB::table('cb_returns_summary')->insert(['id' => 1, 'return_code' => 'RETURN-1', 'return_type' => 'MAINRETURN', 'property_code' => 'P1',
            'landlord_code' => 'LAN-OLD', 'return_period' => 202611, 'year' => 2026, 'month' => 11, 'approved_status' => 0, 'paid_status' => 0, 'voucher_generated' => '0']);
    }

    private function directService(bool $failPosting = false): DirectReturnApprovalService
    {
        $posting = Mockery::mock(ReturnPostingService::class);
        if ($failPosting) $posting->shouldReceive('postApproval')->once()->andThrow(new ClientAccountException('Accounting setup incomplete'));
        else {
            $posting->shouldReceive('postApproval')->once()->withArgs(fn ($r, $user) => $r->id === 1 && (int) $r->approved_status === 1 && in_array($user, [1, 2]));
            $posting->shouldReceive('finaliseMasterIfReady')->once();
        }
        return new DirectReturnApprovalService(app(ReturnAccessService::class), app(ReturnApprovalWorkflowService::class), $posting);
    }

    public function test_only_directors_chief_accountant_and_assigned_field_officer_can_generate(): void
    {
        $access = app(ReturnAccessService::class);
        $this->assertNull($access->generationRefusal(1, null));
        $this->assertNull($access->generationRefusal(2, null));
        $this->assertNull($access->generationRefusal(3, 1));
        $this->assertNotNull($access->generationRefusal(3, null));
        $this->assertNotNull($access->generationRefusal(3, 2));
        foreach ([4, 5, 6, 7] as $user) $this->assertNotNull($access->generationRefusal($user, 1));
        // An unrelated global role must not expand a field officer's property scope.
        DB::table('cb_role_user')->insert(['user_id' => 3, 'role_id' => 4]);
        $this->assertNotNull($access->generationRefusal(3, 2));
    }

    public function test_only_field_officers_can_forward_and_assistant_accountants_cannot_approve_directly(): void
    {
        $access = app(ReturnAccessService::class);
        $this->assertTrue($access->canForward(3));
        foreach ([1, 2, 4, 5, 6, 7] as $user) $this->assertFalse($access->canForward($user));
        foreach ([3, 4, 5, 6, 7] as $user) $this->assertFalse($access->canApproveDirectly($user));
    }

    public function test_director_can_approve_a_draft_without_forwarding_and_audits_only_the_final_action(): void
    {
        $this->directService()->approve(1, 1, 'Reviewed directly', '127.0.0.1');
        $this->assertSame(1, (int) ReturnSummary::findOrFail(1)->approved_status);
        $this->assertSame('Actor 1', ReturnSummary::findOrFail(1)->approved_by);
        $this->assertSame('Approved', DB::table('cb_approval_items')->value('status'));
        $this->assertSame(1, DB::table('cb_approval_actions')->count());
        $this->assertStringContainsString('Direct final approval', DB::table('cb_approval_actions')->value('comments'));
        $this->assertSame([3], DB::table('cb_item_approvers')->pluck('approver_level')->all());
    }

    public function test_chief_accountant_can_give_direct_final_approval_after_an_earlier_review(): void
    {
        $item = $this->item(2);
        DB::table('cb_item_approvers')->insert(['approval_item_id' => $item, 'approver_id' => 2, 'approver_level' => 1, 'approval_status' => 'Approved']);
        $this->directService()->approve(1, 2, null, null);
        $this->assertSame(2, (int) DB::table('cb_approval_items')->value('approved_by'));
        $this->assertSame(2, DB::table('cb_item_approvers')->count());
    }

    public function test_failed_posting_rolls_back_the_direct_approval_and_all_audit_records(): void
    {
        try {
            $this->directService(true)->approve(1, 1, null, null);
            $this->fail('Expected posting failure');
        } catch (ClientAccountException $e) {
            $this->assertSame(0, (int) ReturnSummary::findOrFail(1)->approved_status);
            $this->assertSame(0, DB::table('cb_approval_items')->count());
            $this->assertSame(0, DB::table('cb_approval_actions')->count());
        }
    }

    public function test_a_global_property_manager_cannot_bypass_the_direct_approval_service(): void
    {
        $posting = Mockery::mock(ReturnPostingService::class);
        $posting->shouldNotReceive('postApproval');
        $service = new DirectReturnApprovalService(app(ReturnAccessService::class), app(ReturnApprovalWorkflowService::class), $posting);
        $this->expectException(ReturnApprovalException::class);
        $service->approve(1, 4, null, null);
    }

    public function test_direct_approval_cannot_approve_a_master_or_an_already_approved_return(): void
    {
        $posting = Mockery::mock(ReturnPostingService::class);
        $posting->shouldNotReceive('postApproval');
        $service = new DirectReturnApprovalService(app(ReturnAccessService::class), app(ReturnApprovalWorkflowService::class), $posting);
        foreach ([['return_type' => 'MASTERRETURN'], ['return_type' => 'MAINRETURN', 'approved_status' => 1],
            ['approved_status' => 0, 'paid_status' => 1], ['paid_status' => 0, 'voucher_generated' => '1']] as $changes) {
            DB::table('cb_returns_summary')->where('id', 1)->update($changes);
            try { $service->approve(1, 1, null, null); $this->fail('Invalid return was approved'); }
            catch (ReturnApprovalException $e) { $this->assertSame(0, DB::table('cb_approval_actions')->count()); }
        }
    }

    public function test_repeated_direct_approval_never_posts_twice(): void
    {
        $service = $this->directService();
        $service->approve(1, 1, null, null);
        $this->expectException(ReturnApprovalException::class);
        $service->approve(1, 1, null, null);
    }

    public function test_normal_review_is_zone_manager_then_internal_auditor_and_field_officer_cannot_finalize(): void
    {
        $id = $this->item(1);
        $workflow = app(ReturnApprovalWorkflowService::class);
        $item = DB::table('cb_approval_items')->where('id', $id)->first();
        $this->assertNull($workflow->actorRefusal($item, 1, 5));
        $this->assertNotNull($workflow->actorRefusal($item, 1, 6));
        $this->assertNull($workflow->actorRefusal($item, 2, 6));
        $this->assertNotNull($workflow->actorRefusal($item, 2, 3));
        $this->assertNotNull($workflow->actorRefusal($item, 3, 3));
    }

    public function test_director_sees_a_direct_approval_action_on_an_unforwarded_draft(): void
    {
        Auth::shouldReceive('id')->andReturn(1);
        $controller = new ReturnsApprovalController(app(ReturnApprovalWorkflowService::class), Mockery::mock(ReturnPostingService::class));
        $result = $controller->getReturnApprovals(encrypt(1))->getData(true);
        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['can_approve']);
        $this->assertTrue($result['data']['direct_approval']);
        $this->assertSame('Approve directly', $result['data']['action_label']);
    }

    public function test_direct_approval_timeline_marks_untouched_reviews_as_skipped_and_keeps_the_audit_note(): void
    {
        $this->directService()->approve(1, 1, 'Checked directly', null);
        Auth::shouldReceive('id')->andReturn(1);
        $controller = new ReturnsApprovalController(app(ReturnApprovalWorkflowService::class), Mockery::mock(ReturnPostingService::class));
        $result = $controller->getReturnApprovals(encrypt(1))->getData(true);
        $this->assertTrue($result['success'], json_encode($result));
        $this->assertSame(['Skipped', 'Skipped', 'Approved'], array_column($result['data']['timeline'], 'status'));
        $this->assertStringContainsString('Direct final approval', $result['data']['timeline'][2]['actions'][0]['comments']);
        $this->assertFalse($result['data']['can_approve']);
    }

    private function item(int $level): int
    {
        return DB::table('cb_approval_items')->insertGetId(['item_type' => 'return_summary', 'item_id' => 1, 'item_reference' => 'RETURN-1',
            'status' => 'InProgress', 'current_level' => $level, 'total_levels' => 3, 'initiated_by' => 3, 'initiated_at' => now()]);
    }

    public function test_workflow_setup_is_explicit_repeatable_and_preserves_return_records(): void
    {
        $before = DB::table('cb_returns_summary')->orderBy('id')->get()->toJson();
        $this->artisan('returns:configure-approval-policy')->assertExitCode(0);
        $this->assertSame(0, DB::table('workflow_approvers_changes_log')->count());
        $this->artisan('returns:configure-approval-policy', ['--apply' => true])->assertExitCode(0);
        $this->assertSame([1 => ['zone_manager'], 2 => ['role:6'], 3 => ['role:1', 'role:2']], app(ReturnApprovalWorkflowService::class)->levels());
        $this->artisan('returns:configure-approval-policy', ['--apply' => true])->assertExitCode(0);
        $this->assertSame(1, DB::table('workflow_approvers_changes_log')->count());
        $this->assertSame($before, DB::table('cb_returns_summary')->orderBy('id')->get()->toJson());
        $this->assertSame(0, DB::table('cb_approval_actions')->count());
    }

    public function test_non_privileged_users_cannot_use_legacy_batch_approval_as_a_bypass(): void
    {
        Auth::shouldReceive('id')->andReturn(3);
        $controller = new ReturnsApprovalController(app(ReturnApprovalWorkflowService::class), Mockery::mock(ReturnPostingService::class));
        $response = $controller->approveBatch(encrypt(1), Request::create('/unused', 'POST'));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(0, (int) ReturnSummary::findOrFail(1)->approved_status);
    }

    public function test_server_blocks_generation_and_forwarding_for_a_global_property_manager(): void
    {
        Auth::shouldReceive('id')->andReturn(4);
        $controller = app(ClientReturnsController::class);
        $response = $controller->processGenerate(Request::create('/returns/generate', 'POST', [
            'returns_month' => '2026-11', 'return_type' => 'main', 'property_id' => 1,
        ]));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(403, $controller->forwardReturn(encrypt(1))->getStatusCode());
        $this->assertSame(403, $controller->forwardReturns(Request::create('/unused', 'POST', ['ids' => [encrypt(1)]]))->getStatusCode());
        $this->assertSame(0, DB::table('cb_approval_items')->count());
    }

    public function test_zone_manager_and_auditor_reviews_advance_one_level_and_cannot_finalize(): void
    {
        $currentUser = 5;
        Auth::shouldReceive('id')->andReturnUsing(function () use (&$currentUser) { return $currentUser; });
        Auth::shouldReceive('user')->andReturnUsing(function () use (&$currentUser) { return (object) ['name' => 'Actor ' . $currentUser]; });
        $item = $this->item(1);
        $posting = Mockery::mock(ReturnPostingService::class);
        $posting->shouldNotReceive('postApproval');
        $controller = new ReturnsApprovalController(app(ReturnApprovalWorkflowService::class), $posting);
        $response = $controller->approveReturn(encrypt(1), Request::create('/unused', 'POST'));
        $this->assertSame(200, $response->getStatusCode(), json_encode($response->getData(true)));
        $this->assertSame(2, DB::table('cb_approval_items')->where('id', $item)->value('current_level'));
        $currentUser = 6;
        $response = $controller->approveReturn(encrypt(1), Request::create('/unused', 'POST'));
        $this->assertSame(200, $response->getStatusCode(), json_encode($response->getData(true)));
        $this->assertSame(3, DB::table('cb_approval_items')->where('id', $item)->value('current_level'));
        $this->assertSame(2, (int) ReturnSummary::findOrFail(1)->approved_status);
        $currentUser = 3;
        $response = $controller->approveReturn(encrypt(1), Request::create('/unused', 'POST'));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(2, DB::table('cb_approval_actions')->count());
    }
}
