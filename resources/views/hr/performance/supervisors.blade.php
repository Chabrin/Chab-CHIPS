@extends('layouts.main')

@section('styles')
<style>
    /* Performance Review > Supervisors. Scoped to .ps-* / #ps* so nothing else on the site changes. */
    .ps-hint { font-size: .8125rem; color: #6b7a8c; }
    .ps-intro { max-width: 46rem; }

    /* Summary / filter chips */
    .ps-chips { display: flex; flex-wrap: wrap; gap: .5rem; }
    .ps-chip { display: inline-flex; align-items: center; gap: .5rem; padding: .35rem .75rem; border: 1px solid #dde3ea; border-radius: 999px; background: #fff; color: #3b4a5a; font-size: .8125rem; cursor: pointer; transition: border-color .15s, background .15s; }
    .ps-chip:hover { border-color: #b7c3d0; }
    .ps-chip:focus-visible { outline: 2px solid #4a7cf0; outline-offset: 2px; }
    .ps-chip .ps-count { font-weight: 600; font-variant-numeric: tabular-nums; }
    .ps-chip.is-active { background: #1f2d3d; border-color: #1f2d3d; color: #fff; }
    .ps-chip.is-warn:not(.is-active) .ps-count { color: #b4570b; }
    .ps-chip.is-warn:not(.is-active) { border-color: #f0cf9f; background: #fffaf2; }

    /* Toolbar */
    .ps-toolbar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .ps-toolbar .form-control, .ps-toolbar .form-select { max-width: 16rem; }
    .ps-shown { margin-left: auto; }

    /* Bulk bar: only visible once someone is ticked */
    #psBulk { display: none; align-items: center; flex-wrap: wrap; gap: .5rem; padding: .625rem 1rem; margin: 0 1.25rem .75rem; border: 1px solid #c9d8fb; background: #f2f6ff; border-radius: .5rem; }
    #psBulk.is-on { display: flex; }
    #psBulk .ps-selected { font-weight: 600; color: #1f2d3d; margin-right: .5rem; }
    #psBulk select { min-width: 14rem; max-width: 18rem; }
    #psBulk .ps-bulk-note { flex-basis: 100%; }

    /* Table */
    #psTable thead th { background: #f6f8fb; color: #5b6b7c; font-size: .75rem; font-weight: 600; white-space: nowrap; position: sticky; top: 0; z-index: 1; }
    #psTable tbody td { vertical-align: middle; }
    #psTable tbody tr:hover { background: #fafbfd; }
    #psTable tbody tr.is-picked { background: #f2f6ff; }
    #psTable tbody tr.is-saving { opacity: .55; pointer-events: none; }
    #psTable select { min-width: 13rem; }
    .ps-scroll { max-height: 68vh; overflow: auto; }

    /* Staff cell */
    .ps-person { display: flex; align-items: center; gap: .625rem; }
    .ps-avatar { flex: 0 0 auto; width: 2rem; height: 2rem; border-radius: 50%; background: #e7edf6; color: #405775; display: inline-flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 600; }
    .ps-person-name { font-weight: 600; line-height: 1.2; }
    .ps-person-sub { font-size: .75rem; color: #6b7a8c; }

    /* Status */
    .ps-badge { display: inline-block; padding: .125rem .5rem; border-radius: .25rem; font-size: .7rem; font-weight: 600; line-height: 1.5; }
    .ps-badge.named { background: #e3f6ec; color: #17794a; }
    .ps-badge.auto  { background: #e6effd; color: #2a5bc0; }
    .ps-badge.needs { background: #fdecd3; color: #a3510a; }
    .ps-badge.hr    { background: #eceff3; color: #526171; }

    .ps-empty { padding: 3rem 1rem; text-align: center; color: #6b7a8c; }
    .ps-empty strong { display: block; color: #1f2d3d; margin-bottom: .25rem; }

    .ps-skel { height: .75rem; border-radius: .25rem; background: linear-gradient(90deg, #eef1f5, #f7f9fb, #eef1f5); background-size: 200% 100%; animation: ps-shimmer 1.2s linear infinite; }
    @keyframes ps-shimmer { to { background-position: -200% 0; } }
    @media (prefers-reduced-motion: reduce) { .ps-skel { animation: none; } .ps-chip { transition: none; } }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header card-no-border pb-2">
                <h5 class="mb-1">Supervisors</h5>
                <p class="ps-hint ps-intro mb-3">
                    Each staff member is rated by someone who holds the position they report to. If only one person holds it, they are the supervisor automatically. If several do, choose one. Anyone left unnamed is rated by HR. Your choices carry over to every later cycle.
                </p>

                <div class="ps-chips mb-3" id="psChips" role="group" aria-label="Filter by status">
                    <button type="button" class="ps-chip is-active" data-filter="all">All staff <span class="ps-count" data-count="all">0</span></button>
                    <button type="button" class="ps-chip is-warn" data-filter="needs">Needs a name <span class="ps-count" data-count="needs">0</span></button>
                    <button type="button" class="ps-chip" data-filter="named">Named by HR <span class="ps-count" data-count="named">0</span></button>
                    <button type="button" class="ps-chip" data-filter="auto">Only holder <span class="ps-count" data-count="auto">0</span></button>
                    <button type="button" class="ps-chip" data-filter="hr">Rated by HR <span class="ps-count" data-count="hr">0</span></button>
                </div>

                <div class="ps-toolbar">
                    <select class="form-select form-select-sm" id="psPosition" aria-label="Position"><option value="">All positions</option></select>
                    <input type="search" class="form-control form-control-sm" id="psSearch" placeholder="Search by name" aria-label="Search staff">
                    <span class="ps-hint ps-shown" id="psShown" aria-live="polite"></span>
                </div>
            </div>

            <div id="psBulk" role="region" aria-label="Actions for selected staff">
                <span class="ps-selected" id="psSelected">0 selected</span>
                <select class="form-select form-select-sm" id="psBulkSupervisor" aria-label="Supervisor for the selected staff" disabled><option value="">Choose a supervisor</option></select>
                <button type="button" class="btn btn-primary btn-sm" id="psAssign" disabled>Assign supervisor</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="psClear" disabled>Remove name</button>
                <button type="button" class="btn btn-link btn-sm ms-auto" id="psDeselect">Deselect all</button>
                <span class="ps-hint ps-bulk-note" id="psBulkNote"></span>
            </div>

            <div class="card-body pt-0">
                <div class="table-responsive ps-scroll">
                    <table class="table align-middle mb-0" id="psTable">
                        <thead>
                            <tr>
                                <th style="width:2.5rem;"><input class="form-check-input" type="checkbox" id="psAll" aria-label="Select everyone shown"></th>
                                <th>Staff member</th>
                                <th>Reports to</th>
                                <th>Supervisor</th>
                                <th>Choose supervisor</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/performance-review.js') }}?v={{ filemtime(public_path('assets/js/performance-review.js')) }}"></script>
<script>
    $(function() {
        const esc = window.PerformanceReview.escapeHtml;
        const csrf = $('meta[name="csrf-token"]').attr('content');
        const urls = { list: "{{ route('ajax.performance.supervisors.list') }}", assign: "{{ route('ajax.performance.supervisors.assign') }}" };
        let rows = [];
        let filter = 'all';
        let searchTimer = null;
        const selected = {};

        function message(xhr, fallback) {
            const json = xhr.responseJSON || {};
            if (json.errors) return Object.values(json.errors).flat().join(' ');
            return json.error || json.message || fallback;
        }

        function api(data) {
            return $.ajax({ url: urls.assign, type: 'POST', data: data, dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } });
        }

        function needsName(row) { return row.reason === 'not_named' || row.reason === 'assignment_invalid'; }

        // One status per row, used for the chips, the filter and the badge
        function status(row) {
            if (needsName(row)) return 'needs';
            if (row.route === 'supervisor') return row.reason === 'named' ? 'named' : 'auto';
            return 'hr';
        }

        function initials(name) {
            const parts = $.trim(name).split(/\s+/);
            return ((parts[0] || '').charAt(0) + (parts.length > 1 ? parts[parts.length - 1].charAt(0) : '')).toUpperCase();
        }

        function selectedIds() { return Object.keys(selected).filter(function(id) { return selected[id]; }); }
        function rowById(id) { return rows.filter(function(row) { return String(row.staff_id) === String(id); })[0]; }

        function shown() {
            const position = $('#psPosition').val();
            const term = $.trim($('#psSearch').val()).toLowerCase();
            return rows.filter(function(row) {
                return (!position || String(row.position_id) === position)
                    && (!term || row.name.toLowerCase().indexOf(term) !== -1)
                    && (filter === 'all' || status(row) === filter);
            });
        }

        function counts() {
            const total = { all: rows.length, needs: 0, named: 0, auto: 0, hr: 0 };
            rows.forEach(function(row) { total[status(row)]++; });
            Object.keys(total).forEach(function(key) { $('[data-count="' + key + '"]').text(total[key]); });
        }

        function supervisorCell(row) {
            const state = status(row);
            if (state === 'named' || state === 'auto') {
                return '<div class="fw-semibold">' + esc(row.supervisor) + '</div>'
                    + '<span class="ps-badge ' + state + '">' + (state === 'named' ? 'Named by HR' : 'Only holder') + '</span>';
            }
            return '<span class="ps-badge ' + state + '">' + (state === 'needs' ? 'Needs a name' : 'Rated by HR') + '</span>'
                + '<div class="ps-hint mt-1">' + esc(row.reason_text) + '</div>';
        }

        function chooseCell(row) {
            if (!row.candidates.length) return '<span class="text-muted" title="Nobody holds this position">-</span>';
            const auto = row.candidates.length === 1 ? 'Use the only holder' : 'Not named';
            return '<select class="form-select form-select-sm ps-named" aria-label="Supervisor for ' + esc(row.name) + '"><option value="">' + auto + '</option>'
                + row.candidates.map(function(candidate) {
                    return '<option value="' + candidate.id + '"' + (row.named_id === candidate.id ? ' selected' : '') + '>'
                        + esc(candidate.name) + (candidate.has_login ? '' : ' (no login)') + '</option>';
                }).join('') + '</select>';
        }

        function skeleton() {
            let html = '';
            for (let i = 0; i < 6; i++) {
                html += '<tr><td></td><td><div class="ps-skel" style="width:60%"></div></td><td><div class="ps-skel" style="width:50%"></div></td><td><div class="ps-skel" style="width:55%"></div></td><td><div class="ps-skel" style="width:70%"></div></td></tr>';
            }
            $('#psTable tbody').html(html);
        }

        function draw() {
            const list = shown();
            $('#psShown').text(rows.length ? 'Showing ' + list.length + ' of ' + rows.length : '');
            if (!list.length) {
                const none = rows.length
                    ? '<strong>No staff match</strong>Try a different position, search or status.'
                    : '<strong>No active staff report to a position yet</strong>Assign positions to staff first, then name their supervisors here.';
                $('#psTable tbody').html('<tr><td colspan="5"><div class="ps-empty">' + none + '</div></td></tr>');
            } else {
                $('#psTable tbody').html(list.map(function(row) {
                    const picked = !!selected[row.staff_id];
                    return '<tr data-id="' + row.staff_id + '"' + (picked ? ' class="is-picked"' : '') + '>'
                        + '<td><input class="form-check-input ps-pick" type="checkbox"' + (picked ? ' checked' : '') + ' aria-label="Select ' + esc(row.name) + '"></td>'
                        + '<td><div class="ps-person"><span class="ps-avatar" aria-hidden="true">' + esc(initials(row.name)) + '</span>'
                        + '<div><div class="ps-person-name">' + esc(row.name) + '</div><div class="ps-person-sub">' + esc(row.position) + '</div></div></div></td>'
                        + '<td>' + esc(row.reports_to) + '</td>'
                        + '<td>' + supervisorCell(row) + '</td>'
                        + '<td>' + chooseCell(row) + '</td></tr>';
                }).join(''));
            }
            counts();
            bulk();
        }

        // Bulk bar: shows only with a selection; the dropdown lists people who could supervise everyone selected
        function bulk() {
            const ids = selectedIds().filter(function(id) { return rowById(id); });
            ids.forEach(function(id) { /* keep only ids that still exist */ });
            $('#psBulk').toggleClass('is-on', ids.length > 0);
            $('#psSelected').text(ids.length + ' selected');

            let common = null;
            ids.forEach(function(id) {
                const mine = rowById(id).candidates.map(function(candidate) { return candidate.id; });
                common = common === null ? mine : common.filter(function(candidate) { return mine.indexOf(candidate) !== -1; });
            });
            const hasCommon = !!(common && common.length);

            const options = ['<option value="">Choose a supervisor</option>'];
            if (hasCommon) {
                rowById(ids[0]).candidates.filter(function(candidate) { return common.indexOf(candidate.id) !== -1; }).forEach(function(candidate) {
                    options.push('<option value="' + candidate.id + '">' + esc(candidate.name) + (candidate.has_login ? '' : ' (no login)') + '</option>');
                });
            }
            $('#psBulkSupervisor').html(options.join('')).prop('disabled', !hasCommon);
            $('#psAssign').prop('disabled', !hasCommon);
            $('#psClear').prop('disabled', !ids.length);
            $('#psBulkNote').text(ids.length && !hasCommon ? 'No one can supervise all of the selected staff. Pick staff in the same position, or name each one separately.' : '');

            // Select-all box reflects what is shown
            const list = shown();
            const pickedShown = list.filter(function(row) { return selected[row.staff_id]; }).length;
            const all = $('#psAll');
            all.prop('checked', list.length > 0 && pickedShown === list.length);
            all.prop('indeterminate', pickedShown > 0 && pickedShown < list.length);
        }

        function load() {
            skeleton();
            $.getJSON(urls.list).done(function(res) {
                rows = res.rows;
                const seen = {};
                const current = $('#psPosition').val();
                $('#psPosition').find('option:gt(0)').remove();
                rows.forEach(function(row) {
                    if (!seen[row.position_id]) { seen[row.position_id] = true; $('#psPosition').append($('<option>').val(row.position_id).text(row.position)); }
                });
                $('#psPosition').val(current);
                if ($('#psPosition').val() === null) $('#psPosition').val('');
                draw();
            }).fail(function(xhr) {
                $('#psTable tbody').html('<tr><td colspan="5"><div class="ps-empty"><strong>The staff could not be loaded</strong>Check your connection and refresh the page.</div></td></tr>');
                toastr.error(message(xhr, 'The staff could not be loaded.'));
            });
        }

        function save(staffIds, supervisorId, clearSelection) {
            staffIds.forEach(function(id) { $('#psTable tbody tr[data-id="' + id + '"]').addClass('is-saving'); });
            return api({ staff_ids: staffIds, supervisor_staff_id: supervisorId || '' }).done(function(res) {
                toastr.success(res.message);
                if (clearSelection) { staffIds.forEach(function(id) { delete selected[id]; }); }
                load();
            }).fail(function(xhr) { toastr.error(message(xhr, 'The supervisor could not be saved.')); load(); });
        }

        $('#psChips').on('click', '.ps-chip', function() {
            filter = $(this).attr('data-filter');
            $('#psChips .ps-chip').removeClass('is-active');
            $(this).addClass('is-active');
            draw();
        });
        $('#psPosition').on('change', draw);
        $('#psSearch').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(draw, 150);
        });
        $('#psAll').on('change', function() {
            const on = $(this).is(':checked');
            shown().forEach(function(row) { selected[row.staff_id] = on; });
            draw();
        });
        $(document).on('change', '.ps-pick', function() {
            const tr = $(this).closest('tr');
            selected[tr.attr('data-id')] = $(this).is(':checked');
            tr.toggleClass('is-picked', $(this).is(':checked'));
            bulk();
        });
        $(document).on('change', '.ps-named', function() {
            save([$(this).closest('tr').attr('data-id')], $(this).val(), false);
        });
        $('#psAssign').on('click', function() {
            const ids = selectedIds();
            if (!ids.length || !$('#psBulkSupervisor').val()) { toastr.info('Choose a supervisor first.'); return; }
            save(ids, $('#psBulkSupervisor').val(), true);
        });
        $('#psClear').on('click', function() {
            const ids = selectedIds();
            if (ids.length) save(ids, '', true);
        });
        $('#psDeselect').on('click', function() {
            Object.keys(selected).forEach(function(id) { delete selected[id]; });
            draw();
        });

        load();
    });
</script>
@endsection