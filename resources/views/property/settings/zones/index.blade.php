@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Zones Management</h5>
                        @permission('create_property_settings_items')
                        <div class="d-flex gap-2">
                            <button type="button" id="addZoneBtn" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span>
                                <span class="ms-1">Add Zone</span>
                            </button>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body custom-scrollbar">
                        <p class="text-muted small mb-2">Click a zone row to filter its areas in the table below.</p>
                        <div class="table-responsive">
                            <table id="zonesTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Zone Name</th>
                                        <th>Zone Manager</th>
                                        <th>Zone Supervisor</th>
                                        <th style="width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="mb-0">Zone Areas Management</h5>
                            <span id="zoneAreaFilterLabel" class="badge bg-light-primary text-primary" style="display:none;"></span>
                            <a href="javascript:void(0)" id="clearZoneAreaFilter" class="small" style="display:none;">Clear filter</a>
                        </div>
                        @permission('create_property_settings_items')
                        <div class="d-flex gap-2">
                            <button type="button" id="addZoneAreaBtn" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span>
                                <span class="ms-1">Add Area</span>
                            </button>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body custom-scrollbar">
                        <div class="table-responsive">
                            <table id="zoneAreasTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Area Name</th>
                                        <th>Zone</th>
                                        <th style="width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Zone Modal -->
<div class="modal fade" id="zoneModal" tabindex="-1" aria-labelledby="zoneModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="zoneModalLabel">Add Zone</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="zoneForm">
                <div class="modal-body">
                    <input type="hidden" id="zoneId" name="id">
                    <div class="mb-3">
                        <label for="zoneName" class="form-label">Zone Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="zoneName" name="zone_name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="zoneManagerSelect" class="form-label">Zone Manager</label>
                        <select class="form-select" id="zoneManagerSelect" name="zone_manager_id">
                            <option value="">Select zone manager</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="zoneSupervisorSelect" class="form-label">Zone Supervisor</label>
                        <select class="form-select" id="zoneSupervisorSelect" name="zone_supervisor_id">
                            <option value="">Select zone supervisor</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveZoneBtn">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span class="ms-1">Save</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Zone Area Modal -->
