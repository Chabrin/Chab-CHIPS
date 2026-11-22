<?php

namespace Tests\Unit;

use App\Models\PaymentVoucherHeader;
use App\Services\VoucherPrintService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VoucherPrintServiceTest extends TestCase
{
    public function test_amount_in_words_reads_like_the_printed_voucher(): void
    {
        $this->assertSame('*NINE THOUSAND FOUR HUNDRED FIFTY SHILLINGS AND ZERO CENTS ONLY ***', VoucherPrintService::amountInWords(9450));
        $this->assertSame('*ONE HUNDRED EIGHTY-TWO THOUSAND FOUR HUNDRED TWELVE SHILLINGS AND ZERO CENTS ONLY ***', VoucherPrintService::amountInWords(182412));
    }

    public function test_cents_and_the_edges_are_written_out(): void
    {
        $this->assertSame('*ONE SHILLINGS AND FIFTY CENTS ONLY ***', VoucherPrintService::amountInWords(1.5));
        $this->assertSame('*ZERO SHILLINGS AND ZERO CENTS ONLY ***', VoucherPrintService::amountInWords(0));
        $this->assertSame('*TWO MILLION THREE HUNDRED THOUSAND SHILLINGS AND ZERO CENTS ONLY ***', VoucherPrintService::amountInWords(2300000));
        $this->assertSame('*ONE HUNDRED SHILLINGS AND ZERO CENTS ONLY ***', VoucherPrintService::amountInWords(100));
    }

    public function test_a_narration_ends_with_the_month_and_year_it_is_posted_to_once(): void
    {
        $this->assertSame('FINAL PAYMENTS FLORAH HOUSE-NOV-26', VoucherPrintService::narrationWithPeriod('FINAL PAYMENTS FLORAH HOUSE', 11, 2026));
        $this->assertSame('WELDING NO 102 SAN FRANCISCO-DEC-26', VoucherPrintService::narrationWithPeriod('WELDING NO 102 SAN FRANCISCO-DEC-26', 11, 2026));
        $this->assertSame('WATER BILL', VoucherPrintService::narrationWithPeriod('WATER BILL', 0, 2026));
        $this->assertSame('', VoucherPrintService::narrationWithPeriod('  ', 11, 2026));
    }

    public function test_the_bank_column_uses_the_short_name_of_the_account(): void
    {
        $this->assertSame('CO-OP', VoucherPrintService::bankLabel('CO-OP - BUSINESS ACCOUNT', 'CO-OPERATIVE BANK'));
        $this->assertSame('EQUITY', VoucherPrintService::bankLabel('Equity', 'EQUITY BANK'));
        $this->assertSame('EQUITY BANK', VoucherPrintService::bankLabel('', 'EQUITY BANK'));
    }

    public function test_cheque_charges_are_recognised_by_their_narration(): void
    {
        $this->assertTrue(VoucherPrintService::isChequeCharge('CHEQUE CHARGES'));
        $this->assertTrue(VoucherPrintService::isChequeCharge('  cheque charges JUDES PLAZA'));
        $this->assertFalse(VoucherPrintService::isChequeCharge('CLEANING WEEK ENDING 14TH NOV'));
    }

    public function test_the_print_has_the_vouchers_date_bank_charges_and_a_total_without_the_charges(): void
    {
        Schema::create('cb_payment_vouchers_lines_new', function ($table) {
            $table->increments('id');
            $table->string('voucher_number')->nullable();
            $table->string('entry_description')->nullable();
            $table->string('cheque_number')->nullable();
            $table->integer('cheque_leaf_id')->nullable();
            $table->integer('source_account_id')->nullable();
            $table->decimal('amount', 18, 2)->nullable();
        });
        Schema::create('sys_fin_cheque_books_261_repeat', function ($table) {
            $table->increments('id');
            $table->integer('bank_account_id')->nullable();
        });
        Schema::create('sys_set_bank_accounts', function ($table) {
            $table->increments('id');
            $table->string('account_name')->nullable();
            $table->integer('bank_id')->nullable();
        });
        Schema::create('sys_set_banks', function ($table) {
            $table->increments('id');
            $table->string('bank_name')->nullable();
        });

        DB::table('sys_set_banks')->insert(['id' => 10, 'bank_name' => 'CO-OPERATIVE BANK']);
        DB::table('sys_set_bank_accounts')->insert(['id' => 1, 'account_name' => 'CO-OP - BUSINESS ACCOUNT', 'bank_id' => 10]);
        DB::table('sys_fin_cheque_books_261_repeat')->insert(['id' => 5, 'bank_account_id' => 1]);
        foreach ([['cleaning week ending 14th nov 26 JUDES PLAZA', 3600], ['welding no 102 SAN FRANCISCO', 1900], ['ceiling repair JUDES PLAZA', 3950]] as [$text, $amount]) {
            DB::table('cb_payment_vouchers_lines_new')->insert(['voucher_number' => 'PV2026111724', 'entry_description' => $text, 'cheque_number' => '171917', 'cheque_leaf_id' => 5, 'amount' => $amount]);
        }
        DB::table('cb_payment_vouchers_lines_new')->insert(['voucher_number' => 'PV2026111724', 'entry_description' => 'CHEQUE CHARGES', 'amount' => 300]);

        $voucher = new PaymentVoucherHeader(['voucher_number' => 'PV2026111724', 'month' => 11, 'year' => 2026, 'voucher_date' => '2026-11-14', 'prepared_by' => 'LUCY WANJIRU']);

        $print = (new VoucherPrintService())->forVoucher($voucher);

        $this->assertSame('14-Nov-2026', $print['date']);
        $this->assertCount(3, $print['lines']);
        $this->assertSame('CEILING REPAIR JUDES PLAZA-NOV-26', strtoupper($print['lines'][2]['description']));
        $this->assertSame('CO-OP', $print['lines'][0]['bank']);
        $this->assertSame('171917', $print['lines'][0]['cheque']);
        $this->assertSame([['description' => 'CHEQUE CHARGES', 'amount' => 300.0]], $print['charges']);
        $this->assertSame(9450.0, $print['total']);
        $this->assertSame('*NINE THOUSAND FOUR HUNDRED FIFTY SHILLINGS AND ZERO CENTS ONLY ***', $print['words']);
        $this->assertSame('LUCY WANJIRU', $print['prepared_by']);
    }
}
