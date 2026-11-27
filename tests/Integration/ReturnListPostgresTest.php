<?php

namespace Tests\Integration;

use App\Models\ReturnSummary;
use App\Http\Controllers\ClientReturnsController;
use App\Exports\ReturnStatementExport;
use App\Exceptions\ReturnApprovalException;
use App\Services\ReturnApprovalWorkflowService;
use App\Services\MiniReturnService;
use App\Services\ReturnStatementService;
use App\Services\UtilityReturnsService;
use App\Services\ReturnListService;
use App\Services\ReturnListContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\PostgresReturnFixtures;
use Tests\TestCase;

class ReturnListPostgresTest extends TestCase
{
    use PostgresReturnFixtures { setUp as fixtureSetUp; }

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        DB::statement('ALTER TABLE cb_returns_summary ADD COLUMN transaction_ref text');
        DB::statement('ALTER TABLE sys_pm_clients ADD COLUMN names text');
        DB::table('sys_pm_clients')->where('id', 1)->update(['names' => 'Test Landlord']);
        DB::statement('CREATE TEMP TABLE cb_approval_items (id bigint, item_type text, item_id bigint, status text, current_level int, total_levels int) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_approval_actions (id bigint, approval_item_id bigint, actor_id bigint, action text, comments text, from_level int, returned_to_level int, action_timestamp timestamp, sequence_number int) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE josya_users (id bigint, name text) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_roles (id bigint, name text) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE approval_workflows (id bigint, workflow_name text, status int) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE workflow_approvers (id bigint, workflow_id bigint, is_active int, approval_level int, approver_id bigint, position_type text, role_id bigint) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_returns_merge_batch_items (batch_id bigint, property_id bigint, landlord_ref text) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE sys_set_zones (id bigint, zone_manager_id bigint) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE sys_user_staff_links (staff_id bigint, user_id bigint) ON COMMIT DROP');
        DB::table('cb_returns_summary')->insert([
            $this->record(1, 'CHILD-1', 'MAINRETURN', 'P1', 100, 0, 'MASTER-1'),
            $this->record(2, 'CHILD-2', 'MAINRETURN', 'P2', 200, 2, 'MASTER-1'),
            $this->record(3, 'MINI-1', 'MINIRETURN', 'P1', 50, 1),
            $this->record(4, 'MASTER-1', 'MASTERRETURN', '', 275, 0),
        ]);
        DB::table('cb_approval_items')->insert(['id' => 20, 'item_type' => 'return_summary', 'item_id' => 2,
            'status' => 'InProgress', 'current_level' => 2, 'total_levels' => 3]);
    }

    private function record(int $id, string $code, string $type, string $property, float $net, int $approved, ?string $parent = null): array
    {
        return ['id' => $id, 'return_code' => $code, 'return_type' => $type, 'property_code' => $property,
            'landlord_code' => 'LAN-OLD', 'year' => 2026, 'month' => 11, 'return_period' => 202611,
            'net_amount' => $net, 'approved_status' => $approved, 'paid_status' => 0, 'parent_return_code' => $parent,
            'is_merged' => $parent !== null];
    }

    private function present(?array $allowed = null, ?array $ids = null): array
    {
        $query = ReturnSummary::query();
        if ($ids !== null) $query->whereIn('id', $ids);
        return app(ReturnListService::class)->present($query->get(), $allowed, 3);
    }

    public function test_components_are_nested_once_and_approval_levels_are_visible_without_double_counting(): void
    {
        $before = DB::table('cb_returns_summary')->orderBy('id')->get()->toJson();
        $result = $this->present();
        $this->assertCount(2, $result['data']);
        $this->assertSame(325.0, array_sum(array_column($result['data'], 'net_amount_raw')));
        $master = collect($result['data'])->firstWhere('group', 'merged');
        $this->assertSame('0 of 2 property returns approved', $master['approval_summary']);
        $this->assertSame('Awaiting property approvals', $master['stage']);
        $this->assertFalse($master['is_draft']);
        $this->assertCount(2, $master['children']);
        $this->assertSame('Draft', $master['children'][0]['stage']);
        $this->assertSame('Awaiting level 2 of 3', $master['children'][1]['stage']);
        $this->assertSame($before, DB::table('cb_returns_summary')->orderBy('id')->get()->toJson(), 'Listing must not change return records.');
    }

    public function test_expansion_does_not_disclose_properties_outside_the_users_scope(): void
    {
        $result = $this->present(['P1'], [1, 3, 4]);
        $master = collect($result['data'])->firstWhere('group', 'merged');
        $this->assertCount(1, $master['children']);
        $this->assertSame('P1', $master['children'][0]['property_code']);
        $this->assertSame(1, $master['hidden_child_count']);
        $this->assertSame(['P1'], $master['property_codes']);
        $this->assertSame(['P1'], array_column($result['properties'], 'code'));
        $this->assertStringNotContainsString('CHILD-2', json_encode($result));
        $this->assertStringNotContainsString('Unchanged', json_encode($result));
    }

    public function test_scope_is_also_enforced_for_individual_rows(): void
    {
        DB::table('cb_returns_summary')->where('id', 2)->update(['return_type' => 'MINIRETURN', 'parent_return_code' => null]);
        $result = $this->present(['P1']);
        $this->assertStringNotContainsString('CHILD-2', json_encode($result));
        $this->assertStringNotContainsString('Unchanged', json_encode($result));
    }

    public function test_generation_filtered_to_a_master_still_loads_its_component_returns(): void
    {
        $result = $this->present(null, [4]);
        $this->assertCount(1, $result['data']);
        $this->assertCount(2, $result['data'][0]['children']);
        $this->assertSame(275.0, $result['data'][0]['net_amount_raw']);
    }

    public function test_an_isolated_linked_component_remains_accessible_but_is_excluded_from_standalone_totals(): void
    {
        $row = $this->present(null, [1])['data'][0];
        $this->assertTrue($row['is_component']);
        $this->assertFalse($row['include_in_total']);
        $this->assertSame('CHILD-1', $row['return_code']);
    }

    public function test_csv_legacy_links_are_used_when_parent_flags_are_missing(): void
    {
        DB::table('cb_returns_summary')->whereIn('id', [1, 2])->update(['parent_return_code' => null, 'is_merged' => false]);
        DB::table('cb_returns_summary')->where('id', 4)->update(['transaction_ref' => 'CHILD-1, CHILD-2']);
        $result = $this->present();
        $this->assertCount(2, $result['data']);
        $master = collect($result['data'])->firstWhere('group', 'merged');
        $this->assertCount(2, $master['children']);
    }

    public function test_latest_return_action_shows_revision_reason_until_an_approval_moves_it_on(): void
    {
        DB::table('cb_approval_actions')->insert([
            ['id' => 1, 'approval_item_id' => 20, 'action' => 'Returned', 'comments' => 'Review commission', 'sequence_number' => 2],
            ['id' => 2, 'approval_item_id' => 20, 'action' => 'Commented', 'comments' => 'Noted', 'sequence_number' => 3],
        ]);
        $master = collect($this->present()['data'])->firstWhere('group', 'merged');
        $this->assertSame('returned', $master['children'][1]['status_key']);
        $this->assertSame('Review commission', $master['children'][1]['status_note']);
        DB::table('cb_approval_actions')->insert(['id' => 3, 'approval_item_id' => 20, 'action' => 'Approved', 'comments' => '', 'sequence_number' => 4]);
        $master = collect($this->present()['data'])->firstWhere('group', 'merged');
        $this->assertSame('awaiting', $master['children'][1]['status_key']);
    }

    public function test_approved_master_reports_child_progress_and_paid_children_remain_in_the_breakdown(): void
    {
        DB::table('cb_returns_summary')->whereIn('id', [1, 2, 4])->update(['approved_status' => 1]);
        DB::table('cb_returns_summary')->where('id', 1)->update(['paid_status' => 1]);
        $master = $this->present(null, [4])['data'][0];
        $this->assertSame('Approved', $master['stage']);
        $this->assertSame('2 of 2 property returns approved', $master['approval_summary']);
        $this->assertSame('Paid', $master['children'][0]['stage']);
    }

    public function test_ambiguous_legacy_membership_is_not_invented_even_when_only_one_master_is_requested(): void
    {
        DB::table('cb_returns_summary')->whereIn('id', [1, 2])->update(['parent_return_code' => null, 'is_merged' => false]);
        DB::table('cb_returns_summary')->insert($this->record(5, 'MASTER-2', 'MASTERRETURN', '', 75, 0));
        $master = $this->present(null, [4])['data'][0];
        $this->assertSame('Needs review', $master['stage']);
        $this->assertEmpty($master['children']);
        $result = $this->present();
        $component = collect($result['data'])->firstWhere('id', 1);
        $this->assertFalse($component['include_in_total']);
        $this->assertSame('Merged membership needs review', $component['membership_note']);
    }

    public function test_list_endpoint_still_loads_historical_approvals_when_workflow_is_not_configured(): void
    {
        Auth::shouldReceive('id')->andReturn(99);
        $workflow = Mockery::mock(ReturnApprovalWorkflowService::class);
        $workflow->shouldReceive('totalLevels')->once()->andThrow(new ReturnApprovalException('Workflow missing'));
        $controller = Mockery::mock(ClientReturnsController::class, [$workflow, app(MiniReturnService::class),
            app(ReturnStatementService::class), app(ReturnStatementExport::class), app(UtilityReturnsService::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('checkGlobalRole')->with(99)->andReturn(true);
        $response = $controller->getBatchesData(Request::create('/ajax/returns/batches/data', 'GET', ['year' => 2026, 'month' => 11]));
        $this->assertSame(200, $response->getStatusCode());
        $result = $response->getData(true);
        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['count']);
        $master = collect($result['data'])->firstWhere('group', 'merged');
        $this->assertSame('Awaiting level 2 of 3', $master['children'][1]['stage']);
        $this->assertSame([1, 2, 3], $result['approval_levels']);
        $this->assertFalse($result['workflow_configured']);
    }

    private function configureWorkflow(): void
    {
        DB::table('approval_workflows')->insert(['id' => 1, 'workflow_name' => 'returns_approval', 'status' => 1]);
        DB::table('workflow_approvers')->insert([
            ['id' => 1, 'workflow_id' => 1, 'is_active' => 1, 'approval_level' => 1, 'approver_id' => 101, 'position_type' => null, 'role_id' => null],
            ['id' => 2, 'workflow_id' => 1, 'is_active' => 1, 'approval_level' => 2, 'approver_id' => null, 'position_type' => 'role', 'role_id' => 5],
            ['id' => 3, 'workflow_id' => 1, 'is_active' => 1, 'approval_level' => 3, 'approver_id' => null, 'position_type' => 'zone_manager', 'role_id' => null],
        ]);
        DB::table('josya_users')->insert([['id' => 101, 'name' => 'First Reviewer'], ['id' => 102, 'name' => 'Zone Reviewer']]);
        DB::table('cb_roles')->insert(['id' => 5, 'name' => 'Finance']);
        DB::table('sys_set_zones')->insert(['id' => 7, 'zone_manager_id' => 9]);
        DB::table('sys_user_staff_links')->insert(['staff_id' => 9, 'user_id' => 102]);
        DB::table('sys_pm_properties')->where('reference_number', 'P2')->update(['zone_id' => 7]);
    }

    public function test_context_shows_all_configured_levels_and_resolves_role_and_zone_approvers(): void
    {
        $this->configureWorkflow();
        $result = app(ReturnListContextService::class)->enrich($this->present(), 3);
        $this->assertSame([1, 2, 3], $result['approval_levels']);
        $master = collect($result['data'])->firstWhere('group', 'merged');
        $this->assertSame('Finance (role)', $master['children'][1]['next_approver']);
        $this->assertNull($master['next_approver']);
        DB::table('cb_approval_items')->where('id', 20)->update(['current_level' => 3]);
        $result = app(ReturnListContextService::class)->enrich($this->present(), 3);
        $master = collect($result['data'])->firstWhere('group', 'merged');
        $this->assertSame('Zone Manager - Zone Reviewer', $master['children'][1]['next_approver']);
        DB::table('cb_approval_items')->where('id', 20)->update(['current_level' => 1]);
        $result = app(ReturnListContextService::class)->enrich($this->present(), 3);
        $master = collect($result['data'])->firstWhere('group', 'merged');
        $this->assertSame('First Reviewer', $master['children'][1]['next_approver']);
    }

    public function test_next_approver_context_does_not_disclose_hidden_components_or_change_returns(): void
    {
        $this->configureWorkflow();
        DB::table('cb_approval_items')->where('id', 20)->update(['current_level' => 3]);
        $before = DB::table('cb_returns_summary')->orderBy('id')->get()->toJson();
        $result = app(ReturnListContextService::class)->enrich($this->present(['P1'], [1, 3, 4]), 3);
        $this->assertSame([1, 2, 3], $result['approval_levels']);
        $this->assertStringNotContainsString('Zone Reviewer', json_encode($result));
        $this->assertStringNotContainsString('CHILD-2', json_encode($result));
        $this->assertSame($before, DB::table('cb_returns_summary')->orderBy('id')->get()->toJson());
    }
}
