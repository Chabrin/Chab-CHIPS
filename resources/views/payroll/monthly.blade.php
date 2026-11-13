@extends('layouts.main')

@section('styles')
<style>
    /* Monthly payroll items page. Everything is scoped to .pm-* classes so nothing else on the site changes. */

    /* Header */
    .pm-header-tools { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .pm-header-tools .pm-period { flex: 0 1 19rem; width: 19rem; max-width: 100%; }
    .pm-lock { display: flex; align-items: flex-start; gap: .5rem; margin-top: .5rem; padding: .45rem .75rem; font-size: .8125rem; color: #6b5200; background: #fffbea; border: 1px solid #f3dfa2; border-radius: .5rem; }
    .pm-lock .material-symbols-outlined { font-size: 18px; }
    .pm-unsaved { display: flex; align-items: center; gap: .4rem; margin-bottom: .75rem; padding: .35rem .7rem; font-size: .8125rem; color: #1f4f70; background: #eef6fc; border: 1px solid #cfe6f6; border-radius: .5rem; }
    .pm-unsaved .material-symbols-outlined { font-size: 18px; }

    /* Tabs, with a small count on each */
    .pm-tabs .nav-link { display: inline-flex; align-items: center; gap: .4rem; }
    .pm-tab-count { min-width: 1.35rem; padding: 0 .35rem; font-size: .7rem; font-weight: 600; line-height: 1.4; text-align: center; border-radius: 999px; background: #e8edf3; color: #3d4b5a; }
    .pm-tab-count--warning { background: #fff2cc; color: #8a5a00; }
    .pm-tab-count--info { background: #e3f1fa; color: #1f6f9f; }

    /* Summary tiles on the check tab */
    .pm-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
    @media (min-width: 768px) { .pm-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .pm-stat { border: 1px solid #e3e8ef; border-radius: .5rem; padding: .4rem .7rem; background: #fff; }
    .pm-stat-label { margin: 0; font-size: .68rem; line-height: 1.2; letter-spacing: .04em; text-transform: uppercase; color: #6b7a8c; }
    .pm-stat-value { font-size: 1rem; line-height: 1.35; font-weight: 600; font-variant-numeric: tabular-nums; color: #1f2d3d; }

    /* What the check found: a card per finding that opens to the people it is about */
    .pm-section { --tone: #3598db; margin-bottom: .6rem; border: 1px solid #e3e8ef; border-left: 4px solid var(--tone); border-radius: .5rem; background: #fff; }
    .pm-section--warning { --tone: #e0a100; }
    .pm-section summary { display: flex; align-items: center; gap: .75rem; padding: .55rem .85rem; cursor: pointer; list-style: none; }
    .pm-section summary::-webkit-details-marker { display: none; }
    .pm-section-head { display: flex; flex: 1 1 auto; flex-direction: column; min-width: 0; }
    .pm-section-title { font-weight: 600; color: #1f2d3d; }
    .pm-section-hint { font-size: .78rem; color: #6b7a8c; }
    .pm-section-count { min-width: 1.5rem; padding: 0 .45rem; font-size: .75rem; font-weight: 600; text-align: center; border-radius: 999px; background: #e3f1fa; color: #1f6f9f; }
    .pm-section--warning .pm-section-count { background: #fff2cc; color: #8a5a00; }
    .pm-chevron { transition: transform .15s ease; color: #6b7a8c; }
    .pm-section[open] .pm-chevron { transform: rotate(180deg); }
    .pm-section table { border-top: 1px solid #eef1f5; font-size: .8125rem; }
    .pm-allclear { display: flex; align-items: center; gap: .5rem; padding: .6rem .85rem; color: #1f7a45; background: #eef8f1; border: 1px solid #cdebd8; border-radius: .5rem; }

    /* The bar for adding an item: the fields share the row and the button never wraps */
    .pm-addbar { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .6rem; margin-bottom: .9rem; padding: .75rem; background: #f6f8fb; border: 1px solid #e3e8ef; border-radius: .5rem; }
    .pm-field { display: flex; flex: 1 1 11rem; flex-direction: column; gap: .2rem; min-width: 0; }
    .pm-field--wide { flex-basis: 15rem; }
    .pm-field--narrow { flex: 0 1 9rem; }
    .pm-field > label, .pm-field-head > label { margin: 0; font-size: .72rem; font-weight: 600; line-height: 1.5rem; letter-spacing: .03em; text-transform: uppercase; color: #5b6b7c; }
    /* The "all staff" tick sits on the label row, so every field in the bar is the same height and the labels line up */
    .pm-field-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; height: 1.5rem; }
    .pm-all { display: inline-flex; align-items: center; gap: .3rem; min-height: 0; margin: 0; padding: 0; }
    .pm-all .form-check-input { float: none; flex: 0 0 auto; width: 1rem; height: 1rem; margin: 0; }
    .pm-all .form-check-label { font-size: .75rem; font-weight: 400; letter-spacing: 0; text-transform: none; color: #5b6b7c; line-height: 1.5rem; }
    .pm-add { display: inline-flex; flex: 0 0 auto; align-items: center; gap: .25rem; white-space: nowrap; }
    .pm-add .material-symbols-outlined, .pm-btn .material-symbols-outlined { font-size: 18px; }

    /* Search and type filter above a table */
    .pm-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; margin-bottom: .6rem; }
    .pm-search { position: relative; flex: 1 1 16rem; max-width: 22rem; }
    .pm-search .material-symbols-outlined { position: absolute; left: .5rem; top: 50%; transform: translateY(-50%); font-size: 18px; color: #8794a3; pointer-events: none; }
    .pm-search input { padding-left: 2rem; }
    .pm-chips { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; }
    .pm-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .65rem; font-size: .78rem; line-height: 1.4; border: 1px solid #d5dce5; border-radius: 999px; background: #fff; color: #3d4b5a; cursor: pointer; }
    .pm-chip:hover { background: #f2f7fc; }
    .pm-chip.is-active { background: var(--theme-default, #3598db); border-color: var(--theme-default, #3598db); color: #fff; }
    .pm-chip-count { min-width: 1.25rem; padding: 0 .3rem; border-radius: 999px; font-size: .7rem; font-weight: 600; text-align: center; background: rgba(0, 0, 0, .08); }
    .pm-chip.is-active .pm-chip-count { background: rgba(255, 255, 255, .25); color: #fff; }
    .pm-shown { font-size: .78rem; color: #6b7a8c; }

    /* The tables: the header stays in view while the rows scroll, and an edited row is tinted until it is saved */
    .pm-scroll { max-height: 62vh; overflow: auto; border: 1px solid #e3e8ef; border-radius: .5rem; }
    .pm-table { min-width: 36rem; margin: 0; font-size: .8125rem; }
    .pm-table thead th { position: sticky; top: 0; z-index: 2; padding: .55rem .75rem; background: #f6f8fb; color: #5b6b7c; font-size: .72rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; white-space: nowrap; box-shadow: inset 0 -1px 0 #dfe5ec; }
    .pm-table thead th.pm-right { text-align: right; }
    .pm-table tbody td { padding: .4rem .75rem; vertical-align: middle; border-bottom: 1px solid #eef1f5; }
    .pm-table tbody tr:not(.pm-group):not(.pm-empty):hover > td { background: #f2f7fc; }
    .pm-table tbody tr.is-dirty > td, .pm-table tbody tr.is-dirty:hover > td { background: #fffbea; }
    .pm-table tr.is-dirty .pm-amount, .pm-table tr.is-dirty .item-note { border-color: #e0a100; }
    .pm-table .pm-group th { padding: .45rem .75rem; background: #eef3f8; border-top: 1px solid #dfe5ec; border-bottom: 1px solid #dfe5ec; font-weight: 400; }
    .pm-group-line { display: flex; flex-wrap: wrap; align-items: baseline; gap: .25rem .6rem; }
    .pm-group-name { font-weight: 600; color: #1f2d3d; }
    .pm-group-count { font-size: .72rem; color: #6b7a8c; }
    .pm-group-sums { display: inline-flex; gap: .6rem; margin-left: auto; font-size: .78rem; font-variant-numeric: tabular-nums; }
    .pm-sum--allowance { color: #1f7a45; }
    .pm-sum--deduction { color: #a32e2e; }
    .pm-number { font-size: .75rem; color: #6b7a8c; font-variant-numeric: tabular-nums; }
    .pm-item-cell { padding-left: 1.5rem !important; }
    .pm-terms { display: block; font-size: .75rem; color: #6b7a8c; }
    .pm-type { display: inline-block; padding: .1rem .55rem; font-size: .72rem; font-weight: 600; border-radius: 999px; }
    .pm-type--allowance { background: #eef8f1; color: #1f7a45; }
    .pm-type--deduction { background: #fdeeee; color: #a32e2e; }
    .pm-amount-cell { width: 11rem; text-align: right; white-space: nowrap; }
    .pm-amount { width: 9.5rem; margin-left: auto; text-align: right; font-variant-numeric: tabular-nums; }
    .pm-amount-text { font-variant-numeric: tabular-nums; }
    .pm-note-cell { min-width: 12rem; }
    .pm-actions-cell { width: 9.5rem; text-align: right; white-space: nowrap; }
    .pm-btn { display: inline-flex; align-items: center; gap: .2rem; }
    .pm-table .pm-btn.btn-light:disabled { background: #eef1f5; border-color: #eef1f5; color: #9aa6b2; opacity: 1; }
    .pm-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; padding: 0; }
    .pm-icon-btn .material-symbols-outlined { font-size: 18px; }
    .pm-empty td { padding: 1.25rem .75rem; text-align: center; color: #6b7a8c; }
    .pm-empty .material-symbols-outlined { vertical-align: middle; font-size: 20px; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card mb-3">
            <div class="card-header card-no-border d-flex align-items-start flex-wrap gap-2 py-3">
                <div class="me-auto">
                    <h5 class="mb-0">Monthly Payroll Items</h5>
                    <div class="text-muted small mt-1">Check a period, add one-time items and adjust recurring items before you run payroll.</div>
                </div>
                <div class="pm-header-tools">
                    <label for="periodSelect" class="form-label mb-0 small text-muted">Period</label>
                    <select class="form-select form-select-sm pm-period" id="periodSelect">
                        @foreach($periods as $period)
                        <option value="{{ $period['id'] }}" {{ (int) $selected === (int) $period['id'] ? 'selected' : '' }}>{{ $period['label'] }}</option>
                        @endforeach
                    </select>
                    <a href="{{ route('payroll.processing') }}" class="btn btn-light btn-sm d-inline-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span> Payroll Processing
                    </a>
                </div>
            </div>
            <div class="card-body pt-0">
                <div id="periodInfo" class="mb-3 text-muted">Loading...</div>
                <div id="unsavedNote" class="pm-unsaved d-none" role="status">
                    <span class="material-symbols-outlined">edit_note</span><span id="unsavedText"></span>
                </div>

                <ul class="nav nav-tabs pm-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-check" type="button" role="tab">Check before running <span class="pm-tab-count d-none" id="checkBadge"></span></button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-items" type="button" role="tab">One-time items <span class="pm-tab-count d-none" id="itemsBadge"></span></button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-recurring" type="button" role="tab" id="recurringTabButton">Recurring items per staff <span class="pm-tab-count d-none" id="recurringBadge"></span></button></li>
                </ul>

                <div class="tab-content pt-3">
                    <div class="tab-pane fade show active" id="tab-check" role="tabpanel">
                        <div id="checkSummary" class="pm-stats mb-3"></div>
                        <p class="text-muted small mb-2">This only reports. It never stops a run.</p>
                        <div id="checkSections"></div>
                    </div>

                    <div class="tab-pane fade" id="tab-items" role="tabpanel" data-table="items">
                        <p class="text-muted small">One-time items apply to this period only. They are added when the period is run, and stay here if the run is reversed and run again.</p>
                        <div id="itemForm" class="pm-addbar d-none">
                            <div class="pm-field pm-field--wide">
                                <label for="itemElement">Item</label>
                                <select class="form-select form-select-sm" id="itemElement"></select>
                            </div>
                            <div class="pm-field pm-field--wide">
                                <div class="pm-field-head">
                                    <label for="itemStaff">Staff</label>
                                    <span class="form-check pm-all">
                                        <input class="form-check-input" type="checkbox" id="itemAll">
                                        <label class="form-check-label" for="itemAll">Apply to all staff in the run</label>
                                    </span>
                                </div>
                                <select class="form-select form-select-sm" id="itemStaff"></select>
                            </div>
                            <div class="pm-field pm-field--narrow">
                                <label for="itemAmount">Amount</label>
                                <input type="number" class="form-control form-control-sm" id="itemAmount" min="0" step="0.01" inputmode="decimal">
                            </div>
                            <div class="pm-field pm-field--wide">
                                <label for="itemNote">Note (optional)</label>
                                <input type="text" class="form-control form-control-sm" id="itemNote" maxlength="255">
                            </div>
                            <button type="button" class="btn btn-primary btn-sm pm-add" id="addItemBtn" disabled><span class="material-symbols-outlined">add</span> Add item</button>
                        </div>
                        @include('payroll.partials.monthly-toolbar', ['table' => 'items', 'placeholder' => 'Search staff, item or note'])
                        <div class="pm-scroll">
                            <table class="table pm-table align-middle" id="itemsTable">
                                <thead><tr><th>Staff</th><th>Item</th><th>Type</th><th class="pm-right">Amount</th><th>Note</th><th></th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-recurring" role="tabpanel" data-table="recurring">
                        <p class="text-muted small" id="recurringHint">These are the recurring items on each contract in this run. A change here is saved to the contract, so it applies to this period and every one after it.</p>
                        <div id="recurringForm" class="pm-addbar d-none">
                            <div class="pm-field pm-field--wide">
                                <label for="recurringContract">Staff</label>
                                <select class="form-select form-select-sm" id="recurringContract"></select>
                            </div>
                            <div class="pm-field pm-field--wide">
                                <label for="recurringElement">Item</label>
                                <select class="form-select form-select-sm" id="recurringElement"></select>
                            </div>
                            <div class="pm-field pm-field--narrow">
                                <label for="recurringAmount">Amount</label>
                                <input type="number" class="form-control form-control-sm" id="recurringAmount" min="0" step="0.01" inputmode="decimal">
                            </div>
                            <button type="button" class="btn btn-primary btn-sm pm-add" id="addRecurringBtn" disabled><span class="material-symbols-outlined">add</span> Add item</button>
                        </div>
                        @include('payroll.partials.monthly-toolbar', ['table' => 'recurring', 'placeholder' => 'Search staff, staff number or item'])
                        <div class="pm-scroll">
                            <table class="table pm-table align-middle" id="recurringTable">
                                <thead><tr><th>Item</th><th>Type</th><th class="pm-right">Amount</th><th></th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/payroll-monthly-items.js') }}?v={{ filemtime(public_path('assets/js/payroll-monthly-items.js')) }}"></script>
<script>
    $(document).ready(function() {
        const M = window.PayrollMonthlyItems;
        const base = "{{ url('/ajax/payroll/monthly') }}";
        const csrf = $('meta[name="csrf-token"]').attr('content');
        let state = null;
        let recurring = null;
        let currentPeriod = $('#periodSelect').val();
        // Edits typed but not saved yet, kept while a list is filtered or reloaded: 'r12' is a recurring row, 'i7' a one-time item
        const drafts = {};
        const view = { items: { query: '', type: '' }, recurring: { query: '', type: '' } };

        function esc(value) {
            return $('<span>').text(value === null || value === undefined ? '' : value).html();
        }

        function api(method, url, data) {
            return $.ajax({ url: url, type: method, data: data || {}, dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } });
        }

        function fail(xhr) {
            let message = 'Something went wrong.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                message = Object.values(xhr.responseJSON.errors).flat().map(esc).join('<br>');
            } else if (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) {
                message = esc(xhr.responseJSON.error || xhr.responseJSON.message);
            }
            toastr.error(message);
        }

        function periodId() {
            return $('#periodSelect').val();
        }

        function option(value, text) {
            return '<option value="' + esc(value) + '">' + esc(text) + '</option>';
        }

        // Fill a list but keep what was chosen in it, so adding several items for one person does not mean choosing them again
        function fillSelect($select, html) {
            const previous = $select.val();
            $select.html(html);
            if (previous && $select.find('option').filter(function() { return this.value === previous; }).length) {
                $select.val(previous);
            }
        }

        function editable() {
            return state && !state.locked;
        }

        // ── Unsaved edits ──────────────────────────────────────────────
        function renderUnsaved() {
            const count = Object.keys(drafts).length;
            $('#unsavedNote').toggleClass('d-none', !count);
            $('#unsavedText').text(count === 1 ? '1 change is not saved yet. Press Save on its row.' : count + ' changes are not saved yet. Press Save on each row.');
        }

        // Drop edits for rows that are gone, or that now match what is saved
        function pruneDrafts() {
            const saved = {};
            ((state && state.items) || []).forEach(function(item) { saved['i' + item.id] = item; });
            ((recurring && recurring.rows) || []).forEach(function(row) { saved['r' + row.row_id] = row; });
            Object.keys(drafts).forEach(function(key) {
                const isRecurringKey = key.charAt(0) === 'r';
                if ((isRecurringKey && !recurring) || (!isRecurringKey && !state)) return;
                if (!saved[key] || !M.isDirty(saved[key], drafts[key])) delete drafts[key];
            });
        }

        function clearDrafts() {
            Object.keys(drafts).forEach(function(key) { delete drafts[key]; });
        }

        // ── Load a period ───────────────────────────────────────────────
        // quiet: refresh after a save without flashing "Loading..." over the header
        function load(quiet) {
            if (!periodId()) {
                $('#periodInfo').text('There are no payroll periods yet. Add them in the Payroll Calendar.');
                return;
            }
            history.replaceState(null, '', '?period=' + periodId());
            if (!quiet) $('#periodInfo').text('Loading...');
            recurring = null;
            $.getJSON(base + '/' + periodId()).done(function(res) {
                state = res;
                renderHeader();
                renderCheck();
                renderItems();
                if ($('#tab-recurring').hasClass('active')) {
                    loadRecurring();
                }
                pruneDrafts();
                renderUnsaved();
            }).fail(function(xhr) {
                $('#periodInfo').text('');
                fail(xhr);
            });
        }

        function setBadge(id, count, tone) {
            const $badge = $('#' + id);
            $badge.text(count).toggleClass('d-none', !count).removeClass('pm-tab-count--warning pm-tab-count--info');
            if (tone) $badge.addClass('pm-tab-count--' + tone);
        }

        function renderHeader() {
            $('#periodInfo').removeClass('text-muted').html(M.headerHtml(state));
            $('#recurringHint').text(M.recurringHint(state.locked));
        }

        // ── Check before running ───────────────────────────────────────
        function renderCheck() {
            const c = state.check;
            $('#checkSummary').html(M.checkSummaryHtml(c.summary));
            $('#checkSections').html(M.checkSectionsHtml(c.sections));
            const badge = M.checkBadge(c);
            setBadge('checkBadge', badge.count, badge.tone);
        }

        // ── Search and filter above a table ────────────────────────────
        function syncToolbar(name, all, shown) {
            const $bar = $('.pm-toolbar[data-table="' + name + '"]');
            const counts = M.typeCounts(all);
            $bar.find('.pm-chip').each(function() {
                const type = $(this).attr('data-type');
                const active = type === view[name].type;
                $(this).toggleClass('is-active', active).attr('aria-pressed', active ? 'true' : 'false');
                $(this).find('.pm-chip-count').text(counts[type || 'all']);
            });
            const $search = $bar.find('.pm-search-input');
            if ($search.val() !== view[name].query) $search.val(view[name].query);
            $bar.find('.pm-shown').text(M.shownText(shown, all.length));
        }

        function renderTable(name) {
            if (name === 'items') renderItemsTable(); else renderRecurringTable();
        }

        $(document).on('input', '.pm-search-input', function() {
            const name = $(this).closest('.pm-toolbar').attr('data-table');
            view[name].query = this.value;
            renderTable(name);
        });

        $(document).on('click', '.pm-toolbar .pm-chip', function() {
            const name = $(this).closest('.pm-toolbar').attr('data-table');
            view[name].type = $(this).attr('data-type');
            renderTable(name);
        });

        $(document).on('click', '.pm-clear-filters', function() {
            const name = $(this).closest('[data-table]').attr('data-table');
            view[name] = { query: '', type: '' };
            renderTable(name);
        });

        // ── One-time items ─────────────────────────────────────────────
        function renderItems() {
            const canEdit = state.can_edit_items && editable();
            $('#itemForm').toggleClass('d-none', !canEdit);

            if (canEdit) {
                fillSelect($('#itemElement'), option('', 'Select item') + state.elements.map(function(e) {
                    return option(e.id, e.name + ' (' + M.typeName(e.type) + ')');
                }).join(''));
                fillSelect($('#itemStaff'), option('', 'Select staff') + state.staff.map(function(s) {
                    return option(s.id, s.name + (s.number ? ' - ' + s.number : ''));
                }).join(''));
                if (!state.elements.length) {
                    $('#itemElement').html(option('', 'No one-time items yet. Set an element to One-time under Payroll Elements.'));
                }
                syncAddItem();
            }
            renderItemsTable();
        }

        function renderItemsTable() {
            if (!state) return;
            const canEdit = state.can_edit_items && editable();
            const v = { canEdit: canEdit, drafts: drafts, query: view.items.query, type: view.items.type };
            $('#itemsTable tbody').html(M.itemsBody(state.items, v));
            syncToolbar('items', state.items, M.filterRows(state.items, v.query, v.type).length);
            setBadge('itemsBadge', state.items.length);
        }

        function syncAddItem() {
            const ready = $('#itemElement').val() && ($('#itemAll').is(':checked') || $('#itemStaff').val()) && $('#itemAmount').val() !== '';
            $('#addItemBtn').prop('disabled', !ready);
        }

        $('#itemElement, #itemStaff, #itemAmount').on('input change', syncAddItem);

        $('#itemAll').on('change', function() {
            $('#itemStaff').prop('disabled', this.checked);
            syncAddItem();
        });

        $('#addItemBtn').on('click', function() {
            $(this).prop('disabled', true);
            api('POST', base + '/' + periodId() + '/items', {
                element_id: $('#itemElement').val(),
                staff_id: $('#itemStaff').val(),
                apply_to_all: $('#itemAll').is(':checked') ? 1 : 0,
                amount: $('#itemAmount').val(),
                note: $('#itemNote').val()
            }).done(function() {
                toastr.success('Item added');
                $('#itemAmount, #itemNote').val('');
                load(true);
            }).fail(function(xhr) {
                fail(xhr);
                syncAddItem();
            });
        });

        $(document).on('click', '.save-item', function() {
            const $row = $(this).closest('tr');
            const $button = $(this).prop('disabled', true);
            api('POST', base + '/items/' + $row.attr('data-id'), {
                amount: $row.find('.item-amount').val(),
                note: $row.find('.item-note').val()
            }).done(function() {
                delete drafts[$row.attr('data-key')];
                toastr.success('Saved');
                load(true);
            }).fail(function(xhr) {
                $button.prop('disabled', false);
                fail(xhr);
            });
        });

        $(document).on('click', '.delete-item', function() {
            if (!confirm('Remove this one-time item?')) return;
            const $row = $(this).closest('tr');
            api('DELETE', base + '/items/' + $row.attr('data-id')).done(function() {
                delete drafts[$row.attr('data-key')];
                toastr.success('Removed');
                load(true);
            }).fail(fail);
        });

        // ── Recurring items per staff ──────────────────────────────────
        function loadRecurring() {
            $.getJSON(base + '/' + periodId() + '/recurring').done(function(res) {
                recurring = res;
                renderRecurring();
                pruneDrafts();
                renderUnsaved();
            }).fail(fail);
        }

        $('#recurringTabButton').on('shown.bs.tab', function() {
            if (!recurring && state) loadRecurring();
        });

        function renderRecurring() {
            const canEdit = state.can_edit_recurring;
            $('#recurringForm').toggleClass('d-none', !canEdit);

            if (canEdit) {
                fillSelect($('#recurringContract'), option('', 'Select staff') + recurring.contracts.map(function(c) {
                    return option(c.id, c.name + (c.number ? ' - ' + c.number : ''));
                }).join(''));
                fillSelect($('#recurringElement'), option('', 'Select item') + recurring.elements.map(function(e) {
                    return option(e.id, e.name + ' (' + M.typeName(e.type) + ')');
                }).join(''));
                syncAddRecurring();
            }
            renderRecurringTable();
        }

        function renderRecurringTable() {
            if (!recurring) return;
            const v = { canEdit: state.can_edit_recurring, drafts: drafts, query: view.recurring.query, type: view.recurring.type };
            $('#recurringTable tbody').html(M.recurringBody(recurring.rows, v));
            syncToolbar('recurring', recurring.rows, M.filterRows(recurring.rows, v.query, v.type).length);
            setBadge('recurringBadge', recurring.rows.length);
        }

        function syncAddRecurring() {
            const ready = $('#recurringContract').val() && $('#recurringElement').val() && $('#recurringAmount').val() !== '';
            $('#addRecurringBtn').prop('disabled', !ready);
        }

        $('#recurringContract, #recurringElement, #recurringAmount').on('input change', syncAddRecurring);

        // Offer the amount the element's formula gives when the amount is still empty
        $('#recurringElement').on('change', function() {
            if ($('#recurringAmount').val() !== '') return;
            const el = (recurring.elements || []).find(function(e) { return String(e.id) === String($('#recurringElement').val()); });
            if (el && el.formula === 1 && el.default > 0) {
                $('#recurringAmount').val(el.default.toFixed(2));
                syncAddRecurring();
            }
        });

        $('#addRecurringBtn').on('click', function() {
            $(this).prop('disabled', true);
            api('POST', base + '/contracts/' + $('#recurringContract').val() + '/elements', {
                element_id: $('#recurringElement').val(),
                amount: $('#recurringAmount').val()
            }).done(function() {
                toastr.success('Added to the contract');
                // Keep the staff member chosen for the next item, start the item and amount afresh
                $('#recurringAmount').val('');
                $('#recurringElement').val('');
                load(true);
            }).fail(function(xhr) {
                fail(xhr);
                syncAddRecurring();
            });
        });

        $(document).on('click', '.save-recurring', function() {
            const $row = $(this).closest('tr');
            const $button = $(this).prop('disabled', true);
            api('POST', base + '/contract-elements/' + $row.attr('data-id'), { amount: $row.find('.recurring-amount').val() }).done(function() {
                delete drafts[$row.attr('data-key')];
                toastr.success('Saved to the contract');
                load(true);
            }).fail(function(xhr) {
                $button.prop('disabled', false);
                fail(xhr);
            });
        });

        $(document).on('click', '.remove-recurring', function() {
            if (!confirm('Remove this item from the contract? It will stop in this period and every one after it.')) return;
            const $row = $(this).closest('tr');
            api('DELETE', base + '/contract-elements/' + $row.attr('data-id')).done(function() {
                delete drafts[$row.attr('data-key')];
                toastr.success('Removed from the contract');
                load(true);
            }).fail(fail);
        });

        // ── Editing a row ──────────────────────────────────────────────
        // Typing marks the row, and only then can it be saved. Enter saves it, Escape puts back what was saved.
        $(document).on('input', '.pm-table .pm-amount, .pm-table .item-note', function() {
            const $row = $(this).closest('tr');
            const key = $row.attr('data-key');
            const $amount = $row.find('.pm-amount');
            const $note = $row.find('.item-note');
            const original = { amount: $amount.attr('data-original') };
            const draft = { amount: $amount.val() };
            if ($note.length) {
                original.note = $note.attr('data-original');
                draft.note = $note.val();
            }
            const dirty = M.isDirty(original, draft);
            if (dirty) drafts[key] = draft; else delete drafts[key];
            $row.toggleClass('is-dirty', dirty);
            $row.find('.save-recurring, .save-item').prop('disabled', !dirty).toggleClass('btn-primary', dirty).toggleClass('btn-light', !dirty);
            renderUnsaved();
        });

        $(document).on('keydown', '.pm-table .pm-amount, .pm-table .item-note', function(event) {
            const $row = $(this).closest('tr');
            if (event.key === 'Enter') {
                event.preventDefault();
                $row.find('.save-recurring:not(:disabled), .save-item:not(:disabled)').trigger('click');
            } else if (event.key === 'Escape') {
                $row.find('.pm-amount, .item-note').each(function() { $(this).val($(this).attr('data-original')); }).first().trigger('input');
            }
        });

        // ── Switching period ───────────────────────────────────────────
        $('#periodSelect').on('change', function() {
            if (Object.keys(drafts).length && !confirm('You have changes that are not saved. Switch period and leave them behind?')) {
                $(this).val(currentPeriod);
                return;
            }
            clearDrafts();
            renderUnsaved();
            currentPeriod = periodId();
            load();
        });

        window.addEventListener('beforeunload', function(event) {
            if (Object.keys(drafts).length) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        load();
    });
</script>
@endsection
