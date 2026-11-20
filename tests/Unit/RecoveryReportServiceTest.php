<?php

namespace Tests\Unit;

use App\Http\Controllers\AccountsReportsController;
use App\Services\GlJournalPoster;
use App\Services\RecoveryReportService;
use App\Services\RecoveryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * The Recovery Register: the rows with their reason, landlord, tenant, invoice, status and age, the filters, and the
 * totals by reason, landlord, tenant and age.
 */
class RecoveryReportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sys_set_transactions_types', function ($table) {
            $table->increments('id');
            $table->string('type_name')->nullable();
            $table->integer('is_recovery')->default(0);
            $table->string('item_code')->nullable();
            $table->string('is_commissionable')->nullable();
        });
        Schema::create('sys_pm_clients', function ($table) {
            $table->increments('id');
            $table->string('names')->nullable();
        });
        Schema::create('sys_pm_properties', function ($table) {
            $table->increments('id');
            $table->string('property_name')->nullable();
        });
        Schema::create('sys_pm_units', function ($table) {
            $table->increments('id');
            $table->string('unit_code')->nullable();
        });
        Schema::create('sys_pm_debtors_invoices', function ($table) {
            $table->increments('id');
            $table->string('reference_number')->nullable();
        });
        Schema::create('sys_pm_recoveries', function ($table) {
            $table->increments('id');
            $table->string('ref_number')->nullable();
            $table->integer('type_id')->nullable();
            $table->date('recovery_date')->nullable();
            $table->text('narration')->nullable();
            $table->integer('client_id')->nullable();
            $table->integer('property_id')->nullable();
            $table->integer('unit_id')->nullable();
            $table->integer('tenant_id')->nullable();
            $table->string('commisionable')->nullable();
            $table->integer('current_status_id')->default(1);
            $table->decimal('amount', 13, 2)->nullable();
            $table->integer('offset_invoice_id')->nullable();
            $table->string('returns_batch_id')->nullable();
        });

        DB::table('sys_set_transactions_types')->insert([
            ['id' => 32, 'type_name' => 'Sat Deposit', 'is_recovery' => 1, 'item_code' => '2030', 'is_commissionable' => 'Yes'],
            ['id' => 33, 'type_name' => 'Absconded', 'is_recovery' => 1, 'item_code' => '2031', 'is_commissionable' => 'No'],
            ['id' => 4, 'type_name' => 'Client Expense', 'is_recovery' => 1, 'item_code' => '2084', 'is_commissionable' => 'No'],
        ]);
        DB::table('sys_pm_clients')->insert([['id' => 100, 'names' => 'Landlord One'], ['id' => 101, 'names' => 'Landlord Two'], ['id' => 200, 'names' => 'Tenant A'], ['id' => 201, 'names' => 'Tenant B']]);
        DB::table('sys_pm_properties')->insert([['id' => 300, 'property_name' => 'Plaza'], ['id' => 301, 'property_name' => 'Court']]);
        DB::table('sys_pm_units')->insert([['id' => 400, 'unit_code' => 'A1'], ['id' => 401, 'unit_code' => 'B2']]);
        DB::table('sys_pm_debtors_invoices')->insert([['id' => 500, 'reference_number' => 'INV500'], ['id' => 501, 'reference_number' => 'INV501']]);

        DB::table('sys_pm_recoveries')->insert([
            ['ref_number' => 'REC-2026-00001', 'type_id' => 32, 'recovery_date' => '2026-11-10', 'narration' => 'July rent', 'client_id' => 100, 'property_id' => 300, 'unit_id' => 400, 'tenant_id' => 200, 'commisionable' => 'Yes', 'current_status_id' => 5, 'amount' => 4000, 'offset_invoice_id' => 500, 'returns_batch_id' => null],
            ['ref_number' => 'REC-2026-00002', 'type_id' => 33, 'recovery_date' => '2026-09-01', 'narration' => 'August rent', 'client_id' => 100, 'property_id' => 300, 'unit_id' => 401, 'tenant_id' => 201, 'commisionable' => 'No', 'current_status_id' => 1, 'amount' => 6000, 'offset_invoice_id' => 501, 'returns_batch_id' => null],
            ['ref_number' => 'REC-2026-00003', 'type_id' => 32, 'recovery_date' => '2026-07-01', 'narration' => 'May rent', 'client_id' => 101, 'property_id' => 301, 'unit_id' => 400, 'tenant_id' => 200, 'commisionable' => 'Yes', 'current_status_id' => 41, 'amount' => 1000, 'offset_invoice_id' => null, 'returns_batch_id' => null],
            ['ref_number' => 'OLD-1', 'type_id' => 4, 'recovery_date' => '2026-11-15', 'narration' => 'Old flow', 'client_id' => 101, 'property_id' => 301, 'unit_id' => null, 'tenant_id' => null, 'commisionable' => 'No', 'current_status_id' => 5, 'amount' => 500, 'offset_invoice_id' => null, 'returns_batch_id' => 'B7'],
        ]);
    }

    protected function service(): RecoveryReportService
    {
        return new RecoveryReportService(new RecoveryService(Mockery::mock(GlJournalPoster::class)));
    }

    protected function today(): Carbon
    {
        return Carbon::parse('2026-11-18');
    }

    public function test_every_recovery_is_listed_with_its_reason_people_invoice_and_age(): void
    {
        $rows = $this->service()->rows([], $this->today());

        $this->assertCount(4, $rows);
        $first = $rows->firstWhere('reference', 'REC-2026-00001');
        $this->assertSame('Sat Deposit', $first['reason']);
        $this->assertSame('Landlord One', $first['landlord']);
        $this->assertSame('Plaza', $first['property']);
        $this->assertSame('A1', $first['unit']);
        $this->assertSame('Tenant A', $first['tenant']);
        $this->assertSame('INV500', $first['invoice']);
        $this->assertSame('4,000.00', $first['amount']);
        $this->assertSame('Yes', $first['commissionable']);
        $this->assertSame('10/11/2026', $first['recovery_date']);
        $this->assertSame(8, $first['age_days']);
        $this->assertSame('0 to 30 days', $first['age_band']);
    }

    public function test_a_confirmed_reason_shows_its_receipt_and_the_other_statuses_none(): void
    {
        $rows = $this->service()->rows([], $this->today());

        $confirmed = $rows->firstWhere('reference', 'REC-2026-00001');
        $this->assertSame('Confirmed', $confirmed['status']);
        $this->assertSame('REC-2026-00001', $confirmed['receipt']);

        $pending = $rows->firstWhere('reference', 'REC-2026-00002');
        $this->assertSame('Pending confirmation', $pending['status']);
        $this->assertSame('-', $pending['receipt']);

        $this->assertSame('Rejected', $rows->firstWhere('reference', 'REC-2026-00003')['status']);

        // a type that is not one of the five follows the old flow: Approved, and no receipt
        $old = $rows->firstWhere('reference', 'OLD-1');
        $this->assertSame('Approved', $old['status']);
        $this->assertSame('-', $old['receipt']);
        $this->assertSame('B7', $old['returns_batch']);
    }

    public function test_the_filters_narrow_the_rows(): void
    {
        $service = $this->service();

        $this->assertSame(['REC-2026-00001', 'REC-2026-00003'], $service->rows(['type_id' => 32], $this->today())->pluck('reference')->sort()->values()->all());
        $this->assertSame(['REC-2026-00002'], $service->rows(['status' => 1], $this->today())->pluck('reference')->all());
        $this->assertSame(['OLD-1', 'REC-2026-00003'], $service->rows(['property_id' => 301], $this->today())->pluck('reference')->sort()->values()->all());
        $this->assertSame(['OLD-1', 'REC-2026-00001'], $service->rows(['date_from' => '2026-10-01'], $this->today())->pluck('reference')->sort()->values()->all());
        $this->assertSame(['REC-2026-00002', 'REC-2026-00003'], $service->rows(['date_to' => '2026-09-30'], $this->today())->pluck('reference')->sort()->values()->all());
    }

    public function test_the_totals_are_given_by_reason_landlord_tenant_and_age(): void
    {
        $service = $this->service();
        $summary = $service->summary($service->rows([], $this->today()));

        $this->assertSame(['count' => 4, 'amount' => 11500.0], $summary['total']);

        // the biggest amount first
        $this->assertSame(
            [['label' => 'Absconded', 'count' => 1, 'amount' => 6000.0], ['label' => 'Sat Deposit', 'count' => 2, 'amount' => 5000.0], ['label' => 'Client Expense', 'count' => 1, 'amount' => 500.0]],
            $summary['by_reason']
        );
        $this->assertSame('Landlord One', $summary['by_landlord'][0]['label']);
        $this->assertSame(10000.0, $summary['by_landlord'][0]['amount']);
        $this->assertSame('Tenant B', $summary['by_tenant'][0]['label']);

        // the age bands keep their order and show the empty ones: 18 Nov counted from 15 Nov, 10 Nov, 1 Sep (78 days) and 1 Jul (140 days)
        $this->assertSame(['0 to 30 days', '31 to 60 days', '61 to 90 days', 'Over 90 days', 'No date'], array_column($summary['by_age'], 'label'));
        $this->assertSame([2, 0, 1, 1, 0], array_column($summary['by_age'], 'count'));
        $this->assertSame([4500.0, 0.0, 6000.0, 1000.0, 0.0], array_column($summary['by_age'], 'amount'));
    }

    public function test_the_age_bands_cover_every_day_count(): void
    {
        $service = $this->service();

        $this->assertSame('0 to 30 days', $service->ageBand(0));
        $this->assertSame('0 to 30 days', $service->ageBand(30));
        $this->assertSame('31 to 60 days', $service->ageBand(31));
        $this->assertSame('61 to 90 days', $service->ageBand(90));
        $this->assertSame('Over 90 days', $service->ageBand(91));
        $this->assertSame('Over 90 days', $service->ageBand(4000));
    }

    public function test_a_recovery_dated_in_the_future_has_age_zero_not_a_negative_age(): void
    {
        $rows = $this->service()->rows([], Carbon::parse('2026-11-01'));

        $this->assertSame(0, $rows->firstWhere('reference', 'OLD-1')['age_days']);
    }

    public function test_the_register_page_sends_the_rows_and_the_totals_together(): void
    {
        $controller = new AccountsReportsController($this->service());

        $data = $controller->getRecoveryRegister(Request::create('/x', 'POST', ['type_id' => 32]))->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['count']);
        $this->assertSame(5000.0, (float) $data['summary']['total']['amount']);
        $this->assertSame('Sat Deposit', $data['data'][0]['reason']);
    }
}
