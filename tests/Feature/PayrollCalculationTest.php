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
            $t->string('recurrence')->default('recurring');
            $t->string('applies_to')->default('specific');
            $t->integer('formula_id')->nullable();
            $t->decimal('amount', 15, 4)->default(0);
        });
        Schema::create('sys_hr_employee_contracts_279_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->integer('element_id');
            $t->decimal('amount', 15, 2)->default(0);
        });
        // How often each contract row applies, what a part month does to it, and the company-wide items left out for a person
        (require database_path('migrations/2026_11_11_120000_add_terms_and_exclusions_for_contract_elements.php'))->up();
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
        (require database_path('migrations/2026_11_20_100000_create_scheduled_deduction_tables.php'))->up(); // payroll now asks for the scheduled deductions due
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
        // One insert per element: the columns differ between rows, and SQLite refuses a multi-row insert like that
        foreach ([
            ['id' => 1, 'element_name' => 'HELB Loan', 'element_key' => 'helb_loans', 'element_type' => '1', 'payslip_position' => 50],
            ['id' => 2, 'element_name' => 'Sacco Loan', 'element_key' => null, 'element_type' => '1', 'payslip_position' => 60],
            ['id' => 3, 'element_name' => 'House Allowance', 'element_key' => null, 'element_type' => '2', 'payslip_position' => 5],
            ['id' => 4, 'element_name' => 'Welfare', 'element_key' => null, 'element_type' => '1', 'payslip_position' => 70, 'applies_to' => 'all', 'formula_id' => 1, 'amount' => 200],
            ['id' => 5, 'element_name' => 'Transport', 'element_key' => null, 'element_type' => '2', 'payslip_position' => 6, 'applies_to' => 'all', 'formula_id' => 1, 'amount' => 3000],
            ['id' => 6, 'element_name' => 'NSSF', 'element_key' => 'nssf', 'element_type' => '1', 'payslip_position' => 10, 'applies_to' => 'all'],
        ] as $element) {
            DB::table('sys_hr_payroll_elements')->insert($element);
        }
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

    // ── how often an item applies, and what a part month does to it ─────────────────────────

    private const NOVEMBER_START = '2026-11-01';
    private const NOVEMBER_END = '2026-11-30';
    private const OTHER_CONTRACT_ID = 11;

    private function row(int $element, float $amount, array $terms = [], int $contract = self::CONTRACT_ID): void
    {
        DB::table('sys_hr_employee_contracts_279_repeat')->insert(['parent_id' => $contract, 'element_id' => $element, 'amount' => $amount] + $terms);
    }

    private function companyWide(int ...$elements): \Illuminate\Support\Collection
    {
        return \App\Models\PayrollElement::whereIn('id', $elements)->get();
    }

    /**
     * November 2026 (30 days) for a contract that has run for $activeDays of them, on a 60,000 basic salary, with the given
     * company-wide items.
     */
    private function november(int $activeDays, ?\Illuminate\Support\Collection $recurringAll = null, int $contract = self::CONTRACT_ID, string $periodStart = self::NOVEMBER_START, string $periodEnd = self::NOVEMBER_END, int $periodDays = 30): array
    {
        $method = new ReflectionMethod(PayrollController::class, 'calculatePayroll');
        $method->setAccessible(true);

        return $method->invoke(
            new PayrollController(new NssfCalculator()),
            'Test Person', 60000, self::STAFF_ID, 'S1', 1, 1, collect(), $periodStart, 216,
            $contract, $activeDays, $periodDays, $periodEnd, null, null, $recurringAll
        );
    }

    private function amount(array $result, string $name): ?float
    {
        $found = $this->entries($result, $name);

        return $found ? (float) $found[0]['amount'] : null;
    }

    public function test_the_example_in_the_findings_a_30000_allowance_joining_on_the_16th_of_a_30_day_month(): void
    {
        // Joins on the 16th: the 16th to the 30th is 15 of the 30 days
        $this->row(3, 30000, ['prorate' => true]);
        $prorated = $this->november(15);
        $this->assertSame(15000.0, $this->amount($prorated, 'House Allowance'), 'prorated: KES 15,000');

        DB::table('sys_hr_employee_contracts_279_repeat')->where('element_id', 3)->update(['prorate' => false]);
        $full = $this->november(15);
        $this->assertSame(30000.0, $this->amount($full, 'House Allowance'), 'not prorated: KES 30,000');
    }

    public function test_the_allowance_choice_changes_gross_pay_by_exactly_the_difference(): void
    {
        $this->row(3, 30000, ['prorate' => true]);
        $prorated = $this->november(15);
        DB::table('sys_hr_employee_contracts_279_repeat')->where('element_id', 3)->update(['prorate' => false]);
        $full = $this->november(15);

        $this->assertEqualsWithDelta(15000.0, $full['gross_pay'] - $prorated['gross_pay'], 0.01);
    }

    public function test_a_deduction_can_be_reduced_by_the_days_worked_or_charged_in_full(): void
    {
        $this->row(2, 2000, ['prorate' => true]);
        $this->assertSame(1000.0, $this->amount($this->november(15), 'Sacco Loan'), 'reduced: half the days, half the deduction');

        DB::table('sys_hr_employee_contracts_279_repeat')->where('element_id', 2)->update(['prorate' => false]);
        $this->assertSame(2000.0, $this->amount($this->november(15), 'Sacco Loan'), 'in full');
    }

    public function test_with_no_answer_an_allowance_is_still_reduced_and_a_deduction_still_charged_in_full(): void
    {
        // Rows saved before the choice existed carry no answer, and the run must treat them as it always did
        $this->row(3, 30000);
        $this->row(2, 2000);

        $result = $this->november(15);

        $this->assertSame(15000.0, $this->amount($result, 'House Allowance'));
        $this->assertSame(2000.0, $this->amount($result, 'Sacco Loan'));
    }

    public function test_a_whole_month_is_the_full_amount_whatever_the_answer(): void
    {
        $this->row(3, 30000, ['prorate' => true]);
        $this->row(2, 2000, ['prorate' => true]);

        $result = $this->november(30);

        $this->assertSame(30000.0, $this->amount($result, 'House Allowance'));
        $this->assertSame(2000.0, $this->amount($result, 'Sacco Loan'));
    }

    public function test_the_month_someone_leaves_is_reduced_the_same_way(): void
    {
        $this->row(3, 30000, ['prorate' => true]);

        // Leaves on the 10th: 10 of 30 days
        $this->assertSame(10000.0, $this->amount($this->november(10), 'House Allowance'));
    }

    public function test_the_amounts_are_rounded_to_cents(): void
    {
        $this->row(3, 10000, ['prorate' => true]);

        // 7 of 30 days
        $this->assertSame(2333.33, $this->amount($this->november(7), 'House Allowance'));
    }

    // ── once only ─────────────────────────────────────────────────────────

    public function test_a_once_only_item_appears_in_the_chosen_month_only(): void
    {
        $this->row(3, 5000, ['frequency' => 'once', 'once_month' => '2026-11-01', 'prorate' => false]);

        $this->assertSame(5000.0, $this->amount($this->november(30), 'House Allowance'), 'November');
        $this->assertNull($this->amount($this->november(31, null, self::CONTRACT_ID, '2026-12-01', '2026-12-31', 31), 'House Allowance'), 'December');
        $this->assertNull($this->amount($this->november(31, null, self::CONTRACT_ID, '2026-10-01', '2026-10-31', 31), 'House Allowance'), 'October');
        $this->assertNull($this->amount($this->november(30, null, self::CONTRACT_ID, '2027-11-01', '2027-11-30', 30), 'House Allowance'), 'the same month a year later');
    }

    public function test_an_every_month_item_appears_in_every_month(): void
    {
        $this->row(3, 5000, ['frequency' => 'monthly']);

        foreach ([['2026-10-01', '2026-10-31', 31], ['2026-11-01', '2026-11-30', 30], ['2026-12-01', '2026-12-31', 31]] as [$from, $to, $days]) {
            $this->assertSame(5000.0, $this->amount($this->november($days, null, self::CONTRACT_ID, $from, $to, $days), 'House Allowance'), $from);
        }
    }

    public function test_a_once_only_deduction_is_taken_once_too(): void
    {
        $this->row(2, 1200, ['frequency' => 'once', 'once_month' => '2026-11-01', 'prorate' => false]);

        $this->assertSame(1200.0, $this->amount($this->november(30), 'Sacco Loan'));
        $this->assertNull($this->amount($this->november(31, null, self::CONTRACT_ID, '2026-12-01', '2026-12-31', 31), 'Sacco Loan'));
    }

    public function test_a_once_only_item_can_be_reduced_in_the_month_someone_joins(): void
    {
        $this->row(3, 30000, ['frequency' => 'once', 'once_month' => '2026-11-01', 'prorate' => true]);

        $this->assertSame(15000.0, $this->amount($this->november(15), 'House Allowance'));
    }

    public function test_a_once_only_item_is_taken_by_the_pay_period_that_starts_in_its_month(): void
    {
        $this->row(3, 5000, ['frequency' => 'once', 'once_month' => '2026-11-01', 'prorate' => false]);

        // A period from 16 October to 15 November starts in October, so it is not the November one
        $this->assertNull($this->amount($this->november(31, null, self::CONTRACT_ID, '2026-10-16', '2026-11-15', 31), 'House Allowance'));
        $this->assertSame(5000.0, $this->amount($this->november(30, null, self::CONTRACT_ID, '2026-11-16', '2026-12-15', 30), 'House Allowance'));
    }

    public function test_the_rows_of_another_contract_are_not_taken(): void
    {
        $this->row(3, 5000, [], self::OTHER_CONTRACT_ID);

        $this->assertNull($this->amount($this->november(30), 'House Allowance'));
    }

    // ── company-wide items left out for one person ────────────────────────

    public function test_a_company_wide_item_applies_to_everyone_unless_left_out(): void
    {
        $result = $this->november(30, $this->companyWide(4, 5));

        $this->assertSame(200.0, $this->amount($result, 'Welfare'));
        $this->assertSame(3000.0, $this->amount($result, 'Transport'));
    }

    public function test_leaving_one_item_out_removes_it_for_that_contract_only(): void
    {
        DB::table('contract_element_exclusions')->insert(['contract_id' => self::CONTRACT_ID, 'element_id' => 4, 'reason' => 'Opted out']);

        $excluded = $this->november(30, $this->companyWide(4, 5));
        $someoneElse = $this->november(30, $this->companyWide(4, 5), self::OTHER_CONTRACT_ID);

        $this->assertNull($this->amount($excluded, 'Welfare'), 'left out for this person');
        $this->assertSame(200.0, $this->amount($someoneElse, 'Welfare'), 'still applied to everyone else');
    }

    public function test_leaving_one_item_out_does_not_touch_the_other_company_wide_items(): void
    {
        DB::table('contract_element_exclusions')->insert(['contract_id' => self::CONTRACT_ID, 'element_id' => 4, 'reason' => 'Opted out']);

        $this->assertSame(3000.0, $this->amount($this->november(30, $this->companyWide(4, 5)), 'Transport'));
    }

    public function test_an_item_left_out_leaves_net_pay_higher_by_its_amount(): void
    {
        $with = $this->november(30, $this->companyWide(4));
        DB::table('contract_element_exclusions')->insert(['contract_id' => self::CONTRACT_ID, 'element_id' => 4, 'reason' => 'Opted out']);
        $without = $this->november(30, $this->companyWide(4));

        $this->assertEqualsWithDelta(200.0, $without['net_salary'] - $with['net_salary'], 0.01);
    }

    public function test_leaving_out_an_allowance_lowers_gross_pay_by_its_amount(): void
    {
        $with = $this->november(30, $this->companyWide(5));
        DB::table('contract_element_exclusions')->insert(['contract_id' => self::CONTRACT_ID, 'element_id' => 5, 'reason' => 'Gets a company car']);
        $without = $this->november(30, $this->companyWide(5));

        $this->assertEqualsWithDelta(3000.0, $with['gross_pay'] - $without['gross_pay'], 0.01);
    }

    public function test_an_exclusion_does_not_stop_statutory_deductions(): void
    {
        // Even a row put straight into the table for a statutory item cannot take NSSF, SHA, Housing Levy or PAYE away
        DB::table('contract_element_exclusions')->insert(['contract_id' => self::CONTRACT_ID, 'element_id' => 6, 'reason' => 'Should not work']);

        $result = $this->november(30, $this->companyWide(4, 6));

        $this->assertGreaterThan(0, $result['nssf']);
        $this->assertGreaterThan(0, $result['nhif']);
        $this->assertGreaterThan(0, $result['housing_levy']);
        $this->assertGreaterThan(0, $result['paye']);
    }

    public function test_the_persons_own_amount_replaces_the_company_amount_and_the_exclusion_is_not_needed(): void
    {
        $this->row(4, 500);

        $this->assertSame(500.0, $this->amount($this->november(30, $this->companyWide(4)), 'Welfare'));
    }

    public function test_a_once_only_own_amount_replaces_the_company_amount_in_its_month_only(): void
    {
        $this->row(4, 500, ['frequency' => 'once', 'once_month' => '2026-11-01', 'prorate' => false]);

        $this->assertSame(500.0, $this->amount($this->november(30, $this->companyWide(4)), 'Welfare'), 'November: the person\'s own amount');
        $this->assertSame(200.0, $this->amount($this->november(31, $this->companyWide(4), self::CONTRACT_ID, '2026-12-01', '2026-12-31', 31), 'Welfare'), 'December: the company amount as usual');
    }

    public function test_a_contract_with_nothing_left_out_is_paid_the_company_items_as_before(): void
    {
        $this->assertSame(0, DB::table('contract_element_exclusions')->count());

        $result = $this->november(30, $this->companyWide(4, 5));

        $this->assertSame(200.0, $this->amount($result, 'Welfare'));
        $this->assertSame(3000.0, $this->amount($result, 'Transport'));
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


    // ── scheduled deductions (UAT A6) ───────────────────────────────────────────────────────

    private function scheduledDeduction(array $attributes = []): int
    {
        return DB::table('sys_hr_scheduled_deductions')->insertGetId(array_merge([
            'staff_id' => self::STAFF_ID, 'type_id' => 2, 'total_amount' => 21500, 'monthly_amount' => 2000, 'months' => 11,
            'start_month' => '2026-10-01', 'end_month' => '2027-08-01', 'balance' => 21500, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    public function test_an_active_scheduled_deduction_is_taken_after_tax_and_lowers_net_pay(): void
    {
        $without = $this->calculate();
        $id = $this->scheduledDeduction();

        $result = $this->calculate();

        $this->assertEqualsWithDelta(2000, $result['total_deductions'] - $without['total_deductions'], 0.01);
        $this->assertEqualsWithDelta(2000, $without['net_salary'] - $result['net_salary'], 0.01);
        $this->assertEqualsWithDelta($without['paye'], $result['paye'], 0.01, 'a scheduled deduction does not change the tax');
        $entry = $this->entries($result, 'Salary advance')[0];
        $this->assertEquals(2000, $entry['amount']);
        $this->assertSame($id, $entry['scheduled_deduction_id']);
        $this->assertEquals(19500, $entry['balance_after']);
        $this->assertCount(1, $result['scheduled_deductions_info']);
        $this->assertSame($id, $result['scheduled_deductions_info'][0]['parent_id']);
        $this->assertEquals(2000, $result['scheduled_deductions_info'][0]['deduction_amount']);
        $this->assertSame('2026-10-01', $result['scheduled_deductions_info'][0]['deduction_date']);
        $this->assertEquals(21500, DB::table('sys_hr_scheduled_deductions')->value('balance'), 'nothing is written until the run is approved');
    }

    public static function scheduledDeductionsThatAreNotTaken(): array
    {
        return [
            'on hold, no signed consent form' => [['status' => 'on_hold']],
            'paused' => [['status' => 'paused']],
            'closed early' => [['status' => 'closed']],
            'paid off' => [['status' => 'completed', 'balance' => 0]],
            'nothing left owed' => [['balance' => 0]],
            'its first month has not come' => [['start_month' => '2026-11-01']],
        ];
    }

    #[DataProvider('scheduledDeductionsThatAreNotTaken')]
    public function test_a_scheduled_deduction_that_is_not_running_is_not_taken(array $attributes): void
    {
        $baseline = $this->calculate();
        $this->scheduledDeduction($attributes);

        $result = $this->calculate();

        $this->assertSame([], $this->entries($result, 'Salary advance'));
        $this->assertSame([], $result['scheduled_deductions_info']);
        $this->assertEqualsWithDelta($baseline['total_deductions'], $result['total_deductions'], 0.001);
    }

    public function test_a_scheduled_deduction_is_taken_in_the_month_it_starts(): void
    {
        $this->scheduledDeduction(['start_month' => '2026-10-01']);

        $this->assertCount(1, $this->entries($this->calculate(), 'Salary advance'));
    }

    public function test_the_last_month_takes_only_what_is_left(): void
    {
        $this->scheduledDeduction(['balance' => 1500]);

        $result = $this->calculate();

        $this->assertEquals(1500, $this->entries($result, 'Salary advance')[0]['amount']);
        $this->assertEquals(0, $this->entries($result, 'Salary advance')[0]['balance_after']);
    }

    public function test_several_scheduled_deductions_are_each_taken_and_another_persons_are_not(): void
    {
        $this->scheduledDeduction(['monthly_amount' => 2000]);
        $this->scheduledDeduction(['type_id' => 1, 'monthly_amount' => 700, 'balance' => 5000]);
        DB::table('sys_staff')->insert(['id' => 2, 'disability' => 'no']);
        $this->scheduledDeduction(['staff_id' => 2, 'monthly_amount' => 9999]);

        $result = $this->calculate();

        $this->assertEquals(2000, $this->entries($result, 'Salary advance')[0]['amount']);
        $this->assertEquals(700, $this->entries($result, 'Sacco / Akiba loan')[0]['amount']);
        $this->assertCount(2, $result['scheduled_deductions_info']);
    }


    public function test_a_scheduled_deduction_leaves_the_helb_amount_on_the_contract_alone(): void
    {
        $this->scheduledDeduction(['type_id' => 1, 'monthly_amount' => 1500]);

        $result = $this->calculate();

        $this->assertEquals(self::CONTRACT_HELB_AMOUNT, $result['helb_loans']);
        $this->assertEquals(1500, $this->entries($result, 'Sacco / Akiba loan')[0]['amount']);
    }

    // ── HELB: the payslip says what is still owed to HELB ───────────────────────────────────

    public function test_the_helb_line_on_the_payslip_carries_what_is_still_owed_after_this_month(): void
    {
        $this->helbLoan(['loan_balance' => 5000, 'monthly_deduction' => 1500]);

        $entry = $this->entries($this->calculate(), 'HELB Loan')[0];

        $this->assertEquals(1500, $entry['amount']);
        $this->assertEquals(3500, $entry['balance_after']);
    }

    public function test_the_last_helb_instalment_takes_only_what_is_left_and_leaves_nothing_owed(): void
    {
        $this->helbLoan(['loan_balance' => 1000, 'monthly_deduction' => 1500]);

        $entry = $this->entries($this->calculate(), 'HELB Loan')[0];

        $this->assertEquals(1000, $entry['amount']);
        $this->assertEquals(0, $entry['balance_after']);
    }

    public function test_a_helb_amount_that_is_only_on_the_contract_has_no_balance_to_show(): void
    {
        $entry = $this->entries($this->calculate(), 'HELB Loan')[0];

        $this->assertEquals(self::CONTRACT_HELB_AMOUNT, $entry['amount']);
        $this->assertArrayNotHasKey('balance_after', $entry, 'no loan record, so nothing is tracked');
    }
}
