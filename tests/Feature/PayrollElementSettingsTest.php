<?php

namespace Tests\Feature;

use App\Http\Controllers\PayrollController;
use App\Http\Controllers\StaffController;
use App\Models\Contract;
use App\Models\ContractChange;
use App\Models\PayrollElement;
use App\Models\PayrollPeriodItem;
use App\Models\User;
use App\Services\NssfCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The "how often" and "applies to" settings on payroll elements: how the run treats recurring items for all
 * staff, contract rows for one-time items and one-time items entered for a month, the element screens' rules,
 * the contract element checks, the change log and the two migrations.
 */
class PayrollElementSettingsTest extends TestCase
{
    private const STAFF_ID = 1;
    private const CONTRACT_ID = 10;

    private const BONUS = 1;            // one-time allowance
    private const BASIC_ROW = 2;        // the Basic Salary element
    private const WELFARE = 3;          // recurring for all staff, fixed 500
    private const LEVY = 4;             // recurring for all staff, 2% of basic salary
    private const SACCO = 5;            // recurring for specific staff
    private const TRANSPORT = 6;        // recurring for all staff, fixed 3,100 allowance
    private const PENSION = 7;          // recurring for all staff, 1% of gross payment
    private const ABSENCE = 8;          // one-time deduction
    private const HOUSING = 9;          // recurring allowance for specific staff
    private const NSSF = 10;            // built-in statutory item
    private const SHA = 11;
    private const HOUSING_LEVY = 12;

