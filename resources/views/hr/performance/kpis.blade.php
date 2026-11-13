@extends('layouts.main')

@section('styles')
<style>
    /* Performance Review > KPIs. Scoped to .pk-* so nothing else on the site changes. */
    .pk-hint { font-size: .8125rem; color: #6b7a8c; }
    .pk-positions { max-height: 70vh; overflow-y: auto; }
    .pk-position { cursor: pointer; border-left: 3px solid transparent; }
    .pk-position.active { background: #eef4ff; border-left-color: #2f6fed; }
    .pk-position .pk-count { font-size: .72rem; }
    #pkTable thead th { background: #f6f8fb; color: #5b6b7c; font-size: .72rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; white-space: nowrap; }
    #pkTable tbody td { vertical-align: middle; }
    #pkTable tr.pk-off > td { background: #fafafa; color: #7a8794; }
    .pk-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; padding: 0; }
    .pk-icon-btn .material-symbols-outlined { font-size: 18px; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row g-3">
            <div class="col-12 col-lg-4 col-xl-3">
                <div class="card">
                    <div class="card-header card-no-border py-3">
                        <h5 class="mb-1">Positions</h5>
                        <div class="pk-hint">Pick a position to set what it is rated on.</div>
                        <input type="search" class="form-control form-control-sm mt-2" id="pkSearch" placeholder="Search positions" aria-label="Search positions">
                    </div>
                    <div class="list-group list-group-flush pk-positions" id="pkPositions"><div class="list-group-item text-muted">Loading...</div></div>
                </div>
            </div>
            <div class="col-12 col-lg-8 col-xl-9">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-start flex-wrap gap-2 py-3">
                        <div class="me-auto">
                            <h5 class="mb-0" id="pkTitle">KPIs</h5>
                            <div class="pk-hint mt-1" id="pkSubtitle">Choose a position on the left.</div>
                        </div>
                        <div class="d-flex gap-2" id="pkActions" style="display:none !important;">
                            <button type="button" class="btn btn-light btn-sm d-inline-flex align-items-center gap-1" id="pkCopy">
                                <span class="material-symbols-outlined" style="font-size:18px;">content_copy</span> Copy from another position
                            </button>
                            <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" id="pkAdd">
                                <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span> Add criterion
                            </button>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table align-middle" id="pkTable">
                                <thead><tr><th>Order</th><th>Criterion</th><th>Weight</th><th>In next cycle</th><th></th></tr></thead>
                                <tbody><tr><td colspan="5" class="text-center text-muted py-4">No position chosen.</td></tr></tbody>
                            </table>
                        </div>
                        <div class="pk-hint">Each criterion is rated 1 to 10 by the staff member, their supervisor and HR. The weight says how much it counts towards the score; leave every weight at 1 for a plain average. A cycle copies these when it is published, so changes here never alter reviews already made.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="pkForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="pkModalTitle">Add criterion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pkId" value="">
                <div class="mb-3">
                    <label for="pkName" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="pkName" maxlength="150" required placeholder="For example Attendance">
                    <div class="invalid-feedback" id="pkNameError"></div>
                </div>
                <div class="mb-3">
                    <label for="pkDescription" class="form-label">What it covers</label>
                    <textarea class="form-control" id="pkDescription" rows="2" maxlength="1000" placeholder="Shown to the person rating, so they know what to think about"></textarea>
                    <div class="invalid-feedback" id="pkDescriptionError"></div>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label for="pkWeight" class="form-label">Weight</label>
                        <input type="number" class="form-control" id="pkWeight" min="0.01" max="100" step="0.01" value="1">
                        <div class="invalid-feedback" id="pkWeightError"></div>
                        <div class="pk-hint mt-1">How much it counts towards the score. 1 is normal, 2 counts double. Leave every weight at 1 for a plain average.</div>
                    </div>
                    <div class="col-6">
                        <label for="pkOrder" class="form-label">Order</label>
                        <input type="number" class="form-control" id="pkOrder" min="0" max="65000" step="1" placeholder="Last">
                        <div class="invalid-feedback" id="pkOrderError"></div>
                        <div class="pk-hint mt-1">Where it appears on the review: smaller numbers first. Leave empty to put it last.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="pkSave">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="pkCopyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="pkCopyForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title">Copy criteria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="pkCopyFrom" class="form-label">Copy the active criteria of</label>
                <select class="form-select" id="pkCopyFrom"></select>
                <div class="pk-hint mt-2">They are added to this position. Any it already has by the same name are skipped.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Copy</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/performance-review.js') }}?v={{ filemtime(public_path('assets/js/performance-review.js')) }}"></script>
<script>
    $(function() {
        const esc = window.PerformanceReview.escapeHtml;
        const csrf = $('meta[name="csrf-token"]').attr('content');
        const urls = {
            positions: "{{ route('ajax.performance.positions') }}",
            list: "{{ route('ajax.performance.kpis.list') }}",
            store: "{{ route('ajax.performance.kpis.store') }}",
            copy: "{{ route('ajax.performance.kpis.copy') }}",
            base: "{{ url('/ajax/hr/performance/kpis') }}"
        };
        const modal = new bootstrap.Modal(document.getElementById('pkModal'));
        const copyModal = new bootstrap.Modal(document.getElementById('pkCopyModal'));
        let positions = [];
        let current = null;   // the position being edited
        let kpis = [];

        function message(xhr, fallback) {
            const json = xhr.responseJSON || {};
            if (json.errors) return Object.values(json.errors).flat().join(' ');
            return json.error || json.message || fallback;
        }

        function api(method, url, data) {
            return $.ajax({ url: url, type: method, data: data || {}, dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } });
        }

        function drawPositions() {
            const term = $.trim($('#pkSearch').val()).toLowerCase();
            const html = positions.filter(function(position) { return !term || position.name.toLowerCase().indexOf(term) !== -1; }).map(function(position) {
                return '<div class="list-group-item pk-position' + (current && current.id === position.id ? ' active' : '') + '" data-id="' + position.id + '">'
                    + '<div class="fw-semibold">' + esc(position.name) + '</div>'
                    + '<div class="pk-count text-muted">' + position.kpi_count + (position.kpi_count === 1 ? ' criterion' : ' criteria') + ' · ' + position.staff_count + ' active staff'
                    + (position.kpi_count === 0 && position.staff_count > 0 ? ' · <span class="text-danger">not set up</span>' : '') + '</div></div>';
            }).join('');
            $('#pkPositions').html(html || '<div class="list-group-item text-muted">No positions match.</div>');
        }

        function loadPositions(keep) {
            return $.getJSON(urls.positions).done(function(res) {
                positions = res.positions;
                drawPositions();
                if (keep && current) { current = positions.filter(function(position) { return position.id === current.id; })[0] || current; }
            }).fail(function(xhr) { toastr.error(message(xhr, 'The positions could not be loaded.')); });
        }

        function loadKpis() {
            if (!current) return;
            $.getJSON(urls.list, { position_id: current.id }).done(function(res) {
                kpis = res.kpis;
                $('#pkTitle').text(res.position.name);
                $('#pkSubtitle').html(res.position.reports_to
                    ? 'Rated by the holder of <strong>' + esc(res.position.reports_to) + '</strong> (the position it reports to), then HR.'
                    : 'This position has no "reports to", so HR does the rating after the staff member.');
                $('#pkActions').attr('style', '');
                if (!kpis.length) {
                    $('#pkTable tbody').html('<tr><td colspan="5" class="text-center text-muted py-4">No criteria yet. Staff in this position get no review until you add some.</td></tr>');
                    return;
                }
                $('#pkTable tbody').html(kpis.map(function(kpi) {
                    return '<tr data-id="' + kpi.id + '"' + (kpi.is_active ? '' : ' class="pk-off"') + '>'
                        + '<td>' + esc(kpi.sort_order) + '</td>'
                        + '<td><div class="fw-semibold">' + esc(kpi.name) + '</div>' + (kpi.description ? '<div class="pk-hint">' + esc(kpi.description) + '</div>' : '') + '</td>'
                        + '<td>' + esc(kpi.weight) + '</td>'
                        + '<td class="text-nowrap"><div class="form-check form-switch m-0 d-inline-block"><input class="form-check-input pk-toggle" type="checkbox" role="switch"' + (kpi.is_active ? ' checked' : '') + ' aria-label="Include ' + esc(kpi.name) + ' in the next cycle"></div> <span class="small ' + (kpi.is_active ? 'text-success' : 'text-muted') + '">' + (kpi.is_active ? 'On' : 'Off') + '</span></td>'
                        + '<td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary pk-icon-btn pk-edit" title="Edit" aria-label="Edit ' + esc(kpi.name) + '"><span class="material-symbols-outlined">edit</span></button> '
                        + '<button type="button" class="btn btn-sm btn-outline-danger pk-icon-btn pk-delete" title="Delete" aria-label="Delete ' + esc(kpi.name) + '"><span class="material-symbols-outlined">delete</span></button></td></tr>';
                }).join(''));
            }).fail(function(xhr) { toastr.error(message(xhr, 'The criteria could not be loaded.')); });
        }

        function find(id) { return kpis.filter(function(kpi) { return String(kpi.id) === String(id); })[0]; }

        function clearErrors() {
            $('#pkForm .form-control').removeClass('is-invalid');
            $('#pkForm .invalid-feedback').text('');
        }

        function openModal(kpi) {
            clearErrors();
            $('#pkModalTitle').text(kpi ? 'Edit ' + kpi.name : 'Add criterion to ' + current.name);
            $('#pkId').val(kpi ? kpi.id : '');
            $('#pkName').val(kpi ? kpi.name : '');
            $('#pkDescription').val(kpi ? (kpi.description || '') : '');
            $('#pkWeight').val(kpi ? kpi.weight : 1);
            $('#pkOrder').val(kpi ? kpi.sort_order : '');
            modal.show();
        }

        $('#pkPositions').on('click', '.pk-position', function() {
            const id = Number($(this).attr('data-id'));
            current = positions.filter(function(position) { return position.id === id; })[0];
            drawPositions();
            loadKpis();
        });
        $('#pkSearch').on('input', drawPositions);
        $('#pkAdd').on('click', function() { openModal(null); });
        $(document).on('click', '.pk-edit', function() { openModal(find($(this).closest('tr').attr('data-id'))); });

        $('#pkForm').on('submit', function(event) {
            event.preventDefault();
            clearErrors();
            const id = $('#pkId').val();
            const data = { name: $('#pkName').val(), description: $('#pkDescription').val(), weight: $('#pkWeight').val(), sort_order: $('#pkOrder').val() };
            if (!id) data.position_id = current.id;
            $('#pkSave').prop('disabled', true);
            api('POST', id ? urls.base + '/' + id : urls.store, data).done(function(res) {
                modal.hide();
                toastr.success(res.message);
                loadKpis();
                loadPositions(true);
            }).fail(function(xhr) {
                const errors = (xhr.responseJSON && xhr.responseJSON.errors) || {};
                const fields = { name: 'Name', description: 'Description', weight: 'Weight', sort_order: 'Order' };
                let shown = false;
                Object.keys(fields).forEach(function(field) {
                    if (!errors[field]) return;
                    const key = field === 'sort_order' ? 'Order' : fields[field];
                    $('#pk' + key).addClass('is-invalid');
                    $('#pk' + key + 'Error').text(errors[field][0]);
                    shown = true;
                });
                if (!shown) toastr.error(message(xhr, 'The criterion could not be saved.'));
            }).always(function() { $('#pkSave').prop('disabled', false); });
        });

        $(document).on('change', '.pk-toggle', function() {
            const $box = $(this);
            api('POST', urls.base + '/' + $box.closest('tr').attr('data-id') + '/toggle').done(function(res) {
                toastr.success(res.message);
                loadKpis();
            }).fail(function(xhr) {
                $box.prop('checked', !$box.is(':checked'));
                toastr.error(message(xhr, 'The criterion could not be changed.'));
            });
        });

        $(document).on('click', '.pk-delete', function() {
            const kpi = find($(this).closest('tr').attr('data-id'));
            if (!kpi || !confirm('Delete "' + kpi.name + '"? This cannot be undone.')) return;
            api('DELETE', urls.base + '/' + kpi.id).done(function(res) {
                toastr.success(res.message);
                loadKpis();
                loadPositions(true);
            }).fail(function(xhr) { toastr.error(message(xhr, 'The criterion could not be deleted.')); });
        });

        $('#pkCopy').on('click', function() {
            const options = positions.filter(function(position) { return current && position.id !== current.id && position.kpi_count > 0; }).map(function(position) {
                return '<option value="' + position.id + '">' + esc(position.name) + ' (' + position.kpi_count + ')</option>';
            });
            if (!options.length) { toastr.info('No other position has criteria to copy yet.'); return; }
            $('#pkCopyFrom').html(options.join(''));
            copyModal.show();
        });

        $('#pkCopyForm').on('submit', function(event) {
            event.preventDefault();
            api('POST', urls.copy, { from_position_id: $('#pkCopyFrom').val(), to_position_id: current.id }).done(function(res) {
                copyModal.hide();
                toastr.success(res.message);
                loadKpis();
                loadPositions(true);
            }).fail(function(xhr) { toastr.error(message(xhr, 'The criteria could not be copied.')); });
        });

        loadPositions();
    });
</script>
@endsection
