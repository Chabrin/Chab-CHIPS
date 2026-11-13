<?php

namespace Tests\Feature;

use App\Models\ContractChange;
use App\Models\PayrollPeriodItem;
use App\Models\PayrollRun;
use App\Models\PayrollSummary;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Monthly Payroll Items page: one-time items for a period, recurring items on contracts, the check before the
 * run, and a real run picking the items up for the right people.
 */
class PayrollMonthlyItemsTest extends TestCase
{
    private const PERIOD = 216;
    private const ALICE = 1;
    private const BOB = 2;
    private const CAROL = 3;       // active, no contract
    private const ALICE_CONTRACT = 10;
    private const BOB_CONTRACT = 11;

    private const BONUS = 1;       // one-time allowance
    private const ABSENCE = 2;     // one-time deduction
    private const WELFARE = 3;     // recurring for all staff, fixed 500
    private const SACCO = 4;       // recurring for specific staff

    protected function setUp(): void
    {
        parent::setUp();

        // The payroll queries use raw table names, so run without the sqlite test prefix like production does.
        config(['database.connections.sqlite.prefix' => '']);
        DB::purge('sqlite');
        Schema::clearResolvedInstance('db.schema');

        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('sys_hr_payroll_periods', function (Blueprint $t) {
            $t->id();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->integer('current_status_id')->default(5);
        });
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('staff_number')->nullable();
            $t->string('full_names')->nullable();
            $t->integer('department_id')->nullable();
            $t->integer('current_status_id')->default(1);
            $t->string('disability')->nullable();
            $t->string('employment_type')->nullable();
        });
        // Saving a contract asks the staff member's category whether the contract has an end date
        (require database_path('migrations/2026_11_10_100000_create_employment_categories_table.php'))->up();
        Schema::create('sys_hr_employee_contracts', function (Blueprint $t) {
            $t->id();
            $t->dateTime('date_time')->nullable();
            $t->integer('staff_id');
            $t->dateTime('date_start')->nullable();
            $t->dateTime('date_end')->nullable();
            $t->integer('current_status_id')->default(5);
            $t->integer('position_id')->nullable();
            $t->integer('created_by')->nullable();
            $t->decimal('basic_salary', 15, 2)->default(0);
            $t->string('staff_number')->nullable();
        });
        (require database_path('migrations/2026_11_10_100200_add_period_and_probation_to_contracts.php'))->up();
        // Saving a contract looks for the signed contract already uploaded with the staff record
        Schema::create('document_types', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('employee_documents', function (Blueprint $t) {
            $t->id();
            $t->integer('employee_id');
            $t->integer('contract_id')->nullable();
            $t->integer('document_type_id');
            $t->string('file_path');
            $t->string('file_name')->nullable();
        });
        Schema::create('sys_hr_employee_contracts_279_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->integer('element_id');
            $t->integer('entry_type_id')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
        });
        // How often each row applies, what a part month does to it, and the company-wide items left out for a person
        (require database_path('migrations/2026_11_11_120000_add_terms_and_exclusions_for_contract_elements.php'))->up();
        Schema::create('sys_hr_payroll_elements', function (Blueprint $t) {
            $t->id();
            $t->string('element_name');
            $t->string('element_key')->nullable();
            $t->string('element_type')->default('1');
            $t->string('formula_id')->nullable();
            $t->decimal('amount', 15, 4)->default(0);
            $t->integer('current_status_id')->default(5);
            $t->integer('payslip_position')->nullable();
        });
        Schema::create('sys_hr_payroll_summary', function (Blueprint $t) {
            $t->id();
            $t->dateTime('date_time')->nullable();
            $t->integer('staff_id')->nullable();
            $t->string('staff_number_id')->nullable();
            $t->date('period')->nullable();
            $t->integer('period_id')->nullable();
            foreach (['basic_salary', 'total_earnings', 'tax_payable', 'nhif', 'nssf', 'nssf_tier1', 'nssf_tier2', 'housing_levy', 'total_deductions', 'net_pay', 'helb_loans', 'worked_days'] as $column) {
                $t->decimal($column, 15, 2)->default(0);
            }
            $t->integer('current_status_id')->default(1);
            $t->integer('department_id')->nullable();
            $t->integer('position_id')->nullable();
            $t->text('payslip_elements')->nullable();
            $t->integer('processed_by')->nullable();
            $t->integer('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
        });
        (require base_path('database/migrations/2026_11_04_000001_create_payroll_runs_tables.php'))->up();
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

        // Adds recurrence/applies_to to the element table and creates the item and change tables
        (require base_path('database/migrations/2026_11_05_000001_add_payroll_element_settings_and_monthly_items.php'))->up();

        DB::table('sys_hr_payroll_periods')->insert(['id' => self::PERIOD, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']);
        DB::table('um_tax_bands')->insert(['range_from' => 0, 'range_to' => null, 'tax_rate' => 10, 'is_last' => true]);
        DB::table('sys_staff')->insert([
            ['id' => self::ALICE, 'staff_number' => 'S1', 'full_names' => 'Alice Adams', 'disability' => 'no'],
            ['id' => self::BOB, 'staff_number' => 'S2', 'full_names' => 'Bob Brown', 'disability' => 'no'],
            ['id' => self::CAROL, 'staff_number' => 'S3', 'full_names' => 'Carol Clark', 'disability' => 'no'],
        ]);
        DB::table('sys_hr_employee_contracts')->insert([
            ['id' => self::ALICE_CONTRACT, 'staff_id' => self::ALICE, 'date_start' => '2026-01-01 00:00:00', 'date_end' => '2026-12-31 00:00:00', 'basic_salary' => 50000, 'staff_number' => 'S1'],
            ['id' => self::BOB_CONTRACT, 'staff_id' => self::BOB, 'date_start' => '2026-01-01 00:00:00', 'date_end' => '2026-12-31 00:00:00', 'basic_salary' => 30000, 'staff_number' => 'S2'],
        ]);

        $elements = [
            [self::BONUS, 'Bonus', '2', 'one_time', 'specific', '1', 0],
            [self::ABSENCE, 'Absenteeism', '1', 'one_time', 'specific', '1', 0],
            [self::WELFARE, 'Social Welfare', '1', 'recurring', 'all', '1', 500],
            [self::SACCO, 'Sacco Loan', '1', 'recurring', 'specific', '1', 0],
        ];
        foreach ($elements as [$id, $name, $type, $recurrence, $appliesTo, $formula, $amount]) {
            DB::table('sys_hr_payroll_elements')->insert([
                'id' => $id, 'element_name' => $name, 'element_type' => $type, 'recurrence' => $recurrence,
                'applies_to' => $appliesTo, 'formula_id' => $formula, 'amount' => $amount, 'payslip_position' => $id * 10,
            ]);
        }
    }

    private function actingWith(array $permissions): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->shouldReceive('hasPermission')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->id = 7;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function actingAsHr(): void
    {
        $this->actingWith(['read_payroll_processing', 'create_payroll_processing', 'update_contracts']);
    }

    private function addItem(array $data)
    {
        return $this->postJson('/ajax/payroll/monthly/' . self::PERIOD . '/items', $data);
    }

    private function checkSection(string $key): ?array
    {
        $sections = $this->getJson('/ajax/payroll/monthly/' . self::PERIOD)->assertOk()->json('check.sections');

        return collect($sections)->firstWhere('key', $key);
    }

    // ── one-time items ────────────────────────────────────────────────

    public function test_a_one_time_item_is_added_and_listed_for_the_period(): void
    {
        $this->actingAsHr();

        $this->addItem(['element_id' => self::BONUS, 'staff_id' => self::ALICE, 'amount' => 20000, 'note' => 'Q3 target'])->assertOk()->assertJson(['success' => true]);

        $data = $this->getJson('/ajax/payroll/monthly/' . self::PERIOD)->assertOk();
        $data->assertJsonPath('items.0.staff', 'Alice Adams')->assertJsonPath('items.0.element', 'Bonus')->assertJsonPath('items.0.amount', 20000)->assertJsonPath('items.0.note', 'Q3 target');
        $this->assertEqualsCanonicalizing(['Bonus', 'Absenteeism'], collect($data->json('elements'))->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['Alice Adams', 'Bob Brown'], collect($data->json('staff'))->pluck('name')->all());
    }

    public function test_only_one_time_elements_can_be_entered_as_items(): void
    {
        $this->actingAsHr();

        $this->addItem(['element_id' => self::WELFARE, 'staff_id' => self::ALICE, 'amount' => 100])
            ->assertStatus(422)->assertJson(['success' => false]);
        $this->assertSame(0, PayrollPeriodItem::count());
    }

    public function test_an_item_needs_a_staff_member_with_a_contract_or_all_staff(): void
    {
        $this->actingAsHr();

        $this->addItem(['element_id' => self::BONUS, 'amount' => 100])->assertStatus(422);
        $this->addItem(['element_id' => self::BONUS, 'staff_id' => self::CAROL, 'amount' => 100])
            ->assertStatus(422)->assertJsonPath('error', 'That staff member has no current contract in this period, so the run would skip the item.');
        $this->addItem(['element_id' => self::BONUS, 'staff_id' => self::ALICE, 'amount' => 0])->assertStatus(422);
        $this->assertSame(0, PayrollPeriodItem::count());
    }

    public function test_an_item_for_all_staff_needs_no_staff_member_and_cannot_be_repeated(): void
    {
        $this->actingAsHr();

        $this->addItem(['element_id' => self::ABSENCE, 'apply_to_all' => 1, 'amount' => 250])->assertOk();
        $this->assertNull(PayrollPeriodItem::firstOrFail()->staff_id);
        $this->addItem(['element_id' => self::ABSENCE, 'apply_to_all' => 1, 'amount' => 300])->assertStatus(422);

        $this->addItem(['element_id' => self::BONUS, 'staff_id' => self::ALICE, 'amount' => 100])->assertOk();
        $this->addItem(['element_id' => self::BONUS, 'staff_id' => self::ALICE, 'amount' => 200])->assertStatus(422);
        $this->addItem(['element_id' => self::BONUS, 'staff_id' => self::BOB, 'amount' => 200])->assertOk();
        $this->assertSame(3, PayrollPeriodItem::count());
    }

    public function test_an_item_can_be_changed_and_removed_until_the_period_is_processed(): void
    {
        $this->actingAsHr();
        $this->addItem(['element_id' => self::BONUS, 'staff_id' => self::ALICE, 'amount' => 100])->assertOk();
        $id = PayrollPeriodItem::firstOrFail()->id;

        $this->postJson("/ajax/payroll/monthly/items/{$id}", ['amount' => 150, 'note' => 'Corrected'])->assertOk();
        $this->assertEquals(150, PayrollPeriodItem::find($id)->amount);

        PayrollSummary::create(['staff_id' => self::ALICE, 'period' => '2026-10-01', 'period_id' => self::PERIOD]);

        $this->postJson("/ajax/payroll/monthly/items/{$id}", ['amount' => 999])->assertStatus(422);
        $this->deleteJson("/ajax/payroll/monthly/items/{$id}")->assertStatus(422);
        $this->addItem(['element_id' => self::BONUS, 'staff_id' => self::BOB, 'amount' => 50])->assertStatus(422);
        $this->assertEquals(150, PayrollPeriodItem::find($id)->amount);
        $this->getJson('/ajax/payroll/monthly/' . self::PERIOD)->assertJsonPath('locked', true);

        PayrollSummary::query()->delete(); // the run was reversed
        $this->deleteJson("/ajax/payroll/monthly/items/{$id}")->assertOk();
        $this->assertSame(0, PayrollPeriodItem::count());
    }

    public function test_without_the_permission_nothing_can_be_changed(): void
    {
        $this->actingWith(['read_payroll_processing']);

        $this->getJson('/ajax/payroll/monthly/' . self::PERIOD)->assertOk()->assertJsonPath('can_edit_items', false)->assertJsonPath('can_edit_recurring', false);
        $this->addItem(['element_id' => self::BONUS, 'staff_id' => self::ALICE, 'amount' => 100])->assertForbidden();
        $this->postJson('/ajax/payroll/monthly/contracts/' . self::ALICE_CONTRACT . '/elements', ['element_id' => self::SACCO, 'amount' => 100])->assertForbidden();

        $this->actingWith([]);
        $this->getJson('/ajax/payroll/monthly/' . self::PERIOD)->assertForbidden();
    }

    // ── recurring items on contracts ──────────────────────────────────

    public function test_a_recurring_item_can_be_added_changed_and_removed_with_a_record_of_each(): void
    {
        $this->actingAsHr();
        $contractRows = '/ajax/payroll/monthly/contracts/' . self::ALICE_CONTRACT . '/elements';

        $this->postJson($contractRows, ['element_id' => self::SACCO, 'amount' => 1000])->assertOk();
        $row = DB::table('sys_hr_employee_contracts_279_repeat')->where('parent_id', self::ALICE_CONTRACT)->first();
        $this->assertSame(1, (int) $row->entry_type_id); // taken from the element
        $this->postJson($contractRows, ['element_id' => self::SACCO, 'amount' => 1])->assertStatus(422); // already there

        $this->postJson("/ajax/payroll/monthly/contract-elements/{$row->id}", ['amount' => 1500])->assertOk();
        $this->assertEquals(1500, DB::table('sys_hr_employee_contracts_279_repeat')->where('id', $row->id)->value('amount'));

        $listed = $this->getJson('/ajax/payroll/monthly/' . self::PERIOD . '/recurring')->assertOk();
        $listed->assertJsonPath('rows.0.staff', 'Alice Adams')->assertJsonPath('rows.0.element', 'Sacco Loan')->assertJsonPath('rows.0.amount', 1500);

        $this->deleteJson("/ajax/payroll/monthly/contract-elements/{$row->id}")->assertOk();
        $this->assertSame(0, DB::table('sys_hr_employee_contracts_279_repeat')->count());

        $this->assertSame(['element_added', 'element_changed', 'element_removed'], ContractChange::orderBy('id')->pluck('field')->all());
        $this->assertSame([[null, '1000.00'], ['1000.00', '1500.00'], ['1500.00', null]], ContractChange::orderBy('id')->get()->map(fn ($c) => [$c->old_value, $c->new_value])->all());
        $this->assertSame([7], ContractChange::pluck('changed_by')->unique()->map(fn ($id) => (int) $id)->all());
    }

    public function test_the_recurring_screen_says_how_often_each_item_applies_and_what_a_part_month_does(): void
    {
        $this->actingAsHr();
        DB::table('sys_hr_employee_contracts_279_repeat')->insert([
            ['parent_id' => self::ALICE_CONTRACT, 'element_id' => self::SACCO, 'entry_type_id' => 1, 'amount' => 1000],
        ]);

        // Nothing chosen: every month, and the run's old rule for a deduction (charged in full)
        $this->getJson('/ajax/payroll/monthly/' . self::PERIOD . '/recurring')->assertOk()
            ->assertJsonPath('rows.0.terms', "Every month \u{00B7} full amount in a part month");

        DB::table('sys_hr_employee_contracts_279_repeat')->update(['frequency' => 'once', 'once_month' => '2026-12-01', 'prorate' => 1]);

        $this->getJson('/ajax/payroll/monthly/' . self::PERIOD . '/recurring')->assertOk()
            ->assertJsonPath('rows.0.terms', "Once only (Dec 2026) \u{00B7} reduced by the days worked in a part month");
    }

    public function test_the_recurring_screen_refuses_one_time_and_all_staff_calculated_items(): void
    {
        $this->actingAsHr();
        DB::table('sys_hr_payroll_elements')->insert(['id' => 9, 'element_name' => 'NSSF', 'element_key' => 'nssf', 'element_type' => '1', 'recurrence' => 'recurring', 'applies_to' => 'all']);
        $contractRows = '/ajax/payroll/monthly/contracts/' . self::ALICE_CONTRACT . '/elements';

        $this->postJson($contractRows, ['element_id' => self::BONUS, 'amount' => 100])->assertStatus(422);
        $this->postJson($contractRows, ['element_id' => 9, 'amount' => 100])->assertStatus(422);
        $this->assertSame(0, DB::table('sys_hr_employee_contracts_279_repeat')->count());
        $this->assertSame(0, ContractChange::count());
    }

    // ── the check before the run ──────────────────────────────────────

    public function test_the_check_names_staff_who_would_be_left_out_and_odd_contracts(): void
    {
        $this->actingAsHr();
        DB::table('sys_hr_employee_contracts')->where('id', self::BOB_CONTRACT)->update(['basic_salary' => 0, 'date_start' => '2026-10-16 00:00:00']);
        DB::table('sys_hr_employee_contracts_279_repeat')->insert(['parent_id' => self::ALICE_CONTRACT, 'element_id' => self::BONUS, 'amount' => 20000]);

        $this->assertSame([['name' => 'Carol Clark', 'detail' => 'S3']], $this->checkSection('no_contract')['rows']);
        $this->assertSame([['name' => 'Bob Brown', 'detail' => 'Basic salary is 0']], $this->checkSection('zero_salary')['rows']);
        $this->assertSame([['name' => 'Bob Brown', 'detail' => 'Paid for 16 of 31 days']], $this->checkSection('partial_month')['rows']);
        $this->assertSame([['name' => 'Bonus', 'detail' => 'Alice Adams']], $this->checkSection('ignored_rows')['rows']);
        $this->assertSame('warning', $this->checkSection('ignored_rows')['level']);
        $this->assertNull($this->checkSection('duplicate_contracts'));
    }

    public function test_the_check_has_nothing_to_flag_for_a_clean_period(): void
    {
        $this->actingAsHr();
        DB::table('sys_staff')->where('id', self::CAROL)->update(['current_status_id' => 3]);

        $data = $this->getJson('/ajax/payroll/monthly/' . self::PERIOD)->assertOk();

        $this->assertSame([], $data->json('check.sections'));
        $this->assertSame(2, $data->json('check.summary.staff_in_run'));
    }

    public function test_the_check_lists_only_the_changes_made_since_the_last_approved_run(): void
    {
        $this->actingAsHr();
        DB::table('sys_hr_payroll_periods')->insert(['id' => 215, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
        PayrollRun::create(['period_id' => 215, 'status' => PayrollRun::STATUS_APPROVED, 'processed_at' => '2026-10-02 09:00:00']);
        $contract = DB::table('sys_hr_employee_contracts')->where('id', self::ALICE_CONTRACT)->first();
        $bob = DB::table('sys_hr_employee_contracts')->where('id', self::BOB_CONTRACT)->first();

        $make = fn ($contract, string $field, $old, $new, string $when, ?int $element = null) => ContractChange::create([
            'contract_id' => $contract->id, 'staff_id' => $contract->staff_id, 'field' => $field, 'element_id' => $element,
            'old_value' => $old, 'new_value' => $new, 'changed_by' => 7, 'created_at' => $when,
        ]);
        $make($contract, 'basic_salary', '40000.00', '45000.00', '2026-09-20 10:00:00'); // before the last run: already paid
        $make($contract, 'basic_salary', '45000.00', '50000.00', '2026-10-10 10:00:00');
        $make($bob, 'element_added', null, '700.00', '2026-10-12 10:00:00', self::SACCO);
        $make($bob, 'element_changed', '700.00', '900.00', '2026-10-13 10:00:00', self::SACCO);
        $make($bob, 'contract_created', null, '30000.00', '2026-10-14 10:00:00');

        $salary = $this->checkSection('salary_changes');
        $this->assertSame([['name' => 'Alice Adams', 'detail' => '45,000.00 to 50,000.00']], $salary['rows']);
        $this->assertStringContainsString('September 2026 payroll was processed', $salary['hint']);
        $this->assertSame(
            [['name' => 'Bob Brown', 'detail' => 'Sacco Loan added at 700.00'], ['name' => 'Bob Brown', 'detail' => 'Sacco Loan: 700.00 to 900.00']],
            $this->checkSection('element_changes')['rows']
        );
        $this->assertSame([['name' => 'Bob Brown', 'detail' => 'Starting salary 30,000.00']], $this->checkSection('new_contracts')['rows']);
    }

    // ── once-only items, and company-wide items left out for one person ──────

    private function contractRow(int $contract, int $element, float $amount, array $terms = []): int
    {
        return DB::table('sys_hr_employee_contracts_279_repeat')->insertGetId(array_merge(
            ['parent_id' => $contract, 'element_id' => $element, 'entry_type_id' => 1, 'amount' => $amount, 'frequency' => 'monthly', 'once_month' => null, 'prorate' => null],
            $terms
        ));
    }

    public function test_the_check_lists_the_once_only_items_this_run_will_take_and_no_others(): void
    {
        $this->actingAsHr();
        // The period is October 2026
        $this->contractRow(self::ALICE_CONTRACT, self::SACCO, 1200, ['frequency' => 'once', 'once_month' => '2026-10-01']);
        $this->contractRow(self::BOB_CONTRACT, self::SACCO, 900, ['frequency' => 'once', 'once_month' => '2026-11-01']);
        $this->contractRow(self::BOB_CONTRACT, self::WELFARE, 500); // every month: not listed

        $section = $this->checkSection('once_only_this_period');

        $this->assertSame([['name' => 'Alice Adams', 'detail' => 'Sacco Loan 1,200.00']], $section['rows']);
        $this->assertSame('info', $section['level']);
        $this->assertNull($this->checkSection('once_only_never'));
    }

    public function test_the_check_warns_about_a_once_only_item_the_run_can_never_take(): void
    {
        $this->actingAsHr();
        // The contracts run January to December 2026
        $this->contractRow(self::ALICE_CONTRACT, self::SACCO, 1200, ['frequency' => 'once', 'once_month' => '2027-03-01']);
        $this->contractRow(self::BOB_CONTRACT, self::SACCO, 900, ['frequency' => 'once', 'once_month' => '2025-12-01']);
        $this->contractRow(self::BOB_CONTRACT, self::WELFARE, 500, ['frequency' => 'once', 'once_month' => null]);

        $section = $this->checkSection('once_only_never');

        $this->assertSame('warning', $section['level']);
        $this->assertSame(
            [['name' => 'Alice Adams', 'detail' => 'Sacco Loan (Mar 2027)'], ['name' => 'Bob Brown', 'detail' => 'Sacco Loan (Dec 2025)'], ['name' => 'Bob Brown', 'detail' => 'Social Welfare (no month)']],
            $section['rows']
        );
    }

    public function test_the_first_and_last_months_of_a_contract_are_not_flagged(): void
    {
        $this->actingAsHr();
        $this->contractRow(self::ALICE_CONTRACT, self::SACCO, 1, ['frequency' => 'once', 'once_month' => '2026-01-01']);
        $this->contractRow(self::BOB_CONTRACT, self::SACCO, 1, ['frequency' => 'once', 'once_month' => '2026-12-01']);

        $this->assertNull($this->checkSection('once_only_never'));
    }

    public function test_an_open_ended_contract_has_no_last_month_to_fall_outside(): void
    {
        $this->actingAsHr();
        DB::table('sys_hr_employee_contracts')->where('id', self::ALICE_CONTRACT)->update(['date_end' => null]);
        $this->contractRow(self::ALICE_CONTRACT, self::SACCO, 1, ['frequency' => 'once', 'once_month' => '2031-06-01']);

        $this->assertNull($this->checkSection('once_only_never'));
    }

    public function test_the_check_lists_the_company_wide_items_left_out_with_their_reasons(): void
    {
        $this->actingAsHr();
        DB::table('contract_element_exclusions')->insert([
            ['contract_id' => self::BOB_CONTRACT, 'element_id' => self::WELFARE, 'reason' => 'Opted out in writing'],
        ]);

        $section = $this->checkSection('left_out_items');

        $this->assertSame([['name' => 'Bob Brown', 'detail' => 'Social Welfare - Opted out in writing']], $section['rows']);
        $this->assertSame('info', $section['level']);
    }

    public function test_an_item_left_out_on_a_contract_that_is_not_in_this_run_is_not_listed(): void
    {
        $this->actingAsHr();
        DB::table('sys_hr_employee_contracts')->insert(['id' => 12, 'staff_id' => self::CAROL, 'date_start' => '2025-01-01 00:00:00', 'date_end' => '2025-12-31 00:00:00', 'basic_salary' => 1, 'staff_number' => 'S3']);
        DB::table('contract_element_exclusions')->insert(['contract_id' => 12, 'element_id' => self::WELFARE, 'reason' => 'Old contract']);

        $this->assertNull($this->checkSection('left_out_items'));
    }

    public function test_the_check_says_in_words_what_changed_in_the_terms_and_the_left_out_items(): void
    {
        $this->actingAsHr();
        $bob = DB::table('sys_hr_employee_contracts')->where('id', self::BOB_CONTRACT)->first();
        $make = fn (string $field, $old, $new, int $element) => ContractChange::create([
            'contract_id' => $bob->id, 'staff_id' => $bob->staff_id, 'field' => $field, 'element_id' => $element,
            'old_value' => $old, 'new_value' => $new, 'changed_by' => 7, 'created_at' => now(),
        ]);
        $make('element_terms', 'Monthly, full amount', 'Once Dec 2026, prorated', self::SACCO);
        $make('item_excluded', null, 'Opted out', self::WELFARE);
        $make('item_included', 'Opted out', null, self::WELFARE);

        $this->assertSame([
            ['name' => 'Bob Brown', 'detail' => 'Sacco Loan: terms changed from Monthly, full amount to Once Dec 2026, prorated'],
            ['name' => 'Bob Brown', 'detail' => 'Social Welfare left out for this person (Opted out)'],
            ['name' => 'Bob Brown', 'detail' => 'Social Welfare put back (was left out: Opted out)'],
        ], $this->checkSection('element_changes')['rows']);
    }

    public function test_an_every_month_row_adds_nothing_to_the_check(): void
    {
        $this->actingAsHr();
        DB::table('sys_staff')->where('id', self::CAROL)->update(['current_status_id' => 3]);
        $this->contractRow(self::ALICE_CONTRACT, self::SACCO, 1000);

        $this->assertSame([], $this->getJson('/ajax/payroll/monthly/' . self::PERIOD)->assertOk()->json('check.sections'));
    }

    public function test_an_item_for_someone_with_no_contract_is_reported_as_not_applied(): void
    {
        $this->actingAsHr();
        PayrollPeriodItem::create(['period_id' => self::PERIOD, 'staff_id' => self::CAROL, 'element_id' => self::BONUS, 'amount' => 500]);

        $this->assertSame([['name' => 'Carol Clark', 'detail' => 'Bonus 500.00']], $this->checkSection('unapplied_items')['rows']);
    }

    // ── a real run ────────────────────────────────────────────────────

    public function test_a_run_applies_one_time_items_and_all_staff_items_to_the_right_people(): void
    {
        $this->actingAsHr();
        $this->addItem(['element_id' => self::BONUS, 'staff_id' => self::ALICE, 'amount' => 20000])->assertOk();
        $this->addItem(['element_id' => self::ABSENCE, 'apply_to_all' => 1, 'amount' => 1000])->assertOk();
        DB::table('sys_hr_employee_contracts_279_repeat')->insert(['parent_id' => self::BOB_CONTRACT, 'element_id' => self::SACCO, 'amount' => 700]);

        $response = $this->postJson('/ajax/payroll/process', ['period_id' => self::PERIOD])->assertOk()->assertJson(['success' => true]);
        $this->assertSame([], $response->json('warnings'));

        $payslip = fn (int $staffId) => collect(PayrollSummary::where('period_id', self::PERIOD)->where('staff_id', $staffId)->firstOrFail()->payslip_elements)
            ->pluck('amount', 'element_name');

        $alice = $payslip(self::ALICE);
        $this->assertEquals(20000, $alice['Bonus']);
        $this->assertEquals(1000, $alice['Absenteeism']);
        $this->assertEquals(500, $alice['Social Welfare']);
        $this->assertArrayNotHasKey('Sacco Loan', $alice->all());

        $bob = $payslip(self::BOB);
        $this->assertArrayNotHasKey('Bonus', $bob->all());
        $this->assertEquals(1000, $bob['Absenteeism']);
        $this->assertEquals(500, $bob['Social Welfare']);
        $this->assertEquals(700, $bob['Sacco Loan']);

        $this->assertEquals(70000, PayrollSummary::where('staff_id', self::ALICE)->value('total_earnings')); // 50,000 basic + 20,000 bonus
        $this->assertEquals(30000, PayrollSummary::where('staff_id', self::BOB)->value('total_earnings'));
        $this->assertSame(0, PayrollSummary::where('staff_id', self::CAROL)->count());
    }

    public function test_a_run_warns_about_a_one_time_row_on_a_contract_and_an_item_it_cannot_apply(): void
    {
        $this->actingAsHr();
        DB::table('sys_hr_employee_contracts_279_repeat')->insert(['parent_id' => self::ALICE_CONTRACT, 'element_id' => self::BONUS, 'amount' => 20000]);
        PayrollPeriodItem::create(['period_id' => self::PERIOD, 'staff_id' => self::CAROL, 'element_id' => self::BONUS, 'amount' => 500]);

        $response = $this->postJson('/ajax/payroll/process', ['period_id' => self::PERIOD])->assertOk();

        $warnings = $response->json('warnings');
        $this->assertContains('Bonus is on the contract of Alice Adams, but the run ignores it. Enter one-time items on Monthly Payroll Items.', $warnings);
        $this->assertContains('Bonus for Carol Clark was not applied because they have no current contract in this period.', $warnings);
        $this->assertEquals(50000, PayrollSummary::where('staff_id', self::ALICE)->value('total_earnings'));
    }

    public function test_items_survive_a_reversed_run_and_apply_again_on_the_next_run(): void
    {
        $this->actingAsHr();
        $this->addItem(['element_id' => self::BONUS, 'staff_id' => self::ALICE, 'amount' => 20000])->assertOk();
        $this->postJson('/ajax/payroll/process', ['period_id' => self::PERIOD])->assertOk();

        PayrollSummary::query()->delete();
        PayrollRun::where('period_id', self::PERIOD)->update(['status' => PayrollRun::STATUS_REVERSED]);

        $this->assertSame(1, PayrollPeriodItem::count());
        $this->postJson('/ajax/payroll/process', ['period_id' => self::PERIOD])->assertOk();
        $this->assertEquals(70000, PayrollSummary::where('staff_id', self::ALICE)->value('total_earnings'));
    }

    // ── the contract screens ──────────────────────────────────────────

    private function contractForm(array $overrides = []): array
    {
        return array_merge([
            'staff_id' => self::ALICE,
            'staff_positions' => 1,
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'basic_salary' => 50000,
        ], $overrides);
    }

    public function test_saving_a_contract_keeps_only_recurring_items_and_logs_the_changes(): void
    {
        $this->actingAsHr();
        DB::table('sys_hr_employee_contracts_279_repeat')->insert([
            ['parent_id' => self::ALICE_CONTRACT, 'element_id' => self::BONUS, 'amount' => 20000],
            ['parent_id' => self::ALICE_CONTRACT, 'element_id' => self::SACCO, 'amount' => 1000],
        ]);

        // The form only offers recurring items, so the old one-time row is not sent back: it is cleared
        $this->put('/hr/contracts/' . self::ALICE_CONTRACT, $this->contractForm([
            'basic_salary' => 55000,
            'elements' => [['element' => self::SACCO, 'entry_type' => '2', 'amount' => 1200]],
        ]))->assertRedirect();

        $rows = DB::table('sys_hr_employee_contracts_279_repeat')->where('parent_id', self::ALICE_CONTRACT)->get();
        $this->assertSame([self::SACCO], $rows->pluck('element_id')->map(fn ($id) => (int) $id)->all());
        $this->assertEquals(1200, $rows[0]->amount);
        $this->assertSame(1, (int) $rows[0]->entry_type_id); // from the element, not the submitted value

        $changes = ContractChange::orderBy('id')->get();
        $this->assertSame(['basic_salary', 'element_changed', 'element_removed'], $changes->pluck('field')->all());
        $this->assertSame(['50000.00', '55000.00'], [$changes[0]->old_value, $changes[0]->new_value]);
    }

    public function test_saving_a_contract_with_a_one_time_item_is_refused_and_changes_nothing(): void
    {
        $this->actingAsHr();

        $this->from('/hr/contracts/create')->put('/hr/contracts/' . self::ALICE_CONTRACT, $this->contractForm([
            'basic_salary' => 99999,
            'elements' => [['element' => self::BONUS, 'amount' => 500]],
        ]))->assertRedirect('/hr/contracts/create')->assertSessionHasErrors('elements');

        $this->assertEquals(50000, DB::table('sys_hr_employee_contracts')->where('id', self::ALICE_CONTRACT)->value('basic_salary'));
        $this->assertSame(0, DB::table('sys_hr_employee_contracts_279_repeat')->count());
        $this->assertSame(0, ContractChange::count());
    }

    public function test_a_new_contract_is_logged_with_its_starting_salary(): void
    {
        $this->actingAsHr();

        $this->post('/hr/contracts/store', $this->contractForm([
            'staff_id' => self::CAROL,
            'basic_salary' => 42000,
            'elements' => [['element' => self::SACCO, 'amount' => 500]],
        ]))->assertRedirect();

        $contract = DB::table('sys_hr_employee_contracts')->where('staff_id', self::CAROL)->first();
        $this->assertNotNull($contract);
        $this->assertSame(1, DB::table('sys_hr_employee_contracts_279_repeat')->where('parent_id', $contract->id)->count());
        $change = ContractChange::firstOrFail();
        $this->assertSame(['contract_created', self::CAROL, '42000.00'], [$change->field, (int) $change->staff_id, $change->new_value]);
    }

    public function test_bulk_edit_logs_a_salary_change_and_applies_the_same_item_rules(): void
    {
        $this->actingAsHr();

        $this->putJson('/hr/contracts/bulk-update', ['contracts' => [
            ['id' => self::ALICE_CONTRACT, 'basic_salary' => 60000],
            ['id' => self::BOB_CONTRACT, 'basic_salary' => 30000],
        ]])->assertOk()->assertJson(['updated' => 2]);

        $changes = ContractChange::all();
        $this->assertCount(1, $changes); // Bob's salary did not change
        $this->assertSame(['basic_salary', '50000.00', '60000.00'], [$changes[0]->field, $changes[0]->old_value, $changes[0]->new_value]);

        $this->putJson('/hr/contracts/' . self::ALICE_CONTRACT . '/elements', ['elements' => [['element_id' => self::BONUS, 'amount' => 100]]])
            ->assertStatus(422)->assertJsonPath('error', 'Bonus is a one-time item. Enter it on Monthly Payroll Items instead of the contract.');
        $this->putJson('/hr/contracts/' . self::ALICE_CONTRACT . '/elements', ['elements' => [['element_id' => self::SACCO, 'amount' => 800]]])->assertOk();

        $this->assertSame(['basic_salary', 'element_added'], ContractChange::orderBy('id')->pluck('field')->all());
        $this->assertEquals(800, DB::table('sys_hr_employee_contracts_279_repeat')->where('parent_id', self::ALICE_CONTRACT)->value('amount'));
    }
}
