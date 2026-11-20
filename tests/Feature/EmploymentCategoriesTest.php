<?php

namespace Tests\Feature;

use App\Models\EmploymentCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * HR Settings > Employment Categories: HR adds, edits and switches categories on or off, a category with staff in it
 * cannot be deleted, and Add Staff offers only the categories that are on.
 */
class EmploymentCategoriesTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout(true);
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables
        (require database_path('migrations/2026_11_10_100000_create_employment_categories_table.php'))->up();
        Schema::create('sys_staff', function (Blueprint $table) {
            $table->id();
            $table->string('staff_number')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('national_id')->nullable();
            $table->string('email_address')->nullable();
        });
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function actingAsWho(string ...$permissions): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->shouldReceive('hasPermission')->andReturn(true);
        $user->id = 7;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function actingAsHr(): void
    {
        $this->actingAsWho('read_staff_positions', 'create_staff_positions', 'update_staff_positions', 'delete_staff_positions');
    }

    private function staffIn(string $code, int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::table('sys_staff')->insert(['employment_type' => $code, 'staff_number' => $code . $i]);
        }
    }

    private function category(string $code): EmploymentCategory
    {
        return EmploymentCategory::where('code', $code)->firstOrFail();
    }

    // ── the starting list ─────────────────────────────────────────────────

    public function test_hr_starts_with_the_five_categories_in_the_order_they_asked_for(): void
    {
        $this->assertSame(
            [['Permanent', 'Permanent & Pensionable'], ['Contract', 'Contract'], ['Casual', 'Casual'], ['Intern', 'Internship'], ['Temporary', 'Temporary']],
            EmploymentCategory::ordered()->get()->map(fn ($category) => [$category->code, $category->name])->all()
        );
        $this->assertSame(5, EmploymentCategory::active()->count());
    }

    public function test_the_older_categories_keep_the_values_already_stored_on_staff(): void
    {
        // Staff hold "Permanent", "Temporary", "Casual" and "Intern"; renaming the label must not need a data change
        foreach (['Permanent', 'Temporary', 'Casual', 'Intern'] as $code) {
            $this->assertTrue(EmploymentCategory::where('code', $code)->exists(), $code);
        }
    }

    public function test_only_casual_staff_start_without_a_system_login_and_only_permanent_without_an_end_date(): void
    {
        $this->assertSame(['Casual'], EmploymentCategory::codesWithoutLogin());
        $this->assertSame(['Permanent'], EmploymentCategory::codesWithoutEndDate());
    }

    public function test_running_the_migration_again_does_not_add_duplicates_or_undo_edits(): void
    {
        $this->category('Casual')->update(['name' => 'Casual Labour', 'active' => false]);

        (require database_path('migrations/2026_11_10_100000_create_employment_categories_table.php'))->up();

        $this->assertSame(5, EmploymentCategory::count());
        $this->assertSame('Casual Labour', $this->category('Casual')->name);
        $this->assertFalse($this->category('Casual')->active);
    }

    public function test_staff_numbers_take_their_prefix_from_the_category(): void
    {
        $this->assertSame('CT', EmploymentCategory::prefixFor('Contract'));
        $this->assertSame('', EmploymentCategory::prefixFor('Permanent'), 'permanent staff have plain numbers');
        $this->assertSame('', EmploymentCategory::prefixFor('NoSuchCode'), 'a code with no category gets plain numbers');
        $this->assertSame('', EmploymentCategory::prefixFor(null));

        $this->category('Contract')->update(['staff_number_prefix' => 'K']);
        $this->assertSame('K', EmploymentCategory::prefixFor('Contract'));
    }

    public function test_the_next_staff_number_follows_the_highest_in_each_category_with_its_prefix(): void
    {
        DB::table('sys_staff')->insert([
            ['employment_type' => 'Permanent', 'staff_number' => '279'], ['employment_type' => 'Permanent', 'staff_number' => '280'],
            ['employment_type' => 'Contract', 'staff_number' => 'CT-001'], ['employment_type' => 'Contract', 'staff_number' => 'CT-007'],
            ['employment_type' => 'Casual', 'staff_number' => 'C-004'],
        ]);

        $this->assertSame('281', \App\Models\Staff::nextStaffNumber('Permanent'), 'permanent staff have plain numbers');
        $this->assertSame('CT-008', \App\Models\Staff::nextStaffNumber('Contract'));
        $this->assertSame('C-005', \App\Models\Staff::nextStaffNumber('Casual'));
        $this->assertSame('I-001', \App\Models\Staff::nextStaffNumber('Intern'), 'a category nobody is in starts at 001');
        $this->assertSame('1', \App\Models\Staff::nextStaffNumber('Seasonal'), 'a code with no category gets plain numbers');
    }

    public function test_changing_the_prefix_changes_the_next_number_but_not_the_count(): void
    {
        DB::table('sys_staff')->insert([['employment_type' => 'Contract', 'staff_number' => 'CT-005']]);
        $this->category('Contract')->update(['staff_number_prefix' => 'K']);

        $this->assertSame('K-006', \App\Models\Staff::nextStaffNumber('Contract'));
    }

    // ── showing a category ────────────────────────────────────────────────

    public function test_a_switched_off_category_still_shows_by_name_for_the_staff_in_it(): void
    {
        $this->category('Casual')->update(['active' => false, 'name' => 'Casual Labour']);

        $this->assertSame('Casual Labour', EmploymentCategory::nameFor('Casual'));
        $this->assertNotContains('Casual', EmploymentCategory::activeCodes());
    }

    public function test_an_older_code_with_no_category_shows_as_it_is_and_a_missing_one_as_a_dash(): void
    {
        $this->assertSame('Seasonal', EmploymentCategory::nameFor('Seasonal'));
        $this->assertSame('-', EmploymentCategory::nameFor(null));
        $this->assertSame('-', EmploymentCategory::nameFor(''));
    }

    // ── the list ──────────────────────────────────────────────────────────

    public function test_the_list_shows_each_category_with_how_many_staff_are_in_it(): void
    {
        $this->actingAsHr();
        $this->staffIn('Casual', 3);
        $this->staffIn('Contract', 1);

        $categories = collect($this->getJson(route('ajax.hr.employment_categories.list'))->assertOk()->json('categories'))->keyBy('code');

        $this->assertSame(3, $categories['Casual']['staff_count']);
        $this->assertSame(1, $categories['Contract']['staff_count']);
        $this->assertSame(0, $categories['Permanent']['staff_count']);
        $this->assertSame(['Permanent', 'Contract', 'Casual', 'Intern', 'Temporary'], $categories->keys()->all());
        $this->assertFalse($categories['Casual']['gets_login']);
        $this->assertFalse($categories['Permanent']['has_end_date']);
    }

    public function test_the_page_opens_for_someone_who_can_read_and_hides_adding_from_someone_who_cannot_create(): void
    {
        $this->actingAsWho('read_staff_positions');

        $page = $this->get(route('settings.hr.employment_categories'))->assertOk()->getContent();

        $this->assertStringContainsString('Employment Categories', $page);
        $this->assertStringNotContainsString('id="ecAdd"', $page);
        $this->assertStringContainsString('const can = { update: false, delete: false };', $page);

        $this->actingAsHr();
        $full = $this->get(route('settings.hr.employment_categories'))->assertOk()->getContent();
        $this->assertStringContainsString('id="ecAdd"', $full);
        $this->assertStringContainsString('const can = { update: true, delete: true };', $full);
    }

    // ── who may do what ───────────────────────────────────────────────────

    public function test_every_action_needs_its_own_permission(): void
    {
        $this->actingAsWho();
        $this->get(route('settings.hr.employment_categories'))->assertForbidden();
        $this->getJson(route('ajax.hr.employment_categories.list'))->assertForbidden();

        $this->actingAsWho('read_staff_positions');
        $this->postJson(route('ajax.hr.employment_categories.store'), ['name' => 'Fixed Term'])->assertForbidden();
        $this->postJson(route('ajax.hr.employment_categories.update', 1), ['name' => 'Renamed'])->assertForbidden();
        $this->postJson(route('ajax.hr.employment_categories.toggle', 1))->assertForbidden();
        $this->deleteJson(route('ajax.hr.employment_categories.destroy', 1))->assertForbidden();

        $this->assertSame(5, EmploymentCategory::count());
        $this->assertSame('Permanent & Pensionable', $this->category('Permanent')->name);
    }

    // ── adding ────────────────────────────────────────────────────────────

    public function test_adding_a_category_gives_it_a_code_and_puts_it_last_and_switched_on(): void
    {
        $this->actingAsHr();

        $this->postJson(route('ajax.hr.employment_categories.store'), ['name' => 'Fixed Term', 'staff_number_prefix' => 'ft', 'gets_login' => 0, 'has_end_date' => 1])
            ->assertOk()->assertJsonPath('message', 'Fixed Term was added.');

        $added = $this->category('FixedTerm');
        $this->assertSame('Fixed Term', $added->name);
        $this->assertSame('FT', $added->staff_number_prefix, 'prefixes are kept in capitals');
        $this->assertTrue($added->active);
        $this->assertFalse($added->gets_login);
        $this->assertTrue($added->has_end_date);
        $this->assertSame('FixedTerm', EmploymentCategory::ordered()->get()->last()->code);
    }

    public function test_a_new_category_gets_a_login_and_an_end_date_unless_hr_says_otherwise(): void
    {
        $this->actingAsHr();

        $this->postJson(route('ajax.hr.employment_categories.store'), ['name' => 'Consultant'])->assertOk();

        $this->assertTrue($this->category('Consultant')->gets_login);
        $this->assertTrue($this->category('Consultant')->has_end_date);
        $this->assertSame('', $this->category('Consultant')->staff_number_prefix);
    }

    public function test_two_categories_with_the_same_name_in_any_capitals_are_refused(): void
    {
        $this->actingAsHr();

        $this->postJson(route('ajax.hr.employment_categories.store'), ['name' => 'contract'])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'There is already a category with this name.');
        $this->assertSame(5, EmploymentCategory::count());
    }

    public function test_a_name_is_needed_and_the_prefix_can_only_be_letters(): void
    {
        $this->actingAsHr();

        $this->postJson(route('ajax.hr.employment_categories.store'), ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('ajax.hr.employment_categories.store'), ['name' => 'Seasonal', 'staff_number_prefix' => 'S-1'])
            ->assertUnprocessable()->assertJsonPath('errors.staff_number_prefix.0', 'The staff number prefix can only have letters.');
        $this->postJson(route('ajax.hr.employment_categories.store'), ['name' => 'Seasonal', 'staff_number_prefix' => 'TOOLONG'])
            ->assertUnprocessable()->assertJsonValidationErrors('staff_number_prefix');
    }

    public function test_a_name_that_makes_the_same_code_as_another_still_gets_its_own(): void
    {
        $this->actingAsHr();

        $this->postJson(route('ajax.hr.employment_categories.store'), ['name' => 'Fixed Term'])->assertOk();
        $this->postJson(route('ajax.hr.employment_categories.store'), ['name' => 'Fixed-Term'])->assertOk();

        $this->assertTrue(EmploymentCategory::where('code', 'FixedTerm')->exists());
        $this->assertTrue(EmploymentCategory::where('code', 'FixedTerm2')->exists());
    }

    // ── editing ───────────────────────────────────────────────────────────

    public function test_renaming_a_category_does_not_change_the_code_staff_are_stored_with(): void
    {
        $this->actingAsHr();
        $this->staffIn('Casual', 2);
        $id = $this->category('Casual')->id;

        $this->postJson(route('ajax.hr.employment_categories.update', $id), ['name' => 'Casual Labour', 'staff_number_prefix' => 'C', 'gets_login' => 0, 'has_end_date' => 1])->assertOk();

        $this->assertSame('Casual', $this->category('Casual')->code);
        $this->assertSame('Casual Labour', $this->category('Casual')->name);
        $this->assertSame(2, DB::table('sys_staff')->where('employment_type', 'Casual')->count());
    }

    public function test_a_name_can_be_saved_again_unchanged(): void
    {
        $this->actingAsHr();

        $this->postJson(route('ajax.hr.employment_categories.update', $this->category('Contract')->id), ['name' => 'Contract', 'staff_number_prefix' => 'CT'])->assertOk();
    }

    public function test_the_staff_number_prefix_is_fixed_once_staff_carry_it(): void
    {
        $this->actingAsHr();
        $this->staffIn('Contract', 2);

        $this->postJson(route('ajax.hr.employment_categories.update', $this->category('Contract')->id), ['name' => 'Contract', 'staff_number_prefix' => 'K'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'The staff number prefix cannot change while staff are in Contract, because 2 staff members already carry it.');

        $this->assertSame('CT', $this->category('Contract')->staff_number_prefix);
    }

    public function test_the_prefix_message_is_in_the_singular_for_one_staff_member(): void
    {
        $this->actingAsHr();
        $this->staffIn('Contract', 1);

        $this->postJson(route('ajax.hr.employment_categories.update', $this->category('Contract')->id), ['name' => 'Contract', 'staff_number_prefix' => 'K'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'The staff number prefix cannot change while staff are in Contract, because 1 staff member already carries it.');
    }

    public function test_the_prefix_can_change_while_nobody_is_in_the_category(): void
    {
        $this->actingAsHr();

        $this->postJson(route('ajax.hr.employment_categories.update', $this->category('Contract')->id), ['name' => 'Contract', 'staff_number_prefix' => 'K'])->assertOk();

        $this->assertSame('K', $this->category('Contract')->staff_number_prefix);
    }

    public function test_the_login_and_end_date_settings_can_be_changed_even_with_staff_in_the_category(): void
    {
        $this->actingAsHr();
        $this->staffIn('Casual');

        $this->postJson(route('ajax.hr.employment_categories.update', $this->category('Casual')->id), ['name' => 'Casual', 'staff_number_prefix' => 'C', 'gets_login' => 1, 'has_end_date' => 0])->assertOk();

        $this->assertTrue($this->category('Casual')->gets_login);
        $this->assertFalse($this->category('Casual')->has_end_date);
    }

    public function test_editing_a_category_that_is_not_there_is_a_404(): void
    {
        $this->actingAsHr();

        $this->postJson(route('ajax.hr.employment_categories.update', 999), ['name' => 'Nothing'])->assertNotFound();
    }

    // ── switching on and off ──────────────────────────────────────────────

    public function test_switching_a_category_off_says_it_is_left_out_of_new_staff_but_kept_by_those_in_it(): void
    {
        $this->actingAsHr();
        $this->staffIn('Casual');

        $this->postJson(route('ajax.hr.employment_categories.toggle', $this->category('Casual')->id))
            ->assertOk()->assertJsonPath('active', false)
            ->assertJsonPath('message', 'Casual is switched off. It is no longer offered for new staff, and the staff in it keep it.');

        $this->assertNotContains('Casual', EmploymentCategory::activeCodes());
        $this->assertSame(1, DB::table('sys_staff')->where('employment_type', 'Casual')->count());
    }

    public function test_switching_it_back_on_offers_it_again(): void
    {
        $this->actingAsHr();
        $this->category('Casual')->update(['active' => false]);

        $this->postJson(route('ajax.hr.employment_categories.toggle', $this->category('Casual')->id))
            ->assertOk()->assertJsonPath('active', true)->assertJsonPath('message', 'Casual is on again and can be chosen for new staff.');

        $this->assertContains('Casual', EmploymentCategory::activeCodes());
    }

    // ── deleting ──────────────────────────────────────────────────────────

    public function test_a_category_with_staff_in_it_cannot_be_deleted_and_the_message_says_what_to_do_instead(): void
    {
        $this->actingAsHr();
        $this->staffIn('Casual', 12);

        $this->deleteJson(route('ajax.hr.employment_categories.destroy', $this->category('Casual')->id))
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Cannot delete Casual because 12 staff members are in it. Switch it off instead, so it is no longer offered for new staff.');

        $this->assertTrue(EmploymentCategory::where('code', 'Casual')->exists(), 'nothing is lost');
    }

    public function test_one_staff_member_is_named_in_the_singular(): void
    {
        $this->actingAsHr();
        $this->staffIn('Contract');

        $this->deleteJson(route('ajax.hr.employment_categories.destroy', $this->category('Contract')->id))
            ->assertUnprocessable()->assertJsonPath('error', 'Cannot delete Contract because 1 staff member is in it. Switch it off instead, so it is no longer offered for new staff.');
    }

    public function test_a_switched_off_category_with_staff_in_it_still_cannot_be_deleted(): void
    {
        $this->actingAsHr();
        $this->staffIn('Casual');
        $this->category('Casual')->update(['active' => false]);

        $this->deleteJson(route('ajax.hr.employment_categories.destroy', $this->category('Casual')->id))->assertUnprocessable();
    }

    public function test_a_category_nobody_has_used_can_be_deleted(): void
    {
        $this->actingAsHr();
        $this->postJson(route('ajax.hr.employment_categories.store'), ['name' => 'Seasonal'])->assertOk();

        $this->deleteJson(route('ajax.hr.employment_categories.destroy', $this->category('Seasonal')->id))
            ->assertOk()->assertJsonPath('message', 'Seasonal was deleted.');

        $this->assertFalse(EmploymentCategory::where('code', 'Seasonal')->exists());
        $this->assertSame(5, EmploymentCategory::count());
    }

    // ── Add Staff ─────────────────────────────────────────────────────────

    private function errors(array $data): array
    {
        return $this->postJson(route('staff.store'), $data)->assertUnprocessable()->json('errors');
    }

    public function test_add_staff_accepts_contract_as_a_category(): void
    {
        $this->actingAsHr();

        $this->assertArrayNotHasKey('employment_type', $this->errors(['employment_type' => 'Contract']));
    }

    public function test_add_staff_refuses_a_switched_off_category(): void
    {
        $this->actingAsHr();
        $this->category('Temporary')->update(['active' => false]);

        $this->assertArrayHasKey('employment_type', $this->errors(['employment_type' => 'Temporary']));
    }

    public function test_add_staff_refuses_a_category_that_does_not_exist(): void
    {
        $this->actingAsHr();

        $this->assertArrayHasKey('employment_type', $this->errors(['employment_type' => 'Volunteer']));
    }

    public function test_add_staff_asks_for_a_login_only_when_the_category_gets_one(): void
    {
        $this->actingAsHr();

        $this->assertArrayHasKey('login_email', $this->errors(['employment_type' => 'Contract']));
        // There is no role box: the role comes from the position, so a login needs one
        $this->assertArrayHasKey('position_id', $this->errors(['employment_type' => 'Permanent']));
        $this->assertArrayNotHasKey('login_email', $this->errors(['employment_type' => 'Casual']));
        $this->assertArrayNotHasKey('position_id', $this->errors(['employment_type' => 'Casual']));
    }

    public function test_the_login_rule_follows_the_setting_not_the_name(): void
    {
        $this->actingAsHr();
        $this->category('Casual')->update(['gets_login' => true]);
        $this->category('Temporary')->update(['gets_login' => false]);

        $this->assertArrayHasKey('login_email', $this->errors(['employment_type' => 'Casual']));
        $this->assertArrayNotHasKey('login_email', $this->errors(['employment_type' => 'Temporary']));
    }

    public function test_when_every_category_gets_a_login_it_is_always_asked_for(): void
    {
        $this->actingAsHr();
        $this->category('Casual')->update(['gets_login' => true]);

        foreach (['Permanent', 'Contract', 'Casual', 'Intern', 'Temporary'] as $code) {
            $this->assertArrayHasKey('login_email', $this->errors(['employment_type' => $code]), $code);
        }
    }
}
