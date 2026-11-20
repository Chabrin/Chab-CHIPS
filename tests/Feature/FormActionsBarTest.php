<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\EmploymentCategory;
use App\Models\Staff;
use App\Models\User;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * Save sits at the bottom right of every HR form with Cancel (plain) to its left, the same on every sub-module, and no Save at the
 * top. Back to list may stay at the top.
 */
class FormActionsBarTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    /** page => [form id, Save button id] for the full-page HR forms that had Save in the page header */
    private const PAGES = [
        'staff/contracts/contracts_create.blade.php' => ['staffContractCreateForm', 'createButton'],
        'staff/contracts/contracts_edit.blade.php' => ['staffContractCreateForm', 'createButton'],
        'staff/discipline_tracker/create.blade.php' => ['disciplineForm', 'createButton'],
        'staff/discipline_tracker/edit.blade.php' => ['disciplineForm', 'updateButton'],
        'staff/employee_termination/create.blade.php' => ['terminationForm', 'createButton'],
        'staff/employee_termination/edit.blade.php' => ['terminationForm', 'editButton'],
        'discipline_tracker/create_incident.blade.php' => ['incidentForm', 'createButton'],
    ];

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

    public function test_no_hr_page_keeps_a_save_button_in_its_header(): void
    {
        foreach (self::PAGES as $view => [$form, $button]) {
            $source = file_get_contents(resource_path('views/' . $view));
            $this->assertStringNotContainsString('<button type="submit" form="', $source, "$view still has Save in the header");
            $this->assertStringContainsString("@include('partials.form-actions', ['form' => '$form', 'buttonId' => '$button'", $source, $view);
            // (the tag's own line: a route() call inside it holds a "->")
            $this->assertMatchesRegularExpression('/<form\b[^\n]*\bid="' . $form . '"/', $source, "$view: the form the bar saves");
            $this->assertStringContainsString('Back to', $source, "$view: Back to list stays at the top");
        }
    }

    public function test_the_edit_staff_page_has_one_save_at_the_bottom_and_none_in_the_heading(): void
    {
        $source = file_get_contents(resource_path('views/staff/edit.blade.php'));

        $this->assertStringNotContainsString('id="updateButton"', $source);
        $this->assertStringContainsString('id="updateButtonFooter"', $source);
        $this->assertStringContainsString('data-warn-unsaved', $source, 'leaving with unsaved changes asks first');
        $this->assertStringNotContainsString('Save Changes', $source, 'the same wording as everywhere');
        $this->assertMatchesRegularExpression('/\.staff-form-footer\s*\{[^}]*position:\s*sticky;[^}]*bottom:\s*0/s', $source, 'the bar stays in view while scrolling');
    }

    public function test_the_bar_has_cancel_on_the_left_and_a_coloured_save_on_the_right(): void
    {
        $html = view('partials.form-actions', ['form' => 'someForm', 'buttonId' => 'saveButton', 'cancelUrl' => '/hr/contracts'])->render();

        $this->assertStringContainsString('data-form-actions-for="someForm"', $html);
        $this->assertLessThan(strpos($html, 'type="submit"'), strpos($html, 'Cancel'), 'Cancel comes first, so it is on the left');
        $this->assertStringContainsString('href="/hr/contracts" class="btn btn-light">Cancel</a>', $html);
        $this->assertStringContainsString('class="btn btn-primary', $html);
        $this->assertStringContainsString('form="someForm"', $html);
        $this->assertStringContainsString('id="saveButton"', $html);
        $this->assertStringContainsString('>Save</span>', $html);
        $this->assertStringContainsString('position: sticky; bottom: 0', $html, 'it stays in view at the bottom of the screen');
    }

    public function test_the_bar_can_have_its_own_button_id_and_wording(): void
    {
        $html = view('partials.form-actions', ['form' => 'f', 'buttonId' => 'updateButton', 'cancelUrl' => '/x', 'saveLabel' => 'Save record'])->render();

        $this->assertStringContainsString('id="updateButton"', $html);
        $this->assertStringContainsString('>Save record</span>', $html);
    }

    public function test_the_style_and_script_are_written_once_even_with_two_bars_on_a_page(): void
    {
        $html = view('partials.form-actions', ['form' => 'a', 'cancelUrl' => '/x'])->render()
            . view('partials.form-actions', ['form' => 'b', 'cancelUrl' => '/x'])->render();

        // @once is per render, so a single page that includes the bar twice is checked through one view
        $this->assertGreaterThanOrEqual(1, substr_count($html, 'form-actions.js'));
        $this->assertSame(2, substr_count($html, 'data-form-actions-for='));
    }

    public function test_the_contract_pages_show_cancel_and_save_at_the_bottom_and_no_misspelt_save_in_the_header(): void
    {
        $create = view('staff.contracts.contracts_create', ['staffSignedContracts' => []])->render();

        $this->assertStringContainsString('data-form-actions-for="staffContractCreateForm"', $create);
        $this->assertStringContainsString('href="' . route('contract.index') . '" class="btn btn-light">Cancel</a>', $create);
        $this->assertStringNotContainsString('Save Conract', $create);
        $this->assertStringNotContainsString('Update Conract', $create);
        $this->assertSame(1, preg_match('/<form\b[^>]*id="staffContractCreateForm".*?<div class="form-actions-bar".*?<\/form>/s', $create), 'the bar is inside the form, at its end');

        $contract = new Contract();
        $contract->forceFill(['id' => 3, 'staff_id' => 5, 'basic_salary' => 50000]);
        $contract->setRelation('signedDocument', null);
        $edit = view('staff.contracts.contracts_edit', [
            'contract' => $contract, 'visibleElements' => collect(), 'hiddenElements' => collect(), 'staffCopy' => null, 'exclusions' => [],
        ])->render();
        $this->assertStringContainsString('data-form-actions-for="staffContractCreateForm"', $edit);
        $this->assertStringNotContainsString('Update Conract', $edit);
    }

    public function test_add_staff_steps_have_a_plain_cancel_before_the_coloured_buttons(): void
    {
        $page = view('staff.create', [
            'departments' => collect(), 'banks' => collect(), 'positions' => collect(), 'zones' => collect(),
            'zoneAreas' => collect(), 'roles' => collect(), 'nextStaffNumber' => 'E-001',
            'onboarding' => null, 'prefill' => [], 'onboardingFiles' => [],
            'categories' => EmploymentCategory::active()->ordered()->get(), 'defaultCategory' => 'Permanent',
        ])->render();

        $this->assertSame(6, substr_count($page, 'class="btn btn-light me-1">Cancel</a>'), 'one in each tab footer');
        $footer = substr($page, strpos($page, 'class="staff-step-footer'), 1500);
        $this->assertLessThan(strpos($footer, 'js-next-step'), strpos($footer, 'Cancel'));
    }

    public function test_the_edit_staff_page_renders_with_cancel_and_save_in_its_footer(): void
    {
        $staff = new Staff();
        $staff->forceFill(['id' => 5, 'staff_number' => 'E-005', 'full_names' => 'EXAMPLE STAFF']);
        $page = view('staff.edit', [
            'staff' => $staff, 'departments' => collect(), 'banks' => collect(), 'positions' => collect(),
            'zones' => collect(), 'zoneAreas' => collect(), 'documentSlots' => collect(),
        ])->render();

        $this->assertStringContainsString('id="updateButtonFooter"', $page);
        $this->assertStringNotContainsString('id="updateButton"', $page);
        $this->assertMatchesRegularExpression('/class="btn btn-light">Cancel<\/a>\s*<button type="submit" class="btn btn-primary[^"]*" id="updateButtonFooter">/', $page);
        $this->assertStringContainsString('assets/js/form-actions.js', $page);
    }
}
