@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <!-- Return Types Section -->
                <div class="card mb-4">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Return Types</h5>
                        @permission('create_property_settings_items')
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addReturnTypeBtn">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Return Type</span>
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
                            <table id="returnTypesTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Type Name</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Invoiceable Items Section -->
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Invoiceable Items</h5>
                        @permission('create_property_settings_items')
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-danger btn-sm d-flex align-items-center" id="bulkDeleteInvoiceableItemsBtn" style="display:none;">
                                <span class="material-symbols-outlined">delete</span>
                                <span class="ms-1">Delete Selected</span>
                            </button>
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addInvoiceableItemBtn">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Invoiceable Item</span>
                            </button>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table id="invoiceableItemsTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selectAllInvoiceableItems"></th>
                                        <th>Item Name</th>
                                        <th>Billing Code</th>
                                        <th>Default Cycle</th>
                                        <th>Commissionable</th>
                                        <th>Advance Payable</th>
                                        <th>Is Bill</th>
                                        <th>Fixed Amount</th>
                                        <th>Amount</th>
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

<!-- Return Type Modal -->
<div class="modal fade" id="returnTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="returnTypeModalLabel">Add Return Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="returnTypeForm">
                <div class="modal-body">
                    <input type="hidden" id="returnTypeId" name="id">
                    <div class="mb-3">
                        <label for="returnTypeName" class="form-label">Type Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="returnTypeName" name="type_name" required>
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

<!-- Invoiceable Item Modal -->
<div class="modal fade" id="invoiceableItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceableItemModalLabel">Add Invoiceable Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="invoiceableItemForm">
                <div class="modal-body">
                    <input type="hidden" id="invoiceableItemId" name="id">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="itemName" class="form-label">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="itemName" name="item_name" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="defaultCycle" class="form-label">Default Cycle</label>
                            <select class="form-select" id="defaultCycle" name="default_cycle_id">
                                <option value="">Select Cycle</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="commissionable" class="form-label">Commissionable</label>
                            <select class="form-select" id="commissionable" name="commissionable">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="advancePayable" class="form-label">Advance Payable</label>
                            <select class="form-select" id="advancePayable" name="advance_payable">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="isBill" class="form-label">Is Bill</label>
                            <select class="form-select" id="isBill" name="is_bill">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fixedAmount" class="form-label">Fixed Amount</label>
                            <select class="form-select" id="fixedAmount" name="fixed_amount">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount">
                            <div class="invalid-feedback"></div>
                        </div>
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


