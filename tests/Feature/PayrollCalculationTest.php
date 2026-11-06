<?php

namespace Tests\Feature;

use App\Http\Controllers\PayrollController;
use App\Services\NssfCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Exercises PayrollController::calculatePayroll for one employee: HELB loans, prorated deductions,
 * sick leave and the SHA label. The method is private, so it is called through reflection.
 */
class PayrollCalculationTest extends TestCase
{
    private const STAFF_ID = 1;
    private const CONTRACT_ID = 10;
    private const PERIOD_START = '2026-10-01';
    private const PERIOD_END = '2026-10-31';
    private const CONTRACT_HELB_AMOUNT = 4000;

    protected function setUp(): void
    {
        parent::setUp();

        // The sqlite test connection prefixes every table with "um_", which breaks the raw table names in the
        // payroll queries. The production databases have no prefix, so run these tests the same way.
        config(['database.connections.sqlite.prefix' => '']);
        DB::purge('sqlite');
        Schema::clearResolvedInstance('db.schema');

        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('disability')->nullable();
        });
        Schema::create('sys_hr_payroll_elements', function (Blueprint $t) {
            $t->id();
            $t->string('element_name');
            $t->string('element_key')->nullable();
            $t->string('element_type')->default('1');
            $t->integer('payslip_position')->nullable();
        });
        Schema::create('sys_hr_employee_contracts_279_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->integer('element_id');
            $t->decimal('amount', 15, 2)->default(0);
        });
        Schema::create('um_tax_bands', function (Blueprint $t) {
            $t->id();
            $t->decimal('range_from', 15, 2);
            $t->decimal('range_to', 15, 2)->nullable();
            $t->decimal('tax_rate', 5, 2);
            $t->boolean('is_last')->default(false);
        });
        Schema::create('sys_hr_leave_types', function (Blueprint $t) {
            $t->id();
            $t->string('type_name');
        });
        Schema::create('sys_hr_leave_requests', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id');
            $t->integer('leave_type_id');
            $t->integer('current_status_id')->default(5);
        });
        Schema::create('sys_hr_leave_requests_234_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->date('leave_date');
            $t->integer('current_status_id')->default(5);
            $t->decimal('day_fraction', 5, 2)->nullable();
            $t->decimal('pay_rate', 5, 2)->nullable();
        });
        Schema::create('sys_helb_deductions', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id');
            $t->decimal('loan_balance', 15, 2)->default(0);
            $t->decimal('monthly_deduction', 15, 2)->default(0);
            $t->integer('cleared')->default(0);
            $t->integer('suspended')->default(0);
            $t->date('start_deduction_date')->nullable();
        });
        Schema::create('sys_hr_employee_amortized_deductions', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id');
            $t->integer('element_id')->nullable();
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->decimal('initial_deduction', 15, 2)->default(0);
            $t->decimal('monthly_deduction', 15, 2)->default(0);
            $t->integer('cleared')->default(0);
            $t->integer('suspended')->default(0);
            $t->date('deduction_date')->nullable();
        });
        Schema::create('sys_hr_employee_amortized_deductions_252_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->decimal('total_amount', 15, 2)->default(0);
        });

        // HR Settings > NSSF Rates, empty here so the standard figures apply unless a test adds rows
        Schema::create('sys_hr_nssf_rates', function (Blueprint $t) {
            $t->id();
            $t->dateTime('from_date')->nullable();
            $t->decimal('employee_perc', 8, 2)->nullable();
            $t->decimal('lower_earnings_limit', 12, 2)->nullable();
            $t->decimal('upper_earnings_limit', 12, 2)->nullable();
            $t->string('tier1_name')->nullable();
            $t->string('tier2_name')->nullable();
            $t->integer('current_status_id')->nullable();
        });

        DB::table('sys_staff')->insert(['id' => self::STAFF_ID, 'disability' => 'no']);
        DB::table('um_tax_bands')->insert(['range_from' => 0, 'range_to' => null, 'tax_rate' => 10, 'is_last' => true]);
        DB::table('sys_hr_payroll_elements')->insert([
            ['id' => 1, 'element_name' => 'HELB Loan', 'element_key' => 'helb_loans', 'element_type' => '1', 'payslip_position' => 50],
            ['id' => 2, 'element_name' => 'Sacco Loan', 'element_key' => null, 'element_type' => '1', 'payslip_position' => 60],
        ]);
        // The contract carries a HELB amount, used only when the staff member has no HELB loan record at all.
        DB::table('sys_hr_employee_contracts_279_repeat')->insert(['parent_id' => self::CONTRACT_ID, 'element_id' => 1, 'amount' => self::CONTRACT_HELB_AMOUNT]);
    }

    /**
     * Run calculatePayroll for the test employee on a 50,000 basic salary for the whole of a month (October 2026 unless given).
     */
    private function calculate(string $periodStart = self::PERIOD_START, string $periodEnd = self::PERIOD_END): array
    {
        $method = new ReflectionMethod(PayrollController::class, 'calculatePayroll');
        $method->setAccessible(true);
        $days = (int) (new \DateTime($periodStart))->diff(new \DateTime($periodEnd))->days + 1;

        return $method->invoke(
            new PayrollController(new NssfCalculator()),
            'Test Person', 50000, self::STAFF_ID, 'S1', 1, 1, collect(), $periodStart, 216,
            self::CONTRACT_ID, $days, $days, $periodEnd, null, null
        );
    }

    /**
     * The payslip entries with the given name.
     */
    private function entries(array $result, string $name): array
    {
        return array_values(array_filter($result['payslip_elements'], fn ($entry) => $entry['element_name'] === $name));
    }

    private function helbLoan(array $attributes): int
    {
        return DB::table('sys_helb_deductions')->insertGetId(array_merge(
            ['staff_id' => self::STAFF_ID, 'loan_balance' => 5000, 'monthly_deduction' => 1500, 'cleared' => 0, 'suspended' => 0, 'start_deduction_date' => null],
            $attributes
        ));
    }

    private function proratedLoan(array $attributes, float $alreadyRepaid = 0): int
    {
        $id = DB::table('sys_hr_employee_amortized_deductions')->insertGetId(array_merge(
            ['staff_id' => self::STAFF_ID, 'element_id' => 2, 'total_amount' => 6000, 'initial_deduction' => 0, 'monthly_deduction' => 1000, 'cleared' => 0, 'suspended' => 0, 'deduction_date' => '2026-01-01'],
            $attributes
        ));
        if ($alreadyRepaid > 0) {
            DB::table('sys_hr_employee_amortized_deductions_252_repeat')->insert(['parent_id' => $id, 'total_amount' => $alreadyRepaid]);
        }

        return $id;
    }

    public function test_nssf_uses_the_rates_in_force_for_the_pay_period(): void
    {
        DB::table('sys_hr_nssf_rates')->insert([
            ['from_date' => '2026-02-01 00:00:00', 'employee_perc' => 6, 'lower_earnings_limit' => 9000, 'upper_earnings_limit' => 108000, 'current_status_id' => 5],
            ['from_date' => '2026-11-01 00:00:00', 'employee_perc' => 5, 'lower_earnings_limit' => 10000, 'upper_earnings_limit' => 120000, 'current_status_id' => 5],
        ]);

        $october = $this->calculate();
        $november = $this->calculate('2026-11-01', '2026-11-30');

        $this->assertEquals([540, 2460], [$october['nssf_tier1'], $october['nssf_tier2']]);   // 6% up to 9,000, then 6% up to 108,000
        $this->assertEquals([500, 2000], [$november['nssf_tier1'], $november['nssf_tier2']]); // the new row: 5% up to 10,000, then 5% up to 120,000
    }

    public function test_nssf_uses_the_standard_figures_when_no_rate_has_been_entered(): void
    {
        $result = $this->calculate();

        $this->assertEquals([540, 2460], [$result['nssf_tier1'], $result['nssf_tier2']]);
    }

    public function test_the_contract_helb_amount_is_deducted_when_the_staff_member_has_no_loan_record(): void
    {
        $withHelb = $this->calculate();

        $this->assertEquals(self::CONTRACT_HELB_AMOUNT, $withHelb['helb_loans']);
        $this->assertCount(1, $this->entries($withHelb, 'HELB Loan'));
        $this->assertEquals(self::CONTRACT_HELB_AMOUNT, $this->entries($withHelb, 'HELB Loan')[0]['amount']);
        $this->assertNull($withHelb['helb_deduction_info'], 'a contract amount has no loan balance to track');

        DB::table('sys_hr_employee_contracts_279_repeat')->delete();
        $withoutHelb = $this->calculate();
        $this->assertEqualsWithDelta(self::CONTRACT_HELB_AMOUNT, $withHelb['total_deductions'] - $withoutHelb['total_deductions'], 0.01);
    }

    /**
     * Loans that are not currently being repaid; the contract amount must not take their place.
     */
    public static function loansThatAreNotBeingRepaid(): array
    {
        return [
            'cleared' => [['cleared' => 1, 'loan_balance' => 0]],
            'suspended' => [['suspended' => 1]],
            'starts after the period' => [['start_deduction_date' => '2026-11-15']],
            'nothing left to pay' => [['loan_balance' => 0]],
        ];
    }

    #[DataProvider('loansThatAreNotBeingRepaid')]
    public function test_a_loan_that_is_not_being_repaid_stops_the_contract_helb_amount_too(array $loan): void
    {
        $this->helbLoan($loan);

        $result = $this->calculate();

        $this->assertEquals(0, $result['helb_loans']);
        $this->assertSame([], $this->entries($result, 'HELB Loan'));
        $this->assertNull($result['helb_deduction_info']);
    }

    public function test_a_running_loan_is_used_instead_of_the_contract_amount(): void
    {
        $loanId = $this->helbLoan(['loan_balance' => 5000, 'monthly_deduction' => 1500]);

        $result = $this->calculate();

        $this->assertEquals(1500, $result['helb_loans']);
        $this->assertEquals(1500, $this->entries($result, 'HELB Loan')[0]['amount']);
        $this->assertSame($loanId, $result['helb_deduction_info']['parent_id']);
        $this->assertEquals(1500, $result['helb_deduction_info']['deduction_amount']);
    }

    public function test_the_last_loan_installment_never_exceeds_the_balance(): void
    {
        $this->helbLoan(['loan_balance' => 600, 'monthly_deduction' => 1500]);

        $this->assertEquals(600, $this->calculate()['helb_loans']);
    }

    public function test_a_prorated_deduction_is_deducted_in_full_while_enough_is_left(): void
    {
        $id = $this->proratedLoan([], alreadyRepaid: 1000);

        $result = $this->calculate();

        $this->assertEquals(1000, $this->entries($result, 'Sacco Loan')[0]['amount']);
        $this->assertSame($id, $result['prorated_deductions_info'][0]['parent_id']);
        $this->assertEquals(1000, $result['prorated_deductions_info'][0]['deduction_amount']);
    }

    public function test_the_last_prorated_installment_is_capped_at_what_is_left(): void
    {
        // 2,000 owed, 1,500 already repaid, 1,000 a month: only 500 remains.
        $this->proratedLoan(['total_amount' => 2000], alreadyRepaid: 1500);

        $result = $this->calculate();

        $this->assertEquals(500, $this->entries($result, 'Sacco Loan')[0]['amount']);
        $this->assertEquals(500, $result['prorated_deductions_info'][0]['deduction_amount']);
    }

    public function test_the_initial_deduction_counts_as_already_paid(): void
    {
        // 2,000 owed, 1,800 paid up front, 1,000 a month: only 200 remains.
        $this->proratedLoan(['total_amount' => 2000, 'initial_deduction' => 1800]);

        $this->assertEquals(200, $this->entries($this->calculate(), 'Sacco Loan')[0]['amount']);
    }

    public function test_a_fully_repaid_prorated_deduction_is_skipped_even_if_not_marked_cleared(): void
    {
        $this->proratedLoan(['total_amount' => 2000], alreadyRepaid: 2000);

        $result = $this->calculate();

        $this->assertSame([], $this->entries($result, 'Sacco Loan'));
        $this->assertSame([], $result['prorated_deductions_info']);
    }

    public function test_a_prorated_deduction_that_starts_after_the_period_is_not_deducted_yet(): void
    {
        $this->proratedLoan(['deduction_date' => '2026-11-15']);

        $result = $this->calculate();

        $this->assertSame([], $this->entries($result, 'Sacco Loan'));
        $this->assertSame([], $result['prorated_deductions_info']);
    }

    public function test_cleared_and_suspended_prorated_deductions_are_skipped(): void
    {
        $this->proratedLoan(['cleared' => 1]);
        $this->proratedLoan(['suspended' => 1]);

        $this->assertSame([], $this->calculate()['prorated_deductions_info']);
    }

    public function test_the_health_deduction_is_labelled_sha_and_uses_the_2_75_percent_rate(): void
    {
        $result = $this->calculate();

        $sha = $this->entries($result, 'SHA');
        $this->assertCount(1, $sha);
        $this->assertEquals(1375.00, $sha[0]['amount']); // 2.75% of 50,000
        $this->assertEquals(1375.00, $result['nhif']);
        $this->assertSame([], $this->entries($result, 'SHIF (NHIF)'));
    }

    public function test_unpaid_sick_leave_reduces_pay_whatever_the_leave_type_capitalisation(): void
    {
        $typeId = DB::table('sys_hr_leave_types')->insertGetId(['type_name' => 'SICK Leave']);
        $requestId = DB::table('sys_hr_leave_requests')->insertGetId(['staff_id' => self::STAFF_ID, 'leave_type_id' => $typeId, 'current_status_id' => 5]);
        // Half a day on no pay is half an unpaid day. (A fractional day keeps sqlite from doing integer division;
        // the production columns are numeric.)
        DB::table('sys_hr_leave_requests_234_repeat')->insert(['parent_id' => $requestId, 'leave_date' => '2026-10-05', 'day_fraction' => 0.5, 'pay_rate' => 0]);

        $result = $this->calculate();

        $this->assertEquals(0.5, $result['sick_leave_unpaid_days']);
        $this->assertEqualsWithDelta(50000 / 31 * 0.5, $result['sick_leave_deduction'], 0.01);
        $this->assertCount(1, $this->entries($result, 'Sick Leave Pay Adjustment'));
    }

    public function test_leave_that_is_not_sick_leave_does_not_reduce_pay(): void
    {
        $typeId = DB::table('sys_hr_leave_types')->insertGetId(['type_name' => 'Annual Leave']);
        $requestId = DB::table('sys_hr_leave_requests')->insertGetId(['staff_id' => self::STAFF_ID, 'leave_type_id' => $typeId, 'current_status_id' => 5]);
        DB::table('sys_hr_leave_requests_234_repeat')->insert(['parent_id' => $requestId, 'leave_date' => '2026-10-05', 'day_fraction' => 1, 'pay_rate' => 0]);

        $this->assertEquals(0, $this->calculate()['sick_leave_unpaid_days']);
    }
}
