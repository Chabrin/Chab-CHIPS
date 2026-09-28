@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Allocate Payment to Invoice</h5>
                        <a href="{{ route('tenant.payments.index') }}" class="btn btn-secondary btn-sm">
                            <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">arrow_back</span>
                            Back to Payments
                        </a>
                    </div>
                    <div class="card-body">
                        <form id="allocatePaymentForm">
                            @csrf

                            <!-- Payment Search Section -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">payments</span> Payment Details</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="payment_search_input" class="form-label">Search Payment <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="text" class="form-control form-control-sm" id="payment_search_input"
                                                    placeholder="Search by Reference Number or Receipt Number">
                                                <button type="button" class="btn btn-primary btn-sm" id="search_payment_btn">
                                                    <span class="material-symbols-outlined" id="search_payment_icon">search</span>
                                                    <span class="spinner-border spinner-border-sm" role="status" id="search_payment_spinner" style="display: none;"></span>
                                                </button>
                                            </div>
                                            <small class="text-muted">Enter at least 3 characters to search</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="payment_select" class="form-label">Select Payment <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-sm" id="payment_select" disabled required>
                                                <option value="">-- Select Payment --</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Payment Details -->
                                    <div id="payment_details_section" style="display: none;">
                                        <div class="row mb-2">
                                            <div class="col-md-3">
                                                <small><strong>Reference:</strong> <span id="payment_reference" class="text-primary">-</span></small>
                                            </div>
                                            <div class="col-md-3">
                                                <small><strong>Payment Date:</strong> <span id="payment_date">-</span></small>
                                            </div>
                                            <div class="col-md-3">
                                                <small><strong>Tenant:</strong> <span id="payment_tenant">-</span></small>
                                            </div>
                                            <div class="col-md-3">
                                                <small><strong>Property:</strong> <span id="payment_property">-</span></small>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <small><strong>Total Amount:</strong> <span id="payment_amount" class="badge badge-primary">0.00</span></small>
                                            </div>
                                            <div class="col-md-3">
                                                <small><strong>Allocated:</strong> <span id="payment_allocated" class="badge badge-info">0.00</span></small>
                                            </div>
                                            <div class="col-md-3">
                                                <small><strong>Available:</strong> <span id="payment_available" class="badge badge-primary">0.00</span></small>
                                            </div>
                                            <div class="col-md-3">
                                                <small><strong>Status:</strong> <span id="payment_status_badge">-</span></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice Search Section -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">receipt_long</span> Invoice Details</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="invoice_search_input" class="form-label">Search Invoice <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="text" class="form-control form-control-sm" id="invoice_search_input"
                                                    placeholder="Search by Invoice Reference Number" disabled>
                                                <button type="button" class="btn btn-primary btn-sm" id="search_invoice_btn" disabled>
                                                    <span class="material-symbols-outlined" id="search_invoice_icon">search</span>
                                                    <span class="spinner-border spinner-border-sm" role="status" id="search_invoice_spinner" style="display: none;"></span>
                                                </button>
                                            </div>
                                            <small class="text-muted">Select a payment first to search invoices</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="invoice_select" class="form-label">Select Invoice <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-sm" id="invoice_select" disabled required>
                                                <option value="">-- Select Invoice --</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Invoice Details -->
                                    <div id="invoice_details_section" style="display: none;">
                                        <div class="row mb-2">
                                            <div class="col-md-4">
                                                <small><strong>Reference:</strong> <span id="invoice_reference" class="text-primary">-</span></small>
                                            </div>
                                            <div class="col-md-4">
                                                <small><strong>Date:</strong> <span id="invoice_date">-</span></small>
                                            </div>
                                            <div class="col-md-4">
                                                <small><strong>Due Date:</strong> <span id="invoice_due_date">-</span></small>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <small><strong>Total Amount:</strong> <span id="invoice_amount" class="badge badge-primary">0.00</span></small>
                                            </div>
                                            <div class="col-md-3">
                                                <small><strong>Paid Amount:</strong> <span id="invoice_paid" class="badge badge-info">0.00</span></small>
                                            </div>
                                            <div class="col-md-3">
                                                <small><strong>Balance:</strong> <span id="invoice_balance" class="badge badge-warning">0.00</span></small>
                                            </div>
                                            <div class="col-md-3">
                                                <small><strong>Status:</strong> <span id="invoice_status_badge">-</span></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- Allocation Amount -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">calculate</span> Allocation Details</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="amount_to_allocate" class="form-label">Amount to Allocate <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control form-control-sm" id="amount_to_allocate"
                                                name="amount_to_allocate" step="0.01" min="0.01" required disabled>
                                            <small class="text-muted">Maximum allocatable: <span id="max_allocatable" class="text-primary fw-bold">0.00</span></small>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="payment_priority" class="form-label">Payment Priority</label>
                                            <input type="text" class="form-control form-control-sm" id="payment_priority"
                                                name="payment_priority" placeholder="Optional" disabled>
                                            <small class="text-muted">e.g., Priority 1, Urgent, etc.</small>
                                        </div>
                                    </div>

                                    <!-- Allocation Summary -->
                                    <div id="allocation_summary" class="alert alert-info" style="display: none;">
                                        <h6 class="alert-heading"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">info</span> Allocation Summary</h6>
                                        <hr>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <small><strong>Payment Available:</strong> <span id="summary_payment_available">0.00</span></small><br>
                                                <small><strong>Invoice Balance:</strong> <span id="summary_invoice_balance">0.00</span></small>
                                            </div>
                                            <div class="col-md-6">
                                                <small><strong>Maximum Allocatable:</strong> <span id="summary_max_allocatable" class="text-primary fw-bold">0.00</span></small><br>
                                                <small><strong>Amount to Allocate:</strong> <span id="summary_amount_allocate" class="text-primary fw-bold">0.00</span></small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary" id="submit_btn" disabled>
                                                <span class="spinner-border spinner-border-sm" role="status" id="submit_spinner" style="display: none;"></span>
                                                <span class="material-symbols-outlined" id="submit_icon" style="font-size: 16px; vertical-align: middle;">check_circle</span>
                                                <span class="ms-1">Allocate Payment</span>
                                            </button>
                                            <button type="button" class="btn btn-secondary" id="reset_btn">
                                                <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">refresh</span>
                                                <span class="ms-1">Reset Form</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Existing Allocations -->
                        <div class="card" id="existing_allocations_card" style="display: none;">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">list</span> Existing Allocations for Selected Payment</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Invoice Reference</th>
                                                <th>Date</th>
                                                <th class="text-end">Amount Allocated</th>
                                                <th>Priority</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="allocations_tbody">
                                        </tbody>
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
@endsection
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script>
    $(document).ready(function() {
                let paymentsData = [];
                let invoicesData = [];
                let selectedPayment = null;
                let selectedInvoice = null;
                let selectedTenantId = null;

                // Toast notification helper
                function showToast(message, type = 'info') {
                    Toastify({
                        text: message,
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: type === 'error' ? "#dc3545" : (type === 'success' ? "#0d6efd" : "#0d6efd"),
                        stopOnFocus: true
                    }).showToast();
                }

                // Load top 100 unallocated payments on page load
                loadInitialPayments();

                function loadInitialPayments() {
                    $.ajax({
                        url: '{{ route("ajax.tenant.payments.search.allocate") }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            search: ''
                        },
                        success: function(response) {
                            if (response.success && response.payments.length > 0) {
                                paymentsData = response.payments;
                                $('#payment_select').empty().append('<option value="">-- Select Payment --</option>');

                                response.payments.forEach(function(payment) {
                                    $('#payment_select').append(`<option value="${payment.id}">${payment.reference} - ${payment.payment_date} (Available: ${payment.unallocated_amount})</option>`);
                                });

                                $('#payment_select').prop('disabled', false);
                                showToast(`Loaded ${response.payments.length} unallocated payments`, 'success');
                            }
                        },
                        error: function() {
                            showToast('Error loading payments', 'error');
                        }
                    });
                }

                // Search Payments
                $('#search_payment_btn').on('click', function() {
                        const searchTerm = $('#payment_search_input').val().trim();

                        if (searchTerm.length < 3) {
                            showToast('Please enter at least 3 characters to search', 'error');
                            return;
                        }

                        $('#search_payment_icon').hide();
                        $('#search_payment_spinner').show();
                        $('#search_payment_btn').prop('disabled', true);

                        $.ajax({
                            url: '{{ route("ajax.tenant.payments.search.allocate") }}',
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                search: searchTerm
                            },
                            success: function(response) {
                                if (response.success && response.payments.length > 0) {
                                    paymentsData = response.payments;
                                    $('#payment_select').empty().append('<option value="">-- Select Payment --</option>');

                                    response.payments.forEach(function(payment) {
                                        $('#payment_select').append(`<option value="${payment.id}">${payment.reference} - ${payment.payment_date} (Available: ${payment.unallocated_amount})</option>`);
                                    });

                                    $('#payment_select').prop('disabled', false);
                                    showToast(`Found ${response.payments.length} payments`, 'success');
                                } else {
                                    showToast('No payments found with unallocated balance', 'error');
                                    $('#payment_select').empty().append('<option value="">-- No Payments Found --</option>');
                                }
                            },
                            error: function() {
                                showToast('Error searching payments', 'error');
                            },
                            complete: function() {
                                $('#search_payment_icon').show();
                                $('#search_payment_spinner').hide();
                                $('#search_payment_btn').prop('disabled', false);
                            }
                        });
                    });

                    // Payment Selected
                    $('#payment_select').on('change', function() {
                        const paymentId = $(this).val();

                        if (!paymentId) {
                            resetPaymentDetails();
                            return;
                        }

                        selectedPayment = paymentsData.find(p => p.id == paymentId);

                        if (selectedPayment) {
                            displayPaymentDetails(selectedPayment);
                            selectedTenantId = selectedPayment.tenant_id;

                            // Enable invoice search
                            $('#invoice_search_input').prop('disabled', false);
                            $('#search_invoice_btn').prop('disabled', false);

                            // Load top 100 invoices for this tenant
                            loadInitialInvoices(selectedTenantId);

                            // Load existing allocations
                            loadExistingAllocations(selectedPayment.encrypted_id);
                        }
                    });

                    // Load initial invoices for tenant
                    function loadInitialInvoices(tenantId) {
                        $.ajax({
                            url: '{{ route("ajax.tenant.invoices.search") }}',
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                tenant_id: tenantId,
                                search: ''
                            },
                            success: function(response) {
                                if (response.success && response.invoices.length > 0) {
                                    invoicesData = response.invoices;
                                    $('#invoice_select').empty().append('<option value="">-- Select Invoice --</option>');

                                    response.invoices.forEach(function(invoice) {
                                        $('#invoice_select').append(`<option value="${invoice.id}">${invoice.reference_number} - Balance: ${invoice.balance}</option>`);
                                    });

                                    $('#invoice_select').prop('disabled', false);
                                    showToast(`Loaded ${response.invoices.length} unpaid invoices`, 'success');
                                } else {
                                    showToast('No unpaid invoices found', 'error');
                                }
                            },
                            error: function() {
                                showToast('Error loading invoices', 'error');
                            }
                        });
                    }

                    // Search Invoices
                    $('#search_invoice_btn').on('click', function() {
                            if (!selectedTenantId) {
                                showToast('Please select a payment first', 'error');
                                return;
                            }

                            const searchTerm = $('#invoice_search_input').val().trim();

                            $('#search_invoice_icon').hide();
                            $('#search_invoice_spinner').show();
                            $('#search_invoice_btn').prop('disabled', true);

                            $.ajax({
                                url: '{{ route("ajax.tenant.invoices.search") }}',
                                type: 'POST',
                                data: {
                                    _token: '{{ csrf_token() }}',
                                    tenant_id: selectedTenantId,
                                    search: searchTerm
                                },
                                success: function(response) {
                                    if (response.success && response.invoices.length > 0) {
                                        invoicesData = response.invoices;
                                        $('#invoice_select').empty().append('<option value="">-- Select Invoice --</option>');

                                        response.invoices.forEach(function(invoice) {
                                            $('#invoice_select').append(`<option value="${invoice.id}">${invoice.reference_number} - Balance: ${invoice.balance}</option>`);
                                        });

                                        $('#invoice_select').prop('disabled', false);
                                        showToast(`Found ${response.invoices.length} invoices`, 'success');
                                    } else {
                                        showToast('No unpaid invoices found for this tenant', 'error');
                                        $('#invoice_select').empty().append('<option value="">-- No Invoices Found --</option>');
                                    }
                                },
                                error: function() {
                                    showToast('Error searching invoices', 'error');
                                },
                                complete: function() {
                                    $('#search_invoice_icon').show();
                                    $('#search_invoice_spinner').hide();
                                    $('#search_invoice_btn').prop('disabled', false);
                                }
                            });
                        });

                        // Invoice Selected
                        $('#invoice_select').on('change', function() {
                            const invoiceId = $(this).val();

                            if (!invoiceId) {
                                resetInvoiceDetails();
                                return;
                            }

                            selectedInvoice = invoicesData.find(i => i.id == invoiceId);

                            if (selectedInvoice && selectedPayment) {
                                displayInvoiceDetails(selectedInvoice);
                                calculateMaxAllocatable();

                                // Enable allocation fields
                                $('#amount_to_allocate').prop('disabled', false);
                                $('#payment_priority').prop('disabled', false);
                                $('#submit_btn').prop('disabled', false);
                            }
                        });

                        // Amount to Allocate - Update Summary
                        $('#amount_to_allocate').on('input', function() {
                            const amount = parseFloat($(this).val()) || 0;
                            $('#summary_amount_allocate').text(amount.toFixed(2));

                            // Validation
                            const maxAllocatable = parseFloat($('#max_allocatable').text());

                            if (amount > maxAllocatable) {
                                $(this).addClass('is-invalid');
                                $('#submit_btn').prop('disabled', true);
                                showToast('Amount exceeds maximum allocatable amount!', 'error');
                            } else {
                                $(this).removeClass('is-invalid');
                                if (amount > 0) {
                                    $('#submit_btn').prop('disabled', false);
                                }
                            }
                        });

                        // Submit Allocation
                        $('#allocatePaymentForm').on('submit', function(e) {
                            e.preventDefault();

                            if (!selectedPayment || !selectedInvoice) {
                                showToast('Please select both payment and invoice', 'error');
                                return;
                            }

                            const amount = parseFloat($('#amount_to_allocate').val());
                            const maxAllocatable = parseFloat($('#max_allocatable').text());

                            if (amount <= 0 || amount > maxAllocatable) {
                                showToast('Invalid allocation amount', 'error');
                                return;
                            }

                            if (!confirm(`Allocate ${amount.toFixed(2)} from payment ${selectedPayment.reference} to invoice ${selectedInvoice.reference_number}?`)) {
                                return;
                            }

                            $('#submit_icon').hide();
                            $('#submit_spinner').show();
                            $('#submit_btn').prop('disabled', true);

                            $.ajax({
                                url: '{{ route("ajax.tenant.payments.allocate.store") }}',
                                type: 'POST',
                                data: {
                                    _token: '{{ csrf_token() }}',
                                    payment_id: selectedPayment.encrypted_id,
                                    invoice_id: selectedInvoice.id,
                                    amount_allocated: amount,
                                    payment_priority: $('#payment_priority').val()
                                },
                                success: function(response) {
                                    if (response.success) {
                                        showToast('Payment allocated successfully!', 'success');
                                        setTimeout(function() {
                                            location.reload();
                                        }, 1500);
                                    } else {
                                        showToast('Error: ' + response.message, 'error');
                                    }
                                },
                                error: function(xhr) {
                                    const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error allocating payment';
                                    showToast(message, 'error');
                                },
                                complete: function() {
                                    $('#submit_icon').show();
                                    $('#submit_spinner').hide();
                                    $('#submit_btn').prop('disabled', false);
                                }
                            });
                        });

                        // Reset Form
                        $('#reset_btn').on('click', function() {
                            if (confirm('Reset form? All unsaved data will be lost.')) {
                                location.reload();
                            }
                        });

                        // Helper Functions
                        function displayPaymentDetails(payment) {
                            $('#payment_reference').text(payment.reference);
                            $('#payment_date').text(payment.payment_date);
                            $('#payment_tenant').text(payment.tenant_name);
                            $('#payment_property').text(payment.property_name);
                            $('#payment_amount').text(payment.amount);
                            $('#payment_allocated').text(payment.allocated_amount);
                            $('#payment_available').text(payment.unallocated_amount);

                            let statusBadge = '';
                            if (payment.unallocated_amount_raw == payment.amount_raw) {
                                statusBadge = '<span class="badge badge-warning">Unallocated</span>';
                            } else if (payment.unallocated_amount_raw > 0) {
                                statusBadge = '<span class="badge badge-info">Partially Allocated</span>';
                            } else {
                                statusBadge = '<span class="badge badge-success">Fully Allocated</span>';
                            }
                            $('#payment_status_badge').html(statusBadge);

                            $('#payment_details_section').show();
                        }

                        function displayInvoiceDetails(invoice) {
                            $('#invoice_reference').text(invoice.reference_number);
                            $('#invoice_date').text(invoice.date);
                            $('#invoice_due_date').text(invoice.due_date || '-');
                            $('#invoice_amount').text(invoice.total_amount);
                            $('#invoice_paid').text(invoice.paid_amount);
                            $('#invoice_balance').text(invoice.balance);

                            let statusBadge = '<span class="badge badge-secondary">' + invoice.status + '</span>';
                            $('#invoice_status_badge').html(statusBadge);

                            $('#invoice_details_section').show();
                        }

                        function calculateMaxAllocatable() {
                            const paymentAvailable = selectedPayment.unallocated_amount_raw;
                            const invoiceBalance = selectedInvoice.balance_raw;
                            const maxAllocatable = Math.min(paymentAvailable, invoiceBalance);

                            $('#max_allocatable').text(maxAllocatable.toFixed(2));
                            $('#summary_payment_available').text(paymentAvailable.toFixed(2));
                            $('#summary_invoice_balance').text(invoiceBalance.toFixed(2));
                            $('#summary_max_allocatable').text(maxAllocatable.toFixed(2));

                            $('#amount_to_allocate').attr('max', maxAllocatable);
                            $('#amount_to_allocate').val(maxAllocatable.toFixed(2));
                            $('#summary_amount_allocate').text(maxAllocatable.toFixed(2));

                            $('#allocation_summary').show();
                        }

                        function resetPaymentDetails() {
                            $('#payment_details_section').hide();
                            $('#invoice_search_input').prop('disabled', true).val('');
                            $('#search_invoice_btn').prop('disabled', true);
                            $('#invoice_select').prop('disabled', true).empty().append('<option value="">-- Select Invoice --</option>');
                            resetInvoiceDetails();
                            selectedPayment = null;
                            selectedTenantId = null;
                            $('#existing_allocations_card').hide();
                        }

                        function resetInvoiceDetails() {
                            $('#invoice_details_section').hide();
                            $('#allocation_summary').hide();
                            $('#amount_to_allocate').prop('disabled', true).val('');
                            $('#payment_priority').prop('disabled', true).val('');
                            $('#submit_btn').prop('disabled', true);
                            selectedInvoice = null;
                        }

                        function loadExistingAllocations(encryptedPaymentId) {
                            $.ajax({
                                url: '/ajax/tenant/payments/' + encryptedPaymentId + '/details',
                                type: 'GET',
                                success: function(response) {
                                    if (response.success && response.allocations.length > 0) {
                                        let html = '';
                                        response.allocations.forEach(function(alloc, index) {
                                            html += '<tr>';
                                            html += '<td>' + (index + 1) + '</td>';
                                            html += '<td>' + alloc.invoice_reference + '</td>';
                                            html += '<td>-</td>';
                                            html += '<td class="text-end">' + alloc.amount_allocated + '</td>';
                                            html += '<td>' + alloc.payment_priority + '</td>';
                                            html += '<td class="text-center"><button class="btn btn-danger btn-sm delete-allocation" data-id="' + alloc.id + '"><span class="material-symbols-outlined" style="font-size: 16px;">delete</span></button></td>';
                                            html += '</tr>';
                                        });
                                        $('#allocations_tbody').html(html);
                                        $('#existing_allocations_card').show();
                                    } else {
                                        $('#existing_allocations_card').hide();
                                    }
                                },
                                error: function(xhr) {
                                    toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to load existing allocations.');
                                }
                            });
                        }

                        // Delete Allocation
                        $(document).on('click', '.delete-allocation', function() {
                            if (!confirm('Remove this allocation?')) {
                                return;
                            }

                            const allocationId = $(this).data('id');

                            $.ajax({
                                url: '/ajax/tenant/payments/allocations/' + allocationId,
                                type: 'DELETE',
                                data: {
                                    _token: '{{ csrf_token() }}'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        showToast('Allocation removed successfully!', 'success');
                                        setTimeout(function() {
                                            location.reload();
                                        }, 1500);
                                    }
                                },
                                error: function() {
                                    showToast('Error removing allocation', 'error');
                                }
                            });
                        });
                    });
</script>