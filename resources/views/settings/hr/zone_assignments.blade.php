@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Zone Assignments</h5>
                    </div>
                    <div class="card-body">
                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs mb-3" id="zoneAssignmentsTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="unallocated-tab" data-bs-toggle="tab" data-bs-target="#unallocated-pane" type="button">
                                    <span class="material-symbols-outlined" style="font-size:18px;">assignment_late</span>
                                    Unallocated
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="field-officers-tab" data-bs-toggle="tab" data-bs-target="#field-officers-pane" type="button">
                                    <span class="material-symbols-outlined" style="font-size:18px;">directions_walk</span>
                                    Field Officers
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="zone-managers-tab" data-bs-toggle="tab" data-bs-target="#zone-managers-pane" type="button">
                                    <span class="material-symbols-outlined" style="font-size:18px;">supervisor_account</span>
                                    Zone Managers
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="zone-supervisors-tab" data-bs-toggle="tab" data-bs-target="#zone-supervisors-pane" type="button">
                                    <span class="material-symbols-outlined" style="font-size:18px;">manage_accounts</span>
                                    Zone Supervisors
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="zone-auditors-tab" data-bs-toggle="tab" data-bs-target="#zone-auditors-pane" type="button">
                                    <span class="material-symbols-outlined" style="font-size:18px;">fact_check</span>
                                    Zone Auditors
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="property-managers-tab" data-bs-toggle="tab" data-bs-target="#property-managers-pane" type="button">
                                    <span class="material-symbols-outlined" style="font-size:18px;">badge</span>
                                    Property Managers
                                </button>
                            </li>
                        </ul>

                        <!-- Tab panes -->
                        <div class="tab-content" id="zoneAssignmentsTabContent">
                            <div class="tab-pane fade show active" id="unallocated-pane" role="tabpanel">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="unallocated_property_search_input" class="form-label mb-1">Search Property</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-sm" id="unallocated_property_search_input"
                                                placeholder="-- Search --">
                                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="unallocated_search_property_btn">
                                                <span class="material-symbols-outlined" id="unallocated_search_property_icon" style="font-size:18px;">search</span>
                                                <span class="spinner-border spinner-border-sm" role="status" id="unallocated_search_property_spinner" style="display:none;"></span>
                                            </button>
                                        </div>
                                        <small class="text-muted">Search by property name or reference — pick a result to allocate it</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="unallocated_property_select" class="form-label mb-1">Select Property</label>
                                        <select class="form-select form-select-sm" id="unallocated_property_select" disabled>
                                            <option value="">-- Select Property --</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="table-responsive custom-scrollbar">
                                    <table id="unallocatedPropertiesTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Property Name</th>
                                                <th>Reference Number</th>
                                                <th>Zone</th>
                                                <th>Zone Manager</th>
                                                <th>Zone Supervisor</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="field-officers-pane" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="fieldOfficersTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Name</th>
                                                <th>Mobile</th>
                                                <th>Email</th>
                                                <th>Properties</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="zone-managers-pane" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="zoneManagersTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Name</th>
                                                <th>Mobile</th>
                                                <th>Email</th>
                                                <th>Properties</th>
                                                <th>Field Officers</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="zone-supervisors-pane" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="zoneSupervisorsTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Name</th>
                                                <th>Mobile</th>
                                                <th>Email</th>
                                                <th>Properties</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="zone-auditors-pane" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="zoneAuditorsTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Name</th>
                                                <th>Mobile</th>
                                                <th>Email</th>
                                                <th>Properties</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="property-managers-pane" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="propertyManagersTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Name</th>
                                                <th>Mobile</th>
                                                <th>Email</th>
                                                <th>Position</th>
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
    </div>
</div>

<!-- Shared "Properties under this staff member" modal -->
<div class="modal fade" id="staffPropertiesModal" tabindex="-1" aria-labelledby="staffPropertiesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffPropertiesModalLabel">Properties</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="staffPropertiesBulkBar" class="d-none align-items-center justify-content-between gap-2 mb-2 p-2" style="background:#f0f7fd;border:1px solid #d9ebf7;border-radius:6px;">
                    <span class="small fw-semibold" style="color:#212529;"><strong id="staffPropertiesSelectedCount">0</strong> selected</span>
                    <button type="button" class="btn btn-sm btn-primary" id="reassignSelectedBtn">
                        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">swap_horiz</span>
                        Reassign Selected
                    </button>
                </div>
                <div class="table-responsive custom-scrollbar">
                    <table id="staffPropertiesTable" class="display" style="width:100%;">
                        <thead><tr></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Reassign modal -->
