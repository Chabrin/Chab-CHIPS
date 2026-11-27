<?php

namespace Tests\Integration;

use App\Exceptions\ClientAccountException;
use App\Exceptions\ReturnApprovalException;
use App\Http\Controllers\PaymentVouchersController;
use App\Http\Controllers\ClientReturnsController;
use App\Exports\ReturnStatementExport;
use App\Models\PaymentVoucherHeader;
use App\Models\ReturnSummary;
use App\Services\MergedReturnGenerationService;
use App\Services\ReturnPostingService;
use App\Services\MiniReturnService;
use App\Services\ReturnApprovalWorkflowService;
use App\Services\ReturnStatementService;
use App\Services\UtilityReturnsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PostgresReturnFixtures;
use Tests\TestCase;

/** All SQL runs in connection-local temporary tables, rolled back after each test. */
class MergedReturnGenerationPostgresTest extends TestCase
{
    use PostgresReturnFixtures { setUp as fixtureSetUp; }

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        DB::statement('ALTER TABLE cb_returns_summary ADD COLUMN transaction_ref text, ADD COLUMN landlord_name text, ADD COLUMN client_id bigint,
            ADD COLUMN zone_id bigint, ADD COLUMN field_officer_id bigint, ADD COLUMN zone_supervisor_id bigint,
            ADD COLUMN zone_manager_id bigint, ADD COLUMN server_time timestamp, ADD COLUMN user_id bigint');
        DB::statement('ALTER TABLE cb_returns_summary ALTER COLUMN voucher_generated TYPE bigint USING voucher_generated::bigint');
        DB::statement('ALTER TABLE cb_returns_clients ALTER COLUMN amount_text TYPE text USING amount_text::text');
        DB::statement('ALTER TABLE sys_pm_clients ADD COLUMN names text');
        DB::statement('ALTER TABLE sys_pm_properties ADD COLUMN active int DEFAULT 1');
        DB::table('sys_pm_clients')->where('id', 1)->update(['names' => 'Test Landlord']);
        DB::statement('CREATE TEMP TABLE cb_returns_merge_batches (id bigint PRIMARY KEY, batch_reference text, landlord_id bigint, landlord_name text, bank_detail_id bigint) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_returns_merge_batch_items (id bigint, batch_id bigint, property_id bigint, landlord_ref text) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_approval_items (id bigint, item_type text, item_id bigint, status text, current_level int, total_levels int) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_payment_vouchers_headers_new (id bigint, voucher_status text, is_deleted int) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_payment_vouchers_lines_new (id bigint, entry_id bigint, entry_number bigint, voucher_type text) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE josya_users (id bigint, name text) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_landlord_account (id bigint, client_id bigint, property_id bigint,
            debit_amount numeric(18,2), credit_amount numeric(18,2), entry_type_code text, entry_particulars text,
            entry_description text, payment_ref text, payment_ref_type text, entry_date timestamp, property_code text,
            is_recovered int DEFAULT 0, return_code text, year int, month int, advance_id bigint, schedule_id bigint,
            recovery_year int, recovery_month int, recovery_server_time timestamp) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE sys_pm_clients_advances_requisitions (id bigint, voucher_id bigint) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE sys_gl_transactions (id bigint, trans_details text) ON COMMIT DROP');
        DB::table('josya_users')->insert(['id' => 99, 'name' => 'Generator']);
        DB::table('cb_returns_merge_batches')->insert(['id' => 1, 'batch_reference' => 'BATCH-1', 'landlord_id' => 1]);
        DB::table('cb_returns_merge_batch_items')->insert([
            ['id' => 1, 'batch_id' => 1, 'property_id' => 1, 'landlord_ref' => 'LAN-OLD'],
            ['id' => 2, 'batch_id' => 1, 'property_id' => 2, 'landlord_ref' => 'LAN-OLD'],
        ]);
        foreach ([1 => 100, 2 => 200] as $property => $net) {
            $this->individual($property, $net);
        }
        DB::table('cb_landlord_account')->insert(['id' => 1, 'client_id' => 1, 'entry_type_code' => '0023',
            'year' => 2026, 'month' => 11, 'debit_amount' => 25, 'entry_particulars' => 'Registration fee']);
    }

