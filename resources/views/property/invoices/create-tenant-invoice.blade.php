@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/chabrin.css') }}">
<style>
    .card-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.85);
        z-index: 10;
        display: none;
        justify-content: center;
        align-items: center;
    }

    .card-overlay.active {
        display: flex;
    }

    .card.position-relative {
        position: relative;
    }

    .invoiceable-item-row {
        border-bottom: 1px solid #e9ecef;
        padding: 10px 0;
    }

    .invoiceable-item-row:last-child {
        border-bottom: none;
    }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card position-relative">
                    <div class="card-overlay" id="card-overlay">
                        <div class="text-center">
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Processing...</p>
                        </div>
                    </div>
                    <div class="card-header card-no-border">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">Create Tenant Invoice</h5>
                            <a href="{{ route('invoices.tenant.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span> Back to Invoices
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="create_invoice_form">
                            <!-- Step 1: Search and Select Tenant -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="tenant_search_input" class="form-label">Search Tenant</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="tenant_search_input"
                                            placeholder="Search by Name, Mobile Number, PIN, or Reference Number">
                                        <button type="button" class="btn btn-primary" id="search_tenant_btn">
                                            <span class="material-symbols-outlined" id="search_icon">search</span>
                                            <span class="spinner-border spinner-border-sm" role="status" id="search_spinner" style="display: none;"></span>
                                        </button>
                                    </div>
                                    <small class="text-muted">Enter at least 2 characters to search</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="tenant_select" class="form-label">Select Tenant</label>
                                    <select class="form-select" id="tenant_select" name="tenant_id" required disabled>
                                        <option value="">-- Select Tenant --</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Step 2: Tenant Details Card (appears after tenant selected) -->
                            <div id="tenant_details_section" style="display: none;">
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">
                                            <span class="material-symbols-outlined align-middle">person</span>
                                            Selected Tenant Details
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <p class="mb-2">
                                                    <strong>Name:</strong><br>
                                                    <span id="tenant_name" class="text-muted"></span>
                                                </p>
                                            </div>
                                            <div class="col-md-4">
                                                <p class="mb-2">
                                                    <strong>Mobile Number:</strong><br>
                                                    <span id="tenant_mobile" class="text-muted"></span>
                                                </p>
                                            </div>
                                            <div class="col-md-4">
                                                <p class="mb-2">
                                                    <strong>Email:</strong><br>
                                                    <span id="tenant_email" class="text-muted"></span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 3: Select Lease (appears after tenant details shown) -->
                            <div id="lease_section" style="display: none;">
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="lease_select" class="form-label">Select Lease Agreement <span class="text-danger">*</span></label>
                                        <select class="form-select" id="lease_select" name="lease_id" required disabled>
                                            <option value="">-- Select Lease --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 4: Lease Details (Read-only, appears after lease selected) -->
                            <div id="lease_details_section" style="display: none;">
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Lease Reference</label>
                                        <input type="text" class="form-control" id="lease_reference" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Property</label>
                                        <input type="text" class="form-control" id="lease_property" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Unit</label>
                                        <input type="text" class="form-control" id="lease_unit" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Lease Period</label>
                                        <input type="text" class="form-control" id="lease_period" readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 5: Invoice Details (appears after lease details shown) -->
                            <div id="invoice_details_section" style="display: none;">
                                <hr class="my-4">
                                <h6 class="mb-3">Invoice Details</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="due_date" class="form-label">Due Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="due_date" name="due_date" required disabled>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="narration" class="form-label">Narration <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="narration" name="narration"
                                            placeholder="e.g., Rent for January 2026" required disabled>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 6: Invoiceable Items (appears after invoice details filled) -->
                            <div id="items_section" style="display: none;">
                                <hr class="my-4">
                                <h6 class="mb-3">Select Invoice Items</h6>
                                <div id="invoiceable_items_container" class="mb-3"></div>

                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                    <h5 class="mb-0">Total Amount:</h5>
                                    <h4 class="mb-0 text-primary" id="total_amount">0.00</h4>
                                </div>
                            </div>

                            <!-- Submit Button (appears after items selected) -->
                            <div id="submit_section" style="display: none;" class="mt-4">
                                <hr class="my-4">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('invoices.tenant.index') }}" class="btn btn-secondary btn-sm">
                                        <span class="material-symbols-outlined">close</span> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-success btn-sm" id="submit_invoice_btn">
                                        <span class="spinner-border spinner-border-sm me-2 d-none" role="status" id="submit_spinner"></span>
                                        <span class="material-symbols-outlined" id="submit_icon">check_circle</span>
                                        <span>Create Invoice</span>
                                    </button>
                                </div>
                            </div>

                            <input type="hidden" id="property_id" name="property_id">
                            <input type="hidden" id="unit_id" name="unit_id">
                            <input type="hidden" id="client_id" name="client_id">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    $(document).ready(function() {
        let invoiceableItems = [];

        // Hide overlay initially
        $('#card-overlay').hide();

        // Search tenant
        $('#search_tenant_btn').on('click', function() {
            const searchTerm = $('#tenant_search_input').val().trim();

            if (searchTerm.length < 2) {
                toastr.warning('Please enter at least 2 characters to search');
                return;
            }

            $('#search_icon').hide();
            $('#search_spinner').show();
            $('#search_tenant_btn').prop('disabled', true);
            $('#tenant_search_input').prop('disabled', true);

            $.ajax({
                url: '/tenants/search',
                method: 'GET',
                data: {
                    search: searchTerm
                },
                success: function(response) {
                    if (response.success && response.tenants && response.tenants.length > 0) {
                        $('#tenant_select').empty().append('<option value="">-- Select Tenant --</option>');

                        $.each(response.tenants, function(index, tenant) {
                            $('#tenant_select').append(
                                `<option value="${tenant.encrypted_id}" 
                                     data-name="${tenant.names}" 
                                     data-mobile="${tenant.mobile_number || 'N/A'}" 
                                     data-email="${tenant.email_address || 'N/A'}" 
                                     data-reference="${tenant.reference_number || 'N/A'}">
                                ${tenant.names} - Mobile: ${tenant.mobile_number || 'N/A'} - PIN: ${tenant.national_id || 'N/A'} - Ref: ${tenant.reference_number || 'N/A'}
                            </option>`
                            );
                        });

                        $('#tenant_select').prop('disabled', false);
                        toastr.success(`Found ${response.tenants.length} tenant(s)`);
                    } else {
                        toastr.info('No tenants found');
                        $('#tenant_select').empty().append('<option value="">-- No Results --</option>').prop('disabled', true);
                    }
                },
                error: function(xhr) {
                    toastr.error('Error searching tenants');
                    console.error(xhr);
                },
                complete: function() {
                    $('#search_icon').show();
                    $('#search_spinner').hide();
                    $('#search_tenant_btn').prop('disabled', false);
                    $('#tenant_search_input').prop('disabled', false);
                }
            });
        });

        // Allow search with Enter key
        $('#tenant_search_input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#search_tenant_btn').click();
            }
        });

        // When tenant is selected
        $('#tenant_select').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const tenantId = $(this).val();

            if (tenantId) {
                // Show tenant details in card
                $('#tenant_name').text(selectedOption.data('name'));
                $('#tenant_mobile').text(selectedOption.data('mobile'));
                $('#tenant_email').text(selectedOption.data('email'));
                $('#tenant_details_section').slideDown();

                // Load leases for this tenant
                loadLeases(tenantId);
            } else {
                // Hide all subsequent sections
                $('#tenant_details_section, #lease_section, #lease_details_section, #invoice_details_section, #items_section, #submit_section').hide();
                $('#lease_select').empty().append('<option value="">-- Select Lease --</option>').prop('disabled', true);
            }
        });

        // Load leases for selected tenant
        function loadLeases(tenantId) {
            $('#card-overlay').show();

            $.ajax({
                url: '/ajax/invoices/tenant/' + tenantId + '/leases',
                type: 'GET',
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        $('#lease_select').empty().append('<option value="">-- Select Lease --</option>');

                        $.each(response.data, function(index, lease) {
                            $('#lease_select').append(
                                `<option value="${lease.encrypted_id}"
                                     data-reference="${lease.reference_number}"
                                     data-property="${lease.property_name}"
                                     data-property-id="${lease.property_id}"
                                     data-unit="${lease.unit_name}"
                                     data-unit-id="${lease.unit_id}"
                                     data-client-id="${lease.client_id}"
                                     data-start="${lease.lease_start_date}"
                                     data-end="${lease.lease_end_date}">
                                ${lease.reference_number} - ${lease.property_name} (${lease.unit_name})
                            </option>`
                            );
                        });

                        $('#lease_select').prop('disabled', false);
                        $('#lease_section').slideDown();
                    } else {
                        toastr.warning('No active leases found for this tenant');
                    }
                },
                error: function(xhr) {
                    toastr.error('Error loading leases');
                    console.error(xhr);
                },
                complete: function() {
                    $('#card-overlay').hide();
                }
            });
        }

        // When lease is selected
        $('#lease_select').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const leaseId = $(this).val();

            if (leaseId) {
                // Show lease details
                $('#lease_reference').val(selectedOption.data('reference'));
                $('#lease_property').val(selectedOption.data('property'));
                $('#lease_unit').val(selectedOption.data('unit'));
                $('#lease_period').val(selectedOption.data('start') + ' to ' + selectedOption.data('end'));

                // Store hidden fields
                $('#property_id').val(selectedOption.data('property-id'));
                $('#unit_id').val(selectedOption.data('unit-id'));
                $('#client_id').val(selectedOption.data('client-id'));

                $('#lease_details_section').slideDown();

                // Enable invoice details fields
                $('#due_date, #narration').prop('disabled', false);
                $('#invoice_details_section').slideDown();

                // Load invoiceable items
                loadInvoiceableItems();
            } else {
                $('#lease_details_section, #invoice_details_section, #items_section, #submit_section').hide();
                $('#due_date, #narration').prop('disabled', true);
            }
        });

        // Load invoiceable items
        function loadInvoiceableItems() {
            if (invoiceableItems.length > 0) {
                renderInvoiceableItems();
                return;
            }

            $('#card-overlay').show();

            $.ajax({
                url: '/ajax/invoices/invoiceable-items',
                type: 'GET',
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        invoiceableItems = response.data;
                        renderInvoiceableItems();
                    } else {
                        toastr.warning('No invoiceable items found');
                    }
                },
                error: function(xhr) {
                    toastr.error('Error loading invoiceable items');
                    console.error(xhr);
                },
                complete: function() {
                    $('#card-overlay').hide();
                }
            });
        }

        // Render invoiceable items
        function renderInvoiceableItems() {
            let html = `
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th width="50px">Select</th>
                            <th>Item Name</th>
                            <th>Billing Code</th>
                            <th width="120px">Quantity</th>
                            <th width="150px">Amount</th>
                            <th width="150px">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

            $.each(invoiceableItems, function(index, item) {
                html += `
                <tr class="invoiceable-item-row">
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input item-checkbox" 
                               data-item-id="${item.id}" 
                               data-item-name="${item.item_name}"
                               data-billing-code="${item.billing_code}">
                    </td>
                    <td>${item.item_name}</td>
                    <td>${item.billing_code || 'N/A'}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm item-quantity" 
                               data-item-id="${item.id}" value="1" min="1" disabled>
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm item-amount" 
                               data-item-id="${item.id}" value="0" min="0" step="0.01" disabled>
                    </td>
                    <td class="text-end">
                        <strong class="item-line-total" data-item-id="${item.id}">0.00</strong>
                    </td>
                </tr>
            `;
            });

            html += `
                    </tbody>
                </table>
            </div>
        `;

            $('#invoiceable_items_container').html(html);
            $('#items_section').slideDown();

            // Watch for invoice details changes to enable items section
            $('#due_date, #narration').on('change keyup', function() {
                checkInvoiceDetailsComplete();
            });
        }

        // Check if invoice details are complete
        function checkInvoiceDetailsComplete() {
            const dueDate = $('#due_date').val();
            const narration = $('#narration').val().trim();

            if (dueDate && narration) {
                $('.item-checkbox').prop('disabled', false);
            }
        }

        // Handle item checkbox change
        $(document).on('change', '.item-checkbox', function() {
            const itemId = $(this).data('item-id');
            const isChecked = $(this).is(':checked');

            $(`input.item-quantity[data-item-id="${itemId}"]`).prop('disabled', !isChecked);
            $(`input.item-amount[data-item-id="${itemId}"]`).prop('disabled', !isChecked);

            if (!isChecked) {
                $(`input.item-quantity[data-item-id="${itemId}"]`).val(1);
                $(`input.item-amount[data-item-id="${itemId}"]`).val(0);
            }

            calculateLineTotal(itemId);
            calculateGrandTotal();
            checkIfItemsSelected();
        });

        // Handle quantity and amount changes
        $(document).on('input change', '.item-quantity, .item-amount', function() {
            const itemId = $(this).data('item-id');
            calculateLineTotal(itemId);
            calculateGrandTotal();
        });

        // Calculate line total
        function calculateLineTotal(itemId) {
            const quantity = parseFloat($(`input.item-quantity[data-item-id="${itemId}"]`).val()) || 0;
            const amount = parseFloat($(`input.item-amount[data-item-id="${itemId}"]`).val()) || 0;
            const lineTotal = quantity * amount;

            $(`.item-line-total[data-item-id="${itemId}"]`).text(lineTotal.toFixed(2));
        }

        // Calculate grand total
        function calculateGrandTotal() {
            let grandTotal = 0;

            $('.item-checkbox:checked').each(function() {
                const itemId = $(this).data('item-id');
                const lineTotal = parseFloat($(`.item-line-total[data-item-id="${itemId}"]`).text()) || 0;
                grandTotal += lineTotal;
            });

            $('#total_amount').text(grandTotal.toFixed(2));
        }

        // Check if any items are selected
        function checkIfItemsSelected() {
            const hasSelectedItems = $('.item-checkbox:checked').length > 0;

            if (hasSelectedItems) {
                $('#submit_section').slideDown();
            } else {
                $('#submit_section').hide();
            }
        }

        // Form submission
        $('#create_invoice_form').on('submit', function(e) {
            e.preventDefault();

            // Validate items selected
            if ($('.item-checkbox:checked').length === 0) {
                toastr.warning('Please select at least one invoice item');
                return;
            }

            // Gather line items
            const lineItems = [];
            $('.item-checkbox:checked').each(function() {
                const itemId = $(this).data('item-id');
                const itemName = $(this).data('item-name');
                const billingCode = $(this).data('billing-code');
                const quantity = parseFloat($(`input.item-quantity[data-item-id="${itemId}"]`).val()) || 0;
                const amount = parseFloat($(`input.item-amount[data-item-id="${itemId}"]`).val()) || 0;
                const lineTotal = quantity * amount;

                if (amount > 0) {
                    lineItems.push({
                        item_id: itemId,
                        item_name: itemName,
                        billing_code: billingCode,
                        quantity: quantity,
                        amount: amount,
                        line_total: lineTotal
                    });
                }
            });

            if (lineItems.length === 0) {
                toastr.warning('Please set amounts for selected items');
                return;
            }

            // Prepare form data
            const formData = {
                _token: '{{ csrf_token() }}',
                tenant_id: $('#tenant_select').val(),
                lease_id: $('#lease_select').val(),
                property_id: $('#property_id').val(),
                unit_id: $('#unit_id').val(),
                client_id: $('#client_id').val(),
                due_date: $('#due_date').val(),
                narration: $('#narration').val(),
                line_items: lineItems
            };

            $('#submit_icon').hide();
            $('#submit_spinner').removeClass('d-none');
            $('#submit_invoice_btn').prop('disabled', true);
            $('#card-overlay').show();

            $.ajax({
                url: '/ajax/invoices/tenant/store',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message || 'Invoice created successfully!');
                        setTimeout(function() {
                            window.location.href = '/tenants/invoices';
                        }, 1500);
                    } else {
                        toastr.error(response.message || 'Error creating invoice');
                        $('#submit_icon').show();
                        $('#submit_spinner').addClass('d-none');
                        $('#submit_invoice_btn').prop('disabled', false);
                        $('#card-overlay').hide();
                    }
                },
                error: function(xhr) {
                    let errorMessage = 'Error creating invoice';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    toastr.error(errorMessage);
                    console.error(xhr);

                    $('#submit_icon').show();
                    $('#submit_spinner').addClass('d-none');
                    $('#submit_invoice_btn').prop('disabled', false);
                    $('#card-overlay').hide();
                }
            });
        });
    });
</script>
@endpush