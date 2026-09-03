@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="mb-0">Tenant Management</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <small class="text-muted" id="result_info" style="display:none;">
                                Showing <strong id="result_count">0</strong> tenants (max 1000)
                            </small>
                            <div class="btn-group btn-group-sm" role="group" id="status-filter">
                                <button type="button" class="btn btn-outline-secondary active" data-status="active">Active</button>
                                <button type="button" class="btn btn-outline-secondary" data-status="exited">Exited</button>
                                <button type="button" class="btn btn-outline-secondary" data-status="pending_unit">Pending Unit</button>
                                <button type="button" class="btn btn-outline-secondary" data-status="all">All</button>
                            </div>
                            <a href="{{ route('units.tenants.create') }}" class="btn btn-sm btn-primary d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span> Onboard tenant
                            </a>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center" type="button" id="tenantActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="material-symbols-outlined me-1" style="font-size:18px;">more_vert</span>
                                    <span>Actions</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="tenantActionsDropdown">
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center" href="{{ route('units.tenants.returning.create') }}">
                                            <span class="material-symbols-outlined me-2" style="font-size:18px;">history</span> Returning Tenant
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center" href="{{ route('move.in.inspections.index') }}">
                                            <span class="material-symbols-outlined me-2" style="font-size:18px;">search_check</span> Move-In Inspections
                                        </a>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#bulkUploadTenantsModal">
                                            <span class="material-symbols-outlined me-2" style="font-size:18px;">upload_file</span> Bulk Upload Tenants
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="tenants-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Names</th>
                                        <th>Phone</th>
                                        <th>ID Number</th>
                                        <th>Property</th>
                                        <th>Landlord</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                    <tr class="column-filters">
                                        <th></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_names" placeholder="-- Search --" /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_phone" placeholder="-- Search --" /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_id_number" placeholder="-- Search --" /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_property" placeholder="-- Search --" /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_landlord" placeholder="-- Search --" /></th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Upload Tenants Modal -->
