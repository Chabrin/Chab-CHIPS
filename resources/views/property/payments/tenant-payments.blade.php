@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Tenant Payments</h5>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <label for="allocation_status_filter" class="form-label">Allocation Status</label>
                                <select class="form-select form-select-sm" id="allocation_status_filter" name="allocation_status_filter">
                                    <option value="">All</option>
                                    <option value="unallocated">Unallocated</option>
                                    <option value="partially_allocated">Partially Allocated</option>
                                    <option value="fully_allocated">Fully Allocated</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control form-control-sm" id="date_from" name="date_from">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control form-control-sm" id="date_to" name="date_to">
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-primary btn-sm" id="filter_btn">
                                    <span class="spinner-border spinner-border-sm" role="status" id="filter_spinner" style="display: none;"></span>
                                    <span class="material-symbols-outlined" id="filter_icon" style="font-size: 16px; vertical-align: middle;">filter_list</span>
                                    <span class="ms-1">Filter</span>
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="clear_btn">
                                    <span class="spinner-border spinner-border-sm" role="status" id="clear_spinner" style="display: none;"></span>
                                    <span class="material-symbols-outlined" id="clear_icon" style="font-size: 16px; vertical-align: middle;">clear</span>
                                    <span class="ms-1">Clear</span>
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="payments-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Payment Date</th>
                                        <th>Reference</th>
                                        <th>Unit</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Allocated</th>
                                        <th>Allocation</th>
                                        <th>Narration</th>
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

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-labelledby="paymentDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentDetailsModalLabel">Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="paymentDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Allocate Payment Modal -->
<div class="modal fade" id="allocatePaymentModal" tabindex="-1" aria-labelledby="allocatePaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allocatePaymentModalLabel">Allocate Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="allocatePaymentForm">
                    <input type="hidden" id="payment_id_allocate" name="payment_id">
                    <input type="hidden" id="tenant_id_allocate" name="tenant_id">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Payment Reference</label>
                            <input type="text" class="form-control" id="payment_reference_display" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Available Amount</label>
                            <input type="text" class="form-control" id="available_amount_display" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="invoice_id" class="form-label">Select Invoice <span class="text-danger">*</span></label>
                        <select class="form-select" id="invoice_id" name="invoice_id" required>
                            <option value="">Loading invoices...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="amount_allocated" class="form-label">Amount to Allocate <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="amount_allocated" name="amount_allocated" required>
                        <small class="text-muted">Maximum: <span id="max_amount_display">0.00</span></small>
                    </div>

                    <div class="mb-3">
                        <label for="payment_priority" class="form-label">Payment Priority</label>
                        <input type="text" class="form-control" id="payment_priority" name="payment_priority" placeholder="Optional">
                    </div>

                    <div class="alert alert-info">
                        <strong>Invoice Balance:</strong> <span id="invoice_balance_display">Select an invoice</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveAllocationBtn">
                    <span class="spinner-border spinner-border-sm" role="status" id="save_allocation_spinner" style="display: none;"></span>
                    <span id="save_allocation_text">Allocate Payment</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        var table = $('#payments-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '{{ route("ajax.tenant.payments.search") }}',
                type: 'POST',
                data: function(d) {
                    d._token = '{{ csrf_token() }}';
                    d.allocation_status = $('#allocation_status_filter').val();
                    d.date_from = $('#date_from').val();
                    d.date_to = $('#date_to').val();
                },
                dataSrc: 'payments'
            },
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'payment_date'
                },
                {
                    data: 'reference',
                    render: function(data, type, row) {
                        return '<a href="/tenant/payments/' + row.encrypted_id + '/details" class="text-primary fw-bold">' + data + '</a>';
                    }
                },
                {
                    data: 'unit_name'
                },
                {
                    data: 'amount',
                    className: 'text-end'
                },
                {
                    data: 'allocated_amount',
                    className: 'text-end'
                },
                {
                    data: 'allocation_status',
                    render: function(data, type, row) {
                        if (data === 'Unallocated') {
                            return 'None';
                        } else if (data === 'Partially Allocated') {
                            return 'Partial';
                        } else if (data === 'Fully Allocated') {
                            return 'Full';
                        }
                        return data || '-';
                    }
                },
                {
                    data: 'narration',
                    render: function(data, type, row) {
                        var narration = data && data !== '-' ? data : '';
                        return '<small style="font-style: italic;">' + narration + '</small>';
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            order: [
                [1, 'desc']
            ]
        });

        // Filter button
        $('#filter_btn').on('click', function() {
            $('#filter_spinner').show();
            $('#filter_icon').hide();
            $('#filter_btn').prop('disabled', true);

            table.ajax.reload(function() {
                $('#filter_spinner').hide();
                $('#filter_icon').show();
                $('#filter_btn').prop('disabled', false);
            });
        });

        // Clear button
        $('#clear_btn').on('click', function() {
            $('#allocation_status_filter').val('');
            $('#date_from').val('');
            $('#date_to').val('');

            $('#clear_spinner').show();
            $('#clear_icon').hide();
            $('#clear_btn').prop('disabled', true);

            table.ajax.reload(function() {
                $('#clear_spinner').hide();
                $('#clear_icon').show();
                $('#clear_btn').prop('disabled', false);
            });
        });

        // View payment details
        $(document).on('click', '.view-details', function() {
            var paymentId = $(this).data('id');

            $('#paymentDetailsModal').modal('show');
            $('#paymentDetailsContent').html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');

            $.ajax({
                url: '/ajax/tenant/payments/' + paymentId + '/details',
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="row mb-3">';
                        html += '<div class="col-md-6"><strong>Reference:</strong> ' + response.payment.reference + '</div>';
                        html += '<div class="col-md-6"><strong>Payment Date:</strong> ' + response.payment.payment_date + '</div>';
                        html += '</div>';
                        html += '<div class="row mb-3">';
                        html += '<div class="col-md-6"><strong>Tenant:</strong> ' + response.payment.tenant_name + '</div>';
                        html += '<div class="col-md-6"><strong>Property:</strong> ' + response.payment.property_name + '</div>';
                        html += '</div>';
                        html += '<div class="row mb-3">';
                        html += '<div class="col-md-6"><strong>Unit:</strong> ' + response.payment.unit_name + '</div>';
                        html += '<div class="col-md-6"><strong>Channel:</strong> ' + response.payment.channel + '</div>';
                        html += '</div>';
                        html += '<div class="row mb-3">';
                        html += '<div class="col-md-4"><strong>Amount:</strong> ' + response.payment.amount + '</div>';
                        html += '<div class="col-md-4"><strong>Allocated:</strong> ' + response.payment.total_allocated + '</div>';
                        html += '<div class="col-md-4"><strong>Unallocated:</strong> ' + response.payment.unallocated_amount + '</div>';
                        html += '</div>';
                        html += '<div class="row mb-3">';
                        html += '<div class="col-md-12"><strong>Narration:</strong> ' + response.payment.narration + '</div>';
                        html += '</div>';

                        if (response.payment.receipt_number !== '-') {
                            html += '<div class="row mb-3">';
                            html += '<div class="col-md-12"><strong>Receipt Number:</strong> ' + response.payment.receipt_number + '</div>';
                            html += '</div>';
                        }

                        html += '<hr><h6>Allocations</h6>';

                        if (response.allocations.length > 0) {
                            html += '<table class="table table-sm table-bordered">';
                            html += '<thead><tr><th>Invoice Reference</th><th class="text-end">Amount</th><th>Priority</th><th class="text-center">Action</th></tr></thead>';
                            html += '<tbody>';
                            response.allocations.forEach(function(alloc) {
                                html += '<tr>';
                                html += '<td>' + alloc.invoice_reference + '</td>';
                                html += '<td class="text-end">' + alloc.amount_allocated + '</td>';
                                html += '<td>' + alloc.payment_priority + '</td>';
                                html += '<td class="text-center"><button class="btn btn-danger btn-sm delete-allocation" data-id="' + alloc.id + '"><span class="material-symbols-outlined" style="font-size: 16px;">delete</span></button></td>';
                                html += '</tr>';
                            });
                            html += '</tbody></table>';
                        } else {
                            html += '<p class="text-muted">No allocations yet.</p>';
                        }

                        $('#paymentDetailsContent').html(html);
                    } else {
                        $('#paymentDetailsContent').html('<div class="alert alert-danger">' + response.message + '</div>');
                    }
                },
                error: function() {
                    $('#paymentDetailsContent').html('<div class="alert alert-danger">Error loading payment details.</div>');
                }
            });
        });

        // Allocate payment
        $(document).on('click', '.allocate-payment', function() {
            var paymentId = $(this).data('id');
            var tenantId = $(this).data('tenant-id');
            var reference = $(this).data('reference');
            var available = $(this).data('available');

            $('#payment_id_allocate').val(paymentId);
            $('#tenant_id_allocate').val(tenantId);
            $('#payment_reference_display').val(reference);
            $('#available_amount_display').val(parseFloat(available).toFixed(2));
            $('#max_amount_display').text(parseFloat(available).toFixed(2));
            $('#amount_allocated').attr('max', available);
            $('#invoice_id').html('<option value="">Loading invoices...</option>');
            $('#invoice_balance_display').text('Select an invoice');

            // Load tenant invoices
            $.ajax({
                url: '/ajax/tenant/' + tenantId + '/invoices',
                type: 'GET',
                success: function(response) {
                    if (response.success && response.invoices.length > 0) {
                        var options = '<option value="">-- Select Invoice --</option>';
                        response.invoices.forEach(function(inv) {
                            options += '<option value="' + inv.id + '" data-balance="' + inv.balance_raw + '">' + inv.reference_number + ' (Balance: ' + inv.balance + ')</option>';
                        });
                        $('#invoice_id').html(options);
                    } else {
                        $('#invoice_id').html('<option value="">No unpaid invoices found</option>');
                    }
                },
                error: function() {
                    $('#invoice_id').html('<option value="">Error loading invoices</option>');
                }
            });

            $('#allocatePaymentModal').modal('show');
        });

        // Invoice selection change
        $(document).on('change', '#invoice_id', function() {
            var selectedOption = $(this).find('option:selected');
            var balance = selectedOption.data('balance');

            if (balance) {
                $('#invoice_balance_display').text(parseFloat(balance).toFixed(2));
                var maxAmount = Math.min($('#available_amount_display').val(), balance);
                $('#amount_allocated').val(maxAmount);
            } else {
                $('#invoice_balance_display').text('Select an invoice');
                $('#amount_allocated').val('');
            }
        });

        // Save allocation
        $('#saveAllocationBtn').on('click', function() {
            var paymentId = $('#payment_id_allocate').val();
            var invoiceId = $('#invoice_id').val();
            var amount = $('#amount_allocated').val();
            var priority = $('#payment_priority').val();

            if (!invoiceId || !amount || amount <= 0) {
                alert('Please select an invoice and enter a valid amount.');
                return;
            }

            $('#save_allocation_spinner').show();
            $('#save_allocation_text').text('Allocating...');
            $('#saveAllocationBtn').prop('disabled', true);

            $.ajax({
                url: '/ajax/tenant/payments/' + paymentId + '/allocate',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    invoice_id: invoiceId,
                    amount_allocated: amount,
                    payment_priority: priority
                },
                success: function(response) {
                    if (response.success) {
                        alert('Payment allocated successfully!');
                        $('#allocatePaymentModal').modal('hide');
                        table.ajax.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr) {
                    var message = 'Error allocating payment.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    alert(message);
                },
                complete: function() {
                    $('#save_allocation_spinner').hide();
                    $('#save_allocation_text').text('Allocate Payment');
                    $('#saveAllocationBtn').prop('disabled', false);
                }
            });
        });

        // Delete allocation
        $(document).on('click', '.delete-allocation', function() {
            if (!confirm('Are you sure you want to remove this allocation?')) {
                return;
            }

            var allocationId = $(this).data('id');

            $.ajax({
                url: '/ajax/tenant/payments/allocations/' + allocationId,
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Allocation removed successfully!');
                        $('#paymentDetailsModal').modal('hide');
                        table.ajax.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error removing allocation.');
                }
            });
        });
    });
</script>