    private function individual(int $property, float $net, string $type = 'MAINRETURN'): int
    {
        return DB::table('cb_returns_summary')->insertGetId(['return_code' => ($type === 'UTILITYRETURN' ? 'RU1' : 'R1') . '202611LAN-OLDP' . $property,
            'property_code' => 'P' . $property, 'landlord_code' => 'LAN-OLD', 'client_id' => 1, 'year' => 2026, 'month' => 11,
            'return_period' => 202611, 'return_type' => $type, 'approved_status' => 1, 'paid_status' => 0,
            'voucher_generated' => 0, 'net_amount' => $net, 'net_balance' => $net, 'total_credit' => $net, 'total_debit' => 0,
            'commission_amount' => 0, 'return_id' => $property]);
    }

    private function service(): MergedReturnGenerationService
    {
        return app(MergedReturnGenerationService::class);
    }

    public function test_preview_is_read_only_and_success_uses_approved_amounts_and_recovers_deductions_once(): void
    {
        $state = $this->service()->readiness(1, '202611');
        $this->assertTrue($state['ready']);
        $this->assertSame(275.0, $state['net']);
        $this->assertSame(0, ReturnSummary::where('return_type', 'MASTERRETURN')->count());
        $this->assertNull(DB::table('cb_landlord_account')->value('return_code'));

        $ids = $this->service()->generate(1, '202611', 99, $state['preview_token']);
        $this->assertCount(1, $ids);
        $master = ReturnSummary::findOrFail($ids[0]);
        $this->assertSame(275.0, (float) $master->net_balance);
        $this->assertSame(1, (int) $master->approved_status);
        $this->assertSame(2, ReturnSummary::where('parent_return_code', $master->return_code)->count());
        $this->assertSame(2, DB::table('cb_returns_clients')->where('return_code', $master->return_code)->where('entry_type', 'RETURN NET SUM')->count());
        $this->assertSame(1, (int) DB::table('cb_landlord_account')->value('is_recovered'));
        $this->assertSame(0, DB::table('cb_landlord_account')->where('entry_type_code', '0005')->count(), 'Merging must not repost property credits.');
        $this->assertFalse($this->service()->readiness(1, '202611')['ready']);
        try {
            $this->service()->generate(1, '202611', 99, $state['preview_token']);
            $this->fail('A second merge must be blocked.');
        } catch (ReturnApprovalException $e) {
            $this->assertStringContainsString('already included', $e->getMessage());
        }
        $this->assertSame(1, ReturnSummary::where('return_type', 'MASTERRETURN')->count());
    }

    public static function blockedStates(): array
    {
        return [
            'draft' => [['approved_status' => 0], 'draft'],
            'intermediate approval' => [['approved_status' => 2], 'draft'],
            'paid' => [['paid_status' => 1], 'already paid'],
            'voucher reserved' => [['voucher_generated' => 1], 'payment voucher'],
            'already merged' => [['is_merged' => true], 'already included'],
            'legacy parent link' => [['parent_return_code' => 'LEGACY'], 'already included'],
            'wrong owner' => [['landlord_code' => 'LAN-OTHER'], 'landlord differs'],
        ];
    }

    #[DataProvider('blockedStates')]
    public function test_one_ineligible_member_blocks_the_whole_batch_without_creating_any_master(array $change, string $reason): void
    {
        DB::table('cb_returns_summary')->where('property_code', 'P2')->update($change);
        $state = $this->service()->readiness(1, '202611');
        $this->assertFalse($state['ready']);
        $this->assertStringContainsString($reason, implode(' ', $state['blockers']));
        try {
            $this->service()->generate(1, '202611', 99, $state['preview_token']);
            $this->fail('The batch must not be partially merged.');
        } catch (ReturnApprovalException $e) {
            $this->assertStringContainsString('Cannot generate merged return', $e->getMessage());
        }
        $this->assertSame(0, ReturnSummary::where('return_type', 'MASTERRETURN')->count());
        $this->assertNull(DB::table('cb_landlord_account')->value('return_code'));
    }

