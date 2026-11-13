<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * The payroll review page: one slim bar for warnings, compact summary tiles, a filter bar above the employee grid,
 * aligned amounts with Net Pay highlighted, and the history as an activity log.
 */
class PayrollReviewPageTest extends TestCase
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

    private function makeRun(array $overrides = []): object
    {
        return (object) ($overrides + ['status' => 'draft', 'status_label' => 'Draft', 'processed_by' => 1, 'approved_by' => null, 'return_comment' => null, 'logs' => collect()]);
    }

    private function log(string $action, string $when, ?string $by = 'Hannah', ?string $comment = null): object
    {
        return (object) ['action' => $action, 'user' => $by === null ? null : (object) ['name' => $by], 'created_at' => Carbon::parse($when), 'comment' => $comment];
    }

    private function page(array $overrides = []): string
    {
        return view('payroll.review', $overrides + [
            'period' => (object) ['id' => 4, 'start_date' => Carbon::parse('2026-11-01'), 'end_date' => Carbon::parse('2026-11-30')],
            'run' => $this->makeRun(),
            'totals' => (object) ['employees' => 56, 'gross' => 4500000.5, 'paye' => 600000, 'nssf' => 160000, 'sha' => 120000, 'housing_levy' => 70000, 'total_deductions' => 1000000, 'net_pay' => 3500000.5],
            'warnings' => [],
            'alerts' => [],
            'alertLabels' => ['negative_net' => 'Negative pay', 'no_bank' => 'No bank details', 'multiple_contracts' => 'Several contracts'],
            'userNames' => collect([1 => 'Hannah', 2 => 'Amos']),
            'canSubmit' => false, 'canDiscard' => false, 'canDecide' => false, 'isOwnRun' => false, 'canReverseApproved' => false, 'canDownloadEft' => false,
        ])->render();
    }

    // ── the header ───────────────────────────────────────────────────────

    public function test_the_header_shows_the_month_status_and_who_did_what(): void
    {
        $page = $this->page(['run' => $this->makeRun(['status' => 'approved', 'status_label' => 'Approved', 'approved_by' => 2])]);

        $this->assertStringContainsString('Payroll Review: November 2026', $page);
        $this->assertStringContainsString('badge-light-success">Approved', $page);
        $this->assertStringContainsString('01 Nov 2026 to 30 Nov 2026', $page);
        $this->assertStringContainsString('Processed by Hannah', $page);
        $this->assertStringContainsString('Approved by Amos', $page);
    }

    public function test_hr_sees_submit_and_discard_on_a_draft_and_nothing_for_the_accountant(): void
    {
        $page = $this->page(['canSubmit' => true, 'canDiscard' => true]);

        $this->assertStringContainsString('id="submitRunBtn"', $page);
        $this->assertStringContainsString('data-title="Discard and re-run"', $page);
        $this->assertStringNotContainsString('id="approveRunBtn"', $page);
        $this->assertStringNotContainsString('data-action="return"', $page);
        $this->assertStringNotContainsString('Reverse approved payroll', $page);
        $this->assertStringNotContainsString('Download Test EFT File', $page);
    }

    public function test_the_accountant_sees_approve_and_return_on_a_run_waiting_for_them(): void
    {
        $page = $this->page(['run' => $this->makeRun(['status' => 'with_accountant', 'status_label' => 'With accountant']), 'canDecide' => true]);

        $this->assertStringContainsString('id="approveRunBtn"', $page);
        $this->assertStringContainsString('data-action="return"', $page);
        $this->assertStringNotContainsString('id="submitRunBtn"', $page);
    }

    public function test_an_approved_run_offers_reverse_and_the_test_eft_file(): void
    {
        $page = $this->page(['run' => $this->makeRun(['status' => 'approved', 'status_label' => 'Approved']), 'canReverseApproved' => true, 'canDownloadEft' => true]);

        $this->assertStringContainsString('data-title="Reverse approved payroll"', $page);
        $this->assertStringContainsString('Download Test EFT File', $page);
    }

    public function test_the_excel_download_needs_at_least_one_employee(): void
    {
        $empty = (object) ['employees' => 0, 'gross' => 0, 'paye' => 0, 'nssf' => 0, 'sha' => 0, 'housing_levy' => 0, 'total_deductions' => 0, 'net_pay' => 0];

        $this->assertStringContainsString('Download Excel', $this->page());
        $this->assertStringNotContainsString('Download Excel', $this->page(['totals' => $empty]));
    }

    public function test_every_page_script_hook_is_still_there(): void
    {
        $page = $this->page(['canSubmit' => true, 'canDiscard' => true, 'canDecide' => true]);

        foreach (['id="run-employees-table"', 'id="payslipModal"', 'id="payslipModalBody"', 'id="commentModal"', 'id="commentForm"', 'id="commentText"', 'id="commentSubmitBtn"', 'comment-action'] as $hook) {
            $this->assertStringContainsString($hook, $page, $hook);
        }
    }

    // ── alerts ───────────────────────────────────────────────────────────

    public function test_no_warnings_means_no_warning_bar(): void
    {
        $this->assertStringNotContainsString('runWarningsGroup', $this->page());
    }

    public function test_one_or_two_warnings_are_shown_open_in_one_bar(): void
    {
        $page = $this->page(['warnings' => ['Contract #7 has no staff.', 'Two contracts overlap for Jane.']]);

        $this->assertSame(1, substr_count($page, 'id="runWarningsGroup"'));
        $this->assertStringContainsString('2 warnings to check', $page);
        $this->assertStringContainsString('aria-expanded="true"', $page);
        $this->assertStringContainsString('class="collapse show" id="runWarnings"', $page);
        $this->assertStringContainsString('<span class="pr-warning-text">Contract #7 has no staff.</span>', $page);
        $this->assertStringContainsString('<span class="pr-warning-text">Two contracts overlap for Jane.</span>', $page);
    }

    public function test_a_single_warning_is_worded_in_the_singular(): void
    {
        $this->assertStringContainsString('1 warning to check', $this->page(['warnings' => ['Only one.']]));
    }

    public function test_many_warnings_are_folded_away_with_the_first_one_as_a_preview(): void
    {
        $warnings = array_map(fn ($n) => "Warning number {$n}.", range(1, 5));

        $page = $this->page(['warnings' => $warnings]);

        $this->assertSame(1, substr_count($page, 'id="runWarningsGroup"'));
        $this->assertStringContainsString('5 warnings to check', $page);
        $this->assertStringContainsString('aria-expanded="false"', $page);
        $this->assertStringContainsString('class="collapse " id="runWarnings"', $page);
        $this->assertStringContainsString('flex-grow-1" style="min-width:0;opacity:.8;">Warning number 1.</span>', $page);
        foreach ($warnings as $warning) {
            $this->assertStringContainsString("<span class=\"pr-warning-text\">{$warning}</span>", $page);
        }
    }

    public function test_warnings_are_escaped(): void
    {
        $page = $this->page(['warnings' => ['<script>alert(1)</script>']]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $page);
    }

    public function test_the_accountants_return_note_shows_only_on_a_returned_run(): void
    {
        $returned = $this->page(['run' => $this->makeRun(['status' => 'returned', 'status_label' => 'Returned', 'return_comment' => 'Fix the housing levy'])]);
        $draft = $this->page(['run' => $this->makeRun(['return_comment' => 'Old note'])]);

        $this->assertStringContainsString('Returned by the accountant:</strong> Fix the housing levy', $returned);
        $this->assertStringNotContainsString('Returned by the accountant', $draft);
    }

    public function test_the_own_run_notice_shows_only_while_the_run_is_with_the_accountant(): void
    {
        $waiting = $this->page(['run' => $this->makeRun(['status' => 'with_accountant', 'status_label' => 'With accountant']), 'isOwnRun' => true]);
        $approved = $this->page(['run' => $this->makeRun(['status' => 'approved', 'status_label' => 'Approved']), 'isOwnRun' => true]);

        $this->assertStringContainsString('another accountant must review it', $waiting);
        $this->assertStringNotContainsString('another accountant must review it', $approved);
    }

    // ── summary tiles ───────────────────────────────────────────────────

    public function test_the_summary_is_eight_compact_tiles_in_order_with_net_pay_highlighted(): void
    {
        $page = $this->page();

        preg_match_all('/<div class="pr-stat[^"]*">\s*<div class="pr-stat-label">([^<]+)<\/div>\s*<div class="pr-stat-value">([^<]+)<\/div>/', $page, $tiles, PREG_SET_ORDER);

        $this->assertSame(
            [['Employees', '56'], ['Gross', '4,500,000.50'], ['PAYE', '600,000.00'], ['NSSF', '160,000.00'], ['SHA', '120,000.00'], ['Housing Levy', '70,000.00'], ['Total Deductions', '1,000,000.00'], ['Net Pay', '3,500,000.50']],
            array_map(fn ($tile) => [$tile[1], $tile[2]], $tiles)
        );
        $this->assertSame(1, substr_count($page, 'class="pr-stat pr-stat--net"'), 'one highlighted tile');
        $this->assertMatchesRegularExpression('/<div class="pr-stat pr-stat--net">\s*<div class="pr-stat-label">Net Pay</', $page);
    }

    // ── the filter bar and the grid ──────────────────────────────────

    public function test_the_search_and_entries_controls_sit_in_a_bar_above_the_grid(): void
    {
        $page = $this->page();

        $bar = strpos($page, 'class="pr-filterbar"');
        $this->assertNotFalse($bar);
        $this->assertLessThan(strpos($page, 'id="run-employees-table"'), strpos($page, 'id="prSearch"'));
        $this->assertGreaterThan($bar, strpos($page, 'id="prSearch"'));
        $this->assertLessThan(strpos($page, 'id="run-employees-table"'), strpos($page, 'id="prPageLength"'));
        $this->assertStringContainsString('type="search" id="prSearch"', $page);
    }

    public function test_entries_per_page_offers_the_usual_sizes_with_25_chosen(): void
    {
        $page = $this->page();

        preg_match('/<select id="prPageLength".*?<\/select>/s', $page, $select);
        preg_match_all('/<option value="(-?\d+)"([^>]*)>([^<]+)<\/option>/', $select[0], $options, PREG_SET_ORDER);

        $this->assertSame([['10', '10'], ['25', '25'], ['50', '50'], ['100', '100'], ['-1', 'All']], array_map(fn ($o) => [$o[1], $o[3]], $options));
        $this->assertSame(['25'], array_values(array_map(fn ($o) => $o[1], array_filter($options, fn ($o) => str_contains($o[2], 'selected')))));
    }

    public function test_the_filter_bar_drives_the_grid_and_the_built_in_controls_are_gone(): void
    {
        $page = $this->page();

        $this->assertStringContainsString("grid.search(term).draw()", $page);
        $this->assertStringContainsString('grid.page.len(parseInt(this.value, 10)).draw()', $page);
        $this->assertStringContainsString('layout: { topStart: null, topEnd: null, bottomStart: \'info\', bottomEnd: \'paging\' }', $page);
    }

    public function test_the_header_stays_in_view_while_the_rows_scroll(): void
    {
        $page = $this->page();

        $this->assertStringContainsString("scrollY: '56vh'", $page);
        $this->assertStringContainsString('scrollCollapse: true', $page);
        $this->assertStringContainsString('grid.columns.adjust()', $page);
    }

    public function test_the_amount_headers_are_right_aligned_and_net_pay_is_the_highlighted_column(): void
    {
        $page = $this->page();

        preg_match('/<thead>.*?<\/thead>/s', $page, $head);
        preg_match_all('/<th class="([^"]*)">([^<]+)<\/th>|<th>([^<]+)<\/th>/', $head[0], $cells, PREG_SET_ORDER);
        $classes = [];
        foreach ($cells as $cell) {
            $classes[$cell[2] ?: $cell[3]] = $cell[1];
        }

        foreach (['Basic', 'Gross', 'PAYE', 'NSSF', 'SHA', 'Housing Levy', 'Other', 'Change'] as $amount) {
            $this->assertSame('num', $classes[$amount], $amount);
        }
        $this->assertSame('num col-net', $classes['Net Pay']);
        $this->assertCount(14, $classes);
        foreach (['Staff No', 'Name', 'Department', 'Flags'] as $text) {
            $this->assertSame('', $classes[$text], $text);
        }
    }

    public function test_the_grid_styles_cover_padding_hover_alignment_and_the_net_pay_tint(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('#prGrid table.dataTable tbody tr:hover > td', $page);
        $this->assertStringContainsString('#prGrid table.dataTable .num { text-align: right; font-variant-numeric: tabular-nums;', $page);
        $this->assertStringContainsString('#prGrid table.dataTable .col-net', $page);
        $this->assertStringContainsString('#prGrid table.dataTable thead th', $page);
    }

    public function test_the_grid_columns_come_from_the_helper_which_loads_first(): void
    {
        $page = $this->page();

        $this->assertSame(1, preg_match('/<script src="[^"]*assets\/js\/payroll-review-grid\.js\?v=\d+"><\/script>/', $page, $tag, PREG_OFFSET_CAPTURE));
        $this->assertLessThan(strpos($page, 'PayrollReviewGrid.columns()'), $tag[0][1]);
    }

    // ── history ──────────────────────────────────────────────────────────

    public function test_the_history_is_an_activity_log_with_one_entry_per_event(): void
    {
        $logs = collect([
            $this->log('approved', '2026-11-30 16:05', 'Amos'),
            $this->log('returned', '2026-11-29 11:20', 'Amos', "Fix the housing levy\nfor two staff"),
            $this->log('submitted', '2026-11-28 09:00', 'Hannah'),
            $this->log('processed', '2026-11-28 08:40', null),
        ]);

        $page = $this->page(['run' => $this->makeRun(['logs' => $logs])]);

        $this->assertSame(4, preg_match_all('/<li class="pr-log-item pr-log-item--[a-z]+">/', $page));
        $this->assertSame(['Approved', 'Returned to HR', 'Submitted to accountant', 'Processed'], $this->logLabels($page), 'newest first, as the run keeps it');
        $this->assertStringContainsString('by Amos', $page);
        $this->assertStringContainsString('by System', $page, 'an entry with no user was the system');
        $this->assertStringContainsString('30 Nov 2026, 16:05', $page);
        $this->assertStringContainsString('datetime="' . Carbon::parse('2026-11-30 16:05')->toIso8601String() . '"', $page);
        $this->assertStringContainsString("<div class=\"pr-log-comment\">Fix the housing levy\nfor two staff</div>", $page);
        $this->assertSame(1, substr_count($page, 'pr-log-comment">'), 'only entries with a comment get one');
    }

    private function logLabels(string $page): array
    {
        preg_match_all('/<li class="pr-log-item[^"]*">.*?<strong class="pr-log-title">([^<]+)<\/strong>/s', $page, $labels);

        return $labels[1];
    }

    public function test_each_kind_of_event_has_its_own_icon_and_colour(): void
    {
        $actions = ['processed' => ['calculate', 'secondary'], 'submitted' => ['send', 'primary'], 'approved' => ['check_circle', 'success'],
                    'returned' => ['undo', 'warning'], 'reversed' => ['restart_alt', 'danger'], 'eft_test_file' => ['download', 'info']];
        $logs = collect(array_map(fn ($action) => $this->log($action, '2026-11-30 10:00'), array_keys($actions)));

        $page = $this->page(['run' => $this->makeRun(['logs' => $logs])]);

        foreach ($actions as [$icon, $tone]) {
            $this->assertMatchesRegularExpression('/<li class="pr-log-item pr-log-item--' . $tone . '">\s*<span class="pr-log-dot"><span class="material-symbols-outlined">' . $icon . '<\/span><\/span>/', $page, $icon);
        }
        $this->assertContains('Downloaded test EFT file', $this->logLabels($page));
    }

    public function test_an_unknown_event_still_shows_under_its_own_name(): void
    {
        $page = $this->page(['run' => $this->makeRun(['logs' => collect([$this->log('something_new', '2026-11-30 10:00')])])]);

        $this->assertSame(['something_new'], $this->logLabels($page));
        $this->assertMatchesRegularExpression('/pr-log-item--secondary">\s*<span class="pr-log-dot"><span class="material-symbols-outlined">history</', $page);
    }

    public function test_a_run_with_no_history_says_so_and_draws_no_log(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('No activity recorded yet.', $page);
        $this->assertStringNotContainsString('<ol class="pr-log">', $page);
    }

    public function test_each_history_card_is_coloured_by_its_kind_of_event(): void
    {
        $page = $this->page(['run' => $this->makeRun(['logs' => collect([$this->log('approved', '2026-11-30 10:00'), $this->log('returned', '2026-11-29 10:00'), $this->log('reversed', '2026-11-28 10:00')])])]);

        $this->assertStringContainsString('pr-log-item--success', $page);
        $this->assertStringContainsString('pr-log-item--warning', $page);
        $this->assertStringContainsString('pr-log-item--danger', $page);
        foreach (['success', 'warning', 'danger', 'primary', 'info'] as $tone) {
            $this->assertMatchesRegularExpression('/\.pr-log-item--' . $tone . '\s*\{[^}]*--tone:/', $page, "the {$tone} colour is defined");
        }
        $this->assertStringContainsString('background: var(--tone-bg); border-left: 4px solid var(--tone)', $page, 'the card takes its colour from the tone');
    }

    // ── alerts that can filter the grid ─────────────────────────────────

    private function sampleAlerts(): array
    {
        return [
            ['key' => 'negative_net', 'tone' => 'danger', 'count' => 2, 'filter' => 'negative_net', 'message' => '2 employee(s) have negative net pay.'],
            ['key' => 'no_bank', 'tone' => 'danger', 'count' => 1, 'filter' => 'no_bank', 'message' => '1 employee(s) have no bank details and cannot be included in the EFT file.'],
            ['key' => 'no_contract', 'tone' => 'warning', 'count' => 2, 'filter' => null, 'message' => '2 active staff have no active contract and are not in this run.',
                'staff' => [['number' => 'E-1', 'name' => 'Beth'], ['number' => 'E-2', 'name' => 'Zane <b>']], 'more' => 0],
            ['key' => 'tax_bands', 'tone' => 'danger', 'count' => 1, 'filter' => null, 'message' => 'Tax bands overlap.', 'link' => ['label' => 'Open Tax Bands', 'url' => 'http://localhost/payroll/tax-bands']],
        ];
    }

    public function test_alerts_count_with_the_plain_notes_in_one_bar(): void
    {
        $page = $this->page(['warnings' => ['A note from processing.'], 'alerts' => $this->sampleAlerts()]);

        $this->assertSame(1, substr_count($page, 'id="runWarningsGroup"'));
        $this->assertStringContainsString('5 warnings to check', $page);
        $this->assertStringContainsString('A note from processing.', $page);
        $this->assertStringContainsString('2 employee(s) have negative net pay.', $page);
    }

    public function test_an_alert_about_employees_in_the_run_has_a_show_button_for_that_filter(): void
    {
        $page = $this->page(['alerts' => $this->sampleAlerts()]);

        $this->assertStringContainsString('class="btn btn-sm btn-outline-secondary py-0 pr-alert-action pr-alert-show" data-alert-filter="negative_net">Show 2</button>', $page);
        $this->assertStringContainsString('data-alert-filter="no_bank">Show 1</button>', $page);
        $this->assertSame(2, substr_count($page, 'pr-alert-show" data-alert-filter'), 'only the alerts that can filter the grid');
    }

    public function test_the_chips_above_the_grid_offer_all_and_each_filterable_alert_with_its_count(): void
    {
        $page = $this->page(['alerts' => $this->sampleAlerts()]);

        $this->assertSame(1, preg_match('/<div class="pr-chips" id="prAlertChips".*?<\/div>/s', $page, $group));
        preg_match_all('/<button type="button" class="pr-chip([^"]*)" data-alert-filter="([^"]*)" aria-pressed="(true|false)">\s*([^<]*?)\s*(?:<span class="pr-chip-count">(\d+)<\/span>)?\s*<\/button>/', $group[0], $chips, PREG_SET_ORDER);

        $this->assertSame(
            [[' is-active', '', 'true', 'All', ''], [' pr-chip--danger', 'negative_net', 'false', 'Negative pay', '2'], [' pr-chip--danger', 'no_bank', 'false', 'No bank details', '1']],
            array_map(fn ($chip) => [$chip[1], $chip[2], $chip[3], $chip[4], $chip[5] ?? ''], $chips)
        );
        $this->assertLessThan(strpos($page, 'id="run-employees-table"'), strpos($page, 'id="prAlertChips"'));
    }

    public function test_without_alerts_that_can_filter_there_are_no_chips_but_the_refresh_button_stays(): void
    {
        $page = $this->page(['alerts' => [$this->sampleAlerts()[3]]]);

        $this->assertStringNotContainsString('id="prAlertChips"', $page);
        $this->assertStringContainsString('id="prRefresh"', $page);
        $this->assertStringNotContainsString('id="prAlertChips"', $this->page());
    }

    public function test_a_note_appears_while_a_filter_is_on_and_is_hidden_otherwise(): void
    {
        $page = $this->page(['alerts' => $this->sampleAlerts()]);

        $this->assertStringContainsString('class="pr-filter-note d-none" id="prFilterNote"', $page);
        $this->assertStringContainsString('id="prFilterName"', $page);
        $this->assertStringContainsString('data-alert-filter="">Show all</button>', $page);
    }

    public function test_staff_with_no_contract_can_be_listed_because_they_are_not_rows(): void
    {
        $page = $this->page(['alerts' => $this->sampleAlerts()]);

        $this->assertStringContainsString('data-bs-toggle="modal" data-bs-target="#noContractModal">See who</button>', $page);
        $this->assertStringContainsString('id="noContractModal"', $page);
        $this->assertStringContainsString('<tr><td class="text-nowrap">E-1</td><td>Beth</td></tr>', $page);
        $this->assertStringContainsString('Zane &lt;b&gt;', $page, 'names are escaped');
        $this->assertStringContainsString('href="' . route('contract.create') . '" class="btn btn-primary btn-sm">Add a contract', $page);
        $this->assertStringNotContainsString('and 0 more', $page);
    }

    public function test_a_cut_off_list_says_how_many_more_there_are(): void
    {
        $alerts = $this->sampleAlerts();
        $alerts[2]['more'] = 5;

        $this->assertStringContainsString('and 5 more.', $this->page(['alerts' => $alerts]));
    }

    public function test_there_is_no_staff_list_when_nobody_is_missing_a_contract(): void
    {
        $page = $this->page(['alerts' => [$this->sampleAlerts()[0]]]);

        $this->assertStringNotContainsString('noContractModal', $page);
        $this->assertStringNotContainsString('See who', $page);
    }

    public function test_the_tax_bands_alert_links_to_where_it_is_fixed(): void
    {
        $page = $this->page(['alerts' => $this->sampleAlerts()]);

        $this->assertStringContainsString('<a href="http://localhost/payroll/tax-bands" class="btn btn-sm btn-outline-secondary py-0 pr-alert-action">Open Tax Bands</a>', $page);
    }

    public function test_the_script_sends_the_chosen_alert_to_the_server_and_reloads_the_grid(): void
    {
        $page = $this->page(['alerts' => $this->sampleAlerts()]);

        $this->assertStringContainsString("let activeFilter = '';", $page);
        $this->assertStringContainsString('data: params => { if (activeFilter) params.alert_filter = activeFilter; },', $page);
        $this->assertStringContainsString('grid.ajax.reload();', $page);
        $this->assertStringContainsString("grid.on('xhr.dt'", $page);
        $this->assertStringContainsString('json.alert_counts', $page);
        $this->assertStringContainsString('grid.ajax.reload(null, false)', $page, 'the refresh button keeps the page you are on');
        $this->assertStringContainsString('const alertLabels = {"negative_net":"Negative pay","no_bank":"No bank details","multiple_contracts":"Several contracts"};', $page);
    }

    public function test_the_filter_buttons_and_chips_share_one_handler_and_a_show_button_scrolls_to_the_grid(): void
    {
        $page = $this->page(['alerts' => $this->sampleAlerts()]);

        $this->assertStringContainsString("$(document).on('click', '[data-alert-filter]'", $page);
        $this->assertStringContainsString("scrollIntoView({ behavior: 'smooth', block: 'center' })", $page);
    }

    public function test_history_comments_are_escaped(): void
    {
        $page = $this->page(['run' => $this->makeRun(['logs' => collect([$this->log('returned', '2026-11-30 10:00', 'Amos', '<img src=x onerror=alert(1)>')])])]);

        $this->assertStringNotContainsString('<img src=x', $page);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $page);
    }
}
