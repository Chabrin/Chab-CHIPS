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

    .results-table {
        margin-top: 20px;
    }

    .line-item-row {
        border-bottom: 1px solid #e9ecef;
        padding: 10px 0;
    }

    .btn-remove-item {
        cursor: pointer;
    }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <!-- Search Card -->
                <div class="card position-relative">
                    <div class="card-overlay" id="card-overlay">
                        <div class="text-center">
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Processing...</p>
                        </div>
                    </div>
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Client Invoices</h5>
                        <a href="{{ route('invoices.client.create') }}" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined">add</span> Create Invoice
                        </a>
                    </div>
                    <div class="card-body">
                        <!-- Search Form -->
                        <form id="searchForm">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="client_search" class="form-label">Client</label>
                                    <input type="text" class="form-control" id="client_search" placeholder="Search client...">
                                    <input type="hidden" id="client_id" name="client_id">
                                    <div id="client_search_results" class="list-group" style="position: absolute; z-index: 1000; display: none; max-height: 200px; overflow-y: auto;"></div>
                                </div>
                                <div class="col-md-4">
                                    <label for="reference_number" class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" id="reference_number" name="reference_number" placeholder="Enter reference number">
                                </div>
                                <div class="col-md-4">
                                    <label for="status_id" class="form-label">Status</label>
                                    <select class="form-select" id="status_id" name="status_id">
                                        <option value="">All Statuses</option>
                                        <option value="1">Pending</option>
                                        <option value="6">Posted</option>
                                        <option value="8">Partially Cleared</option>
                                        <option value="9">Cleared</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="date_from" class="form-label">Date From</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from">
                                </div>
                                <div class="col-md-4">
                                    <label for="date_to" class="form-label">Date To</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to">
                                </div>
                                <div class="col-md-4 d-flex align-items-end gap-2">
                                    <button type="button" class="btn btn-primary" id="search_btn">
                                        <span class="material-symbols-outlined" id="search_icon">search</span>
                                        <span class="spinner-border spinner-border-sm" role="status" id="search_spinner" style="display: none;"></span>
                                        Search
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="clear_btn">Clear</button>
                                    <button type="button" class="btn btn-success" id="export_btn" style="display: none;">
                                        <span class="material-symbols-outlined">download</span> Export to Excel
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Results Section -->
                        <div id="results_section" style="display: none;">
                            <hr>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Search Results (<span id="results_count">0</span> invoices found)</h6>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="results_table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Reference</th>
                                            <th>Client</th>
                                            <th>Property</th>
                                            <th>Invoice Date</th>
                                            <th>Due Date</th>
                                            <th>Total Amount</th>
                                            <th>Commission</th>
                                            <th>Payable</th>
                                            <th>Balance</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="results_tbody">
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

