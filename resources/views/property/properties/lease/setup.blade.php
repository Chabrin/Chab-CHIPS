@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Lease Setup</h5>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('property.leases.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Leases</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info py-2 px-3">
                            These leases came in from a data migration and are still Pending — missing an SLA, an end
                            date, or with a tenant that doesn't match who the unit currently says is occupying it.
                            Clear each item below, then activate the lease.
                        </div>

                        <style>
                            .ps-card {
                                background: #fff;
                                border: none;
                                border-bottom: 1px solid #e9edf1;
                                padding: 7px 10px;
                                display: flex;
                                align-items: center;
                                gap: 8px;
                                height: 100%;
                            }
                            .ps-icon {
                                width: 26px;
                                height: 26px;
                                border-radius: 6px;
                                background: var(--ps-icon-bg, #d9ebf7);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                flex-shrink: 0;
                            }
                            .ps-icon .material-symbols-outlined {
                                font-size: 14px !important;
                                color: var(--ps-accent, #0a0a0a) !important;
                            }
                            .ps-body {
                                flex: 1;
                                min-width: 0;
                                display: flex;
                                align-items: baseline;
                                gap: 6px;
                                flex-wrap: wrap;
                            }
                            .ps-value {
                                font-size: 16px;
                                font-weight: 700;
                                color: #0a0a0a;
                                line-height: 1;
                            }
                            .ps-value .spinner-border {
                                width: 0.8rem;
                                height: 0.8rem;
                            }
                            .ps-label {
                                font-size: 10px;
                                font-weight: 600;
                                color: #6b7280;
                                text-transform: uppercase;
                                letter-spacing: 0.03em;
                                white-space: nowrap;
                            }
                            .ps-card.ps-clickable {
                                cursor: pointer;
                                transition: box-shadow .15s ease;
                            }
                            .ps-card.ps-clickable:hover {
                                box-shadow: 0 2px 8px rgba(53, 152, 219, 0.18);
                            }
                            .setup-pills {
                                background: #f3f7fb;
                                border-radius: 8px;
                                padding: 4px;
                                display: inline-flex;
                                flex-wrap: wrap;
                                gap: 4px;
                            }
                            .setup-pills .nav-link {
                                display: flex;
                                align-items: center;
                                gap: 6px;
                                font-size: 12.5px;
                                font-weight: 500;
                                color: #52667a;
                                padding: 6px 12px;
                                border-radius: 6px;
                            }
                            .setup-pills .nav-link.active {
                                background: var(--theme-default);
                                color: #fff;
                            }
                            .end-date-input {
                                width: 150px;
                                display: inline-block;
                            }
                        </style>

                        <!-- Stats -->
                        <div class="row row-cols-2 row-cols-lg-4 g-2 mb-3">
                            <div class="col">
                                <div class="ps-card ps-clickable" style="--ps-accent:#d03b3b; --ps-icon-bg:#fbe1e1;" onclick="$('#missing-sla-tab').tab('show')">
                                    <div class="ps-icon"><span class="material-symbols-outlined">assignment_late</span></div>
                                    <div class="ps-body">
                                        <span class="ps-value" id="stat-missing-sla"><span class="spinner-border spinner-border-sm"></span></span>
                                        <span class="ps-label">Missing SLA</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="ps-card ps-clickable" style="--ps-accent:#8ab4cc; --ps-icon-bg:#e6f0fa;" onclick="$('#missing-end-date-tab').tab('show')">
                                    <div class="ps-icon"><span class="material-symbols-outlined">event_busy</span></div>
                                    <div class="ps-body">
                                        <span class="ps-value" id="stat-missing-end-date"><span class="spinner-border spinner-border-sm"></span></span>
                                        <span class="ps-label">Missing End Date</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="ps-card ps-clickable" style="--ps-accent:#fab219; --ps-icon-bg:#fef3da;" onclick="$('#tenant-mismatch-tab').tab('show')">
                                    <div class="ps-icon"><span class="material-symbols-outlined">person_alert</span></div>
                                    <div class="ps-body">
                                        <span class="ps-value" id="stat-tenant-mismatch"><span class="spinner-border spinner-border-sm"></span></span>
                                        <span class="ps-label">Tenant Mismatch</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="ps-card ps-clickable" style="--ps-accent:#0ca30c; --ps-icon-bg:#dcf3dc;" onclick="$('#ready-tab').tab('show')">
                                    <div class="ps-icon"><span class="material-symbols-outlined">check_circle</span></div>
                                    <div class="ps-body">
                                        <span class="ps-value" id="stat-ready"><span class="spinner-border spinner-border-sm"></span></span>
                                        <span class="ps-label">Ready to Activate</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Nav tabs -->
                        <ul class="nav setup-pills mb-3" id="setupTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button">All Needing Setup</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="missing-sla-tab" data-bs-toggle="tab" data-bs-target="#missing-sla" type="button">Missing SLA</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="missing-end-date-tab" data-bs-toggle="tab" data-bs-target="#missing-end-date" type="button">Missing End Date</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tenant-mismatch-tab" data-bs-toggle="tab" data-bs-target="#tenant-mismatch" type="button">Tenant Mismatch</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="ready-tab" data-bs-toggle="tab" data-bs-target="#ready" type="button">Ready to Activate</button>
                            </li>
                        </ul>

                        <div class="tab-content" id="setupTabContent">
                            <div class="tab-pane fade show active" id="all" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="allTable" class="display setup-table" style="width:100%;"></table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="missing-sla" role="tabpanel">
                                <div class="mb-3">
                                    <button type="button" class="btn btn-primary btn-sm" id="bulkFillSlaBtn">
                                        <span class="spinner-border spinner-border-sm me-1 d-none"></span>
                                        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">auto_fix_high</span>
                                        Auto-fill SLA for all
                                    </button>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="missingSlaTable" class="display setup-table" style="width:100%;"></table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="missing-end-date" role="tabpanel">
                                <div class="mb-3">
                                    <button type="button" class="btn btn-primary btn-sm" id="bulkComputeEndDateBtn">
                                        <span class="spinner-border spinner-border-sm me-1 d-none"></span>
                                        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">calculate</span>
                                        Compute from Start + Years for all
                                    </button>
                                    <small class="text-muted ms-2">Leases with no start date, or no lease term (years), are left for manual entry below.</small>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="missingEndDateTable" class="display setup-table" style="width:100%;"></table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tenant-mismatch" role="tabpanel">
                                <div class="alert alert-light-warning py-2 px-3 mb-3">
                                    Each of these leases names a different tenant than the one currently assigned to that
                                    unit. Review each one — the lease could be for a tenant who has since moved out, or the
                                    unit's own tenant record could be the stale one.
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="tenantMismatchTable" class="display setup-table" style="width:100%;"></table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="ready" role="tabpanel">
                                <div class="mb-3">
                                    <button type="button" class="btn btn-success btn-sm" id="bulkActivateBtn">
                                        <span class="spinner-border spinner-border-sm me-1 d-none"></span>
                                        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">check_circle</span>
                                        Activate all
                                    </button>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="readyTable" class="display setup-table" style="width:100%;"></table>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resolve Tenant Modal -->
<div class="modal fade" id="resolveTenantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">person_alert</span>
                    Resolve Tenant
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Lease <strong id="resolveLeaseRef"></strong> — which tenant is correct for this unit?</p>

                <div class="d-grid gap-2 mb-3">
                    <button type="button" class="btn btn-outline-primary text-start" id="keepLeaseTenantBtn">
                        <div class="fw-semibold">Keep: <span id="resolveLeaseTenantName"></span></div>
                        <small class="text-muted">The lease's own tenant is correct</small>
                    </button>
                    <button type="button" class="btn btn-outline-primary text-start" id="useUnitTenantBtn">
                        <div class="fw-semibold">Use: <span id="resolveUnitTenantName"></span></div>
                        <small class="text-muted">The unit's currently-assigned tenant is correct</small>
                    </button>
                </div>

                <label for="resolveTenantSearch" class="form-label">Or search for a different tenant</label>
                <select class="form-select" id="resolveTenantSearch" style="width:100%;"></select>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function () {
        var leaseIdBeingResolved = null;

        function fmtSlaCell(row) {
            return row.sla_id
                ? '<span class="badge bg-success-light text-success fw-normal">Set</span>'
                : '<span class="badge bg-danger-light text-danger fw-normal">Missing</span>';
        }

        function fmtTenantCell(row) {
            var html = '<div>' + row.tenant_name + '</div>';
            if (row.has_mismatch) {
                html += '<button type="button" class="btn btn-link btn-sm p-0 resolve-tenant-btn" ' +
                    'data-id="' + row.id + '" data-ref="' + row.ref_number + '" ' +
                    'data-lease-tenant-id="' + row.tenant_id + '" data-lease-tenant-name="' + row.tenant_name + '" ' +
                    'data-unit-tenant-id="' + row.unit_tenant_id + '" data-unit-tenant-name="' + row.unit_tenant_name + '">' +
                    '<span class="material-symbols-outlined text-danger" style="font-size:14px;vertical-align:middle;">warning</span> ' +
                    'vs ' + row.unit_tenant_name + ' — Resolve' +
                    '</button>';
            }
            return html;
        }

        function fmtEndDateCell(row) {
            if (row.date_end) {
                return row.date_end;
            }
            var suggested = row.suggested_end_date || '';
            return '<div class="d-flex align-items-center gap-1">' +
                '<input type="date" class="form-control form-control-sm end-date-input" value="' + suggested + '">' +
                '<button type="button" class="btn btn-outline-primary btn-sm save-end-date-btn" data-id="' + row.id + '">Save</button>' +
                '</div>';
        }

        function fmtActionsCell(row) {
            var eligible = row.sla_id && row.date_end;
            if (eligible) {
                return '<button type="button" class="btn btn-success btn-sm activate-btn" data-id="' + row.id + '">Activate</button>';
            }
            return '<button type="button" class="btn btn-light btn-sm" disabled title="Needs SLA and end date first">Activate</button>';
        }

        function columns() {
            return [
                { data: null, orderable: false, searchable: false, render: (d, t, r, m) => m.row + 1 },
                { data: 'ref_number' },
                { data: 'property_name' },
                { data: 'unit_code' },
                { data: null, orderable: false, render: (d, t, r) => fmtTenantCell(r) },
                { data: null, orderable: false, render: (d, t, r) => fmtSlaCell(r) },
                { data: null, orderable: false, render: (d, t, r) => fmtEndDateCell(r) },
                { data: null, orderable: false, searchable: false, render: (d, t, r) => fmtActionsCell(r) },
            ];
        }

        function headerRow() {
            return '<tr><th>#</th><th>Ref</th><th>Property</th><th>Unit</th><th>Tenant</th><th>SLA</th><th>End Date</th><th>Action</th></tr>';
        }

        var tables = {};
        var tableConfig = [
            { id: 'allTable', filter: 'all' },
            { id: 'missingSlaTable', filter: 'missing_sla' },
            { id: 'missingEndDateTable', filter: 'missing_end_date' },
            { id: 'tenantMismatchTable', filter: 'tenant_mismatch' },
            { id: 'readyTable', filter: 'ready' },
        ];

        tableConfig.forEach(function (cfg) {
            $('#' + cfg.id).html('<thead>' + headerRow() + '</thead><tbody></tbody>');
            tables[cfg.filter] = $('#' + cfg.id).DataTable({
                data: [],
                columns: columns(),
                pageLength: 25,
                responsive: true,
                language: { emptyTable: 'Nothing here.' }
            });
        });

        function loadTable(filter) {
            $.ajax({
                url: '{{ route("property.leases.setup.data") }}',
                type: 'GET',
                data: { filter: filter },
                success: function (response) {
                    tables[filter].clear();
                    if (response.data && response.data.length) {
                        tables[filter].rows.add(response.data).draw();
                    } else {
                        tables[filter].draw();
                    }
                },
                error: function () {
                    toastr.error('Failed to load leases for this tab.');
                }
            });
        }

        function loadStats() {
            $.ajax({
                url: '{{ route("property.leases.setup.stats") }}',
                type: 'GET',
                success: function (response) {
                    $('#stat-missing-sla').text(response.missing_sla);
                    $('#stat-missing-end-date').text(response.missing_end_date);
                    $('#stat-tenant-mismatch').text(response.tenant_mismatch);
                    $('#stat-ready').text(response.ready);
                    $('#bulkFillSlaBtn').html($('#bulkFillSlaBtn').html().replace(/for all.*/, 'for all ' + response.missing_sla));
                }
            });
        }

        var loadedFilters = {};
        function ensureLoaded(filter) {
            if (!loadedFilters[filter]) {
                loadTable(filter);
                loadedFilters[filter] = true;
            }
        }

        function reloadAllLoadedTables() {
            Object.keys(loadedFilters).forEach(function (f) {
                if (loadedFilters[f]) loadTable(f);
            });
            loadStats();
        }

        // Initial load
        loadStats();
        ensureLoaded('all');

        $('#setupTab button').on('shown.bs.tab', function (e) {
            var target = $(e.target).data('bs-target');
            var map = { '#all': 'all', '#missing-sla': 'missing_sla', '#missing-end-date': 'missing_end_date', '#tenant-mismatch': 'tenant_mismatch', '#ready': 'ready' };
            ensureLoaded(map[target]);
        });

        // Bulk: fill SLA
        $('#bulkFillSlaBtn').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');
            $.ajax({
                url: '{{ route("property.leases.setup.bulk-fill-sla") }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function (response) {
                    toastr.success('SLA filled on ' + response.updated + ' lease(s).' + (response.skipped ? ' ' + response.skipped + ' could not be resolved.' : ''));
                    reloadAllLoadedTables();
                },
                error: function () {
                    toastr.error('Failed to auto-fill SLA.');
                },
                complete: function () {
                    $btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
                }
            });
        });

        // Bulk: compute end date
        $('#bulkComputeEndDateBtn').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');
            $.ajax({
                url: '{{ route("property.leases.setup.bulk-compute-end-date") }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function (response) {
                    toastr.success('End date computed for ' + response.updated + ' lease(s).' + (response.skipped ? ' ' + response.skipped + ' need one picked manually.' : ''));
                    reloadAllLoadedTables();
                },
                error: function () {
                    toastr.error('Failed to compute end dates.');
                },
                complete: function () {
                    $btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
                }
            });
        });

        // Bulk: activate ready leases
        $('#bulkActivateBtn').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');
            $.ajax({
                url: '{{ route("property.leases.setup.bulk-activate") }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function (response) {
                    toastr.success(response.updated + ' lease(s) activated.');
                    reloadAllLoadedTables();
                },
                error: function () {
                    toastr.error('Failed to activate leases.');
                },
                complete: function () {
                    $btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
                }
            });
        });

        // Per-row: save a manually-entered end date
        $('.setup-table').on('click', '.save-end-date-btn', function () {
            var id = $(this).data('id');
            var value = $(this).closest('td').find('input[type=date]').val();
            if (!value) {
                toastr.warning('Pick a date first.');
                return;
            }
            $.ajax({
                url: '{{ url("/property/leases/setup") }}/' + id,
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', date_end: value },
                success: function () {
                    toastr.success('End date saved.');
                    reloadAllLoadedTables();
                },
                error: function (xhr) {
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save end date.');
                }
            });
        });

        // Per-row: activate
        $('.setup-table').on('click', '.activate-btn', function () {
            var id = $(this).data('id');
            $.ajax({
                url: '{{ url("/property/leases/setup") }}/' + id + '/activate',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function (response) {
                    toastr.success(response.message);
                    reloadAllLoadedTables();
                },
                error: function (xhr) {
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to activate lease.');
                }
            });
        });

        // Resolve tenant mismatch modal
        $('.setup-table').on('click', '.resolve-tenant-btn', function () {
            leaseIdBeingResolved = $(this).data('id');
            $('#resolveLeaseRef').text($(this).data('ref'));
            $('#resolveLeaseTenantName').text($(this).data('lease-tenant-name'));
            $('#resolveUnitTenantName').text($(this).data('unit-tenant-name'));
            $('#keepLeaseTenantBtn').data('tenant-id', $(this).data('lease-tenant-id'));
            $('#useUnitTenantBtn').data('tenant-id', $(this).data('unit-tenant-id'));
            $('#resolveTenantSearch').val(null).trigger('change');
            $('#resolveTenantModal').modal('show');
        });

        function submitTenantResolution(tenantId) {
            $.ajax({
                url: '{{ url("/property/leases/setup") }}/' + leaseIdBeingResolved,
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', tenant_id: tenantId },
                success: function () {
                    toastr.success('Tenant resolved.');
                    $('#resolveTenantModal').modal('hide');
                    reloadAllLoadedTables();
                },
                error: function (xhr) {
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to resolve tenant.');
                }
            });
        }

        $('#keepLeaseTenantBtn').on('click', function () {
            submitTenantResolution($(this).data('tenant-id'));
        });
        $('#useUnitTenantBtn').on('click', function () {
            submitTenantResolution($(this).data('tenant-id'));
        });

        $('#resolveTenantSearch').select2({
            dropdownParent: $('#resolveTenantModal'),
            placeholder: 'Search tenant by name, email, ID, phone or reference...',
            width: '100%',
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("ajax.statements.search.tenants") }}',
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return { search: params.term };
                },
                processResults: function (response) {
                    var results = (response.tenants || []).map(function (t) {
                        return { id: t.id, text: (t.names || '') + ' - ' + (t.reference_number || '') };
                    });
                    return { results: results };
                }
            }
        });

        $('#resolveTenantSearch').on('select2:select', function (e) {
            submitTenantResolution(e.params.data.id);
        });
    });
</script>
@endsection
