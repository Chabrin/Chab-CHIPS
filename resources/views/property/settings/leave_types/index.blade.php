@extends('layouts.main')

@section('content')
<style>
    .btn-check:checked + .applies-to-radio {
        color: #fff;
        background-color: #3598db;
        border-color: #3598db;
    }
</style>
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <h5 class="mb-0">Property Settings — Items</h5>
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

                        <ul class="nav nav-tabs mb-3" id="settingsItemsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="return-types-tab" data-bs-toggle="tab" data-bs-target="#return-types" type="button" role="tab">
                                    Return Types
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="unit-topology-tab" data-bs-toggle="tab" data-bs-target="#unit-topology" type="button" role="tab">
                                    Unit Topology
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="unit-category-tab" data-bs-toggle="tab" data-bs-target="#unit-category" type="button" role="tab">
                                    Unit Category
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="invoiceable-items-tab" data-bs-toggle="tab" data-bs-target="#invoiceable-items" type="button" role="tab">
                                    Invoiceable Items
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="settingsItemsTabsContent">
                            <!-- Return Types Tab -->
                            <div class="tab-pane fade show active" id="return-types" role="tabpanel" aria-labelledby="return-types-tab">
                                <div class="d-flex align-items-center mb-3">
                                    <h6 class="mb-0 me-auto">Return Types</h6>
                                    @permission('create_property_settings_items')
                                    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addReturnTypeBtn">
                                        <span class="material-symbols-outlined">add_circle</span>
                                        <span class="ms-1">Add Return Type</span>
                                    </button>
                                    @endpermission
                                </div>
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

                            <!-- Unit Topology Tab -->
                            <div class="tab-pane fade" id="unit-topology" role="tabpanel" aria-labelledby="unit-topology-tab">
                                <div class="d-flex align-items-center mb-3">
                                    <h6 class="mb-0 me-auto">Unit Topology</h6>
                                    @permission('create_property_settings_items')
                                    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addUnitTopologyBtn">
                                        <span class="material-symbols-outlined">add_circle</span>
                                        <span class="ms-1">Add Topology</span>
                                    </button>
                                    @endpermission
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="unitTopologiesTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>Topology Name</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Unit Category Tab -->
                            <div class="tab-pane fade" id="unit-category" role="tabpanel" aria-labelledby="unit-category-tab">
                                <div class="d-flex align-items-center mb-3">
                                    <h6 class="mb-0 me-auto">Unit Category</h6>
                                    @permission('create_property_settings_items')
                                    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addUnitCategoryBtn">
                                        <span class="material-symbols-outlined">add_circle</span>
                                        <span class="ms-1">Add Category</span>
                                    </button>
                                    @endpermission
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="unitCategoriesTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>Category Name</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Invoiceable Items Tab -->
                            <div class="tab-pane fade" id="invoiceable-items" role="tabpanel" aria-labelledby="invoiceable-items-tab">
                                <div class="d-flex align-items-center mb-3">
                                    <h6 class="mb-0 me-auto">Invoiceable Items</h6>
                                    @permission('create_property_settings_items')
                                    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addInvoiceableItemBtn">
                                        <span class="material-symbols-outlined">add_circle</span>
                                        <span class="ms-1">Add Invoiceable Item</span>
                                    </button>
                                    @endpermission
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="invoiceableItemsTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>Item Name</th>
                                                <th>Applies To</th>
                                                <th>Billing Code</th>
                                                <th>Default Cycle</th>
                                                <th>Commissionable</th>
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

<!-- Unit Topology Modal -->
<div class="modal fade" id="unitTopologyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="unitTopologyModalLabel">Add Topology</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="unitTopologyForm">
                <div class="modal-body">
                    <input type="hidden" id="unitTopologyId" name="id">
                    <div class="mb-3">
                        <label for="unitTopologyName" class="form-label">Topology Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="unitTopologyName" name="topology_name" required>
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

