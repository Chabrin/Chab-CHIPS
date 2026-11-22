<?php

namespace Tests\Unit;

use App\Services\ChequeListingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChequeListingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cb_payment_vouchers_headers_new', function ($table) {
            $table->increments('id');
            $table->string('voucher_number')->nullable();
            $table->date('voucher_date')->nullable();
            $table->string('voucher_status')->nullable();
            $table->integer('is_printed')->default(0);
            $table->string('vendor_name')->nullable();
        });
        Schema::create('cb_payment_vouchers_lines_new', function ($table) {
            $table->increments('id');
            $table->string('voucher_number')->nullable();
            $table->string('entry_description')->nullable();
            $table->string('cheque_number')->nullable();
            $table->integer('cheque_leaf_id')->nullable();
            $table->integer('source_account_id')->nullable();
            $table->string('payee_name')->nullable();
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

        DB::table('sys_set_banks')->insert([['id' => 10, 'bank_name' => 'CO-OPERATIVE BANK'], ['id' => 11, 'bank_name' => 'EQUITY BANK']]);
        DB::table('sys_set_bank_accounts')->insert([['id' => 1, 'account_name' => 'CO-OP - BUSINESS ACCOUNT', 'bank_id' => 10], ['id' => 2, 'account_name' => 'EQUITY - MAIN', 'bank_id' => 11]]);
        DB::table('sys_fin_cheque_books_261_repeat')->insert([['id' => 5, 'bank_account_id' => 1], ['id' => 6, 'bank_account_id' => 2]]);

        $this->voucher(1, 'PV1', '2026-11-14', 'Approved', 1, 'PETRER M KINUTHIA', [['cleaning JUDES PLAZA', '171917', 5, 3600], ['welding SAN FRANCISCO', '171917', 5, 1900], ['ceiling JUDES PLAZA', '171917', 5, 3950], ['CHEQUE CHARGES', '171917', 5, 300]]);
        $this->voucher(2, 'PV2', '2026-11-13', 'Pending', 0, 'JEREMY CHEGE MUNA', [['FINAL PAYMENTS FLORAH HOUSE', '171910', 5, 182412]]);
        $this->voucher(3, 'PV3', '2026-10-01', 'Cancelled', 1, 'OLD PAYEE', [['WATER BILL', '300001', 6, 500]]);
        $this->voucher(4, 'PV4', '2026-09-01', 'Approved', 1, 'LEGACY PAYEE', [['advance salaries', '159689', null, 68200]]);
        $this->voucher(5, 'PV5', '2026-09-02', 'Pending', 0, 'NO CHEQUE', [['cash expense', null, null, 100]]);
    }

    private function voucher(int $id, string $number, string $date, string $status, int $printed, string $payee, array $lines): void
    {
        DB::table('cb_payment_vouchers_headers_new')->insert(['id' => $id, 'voucher_number' => $number, 'voucher_date' => $date, 'voucher_status' => $status, 'is_printed' => $printed, 'vendor_name' => $payee]);
        foreach ($lines as [$text, $cheque, $leaf, $amount]) {
            DB::table('cb_payment_vouchers_lines_new')->insert(['voucher_number' => $number, 'entry_description' => $text, 'cheque_number' => $cheque, 'cheque_leaf_id' => $leaf, 'amount' => $amount]);
        }
    }

    private function page(array $filters = [], string $sort = 'date', string $dir = 'desc', int $page = 1, int $perPage = 50): array
    {
        return (new ChequeListingService())->page($filters, $sort, $dir, $page, $perPage);
    }

    public function test_each_cheque_number_of_a_voucher_is_one_row_with_its_lines_added_up_without_the_cheque_charge(): void
    {
        $result = $this->page();

        $this->assertSame(4, $result['total']);
        $first = collect($result['rows'])->firstWhere('cheque_number', '171917');
        $this->assertSame(9450.0, $first['amount']);
        $this->assertSame(4, $first['lines']);
        $this->assertSame('CO-OP', $first['bank']);
        $this->assertSame('PETRER M KINUTHIA', $first['payee']);
        $this->assertSame('14-Nov-2026', $first['voucher_date']);
        $this->assertTrue($first['printed']);
        // a voucher with no cheque number has no cheque
        $this->assertNull(collect($result['rows'])->firstWhere('voucher_number', 'PV5'));
        $this->assertSame(round(9450 + 182412 + 500 + 68200, 2), $result['amount']);
    }

    public function test_a_legacy_cheque_with_no_leaf_has_no_bank(): void
    {
        $legacy = collect($this->page()['rows'])->firstWhere('cheque_number', '159689');

        $this->assertSame('-', $legacy['bank']);
        $this->assertSame(68200.0, $legacy['amount']);
    }

    public function test_the_filters_narrow_the_list_and_the_totals(): void
    {
        $this->assertSame(['PV2'], array_column($this->page(['status' => 'Pending'])['rows'], 'voucher_number'));
        $this->assertSame(['PV1', 'PV3', 'PV4'], array_column($this->page(['printed' => 'yes'], 'voucher', 'asc')['rows'], 'voucher_number'));
        $this->assertSame(['PV2'], array_column($this->page(['printed' => 'no'])['rows'], 'voucher_number'));
        $this->assertSame(['PV3'], array_column($this->page(['bank_account_id' => 2])['rows'], 'voucher_number'));
        $this->assertSame(['PV1', 'PV2'], array_column($this->page(['date_from' => '2026-11-01'], 'voucher', 'asc')['rows'], 'voucher_number'));
        $this->assertSame(['PV3'], array_column($this->page(['date_to' => '2026-10-31', 'date_from' => '2026-10-01'])['rows'], 'voucher_number'));
        $this->assertSame(1, $this->page(['status' => 'Cancelled'])['total']);
        $this->assertSame(500.0, $this->page(['status' => 'Cancelled'])['amount']);
    }

    public function test_search_finds_by_cheque_voucher_payee_or_narration(): void
    {
        $this->assertSame(['PV2'], array_column($this->page(['search' => '171910'])['rows'], 'voucher_number'));
        $this->assertSame(['PV1'], array_column($this->page(['search' => 'pv1'])['rows'], 'voucher_number'));
        $this->assertSame(['PV1'], array_column($this->page(['search' => 'kinuthia'])['rows'], 'voucher_number'));
        $this->assertSame(['PV1'], array_column($this->page(['search' => 'san francisco'])['rows'], 'voucher_number'));
        $this->assertSame([], $this->page(['search' => 'nothing like this'])['rows']);
    }

    public function test_amount_range_filters_on_the_cheque_amount_not_a_line(): void
    {
        $this->assertSame(['PV2'], array_column($this->page(['amount_min' => 100000])['rows'], 'voucher_number'));
        $this->assertSame(['PV3', 'PV1'], array_column($this->page(['amount_max' => 9450], 'amount', 'asc')['rows'], 'voucher_number'));
        // 3,600 is one line of the 9,450 cheque, so a range around it matches nothing
        $this->assertSame([], $this->page(['amount_min' => 3000, 'amount_max' => 4000])['rows']);
    }

    public function test_sorting_and_paging(): void
    {
        $this->assertSame(['PV1', 'PV2', 'PV3', 'PV4'], array_column($this->page([], 'date', 'desc')['rows'], 'voucher_number'));
        $this->assertSame(['PV4', 'PV3', 'PV2', 'PV1'], array_column($this->page([], 'date', 'asc')['rows'], 'voucher_number'));
        $this->assertSame(['PV2', 'PV4', 'PV1', 'PV3'], array_column($this->page([], 'amount', 'desc')['rows'], 'voucher_number'));
        $this->assertSame(['PV3', 'PV1', 'PV2', 'PV4'], array_column($this->page([], 'cheque', 'desc')['rows'], 'voucher_number'));

        $paged = $this->page([], 'date', 'desc', 2, 25);
        $this->assertSame(4, $paged['total']);
        $this->assertSame(1, $paged['pages']);
        $this->assertSame([], $paged['rows']);
        // an unknown page size falls back to 50
        $this->assertSame(50, $this->page([], 'date', 'desc', 1, 7)['per_page']);
        // an unknown sort falls back to date
        $this->assertSame(['PV1', 'PV2', 'PV3', 'PV4'], array_column($this->page([], 'nonsense')['rows'], 'voucher_number'));
    }
}
