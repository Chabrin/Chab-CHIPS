<?php

namespace Tests\Unit;

use App\Http\Controllers\TenantPaymentsController;
use App\Models\DebtorInvoice;
use App\Models\TenantPayment;
use App\Models\TenantPaymentAllocation;
use App\Services\PaymentAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class TenantPaymentsManualAllocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sys_pm_debtors_invoices', function ($table) {
            $table->increments('id');
        });
        DB::table('sys_pm_debtors_invoices')->insert(['id' => 5]);
    }

    public function test_manual_allocate_goes_through_the_allocation_service(): void
    {
        $service = Mockery::mock(PaymentAllocationService::class);
        $service->shouldReceive('manualAllocate')->once()->with(77, 5)->andReturn(['success' => true, 'result' => ['allocated' => true, 'amount' => 1500.0, 'invoice_status' => 9]]);

        $response = (new TenantPaymentsController($service))->allocatePayment(Request::create('/x', 'POST', ['invoice_id' => 5]), encrypt(77));
        $body = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertSame('1,500.00', $body['allocation']['amount_allocated']);
        $this->assertSame(9, $body['allocation']['invoice_status']);
    }

    public function test_manual_allocate_reports_when_nothing_was_allocated(): void
    {
        $service = Mockery::mock(PaymentAllocationService::class);
        $service->shouldReceive('manualAllocate')->once()->andReturn(['success' => false, 'result' => ['allocated' => false, 'amount' => 0.0, 'invoice_status' => null]]);

        $response = (new TenantPaymentsController($service))->allocatePayment(Request::create('/x', 'POST', ['invoice_id' => 5]), encrypt(77));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
    }

    public function test_manual_allocate_shows_a_service_failure_as_an_error(): void
    {
        $service = Mockery::mock(PaymentAllocationService::class);
        $service->shouldReceive('manualAllocate')->once()->andThrow(new \RuntimeException('GL journal failed'));

        $response = (new TenantPaymentsController($service))->allocatePayment(Request::create('/x', 'POST', ['invoice_id' => 5]), encrypt(77));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertStringContainsString('GL journal failed', $response->getData(true)['message']);
    }

    public function test_the_allocation_model_reads_the_table_the_allocation_service_writes(): void
    {
        $this->assertSame('sys_pm_tenants_payments_invoices', (new TenantPaymentAllocation)->getTable());
        $this->assertSame('payment_id', (new TenantPayment)->allocations()->getForeignKeyName());
        $this->assertSame('payment_id', (new TenantPaymentAllocation)->payment()->getForeignKeyName());
        $this->assertSame('invoice_id', (new DebtorInvoice)->paymentAllocations()->getForeignKeyName());
    }

    public function test_a_payments_allocated_total_comes_from_its_allocation_rows(): void
    {
        Schema::create('sys_pm_tenants_payments_invoices', function ($table) {
            $table->increments('id');
            $table->integer('payment_id');
            $table->integer('invoice_id');
            $table->decimal('amount_allocated', 15, 2);
        });
        DB::table('sys_pm_tenants_payments_invoices')->insert([
            ['payment_id' => 77, 'invoice_id' => 5, 'amount_allocated' => 400],
            ['payment_id' => 77, 'invoice_id' => 6, 'amount_allocated' => 600],
            ['payment_id' => 78, 'invoice_id' => 5, 'amount_allocated' => 999],
        ]);

        $payment = new TenantPayment();
        $payment->id = 77;

        $this->assertEquals(1000, $payment->allocations()->sum('amount_allocated'));
        $this->assertSame(2, $payment->allocations()->count());
    }

    public function test_deleting_an_allocation_is_refused_and_touches_nothing(): void
    {
        $service = Mockery::mock(PaymentAllocationService::class);
        $service->shouldNotReceive('manualAllocate');

        $response = (new TenantPaymentsController($service))->deleteAllocation(123);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
    }
}
