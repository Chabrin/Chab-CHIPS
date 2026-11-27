<?php

namespace Tests\Support;

use App\Models\DebtorInvoice;
use App\Models\Deposit;
use App\Models\InvoiceableItem;
use App\Models\Property;
use App\Models\ReturnClient;
use App\Models\ReturnSummary;
use App\Models\ServiceAgreement;
use App\Models\TenantRecord;
use App\Models\Unit;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;

/** Connection-local temporary tables only; never writes fixtures into the application schema. */
trait PostgresReturnFixtures
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
                $fields = array_unique(array_merge($fields, ['payable_amount', 'non_payable_amount', 'payable_units', 'total_units', 'parent_return_code', 'is_merged']));
            }
            $columns = ['id bigserial PRIMARY KEY'];
            foreach (array_diff($fields, ['id']) as $field) {
                $numeric = preg_match('/amount|balance|percentage|^commission_rate$|^vat_per$|^comm_per$|_total$|^total_credit$|^total_debit$|payable_units|total_units/', $field);
                $integer = str_ends_with($field, '_id') || in_array($field, ['client_d', 'created_by', 'return_period', 'year', 'month', 'approved_status', 'paid_status', 'payable_status']);
                $type = $integer ? 'bigint' : ($numeric ? 'numeric(18,2)' : 'text');
                if ($field === 'is_merged') {
                    $type = 'boolean DEFAULT FALSE';
                }
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
}
