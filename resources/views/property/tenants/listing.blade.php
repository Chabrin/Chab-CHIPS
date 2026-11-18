@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tenant-listing.css') }}?v={{ filemtime(public_path('assets/css/tenant-listing.css')) }}">
@endsection

@section('content')
<div class="page-body" id="tenant-listing">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <section class="card tenant-directory" aria-labelledby="tenant-directory-title">
                    <header class="tenant-directory-header">
                        <div>
                            <p class="tenant-directory-eyebrow">Management</p>
                            <h1 id="tenant-directory-title">Tenants</h1>
                        </div>
                        <div class="tenant-directory-actions">
                            <a href="{{ route('units.tenants.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                <span class="material-symbols-outlined" aria-hidden="true">person_add</span> Onboard tenant
                            </a>
                            <div class="dropdown">
                                <button class="btn tenant-secondary-button dropdown-toggle d-flex align-items-center" type="button" id="tenantActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="material-symbols-outlined me-1" style="font-size:18px;">more_vert</span>
                                    <span>Tenant actions</span>
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
                                </ul>
                            </div>
                        </div>
                    </header>

                    <div class="tenant-directory-body">
                        <div class="tenant-directory-toolbar">
                            <div role="group" aria-label="Filter by tenancy status" id="status-filter">
                                <button type="button" class="active" data-status="active" aria-pressed="true">Active</button>
                                <button type="button" data-status="exited" aria-pressed="false">Exited</button>
                                <button type="button" data-status="pending_unit" aria-pressed="false">Pending unit</button>
                                <button type="button" data-status="all" aria-pressed="false">All</button>
                            </div>
                            <span id="result_info" class="tenant-result-count" aria-live="polite" style="display:none;">
                                <strong id="result_count">0</strong> matching tenants
                            </span>
                        </div>
                        <div id="tenant-filters">
                            <label>Search by tenant name
                                <input type="search" class="form-control col-filter" data-filter="filter_names" placeholder="At least 3 characters">
                            </label>
                            <details>
                                <summary>More filters</summary>
                                <div class="tenant-extra-filters">
                                    <label>Property<input type="search" class="form-control col-filter" data-filter="filter_property"></label>
                                    <label>Landlord<input type="search" class="form-control col-filter" data-filter="filter_landlord"></label>
                                    <label>Phone<input type="search" class="form-control col-filter" data-filter="filter_phone"></label>
                                    <label>ID number<input type="search" class="form-control col-filter" data-filter="filter_id_number"></label>
                                </div>
                            </details>
                        </div>
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="tenants-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Tenant</th>
                                        <th>Phone</th>
                                        <th>ID Number</th>
                                        <th>Property</th>
                                        <th>Landlord</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
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
<script>
    $(document).ready(function () {
        var currentStatus = 'active';
        var pendingRequest;
        const textRenderer = $.fn.dataTable.render.text();
        const escapeText = textRenderer.display;

        var table = $('#tenants-table').DataTable({
            data: [],
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (d, t, r, m) { return m.row + 1; }
                },
                {
                    data: 'names',
                    render: function (name, type, row) {
                        if (type !== 'display') return name;
                        return '<a href="/tenants/' + encodeURIComponent(row.encrypted_id) + '">' + escapeText(name) + '</a>';
                    }
                },
                { data: 'mobile_number', render: textRenderer },
                { data: 'national_id', render: textRenderer },
                {
                    data: 'property_name',
                    render: function (property, type, row) {
                        if (type !== 'display') return property;
                        const unit = row.unit_code ? '<span class="tenant-unit">Unit ' + escapeText(row.unit_code) + '</span>' : '';
                        return escapeText(property) + unit;
                    }
                },
                { data: 'landlord_name', render: textRenderer },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (d, t, r) {
                        if (r.is_exited) {
                            const label = r.exit_date ? 'Exited · ' + r.exit_date : 'Exited';
                            return $('<span>', {
                                class: 'tenant-status tenant-status-exited',
                                title: r.exit_reason || '',
                                text: label
                            })[0];
                        }
                        if (r.pending_unit) {
                            return '<span class="tenant-status tenant-status-pending">Pending unit</span>';
                        }
                        return '<span class="tenant-status tenant-status-active">Active</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (d, t, r) {
                        if (t !== 'display') return '';
                        const id = encodeURIComponent(r.encrypted_id);
                        const actions = $('<div>', { class: 'tenant-row-actions' }).append(
                            '<a href="/tenants/' + id + '" title="View Details" aria-label="View Details">' +
                            '<span class="material-symbols-outlined" aria-hidden="true">visibility</span></a>' +
                            '<a href="' + '{{ route("move.in.inspections.create") }}?tenant=' + id + '" title="Record Move-In Inspection" aria-label="Record Move-In Inspection">' +
                            '<span class="material-symbols-outlined" aria-hidden="true">search_check</span></a>'
                        );
                        if (r.pending_unit) {
                            actions.append($('<a>', {
                                href: '#',
                                class: 'assign-unit-link',
                                'data-id': r.encrypted_id,
                                'data-name': r.names,
                                title: 'Assign Unit',
                                'aria-label': 'Assign Unit'
                            }).append('<span class="material-symbols-outlined" aria-hidden="true">home_work</span>'));
                        }
                        return actions[0];
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
            layout: { topStart: null, topEnd: null, bottomStart: 'info', bottomEnd: ['pageLength', 'paging'] },
            order: [[1, 'asc']],
            pageLength: 50,
            language: {
                emptyTable: 'No tenants match the selected status or filters.',
                lengthMenu: 'Rows per page _MENU_',
                info: '_START_–_END_ of _TOTAL_',
                infoEmpty: '0 tenants'
            }
        });

        // Wrap each filter input with a spinner element
        $('#tenant-filters input.col-filter').each(function () {
            $(this).wrap('<div class="col-filter-wrap"></div>');
            $(this).after('<span class="filter-spinner"></span>');
        });

        var debounceTimer;

        function loadData() {
            if (pendingRequest) pendingRequest.abort();
            $('#tenant-filters .col-filter-wrap').removeClass('loading');
            var $activeFilters = $('#tenant-filters .col-filter-wrap').filter(function () {
                return $(this).find('input').val().trim().length >= 3;
            });
            $activeFilters.addClass('loading');

            var params = { status: currentStatus };
            $('#tenant-filters input.col-filter').each(function () {
                var key = $(this).data('filter');
                var val = $(this).val().trim();
                if (val.length >= 3) params[key] = val;
            });

            pendingRequest = $.ajax({
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
                error: function (xhr, status) {
                    if (status === 'abort') return;
                    $activeFilters.removeClass('loading');
                    var msg = 'Failed to load tenants.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    toastr.error(msg);
                }
            });
        }

        // Status filter tabs
        $('#status-filter button').on('click', function () {
            $('#status-filter button').removeClass('active').attr('aria-pressed', 'false');
            $(this).addClass('active').attr('aria-pressed', 'true');
            currentStatus = $(this).data('status');
            clearTimeout(debounceTimer);
            loadData();
        });

        // Column filters with debounce (3+ chars)
        $('#tenant-filters input.col-filter').on('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadData, 400);
        });

        // Land directly on the Pending Unit tab after a bulk upload that left rows unassigned
        var initialStatus = new URLSearchParams(window.location.search).get('status');
        if (initialStatus && $('#status-filter button[data-status="' + initialStatus + '"]').length) {
            $('#status-filter button').removeClass('active').attr('aria-pressed', 'false');
            $('#status-filter button[data-status="' + initialStatus + '"]').addClass('active').attr('aria-pressed', 'true');
            currentStatus = initialStatus;
        }

        // Initial load
        loadData();

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
