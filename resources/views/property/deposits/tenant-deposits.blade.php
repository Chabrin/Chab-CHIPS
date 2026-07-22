@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Tenant Deposits</h5>
                    </div>
                    <div class="card-body">
                        <!-- Date Range Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control form-control-sm" id="date_from" name="date_from">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control form-control-sm" id="date_to" name="date_to">
                            </div>
                            <div class="col-md-6 d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-primary btn-sm" id="filter_btn">
                                    <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">filter_list</span>
                                    <span class="ms-1">Apply Filter</span>
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="clear_btn">
                                    <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">clear</span>
                                    <span class="ms-1">Clear Filter</span>
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="deposits-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Reference</th>
                                        <th>Tenant</th>
                                        <th>Property</th>
                                        <th>Unit</th>
                                        <th>Held By</th>
                                        <th>Amount</th>
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
<!-- Deposit Details Modal -->
<div class="modal fade" id="depositDetailsModal" tabindex="-1" aria-labelledby="depositDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
            <!-- Modal Header -->
            <div class="modal-header py-2 px-3 bg-white border-bottom">
                <div class="d-flex align-items-center gap-2 w-100">
                    <span class="material-symbols-outlined text-dark">receipt_long</span>
                    <h6 class="mb-0 flex-grow-1 text-dark">Deposit Details</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-0">
                <!-- Deposit Info Banner -->
                <div class="px-3 py-2 bg-white border-bottom">
                    <div class="row">
                        <div class="col-md-3">
                            <small class="text-muted d-block">
                                <strong>Tenant:</strong> <span id="deposit_tenant"></span>
                            </small>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">
                                <strong>Reference:</strong> <span id="deposit_reference"></span>
                            </small>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">
                                <strong>Date:</strong> <span id="deposit_date"></span>
                            </small>
                        </div>
                        <div class="col-md-3 text-end">
                            <small class="d-block">
                                <strong>Amount:</strong> <span id="deposit_amount" class="fw-bold text-primary"></span>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Linked Invoices Section -->
                <div class="px-3 py-2 bg-light">
                    <h6 class="mb-0">Linked Invoices</h6>
                </div>

                <!-- Scrollable Table -->
                <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                    <table class="table table-sm mb-0" id="linked_invoices_table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Narration</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-end">Balance</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="linked_invoices_tbody">
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted mb-0">Loading invoices...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        var table = $('#deposits-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '{{ route("ajax.deposits.search") }}',
                type: 'POST',
                data: function(d) {
                    d._token = '{{ csrf_token() }}';
                    d.date_from = $('#date_from').val();
                    d.date_to = $('#date_to').val();
                },
                dataSrc: 'deposits'
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
                    data: 'date_time'
                },
                {
                    data: 'reference_number',
                    render: function(data, type, row) {
                        return `<a href="#" class="deposit-link" style="color:#3598db" data-id="${row.id}">${data}</a>`;
                    }
                },
                {
                    data: 'tenant_name'
                },
                {
                    data: 'property_name'
                },
                {
                    data: 'unit_name'
                },
                {
                    data: 'held_by',
                    render: function(data) {
                        if (data === 'Company') return '<span class="badge bg-success">Chabrin</span>';
                        if (data === 'Landlord') return '<span class="badge bg-warning text-dark">Landlord</span>';
                        return '<span class="badge bg-secondary">Not specified</span>';
                    }
                },
                {
                    data: 'amount',
                    className: 'text-end'
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

        // Handle filter button click
        $('#filter_btn').on('click', function() {
            table.ajax.reload();
        });

        // Handle clear button click
        $('#clear_btn').on('click', function() {
            $('#date_from').val('');
            $('#date_to').val('');
            table.ajax.reload();
        });

        // Handle deposit link click
        $(document).on('click', '.deposit-link', function(e) {
            e.preventDefault();
            const depositId = $(this).data('id');
            loadDepositDetails(depositId);
        });

        // Load deposit details and show modal
        function loadDepositDetails(depositId) {
            // Reset modal content
            $('#linked_invoices_tbody').html(`
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted mb-0">Loading invoices...</p>
                    </td>
                </tr>
            `);

            // Show modal
            $('#depositDetailsModal').modal('show');

            // Load deposit details
            $.ajax({
                url: '/ajax/deposits/details/' + depositId,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        const deposit = response.deposit;

                        // Populate deposit info
                        $('#deposit_tenant').text(deposit.tenant_name || 'N/A');
                        $('#deposit_reference').text(deposit.reference_number || 'N/A');
                        $('#deposit_date').text(deposit.date_time || 'N/A');
                        $('#deposit_amount').text('KES ' + deposit.amount);

                        // Populate linked invoices
                        if (response.invoices && response.invoices.length > 0) {
                            let invoiceHtml = '';
                            response.invoices.forEach(function(invoice) {
                                const statusClass = {
                                    1: 'danger',
                                    6: 'info',
                                    8: 'primary',
                                    9: 'success'
                                } [invoice.current_status_id] || 'secondary';

                                invoiceHtml += `
                                    <tr>
                                        <td>${invoice.reference_number || 'N/A'}</td>
                                        <td>${invoice.narration || 'N/A'}</td>
                                        <td class="text-end">${invoice.total_amount}</td>
                                        <td class="text-end">${invoice.current_balance}</td>
                                        <td class="text-center">
                                            <span class="badge bg-${statusClass}">${invoice.status_name}</span>
                                        </td>
                                    </tr>
                                `;
                            });
                            $('#linked_invoices_tbody').html(invoiceHtml);
                        } else {
                            $('#linked_invoices_tbody').html(`
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px; opacity: 0.3;">receipt_long</span>
                                        No invoices linked to this deposit
                                    </td>
                                </tr>
                            `);
                        }
                    } else {
                        toastr.error(response.message || 'Failed to load deposit details');
                    }
                },
                error: function(xhr) {
                    toastr.error('Error loading deposit details');
                    console.error(xhr);
                    $('#linked_invoices_tbody').html(`
                        <tr>
                            <td colspan="5" class="text-center py-5 text-danger">
                                <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px; opacity: 0.3;">error</span>
                                Error loading deposit details
                            </td>
                        </tr>
                    `);
                }
            });
        }
    });
</script>