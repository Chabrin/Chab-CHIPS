@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                        <h5>Zones Management</h5>
                        @permission('create_property_settings_items')
                        <div class="d-flex gap-2">
                            <button type="button" id="bulkDeleteZonesBtn" class="btn btn-danger btn-sm" style="display: none;">
                                <i class="fa fa-trash"></i> Delete Selected
                            </button>
                            <button type="button" id="addZoneBtn" class="btn btn-primary btn-sm">
                                <i class="fa fa-plus"></i> Add Zone
                            </button>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body custom-scrollbar">
                        <div class="table-responsive">
                            <table id="zonesTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAllZones">
                                        </th>
                                        <th>Zone Name</th>
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
<div class="modal fade" id="zoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="zoneModalLabel">Add Zone</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="zoneForm">
                <div class="modal-body">
                    <input type="hidden" id="zoneId" name="id">
                    <div class="mb-3">
                        <label for="zoneName" class="form-label">Zone Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="zoneName" name="zone_name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(document).ready(function() {
    let selectedZones = new Set();

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
                render: function(data) {
                    const isChecked = selectedZones.has(data.encrypted_id) ? 'checked' : '';
                    return `<input type="checkbox" class="zone-checkbox" value="${data.encrypted_id}" ${isChecked}>`;
                }
            },
            { data: 'zone_name' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data) {
                    let actions = '';
                    if (data.can_update) {
                        actions += `<a href="javascript:void(0)" class="text-success edit-zone me-2" 
                            data-id="${data.encrypted_id}" 
                            data-zone='${JSON.stringify(data)}' title="Edit">
                            <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                        </a>`;
                    }
                    if (data.can_delete) {
                        actions += `<a href="javascript:void(0)" class="text-danger delete-zone" 
                            data-id="${data.encrypted_id}" 
                            data-name="${data.zone_name}" title="Delete">
                            <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                        </a>`;
                    }
                    return actions || '-';
                }
            }
        ],
        order: [[1, 'asc']],
        pageLength: 10,
        responsive: true
    });

    // Handle select all checkbox
    $('#selectAllZones').on('change', function() {
        const isChecked = $(this).prop('checked');
        
        if (isChecked) {
            // Add all items to the set
            zonesTable.rows().data().each(function(row) {
                selectedZones.add(row.encrypted_id);
            });
        } else {
            // Remove all items from the set
            selectedZones.clear();
        }
        
        // Update checkboxes on current page
        $('.zone-checkbox').prop('checked', isChecked);
        updateBulkDeleteButton();
    });

    // Handle individual checkbox changes
    $('#zonesTable').on('change', '.zone-checkbox', function() {
        const zoneId = $(this).val();
        
        if ($(this).prop('checked')) {
            selectedZones.add(zoneId);
        } else {
            selectedZones.delete(zoneId);
            $('#selectAllZones').prop('checked', false);
        }
        
        updateBulkDeleteButton();
    });

    // Update checkboxes when page changes
    zonesTable.on('draw', function() {
        $('.zone-checkbox').each(function() {
            const zoneId = $(this).val();
            $(this).prop('checked', selectedZones.has(zoneId));
        });
        
        // Update select all checkbox state
        const allOnPageSelected = $('.zone-checkbox').length > 0 && 
            $('.zone-checkbox').length === $('.zone-checkbox:checked').length;
        $('#selectAllZones').prop('checked', allOnPageSelected);
    });

    // Function to update bulk delete button visibility
    function updateBulkDeleteButton() {
        if (selectedZones.size > 0) {
            $('#bulkDeleteZonesBtn').show();
        } else {
            $('#bulkDeleteZonesBtn').hide();
        }
    }

    // Bulk delete zones
    $('#bulkDeleteZonesBtn').click(function() {
        if (selectedZones.size === 0) {
            toastr.warning('Please select at least one zone to delete');
            return;
        }
        
        if (confirm(`Are you sure you want to delete ${selectedZones.size} selected zone(s)?`)) {
            const zoneIds = Array.from(selectedZones);
            let completed = 0;
            let failed = 0;
            
            zoneIds.forEach(function(encryptedId) {
                $.ajax({
                    url: '{{ route("property.settings.zones.delete", "PLACEHOLDER") }}'.replace('PLACEHOLDER', encryptedId),
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function() {
                        completed++;
                        selectedZones.delete(encryptedId);
                        
                        if (completed + failed === zoneIds.length) {
                            zonesTable.ajax.reload();
                            updateBulkDeleteButton();
                            $('#selectAllZones').prop('checked', false);
                            toastr.success(`Successfully deleted ${completed} zone(s)`);
                            if (failed > 0) {
                                toastr.error(`Failed to delete ${failed} zone(s)`);
                            }
                        }
                    },
                    error: function() {
                        failed++;
                        
                        if (completed + failed === zoneIds.length) {
                            zonesTable.ajax.reload();
                            updateBulkDeleteButton();
                            if (completed > 0) {
                                toastr.success(`Successfully deleted ${completed} zone(s)`);
                            }
                            toastr.error(`Failed to delete ${failed} zone(s)`);
                        }
                    }
                });
            });
        }
    });

    // ============ ZONE CRUD ============
    
    // Add Zone
    $('#addZoneBtn').click(function() {
        $('#zoneForm')[0].reset();
        $('#zoneId').val('');
        $('#zoneModalLabel').text('Add Zone');
        $('.invalid-feedback').text('').hide();
        $('.form-control').removeClass('is-invalid');
        $('#zoneModal').modal('show');
    });

    // Edit Zone
    $('#zonesTable').on('click', '.edit-zone', function() {
        const data = $(this).data('zone');
        
        $('#zoneId').val(data.encrypted_id);
        $('#zoneName').val(data.zone_name !== '-' ? data.zone_name : '');
        $('#estatesId').val(data.estates_id !== '-' ? data.estates_id : '');
        
        $('#zoneModalLabel').text('Edit Zone');
        $('.invalid-feedback').text('').hide();
        $('.form-control').removeClass('is-invalid');
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
        
        $.ajax({
            url: url,
            method: method,
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $('#zoneModal').modal('hide');
                zonesTable.ajax.reload();
                toastr.success(response.message);
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    $('.form-control').removeClass('is-invalid');
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
        
        if (confirm(`Are you sure you want to delete "${name}"?`)) {
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
        }
    });
});
</script>
@endsection