<!-- Unit Category Modal -->
<div class="modal fade" id="unitCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="unitCategoryModalLabel">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="unitCategoryForm">
                <div class="modal-body">
                    <input type="hidden" id="unitCategoryId" name="id">
                    <div class="mb-3">
                        <label for="unitCategoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="unitCategoryName" name="category_name" required>
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
                        <div class="col-md-6 mb-3">
                            <label for="itemName" class="form-label">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="itemName" name="item_name" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label d-block">Applies To <span class="text-danger">*</span></label>
                            <div class="btn-group" role="group" aria-label="Applies To">
                                <input type="radio" class="btn-check" name="applies_to" id="appliesToTenant" value="TENANT" autocomplete="off">
                                <label class="btn btn-outline-primary applies-to-radio btn-sm" for="appliesToTenant">
                                    <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">person</span>
                                    Tenant
                                </label>

                                <input type="radio" class="btn-check" name="applies_to" id="appliesToClient" value="CLIENT" autocomplete="off">
                                <label class="btn btn-outline-primary applies-to-radio btn-sm" for="appliesToClient">
                                    <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">apartment</span>
                                    Client
                                </label>

                                <input type="radio" class="btn-check" name="applies_to" id="appliesToBoth" value="BOTH" checked autocomplete="off">
                                <label class="btn btn-outline-primary applies-to-radio btn-sm" for="appliesToBoth">
                                    <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">done_all</span>
                                    Both
                                </label>
                            </div>
                            <div class="invalid-feedback" id="appliesToFeedback"></div>
                        </div>
                    </div>
                    <div class="row">
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
                        <div class="col-md-4 mb-3">
                            <label for="advancePayable" class="form-label">Advance Payable</label>
                            <select class="form-select" id="advancePayable" name="advance_payable">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="isBill" class="form-label">Is Bill</label>
                            <select class="form-select" id="isBill" name="is_bill">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="fixedAmount" class="form-label">Fixed Amount</label>
                            <select class="form-select" id="fixedAmount" name="fixed_amount">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
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

    // Keep DataTables column widths correct when a table is initialized inside a hidden tab
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    });

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

    // ============ UNIT TOPOLOGIES TABLE ============
    const unitTopologiesTable = $('#unitTopologiesTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '{{ route("property.settings.unit-topologies.data") }}',
            type: 'GET',
            dataSrc: 'data',
            error: function(xhr) {
                console.error('Error loading unit topologies:', xhr);
            }
        },
        columns: [
            { data: 'topology_name' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data) {
                    let actions = '';
                    if (data.can_update) {
                        actions += `<a href="javascript:void(0)" class="text-success edit-unit-topology me-2" data-id="${data.encrypted_id}" data-name="${data.topology_name}" title="Edit">
                            <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                        </a>`;
                    }
                    if (data.can_delete) {
                        actions += `<a href="javascript:void(0)" class="text-danger delete-unit-topology" data-id="${data.encrypted_id}" data-name="${data.topology_name}" title="Delete">
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

    // ============ UNIT CATEGORIES TABLE ============
    const unitCategoriesTable = $('#unitCategoriesTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '{{ route("property.settings.unit-categories.data") }}',
            type: 'GET',
            dataSrc: 'data',
            error: function(xhr) {
                console.error('Error loading unit categories:', xhr);
            }
        },
        columns: [
            { data: 'category_name' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data) {
                    let actions = '';
                    if (data.can_update) {
                        actions += `<a href="javascript:void(0)" class="text-success edit-unit-category me-2" data-id="${data.encrypted_id}" data-name="${data.category_name}" title="Edit">
                            <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                        </a>`;
                    }
                    if (data.can_delete) {
                        actions += `<a href="javascript:void(0)" class="text-danger delete-unit-category" data-id="${data.encrypted_id}" data-name="${data.category_name}" title="Delete">
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
            { data: 'item_name' },
            { data: 'applies_to' },
            { data: 'billing_code' },
            { data: 'cycle_name' },
            { data: 'commissionable' },
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
        order: [[0, 'asc']],
        pageLength: 10,
        responsive: true
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

    // ============ UNIT TOPOLOGY CRUD ============

    // Add Unit Topology
    $('#addUnitTopologyBtn').click(function() {
        $('#unitTopologyForm')[0].reset();
        $('#unitTopologyId').val('');
        $('#unitTopologyModalLabel').text('Add Topology');
        $('.invalid-feedback').text('').hide();
        $('.form-control').removeClass('is-invalid');
        $('#unitTopologyModal').modal('show');
    });

    // Edit Unit Topology
    $('#unitTopologiesTable').on('click', '.edit-unit-topology', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        $('#unitTopologyId').val(id);
        $('#unitTopologyName').val(name);
        $('#unitTopologyModalLabel').text('Edit Topology');
        $('.invalid-feedback').text('').hide();
        $('.form-control').removeClass('is-invalid');
        $('#unitTopologyModal').modal('show');
    });

    // Submit Unit Topology Form
    $('#unitTopologyForm').submit(function(e) {
        e.preventDefault();

        const id = $('#unitTopologyId').val();
        const url = id
            ? '{{ route("property.settings.unit-topologies.update", ":id") }}'.replace(':id', id)
            : '{{ route("property.settings.unit-topologies.store") }}';

        $.ajax({
            url: url,
            method: 'POST',
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $('#unitTopologyModal').modal('hide');
                unitTopologiesTable.ajax.reload();
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

    // Delete Unit Topology
    $('#unitTopologiesTable').on('click', '.delete-unit-topology', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        if (confirm(`Are you sure you want to delete "${name}"?`)) {
            $.ajax({
                url: '{{ route("property.settings.unit-topologies.delete", ":id") }}'.replace(':id', id),
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    unitTopologiesTable.ajax.reload();
                    toastr.success(response.message);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to delete unit topology');
                }
            });
        }
    });

    // ============ UNIT CATEGORY CRUD ============

    // Add Unit Category
    $('#addUnitCategoryBtn').click(function() {
        $('#unitCategoryForm')[0].reset();
        $('#unitCategoryId').val('');
        $('#unitCategoryModalLabel').text('Add Category');
        $('.invalid-feedback').text('').hide();
        $('.form-control').removeClass('is-invalid');
        $('#unitCategoryModal').modal('show');
    });

    // Edit Unit Category
    $('#unitCategoriesTable').on('click', '.edit-unit-category', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        $('#unitCategoryId').val(id);
        $('#unitCategoryName').val(name);
        $('#unitCategoryModalLabel').text('Edit Category');
        $('.invalid-feedback').text('').hide();
        $('.form-control').removeClass('is-invalid');
        $('#unitCategoryModal').modal('show');
    });

    // Submit Unit Category Form
    $('#unitCategoryForm').submit(function(e) {
        e.preventDefault();

        const id = $('#unitCategoryId').val();
        const url = id
            ? '{{ route("property.settings.unit-categories.update", ":id") }}'.replace(':id', id)
            : '{{ route("property.settings.unit-categories.store") }}';

        $.ajax({
            url: url,
            method: 'POST',
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $('#unitCategoryModal').modal('hide');
                unitCategoriesTable.ajax.reload();
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

    // Delete Unit Category
    $('#unitCategoriesTable').on('click', '.delete-unit-category', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        if (confirm(`Are you sure you want to delete "${name}"?`)) {
            $.ajax({
                url: '{{ route("property.settings.unit-categories.delete", ":id") }}'.replace(':id', id),
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    unitCategoriesTable.ajax.reload();
                    toastr.success(response.message);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to delete unit category');
                }
            });
        }
    });

    // ============ INVOICEABLE ITEM CRUD ============

    // Add Invoiceable Item
    $('#addInvoiceableItemBtn').click(function() {
        $('#invoiceableItemForm')[0].reset();
        $('#invoiceableItemId').val('');
        $('#appliesToBoth').prop('checked', true);
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
        $('input[name="applies_to"][value="' + (data.applies_to || 'BOTH') + '"]').prop('checked', true);
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
                        if (field === 'applies_to') {
                            $('#appliesToFeedback').text(messages[0]).show();
                        } else {
                            input.siblings('.invalid-feedback').text(messages[0]).show();
                        }
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
