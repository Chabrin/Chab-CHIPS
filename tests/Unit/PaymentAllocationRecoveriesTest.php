<?php

namespace Tests\Unit;

use App\Traits\PaymentAllocationTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A recovery pays an invoice down and credits the tenant but writes no allocation row, because no money came in. The
 * allocator must still count it, or a later payment is allocated against the full invoice and the balance goes back up.
 */
class PaymentAllocationRecoveriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sys_pm_tenants_payments_invoices', function ($table) {
            $table->increments('id');
            $table->integer('invoice_id');
            $table->integer('payment_id');
            $table->decimal('amount_allocated', 19, 4)->default(0);
        });
        Schema::create('sys_set_transactions_types', function ($table) {
            $table->integer('id');
            $table->string('item_code')->nullable();
        });
        Schema::create('sys_pm_recoveries', function ($table) {
            $table->increments('id');
            $table->integer('type_id')->nullable();
            $table->integer('offset_invoice_id')->nullable();
            $table->integer('current_status_id')->default(1);
            $table->decimal('amount', 13, 2)->nullable();
        });

        DB::table('sys_set_transactions_types')->insert([
            ['id' => 31, 'item_code' => '2029'], ['id' => 32, 'item_code' => '2030'], ['id' => 33, 'item_code' => '2031'],
            ['id' => 34, 'item_code' => '2032'], ['id' => 84, 'item_code' => '2079'], ['id' => 49, 'item_code' => '2046'],
        ]);
    }

    /**
     * The allocator's invoice balance, as PaymentAllocationService reads it.
     */
    protected function balance(int $invoiceId, float $total): float
    {
        $probe = new class {
            use PaymentAllocationTrait;

            public function balance(int $invoiceId, float $total): float
            {
                return $this->calculateInvoiceBalance($invoiceId, $total);
            }
        };

        return $probe->balance($invoiceId, $total);
    }

    protected function recovery(int $typeId, float $amount, int $status = 5, int $invoiceId = 500): void
    {
        DB::table('sys_pm_recoveries')->insert(['type_id' => $typeId, 'offset_invoice_id' => $invoiceId, 'current_status_id' => $status, 'amount' => $amount]);
    }

    public function test_an_invoice_with_no_recovery_is_its_total_less_the_allocations(): void
    {
        DB::table('sys_pm_tenants_payments_invoices')->insert(['invoice_id' => 500, 'payment_id' => 1, 'amount_allocated' => 2000]);

        $this->assertEquals(6000.0, $this->balance(500, 8000));
    }

    public function test_a_confirmed_recovery_of_any_of_the_five_reasons_comes_off_the_balance(): void
    {
        foreach ([31, 32, 33, 34, 84] as $typeId) {
            DB::table('sys_pm_recoveries')->delete();
            $this->recovery($typeId, 3000);

            $this->assertEquals(5000.0, $this->balance(500, 8000), 'type ' . $typeId);
        }
    }

    public function test_the_recovery_and_the_allocations_both_come_off(): void
    {
        $this->recovery(32, 3000);
        DB::table('sys_pm_tenants_payments_invoices')->insert(['invoice_id' => 500, 'payment_id' => 1, 'amount_allocated' => 2000]);

        $this->assertEquals(3000.0, $this->balance(500, 8000));
    }

    public function test_a_tenant_paying_what_is_left_after_a_recovery_clears_the_invoice(): void
    {
        $this->recovery(32, 3000);

        // the tenant pays the 5,000 still owed: it must match the balance exactly, so the invoice clears
        $this->assertEquals(5000.0, $this->balance(500, 8000));
    }

    public function test_a_pending_or_rejected_recovery_does_not_count(): void
    {
        $this->recovery(32, 3000, 1);
        $this->recovery(33, 1000, 41);

        $this->assertEquals(8000.0, $this->balance(500, 8000));
    }

    public function test_a_recovery_of_another_type_does_not_count(): void
    {
        // the old approve-and-post flow never paid the invoice down
        $this->recovery(49, 3000);

        $this->assertEquals(8000.0, $this->balance(500, 8000));
    }

    public function test_a_recovery_of_another_invoice_does_not_count(): void
    {
        $this->recovery(32, 3000, 5, 501);

        $this->assertEquals(8000.0, $this->balance(500, 8000));
    }

    public function test_cents_are_not_lost_to_floating_point(): void
    {
        $this->recovery(32, 100000.55);

        // 280,310.52 less 100,000.55 is exactly 180,309.97, so a payment of 180,309.97 matches it and clears the invoice
        $this->assertSame(180309.97, $this->balance(500, 280310.52));
        $this->assertTrue(180309.97 == $this->balance(500, 280310.52));
    }
}
