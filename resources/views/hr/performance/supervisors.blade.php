@extends('layouts.main')

@section('styles')
<style>
    /* Performance Review > Supervisors. Scoped to .ps-* so nothing else on the site changes. */
    .ps-hint { font-size: .8125rem; color: #6b7a8c; }
    #psTable thead th { background: #f6f8fb; color: #5b6b7c; font-size: .72rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; white-space: nowrap; }
    #psTable tbody td { vertical-align: middle; }
    #psTable select { min-width: 14rem; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header card-no-border py-3">
                <h5 class="mb-1">Supervisors</h5>
                <div class="ps-hint">A staff member's supervisor is someone who holds the position they report to. Where only one person holds it, that person is the supervisor automatically. Where several do, name which one. Anyone left unnamed is rated by HR. Your choices are kept for every later cycle.</div>
                <div class="d-flex flex-wrap gap-2 mt-3 align-items-center">
                    <select class="form-select form-select-sm" id="psPosition" aria-label="Position" style="max-width:16rem;"><option value="">All positions</option></select>
                    <input type="search" class="form-control form-control-sm" id="psSearch" placeholder="Search staff" aria-label="Search staff" style="max-width:14rem;">
                    <div class="form-check m-0"><input class="form-check-input" type="checkbox" id="psNeeds"><label class="form-check-label" for="psNeeds">Needs a name</label></div>
                    <div class="ms-auto d-flex flex-wrap gap-2 align-items-center" id="psBulk">
                        <span class="ps-hint" id="psSelected">None selected</span>
                        <select class="form-select form-select-sm" id="psBulkSupervisor" aria-label="Supervisor for the selected staff" style="min-width:14rem;" disabled><option value="">Choose a supervisor</option></select>
                        <button type="button" class="btn btn-primary btn-sm" id="psAssign" disabled>Assign</button>
                        <button type="button" class="btn btn-light btn-sm" id="psClear" disabled>Clear name</button>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle" id="psTable">
                        <thead><tr><th style="width:2rem;"><input type="checkbox" id="psAll" aria-label="Select everyone shown"></th><th>Staff member</th><th>Position</th><th>Reports to</th><th>Supervisor</th><th>Named by HR</th></tr></thead>
                        <tbody><tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr></tbody>
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

        function shown() {
            const position = $('#psPosition').val();
            const term = $.trim($('#psSearch').val()).toLowerCase();
            const needs = $('#psNeeds').is(':checked');
            return rows.filter(function(row) {
                return (!position || String(row.position_id) === position)
                    && (!term || row.name.toLowerCase().indexOf(term) !== -1)
                    && (!needs || needsName(row));
            });
        }

        function supervisorCell(row) {
            if (row.route === 'supervisor') {
                return '<span class="fw-semibold">' + esc(row.supervisor) + '</span> <span class="ps-hint">' + (row.reason === 'named' ? 'named by HR' : 'only holder') + '</span>';
            }
            return '<span class="text-muted">HR rates</span><div class="ps-hint">' + esc(row.reason_text) + '</div>';
        }

        function namedCell(row) {
            if (!row.candidates.length) return '<span class="text-muted">-</span>';
            return '<select class="form-select form-select-sm ps-named"><option value="">Not named</option>' + row.candidates.map(function(candidate) {
                return '<option value="' + candidate.id + '"' + (row.named_id === candidate.id ? ' selected' : '') + '>' + esc(candidate.name) + (candidate.has_login ? '' : ' (no login)') + '</option>';
            }).join('') + '</select>';
        }

        function draw() {
            const list = shown();
            if (!list.length) {
                $('#psTable tbody').html('<tr><td colspan="6" class="text-center text-muted py-4">' + (rows.length ? 'No staff match.' : 'No active staff report to a position yet.') + '</td></tr>');
            } else {
                $('#psTable tbody').html(list.map(function(row) {
                    return '<tr data-id="' + row.staff_id + '"><td><input type="checkbox" class="ps-pick"' + (selected[row.staff_id] ? ' checked' : '') + ' aria-label="Select ' + esc(row.name) + '"></td>'
                        + '<td class="fw-semibold">' + esc(row.name) + '</td><td>' + esc(row.position) + '</td><td>' + esc(row.reports_to) + '</td>'
                        + '<td>' + supervisorCell(row) + '</td><td>' + namedCell(row) + '</td></tr>';
                }).join(''));
            }
            bulk();
        }

        // The people who could supervise every selected staff member, and the buttons that need a selection
        function bulk() {
            const ids = Object.keys(selected).filter(function(id) { return selected[id]; });
            $('#psSelected').text(ids.length ? ids.length + ' selected' : 'None selected');
            let common = null;
            ids.forEach(function(id) {
                const row = rows.filter(function(candidate) { return String(candidate.staff_id) === id; })[0];
                const mine = (row ? row.candidates : []).map(function(candidate) { return candidate.id; });
                common = common === null ? mine : common.filter(function(candidate) { return mine.indexOf(candidate) !== -1; });
            });
            const options = ['<option value="">' + (ids.length && !(common && common.length) ? 'No one is common to all selected' : 'Choose a supervisor') + '</option>'];
            if (common && common.length) {
                const first = rows.filter(function(row) { return String(row.staff_id) === ids[0]; })[0];
                first.candidates.filter(function(candidate) { return common.indexOf(candidate.id) !== -1; }).forEach(function(candidate) {
                    options.push('<option value="' + candidate.id + '">' + esc(candidate.name) + (candidate.has_login ? '' : ' (no login)') + '</option>');
                });
            }
            $('#psBulkSupervisor').html(options.join('')).prop('disabled', !(common && common.length));
            $('#psAssign').prop('disabled', !(common && common.length));
            $('#psClear').prop('disabled', !ids.length);
        }

        function load() {
            $.getJSON(urls.list).done(function(res) {
                rows = res.rows;
                const seen = {};
                $('#psPosition').find('option:gt(0)').remove();
                rows.forEach(function(row) {
                    if (!seen[row.position_id]) { seen[row.position_id] = true; $('#psPosition').append($('<option>').val(row.position_id).text(row.position)); }
                });
                draw();
            }).fail(function(xhr) { toastr.error(message(xhr, 'The staff could not be loaded.')); });
        }

        function save(staffIds, supervisorId) {
            return api({ staff_ids: staffIds, supervisor_staff_id: supervisorId || '' }).done(function(res) {
                toastr.success(res.message);
                load();
            }).fail(function(xhr) { toastr.error(message(xhr, 'The supervisor could not be saved.')); load(); });
        }

        $('#psPosition, #psNeeds').on('change', draw);
        $('#psSearch').on('input', draw);
        $('#psAll').on('change', function() {
            const on = $(this).is(':checked');
            shown().forEach(function(row) { selected[row.staff_id] = on; });
            draw();
        });
        $(document).on('change', '.ps-pick', function() {
            selected[$(this).closest('tr').attr('data-id')] = $(this).is(':checked');
            bulk();
        });
        $(document).on('change', '.ps-named', function() {
            save([$(this).closest('tr').attr('data-id')], $(this).val());
        });
        $('#psAssign').on('click', function() {
            const ids = Object.keys(selected).filter(function(id) { return selected[id]; });
            if (!ids.length || !$('#psBulkSupervisor').val()) { toastr.info('Choose a supervisor first.'); return; }
            save(ids, $('#psBulkSupervisor').val());
        });
        $('#psClear').on('click', function() {
            const ids = Object.keys(selected).filter(function(id) { return selected[id]; });
            if (ids.length) save(ids, '');
        });

        load();
    });
</script>
@endsection
