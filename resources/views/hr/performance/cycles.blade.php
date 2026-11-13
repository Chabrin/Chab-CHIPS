@extends('layouts.main')

@section('styles')
<style>
    /* Performance Review > Review Cycles. Scoped to .pc-* so nothing else on the site changes. */
    .pc-hint { font-size: .8125rem; color: #6b7a8c; }
    #pcTable thead th { background: #f6f8fb; color: #5b6b7c; font-size: .72rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; white-space: nowrap; }
    #pcTable tbody td { vertical-align: middle; }
    .pc-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; padding: 0; }
    .pc-icon-btn .material-symbols-outlined { font-size: 18px; }
    #pcPreviewBody .table td, #pcPreviewBody .table th { font-size: .8125rem; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header card-no-border d-flex align-items-start flex-wrap gap-2 py-3">
                <div class="me-auto">
                    <h5 class="mb-0">Review Cycles</h5>
                    <div class="pc-hint mt-1">A cycle is one round of reviews for a period. Publishing it makes a review for every active staff member whose position has criteria set up under KPIs.</div>
                </div>
                <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" id="pcAdd">
                    <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span> New cycle
                </button>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle" id="pcTable">
                        <thead><tr><th>Cycle</th><th>Period</th><th>Deadline</th><th>Status</th><th class="text-end">Reviews</th><th class="text-end">Finalised</th><th></th></tr></thead>
                        <tbody><tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pcModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="pcForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="pcModalTitle">New cycle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pcId" value="">
                <div class="mb-3">
                    <label for="pcName" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="pcName" maxlength="150" placeholder="For example Q1 2027">
                    <div class="invalid-feedback" id="pcNameError"></div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label for="pcStart" class="form-label">Period starts <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="pcStart">
                        <div class="invalid-feedback" id="pcStartError"></div>
                    </div>
                    <div class="col-6">
                        <label for="pcEnd" class="form-label">Period ends <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="pcEnd">
                        <div class="invalid-feedback" id="pcEndError"></div>
                    </div>
                </div>
                <div class="pc-hint mb-3" style="margin-top:-.5rem;">The time being reviewed, for example October. Attendance, leave and the other records beside the ratings are counted for these dates.</div>
                <div class="row g-3">
                    <div class="col-6">
                        <label for="pcOpen" class="form-label">Opens</label>
                        <input type="date" class="form-control" id="pcOpen">
                        <div class="invalid-feedback" id="pcOpenError"></div>
                    </div>
                    <div class="col-6">
                        <label for="pcDeadline" class="form-label">Deadline</label>
                        <input type="date" class="form-control" id="pcDeadline">
                        <div class="invalid-feedback" id="pcDeadlineError"></div>
                    </div>
                </div>
                <div class="pc-hint mt-2">When people should do the reviews. The deadline is shown on each review and in the alert staff receive. Reviews open as soon as you publish, whatever the Opens date says, and nothing is locked after the deadline.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="pcSave">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="pcPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pcPreviewTitle">Publish</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="pcPreviewBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="pcPublish">Publish</button>
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
        const urls = { list: "{{ route('ajax.performance.cycles.list') }}", store: "{{ route('ajax.performance.cycles.store') }}", base: "{{ url('/ajax/hr/performance/cycles') }}" };
        const modal = new bootstrap.Modal(document.getElementById('pcModal'));
        const previewModal = new bootstrap.Modal(document.getElementById('pcPreviewModal'));
        const STATUS = { draft: ['Draft', 'secondary'], published: ['Published', 'success'], closed: ['Closed', 'dark'] };
        let cycles = [];
        let publishing = null;

        function message(xhr, fallback) {
            const json = xhr.responseJSON || {};
            if (json.errors) return Object.values(json.errors).flat().join(' ');
            return json.error || json.message || fallback;
        }

        function api(method, url, data) {
            return $.ajax({ url: url, type: method, data: data || {}, dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } });
        }

        function load() {
            $.getJSON(urls.list).done(function(res) {
                cycles = res.cycles;
                if (!cycles.length) {
                    $('#pcTable tbody').html('<tr><td colspan="7" class="text-center text-muted py-4">No cycles yet. Create one to start a round of reviews.</td></tr>');
                    return;
                }
                $('#pcTable tbody').html(cycles.map(function(cycle) {
                    const status = STATUS[cycle.status] || [cycle.status, 'secondary'];
                    let actions = '';
                    if (cycle.status === 'draft') {
                        actions = '<button type="button" class="btn btn-sm btn-primary pc-publish">Preview &amp; publish</button> '
                            + '<button type="button" class="btn btn-sm btn-outline-primary pc-icon-btn pc-edit" title="Edit" aria-label="Edit ' + esc(cycle.name) + '"><span class="material-symbols-outlined">edit</span></button> '
                            + '<button type="button" class="btn btn-sm btn-outline-danger pc-icon-btn pc-delete" title="Delete" aria-label="Delete ' + esc(cycle.name) + '"><span class="material-symbols-outlined">delete</span></button>';
                    } else if (cycle.status === 'published') {
                        actions = '<a class="btn btn-sm btn-outline-primary" href="{{ route('performance.all') }}">Reviews</a> '
                            + '<button type="button" class="btn btn-sm btn-outline-secondary pc-add-missing">Add missing staff</button> '
                            + '<button type="button" class="btn btn-sm btn-outline-danger pc-close">Close</button>';
                    } else {
                        actions = '<a class="btn btn-sm btn-outline-primary" href="{{ route('performance.all') }}">Reviews</a>';
                    }
                    return '<tr data-id="' + cycle.id + '"><td class="fw-semibold">' + esc(cycle.name) + '</td>'
                        + '<td class="text-nowrap">' + esc(cycle.period_start) + ' to ' + esc(cycle.period_end) + '</td>'
                        + '<td class="text-nowrap">' + (cycle.deadline ? esc(cycle.deadline) : '<span class="text-muted">-</span>') + '</td>'
                        + '<td><span class="badge bg-' + status[1] + '-subtle text-' + status[1] + '-emphasis border border-' + status[1] + '-subtle">' + status[0] + '</span></td>'
                        + '<td class="text-end">' + cycle.reviews + '</td><td class="text-end">' + cycle.finalised + '</td>'
                        + '<td class="text-end text-nowrap">' + actions + '</td></tr>';
                }).join(''));
            }).fail(function(xhr) { toastr.error(message(xhr, 'The cycles could not be loaded.')); });
        }

        function find(id) { return cycles.filter(function(cycle) { return String(cycle.id) === String(id); })[0]; }
        function rowCycle(element) { return find($(element).closest('tr').attr('data-id')); }

        function clearErrors() {
            $('#pcForm .form-control').removeClass('is-invalid');
            $('#pcForm .invalid-feedback').text('');
        }

        function openModal(cycle) {
            clearErrors();
            $('#pcModalTitle').text(cycle ? 'Edit ' + cycle.name : 'New cycle');
            $('#pcId').val(cycle ? cycle.id : '');
            $('#pcName').val(cycle ? cycle.name : '');
            $('#pcStart').val(cycle ? cycle.period_start : '');
            $('#pcEnd').val(cycle ? cycle.period_end : '');
            $('#pcOpen').val(cycle ? (cycle.open_date || '') : '');
            $('#pcDeadline').val(cycle ? (cycle.deadline || '') : '');
            modal.show();
        }

        $('#pcAdd').on('click', function() { openModal(null); });
        $(document).on('click', '.pc-edit', function() { openModal(rowCycle(this)); });

        $('#pcForm').on('submit', function(event) {
            event.preventDefault();
            clearErrors();
            const id = $('#pcId').val();
            $('#pcSave').prop('disabled', true);
            api('POST', id ? urls.base + '/' + id : urls.store, {
                name: $('#pcName').val(), period_start: $('#pcStart').val(), period_end: $('#pcEnd').val(),
                open_date: $('#pcOpen').val(), deadline: $('#pcDeadline').val()
            }).done(function(res) {
                modal.hide();
                toastr.success(res.message);
                load();
            }).fail(function(xhr) {
                const errors = (xhr.responseJSON && xhr.responseJSON.errors) || {};
                const fields = { name: 'Name', period_start: 'Start', period_end: 'End', open_date: 'Open', deadline: 'Deadline' };
                let shown = false;
                Object.keys(fields).forEach(function(field) {
                    if (!errors[field]) return;
                    $('#pc' + fields[field]).addClass('is-invalid');
                    $('#pc' + fields[field] + 'Error').text(errors[field][0]);
                    shown = true;
                });
                if (!shown) toastr.error(message(xhr, 'The cycle could not be saved.'));
            }).always(function() { $('#pcSave').prop('disabled', false); });
        });

        $(document).on('click', '.pc-delete', function() {
            const cycle = rowCycle(this);
            if (!cycle || !confirm('Delete the draft "' + cycle.name + '"?')) return;
            api('DELETE', urls.base + '/' + cycle.id).done(function(res) { toastr.success(res.message); load(); })
                .fail(function(xhr) { toastr.error(message(xhr, 'The cycle could not be deleted.')); });
        });

        $(document).on('click', '.pc-publish', function() {
            publishing = rowCycle(this);
            $('#pcPreviewTitle').text('Publish ' + publishing.name);
            $('#pcPreviewBody').html('<div class="text-muted">Working out who would get a review...</div>');
            $('#pcPublish').prop('disabled', true);
            previewModal.show();
            $.getJSON(urls.base + '/' + publishing.id + '/preview').done(function(res) {
                const counts = res.counts;
                let html = '<p><strong>' + counts.reviews + '</strong> ' + (counts.reviews === 1 ? 'review' : 'reviews') + ' would be made: '
                    + counts.with_supervisor + ' start with a supervisor after the staff member, and ' + counts.straight_to_hr + ' go straight to HR. '
                    + '<span class="text-muted">' + counts.left_out + ' active staff are left out.</span></p>';
                const warnings = res.include.filter(function(row) { return row.route === 'hr' && row.reason !== 'no_boss_position' && row.reason !== 'no_position'; });
                if (warnings.length) {
                    html += '<div class="alert alert-warning py-2">' + warnings.length + ' will go to HR because no supervisor could be used. '
                        + 'Name a supervisor on the Supervisors page before publishing, or HR will rate them.</div>';
                }
                html += '<h6>Who gets a review</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Staff member</th><th>Position</th><th>Goes to first</th><th>Why</th></tr></thead><tbody>'
                    + (res.include.map(function(row) {
                        return '<tr><td>' + esc(row.name) + (row.has_login ? '' : ' <span class="badge bg-secondary-subtle text-secondary-emphasis">no login</span>') + '</td><td>' + esc(row.position) + '</td>'
                            + '<td>' + (row.supervisor ? esc(row.supervisor) : 'HR') + '</td><td class="text-muted">' + esc(row.reason_text) + '</td></tr>';
                    }).join('') || '<tr><td colspan="4" class="text-muted">Nobody. Set up criteria for a position that has active staff under KPIs first.</td></tr>') + '</tbody></table></div>';
                if (res.exclude.length) {
                    html += '<h6 class="mt-3">Left out</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Staff member</th><th>Position</th><th>Why</th></tr></thead><tbody>'
                        + res.exclude.map(function(row) { return '<tr><td>' + esc(row.name) + '</td><td>' + esc(row.position) + '</td><td class="text-muted">' + esc(row.reason) + '</td></tr>'; }).join('') + '</tbody></table></div>';
                }
                $('#pcPreviewBody').html(html);
                $('#pcPublish').prop('disabled', counts.reviews === 0);
            }).fail(function(xhr) { $('#pcPreviewBody').html('<div class="text-danger">' + esc(message(xhr, 'The preview could not be made.')) + '</div>'); });
        });

        $('#pcPublish').on('click', function() {
            if (!publishing) return;
            $(this).prop('disabled', true);
            api('POST', urls.base + '/' + publishing.id + '/publish').done(function(res) {
                previewModal.hide();
                toastr.success(res.message);
                load();
            }).fail(function(xhr) {
                $('#pcPublish').prop('disabled', false);
                toastr.error(message(xhr, 'The cycle could not be published.'));
            });
        });

        $(document).on('click', '.pc-add-missing', function() {
            const cycle = rowCycle(this);
            api('POST', urls.base + '/' + cycle.id + '/add-missing').done(function(res) { toastr.success(res.message); load(); })
                .fail(function(xhr) { toastr.error(message(xhr, 'Reviews could not be added.')); });
        });

        $(document).on('click', '.pc-close', function() {
            const cycle = rowCycle(this);
            if (!confirm('Close "' + cycle.name + '"? Its reviews can still be read but nobody can change them.')) return;
            api('POST', urls.base + '/' + cycle.id + '/close').done(function(res) { toastr.success(res.message); load(); })
                .fail(function(xhr) { toastr.error(message(xhr, 'The cycle could not be closed.')); });
        });

        load();
    });
</script>
@endsection
