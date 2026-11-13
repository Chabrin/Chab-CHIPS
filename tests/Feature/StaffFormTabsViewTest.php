<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * The Add Staff and Edit Staff pages as HR sees them: a Next button closing every tab, Save Staff on the
 * last one, and the next of kin relationship beside the next of kin name.
 */
class StaffFormTabsViewTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
        (require database_path('migrations/2026_11_10_100000_create_employment_categories_table.php'))->up();
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function actingAsHr(bool $canCreateUsers = true): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturn($canCreateUsers);
        $user->id = 7;
        $this->actingAs($user);
    }

    private function addStaffPage(array $prefill = []): string
    {
        return view('staff.create', [
            'departments' => collect(), 'banks' => collect(), 'positions' => collect(), 'zones' => collect(),
            'zoneAreas' => collect(), 'roles' => collect(), 'nextStaffNumber' => 'E-001',
            'onboarding' => null, 'prefill' => $prefill, 'onboardingFiles' => [],
            'categories' => \App\Models\EmploymentCategory::active()->ordered()->get(), 'defaultCategory' => 'Permanent',
        ])->render();
    }

    private function editStaffPage(array $attributes): string
    {
        $staff = new Staff();
        $staff->forceFill(['id' => 5, 'staff_number' => 'E-005', 'full_names' => 'EXAMPLE STAFF'] + $attributes);

        return view('staff.edit', [
            'staff' => $staff, 'departments' => collect(), 'banks' => collect(), 'positions' => collect(),
            'zones' => collect(), 'zoneAreas' => collect(), 'documentSlots' => collect(),
        ])->render();
    }

    /** The Next/Save pair of the tab with this id, as the markup has it. */
    private function footerIn(string $page, string $paneId): string
    {
        $this->assertSame(1, preg_match('/<div class="tab-pane[^"]*" id="' . $paneId . '"(?:(?!class="tab-pane).)*?<div class="staff-step-footer(?:(?!class="tab-pane).)*?<\/div>\s*<\/div>/s', $page, $match), "No step footer in the {$paneId} tab");

        return $match[0];
    }

    public function test_every_add_staff_tab_ends_with_a_next_button_and_a_save_staff_button(): void
    {
        $this->actingAsHr();

        $page = $this->addStaffPage();

        $panes = ['personal', 'employment', 'contact', 'banking', 'documents', 'user'];
        $this->assertSame(count($panes), substr_count($page, 'class="tab-pane'));
        $this->assertSame(count($panes), substr_count($page, 'class="staff-step-footer'));
        foreach ($panes as $pane) {
            $footer = $this->footerIn($page, $pane);
            $this->assertStringContainsString('js-next-step', $footer, $pane);
            $this->assertStringContainsString('js-save-step', $footer, $pane);
        }
    }

    public function test_the_footer_save_button_submits_the_add_staff_form_and_next_does_not(): void
    {
        $this->actingAsHr();

        $footer = $this->footerIn($this->addStaffPage(), 'personal');

        $this->assertMatchesRegularExpression('/<button type="button"[^>]*js-next-step/', $footer);
        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*js-save-step/', $footer);
    }

    public function test_without_the_create_users_permission_the_last_tab_is_documents(): void
    {
        $this->actingAsHr(false);

        $page = $this->addStaffPage();

        $this->assertSame(5, substr_count($page, 'class="tab-pane'));
        $this->assertSame(5, substr_count($page, 'class="staff-step-footer'));
        $this->assertStringNotContainsString('id="user"', $page);
    }

    public function test_add_staff_loads_the_script_that_runs_the_buttons(): void
    {
        $this->actingAsHr();

        $this->assertStringContainsString('assets/js/staff-form-steps.js', $this->addStaffPage());
    }

    public function test_add_staff_lists_every_relationship_beside_the_next_of_kin_name(): void
    {
        $this->actingAsHr();

        $page = $this->addStaffPage();

        $this->assertStringContainsString('name="kins_relationship"', $page);
        foreach (Staff::KIN_RELATIONSHIPS as $relationship) {
            $this->assertStringContainsString('<option value="' . $relationship . '" >', $page);
        }
    }

    public function test_add_staff_keeps_the_relationship_the_new_staff_member_chose(): void
    {
        $this->actingAsHr();

        $page = $this->addStaffPage(['kins_names' => 'Jane Example', 'kins_relationship' => 'Sibling']);

        $this->assertStringContainsString('<option value="Sibling" selected>', $page);
        $this->assertStringNotContainsString('<option value="Spouse" selected>', $page);
    }

    public function test_edit_staff_shows_the_saved_relationship(): void
    {
        $this->actingAsHr();

        $page = $this->editStaffPage(['kins_names' => 'Jane Example', 'kins_relationship' => 'Guardian']);

        $this->assertStringContainsString('<option value="Guardian" selected>', $page);
        $this->assertStringNotContainsString('<option value="Spouse" selected>', $page);
    }

    public function test_edit_staff_leaves_the_relationship_blank_for_older_records(): void
    {
        $this->actingAsHr();

        $page = $this->editStaffPage(['kins_names' => 'Jane Example']);

        $this->assertStringContainsString('name="kins_relationship"', $page);
        $this->assertStringNotContainsString('name="kins_relationship" required', $page);
        $this->assertDoesNotMatchRegularExpression('/<option value="(?:' . implode('|', Staff::KIN_RELATIONSHIPS) . ')" selected>/', $page);
    }
}
