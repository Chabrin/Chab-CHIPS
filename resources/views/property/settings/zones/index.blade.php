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
                        <div class="alert alert-info py-2 px-3" style="font-size:0.85rem;">
                            <strong>What you can do here:</strong> use <em>Add Zone</em> to create a zone (just a name).
                            For each zone, use the <span class="material-symbols-outlined align-middle" style="font-size:15px;">manage_accounts</span>
                            <em>Assign Zone Roles</em> action to set or clear its Zone Manager and Zone Supervisor
                            (you can also do this from a staff member's own record under Employees &rarr; Employment Details),
                            and the <span class="material-symbols-outlined align-middle" style="font-size:15px;">group_add</span>
                            <em>Assign Field Officers</em> action to set which staff are stationed in that zone.
                        </div>
                        <div class="table-responsive">
                            <table id="zonesTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Zone Name</th>
                                        <th>Zone Manager</th>
                                        <th>Zone Supervisor</th>
                                        <th>Field Officers</th>
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
                    <div class="alert alert-info py-2 px-3 mb-0" style="font-size:0.85rem;">
                        Zone Manager and Zone Supervisor can be set here via the "Assign Zone Roles" action once the
                        zone is saved, or from the staff member's own record —
                        see <a href="{{ route('staff.index') }}" target="_blank">Employees &rarr; Employment Details</a>.
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

<!-- Assign Field Officers Modal -->
<div class="modal fade" id="fieldOfficersModal" tabindex="-1" aria-labelledby="fieldOfficersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fieldOfficersModalLabel">Assign Field Officers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="fieldOfficersForm">
                <div class="modal-body">
                    <input type="hidden" id="fieldOfficersZoneId" name="zone_id">
                    <div class="mb-3">
                        <label for="fieldOfficersSelect" class="form-label">Field Officers</label>
                        <select class="form-select" id="fieldOfficersSelect" name="staff_ids[]" multiple>
                        </select>
                        <small class="text-muted">Staff already stationed in another zone are shown with their current zone — selecting them here moves them to this zone.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveFieldOfficersBtn">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span class="ms-1">Save</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Zone Roles Modal -->
<div class="modal fade" id="zoneRolesModal" tabindex="-1" aria-labelledby="zoneRolesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="zoneRolesModalLabel">Assign Zone Roles</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="zoneRolesForm">
                <div class="modal-body">
                    <input type="hidden" id="zoneRolesZoneId" name="zone_id">
                    <div class="mb-3">
                        <label for="zoneRolesManagerSelect" class="form-label">Zone Manager</label>
                        <select class="form-select @error('zone_manager_id') is-invalid @enderror" id="zoneRolesManagerSelect" name="zone_manager_id">
                            <option value="">Select zone manager</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="zoneRolesSupervisorSelect" class="form-label">Zone Supervisor</label>
                        <select class="form-select @error('zone_supervisor_id') is-invalid @enderror" id="zoneRolesSupervisorSelect" name="zone_supervisor_id">
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
                    <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveZoneRolesBtn">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span class="ms-1">Save</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
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
                render: data => data && data !== '-' ?
                    `<span class="badge badge-light-success">${data}</span>` :
                    '<span class="text-muted">-</span>'
            },
            {
                data: 'zone_supervisor_name',
                render: data => data && data !== '-' ?
                    `<span class="badge badge-light-warning">${data}</span>` :
                    '<span class="text-muted">-</span>'
            },
            {
                data: 'field_officers',
                orderable: false,
                render: function(officers) {
                    if (!officers || officers.length === 0) {
                        return '<span class="text-muted">-</span>';
                    }
                    return officers.map(o => `<span class="badge badge-light-info">${o.full_names}</span>`).join(' ');
                }
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
                        actions += `<a href="javascript:void(0)" class="text-primary assign-field-officers"
                            data-id="${data.encrypted_id}"
                            data-zone='${JSON.stringify(data)}' title="Assign Field Officers">
                            <span class="material-symbols-outlined" style="font-size:18px;">group_add</span>
                        </a>`;
                        actions += `<a href="javascript:void(0)" class="text-primary assign-zone-roles"
                            data-id="${data.encrypted_id}"
                            data-zone='${JSON.stringify(data)}' title="Assign Zone Roles">
                            <span class="material-symbols-outlined" style="font-size:18px;">manage_accounts</span>
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
        responsive: true
    });

    // ============ ZONE CRUD ============

    // Add Zone
    $('#addZoneBtn').click(function() {
        $('#zoneForm')[0].reset();
        $('#zoneId').val('');
        $('#zoneModalLabel').text('Add Zone');
        $('.invalid-feedback').text('').hide();
        $('.form-control, .form-select').removeClass('is-invalid');
        $('#zoneModal').modal('show');
    });

    // Edit Zone
    $('#zonesTable').on('click', '.edit-zone', function() {
        const data = $(this).data('zone');

        $('#zoneId').val(data.encrypted_id);
        $('#zoneName').val(data.zone_name !== '-' ? data.zone_name : '');

        $('#zoneModalLabel').text('Edit Zone');
        $('.invalid-feedback').text('').hide();
        $('.form-control, .form-select').removeClass('is-invalid');
        $('#zoneModal').modal('show');
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

    // ============ ASSIGN FIELD OFFICERS ============

    // Assign Field Officers
    $('#zonesTable').on('click', '.assign-field-officers', function() {
        const data = $(this).data('zone');
        const currentOfficerIds = (data.field_officers || []).map(o => o.id);

        $('#fieldOfficersZoneId').val(data.encrypted_id);
        $('#fieldOfficersModalLabel').text('Assign Field Officers — ' + data.zone_name);

        const $select = $('#fieldOfficersSelect');
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
        $select.empty();

        $.ajax({
            url: '{{ route("ajax.staff.list") }}',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                (response.staff || []).forEach(function(staff) {
                    const inOtherZone = staff.zone_name && staff.zone_name !== data.zone_name;
                    const label = inOtherZone ? `${staff.full_names} (currently: ${staff.zone_name})` : staff.full_names;
                    const selected = currentOfficerIds.includes(staff.id) ? 'selected' : '';
                    $select.append(`<option value="${staff.id}" ${selected}>${label}</option>`);
                });

                $select.select2({ placeholder: 'Select field officers', width: '100%', dropdownParent: $('#fieldOfficersModal') });
                $('#fieldOfficersModal').modal('show');
            },
            error: function() {
                toastr.error('Failed to load staff.');
            }
        });
    });

    // Submit Field Officers Form
    $('#fieldOfficersForm').submit(function(e) {
        e.preventDefault();

        const zoneId = $('#fieldOfficersZoneId').val();
        const $btn = $('#saveFieldOfficersBtn');
        const $icon = $btn.find('.material-symbols-outlined');
        const origText = $btn.find('span.ms-1').text();
        $icon.addClass('spin');
        $btn.find('span.ms-1').text('Saving...');
        $btn.prop('disabled', true);

        $.ajax({
            url: '{{ route("property.settings.zones.field-officers", "PLACEHOLDER") }}'.replace('PLACEHOLDER', zoneId),
            method: 'POST',
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);

                $('#fieldOfficersModal').modal('hide');
                zonesTable.ajax.reload();
                toastr.success(response.message);
            },
            error: function(xhr) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);
                toastr.error(xhr.responseJSON?.message || 'Failed to assign field officers');
            }
        });
    });

    // ============ ASSIGN ZONE ROLES ============

    // Assign Zone Roles
    $('#zonesTable').on('click', '.assign-zone-roles', function() {
        const data = $(this).data('zone');

        $('#zoneRolesZoneId').val(data.encrypted_id);
        $('#zoneRolesModalLabel').text('Assign Zone Roles — ' + data.zone_name);
        $('.invalid-feedback').text('').hide();
        $('.form-select').removeClass('is-invalid');

        const $manager = $('#zoneRolesManagerSelect');
        const $supervisor = $('#zoneRolesSupervisorSelect');

        [$manager, $supervisor].forEach($select => {
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
        });

        $.ajax({
            url: '{{ route("ajax.staff.list") }}',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $manager.empty().append($('<option>', { value: '', text: 'Select zone manager' }));
                $supervisor.empty().append($('<option>', { value: '', text: 'Select zone supervisor' }));

                (response.staff || []).forEach(function(staff) {
                    $manager.append($('<option>', { value: staff.id, text: staff.full_names }));
                    $supervisor.append($('<option>', { value: staff.id, text: staff.full_names }));
                });

                $manager.val(data.zone_manager_id || '');
                $supervisor.val(data.zone_supervisor_id || '');

                $manager.select2({ placeholder: 'Select zone manager', allowClear: true, width: '100%', dropdownParent: $('#zoneRolesModal') });
                $supervisor.select2({ placeholder: 'Select zone supervisor', allowClear: true, width: '100%', dropdownParent: $('#zoneRolesModal') });

                $('#zoneRolesModal').modal('show');
            },
            error: function() {
                toastr.error('Failed to load staff.');
            }
        });
    });

    // Submit Zone Roles Form
    $('#zoneRolesForm').submit(function(e) {
        e.preventDefault();

        const zoneId = $('#zoneRolesZoneId').val();
        const $btn = $('#saveZoneRolesBtn');
        const $icon = $btn.find('.material-symbols-outlined');
        const origText = $btn.find('span.ms-1').text();
        $icon.addClass('spin');
        $btn.find('span.ms-1').text('Saving...');
        $btn.prop('disabled', true);

        $.ajax({
            url: '{{ route("property.settings.zones.roles", "PLACEHOLDER") }}'.replace('PLACEHOLDER', zoneId),
            method: 'POST',
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);

                $('#zoneRolesModal').modal('hide');
                zonesTable.ajax.reload();
                toastr.success(response.message);
            },
            error: function(xhr) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);

                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    $('.form-select').removeClass('is-invalid');
                    $('.invalid-feedback').text('').hide();

                    $.each(errors, function(field, messages) {
                        const input = $(`[name="${field}"]`);
                        input.addClass('is-invalid');
                        input.siblings('.invalid-feedback').text(messages[0]).show();
                    });
                } else {
                    toastr.error(xhr.responseJSON?.message || 'Failed to assign zone roles');
                }
            }
        });
    });
});
</script>
@endsection
