<?php

namespace Tests\Integration;

use App\Models\DebtorInvoice;
use App\Models\Deposit;
use App\Models\InvoiceableItem;
use App\Models\Property;
use App\Models\ReturnClient;
use App\Models\ReturnSummary;
use App\Models\ServiceAgreement;
use App\Models\TenantRecord;
use App\Models\Unit;
use App\Services\MiniReturnService;
use App\Services\ReturnApprovalWorkflowService;
use App\Traits\ReturnsRentLinesTrait;
use App\Traits\ReturnsSummaryTrait;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Opt-in native SQL verification. All tables and sequences exist only in this connection's temporary schema. */
class PropertyOwnershipReturnsPostgresTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PROPERTY_TRANSFER_TEST_PG') !== '1') {
            $this->markTestSkipped('Set PROPERTY_TRANSFER_TEST_PG=1 to test native SQL in isolated local PostgreSQL temporary tables.');
        }
        $env = Dotenv::parse(file_get_contents(base_path('.env')));
        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $this->assertContains($host, ['127.0.0.1', 'localhost'], 'This harness only connects to local PostgreSQL.');
        config(['database.default' => 'ownership_pg', 'database.connections.ownership_pg' => [
            'driver' => 'pgsql', 'host' => $host, 'port' => $env['DB_PORT'] ?? 5432,
            'database' => $env['DB_DATABASE'], 'username' => $env['DB_USERNAME'], 'password' => $env['DB_PASSWORD'] ?? '',
            'charset' => 'utf8', 'prefix' => '', 'search_path' => 'pg_temp', 'sslmode' => 'prefer',
        ]]);
        DB::purge('ownership_pg');
        DB::beginTransaction();
        DB::statement('CREATE TEMP TABLE ownership_test_anchor (id bigint) ON COMMIT DROP');
        DB::statement('SET LOCAL search_path = pg_temp');
        $this->assertStringStartsWith('pg_temp_', DB::selectOne('SELECT current_schema() AS name')->name);
        foreach ([new DebtorInvoice, new Deposit, new InvoiceableItem, new Property, new Unit, new TenantRecord,
            new ServiceAgreement, new ReturnClient, new ReturnSummary] as $model) {
            $fields = array_unique(array_merge($model->getFillable(), $model instanceof ReturnClient ? ['commission_amount', 'server_time', 'user_id'] : []));
            if ($model instanceof ReturnSummary) {
                $fields = array_unique(array_merge($fields, ['payable_amount', 'non_payable_amount', 'payable_units', 'total_units']));
            }
            $columns = ['id bigserial PRIMARY KEY'];
            foreach (array_diff($fields, ['id']) as $field) {
                $numeric = preg_match('/amount|balance|percentage|^commission_rate$|^vat_per$|^comm_per$|_total$|^total_credit$|^total_debit$|payable_units|total_units/', $field);
                $integer = str_ends_with($field, '_id') || in_array($field, ['client_d', 'created_by', 'return_period', 'year', 'month', 'approved_status', 'paid_status', 'payable_status']);
                $type = $integer ? 'bigint' : ($numeric ? 'numeric(18,2)' : 'text');
                if (in_array($field, ['returns_period', 'date_start', 'date_end'])) {
                    $type = 'date';
                }
                if (($model instanceof DebtorInvoice && $field === 'return_type_id') || ($model instanceof Deposit && $field === 'invoice_id')) {
                    $type = 'text';
                }
                $columns[] = '"'.$field.'" '.$type;
            }
            DB::statement('CREATE TEMP TABLE '.$model->getTable().' ('.implode(', ', $columns).') ON COMMIT DROP');
        }
        DB::statement('CREATE TEMP TABLE property_ownership_transfers (id bigint, property_id bigint, from_client_id bigint, status text, effective_date date, old_sla_id bigint, new_sla_id bigint) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE property_transfer_invoice_assignments (id bigint, invoice_id bigint, effective_date date, sla_id bigint) ON COMMIT DROP');
        DB::statement('CREATE TEMP TABLE sys_pm_clients (id bigint, reference_number text, utility_returns text) ON COMMIT DROP');
        DB::table('sys_pm_clients')->insert([['id' => 1, 'reference_number' => 'LAN-OLD'], ['id' => 2, 'reference_number' => 'LAN-NEW']]);
        DB::table('sys_pm_properties')->insert([['id' => 1, 'client_id' => 2, 'reference_number' => 'P1', 'property_name' => 'Transferred'], ['id' => 2, 'client_id' => 1, 'reference_number' => 'P2', 'property_name' => 'Unchanged']]);
        DB::table('sys_pm_units')->insert([['id' => 1, 'unit_code' => 'P1-1', 'vat_able' => 'No'], ['id' => 2, 'unit_code' => 'P2-1', 'vat_able' => 'No']]);
        DB::table('cb_tenant_records')->insert(['id' => 10, 'names' => 'Continuing tenant']);
        DB::table('sys_set_invoiceable_items')->insert(['id' => 1, 'item_name' => 'RENT', 'advance_payable' => 'No']);
        DB::table('sys_pm_service_agreements')->insert([
            ['id' => 1, 'property_id' => 1, 'client_d' => 1, 'date_start' => '2026-01-01', 'commission_percentage' => 10, 'vat_per' => 16],
            ['id' => 2, 'property_id' => 1, 'client_d' => 2, 'date_start' => '2026-12-01', 'commission_percentage' => 12, 'vat_per' => 16],
            ['id' => 3, 'property_id' => 2, 'client_d' => 1, 'date_start' => '2026-01-01', 'commission_percentage' => 10, 'vat_per' => 16],
        ]);
        DB::table('property_ownership_transfers')->insert(['id' => 1, 'property_id' => 1, 'from_client_id' => 1, 'status' => 'completed', 'effective_date' => '2026-12-01', 'old_sla_id' => 1, 'new_sla_id' => 2]);
        DB::table('property_transfer_invoice_assignments')->insert(['id' => 1, 'invoice_id' => 1, 'effective_date' => '2026-12-01', 'sla_id' => 2]);
        foreach ([1 => [1, '2026-11-01', 100, 0], 2 => [1, '2026-12-01', 200, 200], 3 => [2, '2026-12-01', 100, 100]] as $id => [$property, $period, $amount, $balance]) {
            DB::table('sys_pm_debtors_invoices')->insert(['id' => $id, 'property_id' => $property, 'unit_id' => $property, 'tenant_id' => 10, 'lease_id' => $property,
                'client_id' => $id === 2 ? 2 : 1, 'sla_id' => $id === 2 ? 2 : ($property === 2 ? 3 : 1),
                'item_type_id' => 1, 'debtor_type_id' => '2', 'current_status_id' => $balance ? 1 : 9, 'returns_period' => $period,
                'total_amount' => $amount, 'current_balance' => $balance, 'created_by' => 1, 'reference_number' => 'INV-'.$id]);
        }
        DB::table('cb_returns_clients')->insert(['invoice_id' => 1, 'return_code' => 'R1202611LAN-OLDP1', 'property_id' => 1, 'client_id' => 1, 'return_period' => 202611, 'dr_cr' => 'CR', 'net_amount' => 50]);
    }

    protected function tearDown(): void
    {
        if (DB::getDefaultConnection() === 'ownership_pg') {
            DB::rollBack();
            DB::disconnect('ownership_pg');
        }
        parent::tearDown();
    }

    private function generate(): void
    {
        $generator = new class
        {
            use ReturnsRentLinesTrait, ReturnsSummaryTrait;

            public function run(): void
            {
                $this->insertRentLines('202612', '2026-12-01', '05-Dec-2026', 100, 1, 'December', 2026);
                $this->insertMainReturnSummariesLegacy('202612', 2026, 12, 'December', 'Test');
            }
        };
        $generator->run();
    }

    public function test_transferred_arrears_pay_only_the_remaining_amount_to_the_incoming_landlord(): void
    {
        $this->generate();
        $late = DB::table('cb_returns_clients')->where('invoice_id', 1)->where('return_period', 202612)->first();
        $this->assertSame(2, (int) $late->client_id);
        $this->assertSame(50.0, (float) $late->net_amount);
        $this->assertSame(6.0, (float) $late->commission_amount);
        $this->assertSame('R1202612LAN-NEWP1', $late->return_code);
        $this->assertSame(50.0, (float) DB::table('cb_returns_clients')->where('return_period', 202611)->value('net_amount'));
        $this->assertSame(1, (int) DB::table('sys_pm_debtors_invoices')->where('id', 1)->value('client_id'));
        $this->assertSame(12.0, (float) DB::table('cb_returns_summary')->where('property_code', 'P1')->value('commission_rate'));
        $this->assertSame(1, (int) DB::table('cb_returns_clients')->where('invoice_id', 3)->value('client_id'));
    }

    public static function unchangedPropertyAgreements(): array
    {
        return [
            'no SLA' => [false, null, 0],
            'SLA without owner' => [true, null, 0],
            'SLA referencing missing client' => [true, 999, 999],
            'normal SLA' => [true, 1, 1],
        ];
    }

    #[DataProvider('unchangedPropertyAgreements')]
    public function test_untransferred_property_keeps_mains_sla_and_unknown_owner_rules(bool $hasAgreement, ?int $clientId, int $expectedClientId): void
    {
        if ($hasAgreement) {
            DB::table('sys_pm_service_agreements')->where('id', 3)->update(['client_d' => $clientId]);
        } else {
            DB::table('sys_pm_service_agreements')->where('id', 3)->delete();
        }
        $this->generate();
        $line = DB::table('cb_returns_clients')->where('invoice_id', 3)->sole();
        $this->assertSame($expectedClientId, (int) $line->client_id);
        $ownerCode = $clientId === 1 && $hasAgreement ? 'LAN-OLD' : 'UNKNOWN';
        $this->assertSame('R1202612'.$ownerCode.'P2', $line->return_code);
        $this->assertSame('MAINRETURN', DB::table('cb_returns_summary')->where('return_code', $line->return_code)->value('return_type'));
    }

    public function test_retained_arrears_generate_a_separate_return_for_the_former_landlord(): void
    {
        DB::table('property_transfer_invoice_assignments')->delete();
        $this->generate();
        $late = DB::table('cb_returns_clients')->where('invoice_id', 1)->where('return_period', 202612)->first();
        $this->assertSame(1, (int) $late->client_id);
        $this->assertSame(5.0, (float) $late->commission_amount);
        $this->assertSame('R1202612LAN-OLDP1', $late->return_code);
        $this->assertSame(2, DB::table('cb_returns_summary')->where('property_code', 'P1')->count());
        $this->assertSame(2, (int) DB::table('cb_returns_clients')->where('invoice_id', 2)->value('client_id'));
    }

    private function seedSeparateUtilities(): void
    {
        DB::table('property_transfer_invoice_assignments')->delete();
        DB::table('sys_pm_clients')->where('id', 2)->update(['utility_returns' => 'separate_return']);
        DB::table('sys_set_invoiceable_items')->insert(['id' => 2, 'item_name' => 'Service Charge', 'advance_payable' => 'No']);
        foreach ([4 => [1, '2026-11-01'], 5 => [2, '2026-12-01']] as $id => [$owner, $period]) {
            DB::table('sys_pm_debtors_invoices')->insert([
                'id' => $id, 'property_id' => 1, 'unit_id' => 1, 'tenant_id' => 10, 'lease_id' => 1,
                'client_id' => $owner, 'sla_id' => $owner, 'item_type_id' => 2, 'debtor_type_id' => '2',
                'current_status_id' => 9, 'returns_period' => $period, 'total_amount' => 100,
                'current_balance' => 0, 'created_by' => 1, 'reference_number' => 'UTILITY-'.$id,
            ]);
            DB::table('cb_returns_clients')->insert([
                'invoice_id' => $id, 'return_code' => 'R1202611LAN-OLDP1', 'property_id' => 1,
                'client_id' => $owner, 'return_period' => 202611, 'dr_cr' => 'CR', 'net_amount' => 50,
            ]);
        }
    }

    public function test_separate_utility_returns_preserve_each_owners_arrears_and_distinct_return_ids(): void
    {
        $this->seedSeparateUtilities();
        $this->generate();
        $returns = DB::table('cb_returns_summary')->where('property_code', 'P1')->get()->keyBy('return_code');
        $this->assertCount(4, $returns);
        $this->assertCount(4, $returns->pluck('return_id')->unique());
        foreach (['OLD' => 10, 'NEW' => 12] as $owner => $commission) {
            $rent = $returns['R1202612LAN-'.$owner.'P1'];
            $utility = $returns['RU1202612LAN-'.$owner.'P1'];
            $this->assertSame('MAINRETURN', $rent->return_type);
            $this->assertSame('UTILITYRETURN', $utility->return_type);
            $this->assertSame((float) $commission, (float) $utility->commission_rate);
            $this->assertSame(0.0, (float) $utility->commission_amount);
        }
    }

    public function test_minis_include_both_owners_and_utility_returns_and_reserve_used_utility_sequences(): void
    {
        $this->seedSeparateUtilities();
        DB::statement('CREATE TEMP TABLE josya_users (id bigint, name text) ON COMMIT DROP');
        DB::table('sys_pm_debtors_invoices')->where('id', 2)->update(['current_balance' => 0]);
        DB::table('cb_returns_clients')->insert([
            'invoice_id' => 2, 'return_code' => 'R1202612LAN-NEWP1', 'property_id' => 1,
            'client_id' => 2, 'return_period' => 202612, 'dr_cr' => 'CR', 'net_amount' => 50,
        ]);
        DB::table('cb_returns_summary')->insert([
            'return_code' => 'RU2202612LAN-NEWP1', 'return_period' => 202612,
            'property_code' => 'P1', 'return_type' => 'MINIRETURN', 'return_id' => 100,
        ]);
        // Exercise native generation and summaries; record deduction dispatch without unrelated ledger fixtures.
        $service = new class(new ReturnApprovalWorkflowService) extends MiniReturnService
        {
            public array $deductionCodes = [];

            public array $commissionCodes = [];

            protected function mainReturnApproved(string $period, string $propertyCode): bool
            {
                return true;
            }

            protected function deleteUnapprovedMinis(string $period, string $propertyCode): void {}

            protected function insertAdvancePaymentLines(string $returnPeriod, string $transactionDate, int $generatedBy, int $returnYear, int $returnMonth, ?int $propertyId = null, ?string $returnCode = null, bool $batchProperty = false): int
            {
                $this->deductionCodes[] = $returnCode;

                return 0;
            }

            protected function insertCommissionLines(string $returnPeriod, string $transactionDate, int $generatedBy, ?int $propertyId = null, ?string $returnCode = null): int
            {
                $this->commissionCodes[] = $returnCode;

                return 0;
            }
        };
        $result = $service->generate(1, '2026-12', 1, null);
        $rentCodes = ['R3202612LAN-OLDP1', 'R3202612LAN-NEWP1'];
        $allCodes = array_merge($rentCodes, ['RU3202612LAN-OLDP1', 'RU3202612LAN-NEWP1']);
        $this->assertEqualsCanonicalizing($allCodes, $result['return_codes']);
        $this->assertEqualsCanonicalizing($rentCodes, $service->deductionCodes);
        $this->assertEqualsCanonicalizing($rentCodes, $service->commissionCodes);
        $this->assertSame('R3202612LAN-NEWP1', $result['return_code']);
        $this->assertSame(4, DB::table('cb_returns_summary')->whereIn('return_code', $allCodes)->count());
        $this->assertSame(4, DB::table('cb_returns_summary')->whereIn('return_code', $allCodes)->distinct()->count('return_id'));
    }
}
