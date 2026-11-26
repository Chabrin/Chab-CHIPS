<?php

namespace Tests\Integration;

use App\Traits\ReturnsRentLinesTrait;
use App\Traits\ReturnsSummaryTrait;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PostgresReturnFixtures;
use Tests\TestCase;

class LegacyServiceChargeReturnsPostgresTest extends TestCase
{
    use PostgresReturnFixtures;

    private function seedService(float $balance = 1000, ?int $itemId = null): void
    {
        DB::table('sys_set_invoiceable_items')->insert(['id' => 2, 'item_name' => 'Service Charge', 'advance_payable' => 'Yes']);
        DB::table('sys_pm_debtors_invoices')->insert([
            'id' => 4, 'property_id' => 2, 'unit_id' => 2, 'tenant_id' => 10, 'lease_id' => 2,
            'client_id' => 1, 'item_type_id' => $itemId, 'return_type_id' => null,
            'narration' => 'Service Charge FOR December 2026', 'created_by' => null,
            'debtor_type_id' => '2', 'current_status_id' => 1, 'returns_period' => '2026-12-01',
            'total_amount' => 1000, 'current_balance' => $balance, 'reference_number' => 'LEGACY-DEC-SERVICE',
        ]);
    }

    private function generator(): object
    {
        return new class
        {
            use ReturnsRentLinesTrait, ReturnsSummaryTrait;

            public function run(string $period = '202612', int $sequence = 1): void
            {
                $date = CarbonImmutable::createFromFormat('!Ym', $period);
                $this->insertRentLines($period, $date->toDateString(), $date->format('d-M-Y'), $this->getNextReturnIdBase(), 1, $date->format('F'), $date->year, 2, $sequence);
                $this->insertMainReturnSummariesLegacy($period, $date->year, $date->month, $date->format('F'), 'Test', 2, $sequence === 1 ? 'MAINRETURN' : 'MINIRETURN');
            }

            public function regenerate(): void
            {
                $this->deleteUnapprovedReturnsForPeriod('202612', 2);
                $this->run();
            }
        };
    }

    private function cleanupTables(): void
    {
        DB::statement('CREATE TEMP TABLE cb_approval_items (id bigint, item_id bigint, item_type text) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_approval_actions (approval_item_id bigint) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_item_approvers (approval_item_id bigint) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE cb_landlord_account (id bigint, return_code text, is_recovered integer, recovery_year integer, recovery_month integer, recovery_server_time timestamp) ON COMMIT DROP');
    }

    public static function balances(): array
    {
        return ['unpaid' => [1000], 'partly settled' => [600], 'cleared' => [0]];
    }

    #[DataProvider('balances')]
    public function test_legacy_service_is_returned_in_full_regardless_of_collection(float $balance): void
    {
        $this->seedService($balance);
        $invoice = (array) DB::table('sys_pm_debtors_invoices')->where('id', 4)->first();
        $this->generator()->run();
        $line = DB::table('cb_returns_clients')->where('invoice_id', 4)->sole();
        $this->assertSame(1000.0, (float) $line->net_amount);
        $this->assertSame(0.0, (float) $line->commission_amount);
        $this->assertSame(0.0, (float) $line->vat_amount);
        $this->assertSame($invoice, (array) DB::table('sys_pm_debtors_invoices')->where('id', 4)->first());
    }

    public function test_legacy_service_with_a_catalogue_link_and_no_creator_is_eligible(): void
    {
        $this->seedService(1000, 2);
        $this->generator()->run();
        $this->assertSame(1000.0, (float) DB::table('cb_returns_clients')->where('invoice_id', 4)->value('net_amount'));
    }

    public function test_no_setting_and_ambiguous_fallback_do_not_guess_advance_payability(): void
    {
        $this->seedService(600);
        DB::table('sys_set_invoiceable_items')->insert(['id' => 3, 'item_name' => 'Service Charges', 'advance_payable' => 'Yes']);
        $this->generator()->run();
        $this->assertSame(400.0, (float) DB::table('cb_returns_clients')->where('invoice_id', 4)->value('net_amount'));
    }

