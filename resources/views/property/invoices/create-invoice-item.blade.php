@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Create Invoice</h5>
                        <a href="{{ route('invoices.tenant.items.index') }}" class="btn btn-secondary btn-sm">
                            <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">arrow_back</span>
                            Back to List
                        </a>
                    </div>
                    <div class="card-body">
                        <form id="createInvoiceForm">
                            @csrf
                            <!-- Tenant Search Section -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="tenant_search_input" class="form-label">Search Tenant <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control form-control-sm" id="tenant_search_input"
                                            placeholder="Search by Name, Mobile Number, PIN, or Reference Number">
                                        <button type="button" class="btn btn-primary btn-sm" id="search_tenant_btn">
                                            <span class="material-symbols-outlined" id="search_tenant_icon">search</span>
                                            <span class="spinner-border spinner-border-sm" role="status" id="search_tenant_spinner" style="display: none;"></span>
                                        </button>
                                    </div>
                                    <small class="text-muted">Enter at least 2 characters to search</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="tenant_select" class="form-label">Select Tenant <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="tenant_select" name="tenant_id" disabled required>
                                        <option value="">-- Select Tenant --</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Tenant Details -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <small class="text-muted">
                                        <strong>Name:</strong> <span id="selected_tenant_name">-</span> |
                                        <strong>Phone:</strong> <span id="selected_tenant_mobile">-</span> |
                                        <strong>Email:</strong> <span id="selected_tenant_email">-</span> |
                                        <strong>ID Number:</strong> <span id="selected_tenant_id">-</span>
                                    </small>
                                </div>
                            </div>

                            <!-- Readonly Fields for Property, Unit, Lease -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="property_name" class="form-label">Property</label>
                                    <input type="text" class="form-control form-control-sm" id="property_name" readonly>
                                    <input type="hidden" id="property_id" name="property_id">
                                </div>
                                <div class="col-md-4">
                                    <label for="unit_name" class="form-label">Unit</label>
                                    <input type="text" class="form-control form-control-sm" id="unit_name" readonly>
                                    <input type="hidden" id="unit_id" name="unit_id">
                                </div>
                                <div class="col-md-4">
                                    <label for="lease_reference" class="form-label">Lease</label>
                                    <input type="text" class="form-control form-control-sm" id="lease_reference" readonly>
                                    <input type="hidden" id="lease_id" name="lease_id">
                                </div>
                            </div>

                            <hr>

                            <!-- Invoice Details -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="item_type_id" class="form-label">Item Type <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="item_type_id" name="item_type_id" required>
                                        <option value="">-- Select Item Type --</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="date_due" class="form-label">Date Due <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm" id="date_due" name="date_due" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="returns_period" class="form-label">Returns Period <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm" id="returns_period" name="returns_period" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="total_amount" class="form-label">Total Amount <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" id="total_amount" name="total_amount" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="return_report_type" class="form-label">Return Report Type <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="return_report_type" name="return_report_type" required>
                                        <option value="">-- Select --</option>
                                        <option value="Main Returns">Main Returns</option>
                                        <option value="Bills Returns Options">Bills Returns Options</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="is_arrear" class="form-label">Is Arrear <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="is_arrear" name="is_arrear" required>
                                        <option value="">-- Select --</option>
                                        <option value="Yes">Yes</option>
                                        <option value="No">No</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary" id="submit_btn">
                                        <span class="spinner-border spinner-border-sm" role="status" id="submit_spinner" style="display: none;"></span>
                                        <span class="material-symbols-outlined" id="submit_icon" style="font-size: 16px; vertical-align: middle;">save</span>
                                        <span class="ms-1">Create Invoice</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        let tenantsData = [];

        // Load invoiceable items
        $.ajax({
            url: '{{ route("ajax.invoices.invoiceable.items") }}',
            type: 'GET',
            success: function(response) {
                if (response.success && response.items) {
                    response.items.forEach(function(item) {
                        $('#item_type_id').append(`<option value="${item.id}">${item.item_name}</option>`);
                    });
                }
            }
        });

        // Tenant search
        $('#search_tenant_btn').on('click', function() {
            const searchTerm = $('#tenant_search_input').val().trim();

            if (searchTerm.length < 2) {
                toastr.warning('Please enter at least 2 characters');
                return;
            }

            $('#search_tenant_icon').hide();
            $('#search_tenant_spinner').show();
            $('#search_tenant_btn').prop('disabled', true);

            $.ajax({
                url: '{{ route("utility.tenants.search") }}',
                type: 'GET',
                data: {
                    search: searchTerm
                },
                success: function(response) {
                    if (response.success && response.tenants && response.tenants.length > 0) {
                        tenantsData = response.tenants;
                        $('#tenant_select').prop('disabled', false).html('<option value="">-- Select Tenant --</option>');

                        response.tenants.forEach(function(tenant) {
                            $('#tenant_select').append(`
                                <option value="${tenant.id}">${tenant.names} - ${tenant.mobile_number || 'N/A'}</option>
                            `);
                        });

                        toastr.success(`Found ${response.tenants.length} tenant(s)`);
                    } else {
                        toastr.info('No tenants found');
                    }
                },
                error: function() {
                    toastr.error('Error searching tenants');
                },
                complete: function() {
                    $('#search_tenant_icon').show();
                    $('#search_tenant_spinner').hide();
                    $('#search_tenant_btn').prop('disabled', false);
                }
            });
        });

        // Tenant selection
        $('#tenant_select').on('change', function() {
            const tenantId = $(this).val();
            if (!tenantId) {
                // Clear fields
                $('#selected_tenant_name').text('-');
                $('#selected_tenant_mobile').text('-');
                $('#selected_tenant_email').text('-');
                $('#selected_tenant_id').text('-');
                $('#property_name').val('');
                $('#property_id').val('');
                $('#unit_name').val('');
                $('#unit_id').val('');
                $('#lease_reference').val('');
                $('#lease_id').val('');
                return;
            }

            // Fetch tenant details from database
            $.ajax({
                url: `/tenants/${tenantId}/details`,
                type: 'GET',
                success: function(response) {
                    if (response.success && response.tenant) {
                        const tenant = response.tenant;

                        // Display tenant details
                        $('#selected_tenant_name').text(tenant.names || '-');
                        $('#selected_tenant_mobile').text(tenant.mobile_number || '-');
                        $('#selected_tenant_email').text(tenant.email || '-');
                        $('#selected_tenant_id').text(tenant.national_id || '-');

                        // Populate property, unit, lease fields
                        $('#property_name').val(tenant.property_name || '-');
                        $('#property_id').val(tenant.property_id || '');

                        $('#unit_name').val(tenant.unit_name || '-');
                        $('#unit_id').val(tenant.unit_id || '');

                        $('#lease_reference').val(tenant.lease_reference || '-');
                        $('#lease_id').val(tenant.lease_id || '');
                    }
                },
                error: function() {
                    toastr.error('Error fetching tenant details');
                }
            });
        });

        // Form submission
        $('#createInvoiceForm').on('submit', function(e) {
            e.preventDefault();

            $('#submit_spinner').show();
            $('#submit_icon').hide();
            $('#submit_btn').prop('disabled', true);

            const formData = {
                tenant_id: $('#tenant_select').val(),
                property_id: $('#property_id').val(),
                unit_id: $('#unit_id').val(),
                lease_id: $('#lease_id').val(),
                item_type_id: $('#item_type_id').val(),
                date_due: $('#date_due').val(),
                returns_period: $('#returns_period').val(),
                total_amount: $('#total_amount').val(),
                return_report_type: $('#return_report_type').val(),
                is_arrear: $('#is_arrear').val(),
                _token: '{{ csrf_token() }}'
            };

            $.ajax({
                url: '{{ route("ajax.invoices.items.store") }}',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        setTimeout(function() {
                            window.location.href = '{{ route("invoices.tenant.items.index") }}';
                        }, 1500);
                    } else {
                        toastr.error(response.message || 'Failed to create invoice');
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422 && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        let errorMsg = 'Validation errors:\n';
                        Object.keys(errors).forEach(key => {
                            errorMsg += errors[key][0] + '\n';
                        });
                        toastr.error(errorMsg);
                    } else {
                        toastr.error('Failed to create invoice');
                    }
                },
                complete: function() {
                    $('#submit_spinner').hide();
                    $('#submit_icon').show();
                    $('#submit_btn').prop('disabled', false);
                }
            });
        });
    });
</script>
@endsection