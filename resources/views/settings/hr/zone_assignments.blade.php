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
                                <button class="nav-link active" id="field-officers-tab" data-bs-toggle="tab" data-bs-target="#field-officers-pane" type="button">
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
                                <button class="nav-link" id="property-managers-tab" data-bs-toggle="tab" data-bs-target="#property-managers-pane" type="button">
                                    <span class="material-symbols-outlined" style="font-size:18px;">badge</span>
                                    Property Managers
                                </button>
                            </li>
                        </ul>

                        <!-- Tab panes -->
                        <div class="tab-content" id="zoneAssignmentsTabContent">
                            <div class="tab-pane fade show active" id="field-officers-pane" role="tabpanel">
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
                <div class="table-responsive custom-scrollbar">
                    <table id="staffPropertiesTable" class="display" style="width:100%;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Property Name</th>
                                <th>Reference Number</th>
                                <th>Client Name</th>
                                <th>Zone</th>
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
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<style>
    .staff-name-link {
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

        loadReportTable('#fieldOfficersTable', "{{ url('/ajax/hr/zone/assignments/field-officers') }}", [
            rowNumberColumn,
            nameLinkColumn('field_officer'),
            { data: 'mobile_number', render: data => data || '-' },
            { data: 'email_address', render: data => data || '-' },
            { data: 'properties_count', render: data => (typeof data === 'number' ? data : parseInt(data) || 0) },
            detailsColumn('field_officer')
        ]);

        loadReportTable('#zoneManagersTable', "{{ url('/ajax/hr/zone/assignments/zone-managers') }}", [
            rowNumberColumn,
            nameLinkColumn('zone_manager'),
            { data: 'mobile_number', render: data => data || '-' },
            { data: 'email_address', render: data => data || '-' },
            { data: 'properties_count', render: data => (typeof data === 'number' ? data : parseInt(data) || 0) },
            { data: 'field_officers_count', render: data => (typeof data === 'number' ? data : parseInt(data) || 0) },
            detailsColumn('zone_manager')
        ]);

        loadReportTable('#zoneSupervisorsTable', "{{ url('/ajax/hr/zone/assignments/zone-supervisors') }}", [
            rowNumberColumn,
            nameLinkColumn('zone_supervisor'),
            { data: 'mobile_number', render: data => data || '-' },
            { data: 'email_address', render: data => data || '-' },
            { data: 'properties_count', render: data => (typeof data === 'number' ? data : parseInt(data) || 0) },
            detailsColumn('zone_supervisor')
        ]);

        loadReportTable('#propertyManagersTable', "{{ url('/ajax/hr/zone/assignments/property-managers') }}", [
            rowNumberColumn,
            { data: 'full_names', render: data => data || '-' },
            { data: 'mobile_number', render: data => data || '-' },
            { data: 'email_address', render: data => data || '-' },
            { data: 'position_name', render: data => data || '-' }
        ]);

        // ===== Properties drill-down modal (Field Officers / Zone Managers / Zone Supervisors) =====
        function openStaffPropertiesModal(type, staffId, staffName) {
            $('#staffPropertiesModalLabel').text('Properties — ' + (staffName || ''));
            $('#staffPropertiesModal').modal('show');

            if ($.fn.DataTable.isDataTable('#staffPropertiesTable')) {
                $('#staffPropertiesTable').DataTable().destroy();
                $('#staffPropertiesTable tbody').empty();
            }

            $('#staffPropertiesTable').DataTable({
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
                columns: [
                    rowNumberColumn,
                    { data: 'property_name', render: data => data || '-' },
                    { data: 'reference_number', render: data => data || '-' },
                    { data: 'client_name', render: data => data || '-' },
                    { data: 'zone_name', render: data => data || '-' }
                ],
                paging: true,
                pageLength: 10,
                searching: true,
                info: true,
                lengthChange: true
            });
        }

        $(document).on('click', '.staff-name-link, .staff-details-btn', function() {
            const type = $(this).data('type');
            const staffId = $(this).data('staff-id');
            const staffName = $(this).data('staff-name');
            openStaffPropertiesModal(type, staffId, staffName);
        });

        $('#staffPropertiesModal').on('hidden.bs.modal', function() {
            if ($.fn.DataTable.isDataTable('#staffPropertiesTable')) {
                $('#staffPropertiesTable').DataTable().destroy();
                $('#staffPropertiesTable tbody').empty();
            }
        });
    });
</script>
