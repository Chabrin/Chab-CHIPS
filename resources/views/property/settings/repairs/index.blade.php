@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <!-- Property Elements Section -->
                <div class="card mb-4">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Property Elements</h5>
                        @permission('create_property_settings_items')
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addPropertyElementBtn">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Property Element</span>
                            </button>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                        <div class="alert alert-light-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif
                        @if(session('error'))
                        <div class="alert alert-light-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <div class="table-responsive custom-scrollbar">
                            <table id="propertyElementsTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Element Name</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Repair Types Section -->
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Repair Types</h5>
                        @permission('create_property_settings_items')
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addRepairTypeBtn">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Repair Type</span>
                            </button>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table id="repairTypesTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Property Element</th>
                                        <th>Repair Type Name</th>
                                        <th>Status</th>
                                        <th>Actions</th>
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

<!-- Property Element Modal -->
<div class="modal fade" id="propertyElementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="propertyElementModalLabel">Add Property Element</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="propertyElementForm">
                    <input type="hidden" id="propertyElementId">
                    <div class="mb-3">
                        <label for="elementName" class="form-label">Element Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="elementName" name="element_name" required>
                        <div class="invalid-feedback" id="elementNameError"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="savePropertyElementBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Repair Type Modal -->
<div class="modal fade" id="repairTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="repairTypeModalLabel">Add Repair Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="repairTypeForm">
                    <input type="hidden" id="repairTypeId">
                    <div class="mb-3">
                        <label for="propertyElementSelect" class="form-label">Property Element <span class="text-danger">*</span></label>
                        <select class="form-select" id="propertyElementSelect" name="element_id" required>
                            <option value="">Select Property Element</option>
                        </select>
                        <div class="invalid-feedback" id="propertyElementSelectError"></div>
                    </div>
                    <div class="mb-3">
                        <label for="repairTypeName" class="form-label">Repair Type Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="repairTypeName" name="element_name" required>
                        <div class="invalid-feedback" id="repairTypeNameError"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveRepairTypeBtn">Save</button>
            </div>
        </div>
    </div>
</div>

@endsection

