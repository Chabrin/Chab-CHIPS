<?php

namespace Tests\Unit;

use App\Http\Controllers\AccountsReportsController;
use App\Models\ReturnSummary;
use App\Services\ReturnStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The statement's queries (ledger gaps, details-card totals) and the Returns Status report, on a small in-memory schema. */
class ReturnStatementDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Production has no table prefix, and the statement's raw sub-selects name tables directly.
        DB::connection()->setTablePrefix('');
        Schema::create('cb_landlord_account', function ($table) {
            $table->increments('id');
            $table->string('return_code')->nullable();
            $table->string('return_transaction')->nullable();
            $table->string('transaction_number')->nullable();
            $table->string('entry_type_code')->nullable();
            $table->string('entry_description')->nullable();
            $table->string('entry_particulars')->nullable();
            $table->string('payment_ref')->nullable();
            $table->string('payment_ref_type')->nullable();
            $table->date('entry_date')->nullable();
            $table->decimal('debit_amount', 14, 2)->default(0);
            $table->decimal('credit_amount', 14, 2)->default(0);
        });
        Schema::create('cb_returns_clients', function ($table) {
            $table->increments('id');
            $table->string('return_code');
            $table->string('transaction_number')->nullable();
            $table->string('entry_type')->nullable();
            $table->string('dr_cr')->nullable();
            $table->string('transaction_source')->nullable();
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('commission_amount', 14, 2)->nullable();
            $table->decimal('vat_amount', 14, 2)->nullable();
        });
        Schema::create('cb_returns_summary', function ($table) {
            $table->increments('id');
            $table->string('return_code');
            $table->string('return_type');
            $table->string('landlord_code')->nullable();
            $table->string('property_code')->nullable();
            $table->integer('year')->nullable();
            $table->integer('month')->nullable();
            $table->integer('paid_status')->default(0);
            $table->integer('approved_status')->default(0);
            $table->dateTime('prepared_date')->nullable();
            $table->dateTime('approved_date')->nullable();
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->string('parent_return_code')->nullable();
        });
        Schema::create('sys_pm_clients', function ($table) {
            $table->string('reference_number');
            $table->string('names')->nullable();
        });
    }

    private function line(string $code, string $type, string $crDr, float $amount, array $extra = []): void
    {
        DB::table('cb_returns_clients')->insert(array_merge(['return_code' => $code, 'entry_type' => $type, 'dr_cr' => $crDr, 'net_amount' => $amount], $extra));
    }

    public function test_ledger_gaps_are_only_the_debits_that_have_no_line_and_are_not_balance_rows(): void
    {
        $this->line('R1', 'CHEQUE CHARGES', 'DR', 100, ['transaction_number' => 'T1']);
        $this->line('R1', 'Advance', 'DR', 4000, ['transaction_number' => 'T6']);
        $ledger = fn(array $row) => DB::table('cb_landlord_account')->insert(array_merge(['return_code' => 'R1', 'entry_date' => '2026-07-06'], $row));
        $ledger(['entry_type_code' => '0014', 'entry_description' => 'covered by return_transaction', 'return_transaction' => 'T1', 'debit_amount' => 100]);
        $ledger(['entry_type_code' => '0014', 'entry_description' => 'LOST CHEQUE', 'return_transaction' => 'T2', 'debit_amount' => 200, 'payment_ref' => '164 ', 'payment_ref_type' => 'CO-OP']);
        $ledger(['entry_type_code' => '00014', 'entry_description' => 'Closing Balance C/F', 'debit_amount' => 5000]);
        $ledger(['entry_type_code' => '0008', 'entry_description' => 'Opening Balance B/F', 'debit_amount' => 700]);
        $ledger(['entry_type_code' => '0005', 'entry_description' => 'the return credit', 'credit_amount' => 900]);
        $ledger(['entry_type_code' => '0009', 'entry_description' => 'covered by transaction_number', 'transaction_number' => 'T6', 'debit_amount' => 4000]);
        DB::table('cb_landlord_account')->insert(['return_code' => 'R2', 'entry_type_code' => '0014', 'entry_description' => 'another return', 'debit_amount' => 50]);

        $gaps = app(ReturnStatementService::class)->ledgerGaps(new ReturnSummary(['return_code' => 'R1']), collect());

        $this->assertSame(['LOST CHEQUE'], $gaps->pluck('entry_description')->all());
    }

    public function test_details_card_totals_do_not_debit_the_rent_vat_an_old_line_holds_and_skip_the_commission_repeat_line(): void
    {
        // Old system: the rent line's vat_amount is the VAT inside the rent; commission and VAT are their own debit lines.
        $this->line('OLD', 'RENT CURRENT', 'CR', 26796, ['vat_amount' => 3696, 'commission_amount' => 0]);
        $this->line('OLD', 'Deductions Before Commission', 'DR', 3696, ['transaction_source' => 'COMPUTED']);
        $this->line('OLD', 'Add Back Deductions', 'CR', 3696, ['transaction_source' => 'COMPUTED']);
        $this->line('OLD', 'Commission', 'DR', 2310);
        $this->line('OLD', 'VAT', 'DR', 370);
        // Generated here: commission and VAT sit on the rent line, and a DR COMMISSION line repeats them.
        $this->line('GEN', 'RENT CURRENT', 'CR', 20000, ['commission_amount' => 2000, 'vat_amount' => 320]);
        $this->line('GEN', 'COMMISSION', 'DR', 2000, ['vat_amount' => 320, 'transaction_source' => 'COMPUTED']);
        $service = app(ReturnStatementService::class);

        $old = $service->lineTotals('OLD');
        $this->assertEqualsWithDelta(26796 + 3696 - (3696 + 2310 + 370), $old->total_cr - $old->total_dr, 0.001);
        $this->assertEqualsWithDelta(370, $old->vat, 0.001);
        $this->assertEqualsWithDelta(2310, $old->commission, 0.001);
        $this->assertEqualsWithDelta(3696, $old->deductions, 0.001);

        $generated = $service->lineTotals('GEN');
        $this->assertEqualsWithDelta(17680, $generated->total_cr - $generated->total_dr, 0.001);
        $this->assertEqualsWithDelta(320, $generated->vat, 0.001);
        $this->assertEqualsWithDelta(2000, $generated->commission, 0.001);
    }

    public function test_returns_status_report_derives_status_leaves_out_masters_and_takes_gross_rent_from_the_rent_lines(): void
    {
        $summary = fn(string $code, string $type, int $paid, int $approved, string $prepared) => DB::table('cb_returns_summary')->insert([
            'return_code' => $code, 'return_type' => $type, 'paid_status' => $paid, 'approved_status' => $approved,
            'prepared_date' => $prepared, 'net_amount' => 100, 'year' => 2026, 'month' => 7, 'landlord_code' => 'LAN-1', 'property_code' => 'P1',
        ]);
        $summary('PAID', 'MAINRETURN', 1, 1, '2026-07-10 08:00:00');
        $summary('APPROVED', 'MAINRETURN', 0, 1, '2026-07-12 08:00:00');
        $summary('PENDING', 'MINIRETURN', 0, 0, '2026-08-01 08:00:00');
        $summary('MASTER', 'MASTERRETURN', 1, 1, '2026-07-10 08:00:00');
        DB::table('sys_pm_clients')->insert(['reference_number' => 'LAN-1', 'names' => 'A Landlord']);
        $this->line('PAID', 'RENT CURRENT', 'CR', 1000);
        $this->line('PAID', 'RENT ARREARS', 'CR', 200);
        $this->line('PAID', 'House Deposit', 'CR', 500);
        $this->line('PAID', 'VAT', 'DR', 160);

        $controller = app(AccountsReportsController::class);
        $rows = fn(array $query) => (function () use ($query) { return $this->returnsStatusRows(new Request($query)); })->call($controller)->keyBy('reference_number');

        $all = $rows([]);
        $this->assertSame(['PENDING', 'APPROVED', 'PAID'], $all->keys()->all());
        $this->assertFalse($all->has('MASTER'));
        $this->assertSame(['processed', 'approved', 'pending'], [$all['PAID']->status, $all['APPROVED']->status, $all['PENDING']->status]);
        $this->assertEqualsWithDelta(1200, $all['PAID']->gross_rent, 0.001);
        $this->assertEqualsWithDelta(160, $all['PAID']->vat, 0.001);
        $this->assertSame(['APPROVED'], $rows(['status' => 'approved'])->keys()->all());
        $this->assertSame(['PAID'], $rows(['date_from' => '2026-07-01', 'date_to' => '2026-07-11'])->keys()->all());
    }
}