<div class="modal fade" id="zoneAreaModal" tabindex="-1" aria-labelledby="zoneAreaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="zoneAreaModalLabel">Add Area</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="zoneAreaForm">
                <div class="modal-body">
                    <input type="hidden" id="zoneAreaId" name="id">
                    <div class="mb-3">
                        <label for="zoneAreaZoneSelect" class="form-label">Zone <span class="text-danger">*</span></label>
                        <select class="form-select" id="zoneAreaZoneSelect" name="zone_id" required>
                            <option value="">Select zone</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="zoneAreaName" class="form-label">Area Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="zoneAreaName" name="area_name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveZoneAreaBtn">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span class="ms-1">Save</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    #zonesTable tbody tr { cursor: pointer; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    let selectedZoneId = null;

    // ============ ZONES TABLE ============
    const zonesTable = $('#zonesTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '{{ route("property.settings.zones.data") }}',
            type: 'GET',
            dataSrc: 'data',
            error: function(xhr) {
                console.error('Error loading zones:', xhr);
            }
        },
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                render: (data, type, row, meta) => meta.row + 1
            },
            { data: 'zone_name' },
            {
                data: 'zone_manager_name',
                render: data => data && data !== '-' ? data : '<span class="text-muted">-</span>'
            },
            {
                data: 'zone_supervisor_name',
                render: data => data && data !== '-' ? data : '<span class="text-muted">-</span>'
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data) {
                    let actions = '<div class="d-flex flex-row gap-1">';
                    if (data.can_update) {
                        actions += `<a href="javascript:void(0)" class="text-primary edit-zone"
                            data-id="${data.encrypted_id}"
                            data-zone='${JSON.stringify(data)}' title="Edit">
                            <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                        </a>`;
                    }
                    if (data.can_delete) {
                        actions += `<a href="javascript:void(0)" class="text-primary delete-zone"
                            data-id="${data.encrypted_id}"
                            data-name="${data.zone_name}" title="Delete">
                            <span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>
                        </a>`;
                    }
                    actions += '</div>';
                    return actions;
                }
            }
        ],
        order: [[1, 'asc']],
        pageLength: 10,
        responsive: true,
        drawCallback: function() {
            if (!selectedZoneId) return;
            this.api().rows().every(function() {
                if (String(this.data().id) === String(selectedZoneId)) {
                    $(this.node()).addClass('table-active');
                }
            });
        }
    });

    // Filter the Zone Areas table to the clicked zone; clicking the same zone again clears it.
    $('#zonesTable tbody').on('click', 'tr', function(e) {
        if ($(e.target).closest('a').length) return;

        const rowData = zonesTable.row(this).data();
        if (!rowData) return;

        const alreadySelected = String(selectedZoneId) === String(rowData.id);
        $('#zonesTable tbody tr').removeClass('table-active');

        if (alreadySelected) {
            selectedZoneId = null;
            $('#zoneAreaFilterLabel, #clearZoneAreaFilter').hide();
        } else {
            selectedZoneId = rowData.id;
            $(this).addClass('table-active');
            $('#zoneAreaFilterLabel').text(`Areas for: ${rowData.zone_name}`).show();
            $('#clearZoneAreaFilter').show();
        }

        zoneAreasTable.ajax.reload();
    });

    $('#clearZoneAreaFilter').click(function() {
        selectedZoneId = null;
        $('#zonesTable tbody tr').removeClass('table-active');
        $('#zoneAreaFilterLabel, #clearZoneAreaFilter').hide();
        zoneAreasTable.ajax.reload();
    });

    // ============ ZONE CRUD ============

    // Loads the staff list into the Zone Manager / Zone Supervisor selects,
    // each filtered to staff whose position slug matches (sys_set_staff_positions.slug_name
    // = 'zone_manager' / 'zone_supervisor'), preselecting the given ids (used by both Add
    // and Edit). If the zone's currently assigned manager/supervisor no longer holds a
    // matching position, they're added back in as an extra option (labeled "current") so
    // saving the form without touching that field doesn't silently wipe the assignment.
    function loadZoneRoleSelects(managerId, supervisorId, managerName, supervisorName) {
        const $manager = $('#zoneManagerSelect');
        const $supervisor = $('#zoneSupervisorSelect');

        [$manager, $supervisor].forEach($select => {
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
        });

        const managersRequest = $.ajax({
            url: '{{ route("ajax.staff.list") }}',
            type: 'GET',
            dataType: 'json',
            data: { position_slug: 'zone_manager' }
        });
        const supervisorsRequest = $.ajax({
            url: '{{ route("ajax.staff.list") }}',
            type: 'GET',
            dataType: 'json',
            data: { position_slug: 'zone_supervisor' }
        });

        return $.when(managersRequest, supervisorsRequest).then(function(managersResp, supervisorsResp) {
            const managers = managersResp[0].staff || [];
            const supervisors = supervisorsResp[0].staff || [];

            $manager.empty().append($('<option>', { value: '', text: 'Select zone manager' }));
            managers.forEach(function(staff) {
                $manager.append($('<option>', { value: staff.id, text: staff.full_names }));
            });
            if (managerId && !managers.some(s => String(s.id) === String(managerId)) && managerName && managerName !== '-') {
                $manager.append($('<option>', { value: managerId, text: managerName + ' (current)' }));
            }

            $supervisor.empty().append($('<option>', { value: '', text: 'Select zone supervisor' }));
            supervisors.forEach(function(staff) {
                $supervisor.append($('<option>', { value: staff.id, text: staff.full_names }));
            });
            if (supervisorId && !supervisors.some(s => String(s.id) === String(supervisorId)) && supervisorName && supervisorName !== '-') {
                $supervisor.append($('<option>', { value: supervisorId, text: supervisorName + ' (current)' }));
            }

            $manager.val(managerId || '');
            $supervisor.val(supervisorId || '');

            $manager.select2({ placeholder: 'Select zone manager', allowClear: true, width: '100%', dropdownParent: $('#zoneModal') });
            $supervisor.select2({ placeholder: 'Select zone supervisor', allowClear: true, width: '100%', dropdownParent: $('#zoneModal') });
        }, function() {
            toastr.error('Failed to load staff list');
        });
    }

    // Add Zone
    $('#addZoneBtn').click(function() {
        $('#zoneForm')[0].reset();
        $('#zoneId').val('');
        $('#zoneModalLabel').text('Add Zone');
        $('.invalid-feedback').text('').hide();
        $('.form-control, .form-select').removeClass('is-invalid');

        loadZoneRoleSelects(null, null).then(function() {
            $('#zoneModal').modal('show');
        });
    });

    // Edit Zone
    $('#zonesTable').on('click', '.edit-zone', function() {
        const data = $(this).data('zone');

        $('#zoneId').val(data.encrypted_id);
        $('#zoneName').val(data.zone_name !== '-' ? data.zone_name : '');

        $('#zoneModalLabel').text('Edit Zone');
        $('.invalid-feedback').text('').hide();
        $('.form-control, .form-select').removeClass('is-invalid');

        loadZoneRoleSelects(data.zone_manager_id, data.zone_supervisor_id, data.zone_manager_name, data.zone_supervisor_name).then(function() {
            $('#zoneModal').modal('show');
        });
    });

    // Submit Zone Form
    $('#zoneForm').submit(function(e) {
        e.preventDefault();

        const id = $('#zoneId').val();
        const url = id
            ? '{{ route("property.settings.zones.update", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id)
            : '{{ route("property.settings.zones.store") }}';
        const method = 'POST';

        const $btn = $('#saveZoneBtn');
        const $icon = $btn.find('.material-symbols-outlined');
        const origText = $btn.find('span.ms-1').text();
        $icon.addClass('spin');
        $btn.find('span.ms-1').text('Saving...');
        $btn.prop('disabled', true);

        $.ajax({
            url: url,
            method: method,
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);

                $('#zoneModal').modal('hide');
                zonesTable.ajax.reload();
                toastr.success(response.message);
            },
            error: function(xhr) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);

                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    $('.form-control, .form-select').removeClass('is-invalid');
                    $('.invalid-feedback').text('').hide();

                    $.each(errors, function(field, messages) {
                        const input = $(`[name="${field}"]`);
                        input.addClass('is-invalid');
                        input.siblings('.invalid-feedback').text(messages[0]).show();
                    });
                } else {
                    toastr.error(xhr.responseJSON?.message || 'An error occurred');
                }
            }
        });
    });

    // Delete Zone
    $('#zonesTable').on('click', '.delete-zone', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        Swal.fire({
            title: 'Delete zone?',
            text: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: '{{ route("property.settings.zones.delete", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id),
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    zonesTable.ajax.reload();
                    toastr.success(response.message);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to delete zone');
                }
            });
        });
    });

    // ============ ZONE AREAS TABLE ============
    const zoneAreasTable = $('#zoneAreasTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: function(data, callback) {
            if (!selectedZoneId) {
                callback({ data: [] });
                return;
            }
            $.ajax({
                url: '{{ route("property.settings.zone-areas.data") }}',
                type: 'GET',
                dataType: 'json',
                data: { zone_id: selectedZoneId },
                success: callback,
                error: function(xhr) {
                    console.error('Error loading zone areas:', xhr);
                    callback({ data: [] });
                }
            });
        },
        language: {
            emptyTable: 'Select a zone above to view its areas.'
        },
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                render: (data, type, row, meta) => meta.row + 1
            },
            { data: 'area_name' },
            { data: 'zone_name' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data) {
                    let actions = '<div class="d-flex flex-row gap-1">';
                    if (data.can_update) {
                        actions += `<a href="javascript:void(0)" class="text-primary edit-zone-area"
                            data-id="${data.encrypted_id}"
                            data-area='${JSON.stringify(data)}' title="Edit">
                            <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                        </a>`;
                    }
                    if (data.can_delete) {
                        actions += `<a href="javascript:void(0)" class="text-primary delete-zone-area"
                            data-id="${data.encrypted_id}"
                            data-name="${data.area_name}" title="Delete">
                            <span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>
                        </a>`;
                    }
                    actions += '</div>';
                    return actions;
                }
            }
        ],
        order: [[1, 'asc']],
        pageLength: 10,
        responsive: true
    });

    // ============ ZONE AREA CRUD ============

    // Loads all zones into the zone select, preselecting the given id (used by both Add and Edit).
    function loadZoneAreaZoneSelect(zoneId) {
        const $zoneSelect = $('#zoneAreaZoneSelect');

        if ($zoneSelect.hasClass('select2-hidden-accessible')) {
            $zoneSelect.select2('destroy');
        }

        return $.ajax({
            url: '{{ route("property.settings.zones.data") }}',
            type: 'GET',
            dataType: 'json'
        }).then(function(response) {
            const zones = response.data || [];

            $zoneSelect.empty().append($('<option>', { value: '', text: 'Select zone' }));
            zones.forEach(function(zone) {
                $zoneSelect.append($('<option>', { value: zone.id, text: zone.zone_name }));
            });

            $zoneSelect.val(zoneId || '');
            $zoneSelect.select2({ placeholder: 'Select zone', width: '100%', dropdownParent: $('#zoneAreaModal') });
        }, function() {
            toastr.error('Failed to load zones list');
        });
    }

    // Add Zone Area
    $('#addZoneAreaBtn').click(function() {
        $('#zoneAreaForm')[0].reset();
        $('#zoneAreaId').val('');
        $('#zoneAreaModalLabel').text('Add Area');
        $('.invalid-feedback').text('').hide();
        $('.form-control, .form-select').removeClass('is-invalid');

        loadZoneAreaZoneSelect(null).then(function() {
            $('#zoneAreaModal').modal('show');
        });
    });

    // Edit Zone Area
    $('#zoneAreasTable').on('click', '.edit-zone-area', function() {
        const data = $(this).data('area');

        $('#zoneAreaId').val(data.encrypted_id);
        $('#zoneAreaName').val(data.area_name !== '-' ? data.area_name : '');

        $('#zoneAreaModalLabel').text('Edit Area');
        $('.invalid-feedback').text('').hide();
        $('.form-control, .form-select').removeClass('is-invalid');

        loadZoneAreaZoneSelect(data.zone_id).then(function() {
            $('#zoneAreaModal').modal('show');
        });
    });

    // Submit Zone Area Form
    $('#zoneAreaForm').submit(function(e) {
        e.preventDefault();

        const id = $('#zoneAreaId').val();
        const url = id
            ? '{{ route("property.settings.zone-areas.update", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id)
            : '{{ route("property.settings.zone-areas.store") }}';
        const method = 'POST';

        const $btn = $('#saveZoneAreaBtn');
        const $icon = $btn.find('.material-symbols-outlined');
        const origText = $btn.find('span.ms-1').text();
        $icon.addClass('spin');
        $btn.find('span.ms-1').text('Saving...');
        $btn.prop('disabled', true);

        $.ajax({
            url: url,
            method: method,
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);

                $('#zoneAreaModal').modal('hide');
                zoneAreasTable.ajax.reload();
                toastr.success(response.message);
            },
            error: function(xhr) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);

                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    $('.form-control, .form-select').removeClass('is-invalid');
                    $('.invalid-feedback').text('').hide();

                    $.each(errors, function(field, messages) {
                        const input = $(`[name="${field}"]`);
                        input.addClass('is-invalid');
                        input.siblings('.invalid-feedback').text(messages[0]).show();
                    });
                } else {
                    toastr.error(xhr.responseJSON?.message || 'An error occurred');
                }
            }
        });
    });

    // Delete Zone Area
    $('#zoneAreasTable').on('click', '.delete-zone-area', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        Swal.fire({
            title: 'Delete area?',
            text: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: '{{ route("property.settings.zone-areas.delete", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id),
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    zoneAreasTable.ajax.reload();
                    toastr.success(response.message);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to delete zone area');
                }
            });
        });
    });

});
</script>
@endsection