    public function test_a_service_marked_no_and_other_legacy_bills_keep_collection_rules(): void
    {
        $this->seedService(600);
        DB::table('sys_set_invoiceable_items')->where('id', 2)->update(['advance_payable' => 'No']);
        $this->generator()->run();
        $this->assertSame(400.0, (float) DB::table('cb_returns_clients')->where('invoice_id', 4)->value('net_amount'));
    }

    public function test_legacy_water_is_not_made_advance_payable_by_the_service_setting(): void
    {
        $this->seedService();
        DB::table('sys_pm_debtors_invoices')->where('id', 4)->update(['narration' => 'Water Bill FOR December 2026']);
        $this->generator()->run();
        $this->assertSame(0.0, (float) DB::table('cb_returns_clients')->where('invoice_id', 4)->value('net_amount'));
    }

    public static function historyLinks(): array
    {
        return ['invoice link' => [4, 202611, ''], 'original month without link' => [null, 202612, ''], 'invoice reference without link' => [null, 202701, 'LEGACY-DEC-SERVICE']];
    }

    #[DataProvider('historyLinks')]
    public function test_previous_service_amount_is_subtracted_even_without_an_invoice_link(?int $invoiceId, int $period, string $reference): void
    {
        $this->seedService();
        DB::table('cb_returns_clients')->insert([
            'invoice_id' => $invoiceId, 'property_id' => 2, 'unit_id' => 2, 'tenant_id' => 10,
            'return_code' => 'HISTORICAL-SERVICE', 'return_period' => $period,
            'transaction_ref' => $reference, 'entry_type' => 'Service Charge', 'dr_cr' => 'CR', 'net_amount' => 400,
        ]);
        $history = (array) DB::table('cb_returns_clients')->where('return_code', 'HISTORICAL-SERVICE')->first();
        $this->generator()->run();
        $this->assertSame(600.0, (float) DB::table('cb_returns_clients')->where('return_code', 'R1202612LAN-OLDP2')->where('invoice_id', 4)->value('net_amount'));
        $this->assertSame($history, (array) DB::table('cb_returns_clients')->where('return_code', 'HISTORICAL-SERVICE')->first());
    }

    public function test_another_month_and_another_property_are_not_mistaken_for_this_charge(): void
    {
        $this->seedService();
        DB::table('cb_returns_clients')->insert([
            ['invoice_id' => null, 'property_id' => 2, 'unit_id' => 2, 'tenant_id' => 10, 'return_code' => 'NOV-SERVICE', 'return_period' => 202611, 'entry_type' => 'Service Charge', 'dr_cr' => 'CR', 'net_amount' => 1000],
            ['invoice_id' => 4, 'property_id' => 1, 'unit_id' => 1, 'tenant_id' => 10, 'return_code' => 'OTHER-PROPERTY', 'return_period' => 202612, 'entry_type' => 'Service Charge', 'dr_cr' => 'CR', 'net_amount' => 1000],
        ]);
        $this->generator()->run();
        $this->assertSame(1000.0, (float) DB::table('cb_returns_clients')->where('return_code', 'R1202612LAN-OLDP2')->where('invoice_id', 4)->value('net_amount'));
    }

    public function test_a_fully_returned_legacy_charge_is_not_paid_again(): void
    {
        $this->seedService();
        DB::table('cb_returns_clients')->insert([
            'property_id' => 2, 'unit_id' => 2, 'tenant_id' => 10, 'return_code' => 'OLD-UTILITY',
            'return_period' => 202612, 'entry_type' => 'Service Charge', 'dr_cr' => 'CR', 'net_amount' => 1000,
        ]);
        $this->generator()->run();
        $this->assertSame(0.0, (float) DB::table('cb_returns_clients')->where('invoice_id', 4)->value('net_amount'));
        $this->assertSame(1000.0, (float) DB::table('cb_returns_clients')->where('property_id', 2)->where('entry_type', 'Service Charge')->sum('net_amount'));
    }