    public function test_a_missing_or_wrong_month_return_blocks_the_batch_and_reports_the_property(): void
    {
        DB::table('cb_returns_summary')->where('property_code', 'P2')->update(['return_period' => 202610]);
        $state = $this->service()->readiness(1, '202611');
        $this->assertFalse($state['ready']);
        $this->assertStringContainsString('P2', implode(' ', $state['blockers']));
        $this->assertStringContainsString('requires exactly one main return', implode(' ', $state['blockers']));
    }

    public function test_a_forwarded_return_reports_its_current_level(): void
    {
        $id = ReturnSummary::where('property_code', 'P2')->value('id');
        DB::table('cb_returns_summary')->where('id', $id)->update(['approved_status' => 2]);
        DB::table('cb_approval_items')->insert(['id' => 1, 'item_id' => $id, 'item_type' => 'return_summary', 'status' => 'InProgress', 'current_level' => 2]);
        $this->assertStringContainsString('awaiting approval level 2', implode(' ', $this->service()->readiness(1, '202611')['blockers']));
    }

    public function test_live_voucher_lines_block_merging_even_if_the_summary_flag_is_stale(): void
    {
        $id = ReturnSummary::where('property_code', 'P2')->value('id');
        DB::table('cb_payment_vouchers_headers_new')->insert(['id' => 10, 'voucher_status' => 'Pending', 'is_deleted' => 0]);
        DB::table('cb_payment_vouchers_lines_new')->insert(['id' => 1, 'entry_id' => 10, 'entry_number' => $id, 'voucher_type' => 'client_returns']);
        $this->assertFalse($this->service()->readiness(1, '202611')['ready']);
    }

    public function test_deductions_changed_since_preview_require_another_review(): void
    {
        $state = $this->service()->readiness(1, '202611');
        DB::table('cb_landlord_account')->where('id', 1)->update(['debit_amount' => 30]);
        $this->expectException(ReturnApprovalException::class);
        $this->expectExceptionMessage('Review generation again');
        $this->service()->generate(1, '202611', 99, $state['preview_token']);
    }

    public function test_a_single_utility_return_is_merged_separately_and_must_also_be_approved(): void
    {
        $id = $this->individual(1, 50, 'UTILITYRETURN');
        DB::table('cb_returns_summary')->where('id', $id)->update(['approved_status' => 0]);
        $this->assertFalse($this->service()->readiness(1, '202611')['ready']);
        DB::table('cb_returns_summary')->where('id', $id)->update(['approved_status' => 1]);
        $state = $this->service()->readiness(1, '202611');
        $ids = $this->service()->generate(1, '202611', 99, $state['preview_token']);
        $this->assertCount(2, $ids);
        $utility = ReturnSummary::whereIn('id', $ids)->where('return_code', 'like', 'RU1%')->firstOrFail();
        $this->assertSame(50.0, (float) $utility->net_balance);
        $this->assertSame(1, (int) $utility->approved_status);
        $this->assertSame(1, DB::table('cb_landlord_account')->count());
    }

    public function test_batch_returns_are_not_available_for_individual_vouchers_but_minis_and_standalones_are(): void
    {
        $this->assertSame(0, ReturnSummary::eligibleForVoucher()->count());
        DB::table('cb_returns_summary')->where('property_code', 'P2')->update(['return_type' => 'MINIRETURN']);
        $this->assertSame(1, ReturnSummary::eligibleForVoucher()->count());
        DB::table('cb_returns_merge_batch_items')->where('property_id', 1)->delete();
        $this->assertSame(2, ReturnSummary::eligibleForVoucher()->count());
    }

    public function test_direct_voucher_creation_cannot_bypass_batch_membership(): void
    {
        $return = ReturnSummary::where('property_code', 'P1')->firstOrFail();
        $response = app(PaymentVouchersController::class)->createReturnVoucher(Request::create('/test', 'POST', [
            'return_summary_id' => $return->id, 'landlord_id' => 1, 'vendor_name' => 'Test Landlord', 'vendor_type' => 'Landlord',
            'voucher_date' => '2026-11-26', 'post_year' => 2026, 'post_month' => 11, 'net_balance' => 100,
        ]));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('merged return', $response->getData(true)['message']);
        $this->assertSame(0, DB::table('cb_payment_vouchers_headers_new')->count());
    }

