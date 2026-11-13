<?php

namespace Tests\Unit;

use App\Traits\ReturnThresholdTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Paying a migrated invoice (no item_type_id, 96% of invoices) that has a return row must not fail: the old threshold
 * update threw "Item configuration not found", which rolled back every arrears payment. An invoice that does have an item
 * type with no configuration is still an error.
 */
class ReturnThresholdMigratedInvoiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sys_pm_debtors_invoices', function ($table) {
            $table->increments('id');
            $table->integer('item_type_id')->nullable();
            $table->integer('lease_id')->nullable();
            $table->decimal('commisionable_amount', 15, 2)->nullable();
            $table->decimal('current_balance', 15, 2)->nullable();
            $table->integer('current_status_id')->nullable();
            $table->dateTime('date_due')->nullable();
        });
        Schema::create('sys_pm_clients_returns', function ($table) {
            $table->increments('id');
            $table->integer('invoice_id');
            $table->integer('payable_status_id')->nullable();
        });
        Schema::create('sys_set_invoiceable_items', function ($table) {
            $table->increments('id');
            $table->string('item_name')->nullable();
            $table->integer('advance_payable')->nullable();
            $table->integer('commissionable')->nullable();
            $table->integer('payment_priority')->nullable();
        });
    }

    private function runThreshold(int $invoiceId): bool {
        $subject = new class {
            use ReturnThresholdTrait;

            public function run($invoiceId) {
                return $this->executeThresholdUpdate($invoiceId, null);
            }
        };

        return $subject->run($invoiceId);
    }

    public function test_a_migrated_invoice_with_a_return_row_is_skipped_not_failed(): void
    {
        $invoiceId = DB::table('sys_pm_debtors_invoices')->insertGetId(['item_type_id' => null, 'lease_id' => 1, 'commisionable_amount' => 1000, 'current_balance' => 500]);
        DB::table('sys_pm_clients_returns')->insert(['invoice_id' => $invoiceId]);

        $this->assertTrue($this->runThreshold($invoiceId));
        $this->assertNull(DB::table('sys_pm_clients_returns')->where('invoice_id', $invoiceId)->value('payable_status_id'), 'nothing was written to the return');
    }

    public function test_an_invoice_with_an_item_type_that_has_no_configuration_still_fails(): void
    {
        $invoiceId = DB::table('sys_pm_debtors_invoices')->insertGetId(['item_type_id' => 999, 'lease_id' => 1, 'commisionable_amount' => 1000, 'current_balance' => 500]);
        DB::table('sys_pm_clients_returns')->insert(['invoice_id' => $invoiceId]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Item configuration not found for item_type_id: 999');

        $this->runThreshold($invoiceId);
    }

    public function test_an_invoice_with_no_return_row_is_still_skipped(): void
    {
        $invoiceId = DB::table('sys_pm_debtors_invoices')->insertGetId(['item_type_id' => null, 'lease_id' => 1, 'commisionable_amount' => 1000, 'current_balance' => 500]);

        $this->assertTrue($this->runThreshold($invoiceId));
    }
}