<div class="modal fade" id="bulkReassignModal" tabindex="-1" aria-labelledby="bulkReassignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkReassignModalLabel">Reassign Properties</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bulkReassignForm">
                <div class="modal-body">
                    <p class="text-muted small mb-3"><strong id="bulkReassignCount">0</strong> propert(ies) will be moved to the field officer selected below.</p>
                    <div class="mb-3">
                        <label for="bulk_reassign_field_officer_id" class="form-label">New Field Officer <span class="text-danger">*</span></label>
                        <select class="form-select" id="bulk_reassign_field_officer_id" name="field_officer_id" required>
                            <option value="">Select field officer</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveBulkReassignBtn">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span class="ms-1">Reassign</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Allocate Property modal -->
<div class="modal fade" id="allocatePropertyModal" tabindex="-1" aria-labelledby="allocatePropertyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allocatePropertyModalLabel">Allocate Property</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="allocatePropertyForm">
                <div class="modal-body">
                    <input type="hidden" id="allocate_property_id" name="property_id">
                    <div class="mb-3">
                        <label class="form-label">Property</label>
                        <div class="form-control-plaintext py-0 fw-semibold" id="allocate_property_name">-</div>
                        <small class="text-muted" id="allocate_property_zone"></small>
                    </div>
                    <div class="mb-3">
                        <label for="allocate_field_officer_id" class="form-label">Field Officer <span class="text-danger">*</span></label>
                        <select class="form-select" id="allocate_field_officer_id" name="field_officer_id" required>
                            <option value="">Select field officer</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Zone Manager</label>
                        <div class="form-control-plaintext py-0" id="allocate_zone_manager_name">-</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Zone Supervisor</label>
                        <div class="form-control-plaintext py-0" id="allocate_zone_supervisor_name">-</div>
                    </div>
                    <small class="text-muted d-block">Zone Manager/Supervisor are assigned from the staff member's own record — change them under Employees &gt; Employment Details.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveAllocationBtn">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span class="ms-1">Save</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .staff-name-link,
    .text-link {
        color: var(--theme-default, #3598db);
        text-decoration: underline;
        cursor: pointer;
    }
</style>
<script>
    $(document).ready(function() {
        function loadReportTable(tableId, url, columns) {
            return $(tableId).DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: url,
                    type: 'GET',
                    dataType: 'json',
                    dataSrc: 'data'
                },
                columns: columns,
                paging: true,
                pageLength: 10,
                info: true,
                lengthChange: true
            });
        }

        const rowNumberColumn = {
            data: null,
            orderable: false,
            searchable: false,
            render: (data, type, row, meta) => meta.row + 1
        };

        function nameLinkColumn(type) {
            return {
                data: 'full_names',
                render: function(data, renderType, row) {
                    if (renderType !== 'display') return data || '';
                    const name = data || '-';
                    return `<span class="staff-name-link" data-type="${type}" data-staff-id="${row.id}" data-staff-name="${name}">${name}</span>`;
                }
            };
        }

        function detailsColumn(type) {
            return {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, renderType, row) {
                    return `<span class="staff-name-link staff-details-btn" data-type="${type}" data-staff-id="${row.id}" data-staff-name="${row.full_names || ''}">Details</span>`;
                }
            };
        }

        const fieldOfficersTable = loadReportTable('#fieldOfficersTable', "{{ url('/ajax/hr/zone/assignments/field-officers') }}", [
            rowNumberColumn,
            nameLinkColumn('field_officer'),
            { data: 'mobile_number', render: data => data || '-' },
            { data: 'email_address', render: data => data || '-' },
            { data: 'properties_count', render: data => (typeof data === 'number' ? data : parseInt(data) || 0) },
            detailsColumn('field_officer')
        ]);

        const zoneManagersTable = loadReportTable('#zoneManagersTable', "{{ url('/ajax/hr/zone/assignments/zone-managers') }}", [
            rowNumberColumn,
            nameLinkColumn('zone_manager'),
            { data: 'mobile_number', render: data => data || '-' },
            { data: 'email_address', render: data => data || '-' },
            { data: 'properties_count', render: data => (typeof data === 'number' ? data : parseInt(data) || 0) },
            { data: 'field_officers_count', render: data => (typeof data === 'number' ? data : parseInt(data) || 0) },
            detailsColumn('zone_manager')
        ]);

        const zoneSupervisorsTable = loadReportTable('#zoneSupervisorsTable', "{{ url('/ajax/hr/zone/assignments/zone-supervisors') }}", [
            rowNumberColumn,
            nameLinkColumn('zone_supervisor'),
            { data: 'mobile_number', render: data => data || '-' },
            { data: 'email_address', render: data => data || '-' },
            { data: 'properties_count', render: data => (typeof data === 'number' ? data : parseInt(data) || 0) },
            detailsColumn('zone_supervisor')
        ]);

        const zoneAuditorsTable = loadReportTable('#zoneAuditorsTable', "{{ url('/ajax/hr/zone/assignments/zone-auditors') }}", [
            rowNumberColumn,
            nameLinkColumn('zone_auditor'),
            { data: 'mobile_number', render: data => data || '-' },
            { data: 'email_address', render: data => data || '-' },
            { data: 'properties_count', render: data => (typeof data === 'number' ? data : parseInt(data) || 0) },
            detailsColumn('zone_auditor')
        ]);

        loadReportTable('#propertyManagersTable', "{{ url('/ajax/hr/zone/assignments/property-managers') }}", [
            rowNumberColumn,
            { data: 'full_names', render: data => data || '-' },
            { data: 'mobile_number', render: data => data || '-' },
            { data: 'email_address', render: data => data || '-' },
            { data: 'position_name', render: data => data || '-' }
        ]);

        const unallocatedTable = loadReportTable('#unallocatedPropertiesTable', "{{ url('/ajax/hr/zone/assignments/unallocated') }}", [
            rowNumberColumn,
            { data: 'property_name', render: data => data || '-' },
            { data: 'reference_number', render: data => data || '-' },
            { data: 'zone_name', render: data => data || '-' },
            { data: 'zone_manager_name', render: data => data || '-' },
            { data: 'zone_supervisor_name', render: data => data || '-' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data) {
                    return `<span class="text-link allocate-property-link" data-property-id="${data.id}">Allocate</span>`;
                }
            }
        ]);

        // ===== Properties drill-down modal (Field Officers / Zone Managers / Zone Supervisors) =====
        // Only Field Officer allocations support reassignment here — Zone Manager/
        // Supervisor are set from the staff record or the Zones page instead.
        let staffPropertiesType = null;
        let staffPropertiesTable = null;
        let selectedProperties = new Map();

        function updateStaffPropertiesBulkBar() {
            const count = selectedProperties.size;
            $('#staffPropertiesSelectedCount').text(count);
            $('#staffPropertiesBulkBar').toggleClass('d-none', count === 0).toggleClass('d-flex', count > 0);
        }

        // Header checkbox reflects selection across ALL pages, not just the visible one.
        function updateSelectAllStaffPropertiesHeader() {
            const $selectAll = $('#selectAllStaffProperties');
            if (!$selectAll.length || !staffPropertiesTable) return;
            const total = staffPropertiesTable.rows().count();
            const selected = selectedProperties.size;
            $selectAll.prop('checked', total > 0 && selected === total);
            $selectAll.prop('indeterminate', selected > 0 && selected < total);
        }

        function openStaffPropertiesModal(type, staffId, staffName) {
            staffPropertiesType = type;
            selectedProperties = new Map();
            updateStaffPropertiesBulkBar();

            $('#staffPropertiesModalLabel').text('Properties — ' + (staffName || ''));
            $('#staffPropertiesModal').modal('show');

            if ($.fn.DataTable.isDataTable('#staffPropertiesTable')) {
                $('#staffPropertiesTable').DataTable().destroy();
                $('#staffPropertiesTable tbody').empty();
            }

            const isFieldOfficer = type === 'field_officer';

            const headerCells = ['#', 'Property Name', 'Reference Number', 'Client Name', 'Zone'];
            if (isFieldOfficer) {
                headerCells.unshift('<input type="checkbox" id="selectAllStaffProperties" class="form-check-input">');
                headerCells.push('Action');
            }
            $('#staffPropertiesTable thead tr').html(headerCells.map(h => `<th>${h}</th>`).join(''));

            const columns = [
                rowNumberColumn,
                { data: 'property_name', render: data => data || '-' },
                { data: 'reference_number', render: data => data || '-' },
                { data: 'client_name', render: data => data || '-' },
                { data: 'zone_name', render: data => data || '-' }
            ];
            if (isFieldOfficer) {
                columns.unshift({
                    data: null,
                    orderable: false,
                    searchable: false,
                    // Reflects selectedProperties on every render, so paginating (which
                    // destroys/rebuilds row DOM) or a redraw after "select all" both show
                    // the correct checked state without a separate manual sync step.
                    render: (data, t, row) => `<input type="checkbox" class="form-check-input row-select-property" data-id="${row.id}" ${selectedProperties.has(row.id) ? 'checked' : ''}>`
                });
                columns.push({
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (data, t, row) => `<a href="javascript:void(0)" class="reassign-property-link" data-property-id="${row.id}" title="Reassign">
                        <span class="material-symbols-outlined" style="font-size:18px;color:var(--accent, #3598db);">swap_horiz</span></a>`
                });
            }

            staffPropertiesTable = $('#staffPropertiesTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: "{{ url('/ajax/hr/zone/assignments/staff-properties') }}",
                    type: 'GET',
                    data: {
                        type: type,
                        staff_id: staffId
                    },
                    dataType: 'json',
                    dataSrc: 'data'
                },
                columns: columns,
                paging: true,
                pageLength: 10,
                searching: true,
                info: true,
                lengthChange: true,
                drawCallback: function() {
                    if (isFieldOfficer) {
                        updateSelectAllStaffPropertiesHeader();
                    }
                }
            });
        }

        $(document).on('click', '.staff-name-link, .staff-details-btn', function() {
            const type = $(this).data('type');
            const staffId = $(this).data('staff-id');
            if (!type || !staffId) return;
            const staffName = $(this).data('staff-name');
            openStaffPropertiesModal(type, staffId, staffName);
        });

        $('#staffPropertiesModal').on('hidden.bs.modal', function() {
            if ($.fn.DataTable.isDataTable('#staffPropertiesTable')) {
                $('#staffPropertiesTable').DataTable().destroy();
                $('#staffPropertiesTable tbody').empty();
            }
            selectedProperties = new Map();
            updateStaffPropertiesBulkBar();
        });

        // Row selection
        $(document).on('change', '#staffPropertiesTable .row-select-property', function() {
            const id = $(this).data('id');
            if ($(this).is(':checked')) {
                selectedProperties.set(id, true);
            } else {
                selectedProperties.delete(id);
            }
            updateStaffPropertiesBulkBar();
            updateSelectAllStaffPropertiesHeader();
        });

        // Select all — across every page, not just the one currently visible. This
        // DataTable loads its full dataset client-side, so .rows() already has every
        // row's data regardless of pagination. DataTables caches each row's rendered
        // <td> nodes after the first draw and won't re-invoke the column's render()
        // on a plain draw() — invalidate('data') clears that cache so the checkbox
        // column re-renders (picking up the updated selectedProperties Map) both for
        // the currently visible page and for any page the user pages to afterwards.
        $(document).on('change', '#selectAllStaffProperties', function() {
            const checked = $(this).is(':checked');
            if (staffPropertiesTable) {
                staffPropertiesTable.rows().every(function() {
                    const id = this.data().id;
                    if (checked) {
                        selectedProperties.set(id, true);
                    } else {
                        selectedProperties.delete(id);
                    }
                });
                staffPropertiesTable.rows().invalidate('data').draw(false);
            }
            updateStaffPropertiesBulkBar();
        });

        // Per-row reassign — reuses the same Allocate modal the Unallocated tab uses.
        $(document).on('click', '.reassign-property-link', function() {
            const propertyId = $(this).data('property-id');
            fetchAndOpenAllocateModal(propertyId);
        });

        // Open the bulk reassign modal
        $('#reassignSelectedBtn').on('click', function() {
            const count = selectedProperties.size;
            if (count === 0) return;

            $('#bulkReassignCount').text(count);
            $('#bulkReassignForm .invalid-feedback').text('').hide();
            $('#bulkReassignForm .form-select').removeClass('is-invalid');

            loadStaffOptions().then(function(staffList) {
                populateStaffSelect($('#bulk_reassign_field_officer_id'), staffList, null, 'Select field officer');
                $('#bulk_reassign_field_officer_id').select2({
                    placeholder: 'Select field officer',
                    allowClear: true,
                    dropdownParent: $('#bulkReassignModal')
                });
                $('#bulkReassignModal').modal('show');
            }, function() {
                toastr.error('Failed to load staff list');
            });
        });

        // Submit bulk reassignment
        $('#bulkReassignForm').submit(function(e) {
            e.preventDefault();

            const fieldOfficerId = $('#bulk_reassign_field_officer_id').val();
            if (!fieldOfficerId) {
                $('#bulk_reassign_field_officer_id').addClass('is-invalid');
                return;
            }

            const $btn = $('#saveBulkReassignBtn');
            const $icon = $btn.find('.material-symbols-outlined');
            const origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Reassigning...');
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ route('ajax.zoneAssignments.bulkReassign') }}",
                method: 'POST',
                data: {
                    property_ids: Array.from(selectedProperties.keys()),
                    field_officer_id: fieldOfficerId
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);

                    $('#bulkReassignModal').modal('hide');
                    toastr.success(response.message);

                    selectedProperties = new Map();
                    updateStaffPropertiesBulkBar();

                    if ($.fn.DataTable.isDataTable('#staffPropertiesTable')) {
                        $('#staffPropertiesTable').DataTable().ajax.reload(null, false);
                    }
                    if ($.fn.DataTable.isDataTable('#unallocatedPropertiesTable')) {
                        $('#unallocatedPropertiesTable').DataTable().ajax.reload(null, false);
                    }
                    if ($.fn.DataTable.isDataTable('#fieldOfficersTable')) {
                        $('#fieldOfficersTable').DataTable().ajax.reload(null, false);
                    }
                    if ($.fn.DataTable.isDataTable('#zoneManagersTable')) {
                        $('#zoneManagersTable').DataTable().ajax.reload(null, false);
                    }
                    if ($.fn.DataTable.isDataTable('#zoneSupervisorsTable')) {
                        $('#zoneSupervisorsTable').DataTable().ajax.reload(null, false);
                    }
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    toastr.error(xhr.responseJSON?.message || xhr.responseJSON?.error || 'Failed to reassign properties');
                }
            });
        });

        // ===== Allocate Property modal =====
        let staffOptionsCache = null;

        function loadStaffOptions() {
            if (staffOptionsCache) {
                return $.Deferred().resolve(staffOptionsCache).promise();
            }
            return $.ajax({
                url: "{{ url('/ajax/hr/staff/list') }}",
                type: 'GET',
                dataType: 'json',
                data: {
                    position_slug: 'field_officer'
                }
            }).then(function(response) {
                staffOptionsCache = response.staff || [];
                return staffOptionsCache;
            });
        }

        function populateStaffSelect($select, staffList, selectedId, placeholder) {
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            $select.empty().append($('<option>', { value: '', text: placeholder }));
            staffList.forEach(function(s) {
                $select.append($('<option>', { value: s.id, text: s.full_names }));
            });
            $select.val(selectedId || '');
            $select.select2({
                placeholder: placeholder,
                allowClear: true,
                dropdownParent: $('#allocatePropertyModal')
            });
        }

        function openAllocateModal(data) {
            $('#allocate_property_id').val(data.id);
            $('#allocate_property_name').text(data.property_name || '-');
            $('#allocate_property_zone').text(data.zone_name ? ('Zone: ' + data.zone_name) : '');
            $('#allocate_zone_manager_name').text(data.zone_manager_name || '-');
            $('#allocate_zone_supervisor_name').text(data.zone_supervisor_name || '-');
            $('#allocatePropertyForm .invalid-feedback').text('').hide();
            $('#allocatePropertyForm .form-select').removeClass('is-invalid');

            $('#allocatePropertyModal').modal('show');

            loadStaffOptions().then(function(staffList) {
                populateStaffSelect($('#allocate_field_officer_id'), staffList, data.field_officer_id, 'Select field officer');
            }, function() {
                toastr.error('Failed to load staff list');
            });
        }

        // Fetch a property's current allocation (with zone-level manager/supervisor
        // defaults resolved server-side) and open the Allocate modal for it.
        function fetchAndOpenAllocateModal(propertyId) {
            $.ajax({
                url: "{{ url('/ajax/hr/zone/assignments/property-details') }}/" + propertyId,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    openAllocateModal(response.data);
                },
                error: function() {
                    toastr.error('Failed to load property details');
                }
            });
        }

        // Allocate from the Unallocated table row
        $(document).on('click', '.allocate-property-link', function() {
            fetchAndOpenAllocateModal($(this).data('property-id'));
        });

        // ===== Property search box (borrowed from /tenants/statements) =====
        $('#unallocated_search_property_btn').on('click', function() {
            const searchTerm = $('#unallocated_property_search_input').val().trim();
            if (searchTerm.length < 2) {
                toastr.warning('Please enter at least 2 characters to search');
                return;
            }

            $('#unallocated_search_property_icon').hide();
            $('#unallocated_search_property_spinner').show();
            $('#unallocated_search_property_btn').prop('disabled', true);

            $.ajax({
                url: "{{ url('/ajax/statements/properties') }}",
                method: 'GET',
                data: {
                    search: searchTerm
                },
                success: function(response) {
                    if (response.success && response.properties && response.properties.length > 0) {
                        $('#unallocated_property_select').empty().append('<option value="">-- Select Property --</option>');
                        $.each(response.properties, function(index, prop) {
                            $('#unallocated_property_select').append(
                                `<option value="${prop.id}">${prop.property_name} - ${prop.reference_number || ''}</option>`
                            );
                        });
                        $('#unallocated_property_select').prop('disabled', false);
                        toastr.success(`Found ${response.properties.length} property(ies)`);
                    } else {
                        toastr.info('No properties found');
                        $('#unallocated_property_select').empty().append('<option value="">-- No Results --</option>').prop('disabled', true);
                    }
                },
                error: function() {
                    toastr.error('Error searching properties');
                },
                complete: function() {
                    $('#unallocated_search_property_icon').show();
                    $('#unallocated_search_property_spinner').hide();
                    $('#unallocated_search_property_btn').prop('disabled', false);
                }
            });
        });
        $('#unallocated_property_search_input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#unallocated_search_property_btn').click();
            }
        });

        // Property selected from search results → load its current allocation and open the modal
        $('#unallocated_property_select').on('change', function() {
            const propertyId = $(this).val();
            if (!propertyId) return;
            fetchAndOpenAllocateModal(propertyId);
        });

        // Submit allocation
        $('#allocatePropertyForm').submit(function(e) {
            e.preventDefault();

            const propertyId = $('#allocate_property_id').val();
            const $btn = $('#saveAllocationBtn');
            const $icon = $btn.find('.material-symbols-outlined');
            const origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Saving...');
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ url('/ajax/hr/zone/assignments/allocate') }}/" + propertyId,
                method: 'POST',
                data: $(this).serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);

                    $('#allocatePropertyModal').modal('hide');
                    toastr.success(response.message);

                    unallocatedTable.ajax.reload(null, false);
                    fieldOfficersTable.ajax.reload(null, false);
                    zoneManagersTable.ajax.reload(null, false);
                    zoneSupervisorsTable.ajax.reload(null, false);
                    zoneAuditorsTable.ajax.reload(null, false);
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);

                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors;
                        $('#allocatePropertyForm .form-select').removeClass('is-invalid');
                        $('#allocatePropertyForm .invalid-feedback').text('').hide();

                        $.each(errors, function(field, messages) {
                            const $input = $(`#allocatePropertyForm [name="${field}"]`);
                            $input.addClass('is-invalid');
                            $input.siblings('.invalid-feedback').text(messages[0]).show();
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.error || xhr.responseJSON?.message || 'Failed to allocate property');
                    }
                }
            });
        });
    });
</script>
@endsection