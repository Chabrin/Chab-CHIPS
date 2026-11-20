<?php

namespace Tests\Feature;

use App\Models\EmploymentCategory;
use App\Models\Staff;
use App\Models\User;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * The staff and contract forms ask the browser not to suggest what was typed for other people, and no screen asks for, shows or
 * sets a password for someone else (the new user is emailed a link instead).
 */
class StaffFormsHideSuggestionsTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
        (require database_path('migrations/2026_11_10_100000_create_employment_categories_table.php'))->up();
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturn(true);
        $user->id = 7;
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function addStaffPage(): string
    {
        return view('staff.create', [
            'departments' => collect(), 'banks' => collect(), 'positions' => collect(), 'zones' => collect(),
            'zoneAreas' => collect(), 'roles' => collect(), 'nextStaffNumber' => 'E-001',
            'onboarding' => null, 'prefill' => [], 'onboardingFiles' => [],
            'categories' => EmploymentCategory::active()->ordered()->get(), 'defaultCategory' => 'Permanent',
        ])->render();
    }

    private function formTag(string $page, string $id): string
    {
        $this->assertSame(1, preg_match('/<form\b[^>]*\bid="' . preg_quote($id, '/') . '"[^>]*>/s', $page, $match), "No form with id $id");

        return $match[0];
    }

    public function test_add_staff_and_edit_staff_ask_the_browser_for_no_suggestions(): void
    {
        $this->assertStringContainsString('autocomplete="off"', $this->formTag($this->addStaffPage(), 'staffCreateForm'));

        $staff = new Staff();
        $staff->forceFill(['id' => 5, 'staff_number' => 'E-005', 'full_names' => 'EXAMPLE STAFF']);
        $edit = view('staff.edit', [
            'staff' => $staff, 'departments' => collect(), 'banks' => collect(), 'positions' => collect(),
            'zones' => collect(), 'zoneAreas' => collect(), 'documentSlots' => collect(),
        ])->render();
        $this->assertStringContainsString('autocomplete="off"', $this->formTag($edit, 'staffEditForm'));
    }

    public function test_the_contract_forms_ask_the_browser_for_no_suggestions(): void
    {
        $create = view('staff.contracts.contracts_create', ['staffSignedContracts' => []])->render();

        $this->assertStringContainsString('autocomplete="off"', $this->formTag($create, 'staffContractCreateForm'));
    }

    public function test_add_staff_has_no_password_box_and_says_a_link_is_emailed(): void
    {
        $page = $this->addStaffPage();

        $this->assertStringNotContainsString('name="password"', $page);
        $this->assertStringContainsString('emailed a link to choose their own password', $page);
    }

    public function test_no_screen_asks_an_administrator_to_type_a_password_for_someone_else(): void
    {
        foreach (['users/systemusers.blade.php', 'staff/create.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/' . $view));
            $this->assertStringNotContainsString('name="password"', $source, $view);
        }
        $index = file_get_contents(resource_path('views/staff/index.blade.php'));
        $this->assertStringNotContainsString('convert-user-password', $index, 'The Convert to user box asks for no password');
        $this->assertStringContainsString('emailed a link to choose their own password', $index);
    }

    public function test_the_site_layout_loads_the_script_that_switches_suggestions_off(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/main.blade.php'));

        $this->assertStringContainsString("assets/js/no-autofill.js", $layout);
        $this->assertFileExists(public_path('assets/js/no-autofill.js'));
    }
}
