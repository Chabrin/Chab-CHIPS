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
                        <label for="zoneManagerId" class="form-label">Zone Manager</label>
                        <select class="form-select" id="zoneManagerId" name="zone_manager_id">
                            <option value="">Select zone manager</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="zoneSupervisorId" class="form-label">Zone Supervisor</label>
                        <select class="form-select" id="zoneSupervisorId" name="zone_supervisor_id">
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    let usersLoaded = false;

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
                    `<span class="d-flex align-items-center gap-1"><span class="material-symbols-outlined" style="font-size:16px;">supervisor_account</span>${data}</span>` :
                    '<span class="text-muted">-</span>'
            },
            {
                data: 'zone_supervisor_name',
                render: data => data && data !== '-' ?
                    `<span class="d-flex align-items-center gap-1"><span class="material-symbols-outlined" style="font-size:16px;">manage_accounts</span>${data}</span>` :
                    '<span class="text-muted">-</span>'
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
        responsive: true
    });

    // ============ ZONE CRUD ============

    // Load zone manager / supervisor users (josya_users) into both selects
    function loadZoneUsers(selectedManagerId, selectedSupervisorId) {
        return $.ajax({
            url: '{{ route("property.settings.zones.users") }}',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                const $manager = $('#zoneManagerId');
                const $supervisor = $('#zoneSupervisorId');

                [$manager, $supervisor].forEach($select => {
                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }
                });

                $manager.empty().append($('<option>', { value: '', text: 'Select zone manager' }));
                $supervisor.empty().append($('<option>', { value: '', text: 'Select zone supervisor' }));

                (response.data || []).forEach(function(user) {
                    $manager.append($('<option>', { value: user.id, text: user.name || user.username }));
                    $supervisor.append($('<option>', { value: user.id, text: user.name || user.username }));
                });

                $manager.val(selectedManagerId || '');
                $supervisor.val(selectedSupervisorId || '');

                $manager.select2({ placeholder: 'Select zone manager', allowClear: true, dropdownParent: $('#zoneModal') });
                $supervisor.select2({ placeholder: 'Select zone supervisor', allowClear: true, dropdownParent: $('#zoneModal') });

                usersLoaded = true;
            },
            error: function() {
                toastr.error('Failed to load users.');
            }
        });
    }

    // Add Zone
    $('#addZoneBtn').click(function() {
        $('#zoneForm')[0].reset();
        $('#zoneId').val('');
        $('#zoneModalLabel').text('Add Zone');
        $('.invalid-feedback').text('').hide();
        $('.form-control, .form-select').removeClass('is-invalid');
        $('#zoneModal').modal('show');
        loadZoneUsers(null, null);
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
        loadZoneUsers(data.zone_manager_id, data.zone_supervisor_id);
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
});
</script>
@endsection
