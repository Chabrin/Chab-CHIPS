<?php

namespace Tests\Feature;

use App\Http\Controllers\HrSettingsController;
use App\Models\Contract;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * The contract form: the end date follows from the start date and the contract period, permanent staff have open-ended
 * contracts with a probation period only, and any contract may have a probation period.
 */
class ContractPeriodFormTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    private const FIXED = 5;      // Contract: has an end date
    private const PERMANENT = 6;  // Permanent & Pensionable: open-ended
    private const CASUAL = 7;

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
            $t->integer('position_id')->nullable();
            $t->integer('zone_id')->nullable();
            $t->integer('department_id')->nullable();
        });
        Schema::create('sys_set_staff_positions', function (Blueprint $t) { $t->id(); $t->string('position_name')->nullable(); });
        Schema::create('sys_set_zones', function (Blueprint $t) { $t->id(); $t->string('zone_name')->nullable(); });
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
        // How often each row applies, what a part month does to it, and the company-wide items left out for a person
        (require database_path('migrations/2026_11_11_120000_add_terms_and_exclusions_for_contract_elements.php'))->up();
        Schema::create('sys_hr_payroll_elements', function (Blueprint $t) { $t->id(); $t->string('element_name')->nullable(); });
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

        DB::table('document_types')->insert(['id' => 3, 'name' => 'Signed Employment Contract']);
        DB::table('sys_staff')->insert([
            ['id' => self::FIXED, 'staff_number' => 'CT-001', 'full_names' => 'Fixed Term', 'employment_type' => 'Contract'],
            ['id' => self::PERMANENT, 'staff_number' => '019', 'full_names' => 'Perm Anent', 'employment_type' => 'Permanent'],
            ['id' => self::CASUAL, 'staff_number' => 'C-001', 'full_names' => 'Cas Ual', 'employment_type' => 'Casual'],
        ]);

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
        return $overrides + ['staff_id' => self::FIXED, 'staff_positions' => 1, 'date_start' => '2027-01-01', 'basic_salary' => 30000];
    }

    private function store(array $overrides = [], ?UploadedFile $file = null)
    {
        return $this->call('POST', route('contract.store'), $this->fields($overrides), [], $file ? ['signed_contract' => $file] : []);
    }

    private function contract(): Contract
    {
        return Contract::firstOrFail();
    }

    private function endOf(Contract $contract): ?string
    {
        return $contract->date_end?->toDateString();
    }

    // ── the end date follows from the period ──────────────────────────────

    public function test_twelve_months_from_the_first_of_january_ends_on_the_last_day_of_december(): void
    {
        $this->store(['period_value' => 12, 'period_unit' => 'months'])->assertSessionHasNoErrors();

        $contract = $this->contract();
        $this->assertSame('2027-12-31', $this->endOf($contract));
        $this->assertSame(12, (int) $contract->period_value);
        $this->assertSame('months', $contract->period_unit);
    }

    public function test_six_months_from_the_fifteenth_of_march_ends_on_the_fourteenth_of_september(): void
    {
        $this->store(['date_start' => '2027-03-15', 'period_value' => 6, 'period_unit' => 'months'])->assertSessionHasNoErrors();

        $this->assertSame('2027-09-14', $this->endOf($this->contract()));
    }

    public function test_years_weeks_and_days_are_allowed_too(): void
    {
        foreach ([[2, 'years', '2028-12-31'], [52, 'weeks', '2027-12-30'], [30, 'days', '2027-01-30']] as [$value, $unit, $end]) {
            Contract::query()->delete();
            $this->store(['period_value' => $value, 'period_unit' => $unit])->assertSessionHasNoErrors();
            $this->assertSame($end, $this->endOf($this->contract()), "{$value} {$unit}");
        }
    }

    public function test_the_period_wins_over_an_end_date_typed_beside_it(): void
    {
        $this->store(['period_value' => 12, 'period_unit' => 'months', 'date_end' => '2030-06-30'])->assertSessionHasNoErrors();

        $this->assertSame('2027-12-31', $this->endOf($this->contract()));
    }

    public function test_an_end_date_can_still_be_entered_without_a_period(): void
    {
        $this->store(['date_end' => '2027-06-30'])->assertSessionHasNoErrors();

        $contract = $this->contract();
        $this->assertSame('2027-06-30', $this->endOf($contract));
        $this->assertNull($contract->period_value);
        $this->assertNull($contract->period_unit);
    }

    public function test_without_a_period_the_end_date_is_needed_and_must_not_be_before_the_start(): void
    {
        $this->store()->assertSessionHasErrors('date_end');
        $this->store(['date_end' => '2026-12-31'])->assertSessionHasErrors('date_end');
        $this->assertSame(0, Contract::count());
    }

    public function test_a_period_needs_its_unit_and_a_sensible_number(): void
    {
        $this->store(['period_value' => 12])->assertSessionHasErrors('period_unit');
        $this->store(['period_value' => 0, 'period_unit' => 'months'])->assertSessionHasErrors('period_value');
        $this->store(['period_value' => 3651, 'period_unit' => 'days'])->assertSessionHasErrors('period_value');
        $this->store(['period_value' => 1.5, 'period_unit' => 'months'])->assertSessionHasErrors('period_value');
        $this->store(['period_value' => 12, 'period_unit' => 'fortnights'])->assertSessionHasErrors('period_unit');
        $this->assertSame(0, Contract::count());
    }

    // ── permanent staff: open-ended, probation only ───────────────────────

    public function test_a_permanent_contract_needs_no_end_date_and_is_saved_without_one(): void
    {
        $this->store(['staff_id' => self::PERMANENT])->assertSessionHasNoErrors();

        $contract = $this->contract();
        $this->assertNull($contract->date_end, 'open-ended, like the permanent contracts already in the data');
        $this->assertNull($contract->period_value);
    }

    public function test_an_end_date_entered_on_a_permanent_staff_members_contract_is_kept(): void
    {
        // Some contracts of permanent staff do have an end date; it is only never asked for
        $this->store(['staff_id' => self::PERMANENT, 'date_end' => '2027-06-30'])->assertSessionHasNoErrors();

        $this->assertSame('2027-06-30', $this->endOf($this->contract()));
    }

    public function test_a_period_on_a_permanent_staff_members_contract_works_the_end_date_out(): void
    {
        $this->store(['staff_id' => self::PERMANENT, 'period_value' => 12, 'period_unit' => 'months'])->assertSessionHasNoErrors();

        $contract = $this->contract();
        $this->assertSame('2027-12-31', $this->endOf($contract));
        $this->assertSame(12, (int) $contract->period_value);
    }

    public function test_an_end_date_before_the_start_is_refused_for_permanent_staff_too(): void
    {
        $this->store(['staff_id' => self::PERMANENT, 'date_end' => '2026-12-31'])->assertSessionHasErrors('date_end');
        $this->assertSame(0, Contract::count());
    }

    public function test_casual_staff_still_have_an_end_date(): void
    {
        $this->store(['staff_id' => self::CASUAL])->assertSessionHasErrors('date_end');
        $this->store(['staff_id' => self::CASUAL, 'period_value' => 3, 'period_unit' => 'months'])->assertSessionHasNoErrors();

        $this->assertSame('2027-03-31', $this->endOf($this->contract()));
    }

    public function test_which_categories_are_open_ended_is_a_setting_not_a_name(): void
    {
        DB::table('employment_categories')->where('code', 'Permanent')->update(['has_end_date' => true]);
        DB::table('employment_categories')->where('code', 'Contract')->update(['has_end_date' => false]);

        $this->store(['staff_id' => self::PERMANENT])->assertSessionHasErrors('date_end');
        $this->store(['staff_id' => self::FIXED])->assertSessionHasNoErrors();
        $this->assertNull($this->contract()->date_end);
    }

    public function test_a_staff_member_with_no_known_category_is_asked_for_an_end_date_as_before(): void
    {
        DB::table('sys_staff')->where('id', self::FIXED)->update(['employment_type' => 'Seasonal']);

        $this->store()->assertSessionHasErrors('date_end');
    }

    // ── probation ─────────────────────────────────────────────────────────

    public function test_probation_months_give_a_probation_end_date(): void
    {
        $this->store(['staff_id' => self::PERMANENT, 'probation_months' => 3])->assertSessionHasNoErrors();

        $contract = $this->contract();
        $this->assertSame(3, (int) $contract->probation_months);
        $this->assertSame('2027-03-31', $contract->probation_end->toDateString());
        $this->assertNull($contract->date_end, 'a permanent contract has probation only');
    }

    public function test_a_contract_with_an_end_date_may_have_a_probation_period_too(): void
    {
        $this->store(['period_value' => 12, 'period_unit' => 'months', 'probation_months' => 6])->assertSessionHasNoErrors();

        $contract = $this->contract();
        $this->assertSame('2027-12-31', $this->endOf($contract));
        $this->assertSame('2027-06-30', $contract->probation_end->toDateString());
    }

    public function test_probation_is_optional_and_kept_empty_when_not_given(): void
    {
        $this->store(['period_value' => 12, 'period_unit' => 'months'])->assertSessionHasNoErrors();

        $this->assertNull($this->contract()->probation_months);
        $this->assertNull($this->contract()->probation_end);
    }

    public function test_probation_must_be_a_whole_number_of_months_up_to_five_years(): void
    {
        foreach ([0, 61, 1.5, 'abc'] as $months) {
            $this->store(['period_value' => 12, 'period_unit' => 'months', 'probation_months' => $months])->assertSessionHasErrors('probation_months');
        }
        $this->assertSame(0, Contract::count());
    }

    // ── changing a contract ───────────────────────────────────────────────

    public function test_changing_the_start_date_or_period_of_a_saved_contract_works_the_end_date_out_again(): void
    {
        $this->store(['period_value' => 12, 'period_unit' => 'months'])->assertSessionHasNoErrors();
        $id = $this->contract()->id;

        $this->call('PUT', route('contract.update', $id), $this->fields(['date_start' => '2027-03-15', 'period_value' => 6, 'period_unit' => 'months']))->assertSessionHasNoErrors();

        $contract = $this->contract()->fresh();
        $this->assertSame('2027-09-14', $this->endOf($contract));
        $this->assertSame(6, (int) $contract->period_value);
    }

    public function test_clearing_the_period_keeps_the_end_date_entered_by_hand(): void
    {
        $this->store(['period_value' => 12, 'period_unit' => 'months'])->assertSessionHasNoErrors();
        $id = $this->contract()->id;

        $this->call('PUT', route('contract.update', $id), $this->fields(['date_end' => '2027-08-31']))->assertSessionHasNoErrors();

        $contract = $this->contract()->fresh();
        $this->assertSame('2027-08-31', $this->endOf($contract));
        $this->assertNull($contract->period_value, 'the old period no longer describes the contract');
    }

    public function test_an_open_ended_contract_can_be_saved_again_without_gaining_an_end_date(): void
    {
        DB::table('sys_hr_employee_contracts')->insert(['id' => 40, 'staff_id' => self::PERMANENT, 'date_start' => '2020-01-01', 'date_end' => null, 'basic_salary' => 25000]);

        $this->call('PUT', route('contract.update', 40), $this->fields(['staff_id' => self::PERMANENT, 'date_start' => '2020-01-01', 'probation_months' => 3]))->assertSessionHasNoErrors();

        $contract = Contract::find(40);
        $this->assertNull($contract->date_end, 'saving does not turn "no end date" into today');
        $this->assertSame('2020-03-31', $contract->probation_end->toDateString());
    }

    public function test_leaving_the_end_date_empty_for_a_permanent_staff_member_clears_it(): void
    {
        $this->store(['period_value' => 12, 'period_unit' => 'months'])->assertSessionHasNoErrors();
        $id = $this->contract()->id;

        $this->call('PUT', route('contract.update', $id), $this->fields(['staff_id' => self::PERMANENT]))->assertSessionHasNoErrors();

        $contract = $this->contract()->fresh();
        $this->assertNull($contract->date_end, 'HR left it empty, so the contract is open-ended');
        $this->assertNull($contract->period_value);
    }

    public function test_saving_a_dated_contract_of_a_permanent_staff_member_keeps_its_end_date(): void
    {
        DB::table('sys_hr_employee_contracts')->insert(['id' => 41, 'staff_id' => self::PERMANENT, 'date_start' => '2026-01-01', 'date_end' => '2026-12-31', 'basic_salary' => 25000]);

        $this->call('PUT', route('contract.update', 41), $this->fields(['staff_id' => self::PERMANENT, 'date_start' => '2026-01-01', 'date_end' => '2026-12-31']))->assertSessionHasNoErrors();

        $this->assertSame('2026-12-31', $this->endOf(Contract::find(41)));
    }

    // ── the signed contract's title ───────────────────────────────────────

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('signed.pdf', "%PDF-1.4\nsigned");
    }

    public function test_the_title_of_a_signed_open_ended_contract_does_not_print_a_date_in_1970(): void
    {
        $this->store(['staff_id' => self::PERMANENT], $this->pdf())->assertSessionHasNoErrors();

        $this->assertSame('Signed contract 01 Jan 2027 - open-ended', EmployeeDocument::firstOrFail()->title);
    }

    public function test_the_title_of_a_signed_fixed_term_contract_has_both_dates(): void
    {
        $this->store(['period_value' => 12, 'period_unit' => 'months'], $this->pdf())->assertSessionHasNoErrors();

        $this->assertSame('Signed contract 01 Jan 2027 - 31 Dec 2027', EmployeeDocument::firstOrFail()->title);
    }

    // ── the staff list the form reads ─────────────────────────────────────

    public function test_the_staff_list_tells_the_form_which_staff_have_an_end_date(): void
    {
        $staff = collect(app(HrSettingsController::class)->staffList(Request::create('/', 'GET'))->getData(true)['staff'])->keyBy('id');

        $this->assertTrue($staff[self::FIXED]['has_end_date']);
        $this->assertFalse($staff[self::PERMANENT]['has_end_date']);
        $this->assertTrue($staff[self::CASUAL]['has_end_date']);
    }

    // ── the edit page decides from the staff member and what the contract already has ─────

    private function editPage(int $staffId): string
    {
        return $this->get(route('contract.edit', \Illuminate\Support\Facades\Crypt::encrypt($staffId)))->assertOk()->getContent();
    }

    public function test_the_edit_page_of_a_permanent_staff_members_open_ended_contract_asks_for_probation_only(): void
    {
        DB::table('sys_hr_employee_contracts')->insert(['id' => 50, 'staff_id' => self::PERMANENT, 'date_start' => '2020-01-01', 'date_end' => null, 'basic_salary' => 25000]);

        $page = $this->editPage(self::PERMANENT);

        $this->assertSame(2, substr_count($page, 'col-6 d-none" data-end-date-field'), 'the period and end date are hidden');
        $this->assertStringContainsString('name="date_end" id="date_end" disabled value=""', $page, 'with no date, not today');
        $this->assertStringContainsString('id="probation_months"', $page);
    }

    public function test_the_edit_page_of_a_permanent_staff_members_dated_contract_keeps_the_end_date_in_view(): void
    {
        DB::table('sys_hr_employee_contracts')->insert(['id' => 51, 'staff_id' => self::PERMANENT, 'date_start' => '2026-01-01', 'date_end' => '2026-12-31', 'basic_salary' => 25000]);

        $page = $this->editPage(self::PERMANENT);

        $this->assertStringNotContainsString('col-6 d-none" data-end-date-field', $page);
        $this->assertStringContainsString('name="date_end" id="date_end" required value="2026-12-31"', $page);
    }

    public function test_the_edit_page_of_a_fixed_term_contract_shows_the_period_and_end_date(): void
    {
        DB::table('sys_hr_employee_contracts')->insert(['id' => 52, 'staff_id' => self::FIXED, 'date_start' => '2026-01-01', 'date_end' => '2026-12-31', 'basic_salary' => 25000, 'period_value' => 12, 'period_unit' => 'months']);

        $page = $this->editPage(self::FIXED);

        $this->assertStringNotContainsString('col-6 d-none" data-end-date-field', $page);
        $this->assertStringContainsString('name="period_value" id="period_value" min="1" max="3650" step="1" value="12"', $page);
        $this->assertStringContainsString('<option value="months" selected>Months</option>', $page);
    }

    public function test_a_permanent_staff_members_contract_with_a_period_but_no_end_date_still_shows_the_period(): void
    {
        DB::table('sys_hr_employee_contracts')->insert(['id' => 53, 'staff_id' => self::PERMANENT, 'date_start' => '2026-01-01', 'date_end' => null, 'basic_salary' => 25000, 'period_value' => 6, 'period_unit' => 'months']);

        $this->assertStringNotContainsString('col-6 d-none" data-end-date-field', $this->editPage(self::PERMANENT));
    }

    // ── the fields on the page ────────────────────────────────────────────

    private function fieldsHtml(?Contract $contract, bool $openEnded): string
    {
        return view('staff.contracts.partials.period-fields', ['contract' => $contract, 'openEnded' => $openEnded])->render();
    }

    public function test_a_new_contract_shows_the_period_end_date_and_probation_fields(): void
    {
        $html = $this->fieldsHtml(null, false);

        foreach (['id="period_value"', 'id="period_unit"', 'id="date_end"', 'id="probation_months"', 'id="probation_end"'] as $field) {
            $this->assertStringContainsString($field, $html, $field);
        }
        $this->assertStringContainsString('<option value="months" selected>Months</option>', $html, 'months by default');
        foreach (['days', 'weeks', 'months', 'years'] as $unit) {
            $this->assertStringContainsString('<option value="' . $unit . '"', $html);
        }
        $this->assertStringNotContainsString('d-none', $html);
        $this->assertStringContainsString('name="date_end" id="date_end" required', $html);
    }

    public function test_an_open_ended_contract_hides_the_period_and_end_date_and_keeps_probation(): void
    {
        $html = $this->fieldsHtml(null, true);

        $this->assertSame(2, substr_count($html, 'data-end-date-field'));
        $this->assertSame(2, substr_count($html, 'col-6 d-none" data-end-date-field'), 'both are hidden');
        $this->assertStringContainsString('id="date_end" disabled', $html, 'and not sent');
        $this->assertStringContainsString('name="period_value" id="period_value" min="1" max="3650" step="1" value="" placeholder="e.g. 12" disabled', $html);
        $this->assertStringContainsString('This contract has no end date. Only the probation period is recorded.', $html);
        $this->assertStringContainsString('id="probation_months"', $html);
        $this->assertStringNotContainsString('id="probation_months" min="1" max="60" step="1" value="" placeholder="e.g. 3" disabled', $html);
    }

    public function test_a_saved_contract_shows_its_period_end_and_probation_again(): void
    {
        $contract = new Contract();
        $contract->forceFill(['date_end' => '2027-12-31', 'period_value' => 12, 'period_unit' => 'months', 'probation_months' => 3, 'probation_end' => '2027-03-31']);

        $html = $this->fieldsHtml($contract, false);

        $this->assertStringContainsString('value="12"', $html);
        $this->assertStringContainsString('<option value="months" selected>Months</option>', $html);
        $this->assertStringContainsString('value="2027-12-31"', $html);
        $this->assertStringContainsString('name="probation_months" id="probation_months" min="1" max="60" step="1" value="3"', $html);
        $this->assertStringContainsString('id="probation_end" value="2027-03-31"', $html);
    }

    public function test_a_saved_contract_with_no_end_date_shows_an_empty_box_not_todays_date(): void
    {
        $contract = new Contract();
        $contract->forceFill(['date_end' => null]);

        $html = $this->fieldsHtml($contract, false);

        $this->assertStringContainsString('name="date_end" id="date_end" required value=""', $html);
    }

    public function test_a_unit_other_than_months_is_the_one_selected_when_saved(): void
    {
        $contract = new Contract();
        $contract->forceFill(['period_value' => 2, 'period_unit' => 'years']);

        $this->assertStringContainsString('<option value="years" selected>Years</option>', $this->fieldsHtml($contract, false));
    }
}
