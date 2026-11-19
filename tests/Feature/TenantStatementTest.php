<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class TenantStatementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'tenant_statement_test', 'database.connections.tenant_statement_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        config(['app.key' => 'base64:' . base64_encode(str_repeat('a', 32))]);
        $this->withoutMiddleware();

        Schema::create('sys_pm_tenants_accounts_trans', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->integer('property_id');
            $table->integer('current_status_id')->default(1);
            $table->dateTime('date_time');
            $table->decimal('debit_amount', 16, 4);
            $table->decimal('credit_amount', 16, 4);
            foreach (['dr_cr', 'invoice_number', 'receipt_number', 'entry_ref', 'narration'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::create('sys_pm_clients', function (Blueprint $table) {
            $table->id();
            $table->string('names');
        });
        Schema::create('cb_tenant_records', fn (Blueprint $table) => $table->id());
        Schema::create('sys_pm_properties', function (Blueprint $table) {
            $table->id();
            $table->string('property_name');
            $table->boolean('active')->default(true);
        });
        Schema::create('sys_pm_units', function (Blueprint $table) {
            $table->id();
            $table->string('unit_number')->nullable();
            $table->string('unit_name');
        });
        Schema::create('sys_pm_lease_agreements', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->integer('property_id');
            $table->integer('unit_id');
        });

        DB::table('sys_pm_clients')->insert(['id' => 1, 'names' => 'Test Tenant']);
        DB::table('sys_pm_properties')->insert(['id' => 20, 'property_name' => 'Selected Property']);
        DB::table('sys_pm_units')->insert(['id' => 25, 'unit_number' => 'B12', 'unit_name' => 'Unit B12']);
        DB::table('sys_pm_lease_agreements')->insert(['tenant_id' => 1, 'property_id' => 20, 'unit_id' => 25]);

        $this->transaction('2026-07-01 00:00:00', 900, 0, ['property_id' => 10]);
        $this->transaction('2026-07-10 00:00:00', 100, 0);
        $this->transaction('2026-07-20 00:00:00', 0, 25);
        $this->transaction('2026-07-25 00:00:00', 500, 0, ['current_status_id' => 2]);
        $this->transaction('2026-08-01 08:00:00', 30, 0, ['invoice_number' => 'INV-123']);
        $this->transaction('2026-08-01 08:00:00', 0, 15, ['receipt_number' => 'RCPT-123']);
        $this->transaction('2026-08-31 23:59:59', 0, 10, ['entry_ref' => 'ADJ-123']);
        $this->transaction('2026-10-01 00:00:00', 500, 0);
    }

    public function test_statement_and_exports_share_the_property_period_and_balances(): void
    {
        $filters = $this->filters();
        $payload = $this->postJson(route('ajax.invoices.tenant.search'), $filters)->assertOk()->json();

        $this->assertSame([
            'opening_balance' => -75,
            'total_debit' => 30,
            'total_credit' => 25,
            'closing_balance' => -80,
        ], $payload['summary']);
        $this->assertSame([-105, -90, -80], array_column($payload['statement'], 'balance'));
        $this->assertSame(['INV-123', 'RCPT-123', 'ADJ-123'], array_column($payload['statement'], 'reference'));

        $pdfData = null;
        View::composer('property.invoices.tenant-statement-pdf', function ($view) use (&$pdfData) {
            $pdfData = $view->getData();
        });
        $pdf = $this->get(route('invoices.tenant.statement.export.pdf', $filters))->assertDownload();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertEquals($payload['summary'], $pdfData['summary']);
        $this->assertSame(['Selected Property', 'B12'], [$pdfData['tenantInfo']['property_name'], $pdfData['tenantInfo']['unit_name']]);
        $this->assertSame(['INV-123', 'RCPT-123', 'ADJ-123'], array_column($pdfData['statement'], 'reference'));

        $sheet = $this->excelSheet($filters);
        $this->assertSame('Property: Selected Property | Unit: B12', $sheet->getCell('A3')->getValue());
        $this->assertSame('Period: 01 Aug 2026 to 31 Aug 2026', $sheet->getCell('A4')->getValue());
        $this->assertEquals(-75, $sheet->getCell('F5')->getValue());
        $this->assertSame('RCPT-123', $sheet->getCell('B8')->getValue());
        $this->assertEquals(-80, $sheet->getCell('F10')->getValue());
    }

    public function test_empty_range_carries_balance_and_through_date_includes_older_entries(): void
    {
        $filters = $this->filters(['date_from' => '2026-09-01', 'date_to' => '2026-09-30']);
        $this->postJson(route('ajax.invoices.tenant.search'), $filters)->assertOk()
            ->assertJsonPath('statement', [])
            ->assertJsonPath('summary.opening_balance', -80)
            ->assertJsonPath('summary.closing_balance', -80);
        $sheet = $this->excelSheet($filters);
        $this->assertEquals(-80, $sheet->getCell('F5')->getValue());
        $this->assertEquals(-80, $sheet->getCell('F7')->getValue());

        $filters = $this->filters(['date_type' => 'end', 'date_from' => '2026-08-01']);
        $this->postJson(route('ajax.invoices.tenant.search'), $filters)->assertOk()
            ->assertJsonCount(5, 'statement')
            ->assertJsonPath('summary.opening_balance', null)
            ->assertJsonPath('summary.closing_balance', -80);
    }

    private function filters(array $overrides = []): array
    {
        return $overrides + [
            'tenant_id' => Crypt::encrypt(1), 'property_id' => 20,
            'date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'date_type' => 'range',
        ];
    }

    private function transaction(string $date, int $debit, int $credit, array $overrides = []): void
    {
        DB::table('sys_pm_tenants_accounts_trans')->insert($overrides + [
            'tenant_id' => 1, 'property_id' => 20, 'date_time' => $date,
            'debit_amount' => $debit, 'credit_amount' => $credit, 'dr_cr' => $debit ? 'DR' : 'CR',
        ]);
    }

    private function excelSheet(array $filters): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $response = $this->get(route('invoices.tenant.statement.export.excel', $filters))->assertDownload();
        $file = $response->baseResponse->getFile()->getPathname();

        try {
            return IOFactory::load($file)->getActiveSheet();
        } finally {
            unlink($file);
        }
    }
}
