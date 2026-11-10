<?php

namespace Tests\Unit;

use App\Models\PaymentVoucherHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class PaymentVoucherSequenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cb_payment_vouchers_headers_new', function ($table) {
            $table->increments('id');
            $table->integer('voucher_sequence')->nullable();
        });
    }

    public function test_next_sequence_refuses_to_run_outside_a_transaction(): void
    {
        // The test case itself is not wrapped in a transaction (no RefreshDatabase), so level 0 is the real situation.
        $this->assertSame(0, DB::transactionLevel());
        $this->expectException(LogicException::class);

        PaymentVoucherHeader::nextSequence();
    }

    public function test_next_sequence_starts_at_one_on_an_empty_table(): void
    {
        $this->assertSame(1, DB::transaction(fn () => PaymentVoucherHeader::nextSequence()));
    }

    public function test_next_sequence_is_one_more_than_the_highest_in_use(): void
    {
        DB::table('cb_payment_vouchers_headers_new')->insert([['voucher_sequence' => 5], ['voucher_sequence' => 2446], ['voucher_sequence' => 12]]);

        $this->assertSame(2447, DB::transaction(fn () => PaymentVoucherHeader::nextSequence()));
    }
}