    protected function setUp(): void
    {
        parent::setUp();

        // The payroll queries use raw table names, so run without the sqlite test prefix like production does.
        config(['database.connections.sqlite.prefix' => '']);
        DB::purge('sqlite');
        Schema::clearResolvedInstance('db.schema');

        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('disability')->nullable();
        });
        // The element table as it was before the two settings existed; the migration below adds them
        Schema::create('sys_hr_payroll_elements', function (Blueprint $t) {
            $t->id();
            $t->string('element_name');
            $t->string('element_key')->nullable();
            $t->string('element_type')->default('1');
            $t->string('formula_id')->nullable();
            $t->decimal('amount', 15, 4)->default(0);
            $t->integer('current_status_id')->default(5);
            $t->integer('payslip_position')->nullable();
            $t->integer('account_id')->nullable();
            $t->integer('staff_positions_id')->nullable();
            $t->integer('created_by_id')->nullable();
            $t->timestamp('date_time')->nullable();
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

        DB::table('sys_staff')->insert(['id' => self::STAFF_ID, 'disability' => 'no']);
        DB::table('um_tax_bands')->insert(['range_from' => 0, 'range_to' => null, 'tax_rate' => 10, 'is_last' => true]);

        $elements = [
            [self::BONUS, 'Bonus', null, '2', null, 0],
            [self::BASIC_ROW, 'Basic Salary', 'basic_salary', '2', '3', 0],
            [self::WELFARE, 'Social Welfare', null, '1', '1', 500],
            [self::LEVY, 'Staff Levy', null, '1', '2', 2],
            [self::SACCO, 'Sacco Loan', null, '1', '1', 0],
            [self::TRANSPORT, 'Transport Allowance', null, '2', '1', 3100],
            [self::PENSION, 'Pension', null, '1', '3', 1],
            [self::ABSENCE, 'Absenteeism', null, '1', '1', 0],
            [self::HOUSING, 'Housing Allowance', null, '2', '1', 0],
            [self::NSSF, 'NSSF', 'nssf', '1', '4', 0],
            [self::SHA, 'SHA', 'nhif', '1', '1', 0],
            [self::HOUSING_LEVY, 'Housing Levy', 'housing_levy', '1', '1', 0],
        ];
        foreach ($elements as [$id, $name, $key, $type, $formula, $amount]) {
            DB::table('sys_hr_payroll_elements')->insert([
                'id' => $id, 'element_name' => $name, 'element_key' => $key, 'element_type' => $type,
                'formula_id' => $formula, 'amount' => $amount, 'payslip_position' => $id * 10,
            ]);
        }

        // Adds recurrence/applies_to and flags the statutory items, then the test elements are set below
        (require base_path('database/migrations/2026_11_05_000001_add_payroll_element_settings_and_monthly_items.php'))->up();

        foreach ([self::BONUS, self::ABSENCE] as $id) {
            DB::table('sys_hr_payroll_elements')->where('id', $id)->update(['recurrence' => 'one_time']);
        }
        foreach ([self::WELFARE, self::LEVY, self::TRANSPORT, self::PENSION] as $id) {
            DB::table('sys_hr_payroll_elements')->where('id', $id)->update(['applies_to' => 'all']);
        }
    }

    private function element(int $id): PayrollElement
    {
        return PayrollElement::findOrFail($id);
    }

    /**
     * calculatePayroll for one employee on a 50,000 basic salary for October 2026 (31 days).
     */
    private function calculate(array $contractRows = [], array $allStaff = [], array $items = [], int $activeDays = 31): array
    {
        DB::table('sys_hr_employee_contracts_279_repeat')->where('parent_id', self::CONTRACT_ID)->delete();
        foreach ($contractRows as $elementId => $amount) {
            DB::table('sys_hr_employee_contracts_279_repeat')->insert(['parent_id' => self::CONTRACT_ID, 'element_id' => $elementId, 'amount' => $amount]);
        }

        $method = new ReflectionMethod(PayrollController::class, 'calculatePayroll');
        $method->setAccessible(true);

        return $method->invoke(
            new PayrollController(new NssfCalculator()),
            'Test Person', 50000, self::STAFF_ID, 'S1', 1, 1, collect(), '2026-10-01', 216,
            self::CONTRACT_ID, $activeDays, 31, '2026-10-31', null, null,
            collect(array_map(fn ($id) => $this->element($id), $allStaff)),
            collect($items)
        );
    }

    private function item(int $elementId, float $amount, ?int $staffId = null): PayrollPeriodItem
    {
        $item = new PayrollPeriodItem(['period_id' => 216, 'staff_id' => $staffId, 'element_id' => $elementId, 'amount' => $amount]);
        $item->setRelation('element', $this->element($elementId));

        return $item;
    }

    private function entries(array $result, string $name): array
    {
        return array_values(array_filter($result['payslip_elements'], fn ($entry) => $entry['element_name'] === $name));
    }

    // ── the payroll run ────────────────────────────────────────────────

    public function test_a_contract_row_for_a_one_time_element_is_ignored(): void
    {
        $result = $this->calculate([self::BONUS => 10000]);

        $this->assertSame([], $this->entries($result, 'Bonus'));
        $this->assertEquals(50000, $result['gross_pay']);
    }

    public function test_a_basic_salary_row_on_a_contract_is_not_paid_twice(): void
    {
        $result = $this->calculate([self::BASIC_ROW => 30000]);

        $this->assertSame([], $this->entries($result, 'Basic Salary'));
        $this->assertEquals(50000, $result['gross_pay']);
    }

    public function test_an_all_staff_item_applies_without_a_contract_row(): void
    {
        $without = $this->calculate();
        $with = $this->calculate([], [self::WELFARE]);

        $entries = $this->entries($with, 'Social Welfare');
        $this->assertCount(1, $entries);
        $this->assertEquals(500, $entries[0]['amount']);
        $this->assertEquals($without['net_salary'] - 500, $with['net_salary']);
    }

    public function test_percent_items_follow_basic_salary_and_gross_payment(): void
    {
        $result = $this->calculate([self::HOUSING => 5000], [self::LEVY, self::PENSION]);

        $this->assertEquals(1000, $this->entries($result, 'Staff Levy')[0]['amount']);    // 2% of 50,000 basic
        $this->assertEquals(550, $this->entries($result, 'Pension')[0]['amount']);        // 1% of 55,000 gross
    }

    public function test_a_contract_row_replaces_the_all_staff_amount_instead_of_adding_to_it(): void
    {
        $entries = $this->entries($this->calculate([self::WELFARE => 300], [self::WELFARE]), 'Social Welfare');

        $this->assertCount(1, $entries);
        $this->assertEquals(300, $entries[0]['amount']);
    }

    public function test_a_fixed_all_staff_allowance_follows_the_days_worked(): void
    {
        $result = $this->calculate([], [self::TRANSPORT], [], 16);

        $this->assertEquals(round(3100 * 16 / 31, 2), $this->entries($result, 'Transport Allowance')[0]['amount']);
        $this->assertEquals(round(50000 * 16 / 31, 2) + round(3100 * 16 / 31, 2), $result['gross_pay']);
    }

    public function test_one_time_items_are_taken_as_entered_and_not_pro_rated(): void
    {
        $plain = $this->calculate([], [], [], 16);
        $result = $this->calculate([], [], [$this->item(self::BONUS, 20000), $this->item(self::ABSENCE, 1000)], 16);

        $this->assertEquals(20000, $this->entries($result, 'Bonus')[0]['amount']);
        $this->assertEquals(round(50000 * 16 / 31, 2) + 20000, $result['gross_pay']);
        $this->assertEquals(1000, $this->entries($result, 'Absenteeism')[0]['amount']);
        $this->assertEquals($plain['gross_pay'] + 20000, $result['gross_pay']);
    }

    public function test_an_absence_deduction_lowers_net_pay_by_exactly_its_amount(): void
    {
        $plain = $this->calculate();
        $result = $this->calculate([], [], [$this->item(self::ABSENCE, 1000)]);

        $this->assertEquals($plain['net_salary'] - 1000, $result['net_salary']);
        $this->assertEquals($plain['paye'], $result['paye']);
    }

    public function test_a_statutory_element_is_never_applied_as_a_one_time_item(): void
    {
        $plain = $this->calculate();
        $result = $this->calculate([], [], [$this->item(self::NSSF, 9999)]);

        $this->assertSame([], $this->entries($result, 'NSSF'));
        $this->assertEquals($plain['total_deductions'], $result['total_deductions']);
    }

    // ── the element screen ────────────────────────────────────────────

    private function actingWithElementPermissions(array $permissions = ['read_payroll_elements', 'create_payroll_elements', 'update_payroll_elements']): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->id = 5;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function elementData(array $overrides = []): array
    {
        return array_merge([
            'element_name' => 'Staff Welfare',
            'element_type' => '1',
            'formula' => '1',
            'amount' => 200,
            'recurrence' => 'recurring',
            'applies_to' => 'specific',
            'payslip_position' => 5,
        ], $overrides);
    }

    public function test_a_new_element_saves_how_often_and_who_it_applies_to(): void
    {
        $this->actingWithElementPermissions();

        $this->postJson('/ajax/save/payroll/elements', $this->elementData(['element_name' => 'Christmas Bonus', 'recurrence' => 'one_time', 'applies_to' => 'all']))
            ->assertOk()->assertJson(['success' => true]);

        $saved = PayrollElement::where('element_name', 'Christmas Bonus')->firstOrFail();
        $this->assertSame('one_time', $saved->recurrence);
        $this->assertSame('all', $saved->applies_to);
        $this->assertTrue($saved->isOneTime());
    }

    public function test_both_settings_are_required(): void
    {
        $this->actingWithElementPermissions();

        $this->postJson('/ajax/save/payroll/elements', array_diff_key($this->elementData(), ['recurrence' => 1, 'applies_to' => 1]))
            ->assertStatus(422)->assertJsonValidationErrors(['recurrence', 'applies_to']);
        $this->postJson('/ajax/save/payroll/elements', $this->elementData(['recurrence' => 'sometimes']))
            ->assertStatus(422)->assertJsonValidationErrors(['recurrence']);
    }

    public function test_an_item_that_repeats_for_all_staff_needs_a_formula_the_run_can_use(): void
    {
        $this->actingWithElementPermissions();

        $this->postJson('/ajax/save/payroll/elements', $this->elementData(['applies_to' => 'all', 'formula' => '5']))
            ->assertStatus(422)->assertJsonPath('errors.applies_to.0', 'An item that repeats for all staff needs the Fixed, Percent of basic salary or Percent of gross payment formula.');
        $this->postJson('/ajax/save/payroll/elements', $this->elementData(['applies_to' => 'all', 'formula' => '2']))->assertOk();
        // A one-time item or one for specific staff takes its amount on entry, so any formula is allowed
        $this->postJson('/ajax/save/payroll/elements', $this->elementData(['element_name' => 'Arrears', 'recurrence' => 'one_time', 'applies_to' => 'all', 'formula' => '5']))->assertOk();
    }

    public function test_an_element_can_change_its_settings_but_a_statutory_one_cannot(): void
    {
        $this->actingWithElementPermissions();

        $this->postJson('/ajax/payroll/elements/' . self::SACCO, $this->elementData(['element_name' => 'Sacco Loan', 'recurrence' => 'one_time']))->assertOk();
        $this->assertSame('one_time', $this->element(self::SACCO)->recurrence);

        $this->postJson('/ajax/payroll/elements/' . self::NSSF, $this->elementData(['element_name' => 'NSSF', 'formula' => '4', 'recurrence' => 'one_time', 'applies_to' => 'all']))
            ->assertStatus(422)->assertJsonPath('errors.applies_to.0', 'NSSF is calculated by the payroll run for every staff member, so it must stay recurring for all staff.');
        $this->postJson('/ajax/payroll/elements/' . self::NSSF, $this->elementData(['element_name' => 'NSSF', 'formula' => '4', 'applies_to' => 'specific']))->assertStatus(422);
        $this->assertSame('recurring', $this->element(self::NSSF)->recurrence);
        $this->assertSame('all', $this->element(self::NSSF)->applies_to);
        // Editing it without touching the settings is fine
        $this->postJson('/ajax/payroll/elements/' . self::NSSF, $this->elementData(['element_name' => 'NSSF', 'formula' => '4', 'applies_to' => 'all']))->assertOk();
    }

    public function test_the_element_lists_for_the_forms_only_offer_what_fits(): void
    {
        $this->actingWithElementPermissions();

        $names = fn ($url) => collect($this->getJson($url)->assertOk()->json('elements'))->pluck('element_name')->all();

        $forContract = $names('/ajax/payroll/elements/list/simpler?for=contract');
        $this->assertContains('Sacco Loan', $forContract);
        $this->assertContains('Social Welfare', $forContract); // an all-staff item may be given its own amount
        $this->assertNotContains('Bonus', $forContract);        // one-time
        $this->assertNotContains('NSSF', $forContract);         // statutory
        $this->assertNotContains('Basic Salary', $forContract); // system element

        $this->assertEqualsCanonicalizing(['Bonus', 'Absenteeism'], $names('/ajax/payroll/elements/list/simpler?for=monthly'));
        $this->assertCount(12, $names('/ajax/payroll/elements/list/simpler')); // unchanged for the other pages
    }

    public function test_the_all_staff_preview_works_out_the_statutory_items_like_the_run(): void
    {
        $this->actingWithElementPermissions();
        DB::table('sys_hr_payroll_elements')->whereIn('id', [self::NSSF, self::SHA, self::HOUSING_LEVY])->update(['applies_to' => 'all']);

        $rows = collect($this->getJson('/ajax/payroll/elements/all-staff-preview?basic=50000')->assertOk()->json('elements'))->keyBy('name');

        $this->assertEquals(1375, $rows['SHA']['amount']);           // 2.75% of gross
        $this->assertEquals(750, $rows['Housing Levy']['amount']);   // 1.5% of gross
        $this->assertEquals((new NssfCalculator())->calculate(50000)['total'], $rows['NSSF']['amount']);
        $this->assertEquals(500, $rows['Social Welfare']['amount']);
        $this->assertEquals(1000, $rows['Staff Levy']['amount']);
        $this->assertTrue($rows['NSSF']['statutory']);
        $this->assertArrayNotHasKey('Basic Salary', $rows->all());

        $empty = collect($this->getJson('/ajax/payroll/elements/all-staff-preview?basic=0')->json('elements'));
        $this->assertTrue($empty->every(fn ($row) => $row['amount'] === null));
    }

    public function test_the_all_staff_preview_splits_nssf_into_its_tiers(): void
    {
        $this->actingWithElementPermissions();
        DB::table('sys_hr_payroll_elements')->whereIn('id', [self::NSSF, self::SHA, self::HOUSING_LEVY])->update(['applies_to' => 'all']);

        $rows = collect($this->getJson('/ajax/payroll/elements/all-staff-preview?basic=50000')->assertOk()->json('elements'))->keyBy('name');

        $tiers = $rows['NSSF']['breakdown'];
        $this->assertSame(['Tier I (NSSF)', 'Tier II (Britam)'], array_column($tiers, 'name'));
        $this->assertEquals([540, 2460], array_column($tiers, 'amount'));
        $this->assertSame(['6% of pay up to 9,000', '6% of pay from 9,000 up to 108,000'], array_column($tiers, 'how'));
        $this->assertEquals($rows['NSSF']['amount'], array_sum(array_column($tiers, 'amount'))); // the total is the tiers added up
        $this->assertNull($rows['SHA']['breakdown']);
        $this->assertNull($rows['Housing Levy']['breakdown']);
    }

    public function test_pay_inside_tier_one_shows_nothing_for_tier_two_and_no_pay_shows_no_amounts(): void
    {
        $this->actingWithElementPermissions();
        DB::table('sys_hr_payroll_elements')->where('id', self::NSSF)->update(['applies_to' => 'all']);
        $nssf = fn (int $basic) => collect($this->getJson("/ajax/payroll/elements/all-staff-preview?basic={$basic}")->assertOk()->json('elements'))->firstWhere('name', 'NSSF');

        $small = $nssf(5000);
        $this->assertEquals([300, 0], array_column($small['breakdown'], 'amount'));
        $this->assertEquals(300, $small['amount']);

        $none = $nssf(0);
        $this->assertSame([null, null], array_column($none['breakdown'], 'amount'));
        $this->assertNull($none['amount']);
    }

    // ── contract rows ─────────────────────────────────────────────────

    private function contractRows(array $elementIds): array
    {
        $method = new ReflectionMethod(StaffController::class, 'contractElementRows');
        $method->setAccessible(true);

        return $method->invoke(app(StaffController::class), array_map(fn ($id) => ['element' => $id, 'amount' => 100], $elementIds));
    }

    public function test_a_contract_only_accepts_recurring_items_and_takes_the_entry_type_from_the_element(): void
    {
        $rows = $this->contractRows([self::SACCO, self::HOUSING, self::WELFARE]);

        $this->assertSame([1, 2, 1], array_column($rows, 'entry_type_id'));
        $this->assertSame([self::SACCO, self::HOUSING, self::WELFARE], array_column($rows, 'element_id'));
    }

    public function test_a_contract_refuses_one_time_statutory_system_and_repeated_items(): void
    {
        $messages = function (array $ids) {
            try {
                $this->contractRows($ids);
            } catch (ValidationException $e) {
                return $e->errors()['elements'];
            }
            $this->fail('The contract rows were accepted.');
        };

        $this->assertStringContainsString('Bonus is a one-time item', $messages([self::BONUS])[0]);
        $this->assertStringContainsString('NSSF is calculated by the payroll run', $messages([self::NSSF])[0]);
        $this->assertStringContainsString('comes from the contract\'s basic salary', $messages([self::BASIC_ROW])[0]);
        $this->assertStringContainsString('Sacco Loan is added more than once', $messages([self::SACCO, self::SACCO])[0]);
        $this->assertStringContainsString('no longer exists', $messages([999])[0]);
    }

    // ── the change log ────────────────────────────────────────────────

    private function contract(): Contract
    {
        $contract = new Contract(['staff_id' => self::STAFF_ID]);
        $contract->id = self::CONTRACT_ID;

        return $contract;
    }

    public function test_a_salary_change_is_recorded_once_and_a_repeat_of_the_same_salary_is_not(): void
    {
        ContractChange::salary($this->contract(), '50000.00', 50000, 5);
        $this->assertSame(0, ContractChange::count());

        ContractChange::salary($this->contract(), '50000.00', '55000', 5);
        $change = ContractChange::firstOrFail();
        $this->assertSame(['basic_salary', '50000.00', '55000.00', 5], [$change->field, $change->old_value, $change->new_value, $change->changed_by]);
        $this->assertSame(self::CONTRACT_ID, (int) $change->contract_id);
    }

    public function test_added_changed_and_removed_items_are_each_recorded(): void
    {
        ContractChange::elements($this->contract(), [self::SACCO => 1000.0, self::HOUSING => 500.0, self::TRANSPORT => 100.0], [self::SACCO => 1500.0, self::HOUSING => 500.0, self::WELFARE => 200.0], 5);

        $changes = ContractChange::all()->keyBy('element_id');
        $this->assertCount(3, $changes);
        $this->assertSame(['element_changed', '1000.00', '1500.00'], [$changes[self::SACCO]->field, $changes[self::SACCO]->old_value, $changes[self::SACCO]->new_value]);
        $this->assertSame(['element_added', null, '200.00'], [$changes[self::WELFARE]->field, $changes[self::WELFARE]->old_value, $changes[self::WELFARE]->new_value]);
        $this->assertSame(['element_removed', '100.00', null], [$changes[self::TRANSPORT]->field, $changes[self::TRANSPORT]->old_value, $changes[self::TRANSPORT]->new_value]);
    }

    // ── the migrations ─────────────────────────────────────────────────

    public function test_the_settings_migration_flags_the_statutory_items_and_leaves_the_rest_alone(): void
    {
        $this->assertSame(['all', 'recurring'], [$this->element(self::NSSF)->applies_to, $this->element(self::NSSF)->recurrence]);
        $this->assertSame('all', $this->element(self::SHA)->applies_to);
        $this->assertSame('all', $this->element(self::HOUSING_LEVY)->applies_to);
        $this->assertSame(['specific', 'recurring'], [$this->element(self::SACCO)->applies_to, $this->element(self::SACCO)->recurrence]);
        $this->assertSame(['specific', 'recurring'], [$this->element(self::HOUSING)->applies_to, $this->element(self::HOUSING)->recurrence]);
    }

    public function test_the_settings_migration_is_safe_to_run_again(): void
    {
        DB::table('sys_hr_payroll_elements')->where('id', self::SACCO)->update(['recurrence' => 'one_time']);
        $before = DB::table('sys_hr_payroll_elements')->orderBy('id')->get()->toArray();

        (require base_path('database/migrations/2026_11_05_000001_add_payroll_element_settings_and_monthly_items.php'))->up();

        $this->assertEquals($before, DB::table('sys_hr_payroll_elements')->orderBy('id')->get()->toArray());
        $this->assertTrue(Schema::hasTable('payroll_period_items'));
        $this->assertTrue(Schema::hasTable('contract_changes'));
    }

    public function test_the_menu_migration_gives_the_new_page_only_to_the_roles_that_see_payroll_processing(): void
    {
        Schema::create('cb_sub_modules', function (Blueprint $t) {
            $t->id();
            $t->string('sub_module_name');
            $t->string('sub_module_url');
            $t->string('icon')->nullable();
            $t->integer('order')->nullable();
            $t->string('added_by')->nullable();
            $t->dateTime('added_on')->nullable();
            $t->integer('module_id');
            $t->timestamps();
        });
        Schema::create('cb_sub_module_permissions', function (Blueprint $t) {
            $t->id();
            $t->integer('role_id');
            $t->integer('sub_module_id');
            $t->dateTime('added_on')->nullable();
            $t->timestamps();
        });
        $processing = DB::table('cb_sub_modules')->insertGetId(['sub_module_name' => 'Payroll Processing', 'sub_module_url' => '/payroll/processing', 'order' => 4, 'module_id' => 9]);
        DB::table('cb_sub_modules')->insert(['sub_module_name' => 'Payslips', 'sub_module_url' => '/payroll/payslips', 'order' => 7, 'module_id' => 9]);
        DB::table('cb_sub_module_permissions')->insert([['role_id' => 1, 'sub_module_id' => $processing], ['role_id' => 2, 'sub_module_id' => $processing]]);

        $migration = require base_path('database/migrations/2026_11_05_000002_add_monthly_payroll_items_menu.php');
        $migration->up();
        $migration->up();

        $page = DB::table('cb_sub_modules')->where('sub_module_url', '/payroll/monthly-items')->get();
        $this->assertCount(1, $page);
        $this->assertSame([9, 8], [(int) $page[0]->module_id, (int) $page[0]->order]);
        $roles = DB::table('cb_sub_module_permissions')->where('sub_module_id', $page[0]->id)->pluck('role_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $this->assertSame([1, 2], $roles);
    }
}