    public function test_draft_regeneration_and_later_collection_do_not_duplicate_the_advance(): void
    {
        $this->seedService();
        $this->cleanupTables();
        $generator = $this->generator();
        $generator->run();
        $generator->regenerate();
        $this->assertSame(1, DB::table('cb_returns_clients')->where('invoice_id', 4)->count());
        $this->assertSame(1000.0, (float) DB::table('cb_returns_clients')->where('invoice_id', 4)->sum('net_amount'));
        DB::table('sys_pm_debtors_invoices')->where('id', 4)->update(['current_balance' => 0]);
        $generator->run('202701', 2);
        $this->assertSame(1, DB::table('cb_returns_clients')->where('invoice_id', 4)->count());
        $this->assertSame(1000.0, (float) DB::table('cb_returns_clients')->where('invoice_id', 4)->sum('net_amount'));
    }

    public function test_separate_utility_mode_carries_the_same_advance_once(): void
    {
        $this->seedService();
        DB::table('sys_pm_clients')->where('id', 1)->update(['utility_returns' => 'separate_return']);
        $this->generator()->run();
        $line = DB::table('cb_returns_clients')->where('invoice_id', 4)->sole();
        $this->assertSame('RU1202612LAN-OLDP2', $line->return_code);
        $this->assertSame(1000.0, (float) $line->net_amount);
        $this->assertSame('UTILITYRETURN', DB::table('cb_returns_summary')->where('return_code', $line->return_code)->value('return_type'));
    }

    public static function protectedReturns(): array
    {
        return ['approved property' => [1, 0, false], 'paid property' => [0, 1, false], 'approved merged parent' => [1, 0, true], 'paid merged parent' => [0, 1, true]];
    }

    #[DataProvider('protectedReturns')]
    public function test_processed_property_and_merged_returns_cannot_be_rewritten(int $approved, int $paid, bool $parent): void
    {
        $this->seedService();
        $generator = $this->generator();
        $generator->run();
        if ($parent) {
            DB::table('cb_returns_summary')->insert(['return_code' => 'MASTER-PROTECTED', 'return_period' => 202612, 'approved_status' => $approved, 'paid_status' => $paid]);
            DB::table('cb_returns_summary')->where('property_code', 'P2')->update(['parent_return_code' => 'MASTER-PROTECTED']);
        } else {
            DB::table('cb_returns_summary')->where('property_code', 'P2')->update(['approved_status' => $approved, 'paid_status' => $paid]);
        }
        $before = [DB::table('cb_returns_summary')->orderBy('id')->get()->toJson(), DB::table('cb_returns_clients')->orderBy('id')->get()->toJson()];
        try {
            $generator->regenerate();
            $this->fail('A processed return was regenerated.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Approved or paid', $exception->getMessage());
        }
        $this->assertSame($before, [DB::table('cb_returns_summary')->orderBy('id')->get()->toJson(), DB::table('cb_returns_clients')->orderBy('id')->get()->toJson()]);
    }

    public function test_duplicate_legacy_invoices_require_reconciliation_before_generation(): void
    {
        $this->seedService();
        $copy = (array) DB::table('sys_pm_debtors_invoices')->where('id', 4)->first();
        $copy['id'] = 5;
        DB::table('sys_pm_debtors_invoices')->insert($copy);
        $before = DB::table('cb_returns_clients')->get()->toJson();
        try {
            $this->generator()->run();
            $this->fail('Duplicate service invoices were paid.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Duplicate legacy service-charge invoices', $exception->getMessage());
        }
        $this->assertSame($before, DB::table('cb_returns_clients')->get()->toJson());
    }
}
