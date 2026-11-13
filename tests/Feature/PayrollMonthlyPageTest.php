<?php

namespace Tests\Feature;

use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * The Monthly Payroll Items page: a tidy header, three tabs with counts, an add bar whose button never wraps,
 * a search and type filter above each table, and the scripts that draw the rows.
 */
class PayrollMonthlyPageTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout(true);
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function page(array $overrides = []): string
    {
        return view('payroll.monthly', $overrides + [
            'periods' => collect([
                ['id' => 216, 'label' => '01 Nov 2026 to 30 Nov 2026'],
                ['id' => 217, 'label' => '01 Oct 2026 to 31 Oct 2026 (processed)'],
            ]),
            'selected' => 217,
        ])->render();
    }

    public function test_the_header_has_the_title_a_period_picker_and_a_way_back(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('<h5 class="mb-0">Monthly Payroll Items</h5>', $page);
        $this->assertStringContainsString('<label for="periodSelect"', $page);
        $this->assertStringContainsString('<option value="216" >01 Nov 2026 to 30 Nov 2026</option>', $page);
        $this->assertStringContainsString('<option value="217" selected>01 Oct 2026 to 31 Oct 2026 (processed)</option>', $page);
        $this->assertStringContainsString('href="' . route('payroll.processing') . '"', $page);
        $this->assertStringContainsString('id="periodInfo"', $page);
    }

    public function test_the_period_picker_is_not_a_fixed_width_that_squeezes_the_header(): void
    {
        $page = $this->page();

        $this->assertStringNotContainsString('style="min-width:260px;"', $page);
        $this->assertStringContainsString('.pm-header-tools .pm-period { flex: 0 1 19rem; width: 19rem; max-width: 100%; }', $page);
        $this->assertStringContainsString('<div class="pm-header-tools">', $page);
    }

    public function test_there_are_three_tabs_and_each_can_show_a_count(): void
    {
        $page = $this->page();

        foreach (['check' => 'Check before running', 'items' => 'One-time items', 'recurring' => 'Recurring items per staff'] as $tab => $label) {
            $this->assertStringContainsString('data-bs-target="#tab-' . $tab . '"', $page);
            $this->assertStringContainsString($label, $page);
            $this->assertStringContainsString('id="tab-' . $tab . '" role="tabpanel"', preg_replace('/ data-table="[a-z]+"/', '', $page));
        }
        foreach (['checkBadge', 'itemsBadge', 'recurringBadge'] as $badge) {
            $this->assertStringContainsString('<span class="pm-tab-count d-none" id="' . $badge . '"></span>', $page);
        }
        $this->assertStringContainsString('id="recurringTabButton"', $page);
    }

    public function test_the_add_buttons_have_room_and_say_what_they_do(): void
    {
        $page = $this->page();

        // The old narrow column squeezed the button until "Add" broke into "Ad" and "d"
        $this->assertStringNotContainsString('col-md-1', $page);
        $this->assertSame(2, substr_count($page, '<span class="material-symbols-outlined">add</span> Add item</button>'));
        $this->assertStringContainsString('.pm-add { display: inline-flex; flex: 0 0 auto; align-items: center; gap: .25rem; white-space: nowrap; }', $page);
        $this->assertStringContainsString('class="btn btn-primary btn-sm pm-add" id="addItemBtn" disabled', $page);
        $this->assertStringContainsString('class="btn btn-primary btn-sm pm-add" id="addRecurringBtn" disabled', $page);
    }

    public function test_every_field_in_the_add_bars_has_a_label_that_points_at_it(): void
    {
        $page = $this->page();

        foreach (['itemElement', 'itemStaff', 'itemAmount', 'itemNote', 'itemAll', 'recurringContract', 'recurringElement', 'recurringAmount'] as $id) {
            $this->assertStringContainsString('for="' . $id . '"', $page, $id);
            $this->assertStringContainsString('id="' . $id . '"', $page, $id);
        }
    }

    public function test_the_add_bars_start_hidden_until_the_script_knows_the_user_may_edit(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('id="itemForm" class="pm-addbar d-none"', $page);
        $this->assertStringContainsString('id="recurringForm" class="pm-addbar d-none"', $page);
    }

    public function test_each_table_has_a_search_and_all_allowances_deductions_chips(): void
    {
        $page = $this->page();

        foreach (['items' => 'Search staff, item or note', 'recurring' => 'Search staff, staff number or item'] as $table => $placeholder) {
            $this->assertStringContainsString('<div class="pm-toolbar" data-table="' . $table . '">', $page);
            $this->assertStringContainsString('placeholder="' . $placeholder . '" aria-label="' . $placeholder . '"', $page);
        }
        $this->assertSame(2, substr_count($page, '<button type="button" class="pm-chip is-active" data-type="" aria-pressed="true">All <span class="pm-chip-count">0</span></button>'));
        $this->assertSame(2, substr_count($page, 'data-type="allowance" aria-pressed="false">Allowances'));
        $this->assertSame(2, substr_count($page, 'data-type="deduction" aria-pressed="false">Deductions'));
        $this->assertSame(2, substr_count($page, 'class="pm-shown ms-auto" aria-live="polite"'));
    }

    public function test_the_tab_panes_name_their_table_so_the_clear_filters_button_knows_which_to_reset(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('id="tab-items" role="tabpanel" data-table="items"', $page);
        $this->assertStringContainsString('id="tab-recurring" role="tabpanel" data-table="recurring"', $page);
    }

    public function test_the_tables_scroll_inside_a_frame_so_their_headers_stay_in_view(): void
    {
        $page = $this->page();

        $this->assertSame(2, substr_count($page, '<div class="pm-scroll">'));
        $this->assertStringContainsString('.pm-scroll { max-height: 62vh; overflow: auto;', $page);
        $this->assertStringContainsString('.pm-table thead th { position: sticky; top: 0;', $page);
    }

    public function test_the_recurring_table_has_no_staff_column_because_staff_are_group_headers(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('<thead><tr><th>Item</th><th>Type</th><th class="pm-right">Amount</th><th></th></tr></thead>', $page);
        $this->assertStringContainsString('<thead><tr><th>Staff</th><th>Item</th><th>Type</th><th class="pm-right">Amount</th><th>Note</th><th></th></tr></thead>', $page);
    }

    public function test_an_edited_row_is_tinted_and_an_unsaved_note_is_ready(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('.pm-table tbody tr.is-dirty > td, .pm-table tbody tr.is-dirty:hover > td { background: #fffbea; }', $page);
        $this->assertStringContainsString('.pm-table .pm-btn.btn-light:disabled { background: #eef1f5;', $page);
        $this->assertStringContainsString(".prop('disabled', !dirty).toggleClass('btn-primary', dirty).toggleClass('btn-light', !dirty);", $page, 'Save turns blue only while the row has something to save');
        $this->assertStringContainsString('id="unsavedNote" class="pm-unsaved d-none" role="status"', $page);
        $this->assertStringContainsString('id="unsavedText"', $page);
    }

    public function test_the_lock_notice_and_type_colours_are_styled(): void
    {
        $page = $this->page();

        foreach (['.pm-lock {', '.pm-type--allowance {', '.pm-type--deduction {', '.pm-section--warning {', '.pm-allclear {', '.pm-stats {'] as $rule) {
            $this->assertStringContainsString($rule, $page, $rule);
        }
    }

    public function test_the_page_loads_the_row_drawing_script_before_its_own(): void
    {
        $page = $this->page();

        $include = strpos($page, 'assets/js/payroll-monthly-items.js?v=');
        $this->assertNotFalse($include);
        $this->assertLessThan(strpos($page, 'const M = window.PayrollMonthlyItems;'), $include);
    }

    public function test_the_script_talks_to_the_same_endpoints_as_before(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('const base = "' . url('/ajax/payroll/monthly') . '";', $page);
        foreach (["base + '/' + periodId() + '/items'", "base + '/items/'", "base + '/' + periodId() + '/recurring'", "base + '/contracts/'", "base + '/contract-elements/'"] as $call) {
            $this->assertStringContainsString($call, $page, $call);
        }
    }

    public function test_the_script_keeps_unsaved_edits_and_asks_before_they_are_lost(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('const drafts = {};', $page);
        $this->assertStringContainsString("if (dirty) drafts[key] = draft; else delete drafts[key];", $page);
        $this->assertStringContainsString("confirm('You have changes that are not saved. Switch period and leave them behind?')", $page);
        $this->assertStringContainsString("window.addEventListener('beforeunload'", $page);
        $this->assertStringContainsString("event.key === 'Enter'", $page);
        $this->assertStringContainsString("event.key === 'Escape'", $page);
    }

    public function test_saving_reloads_without_flashing_loading_over_the_header(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('function load(quiet)', $page);
        $this->assertStringContainsString("if (!quiet) $('#periodInfo').text('Loading...');", $page);
        $this->assertSame(0, substr_count($page, 'loadRecurring();\n                load();'), 'saving no longer asks for the recurring list twice');
        $this->assertGreaterThanOrEqual(6, substr_count($page, 'load(true);'));
    }

    public function test_the_add_buttons_wait_until_the_form_is_filled_in(): void
    {
        $page = $this->page();

        $this->assertStringContainsString("const ready = $('#itemElement').val() && ($('#itemAll').is(':checked') || $('#itemStaff').val()) && $('#itemAmount').val() !== '';", $page);
        $this->assertStringContainsString("const ready = $('#recurringContract').val() && $('#recurringElement').val() && $('#recurringAmount').val() !== '';", $page);
    }

    public function test_a_period_with_no_staff_or_items_still_renders_an_empty_page(): void
    {
        $page = $this->page(['periods' => collect(), 'selected' => null]);

        $this->assertSame(1, preg_match('/<select[^>]*id="periodSelect">(.*?)<\/select>/s', $page, $select));
        $this->assertSame('', trim($select[1]), 'no periods, no options');
    }

    public function test_period_labels_are_escaped(): void
    {
        $page = $this->page(['periods' => collect([['id' => 1, 'label' => '<script>alert(1)</script>']]), 'selected' => 1]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $page);
    }
}
