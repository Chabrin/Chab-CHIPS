<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractChange;
use App\Models\ContractElement;
use App\Models\ContractElementExclusion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * The contract form's two questions about each allowance or deduction (how often, and what a part month does to it), and
 * company-wide items left out for one person with a reason: what is saved, what is refused, what is logged.
 */
class ContractElementTermsTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    private const STAFF = 5;
    private const OTHER_STAFF = 6;
    private const SACCO = 1;       // deduction, specific staff
    private const HOUSE = 2;       // allowance, specific staff
    private const WELFARE = 3;     // deduction, every staff member
    private const BONUS = 4;       // one-time
    private const NSSF = 5;        // statutory
    private const TRANSPORT = 6;   // allowance, every staff member
    private const BASIC = 7;       // system

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables
        Storage::fake('local');

        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('staff_number')->nullable();
            $t->string('full_names')->nullable();
            $t->string('employment_type')->nullable();
            $t->string('photo')->nullable();
        });
        (require database_path('migrations/2026_11_10_100000_create_employment_categories_table.php'))->up();
        Schema::create('sys_hr_employee_contracts', function (Blueprint $t) {
            $t->id();
            $t->timestamp('date_time')->nullable();
            $t->integer('staff_id');
            $t->timestamp('date_start')->nullable();
            $t->timestamp('date_end')->nullable();
            $t->integer('current_status_id')->default(5);
            $t->integer('position_id')->nullable();
            $t->integer('created_by')->nullable();
            $t->decimal('basic_salary', 15, 2)->default(0);
            $t->string('staff_number')->nullable();
        });
        (require database_path('migrations/2026_11_10_100200_add_period_and_probation_to_contracts.php'))->up();
        Schema::create('sys_hr_employee_contracts_279_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->integer('element_id');
            $t->integer('entry_type_id')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
        });
        (require database_path('migrations/2026_11_11_120000_add_terms_and_exclusions_for_contract_elements.php'))->up();
        Schema::create('sys_hr_payroll_elements', function (Blueprint $t) {
            $t->id();
            $t->string('element_name')->nullable();
            $t->string('element_key')->nullable();
            $t->string('element_type')->nullable();
            $t->integer('current_status_id')->default(5);
            $t->string('recurrence')->default('recurring');
            $t->string('applies_to')->default('specific');
            $t->integer('formula_id')->nullable();
            $t->decimal('amount', 15, 4)->default(0);
            $t->integer('payslip_position')->nullable();
        });
        Schema::create('contract_changes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('contract_id')->nullable();
            $t->unsignedBigInteger('staff_id');
            $t->string('field', 30);
            $t->unsignedBigInteger('element_id')->nullable();
            $t->string('old_value', 50)->nullable();
            $t->string('new_value', 50)->nullable();
            $t->unsignedBigInteger('changed_by')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('document_types', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('employee_documents', function (Blueprint $t) {
            $t->id();
            $t->integer('employee_id');
            $t->integer('contract_id')->nullable();
            $t->integer('document_type_id');
            $t->string('title')->nullable();
            $t->string('file_path');
            $t->string('file_name')->nullable();
            $t->integer('file_size')->nullable();
            $t->string('mime_type')->nullable();
            $t->integer('uploaded_by')->nullable();
            $t->timestamps();
        });

        DB::table('sys_staff')->insert([
            ['id' => self::STAFF, 'staff_number' => 'CT-001', 'full_names' => 'Fixed Term', 'employment_type' => 'Contract'],
            ['id' => self::OTHER_STAFF, 'staff_number' => 'CT-002', 'full_names' => 'Other Person', 'employment_type' => 'Contract'],
        ]);
        foreach ([
            [self::SACCO, 'Sacco Loan', null, '1', 'recurring', 'specific', null, 0],
            [self::HOUSE, 'House Allowance', null, '2', 'recurring', 'specific', null, 0],
            [self::WELFARE, 'Welfare', null, '1', 'recurring', 'all', 1, 200],
            [self::BONUS, 'Bonus', null, '2', 'one_time', 'specific', null, 0],
            [self::NSSF, 'NSSF', 'nssf', '1', 'recurring', 'all', null, 0],
            [self::TRANSPORT, 'Transport', null, '2', 'recurring', 'all', 1, 3000],
            [self::BASIC, 'Basic Salary', 'basic_salary', '2', 'recurring', 'specific', null, 0],
        ] as [$id, $name, $key, $type, $recurrence, $appliesTo, $formula, $amount]) {
            DB::table('sys_hr_payroll_elements')->insert([
                'id' => $id, 'element_name' => $name, 'element_key' => $key, 'element_type' => $type,
                'recurrence' => $recurrence, 'applies_to' => $appliesTo, 'formula_id' => $formula, 'amount' => $amount,
            ]);
        }

        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturn(true);
        $user->shouldReceive('hasPermission')->andReturn(true);
        $user->id = 7;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function fields(array $overrides = []): array
    {
        return $overrides + [
            'staff_id' => self::STAFF, 'staff_positions' => 1, 'date_start' => '2027-01-01', 'date_end' => '2027-12-31', 'basic_salary' => 60000,
            'elements' => [],
        ];
    }

    private function row(int $element, array $extra = [], $amount = 1000): array
    {
        return ['element' => $element, 'entry_type' => $element === self::HOUSE ? 2 : 1, 'amount' => $amount] + $extra;
    }

    private function store(array $overrides = [])
    {
        return $this->call('POST', route('contract.store'), $this->fields($overrides));
    }

    private function update(int $id, array $overrides = [])
    {
        return $this->call('PUT', route('contract.update', $id), $this->fields($overrides));
    }

    private function contract(): Contract
    {
        return Contract::firstOrFail();
    }

    private function savedRow(int $element): ContractElement
    {
        return ContractElement::where('element_id', $element)->firstOrFail();
    }

    // ── how often ─────────────────────────────────────────────────────────

    public function test_an_item_is_every_month_unless_it_says_once_only(): void
    {
        $this->store(['elements' => [$this->row(self::SACCO)]])->assertSessionHasNoErrors();

        $row = $this->savedRow(self::SACCO);
        $this->assertSame('monthly', $row->frequency);
        $this->assertNull($row->once_month);
    }

    public function test_a_once_only_item_keeps_its_month(): void
    {
        $this->store(['elements' => [$this->row(self::HOUSE, ['frequency' => 'once', 'once_month' => '2027-06'])]])->assertSessionHasNoErrors();

        $row = $this->savedRow(self::HOUSE);
        $this->assertSame('once', $row->frequency);
        $this->assertSame('2027-06-01', $row->once_month->toDateString());
    }

    public function test_a_stray_month_on_an_every_month_item_is_not_kept(): void
    {
        $this->store(['elements' => [$this->row(self::SACCO, ['frequency' => 'monthly', 'once_month' => '2027-06'])]])->assertSessionHasNoErrors();

        $this->assertNull($this->savedRow(self::SACCO)->once_month);
    }

    public function test_a_once_only_item_without_a_month_is_refused_and_nothing_is_saved(): void
    {
        $this->store(['elements' => [$this->row(self::HOUSE, ['frequency' => 'once'])]])
            ->assertSessionHasErrors(['elements' => 'House Allowance is set to apply once only, so choose the month it applies in.']);

        $this->assertSame(0, Contract::count());
    }

    public function test_how_often_can_only_be_every_month_or_once(): void
    {
        $this->store(['elements' => [$this->row(self::SACCO, ['frequency' => 'weekly'])]])->assertSessionHasErrors('elements.0.frequency');
        $this->store(['elements' => [$this->row(self::SACCO, ['frequency' => 'once', 'once_month' => 'June 2027'])]])->assertSessionHasErrors('elements.0.once_month');
        $this->store(['elements' => [$this->row(self::SACCO, ['frequency' => 'once', 'once_month' => '2027-13'])]])->assertSessionHasErrors('elements.0.once_month');
        $this->assertSame(0, Contract::count());
    }

    public function test_the_once_only_month_has_to_be_a_month_the_contract_runs(): void
    {
        $this->store(['elements' => [$this->row(self::HOUSE, ['frequency' => 'once', 'once_month' => '2026-12'])]])
            ->assertSessionHasErrors(['elements' => 'House Allowance: the once-only month (Dec 2026) is before the contract starts (Jan 2027).']);
        $this->store(['elements' => [$this->row(self::HOUSE, ['frequency' => 'once', 'once_month' => '2028-01'])]])
            ->assertSessionHasErrors(['elements' => 'House Allowance: the once-only month (Jan 2028) is after the contract ends (Dec 2027).']);
        $this->assertSame(0, Contract::count());
    }

    public function test_the_first_and_last_months_of_the_contract_are_allowed(): void
    {
        $this->store(['elements' => [
            $this->row(self::HOUSE, ['frequency' => 'once', 'once_month' => '2027-01']),
            $this->row(self::SACCO, ['frequency' => 'once', 'once_month' => '2027-12']),
        ]])->assertSessionHasNoErrors();

        $this->assertSame(2, ContractElement::count());
    }

    public function test_an_open_ended_contract_has_no_last_month_but_still_a_first_one(): void
    {
        DB::table('employment_categories')->where('code', 'Contract')->update(['has_end_date' => false]);

        $this->store(['date_end' => null, 'elements' => [$this->row(self::HOUSE, ['frequency' => 'once', 'once_month' => '2035-03'])]])->assertSessionHasNoErrors();
        $this->assertSame('2035-03-01', $this->savedRow(self::HOUSE)->once_month->toDateString());

        Contract::query()->delete();
        $this->store(['date_end' => null, 'elements' => [$this->row(self::HOUSE, ['frequency' => 'once', 'once_month' => '2026-12'])]])
            ->assertSessionHasErrors('elements');
    }

    // ── a part month ──────────────────────────────────────────────────────

    public function test_the_part_month_answer_is_kept_either_way(): void
    {
        $this->store(['elements' => [$this->row(self::HOUSE, ['prorate' => '0']), $this->row(self::SACCO, ['prorate' => '1'])]])->assertSessionHasNoErrors();

        $this->assertFalse($this->savedRow(self::HOUSE)->prorate, 'an allowance charged in full');
        $this->assertTrue($this->savedRow(self::SACCO)->prorate, 'a deduction reduced by the days worked');
    }

    public function test_with_no_answer_an_allowance_is_reduced_and_a_deduction_is_not_as_before(): void
    {
        $this->store(['elements' => [$this->row(self::HOUSE), $this->row(self::SACCO)]])->assertSessionHasNoErrors();

        $this->assertNull($this->savedRow(self::HOUSE)->prorate);
        $this->assertTrue($this->savedRow(self::HOUSE)->fresh('payrollElement')->prorates());
        $this->assertFalse($this->savedRow(self::SACCO)->fresh('payrollElement')->prorates());
    }

    public function test_the_part_month_answer_can_only_be_yes_or_no(): void
    {
        $this->store(['elements' => [$this->row(self::HOUSE, ['prorate' => 'maybe'])]])->assertSessionHasErrors('elements.0.prorate');
    }

    // ── changing a contract ───────────────────────────────────────────────

    public function test_changing_the_terms_of_an_item_on_a_saved_contract_is_logged_in_words(): void
    {
        $this->store(['elements' => [$this->row(self::HOUSE, ['prorate' => '1'])]])->assertSessionHasNoErrors();
        $id = $this->contract()->id;

        $this->update($id, ['elements' => [$this->row(self::HOUSE, ['frequency' => 'once', 'once_month' => '2027-06', 'prorate' => '0'])]])->assertSessionHasNoErrors();

        $change = ContractChange::where('field', ContractChange::ELEMENT_TERMS)->firstOrFail();
        $this->assertSame(self::HOUSE, (int) $change->element_id);
        $this->assertSame('Monthly, prorated', $change->old_value);
        $this->assertSame('Once Jun 2027, full amount', $change->new_value);
        $this->assertSame(7, (int) $change->changed_by);
    }

    public function test_saving_again_with_the_same_terms_logs_nothing(): void
    {
        $this->store(['elements' => [$this->row(self::HOUSE, ['prorate' => '1'])]])->assertSessionHasNoErrors();
        $id = $this->contract()->id;

        $this->update($id, ['elements' => [$this->row(self::HOUSE, ['prorate' => '1'])]])->assertSessionHasNoErrors();

        $this->assertSame(0, ContractChange::where('field', ContractChange::ELEMENT_TERMS)->count());
    }

    public function test_choosing_the_old_rule_explicitly_is_not_a_change(): void
    {
        $this->store(['elements' => [$this->row(self::HOUSE)]])->assertSessionHasNoErrors(); // no answer: reduced, as an allowance always was
        $id = $this->contract()->id;

        $this->update($id, ['elements' => [$this->row(self::HOUSE, ['prorate' => '1'])]])->assertSessionHasNoErrors();

        $this->assertSame(0, ContractChange::where('field', ContractChange::ELEMENT_TERMS)->count(), 'the run does the same thing before and after');
    }

    public function test_a_new_item_is_logged_as_added_not_as_a_terms_change(): void
    {
        $this->store(['elements' => []])->assertSessionHasNoErrors();
        $id = $this->contract()->id;

        $this->update($id, ['elements' => [$this->row(self::SACCO, ['frequency' => 'once', 'once_month' => '2027-03'])]])->assertSessionHasNoErrors();

        $this->assertSame(1, ContractChange::where('field', ContractChange::ELEMENT_ADDED)->count());
        $this->assertSame(0, ContractChange::where('field', ContractChange::ELEMENT_TERMS)->count());
    }

    public function test_the_bulk_edit_page_changes_amounts_and_keeps_the_terms_already_chosen(): void
    {
        $this->store(['elements' => [$this->row(self::HOUSE, ['frequency' => 'once', 'once_month' => '2027-06', 'prorate' => '0']), $this->row(self::SACCO, ['prorate' => '1'])]])->assertSessionHasNoErrors();
        $id = $this->contract()->id;

        $this->putJson(route('contract.elements.update', $id), ['elements' => [
            ['element_id' => self::HOUSE, 'amount' => 9000], ['element_id' => self::SACCO, 'amount' => 700],
        ]])->assertOk();

        $house = $this->savedRow(self::HOUSE);
        $this->assertEquals(9000, $house->amount);
        $this->assertSame('once', $house->frequency);
        $this->assertSame('2027-06-01', $house->once_month->toDateString());
        $this->assertFalse($house->prorate);
        $sacco = $this->savedRow(self::SACCO);
        $this->assertEquals(700, $sacco->amount);
        $this->assertTrue($sacco->prorate);
        $this->assertSame(0, ContractChange::where('field', ContractChange::ELEMENT_TERMS)->count());
    }

    public function test_the_bulk_edit_page_leaves_a_row_that_never_had_an_answer_without_one(): void
    {
        $this->store(['elements' => [$this->row(self::HOUSE)]])->assertSessionHasNoErrors();
        $id = $this->contract()->id;

        $this->putJson(route('contract.elements.update', $id), ['elements' => [['element_id' => self::HOUSE, 'amount' => 5000]]])->assertOk();

        $this->assertNull($this->savedRow(self::HOUSE)->prorate);
    }

    // ── company-wide items left out for one person ────────────────────────

    private function exclude(int $element, string $reason = 'Opted out of the welfare scheme'): array
    {
        return [$element => ['excluded' => '1', 'reason' => $reason]];
    }

    public function test_a_company_wide_item_can_be_left_out_for_one_person_with_a_reason(): void
    {
        $this->store(['exclusions' => $this->exclude(self::WELFARE)])->assertSessionHasNoErrors();

        $exclusion = ContractElementExclusion::firstOrFail();
        $this->assertSame($this->contract()->id, (int) $exclusion->contract_id);
        $this->assertSame(self::WELFARE, (int) $exclusion->element_id);
        $this->assertSame('Opted out of the welfare scheme', $exclusion->reason);
        $this->assertSame(7, (int) $exclusion->created_by);
    }

    public function test_leaving_an_item_out_is_logged_with_its_reason(): void
    {
        $this->store(['exclusions' => $this->exclude(self::WELFARE)])->assertSessionHasNoErrors();

        $change = ContractChange::where('field', ContractChange::ITEM_EXCLUDED)->firstOrFail();
        $this->assertSame(self::WELFARE, (int) $change->element_id);
        $this->assertSame('Opted out of the welfare scheme', $change->new_value);
        $this->assertSame(self::STAFF, (int) $change->staff_id);
    }

    public function test_a_long_reason_is_kept_whole_and_only_shortened_in_the_log(): void
    {
        $reason = str_repeat('Opted out because of a very long explanation. ', 5);

        $this->store(['exclusions' => $this->exclude(self::WELFARE, trim($reason))])->assertSessionHasNoErrors();

        $this->assertSame(trim($reason), ContractElementExclusion::firstOrFail()->reason);
        $this->assertSame(50, mb_strlen(ContractChange::where('field', ContractChange::ITEM_EXCLUDED)->firstOrFail()->new_value));
    }

    public function test_leaving_an_item_out_needs_a_reason(): void
    {
        $this->store(['exclusions' => $this->exclude(self::WELFARE, '   ')])->assertSessionHasErrors(['exclusions' => 'Give the reason for leaving out Welfare.']);
        $this->store(['exclusions' => [self::WELFARE => ['excluded' => '1']]])->assertSessionHasErrors(['exclusions' => 'Give the reason for leaving out Welfare.']);
        $this->assertSame(0, Contract::count());
        $this->assertSame(0, ContractElementExclusion::count());
    }

    public function test_a_reason_cannot_be_longer_than_255_characters(): void
    {
        $this->store(['exclusions' => $this->exclude(self::WELFARE, str_repeat('x', 256))])->assertSessionHasErrors('exclusions.' . self::WELFARE . '.reason');
    }

    public function test_statutory_items_cannot_be_left_out(): void
    {
        $this->store(['exclusions' => $this->exclude(self::NSSF, 'Not wanted')])
            ->assertSessionHasErrors(['exclusions' => 'NSSF is required by the payroll run for every staff member, so it cannot be left out.']);

        $this->assertSame(0, ContractElementExclusion::count());
        $this->assertSame(0, Contract::count());
    }

    public function test_every_statutory_item_is_refused_whatever_it_is_called(): void
    {
        foreach (['nhif', 'housing_levy', 'payee'] as $key) {
            $id = DB::table('sys_hr_payroll_elements')->insertGetId(['element_name' => strtoupper($key), 'element_key' => $key, 'element_type' => '1', 'recurrence' => 'recurring', 'applies_to' => 'all']);
            $this->store(['exclusions' => $this->exclude($id, 'No')])->assertSessionHasErrors('exclusions');
        }
        $this->assertSame(0, ContractElementExclusion::count());
    }

    public function test_an_item_that_is_not_company_wide_has_nothing_to_leave_out(): void
    {
        $this->store(['exclusions' => $this->exclude(self::SACCO, 'No')])->assertSessionHasErrors(['exclusions' => 'Sacco Loan is not a company-wide item, so there is nothing to leave out.']);
        $this->store(['exclusions' => $this->exclude(self::BONUS, 'No')])->assertSessionHasErrors('exclusions');
        $this->assertSame(0, ContractElementExclusion::count());
    }

    public function test_an_item_that_does_not_exist_is_refused(): void
    {
        $this->store(['exclusions' => $this->exclude(999, 'No')])->assertSessionHasErrors(['exclusions' => 'One of the items left out no longer exists.']);
    }

    public function test_an_item_cannot_be_both_left_out_and_given_its_own_amount(): void
    {
        $this->store(['elements' => [$this->row(self::WELFARE, [], 500)], 'exclusions' => $this->exclude(self::WELFARE)])
            ->assertSessionHasErrors(['exclusions' => 'Welfare is left out and also has its own amount on this contract. Keep one of them.']);

        $this->assertSame(0, Contract::count());
    }

    public function test_a_box_that_is_not_ticked_leaves_nothing_out(): void
    {
        $this->store(['exclusions' => [self::WELFARE => ['reason' => 'typed but not ticked']]])->assertSessionHasNoErrors();

        $this->assertSame(0, ContractElementExclusion::count());
    }

    public function test_leaving_out_one_item_does_not_touch_the_others_or_another_persons_contract(): void
    {
        $this->store(['exclusions' => $this->exclude(self::WELFARE)])->assertSessionHasNoErrors();
        $this->store(['staff_id' => self::OTHER_STAFF])->assertSessionHasNoErrors();

        $this->assertSame(2, Contract::count());
        $this->assertSame(1, ContractElementExclusion::count(), 'one person, one item');
        $other = Contract::where('staff_id', self::OTHER_STAFF)->firstOrFail();
        $this->assertSame(0, ContractElementExclusion::where('contract_id', $other->id)->count());
    }

    public function test_putting_an_item_back_removes_it_and_is_logged(): void
    {
        $this->store(['exclusions' => $this->exclude(self::WELFARE)])->assertSessionHasNoErrors();
        $id = $this->contract()->id;

        $this->update($id)->assertSessionHasNoErrors();

        $this->assertSame(0, ContractElementExclusion::count());
        $change = ContractChange::where('field', ContractChange::ITEM_INCLUDED)->firstOrFail();
        $this->assertSame(self::WELFARE, (int) $change->element_id);
        $this->assertSame('Opted out of the welfare scheme', $change->old_value);
    }

    public function test_changing_only_the_reason_updates_it_without_a_log_entry(): void
    {
        $this->store(['exclusions' => $this->exclude(self::WELFARE, 'First reason')])->assertSessionHasNoErrors();
        $id = $this->contract()->id;

        $this->update($id, ['exclusions' => $this->exclude(self::WELFARE, 'Better reason')])->assertSessionHasNoErrors();

        $this->assertSame(1, ContractElementExclusion::count());
        $this->assertSame('Better reason', ContractElementExclusion::firstOrFail()->reason);
        $this->assertSame(1, ContractChange::where('field', ContractChange::ITEM_EXCLUDED)->count(), 'only the first time');
        $this->assertSame(0, ContractChange::where('field', ContractChange::ITEM_INCLUDED)->count());
    }

    public function test_leaving_out_two_items_and_putting_one_back(): void
    {
        $this->store(['exclusions' => $this->exclude(self::WELFARE) + $this->exclude(self::TRANSPORT, 'Provided by the office')])->assertSessionHasNoErrors();
        $id = $this->contract()->id;
        $this->assertSame(2, ContractElementExclusion::count());

        $this->update($id, ['exclusions' => $this->exclude(self::TRANSPORT, 'Provided by the office')])->assertSessionHasNoErrors();

        $this->assertSame([self::TRANSPORT], ContractElementExclusion::pluck('element_id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_a_refused_update_leaves_the_exclusions_as_they_were(): void
    {
        $this->store(['exclusions' => $this->exclude(self::WELFARE)])->assertSessionHasNoErrors();
        $id = $this->contract()->id;

        $this->update($id, ['exclusions' => $this->exclude(self::NSSF, 'No')])->assertSessionHasErrors('exclusions');

        $this->assertSame([self::WELFARE], ContractElementExclusion::pluck('element_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(0, ContractChange::where('field', ContractChange::ITEM_INCLUDED)->count());
    }

    public function test_a_contract_with_nothing_left_out_stays_that_way(): void
    {
        $this->store()->assertSessionHasNoErrors();
        $this->update($this->contract()->id)->assertSessionHasNoErrors();

        $this->assertSame(0, ContractElementExclusion::count());
        $this->assertSame(0, ContractChange::whereIn('field', [ContractChange::ITEM_EXCLUDED, ContractChange::ITEM_INCLUDED])->count());
    }

    // ── what the model says ───────────────────────────────────────────────

    public function test_the_model_describes_the_terms_in_words(): void
    {
        $row = new ContractElement(['frequency' => 'once', 'once_month' => '2027-06-01', 'prorate' => false]);
        $row->setRelation('payrollElement', new \App\Models\PayrollElement(['element_type' => '2']));

        $this->assertSame('Once Jun 2027, full amount', $row->termsBrief());
        $this->assertSame("Once only (Jun 2027) \u{00B7} full amount in a part month", $row->termsText());

        $every = new ContractElement(['frequency' => 'monthly', 'prorate' => true]);
        $every->setRelation('payrollElement', new \App\Models\PayrollElement(['element_type' => '1']));
        $this->assertSame('Monthly, prorated', $every->termsBrief());
        $this->assertSame("Every month \u{00B7} reduced by the days worked in a part month", $every->termsText());
    }

    public function test_a_once_only_item_applies_in_the_period_that_starts_in_its_month_and_no_other(): void
    {
        $row = new ContractElement(['frequency' => 'once', 'once_month' => '2027-06-01']);

        $this->assertTrue($row->appliesInPeriod('2027-06-01'));
        $this->assertTrue($row->appliesInPeriod('2027-06-16'), 'a period that starts mid-month, in June');
        $this->assertFalse($row->appliesInPeriod('2027-05-31'));
        $this->assertFalse($row->appliesInPeriod('2027-07-01'));
        $this->assertFalse($row->appliesInPeriod('2028-06-01'), 'the same month a year on is another month');
    }

    public function test_an_every_month_item_applies_in_every_period_and_a_once_only_item_with_no_month_in_none(): void
    {
        $this->assertTrue((new ContractElement(['frequency' => 'monthly']))->appliesInPeriod('2027-06-01'));
        $this->assertTrue((new ContractElement())->appliesInPeriod('2027-06-01'), 'a row saved before this existed');
        $this->assertFalse((new ContractElement(['frequency' => 'once']))->appliesInPeriod('2027-06-01'));
    }
}