<script>
$(document).ready(function() {
    // Initialize Property Elements DataTable
    var propertyElementsTable = $('#propertyElementsTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ url('property/settings/repairs/property-elements/data') }}",
            type: 'GET',
            dataSrc: 'data'
        },
        columns: [
            { data: 'element_name' },
            { 
                data: 'status',
                render: function(data) {
                    if (data === 'Active') {
                        return '<span class="badge bg-success">Active</span>';
                    }
                    return '<span class="badge bg-secondary">Inactive</span>';
                }
            },
            {
                data: 'encrypted_id',
                orderable: false,
                searchable: false,
                render: function(encryptedId, type, row) {
                    let actions = '';
                    
                    if (row.can_update) {
                        actions += `
                            <a href="javascript:void(0)" class="text-success edit-property-element" data-id="${encryptedId}" title="Edit">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                            </a>
                        `;
                    }
                    
                    if (row.can_delete) {
                        actions += `
                            <a href="javascript:void(0)" class="text-danger delete-property-element" data-id="${encryptedId}" title="Delete">
                                <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                            </a>
                        `;
                    }
                    
                    return actions || '-';
                }
            }
        ],
        pageLength: 10,
        responsive: true,
        order: [[0, 'asc']]
    });

    // Initialize Repair Types DataTable
    var repairTypesTable = $('#repairTypesTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ url('property/settings/repairs/repair-types/data') }}",
            type: 'GET',
            dataSrc: 'data'
        },
        columns: [
            { data: 'property_element_name' },
            { data: 'repair_type_name' },
            { 
                data: 'status',
                render: function(data) {
                    if (data === 'Active') {
                        return '<span class="badge bg-success">Active</span>';
                    }
                    return '<span class="badge bg-secondary">Inactive</span>';
                }
            },
            {
                data: 'encrypted_id',
                orderable: false,
                searchable: false,
                render: function(encryptedId, type, row) {
                    let actions = '';
                    
                    if (row.can_update) {
                        actions += `
                            <a href="javascript:void(0)" class="text-success edit-repair-type" 
                               data-id="${encryptedId}" 
                               data-element-id="${row.element_id}" 
                               title="Edit">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                            </a>
                        `;
                    }
                    
                    if (row.can_delete) {
                        actions += `
                            <a href="javascript:void(0)" class="text-danger delete-repair-type" data-id="${encryptedId}" title="Delete">
                                <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                            </a>
                        `;
                    }
                    
                    return actions || '-';
                }
            }
        ],
        pageLength: 10,
        responsive: true,
        order: [[0, 'asc'], [1, 'asc']]
    });

    // Load property elements dropdown
    function loadPropertyElementsDropdown() {
        $.ajax({
            url: "{{ url('property/settings/repairs/property-elements/dropdown') }}",
            type: 'GET',
            success: function(response) {
                $('#propertyElementSelect').empty().append('<option value="">Select Property Element</option>');
                response.forEach(function(element) {
                    $('#propertyElementSelect').append(
                        $('<option>', {
                            value: element.id,
                            text: element.text
                        })
                    );
                });
            },
            error: function() {
                toastr.error('Failed to load property elements');
            }
        });
    }

    // Add Property Element Button
    $('#addPropertyElementBtn').on('click', function() {
        $('#propertyElementForm')[0].reset();
        $('#propertyElementId').val('');
        $('#propertyElementModalLabel').text('Add Property Element');
        $('.invalid-feedback').hide();
        $('.form-control').removeClass('is-invalid');
        $('#propertyElementModal').modal('show');
    });

    // Edit Property Element
    $(document).on('click', '.edit-property-element', function() {
        const encryptedId = $(this).data('id');
        const row = propertyElementsTable.rows().data().toArray().find(r => r.encrypted_id === encryptedId);
        
        $('#propertyElementId').val(encryptedId);
        $('#elementName').val(row.element_name);
        $('#propertyElementModalLabel').text('Edit Property Element');
        $('.invalid-feedback').hide();
        $('.form-control').removeClass('is-invalid');
        $('#propertyElementModal').modal('show');
    });

    // Save Property Element
    $('#savePropertyElementBtn').on('click', function() {
        const encryptedId = $('#propertyElementId').val();
        const isEdit = encryptedId !== '';
        const url = isEdit 
            ? "{{ url('property/settings/repairs/property-elements') }}/" + encryptedId
            : "{{ url('property/settings/repairs/property-elements/store') }}";
        const method = isEdit ? 'PUT' : 'POST';

        $('.invalid-feedback').hide();
        $('.form-control').removeClass('is-invalid');

        $.ajax({
            url: url,
            type: method,
            data: {
                _token: "{{ csrf_token() }}",
                element_name: $('#elementName').val()
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#propertyElementModal').modal('hide');
                    propertyElementsTable.ajax.reload();
                    loadPropertyElementsDropdown(); // Reload dropdown for repair types
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    if (errors.element_name) {
                        $('#elementName').addClass('is-invalid');
                        $('#elementNameError').text(errors.element_name[0]).show();
                    }
                } else {
                    toastr.error('An error occurred. Please try again.');
                }
            }
        });
    });

    // Delete Property Element
    $(document).on('click', '.delete-property-element', function() {
        if (!confirm('Are you sure you want to delete this property element?')) {
            return;
        }

        const encryptedId = $(this).data('id');

        $.ajax({
            url: "{{ url('property/settings/repairs/property-elements') }}/" + encryptedId,
            type: 'DELETE',
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    propertyElementsTable.ajax.reload();
                    loadPropertyElementsDropdown();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('Failed to delete property element');
            }
        });
    });

    // Add Repair Type Button
    $('#addRepairTypeBtn').on('click', function() {
        $('#repairTypeForm')[0].reset();
        $('#repairTypeId').val('');
        $('#repairTypeModalLabel').text('Add Repair Type');
        $('.invalid-feedback').hide();
        $('.form-control, .form-select').removeClass('is-invalid');
        loadPropertyElementsDropdown();
        $('#repairTypeModal').modal('show');
    });

    // Edit Repair Type
    $(document).on('click', '.edit-repair-type', function() {
        const encryptedId = $(this).data('id');
        const elementId = $(this).data('element-id');
        const row = repairTypesTable.rows().data().toArray().find(r => r.encrypted_id === encryptedId);
        
        $('#repairTypeId').val(encryptedId);
        $('#repairTypeName').val(row.repair_type_name);
        $('#repairTypeModalLabel').text('Edit Repair Type');
        $('.invalid-feedback').hide();
        $('.form-control, .form-select').removeClass('is-invalid');
        
        loadPropertyElementsDropdown();
        
        // Set element after dropdown loads
        setTimeout(function() {
            $('#propertyElementSelect').val(elementId);
        }, 500);
        
        $('#repairTypeModal').modal('show');
    });

    // Save Repair Type
    $('#saveRepairTypeBtn').on('click', function() {
        const encryptedId = $('#repairTypeId').val();
        const isEdit = encryptedId !== '';
        const url = isEdit 
            ? "{{ url('property/settings/repairs/repair-types') }}/" + encryptedId
            : "{{ url('property/settings/repairs/repair-types/store') }}";
        const method = isEdit ? 'PUT' : 'POST';

        $('.invalid-feedback').hide();
        $('.form-control, .form-select').removeClass('is-invalid');

        $.ajax({
            url: url,
            type: method,
            data: {
                _token: "{{ csrf_token() }}",
                element_id: $('#propertyElementSelect').val(),
                element_name: $('#repairTypeName').val()
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#repairTypeModal').modal('hide');
                    repairTypesTable.ajax.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    if (errors.element_id) {
                        $('#propertyElementSelect').addClass('is-invalid');
                        $('#propertyElementSelectError').text(errors.element_id[0]).show();
                    }
                    if (errors.element_name) {
                        $('#repairTypeName').addClass('is-invalid');
                        $('#repairTypeNameError').text(errors.element_name[0]).show();
                    }
                } else {
                    toastr.error('An error occurred. Please try again.');
                }
            }
        });
    });

    // Delete Repair Type
    $(document).on('click', '.delete-repair-type', function() {
        if (!confirm('Are you sure you want to delete this repair type?')) {
            return;
        }

        const encryptedId = $(this).data('id');

        $.ajax({
            url: "{{ url('property/settings/repairs/repair-types') }}/" + encryptedId,
            type: 'DELETE',
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    repairTypesTable.ajax.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('Failed to delete repair type');
            }
        });
    });
});
</script>