    public function test_an_existing_individual_voucher_cannot_be_paid_after_property_is_batched(): void
    {
        $return = ReturnSummary::where('property_code', 'P1')->firstOrFail();
        $this->expectException(ClientAccountException::class);
        $this->expectExceptionMessage('must be paid through its merged return');
        app(ReturnPostingService::class)->postPayout($return, new PaymentVoucherHeader(), 99);
    }

    public function test_a_settlement_failure_rolls_back_the_master_links_and_reserved_deductions(): void
    {
        $posting = Mockery::mock(ReturnPostingService::class);
        $posting->shouldReceive('finaliseMasterIfReady')->once()->andThrow(new ClientAccountException('Settlement failed'));
        $service = new MergedReturnGenerationService($posting);
        $state = $service->readiness(1, '202611');
        try {
            $service->generate(1, '202611', 99, $state['preview_token']);
            $this->fail('Settlement failure must roll back generation.');
        } catch (ClientAccountException $e) {
            $this->assertSame('Settlement failed', $e->getMessage());
        }
        $this->assertSame(0, ReturnSummary::where('return_type', 'MASTERRETURN')->count());
        $this->assertSame(0, ReturnSummary::whereNotNull('parent_return_code')->count());
        $this->assertNull(DB::table('cb_landlord_account')->value('return_code'));
        $this->assertSame(0, (int) DB::table('cb_landlord_account')->value('is_recovered'));
    }

    public function test_generating_one_batch_does_not_merge_another_batch_of_the_same_landlord(): void
    {
        DB::table('cb_returns_merge_batches')->insert(['id' => 2, 'batch_reference' => 'BATCH-2', 'landlord_id' => 1]);
        DB::table('sys_pm_properties')->insert([
            ['id' => 3, 'reference_number' => 'P3', 'property_name' => 'Third'],
            ['id' => 4, 'reference_number' => 'P4', 'property_name' => 'Fourth'],
        ]);
        DB::table('cb_returns_merge_batch_items')->insert([
            ['id' => 3, 'batch_id' => 2, 'property_id' => 3, 'landlord_ref' => 'LAN-OLD'],
            ['id' => 4, 'batch_id' => 2, 'property_id' => 4, 'landlord_ref' => 'LAN-OLD'],
        ]);
        $this->individual(3, 400);
        $this->individual(4, 500);
        $state = $this->service()->readiness(1, '202611');
        $this->service()->generate(1, '202611', 99, $state['preview_token']);
        $this->assertSame(0, ReturnSummary::whereIn('property_code', ['P3', 'P4'])->whereNotNull('parent_return_code')->count());
        $state = $this->service()->readiness(2, '202611');
        $ids = $this->service()->generate(2, '202611', 99, $state['preview_token']);
        $this->assertSame('R1202611LAN-OLD-2', ReturnSummary::findOrFail($ids[0])->return_code);
        $this->assertSame(900.0, (float) ReturnSummary::findOrFail($ids[0])->net_balance);
    }