<div class="modal fade" id="bulkUploadTenantsModal" tabindex="-1" aria-labelledby="bulkUploadTenantsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkUploadTenantsModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align: middle;">upload_file</span>
                    Bulk Upload Tenants
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('property.bulk.upload') }}" method="POST" enctype="multipart/form-data" id="bulkUploadTenantsForm">
                @csrf
                <input type="hidden" name="upload_type" value="tenants">

                <div class="modal-body">
                    <div class="mb-4 p-3 bg-light rounded">
                        <div class="d-flex align-items-start">
                            <span class="material-symbols-outlined text-primary me-2" style="font-size:24px;">info</span>
                            <div class="flex-grow-1">
                                <h6 class="mb-2">Download Sample Template</h6>
                                <p class="mb-2 text-muted small">
                                    Fill in the tenants to create. Leave "Unit Code" blank for a tenant whose unit
                                    isn't known yet — they'll appear under "Pending Unit" for manual assignment
                                    afterward. Move-in inspections are not part of this upload.
                                </p>
                                <a href="{{ route('property.bulk.sample') }}?type=tenants" class="btn btn-outline-primary btn-sm">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">download</span>
                                    Download Sample File
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="tenants_bulk_file" class="form-label fw-bold">
                            <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">attach_file</span>
                            Upload File <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control" id="tenants_bulk_file" name="bulk_file" accept=".xlsx,.xls,.csv" required>
                        <small class="form-text text-muted">Accepted formats: Excel (.xlsx, .xls) or CSV (.csv)</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="uploadTenantsBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="uploadTenantsSpinner"></span>
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">upload</span>
                        Upload &amp; Process
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Unit Modal -->
<div class="modal fade" id="assignUnitModal" tabindex="-1" aria-labelledby="assignUnitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignUnitModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align: middle;">home_work</span>
                    Assign Unit
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="assignUnitTenantName"></p>

                <div class="mb-3">
                    <label for="assign_landlord_id" class="form-label">Landlord <span class="text-muted">(optional, narrows Property below)</span></label>
                    <select class="form-select" id="assign_landlord_id" style="width:100%;"></select>
                </div>

                <div class="mb-3">
                    <label for="assign_property_id" class="form-label">Property <span class="text-danger">*</span></label>
                    <select class="form-select" id="assign_property_id" style="width:100%;"></select>
                </div>

                <div class="mb-0">
                    <label for="assign_unit_id" class="form-label">Vacant Unit <span class="text-danger">*</span></label>
                    <select class="form-select" id="assign_unit_id" style="width:100%;" disabled></select>
                    <small class="text-muted" id="assignUnitHint">Select a property first.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="assignUnitSaveBtn">
                    <span class="spinner-border spinner-border-sm me-2 d-none" id="assignUnitSpinner"></span>
                    Assign
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<style>
    .col-filter-wrap {
        position: relative;
    }

    .col-filter-wrap .col-filter {
        padding-right: 22px;
    }

    .col-filter-wrap .filter-spinner {
        position: absolute;
        right: 6px;
        top: 50%;
        margin-top: -7px;
        width: 14px;
        height: 14px;
        border: 2px solid #ccc;
        border-top-color: var(--accent, #3598db);
        border-radius: 50%;
        animation: filterspin .6s linear infinite;
        display: none;
    }

    .col-filter-wrap.loading .filter-spinner {
        display: block;
    }

    @keyframes filterspin {
        to { transform: rotate(360deg); }
    }

    tr.tenant-exited td {
        color: #9ca3af !important;
    }

    tr.tenant-exited td a .material-symbols-outlined {
        color: #9ca3af !important;
    }
</style>
<script>
    $(document).ready(function () {
        var currentStatus = 'active';

        var table = $('#tenants-table').DataTable({
            data: [],
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (d, t, r, m) { return m.row + 1; }
                },
                { data: 'names' },
                { data: 'mobile_number' },
                { data: 'national_id' },
                { data: 'property_name' },
                { data: 'landlord_name' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (d, t, r) {
                        if (r.is_exited) {
                            var label = r.exit_date ? 'Exited · ' + r.exit_date : 'Exited';
                            var title = r.exit_reason ? ' title="' + $('<div>').text(r.exit_reason).html() + '"' : '';
                            return '<span class="badge bg-danger-light text-danger fw-normal"' + title + '>' + label + '</span>';
                        }
                        return '<span class="badge bg-success-light text-success fw-normal">Active</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (d, t, r) {
                        var html = '<div class="d-flex flex-row gap-2">' +
                            '<a href="/tenants/' + r.encrypted_id + '" title="View Details">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;color:var(--accent);">folder_eye</span></a>' +
                            '<a href="' + '{{ route("move.in.inspections.create") }}?tenant=' + r.encrypted_id + '" title="Record Move-In Inspection">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;color:var(--accent);">search_check</span></a>';
                        if (r.pending_unit) {
                            html += '<a href="#" class="assign-unit-link" data-id="' + r.encrypted_id + '" data-name="' + $('<div>').text(r.names).html() + '" title="Assign Unit">' +
                                '<span class="material-symbols-outlined" style="font-size:18px;color:var(--accent);">home_work</span></a>';
                        }
                        html += '</div>';
                        return html;
                    }
                }
            ],
            createdRow: function (row, data) {
                if (data.is_exited) {
                    $(row).addClass('tenant-exited');
                }
            },
            paging: true,
            info: true,
            lengthChange: true,
            searching: false,
            ordering: true,
            orderCellsTop: true,
            order: [[1, 'asc']],
            pageLength: 50
        });

        // Wrap each filter input with a spinner element
        $('#tenants-table thead .column-filters input.col-filter').each(function () {
            $(this).wrap('<div class="col-filter-wrap"></div>');
            $(this).after('<span class="filter-spinner"></span>');
        });

        var debounceTimer;

        function loadData() {
            var $activeFilters = $('#tenants-table thead .column-filters .col-filter-wrap').filter(function () {
                return $(this).find('input').val().trim().length >= 3;
            });
            $activeFilters.addClass('loading');

            var params = { status: currentStatus };
            $('#tenants-table thead .column-filters input.col-filter').each(function () {
                var key = $(this).data('filter');
                var val = $(this).val().trim();
                if (val.length >= 3) params[key] = val;
            });

            $.ajax({
                url: '{{ route("ajax.tenants.listing.data") }}',
                type: 'GET',
                data: params,
                success: function (response) {
                    $activeFilters.removeClass('loading');
                    if (response.success) {
                        $('#result_info').show();
                        $('#result_count').text(response.count);
                        table.clear();
                        table.rows.add(response.data);
                        table.draw();
                    }
                },
                error: function (xhr) {
                    $activeFilters.removeClass('loading');
                    var msg = 'Failed to load tenants.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    toastr.error(msg);
                }
            });
        }

        // Status filter tabs
        $('#status-filter button').on('click', function () {
            $('#status-filter button').removeClass('active');
            $(this).addClass('active');
            currentStatus = $(this).data('status');
            loadData();
        });

        // Column filters with debounce (3+ chars)
        $('#tenants-table thead .column-filters input.col-filter').on('keyup', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadData, 400);
        });

        // Land directly on the Pending Unit tab after a bulk upload that left rows unassigned
        var initialStatus = new URLSearchParams(window.location.search).get('status');
        if (initialStatus && $('#status-filter button[data-status="' + initialStatus + '"]').length) {
            $('#status-filter button').removeClass('active');
            $('#status-filter button[data-status="' + initialStatus + '"]').addClass('active');
            currentStatus = initialStatus;
        }

        // Initial load
        loadData();

        // Bulk Upload Tenants modal
        $('#bulkUploadTenantsForm').on('submit', function () {
            $('#uploadTenantsSpinner').removeClass('d-none');
            $('#uploadTenantsBtn').prop('disabled', true);
        });

        $('#bulkUploadTenantsModal').on('hidden.bs.modal', function () {
            $('#bulkUploadTenantsForm')[0].reset();
            $('#uploadTenantsSpinner').addClass('d-none');
            $('#uploadTenantsBtn').prop('disabled', false);
        });

        // Assign Unit modal — Landlord (optional) -> Property -> Unit cascade
        var selectedPropertyId = null;

        $('#assign_landlord_id').select2({
            dropdownParent: $('#assignUnitModal'),
            placeholder: 'Search landlord by name (optional)...',
            width: '100%',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("units.tenants.search-landlords") }}',
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return { q: params.term };
                },
                processResults: function (response) {
                    return { results: response.results };
                }
            }
        });

        // Once a landlord is chosen, Property switches from type-to-search
        // (minimumInputLength 2, across all properties) to listing that
        // landlord's full property set as soon as the field is opened
        // (minimumInputLength 0) — no need to type when picking from an
        // already-narrowed list. Select2 doesn't support changing
        // minimumInputLength on a live instance, so this destroys and
        // reinitializes it whenever the landlord selection changes.
        function initPropertyPicker(scopedToLandlord) {
            var $field = $('#assign_property_id');
            if ($field.hasClass('select2-hidden-accessible')) {
                $field.select2('destroy');
            }
            $field.select2({
                dropdownParent: $('#assignUnitModal'),
                placeholder: scopedToLandlord ? 'Select from landlord\'s properties...' : 'Search property by name or reference...',
                width: '100%',
                minimumInputLength: scopedToLandlord ? 0 : 2,
                ajax: {
                    url: '{{ route("units.tenants.search-properties") }}',
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        return { term: params.term, client_id: $('#assign_landlord_id').val() || null };
                    },
                    processResults: function (response) {
                        return { results: response.results };
                    }
                }
            });
        }

        initPropertyPicker(false);

        $('#assign_unit_id').select2({
            dropdownParent: $('#assignUnitModal'),
            placeholder: 'Select a property first',
            width: '100%',
            minimumInputLength: 0,
            ajax: {
                url: '{{ route("units.tenants.vacant-units") }}',
                dataType: 'json',
                delay: 200,
                data: function (params) {
                    return { property_id: selectedPropertyId, term: params.term };
                },
                processResults: function (response) {
                    return { results: response.results };
                }
            }
        });

        // Re-searching the landlord clears whatever property/unit was already picked,
        // since the property list itself is about to change.
        $('#assign_landlord_id').on('change', function () {
            initPropertyPicker(!!$(this).val());
            $('#assign_property_id').val(null).trigger('change');
        });

        $('#assign_property_id').on('change', function () {
            selectedPropertyId = $(this).val();
            $('#assign_unit_id').val(null).trigger('change');
            $('#assign_unit_id').prop('disabled', !selectedPropertyId);
            $('#assignUnitHint').text(selectedPropertyId ? '' : 'Select a property first.');
        });

        $('#tenants-table tbody').on('click', '.assign-unit-link', function (e) {
            e.preventDefault();
            $('#assignUnitSaveBtn').data('tenant-id', $(this).data('id'));
            $('#assignUnitTenantName').text('Tenant: ' + $(this).data('name'));
            selectedPropertyId = null;
            $('#assign_landlord_id').val(null).trigger('change'); // also resets Property back to search mode
            $('#assign_unit_id').val(null).trigger('change').prop('disabled', true);
            $('#assignUnitHint').text('Select a property first.');
            $('#assignUnitModal').modal('show');
        });

        $('#assignUnitSaveBtn').on('click', function () {
            var tenantId = $(this).data('tenant-id');
            var unitId = $('#assign_unit_id').val();

            if (!unitId) {
                toastr.error('Please select a unit.');
                return;
            }

            var $btn = $(this);
            $('#assignUnitSpinner').removeClass('d-none');
            $btn.prop('disabled', true);

            $.ajax({
                url: '/property/tenants/' + tenantId + '/assign-unit',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', unit_id: unitId },
                success: function (response) {
                    $('#assignUnitSpinner').addClass('d-none');
                    $btn.prop('disabled', false);
                    if (response.success) {
                        toastr.success(response.message);
                        $('#assignUnitModal').modal('hide');
                        loadData();
                    } else {
                        toastr.error(response.message || 'Failed to assign unit.');
                    }
                },
                error: function (xhr) {
                    $('#assignUnitSpinner').addClass('d-none');
                    $btn.prop('disabled', false);
                    var msg = 'Failed to assign unit.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    toastr.error(msg);
                }
            });
        });
    });
</script>
@endsection
