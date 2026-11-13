<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractElement;
use App\Models\PayrollElement;
use App\Models\User;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * The contract forms ask, for each allowance or deduction, how often it applies and what a part month does to it, and let
 * HR leave a company-wide item out for this one person. These tests look at what the two pages send to the browser.
 */
class ContractFormTermsViewTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
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

    private function createPage(): string
    {
        return view('staff.contracts.contracts_create', ['staffSignedContracts' => []])->render();
    }

    private function row(array $attributes, string $elementType): ContractElement
    {
        $row = new ContractElement();
        $row->forceFill($attributes);
        $element = new PayrollElement();
        $element->forceFill(['id' => $attributes['element_id'], 'element_type' => $elementType]);
        $row->setRelation('payrollElement', $element);

        return $row;
    }

    private function editPage(array $rows = [], array $exclusions = []): string
    {
        $contract = new Contract();
        $contract->forceFill(['id' => 3, 'staff_id' => 5, 'basic_salary' => 50000]);
        $contract->setRelation('signedDocument', null);

        return view('staff.contracts.contracts_edit', [
            'contract' => $contract, 'visibleElements' => collect($rows), 'hiddenElements' => collect(), 'staffCopy' => null,
            'exclusions' => $exclusions,
        ])->render();
    }

    /** The markup of one element row, from its opening div to the end of its terms fields. */
    private function rowMarkup(string $page, int $index): string
    {
        $start = strpos($page, 'name="elements[' . $index . '][element]"');
        $this->assertNotFalse($start, "Row {$index} is not on the page");

        return substr($page, $start, strpos($page, 'part-month-field', $start) + 900 - $start);
    }

    // ── a new row ─────────────────────────────────────────────────────

    public function test_a_new_contract_row_asks_how_often_and_what_a_part_month_does(): void
    {
        $row = $this->rowMarkup($this->createPage(), 0);

        $this->assertStringContainsString('How often?', $row);
        $this->assertStringContainsString('name="elements[0][frequency]"', $row);
        $this->assertStringContainsString('Every month', $row);
        $this->assertStringContainsString('Once only', $row);
        $this->assertStringContainsString('Part month?', $row);
        $this->assertStringContainsString('name="elements[0][prorate]"', $row);
        $this->assertStringContainsString('Reduced by the days worked (prorated)', $row);
        $this->assertStringContainsString('Full amount', $row);
    }

    public function test_a_new_row_is_every_month_with_no_month_box_to_fill_in(): void
    {
        $row = $this->rowMarkup($this->createPage(), 0);

        $this->assertMatchesRegularExpression('/<option value="monthly"\s+selected>/', $row);
        $this->assertDoesNotMatchRegularExpression('/<option value="once"\s+selected>/', $row);
        $this->assertMatchesRegularExpression('/once-month-field d-none/', $row, 'the month box stays hidden until "Once only" is chosen');
        $this->assertMatchesRegularExpression('/name="elements\[0\]\[once_month\]"[^>]*disabled/', $row, 'and is not sent');
    }

    public function test_both_forms_load_the_terms_and_exclusion_scripts_before_the_page_script(): void
    {
        foreach ([$this->createPage(), $this->editPage()] as $page) {
            foreach (['contract-element-terms', 'contract-exclusions'] as $script) {
                $this->assertSame(1, preg_match('/<script src="[^"]*assets\/js\/' . $script . '\.js\?v=\d+"><\/script>/', $page, $tag, PREG_OFFSET_CAPTURE), "{$script}.js is not loaded");
                $this->assertLessThan(strpos($page, 'ContractExclusions.create'), $tag[0][1], "{$script}.js loads after the code that uses it");
            }
            $this->assertLessThan(strpos($page, 'ContractElementTerms.fieldsHtml'), strpos($page, 'contract-element-terms.js'));
        }
    }

    public function test_rows_added_with_the_button_ask_the_same_questions(): void
    {
        foreach ([$this->createPage(), $this->editPage()] as $page) {
            $this->assertStringContainsString('${ContractElementTerms.fieldsHtml(index)}', $page);
        }
    }

    // ── a saved row ───────────────────────────────────────────────────

    public function test_a_saved_once_only_row_shows_its_month_and_its_part_month_answer(): void
    {
        $page = $this->editPage([
            $this->row(['id' => 1, 'element_id' => 4, 'entry_type_id' => 2, 'amount' => 30000, 'frequency' => 'once', 'once_month' => '2026-12-01', 'prorate' => false], '2'),
        ]);
        $row = $this->rowMarkup($page, 0);

        $this->assertMatchesRegularExpression('/<option value="once"\s+selected>/', $row);
        $this->assertStringContainsString('value="2026-12"', $row);
        $this->assertDoesNotMatchRegularExpression('/once-month-field d-none/', $row, 'the month is shown for a once-only item');
        $this->assertMatchesRegularExpression('/<option value="0"\s+selected>/', $row, 'prorate was set to Full amount');
        $this->assertMatchesRegularExpression('/name="elements\[0\]\[once_month\]"[^>]*required/', $row);
    }

    public function test_a_saved_row_that_never_had_an_answer_shows_the_old_rule_for_its_type(): void
    {
        $page = $this->editPage([
            $this->row(['id' => 1, 'element_id' => 4, 'entry_type_id' => 2, 'amount' => 30000, 'frequency' => 'monthly', 'once_month' => null, 'prorate' => null], '2'),
            $this->row(['id' => 2, 'element_id' => 5, 'entry_type_id' => 1, 'amount' => 2000, 'frequency' => 'monthly', 'once_month' => null, 'prorate' => null], '1'),
        ]);

        $this->assertMatchesRegularExpression('/<option value="1"\s+selected>/', $this->rowMarkup($page, 0), 'an allowance is reduced by the days worked');
        $this->assertMatchesRegularExpression('/<option value="0"\s+selected>/', $this->rowMarkup($page, 1), 'a deduction is charged in full');
    }

    public function test_a_saved_row_keeps_its_answer_when_its_entry_type_is_changed(): void
    {
        $page = $this->editPage([
            $this->row(['id' => 1, 'element_id' => 4, 'entry_type_id' => 2, 'amount' => 1, 'frequency' => 'monthly', 'once_month' => null, 'prorate' => true], '2'),
        ]);

        $this->assertMatchesRegularExpression('/prorate-select"[^>]*data-touched=1/', $this->rowMarkup($page, 0));
    }

    public function test_a_row_with_no_terms_columns_at_all_still_renders_as_every_month(): void
    {
        // Rows read before the new columns existed carry no attributes for them
        $page = $this->editPage([
            $this->row(['id' => 1, 'element_id' => 4, 'entry_type_id' => 2, 'amount' => 1], '2'),
        ]);

        $this->assertMatchesRegularExpression('/<option value="monthly"\s+selected>/', $this->rowMarkup($page, 0));
    }

    public function test_the_terms_of_every_saved_row_are_on_the_page(): void
    {
        $page = $this->editPage([
            $this->row(['id' => 1, 'element_id' => 4, 'entry_type_id' => 2, 'amount' => 1, 'frequency' => 'monthly', 'prorate' => null], '2'),
            $this->row(['id' => 2, 'element_id' => 5, 'entry_type_id' => 1, 'amount' => 1, 'frequency' => 'once', 'once_month' => '2026-11-01', 'prorate' => false], '1'),
            $this->row(['id' => 3, 'element_id' => 6, 'entry_type_id' => 1, 'amount' => 1, 'frequency' => 'monthly', 'prorate' => true], '1'),
        ]);

        foreach ([0, 1, 2] as $index) {
            foreach (['frequency', 'once_month', 'prorate'] as $field) {
                $this->assertStringContainsString("name=\"elements[{$index}][{$field}]\"", $page);
            }
        }
    }

    // ── company-wide items left out for this person ────────────────────

    public function test_the_all_staff_table_has_a_column_for_what_applies_to_this_person(): void
    {
        foreach ([$this->createPage(), $this->editPage()] as $page) {
            $this->assertStringContainsString('<th>For this person</th>', $page);
            $this->assertStringContainsString('colspan="5"', $page);
            $this->assertStringNotContainsString('colspan="4" class="text-muted">Enter a basic salary', $page);
            $this->assertStringContainsString('NSSF, SHA, Housing Levy and PAYE always apply and cannot be left out.', $page);
        }
    }

    public function test_the_page_script_draws_the_leave_out_control_and_says_statutory_items_always_apply(): void
    {
        foreach ([$this->createPage(), $this->editPage()] as $page) {
            $this->assertStringContainsString('left-out-toggle', $page);
            $this->assertStringContainsString('left-out-reason', $page);
            $this->assertStringContainsString('Always applies', $page);
            $this->assertStringContainsString("placeholder=\"Reason (required)\"", $page);
        }
    }

    public function test_items_already_left_out_are_in_the_form_from_the_start(): void
    {
        $page = $this->editPage([], [4 => 'Opted out', 9 => 'Has a company car']);

        $this->assertStringContainsString('<input type="hidden" name="exclusions[4][excluded]" value="1">', $page);
        $this->assertStringContainsString('<input type="hidden" name="exclusions[4][reason]" value="Opted out">', $page);
        $this->assertStringContainsString('<input type="hidden" name="exclusions[9][reason]" value="Has a company car">', $page);
        $this->assertStringContainsString('data-left-out="{&quot;4&quot;:&quot;Opted out&quot;,&quot;9&quot;:&quot;Has a company car&quot;}"', $page);
    }

    public function test_a_contract_with_nothing_left_out_sends_no_exclusions(): void
    {
        foreach ([$this->createPage(), $this->editPage()] as $page) {
            $this->assertStringNotContainsString('name="exclusions[', substr($page, 0, strpos($page, '<script')));
            $this->assertStringContainsString('data-left-out="{}"', $page);
        }
    }

    public function test_a_reason_with_markup_in_it_is_escaped(): void
    {
        $page = $this->editPage([], [4 => '"><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
        $this->assertStringContainsString('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $page);
    }

    public function test_what_was_left_out_comes_back_after_a_refused_save(): void
    {
        // The form was refused (for example a missing reason on another item): what was typed is shown again, not the saved state
        request()->setLaravelSession(app('session.store'));
        session()->put('_old_input', ['exclusions' => [
            '4' => ['excluded' => '1', 'reason' => 'Typed just now'],
            '5' => ['reason' => 'Box was not ticked'],
        ]]);

        $page = $this->editPage([], [7 => 'Saved earlier']);

        $this->assertStringContainsString('name="exclusions[4][reason]" value="Typed just now"', $page);
        $this->assertStringNotContainsString('name="exclusions[5]', $page, 'an item whose box was not ticked is not left out');
        $this->assertStringNotContainsString('name="exclusions[7]', $page, 'what was typed replaces what was saved');
    }
}