<!-- Create Invoice Modal -->
<div class="modal fade" id="createInvoiceModal" tabindex="-1" aria-labelledby="createInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createInvoiceModalLabel">Create Client Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createInvoiceForm">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="create_client_search" class="form-label">Client <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="create_client_search" placeholder="Search client..." required>
                            <input type="hidden" id="create_client_id" name="client_id" required>
                            <div id="create_client_search_results" class="list-group" style="position: absolute; z-index: 1050; display: none; max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="create_property_id" class="form-label">Property (Optional)</label>
                            <select class="form-select" id="create_property_id" name="property_id" disabled>
                                <option value="">Select client first</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="create_date_due" class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="create_date_due" name="date_due" required>
                        </div>
                        <div class="col-md-6">
                            <label for="create_narration" class="form-label">Narration <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="create_narration" name="narration" placeholder="Invoice description" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="create_comm_per" class="form-label">Commission %</label>
                            <input type="number" class="form-control" id="create_comm_per" name="comm_per" placeholder="0.00" step="0.01" min="0" max="100" value="0">
                        </div>
                        <div class="col-md-6">
                            <label for="create_vat_per" class="form-label">VAT %</label>
                            <input type="number" class="form-control" id="create_vat_per" name="vat_per" placeholder="0.00" step="0.01" min="0" max="100" value="0">
                        </div>
                    </div>

                    <hr>
                    <h6 class="mb-3">Line Items</h6>
                    <div id="line_items_container">
                        <div class="line-item-row row mb-2">
                            <div class="col-md-5">
                                <input type="text" class="form-control" name="line_items[0][item_id]" placeholder="Item Description" required>
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control line-item-qty" name="line_items[0][quantity]" placeholder="Qty" value="1" min="1" required>
                            </div>
                            <div class="col-md-3">
                                <input type="number" class="form-control line-item-amount" name="line_items[0][amount]" placeholder="Amount" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-2 text-end">
                                <span class="line-item-total">0.00</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" id="add_line_item">
                        <span class="material-symbols-outlined">add</span> Add Item
                    </button>

                    <div class="mt-3 p-3 bg-light rounded">
                        <div class="row">
                            <div class="col-6 text-end"><strong>Subtotal:</strong></div>
                            <div class="col-6 text-end"><span id="invoice_subtotal">0.00</span></div>
                        </div>
                        <div class="row">
                            <div class="col-6 text-end"><strong>Commission (<span id="display_comm_per">0</span>%):</strong></div>
                            <div class="col-6 text-end"><span id="invoice_commission">0.00</span></div>
                        </div>
                        <div class="row">
                            <div class="col-6 text-end"><strong>Payable to Client:</strong></div>
                            <div class="col-6 text-end"><span id="invoice_payable">0.00</span></div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6 text-end">
                                <h5><strong>Total:</strong></h5>
                            </div>
                            <div class="col-6 text-end">
                                <h5><span id="invoice_total">0.00</span></h5>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="create_invoice_btn">
                        <span class="spinner-border spinner-border-sm" role="status" id="create_spinner" style="display: none;"></span>
                        Create Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        let lineItemIndex = 1;
        let searchTimeout;

        // Client search in search form
        $('#client_search').on('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = $(this).val().trim();

            if (searchTerm.length < 2) {
                $('#client_search_results').hide().empty();
                $('#client_id').val('');
                return;
            }

            searchTimeout = setTimeout(function() {
                searchClients(searchTerm, '#client_search_results', '#client_id', '#client_search');
            }, 300);
        });

        // Client search in create modal
        $('#create_client_search').on('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = $(this).val().trim();

            if (searchTerm.length < 2) {
                $('#create_client_search_results').hide().empty();
                $('#create_client_id').val('');
                $('#create_property_id').prop('disabled', true).html('<option value="">Select client first</option>');
                return;
            }

            searchTimeout = setTimeout(function() {
                searchClients(searchTerm, '#create_client_search_results', '#create_client_id', '#create_client_search');
            }, 300);
        });

        // Search clients function
        function searchClients(query, resultsContainer, idField, displayField) {
            $.ajax({
                url: '{{ route("ajax.invoices.clients") }}',
                type: 'GET',
                data: {
                    search: query
                },
                success: function(response) {
                    if (response.success && response.clients.length > 0) {
                        let html = '';
                        response.clients.forEach(function(client) {
                            html += `<a href="#" class="list-group-item list-group-item-action client-select" 
                                        data-client-id="${client.id}" 
                                        data-client-name="${client.names}"
                                        data-results-container="${resultsContainer}"
                                        data-id-field="${idField}"
                                        data-display-field="${displayField}">
                                        ${client.names} - ${client.mobile_number || 'N/A'} - ${client.reference_number || 'N/A'}
                                    </a>`;
                        });
                        $(resultsContainer).html(html).show();
                    } else {
                        $(resultsContainer).html('<div class="list-group-item">No clients found</div>').show();
                    }
                },
                error: function() {
                    toastr.error('Failed to search clients');
                }
            });
        }

        // Select client
        $(document).on('click', '.client-select', function(e) {
            e.preventDefault();
            const clientId = $(this).data('client-id');
            const clientName = $(this).data('client-name');
            const resultsContainer = $(this).data('results-container');
            const idField = $(this).data('id-field');
            const displayField = $(this).data('display-field');

            $(idField).val(clientId);
            $(displayField).val(clientName);
            $(resultsContainer).hide().empty();

            // If in create modal, load properties
            if (idField === '#create_client_id') {
                loadProperties(clientId);
            }
        });

        // Load properties for client
        function loadProperties(clientId) {
            $('#create_property_id').prop('disabled', true).html('<option value="">Loading...</option>');

            $.ajax({
                url: `/ajax/invoices/client/${clientId}/properties`,
                type: 'GET',
                success: function(response) {
                    if (response.success && response.properties.length > 0) {
                        let html = '<option value="">No property (General invoice)</option>';
                        response.properties.forEach(function(property) {
                            html += `<option value="${property.id}">${property.property_name} - ${property.reference_number || ''}</option>`;
                        });
                        $('#create_property_id').html(html).prop('disabled', false);
                    } else {
                        $('#create_property_id').html('<option value="">No property (General invoice)</option>').prop('disabled', false);
                    }
                },
                error: function() {
                    $('#create_property_id').html('<option value="">Error loading properties</option>');
                    toastr.error('Failed to load properties');
                }
            });
        }

        // Add line item
        $('#add_line_item').on('click', function() {
            const html = `
                <div class="line-item-row row mb-2">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="line_items[${lineItemIndex}][item_id]" placeholder="Item Description" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control line-item-qty" name="line_items[${lineItemIndex}][quantity]" placeholder="Qty" value="1" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <input type="number" class="form-control line-item-amount" name="line_items[${lineItemIndex}][amount]" placeholder="Amount" step="0.01" min="0" required>
                    </div>
                    <div class="col-md-2 text-end">
                        <span class="line-item-total">0.00</span>
                        <span class="material-symbols-outlined btn-remove-item text-danger" style="cursor: pointer;">delete</span>
                    </div>
                </div>
            `;
            $('#line_items_container').append(html);
            lineItemIndex++;
        });

        // Remove line item
        $(document).on('click', '.btn-remove-item', function() {
            $(this).closest('.line-item-row').remove();
            calculateTotal();
        });

        // Calculate line item total and invoice total
        $(document).on('input', '.line-item-qty, .line-item-amount', function() {
            const row = $(this).closest('.line-item-row');
            const qty = parseFloat(row.find('.line-item-qty').val()) || 0;
            const amount = parseFloat(row.find('.line-item-amount').val()) || 0;
            const total = qty * amount;
            row.find('.line-item-total').text(total.toFixed(2));
            calculateTotal();
        });

        // Recalculate on commission/vat change
        $('#create_comm_per, #create_vat_per').on('input', function() {
            calculateTotal();
        });

        function calculateTotal() {
            let subtotal = 0;
            $('.line-item-total').each(function() {
                subtotal += parseFloat($(this).text()) || 0;
            });

            const commPer = parseFloat($('#create_comm_per').val()) || 0;
            const commission = (subtotal * commPer) / 100;
            const payable = subtotal - commission;

            $('#invoice_subtotal').text(subtotal.toFixed(2));
            $('#display_comm_per').text(commPer.toFixed(2));
            $('#invoice_commission').text(commission.toFixed(2));
            $('#invoice_payable').text(payable.toFixed(2));
            $('#invoice_total').text(subtotal.toFixed(2));
        }

        // Search invoices
        $('#search_btn').on('click', function() {
            searchInvoices();
        });

        // Clear search
        $('#clear_btn').on('click', function() {
            $('#searchForm')[0].reset();
            $('#client_id').val('');
            $('#results_section').hide();
            $('#export_btn').hide();
        });

        // Search on Enter key
        $('#searchForm input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                searchInvoices();
            }
        });

        function searchInvoices() {
            const formData = {
                client_id: $('#client_id').val(),
                reference_number: $('#reference_number').val(),
                status_id: $('#status_id').val(),
                date_from: $('#date_from').val(),
                date_to: $('#date_to').val(),
                _token: '{{ csrf_token() }}'
            };

            $('#search_icon').hide();
            $('#search_spinner').show();
            $('#search_btn').prop('disabled', true);

            $.ajax({
                url: '{{ route("ajax.invoices.client.search") }}',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success && response.invoices.length > 0) {
                        displayResults(response.invoices);
                        $('#results_count').text(response.invoices.length);
                        $('#results_section').show();
                        $('#export_btn').show();
                        toastr.success(`Found ${response.invoices.length} invoice(s)`);
                    } else {
                        $('#results_section').hide();
                        $('#export_btn').hide();
                        toastr.info('No invoices found');
                    }
                },
                error: function() {
                    toastr.error('Search failed');
                },
                complete: function() {
                    $('#search_icon').show();
                    $('#search_spinner').hide();
                    $('#search_btn').prop('disabled', false);
                }
            });
        }

        function displayResults(invoices) {
            let html = '';
            invoices.forEach(function(invoice) {
                html += `
                    <tr>
                        <td>${invoice.reference_number}</td>
                        <td>${invoice.client_name}</td>
                        <td>${invoice.property_name}</td>
                        <td>${invoice.date_time}</td>
                        <td>${invoice.date_due}</td>
                        <td class="text-end">${invoice.total_amount}</td>
                        <td class="text-end">${invoice.comm_ammount}</td>
                        <td class="text-end">${invoice.payable_amount}</td>
                        <td class="text-end">${invoice.current_balance}</td>
                        <td><span class="badge bg-info">${invoice.status}</span></td>
                    </tr>
                `;
            });
            $('#results_tbody').html(html);
        }

        // Export to Excel
        $('#export_btn').on('click', function() {
            const params = new URLSearchParams({
                client_id: $('#client_id').val(),
                reference_number: $('#reference_number').val(),
                status_id: $('#status_id').val(),
                date_from: $('#date_from').val(),
                date_to: $('#date_to').val()
            });
            window.location.href = '{{ route("invoices.client.export") }}?' + params.toString();
        });

        // Create invoice
        $('#createInvoiceForm').on('submit', function(e) {
            e.preventDefault();

            const formData = {
                client_id: $('#create_client_id').val(),
                property_id: $('#create_property_id').val(),
                date_due: $('#create_date_due').val(),
                narration: $('#create_narration').val(),
                comm_per: $('#create_comm_per').val(),
                vat_per: $('#create_vat_per').val(),
                line_items: [],
                _token: '{{ csrf_token() }}'
            };

            // Collect line items
            $('.line-item-row').each(function() {
                const row = $(this);
                formData.line_items.push({
                    item_id: row.find('[name*="[item_id]"]').val(),
                    quantity: row.find('[name*="[quantity]"]').val(),
                    amount: row.find('[name*="[amount]"]').val()
                });
            });

            $('#create_spinner').show();
            $('#create_invoice_btn').prop('disabled', true);

            $.ajax({
                url: '{{ route("ajax.invoices.client.store") }}',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        $('#createInvoiceModal').modal('hide');
                        $('#createInvoiceForm')[0].reset();
                        $('#create_client_id').val('');
                        $('#create_property_id').prop('disabled', true).html('<option value="">Select client first</option>');
                        $('#line_items_container').html(`
                            <div class="line-item-row row mb-2">
                                <div class="col-md-5">
                                    <input type="text" class="form-control" name="line_items[0][item_id]" placeholder="Item Description" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control line-item-qty" name="line_items[0][quantity]" placeholder="Qty" value="1" min="1" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" class="form-control line-item-amount" name="line_items[0][amount]" placeholder="Amount" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-2 text-end">
                                    <span class="line-item-total">0.00</span>
                                </div>
                            </div>
                        `);
                        lineItemIndex = 1;
                        calculateTotal();
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
                    $('#create_spinner').hide();
                    $('#create_invoice_btn').prop('disabled', false);
                }
            });
        });

        // Hide search results when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.form-control, .list-group').length) {
                $('.list-group').hide();
            }
        });
    });
</script>
@endsection