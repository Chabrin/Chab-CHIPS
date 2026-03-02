@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Zone Assignments Settings</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_zone_assignments')
                                <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addZoneAssignmentModal">
                                    <span class="material-symbols-outlined">add_circle</span>
                                    <span class="ms-1">Add Zone Assignment</span>
                                </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="zone-assignments-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Zone</th>
                                        <th>Assigned As</th>
                                        <th>Staff</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Date/Time</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data loaded via DataTables AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Add/Edit Zone Assignment Modal -->
<div class="modal fade" id="addZoneAssignmentModal" tabindex="-1" aria-labelledby="addZoneAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addZoneAssignmentModalLabel">Zone Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="zoneAssignmentForm">
                    <input type="hidden" name="id" id="zoneAssignmentId">
                    <div class="mb-3">
                        <label for="zone_id" class="form-label">Zone <span style="color:red">*</span></label>
                        <select class="form-select" name="zone_id" id="zone_id" required>
                            <!-- Populate dynamically -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="assigned_as" class="form-label">Assigned As <span style="color:red">*</span></label>
                        <select class="form-select" name="assigned_as" id="assigned_as" required>
                            <!-- Populate dynamically -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="staff_id" class="form-label">Staff <span style="color:red">*</span></label>
                        <select class="form-select" name="staff_id" id="staff_id" required>
                            <!-- Populate dynamically -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="properties_ids" class="form-label">Zone Properties</label>
                        <select class="form-select" name="properties_ids[]" id="properties_ids" multiple>
                            <!-- Populate dynamically -->
                        </select>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        @permission('create_zone_assignments')
                        <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="saveZoneAssignmentBtn">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Save</span>
                        </button>
                        @endpermission
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        let table = $('#zone-assignments-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/hr/zone/assignments/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'zone_assignments'
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
                { data: 'zone', title: 'Zone', render: data => data && data.zone_name ? data.zone_name : '-' },
                { data: 'assignment_type', title: 'Assigned As', render: data => data && data.type_name ? data.type_name : '-'  },
                { data: 'staff', title: 'Staff', render: data => data && data.name ? data.name : '-' },
                // { data: 'fo_ids', title: 'FO IDs' },
                // { data: 'properties_ids', title: 'Properties IDs' },
                { data: 'current_status_id', title: 'Status', render: data => data == 5 ? 'Active' : 'Pending' },
                { data: 'created_by', title: 'Created By', render: data => data && data.name ? data.name : '-' },
                { data: 'date_time', title: 'Date/Time', render: data => data ? new Date(data).toLocaleString() : '-' },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        if (row.can_update) {
                            buttons += `
                                <button class="btn btn-xs btn-light edit-zone-assignment" 
                                    data-id="${row.id}" 
                                    data-zone_id="${row.zone && row.zone.id ? row.zone.id : ''}" 
                                    data-assigned_as="${row.assignment_type && row.assignment_type.id ? row.assignment_type.id : ''}" 
                                    data-staff_id="${row.staff && row.staff.id ? row.staff.id : ''}" 
                                    data-fo_ids="${row.fo_ids ? row.fo_ids : ''}" 
                                    data-properties_ids="${row.properties_ids ? row.properties_ids : ''}" 
                                    data-current_status_id="${row.current_status_id}" 
                                    style="padding:1px 2px;">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </button>`;
                        }
                        if (row.can_delete) {
                            buttons += `
                                <button class="btn btn-xs btn-light delete-zone-assignment" 
                                    data-id="${row.id}" style="padding:1px 2px;">
                                    <span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>
                                    <span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>
                                </button>`;
                        }
                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true
        });

        $('#addZoneAssignmentModal').on('show.bs.modal', function () {
            // Populate staff select
            $.ajax({
                url: "{{ url('/ajax/hr/staff/list') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let $staff_id = $('#staff_id');
                    $staff_id.empty();
                    $staff_id.append($('<option>', {
                        value: '',
                        text: 'Select staff',
                        disabled: true,
                        selected: true
                    }));
                    if (response.staff && Array.isArray(response.staff)) {
                        response.staff.forEach(function(staff) {
                            $staff_id.append($('<option>', {
                                value: staff.id,
                                text: staff.full_names
                            }));
                        });
                    }

                    // Check if Select2 is already initialized and destroy it
                    if ($staff_id.hasClass("select2-hidden-accessible")) {
                        $staff_id.select2('destroy');
                    }
                    
                    // Initialize Select2
                    $staff_id.select2({
                        placeholder: 'Select Staff',
                        allowClear: true
                    });
                },
                error: function() {
                    toastr.error('Failed to load staff.');
                }
            });
        
            // Populate assigned_as select
            $.ajax({
                url: "{{ url('/ajax/hr/assignment_types/list') }}", // Create this endpoint
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let $assigned_as = $('#assigned_as');
                    $assigned_as.empty();
                    $assigned_as.append($('<option>', {
                        value: '',
                        text: 'Select assignment type',
                        disabled: true,
                        selected: true
                    }));
                    if (response.assignment_types && Array.isArray(response.assignment_types)) {
                        response.assignment_types.forEach(function(type) {
                            $assigned_as.append($('<option>', {
                                value: type.id,
                                text: type.type_name
                            }));
                        });
                    }
                },
                error: function() {
                    toastr.error('Failed to load assignment types.');
                }
            });
        
            // Populate zone select
            $.ajax({
                url: "{{ url('/ajax/hr/zones/list') }}", // Create this endpoint
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let $zone_id = $('#zone_id');
                    $zone_id.empty();
                    $zone_id.append($('<option>', {
                        value: '',
                        text: 'Select zone',
                        disabled: true,
                        selected: true
                    }));
                    if (response.zones && Array.isArray(response.zones)) {
                        response.zones.forEach(function(zone) {
                            $zone_id.append($('<option>', {
                                value: zone.id,
                                text: zone.zone_name
                            }));
                        });
                    }
                },
                error: function() {
                    toastr.error('Failed to load zones.');
                }
            });
        });

        $('#zone_id').on('change', function() {
            let zoneId = $(this).val();
            let $properties = $('#properties_ids');
            $properties.empty();
        
            if (!zoneId) {
                $properties.append($('<option>', {
                    value: '',
                    text: 'Select zone first',
                    disabled: true,
                    selected: true
                }));
                return;
            }
        
            $.ajax({
                url: "{{ url('/ajax/hr/properties/by-zone') }}/" + zoneId,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let $properties = $('#properties_ids');
                    $properties.empty();
                    if (response.properties && Array.isArray(response.properties)) {
                        response.properties.forEach(function(property) {
                            $properties.append($('<option>', {
                                value: property.id,
                                text: property.reference_number + ' - ' + property.description
                            }));
                        });
                    }
                },
                error: function() {
                    toastr.error('Failed to load properties for selected zone.');
                }
            });
        });

        $('#zoneAssignmentForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#zoneAssignmentId').val();
            var url = id ? "{{ url('/ajax/hr/zone/assignments') }}/" + id : "{{ url('/ajax/hr/zone/assignments') }}";
            var method = 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveZoneAssignmentBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();

            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);

            $.ajax({
                url: url,
                type: method,
                data: formData,
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);

                    if (res.success) {
                        toastr.success('Saved successfully');
                        $('#addZoneAssignmentModal').modal('hide');
                        $('#zoneAssignmentForm')[0].reset();
                        $('#zoneAssignmentId').val('');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving');
                    }
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);

                    let msg = 'Validation error';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).join('<br>');
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $(document).on('click', '.edit-zone-assignment', function() {
            $('#zoneAssignmentId').val($(this).data('id'));
            $('#fo_ids').val($(this).data('fo_ids'));
            $('#properties_ids').val($(this).data('properties_ids'));
            $('#current_status_id').val($(this).data('current_status_id'));
            $('#addZoneAssignmentModal').modal('show');
        
            // Populate and set Zone select
            $.ajax({
                url: "{{ url('/ajax/hr/zones/list') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let $zone_id = $('#zone_id');
                    $zone_id.empty();
                    $zone_id.append($('<option>', {
                        value: '',
                        text: 'Select zone',
                        disabled: true,
                        selected: true
                    }));
                    if (response.zones && Array.isArray(response.zones)) {
                        response.zones.forEach(function(zone) {
                            $zone_id.append($('<option>', {
                                value: zone.id,
                                text: zone.zone_name
                            }));
                        });
                    }
                    $zone_id.val($(this).data('zone_id'));
                }.bind(this),
                error: function() {
                    toastr.error('Failed to load zones.');
                }
            });
        
            // Populate and set Assigned As select
            $.ajax({
                url: "{{ url('/ajax/hr/assignment_types/list') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let $assigned_as = $('#assigned_as');
                    $assigned_as.empty();
                    $assigned_as.append($('<option>', {
                        value: '',
                        text: 'Select assignment type',
                        disabled: true,
                        selected: true
                    }));
                    if (response.assignment_types && Array.isArray(response.assignment_types)) {
                        response.assignment_types.forEach(function(type) {
                            $assigned_as.append($('<option>', {
                                value: type.id,
                                text: type.type_name
                            }));
                        });
                    }
                    $assigned_as.val($(this).data('assigned_as'));
                }.bind(this),
                error: function() {
                    toastr.error('Failed to load assignment types.');
                }
            });
        
            // Populate and set Staff select
            $.ajax({
                url: "{{ url('/ajax/hr/staff/list') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let $staff_id = $('#staff_id');
                    $staff_id.empty();
                    $staff_id.append($('<option>', {
                        value: '',
                        text: 'Select staff',
                        disabled: true,
                        selected: true
                    }));
                    if (response.staff && Array.isArray(response.staff)) {
                        response.staff.forEach(function(staff) {
                            $staff_id.append($('<option>', {
                                value: staff.id,
                                text: staff.full_names
                            }));
                        });
                    }
                    // Set selected value after options are added
                    let selectedStaffId = $(this).data('staff_id');
                    console.log(selectedStaffId);
                    $staff_id.val(selectedStaffId).trigger('change');

                    // Check if Select2 is already initialized and destroy it
                    if ($staff_id.hasClass("select2-hidden-accessible")) {
                        $staff_id.select2('destroy');
                    }
                    
                    // Initialize Select2
                    $staff_id.select2({
                        placeholder: 'Select Staff',
                        allowClear: true
                    });
                }.bind(this),
                error: function() {
                    toastr.error('Failed to load staff.');
                }
            });
        });

        $(document).on('click', '.delete-zone-assignment', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();

            if (!confirm('Are you sure you want to delete this zone assignment?')) {
                return;
            }

            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ url('/ajax/hr/zone/assignments') }}/" + $btn.data('id'),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);

                    if (res.success) {
                        toastr.success('Deleted');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error deleting');
                    }
                },
                error: function(xhr) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);

                    let msg = 'Delete error';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $('#addZoneAssignmentModal').on('hidden.bs.modal', function() {
            $('#zoneAssignmentForm')[0].reset();
            $('#zoneAssignmentId').val('');
        });
    });
</script>