<script>
$(document).ready(function() {
    let selectedInvoiceableItems = new Set();

    // ============ RETURN TYPES TABLE ============
    const returnTypesTable = $('#returnTypesTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '{{ route("property.settings.return-types.data") }}',
            type: 'GET',
            dataSrc: 'data',
            error: function(xhr) {
                console.error('Error loading return types:', xhr);
            }
        },
        columns: [
            { data: 'type_name' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data) {
                    let actions = '';
                    if (data.can_update) {
                        actions += `<a href="javascript:void(0)" class="text-success edit-return-type me-2" data-id="${data.encrypted_id}" data-name="${data.type_name}" title="Edit">
                            <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                        </a>`;
                    }
                    if (data.can_delete) {
                        actions += `<a href="javascript:void(0)" class="text-danger delete-return-type" data-id="${data.encrypted_id}" data-name="${data.type_name}" title="Delete">
                            <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                        </a>`;
                    }
                    return actions || '-';
                }
            }
        ],
        order: [[0, 'asc']],
        pageLength: 10,
        responsive: true
    });

    // ============ INVOICEABLE ITEMS TABLE ============
    const invoiceableItemsTable = $('#invoiceableItemsTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '{{ route("property.settings.invoiceable-items.data") }}',
            type: 'GET',
            dataSrc: 'data',
            error: function(xhr) {
                console.error('Error loading invoiceable items:', xhr);
            }
        },
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data) {
                    const isChecked = selectedInvoiceableItems.has(data.encrypted_id) ? 'checked' : '';
                    return `<input type="checkbox" class="invoiceable-item-checkbox" value="${data.encrypted_id}" ${isChecked}>`;
                }
            },
            { data: 'item_name' },
            { data: 'billing_code' },
            { data: 'cycle_name' },
            { data: 'commissionable' },
            { data: 'advance_payable' },
            { data: 'is_bill' },
            { data: 'fixed_amount' },
            { data: 'amount' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data) {
                    let actions = '';
                    if (data.can_update) {
                        actions += `<a href="javascript:void(0)" class="text-success edit-invoiceable-item me-2" 
                            data-id="${data.encrypted_id}" 
                            data-item='${JSON.stringify(data)}' title="Edit">
                            <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                        </a>`;
                    }
                    if (data.can_delete) {
                        actions += `<a href="javascript:void(0)" class="text-danger delete-invoiceable-item" 
                            data-id="${data.encrypted_id}" 
                            data-name="${data.item_name}" title="Delete">
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

    // Handle select all checkbox for invoiceable items
    $('#selectAllInvoiceableItems').on('change', function() {
        const isChecked = $(this).prop('checked');
        
        if (isChecked) {
            // Add all items to the set
            invoiceableItemsTable.rows().data().each(function(row) {
                selectedInvoiceableItems.add(row.encrypted_id);
            });
        } else {
            // Remove all items from the set
            selectedInvoiceableItems.clear();
        }
        
        // Update checkboxes on current page
        $('.invoiceable-item-checkbox').prop('checked', isChecked);
        updateBulkDeleteButtonInvoiceableItems();
    });

    // Handle individual checkbox changes for invoiceable items
    $('#invoiceableItemsTable').on('change', '.invoiceable-item-checkbox', function() {
        const itemId = $(this).val();
        
        if ($(this).prop('checked')) {
            selectedInvoiceableItems.add(itemId);
        } else {
            selectedInvoiceableItems.delete(itemId);
            $('#selectAllInvoiceableItems').prop('checked', false);
        }
        
        updateBulkDeleteButtonInvoiceableItems();
    });

    // Update checkboxes when page changes
    invoiceableItemsTable.on('draw', function() {
        $('.invoiceable-item-checkbox').each(function() {
            const itemId = $(this).val();
            $(this).prop('checked', selectedInvoiceableItems.has(itemId));
        });
        
        // Update select all checkbox state
        const allOnPageSelected = $('.invoiceable-item-checkbox').length > 0 && 
            $('.invoiceable-item-checkbox').length === $('.invoiceable-item-checkbox:checked').length;
        $('#selectAllInvoiceableItems').prop('checked', allOnPageSelected);
    });

    // Function to update bulk delete button visibility
    function updateBulkDeleteButtonInvoiceableItems() {
        if (selectedInvoiceableItems.size > 0) {
            $('#bulkDeleteInvoiceableItemsBtn').show();
        } else {
            $('#bulkDeleteInvoiceableItemsBtn').hide();
        }
    }

    // Bulk delete invoiceable items
    $('#bulkDeleteInvoiceableItemsBtn').click(function() {
        if (selectedInvoiceableItems.size === 0) {
            toastr.warning('Please select at least one item to delete');
            return;
        }
        
        if (confirm(`Are you sure you want to delete ${selectedInvoiceableItems.size} selected item(s)?`)) {
            const itemIds = Array.from(selectedInvoiceableItems);
            let completed = 0;
            let failed = 0;
            
            itemIds.forEach(function(encryptedId) {
                $.ajax({
                    url: '{{ route("property.settings.invoiceable-items.delete", ":id") }}'.replace(':id', encryptedId),
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function() {
                        completed++;
                        selectedInvoiceableItems.delete(encryptedId);
                        
                        if (completed + failed === itemIds.length) {
                            invoiceableItemsTable.ajax.reload();
                            updateBulkDeleteButtonInvoiceableItems();
                            $('#selectAllInvoiceableItems').prop('checked', false);
                            toastr.success(`Successfully deleted ${completed} item(s)`);
                            if (failed > 0) {
                                toastr.error(`Failed to delete ${failed} item(s)`);
                            }
                        }
                    },
                    error: function() {
                        failed++;
                        
                        if (completed + failed === itemIds.length) {
                            invoiceableItemsTable.ajax.reload();
                            updateBulkDeleteButtonInvoiceableItems();
                            if (completed > 0) {
                                toastr.success(`Successfully deleted ${completed} item(s)`);
                            }
                            toastr.error(`Failed to delete ${failed} item(s)`);
                        }
                    }
                });
            });
        }
    });

    // ============ LOAD CYCLES ============
    function loadCycles() {
        $.ajax({
            url: '{{ route("property.settings.cycles.data") }}',
            method: 'GET',
            success: function(cycles) {
                const select = $('#defaultCycle');
                select.find('option:not(:first)').remove();
                cycles.forEach(function(cycle) {
                    select.append(`<option value="${cycle.id}">${cycle.text}</option>`);
                });
            },
            error: function(xhr) {
                console.error('Error loading cycles:', xhr);
            }
        });
    }

    // Load cycles on page load
    loadCycles();

    // ============ RETURN TYPE CRUD ============
    
    // Add Return Type
    $('#addReturnTypeBtn').click(function() {
        $('#returnTypeForm')[0].reset();
        $('#returnTypeId').val('');
        $('#returnTypeModalLabel').text('Add Return Type');
        $('.invalid-feedback').text('').hide();
        $('.form-control').removeClass('is-invalid');
        $('#returnTypeModal').modal('show');
    });

    // Edit Return Type
    $('#returnTypesTable').on('click', '.edit-return-type', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        $('#returnTypeId').val(id);
        $('#returnTypeName').val(name);
        $('#returnTypeModalLabel').text('Edit Return Type');
        $('.invalid-feedback').text('').hide();
        $('.form-control').removeClass('is-invalid');
        $('#returnTypeModal').modal('show');
    });

    // Submit Return Type Form
    $('#returnTypeForm').submit(function(e) {
        e.preventDefault();
        
        const id = $('#returnTypeId').val();
        const url = id 
            ? '{{ route("property.settings.return-types.update", ":id") }}'.replace(':id', id)
            : '{{ route("property.settings.return-types.store") }}';
        const method = 'POST';
        
        $.ajax({
            url: url,
            method: method,
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $('#returnTypeModal').modal('hide');
                returnTypesTable.ajax.reload();
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

    // Delete Return Type
    $('#returnTypesTable').on('click', '.delete-return-type', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        if (confirm(`Are you sure you want to delete "${name}"?`)) {
            $.ajax({
                url: '{{ route("property.settings.return-types.delete", ":id") }}'.replace(':id', id),
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    returnTypesTable.ajax.reload();
                    toastr.success(response.message);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to delete return type');
                }
            });
        }
    });

    // ============ INVOICEABLE ITEM CRUD ============
    
    // Add Invoiceable Item
    $('#addInvoiceableItemBtn').click(function() {
        $('#invoiceableItemForm')[0].reset();
        $('#invoiceableItemId').val('');
        $('#invoiceableItemModalLabel').text('Add Invoiceable Item');
        $('.invalid-feedback').text('').hide();
        $('.form-control, .form-select').removeClass('is-invalid');
        $('#invoiceableItemModal').modal('show');
    });

    // Edit Invoiceable Item
    $('#invoiceableItemsTable').on('click', '.edit-invoiceable-item', function() {
        const data = $(this).data('item');
        
        $('#invoiceableItemId').val(data.encrypted_id);
        $('#itemName').val(data.item_name);
        $('#billingCode').val(data.billing_code !== '-' ? data.billing_code : '');
        $('#defaultCycle').val(data.default_cycle_id || '');
        $('#commissionable').val(data.commissionable);
        $('#advancePayable').val(data.advance_payable);
        $('#isBill').val(data.is_bill);
        $('#fixedAmount').val(data.fixed_amount);
        $('#amount').val(data.amount !== '0.00' ? data.amount.replace(/,/g, '') : '');
        
        $('#invoiceableItemModalLabel').text('Edit Invoiceable Item');
        $('.invalid-feedback').text('').hide();
        $('.form-control, .form-select').removeClass('is-invalid');
        $('#invoiceableItemModal').modal('show');
    });

    // Submit Invoiceable Item Form
    $('#invoiceableItemForm').submit(function(e) {
        e.preventDefault();
        
        const id = $('#invoiceableItemId').val();
        const url = id 
            ? '{{ route("property.settings.invoiceable-items.update", ":id") }}'.replace(':id', id)
            : '{{ route("property.settings.invoiceable-items.store") }}';
        const method = 'POST';
        
        $.ajax({
            url: url,
            method: method,
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $('#invoiceableItemModal').modal('hide');
                invoiceableItemsTable.ajax.reload();
                toastr.success(response.message);
            },
            error: function(xhr) {
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

    // Delete Invoiceable Item
    $('#invoiceableItemsTable').on('click', '.delete-invoiceable-item', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        if (confirm(`Are you sure you want to delete "${name}"?`)) {
            $.ajax({
                url: '{{ route("property.settings.invoiceable-items.delete", ":id") }}'.replace(':id', id),
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    invoiceableItemsTable.ajax.reload();
                    toastr.success(response.message);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to delete invoiceable item');
                }
            });
        }
    });
});
</script>
@endsection