    private function controller(): ClientReturnsController
    {
        Auth::shouldReceive('id')->andReturn(99);
        Auth::shouldReceive('check')->andReturn(false);
        $workflow = Mockery::mock(ReturnApprovalWorkflowService::class);
        $workflow->shouldReceive('assertConfigured')->andReturnNull();
        $controller = Mockery::mock(ClientReturnsController::class, [$workflow, app(MiniReturnService::class),
            app(ReturnStatementService::class), app(ReturnStatementExport::class), app(UtilityReturnsService::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('generationRefusal')->andReturnNull()->byDefault();
        $controller->shouldReceive('generationRedirectUrl')->andReturn('/returns/list');
        return $controller;
    }

    public static function individualScopes(): array
    {
        return ['one property in a batch' => [2], 'all outstanding properties' => [null]];
    }

    #[DataProvider('individualScopes')]
    public function test_individual_generation_preserves_approved_partners_and_does_not_create_a_master(?int $propertyId): void
    {
        DB::table('sys_pm_properties')->update(['active' => 1]);
        DB::table('cb_returns_summary')->where('property_code', 'P2')->update(['approved_status' => 0]);
        $approved = ReturnSummary::where('property_code', 'P1')->firstOrFail()->getAttributes();
        $controller = $this->controller();
        $controller->shouldReceive('countApprovedInvoices')->once()->with('2026-11-01', 2)->andReturn(1);
        $controller->shouldReceive('deleteUnapprovedReturnsForPeriod')->once()->with('202611', 2)->andReturn([]);
        $controller->shouldReceive('getNextReturnIdBase')->andReturn(100);
        $controller->shouldReceive('insertRentLines')->once()->withArgs(fn (...$args) => $args[7] === 2)->andReturn(1);
        $controller->shouldReceive('updatePayThresholds')->once()->with('202611', '2026-11-01', '2026-10-01', 2);
        foreach (['insertAdvancePaymentLines', 'insertRecoveryLines', 'insertCollectionLines', 'insertCommissionLines'] as $method) {
            $controller->shouldReceive($method)->andReturn(0);
        }
        $controller->shouldReceive('insertMainReturnSummariesLegacy')->once()->andReturn(1);
        $controller->shouldReceive('markLandlordAccountRecovered')->once();
        $controller->shouldReceive('linkMoneyRecoveriesToReturns')->once();
        $response = $controller->processGenerate(Request::create('/returns/generate', 'POST', [
            'return_type' => 'main', 'returns_month' => '2026-11', 'property_id' => $propertyId,
        ]));
        $this->assertSame(200, $response->getStatusCode(), json_encode($response->getData(true)));
        $this->assertSame(0, $response->getData(true)['master_count']);
        $this->assertSame($approved, ReturnSummary::where('property_code', 'P1')->firstOrFail()->getAttributes());
        $this->assertSame(0, ReturnSummary::where('return_type', 'MASTERRETURN')->count());
    }

    public function test_direct_merged_generation_rechecks_approval_after_the_preview(): void
    {
        $state = $this->service()->readiness(1, '202611');
        DB::table('cb_returns_summary')->where('property_code', 'P2')->update(['approved_status' => 2]);
        $response = $this->controller()->processGenerate(Request::create('/returns/generate', 'POST', [
            'return_type' => 'merged', 'returns_month' => '2026-11', 'property_id' => 1, 'preview_token' => $state['preview_token'],
        ]));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Cannot generate merged return', $response->getData(true)['message']);
        $this->assertSame(0, ReturnSummary::where('return_type', 'MASTERRETURN')->count());
    }

    public function test_merged_generation_checks_access_to_every_batch_member(): void
    {
        $state = $this->service()->readiness(1, '202611');
        $controller = $this->controller();
        $controller->shouldReceive('generationRefusal')->with(2)->andReturn('Outside your zone');
        $response = $controller->processGenerate(Request::create('/returns/generate', 'POST', [
            'return_type' => 'merged', 'returns_month' => '2026-11', 'property_id' => 1, 'preview_token' => $state['preview_token'],
        ]));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('access to every property', $response->getData(true)['message']);
        $this->assertSame(0, ReturnSummary::where('return_type', 'MASTERRETURN')->count());
    }

    public static function legacyMasters(): array
    {
        return ['CSV child references' => ['R1202611LAN-OLDP1,R1202611LAN-OLDP2'], 'unknown migrated membership' => [null]];
    }

    #[DataProvider('legacyMasters')]
    public function test_existing_masters_without_parent_flags_are_preserved_and_block_duplicate_merging(?string $codes): void
    {
        DB::table('cb_returns_summary')->insert(['return_code' => 'LEGACY-MASTER', 'landlord_code' => 'LAN-OLD',
            'return_period' => 202611, 'return_type' => 'MASTERRETURN', 'transaction_ref' => $codes, 'approved_status' => 0]);
        $state = $this->service()->readiness(1, '202611');
        $this->assertFalse($state['ready']);
        $this->assertStringContainsString('LEGACY-MASTER must be reviewed', implode(' ', $state['blockers']));
        $this->assertSame(1, ReturnSummary::where('return_type', 'MASTERRETURN')->count());
    }
}
