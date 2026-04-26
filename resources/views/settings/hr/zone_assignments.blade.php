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
                                <tfoot>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
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

                    <div class="row">
                        <!-- Step 1: Select Staff -->
                        <div class="col-lg-6 mb-3">
                            <label for="staff_id" class="form-label">Staff <span style="color:red">*</span></label>
                            <select class="form-select" name="staff_id" id="staff_id" required>
                                <option value="" disabled selected>Select staff first</option>
                            </select>
                        </div>

                        <!-- Step 2: Select Assignment Role -->
                        <div class="col-lg-6 mb-3">
                            <label for="assigned_as" class="form-label">Assign As Role <span style="color:red">*</span></label>
                            <select class="form-select" name="assigned_as" id="assigned_as" required disabled>
                                <option value="" disabled selected>Select staff first, then select role</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Step 3: Select Zone -->
                        <div class="col-lg-6 mb-3">
                            <label for="zone_id" class="form-label">Zone <span style="color:red">*</span></label>
                            <select class="form-select" name="zone_id" id="zone_id" required disabled>
                                <option value="" disabled selected>Select role first, then select zone</option>
                            </select>
                        </div>
                    </div>

                    <!-- Step 4: Filter and Select Available Properties -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Available Zone Properties <span style="color:red">*</span></label>
                            <span id="zoneAssignmentSelectedCount" class="badge bg-primary text-white">0 selected</span>
                        </div>

                        <table class="table table-sm table-hover mb-0" id="zoneAssignmentPropertiesTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:30px;"><input type="checkbox" id="zoneAssignmentCheckAll" title="Select/Deselect All"></th>
                                    <th>Property Ref</th>
                                    <th>Property Name</th>
                                </tr>
                            </thead>
                            <tbody id="zoneAssignmentPropertiesTableBody">
                                <tr>
                                    <td colspan="3" class="text-muted text-center py-3">Select zone and role first</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                        <small class="form-text text-muted d-block mt-2">Properties shown are not assigned to any staff member for this role.</small>
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
    let propertiesTableData = [];
    let isEditMode = false;

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
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (data, type, row, meta) => meta.row + 1
                },
                {
                    data: 'zone',
                    title: 'Zone',
                    render: data => data && data.zone_name ? data.zone_name : '-'
                },
                {
                    data: 'assignment_type',
                    title: 'Assigned As',
                    render: data => data && data.type_name ? data.type_name : '-'
                },
                {
                    data: 'staff',
                    title: 'Staff',
                    render: data => data && data.name ? data.name : '-'
                },
                {
                    data: 'current_status_id',
                    title: 'Status',
                    render: data => data == 5 ? 'Active' : 'Pending'
                },
                {
                    data: 'created_by',
                    title: 'Created By',
                    render: data => data && data.name ? data.name : '-'
                },
                {
                    data: 'date_time',
                    title: 'Date/Time',
                    render: data => data ? new Date(data).toLocaleString() : '-'
                },
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
                                    data-zone_id="${row.zone_id || (row.zone && row.zone.id ? row.zone.id : '')}"
                                    data-assigned_as="${row.assigned_as || (row.assignment_type && row.assignment_type.id ? row.assignment_type.id : '')}"
                                    data-staff_id="${row.staff_id || (row.staff && row.staff.id ? row.staff.id : '')}"
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
            pageLength: 10,
            info: true,
            lengthChange: true
        });

        // ===== STEP 1: When modal opens, load staff list (Add mode only) =====
        $('#addZoneAssignmentModal').on('show.bs.modal', function() {
            // Skip reset/load if we are in edit mode — the edit handler will populate
            if (isEditMode) {
                return;
            }

            // Reset form
            $('#zoneAssignmentForm')[0].reset();
            $('#zoneAssignmentId').val('');

            // Re-enable staff and role selects (they may have been disabled by a previous edit)
            $('#staff_id').prop('disabled', false);
            $('#assigned_as').prop('disabled', true);
            $('#zone_id').prop('disabled', true);

            // Reset properties table
            if ($.fn.DataTable.isDataTable('#zoneAssignmentPropertiesTable')) {
                $('#zoneAssignmentPropertiesTable').DataTable().destroy();
            }
            $('#zoneAssignmentPropertiesTableBody').html('<tr><td colspan="3" class="text-muted text-center py-3">Select zone and role first</td></tr>');
            $('#zoneAssignmentSelectedCount').text('0 selected');
            propertiesTableData = [];

            // Disable steps 2-4
            $('#assigned_as').empty().append($('<option>', {
                value: '',
                text: 'Select staff first, then select role',
                disabled: true,
                selected: true
            }));
            $('#zone_id').empty().append($('<option>', {
                value: '',
                text: 'Select role first, then select zone',
                disabled: true,
                selected: true
            }));

            // Load staff list
            $.ajax({
                url: "{{ url('/ajax/hr/staff/list') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let $staff_id = $('#staff_id');

                    // Destroy existing Select2 first
                    if ($staff_id.hasClass("select2-hidden-accessible")) {
                        $staff_id.select2('destroy');
                    }

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

                    // Initialize Select2 with dropdownParent so search input is focusable inside modal
                    $staff_id.select2({
                        placeholder: 'Select Staff',
                        allowClear: true,
                        dropdownParent: $('#addZoneAssignmentModal')
                    });
                },
                error: function() {
                    toastr.error('Failed to load staff.');
                }
            });
        });

        // ===== STEP 2: When staff selected, load available roles =====
        $('#staff_id').on('change', function() {
            let staffId = $(this).val();
            let $assigned_as = $('#assigned_as');

            if (!staffId) {
                $assigned_as.prop('disabled', true).empty().append($('<option>', {
                    value: '',
                    text: 'Select staff first, then select role',
                    disabled: true,
                    selected: true
                }));
                $('#zone_id').prop('disabled', true).empty().append($('<option>', {
                    value: '',
                    text: 'Select role first, then select zone',
                    disabled: true,
                    selected: true
                }));
                $('#properties_ids').prop('disabled', true).empty().append($('<option>', {
                    disabled: true,
                    text: 'Select zone first to see available properties'
                }));
                return;
            }

            // Load available roles
            $.ajax({
                url: "{{ url('/ajax/hr/roles/list') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    $assigned_as.empty();
                    $assigned_as.append($('<option>', {
                        value: '',
                        text: 'Select role',
                        disabled: true,
                        selected: true
                    }));
                    if (response.roles && Array.isArray(response.roles)) {
                        response.roles.forEach(function(role) {
                            $assigned_as.append($('<option>', {
                                value: role.id,
                                text: role.name
                            }));
                        });
                    }
                    $assigned_as.prop('disabled', false);

                    // Reset zone and properties
                    $('#zone_id').prop('disabled', true).empty().append($('<option>', {
                        value: '',
                        text: 'Select role first, then select zone',
                        disabled: true,
                        selected: true
                    }));
                    $('#properties_ids').prop('disabled', true).empty().append($('<option>', {
                        disabled: true,
                        text: 'Select zone first to see available properties'
                    }));
                },
                error: function() {
                    toastr.error('Failed to load roles.');
                }
            });
        });

        // ===== STEP 3: When role selected, load zones =====
        $('#assigned_as').on('change', function() {
            let staffId = $('#staff_id').val();
            let assignedAs = $(this).val();
            let $zone_id = $('#zone_id');

            if (!staffId || !assignedAs) {
                $zone_id.prop('disabled', true).empty().append($('<option>', {
                    value: '',
                    text: 'Select role first, then select zone',
                    disabled: true,
                    selected: true
                }));
                $('#properties_ids').prop('disabled', true).empty().append($('<option>', {
                    disabled: true,
                    text: 'Select zone first to see available properties'
                }));
                return;
            }

            // Load zones
            $.ajax({
                url: "{{ url('/ajax/hr/zones/list') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
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
                    $zone_id.prop('disabled', false);

                    // Reset properties
                    $('#properties_ids').prop('disabled', true).empty().append($('<option>', {
                        disabled: true,
                        text: 'Select zone first to see available properties'
                    }));
                },
                error: function() {
                    toastr.error('Failed to load zones.');
                }
            });
        });

        // ===== STEP 4: When zone selected, load available properties =====
        $('#zone_id').on('change', function() {
            let zoneId = $(this).val();
            let staffId = $('#staff_id').val();
            let assignedAs = $('#assigned_as').val();

            if (!zoneId || !staffId || !assignedAs) {
                renderZoneAssignmentPropertiesTable([], []);
                return;
            }

            // Load available properties in zone that are not assigned for this role
            $.ajax({
                url: "{{ url('/ajax/hr/properties/available-in-zone') }}",
                type: 'GET',
                data: {
                    zone_id: zoneId,
                    assigned_as: assignedAs,
                    staff_id: staffId
                },
                dataType: 'json',
                success: function(response) {
                    renderZoneAssignmentPropertiesTable(response.properties || [], []);
                    refreshZoneAssignmentSelectedCount();
                },
                error: function() {
                    toastr.error('Failed to load available properties.');
                    renderZoneAssignmentPropertiesTable([], []);
                }
            });
        });

        // Function to render properties table with checkboxes
        function renderZoneAssignmentPropertiesTable(properties, selectedIds) {
            // Destroy existing DataTable before re-rendering
            if ($.fn.DataTable.isDataTable('#zoneAssignmentPropertiesTable')) {
                $('#zoneAssignmentPropertiesTable').DataTable().destroy();
            }

            $('#zoneAssignmentCheckAll').prop('checked', false);

            if (!properties || properties.length === 0) {
                propertiesTableData = [];
                $('#zoneAssignmentPropertiesTableBody').html('<tr><td colspan="3" class="text-muted text-center py-3">No available properties in this zone for this role.</td></tr>');
                refreshZoneAssignmentSelectedCount();
                return;
            }

            // Store properties data globally with checked state
            propertiesTableData = properties.map(function(prop) {
                return {
                    id: prop.id,
                    reference_number: prop.reference_number,
                    property_name: prop.property_name,
                    checked: selectedIds.indexOf(Number(prop.id)) !== -1
                };
            });

            renderPropertiesTableBody();

            // Initialize DataTable with paging and global search
            $('#zoneAssignmentPropertiesTable').DataTable({
                paging: true,
                pageLength: 10,
                searching: true,
                info: true,
                lengthChange: true,
                columnDefs: [
                    { orderable: false, targets: 0 }
                ]
            });

            refreshZoneAssignmentSelectedCount();
            updateZoneAssignmentCheckAllState();
        }

        // Render properties table body with checked rows first
        function renderPropertiesTableBody() {
            // Sort: checked first, then unchecked
            let sorted = propertiesTableData.slice().sort(function(a, b) {
                if (a.checked === b.checked) return 0;
                return a.checked ? -1 : 1;
            });

            let tbody = $('#zoneAssignmentPropertiesTableBody');
            tbody.empty();

            sorted.forEach(function(prop) {
                tbody.append(
                    '<tr>' +
                    '<td class="text-center"><input class="form-check-input zone-prop-check" type="checkbox" value="' + prop.id + '" ' + (prop.checked ? 'checked' : '') + '></td>' +
                    '<td>' + (prop.reference_number || '-') + '</td>' +
                    '<td>' + (prop.property_name || '-') + '</td>' +
                    '</tr>'
                );
            });
        }

        // Get selected property IDs from checkboxes
        function getSelectedZoneAssignmentPropertyIds() {
            let selected = [];
            $('.zone-prop-check:checked').each(function() {
                selected.push($(this).val());
            });
            return selected;
        }

        // Refresh the selected count badge
        function refreshZoneAssignmentSelectedCount() {
            let count = getSelectedZoneAssignmentPropertyIds().length;
            $('#zoneAssignmentSelectedCount').text(count + ' selected');
        }

        // Update check all checkbox state
        function updateZoneAssignmentCheckAllState() {
            let total = $('.zone-prop-check:not(:disabled)').length;
            let checked = $('.zone-prop-check:not(:disabled):checked').length;
            $('#zoneAssignmentCheckAll').prop('checked', total > 0 && total === checked);
        }

        // Handle individual checkbox change - rebuild table with checked rows on top
        $(document).on('change', '.zone-prop-check', function() {
            let propId = $(this).val();
            let isChecked = $(this).prop('checked');

            // Update data
            let propData = propertiesTableData.find(p => p.id == propId);
            if (propData) {
                propData.checked = isChecked;
            }

            // Rebuild and reinitialize DataTable
            if ($.fn.DataTable.isDataTable('#zoneAssignmentPropertiesTable')) {
                $('#zoneAssignmentPropertiesTable').DataTable().destroy();
            }

            renderPropertiesTableBody();

            $('#zoneAssignmentPropertiesTable').DataTable({
                paging: true,
                pageLength: 10,
                searching: true,
                info: true,
                lengthChange: true,
                columnDefs: [
                    { orderable: false, targets: 0 }
                ]
            });

            refreshZoneAssignmentSelectedCount();
            updateZoneAssignmentCheckAllState();
        });

        // Handle check all checkbox - rebuild table with all checked rows on top
        $(document).on('change', '#zoneAssignmentCheckAll', function() {
            let isChecked = $(this).prop('checked');

            // Update all data
            propertiesTableData.forEach(p => p.checked = isChecked);

            // Rebuild and reinitialize DataTable
            if ($.fn.DataTable.isDataTable('#zoneAssignmentPropertiesTable')) {
                $('#zoneAssignmentPropertiesTable').DataTable().destroy();
            }

            renderPropertiesTableBody();

            $('#zoneAssignmentPropertiesTable').DataTable({
                paging: true,
                pageLength: 10,
                searching: true,
                info: true,
                lengthChange: true,
                columnDefs: [
                    { orderable: false, targets: 0 }
                ]
            });

            refreshZoneAssignmentSelectedCount();
        });

        $('#zoneAssignmentForm').on('submit', function(e) {
            e.preventDefault();

            // Get selected property IDs
            let selectedPropertyIds = getSelectedZoneAssignmentPropertyIds();

            // Validate that at least one property is selected
            if (selectedPropertyIds.length === 0) {
                toastr.error('Please select at least one property');
                return;
            }

            var id = $('#zoneAssignmentId').val();
            var url = id ? "{{ url('/ajax/hr/zone/assignments') }}/" + id : "{{ url('/ajax/hr/zone/assignments') }}";
            var method = 'POST';

            // Create form data with selected property IDs
            var formData = new FormData();
            formData.append('zone_id', $('#zone_id').val());
            formData.append('assigned_as', $('#assigned_as').val());
            formData.append('staff_id', $('#staff_id').val());
            formData.append('_token', '{{ csrf_token() }}');
            selectedPropertyIds.forEach(function(id) {
                formData.append('properties_ids[]', id);
            });
            if (id) {
                formData.append('id', id);
            }

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
                processData: false,
                contentType: false,
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
            let assignmentData = $(this).data();

            // Mark as edit mode BEFORE opening modal so show.bs.modal handler skips its reset/load
            isEditMode = true;

            $('#zoneAssignmentId').val(assignmentData.id);

            // Parse existing properties IDs robustly (handles string "1,2,3", single number, or empty)
            let propIdsRaw = String(assignmentData.properties_ids || '');
            let selectedPropertyIds = propIdsRaw
                ? propIdsRaw.split(',').map(p => Number(String(p).trim())).filter(n => !isNaN(n))
                : [];

            $('#addZoneAssignmentModal').modal('show');

            // Load and set staff
            $.ajax({
                url: "{{ url('/ajax/hr/staff/list') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let $staff_id = $('#staff_id');

                    if ($staff_id.hasClass("select2-hidden-accessible")) {
                        $staff_id.select2('destroy');
                    }

                    $staff_id.empty();
                    $staff_id.append($('<option>', {
                        value: '',
                        text: 'Select staff',
                        disabled: true
                    }));
                    if (response.staff && Array.isArray(response.staff)) {
                        response.staff.forEach(function(staff) {
                            $staff_id.append($('<option>', {
                                value: staff.id,
                                text: staff.full_names
                            }));
                        });
                    }

                    $staff_id.val(assignmentData.staff_id);

                    // Initialize Select2 with dropdownParent
                    $staff_id.select2({
                        placeholder: 'Select Staff',
                        allowClear: false,
                        dropdownParent: $('#addZoneAssignmentModal')
                    });

                    // Lock staff field on edit
                    $staff_id.prop('disabled', true).trigger('change.select2');

                    // Now load roles for this staff
                    $.ajax({
                        url: "{{ url('/ajax/hr/roles/list') }}",
                        type: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            let $assigned_as = $('#assigned_as');
                            $assigned_as.empty();
                            $assigned_as.append($('<option>', {
                                value: '',
                                text: 'Select role',
                                disabled: true
                            }));
                            if (response.roles && Array.isArray(response.roles)) {
                                response.roles.forEach(function(role) {
                                    $assigned_as.append($('<option>', {
                                        value: role.id,
                                        text: role.name
                                    }));
                                });
                            }

                            $assigned_as.val(assignmentData.assigned_as);

                            // Lock role field on edit
                            $assigned_as.prop('disabled', true);

                            // Load zones for this role
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
                                        disabled: true
                                    }));
                                    if (response.zones && Array.isArray(response.zones)) {
                                        response.zones.forEach(function(zone) {
                                            $zone_id.append($('<option>', {
                                                value: zone.id,
                                                text: zone.zone_name
                                            }));
                                        });
                                    }

                                    $zone_id.prop('disabled', false);
                                    $zone_id.val(assignmentData.zone_id);

                                    // Load available properties for this zone
                                    $.ajax({
                                        url: "{{ url('/ajax/hr/properties/available-in-zone') }}",
                                        type: 'GET',
                                        data: {
                                            zone_id: assignmentData.zone_id,
                                            assigned_as: assignmentData.assigned_as,
                                            staff_id: assignmentData.staff_id
                                        },
                                        dataType: 'json',
                                        success: function(response) {
                                            let availableProps = response.properties || [];

                                            // Ensure currently-selected properties always appear
                                            // (even if endpoint excludes them for some reason)
                                            let presentIds = availableProps.map(p => Number(p.id));
                                            selectedPropertyIds.forEach(function(selId) {
                                                if (presentIds.indexOf(selId) === -1) {
                                                    availableProps.push({
                                                        id: selId,
                                                        reference_number: '#' + selId,
                                                        property_name: '(currently assigned)'
                                                    });
                                                }
                                            });

                                            renderZoneAssignmentPropertiesTable(availableProps, selectedPropertyIds);
                                        },
                                        error: function() {
                                            toastr.error('Failed to load available properties.');
                                            renderZoneAssignmentPropertiesTable([], []);
                                        }
                                    });
                                },
                                error: function() {
                                    toastr.error('Failed to load zones.');
                                }
                            });
                        },
                        error: function() {
                            toastr.error('Failed to load roles.');
                        }
                    });
                },
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
            isEditMode = false;

            if ($.fn.DataTable.isDataTable('#zoneAssignmentPropertiesTable')) {
                $('#zoneAssignmentPropertiesTable').DataTable().destroy();
            }
            $('#zoneAssignmentForm')[0].reset();
            $('#zoneAssignmentId').val('');

            // Re-enable locked fields so next open (Add) starts clean
            $('#staff_id').prop('disabled', false);
            $('#assigned_as').prop('disabled', false);
            $('#zone_id').prop('disabled', false);
            propertiesTableData = [];
        });
    });
</script>