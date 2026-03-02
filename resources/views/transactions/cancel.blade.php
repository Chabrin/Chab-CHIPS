@extends('layouts.main')

@section('title', 'Cancel Transactions')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">
                            <span class="material-symbols-outlined">remove_circle</span> Cancel Transactions
                        </h4>
                        <div class="text-muted ms-auto">
                            <small>List unconfirmed transactions available for cancellation</small>
                        </div>
                    </div>

                    <div class="card-body">
                        <style>
                            /* Compact search buttons and material icon sizing */
                            .reversal-filters .btn-sm {
                                padding: .25rem .5rem;
                                font-size: .82rem;
                                border-radius: .35rem;
                                min-width: 80px;
                            }

                            .reversal-filters .material-symbols-outlined {
                                font-size: 16px;
                                vertical-align: middle;
                            }

                            /* Ensure DataTable action column looks compact */
                            table#basic-1 td,
                            table#basic-1 th {
                                padding: .35rem .5rem;
                            }

                            /* reduce badge overlap with buttons */
                            #resultsCount {
                                margin-left: .5rem;
                            }

                            /* Medium modal sizing helper: bootstrap doesn't include modal-md by default
                               so create a reasonable medium width and center it. Keeps mobile responsive. */
                            .modal-dialog.modal-md {
                                max-width: 780px;
                                /* medium width */
                                margin: 1.75rem auto;
                                /* center vertically on larger screens */
                            }

                            @media (max-width: 768px) {
                                .modal-dialog.modal-md {
                                    max-width: 95%;
                                    margin: 1rem auto;
                                }
                            }
                        </style>
                        <!-- Search Filters (match reversal UI) -->
                        <form id="cancelFilter" class="row g-2 mb-3 reversal-filters align-items-end">
                            <div class="col-md-3">
                                <label for="company_id" class="form-label">Company <span class="text-danger">*</span></label>
                                <select class="form-select form-control-sm" id="company_id" name="company_id" required>
                                    <option value="">-- Company --</option>
                                    @foreach ($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="date_from" class="form-label">From <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" required>
                            </div>

                            <div class="col-md-2">
                                <label for="date_to" class="form-label">To <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" required>
                            </div>

                            <div class="col-md-3">
                                <label for="search_term" class="form-label">Search</label>
                                <input type="text" class="form-control form-control-sm" id="search_term" name="search_term" placeholder="Account No, Member No, Reference...">
                            </div>

                            <div class="col-md-2">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-primary btn-xs btn-icon" id="searchBtn">
                                        <span id="searchSpinner" class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                                        <span class="material-symbols-outlined">search</span>
                                        <span class="ms-1">Search</span>
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-xs btn-icon" id="clearBtn">
                                        <span class="material-symbols-outlined">clear</span>
                                        <span class="ms-1">Clear</span>
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div id="resultsContainer">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div></div>
                                <span id="resultsCount" class="badge bg-warning"></span>
                            </div>

                            <div class="table-responsive custom-scrollbar">
                                <table class="table table-hover table-sm" id="basic-1" style="font-size:0.85rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Account</th>
                                            <th>Member No</th>
                                            <th>Reference</th>
                                            <th class="text-end">Amount</th>
                                            <th>Fund</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cancelTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Embedded transaction details modal (medium) -->
<div class="modal fade" id="cancelDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header p-2 border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3" id="cancelDetailsContent" style="font-size:0.9rem;">
                <div id="details-loading" class="d-flex align-items-center justify-content-center py-4">
                    <div class="d-flex align-items-center gap-2">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div>Loading transaction details...</div>
                    </div>
                </div>

                <div id="details-pane" class="d-none">
                    <div class="mb-2">
                        <div class="fw-semibold mb-1">Member Details</div>
                        <div id="d-member-summary" class="small">-</div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-12">
                            <div class="fw-semibold mb-1">Employee</div>
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless mb-0 table-condensed-custom">
                                    <thead>
                                        <tr>
                                            <th style="width: 20%"></th>
                                            <th class="text-end">Amt</th>
                                            <th class="text-end">Registered</th>
                                            <th class="text-end">Unregistered</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="text-muted small">Amt</td>
                                            <td id="d-employee-amount" class="text-end">-</td>
                                            <td id="d-employee-registered-amount" class="text-end">-</td>
                                            <td id="d-employee-unregistered-amount" class="text-end">-</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted small">Units</td>
                                            <td id="d-employee-units" class="text-end">-</td>
                                            <td id="d-employee-registered-units" class="text-end">-</td>
                                            <td id="d-employee-unregistered-units" class="text-end">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-12 mt-3">
                            <div class="fw-semibold mb-1">Employer</div>
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless mb-0 table-condensed-custom">
                                    <thead>
                                        <tr>
                                            <th style="width: 20%"></th>
                                            <th class="text-end">Amt</th>
                                            <th class="text-end">Registered</th>
                                            <th class="text-end">Unregistered</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="text-muted small">Amt</td>
                                            <td id="d-employer-amount" class="text-end">-</td>
                                            <td id="d-employer-registered-amount" class="text-end">-</td>
                                            <td id="d-employer-unregistered-amount" class="text-end">-</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted small">Units</td>
                                            <td id="d-employer-units" class="text-end">-</td>
                                            <td id="d-employer-registered-units" class="text-end">-</td>
                                            <td id="d-employer-unregistered-units" class="text-end">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div id="d-totals-line" class="d-flex justify-content-between align-items-center small">
                            <div id="d-totals-left" class="text-start">
                                <span class="text-muted">Totals</span>
                                &nbsp;&nbsp;
                                <strong>Amt:</strong>
                                <span id="d-total-amount">-</span>
                                &nbsp;&nbsp;
                                <strong>Units:</strong>
                                <span id="d-total-units">-</span>
                            </div>
                            <div id="d-meta-summary" class="text-end text-muted small">-</div>
                        </div>
                    </div>

                    <div class="mb-2 small">
                        <div><strong>Transacted By:</strong> <span id="d-transacted-by">-</span></div>
                    </div>

                    <div class="d-flex justify-content-end align-items-center gap-2 mt-2">
                        <button class="btn btn-danger btn-sm d-flex align-items-center" id="modal-cancel-btn" style="display:none;">
                            <span class="material-symbols-outlined">remove_circle</span>
                            <span class="ms-1">Cancel Transaction</span>
                        </button>
                        <button class="btn btn-outline-secondary btn-sm d-flex align-items-center" id="close-cancel-details">
                            <span class="material-symbols-outlined">cancel</span>
                            <span class="ms-1">Close</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer p-2 border-0">
                <!-- minimal footer -->
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    $(function() {
        // expose a global refresh hook used by the modal after cancellation
        window.refreshCancelList = function() {
            searchTransactions();
        };

        // Toggle spinner helper
        function toggleSpinner(on) {
            if (on) {
                $('#searchSpinner').removeClass('d-none');
                $('#searchBtn').prop('disabled', true);
            } else {
                $('#searchSpinner').addClass('d-none');
                $('#searchBtn').prop('disabled', false);
            }
        }

        // Clear results
        function clearResults() {
            $('#cancelTableBody').empty();
            $('#resultsCount').text('');
        }

        // Search button
        $('#searchBtn').click(function() {
            const companyId = $('#company_id').val();
            const dateFrom = $('#date_from').val();
            const dateTo = $('#date_to').val();
            if (!companyId) return toastr.error('Please select a company before searching');
            if (!dateFrom || !dateTo) return toastr.error('Please select both From and To dates');
            searchTransactions();
        });

        // Clear button
        $('#clearBtn').click(function() {
            $('#company_id').val('');
            $('#date_from').val('');
            $('#date_to').val('');
            $('#search_term').val('');
            clearResults();
        });

        // Enter key in search box
        $('#search_term').keypress(function(e) {
            if (e.which == 13) searchTransactions();
        });

        // Perform AJAX search to server endpoint
        function searchTransactions() {
            const formData = {
                company_id: $('#company_id').val(),
                date_from: $('#date_from').val(),
                date_to: $('#date_to').val(),
                search_term: $('#search_term').val(),
                _token: $('meta[name="csrf-token"]').attr('content')
            };

            toggleSpinner(true);
            $.post('/search/cancel-transactions', formData)
                .done(function(response) {
                    toggleSpinner(false);
                    if (response && response.success && response.transactions && response.transactions.length) {
                        displayTransactions(response.transactions);
                        $('#resultsCount').text(response.transactions.length + ' available');
                    } else {
                        clearResults();
                        toastr.warning('No transactions found');
                    }
                })
                .fail(function(xhr) {
                    toggleSpinner(false);
                    const body = xhr.responseJSON || {};
                    const err = body.message || body.error || 'Search failed';
                    toastr.error(err);
                });
        }

        function displayTransactions(transactions) {
            const tbody = $('#cancelTableBody');
            tbody.empty();
            transactions.forEach(function(t) {
                const amount = t.amount !== undefined ? t.amount : t.net_amount;
                const actionBtn = `<a href="#" class="view-details-btn small" data-transaction-id="${t.id}">Details</a>`;
                const row = `
                    <tr>
                        <td>${t.trans_date ? new Date(t.trans_date).toLocaleDateString() : '-'}</td>
                        <td>${t.account_no}</td>
                        <td>${t.member_no || '<span class="text-muted">N/A</span>'}</td>
                        <td>${t.reference_no || 'N/A'}</td>
                        <td class="text-end">${amount}</td>
                        <td>${t.fund_name || ''}</td>
                        <td class="text-center">${actionBtn}</td>
                    </tr>
                `;
                tbody.append(row);
            });

            // Initialize DataTable once if available
            if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#basic-1')) {
                $('#basic-1').DataTable({
                    pageLength: 25,
                    lengthChange: false,
                    ordering: true,
                    searching: false,
                    columnDefs: [{
                        orderable: false,
                        targets: -1
                    }]
                });
            }

            // If DataTables is present, redraw rows to keep pagination and controls intact
            const dt = $.fn.DataTable && $.fn.DataTable.isDataTable('#basic-1') ? $('#basic-1').DataTable() : null;
            if (dt) {
                dt.clear();
                transactions.forEach(function(transaction) {
                    const amount = transaction.amount !== undefined ? transaction.amount : transaction.net_amount;
                    const actionBtn = `<a href="#" class="view-details-btn small" data-transaction-id="${transaction.id}">Details</a>`;
                    dt.row.add([
                        transaction.trans_date ? new Date(transaction.trans_date).toLocaleDateString() : '-',
                        transaction.account_no,
                        transaction.member_no || 'N/A',
                        transaction.reference_no || 'N/A',
                        amount,
                        transaction.fund_name || '',
                        actionBtn
                    ]);
                });
                dt.draw(false);
            }
        }

        // Delegate detail button click: fetch /transactions/{id} and populate shared modal
        $(document).on('click', '.view-details-btn', function(e) {
            e.preventDefault();
            const id = $(this).data('transaction-id');
            if (!id) return;
            const $btn = $(this);
            if ($btn.data('loading')) return;
            $btn.data('loading', true);
            const original = $btn.html();
            $btn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...');

            $.get(`/transactions/${id}`)
                .done(function(resp) {
                    let tx = null;
                    if (resp && resp.transaction) tx = resp.transaction;
                    else if (resp && resp.id) tx = resp;
                    if (!tx) {
                        toastr.warning((resp && (resp.message || resp.error)) || 'No details found');
                        return;
                    }
                    // populate shared modal
                    // reset loading/visibility inside modal partial
                    $('#details-loading').removeClass('d-none');
                    $('#details-pane').addClass('d-none');
                    if (typeof populateTransactionDetails === 'function') {
                        populateTransactionDetails(tx);
                    } else {
                        // fallback: show modal raw
                        $('#cancelDetailsModal').modal('show');
                    }
                })
                .fail(function(xhr) {
                    const body = xhr.responseJSON || {};
                    const err = body.message || body.error || 'Failed to load details';
                    toastr.error(err);
                })
                .always(function() {
                    $btn.data('loading', false);
                    $btn.html(original);
                });
        });

        // Modal helper: populate fields
        function populateTransactionDetails(tx) {
            const memberName = tx.member ? (tx.member.first_name + ' ' + tx.member.last_name) : (tx.member_name || 'N/A');
            $('#d-member-summary').html(memberName + ' | Acc: ' + (tx.account_no || '-') + ' | Mno: ' + (tx.member_no || '-'));

            $('#d-employee-amount').text(fmtAmount(tx.employee_amount));
            $('#d-employee-units').text(fmtAmount(tx.employee_units));
            $('#d-employee-registered-amount').text(fmtAmount(tx.employee_registered_amount));
            $('#d-employee-unregistered-amount').text(fmtAmount(tx.employee_unregistered_amount));
            $('#d-employee-registered-units').text(fmtAmount(tx.employee_registered_units));
            $('#d-employee-unregistered-units').text(fmtAmount(tx.employee_unregistered_units));

            $('#d-employer-amount').text(fmtAmount(tx.employer_amount));
            $('#d-employer-units').text(fmtAmount(tx.employer_units));
            $('#d-employer-registered-amount').text(fmtAmount(tx.employer_registered_amount));
            $('#d-employer-unregistered-amount').text(fmtAmount(tx.employer_unregistered_amount));
            $('#d-employer-registered-units').text(fmtAmount(tx.employer_registered_units));
            $('#d-employer-unregistered-units').text(fmtAmount(tx.employer_unregistered_units));

            $('#d-total-amount').text(fmtAmount(tx.total_amount || tx.amount));
            $('#d-total-units').text(fmtAmount(tx.total_units));

            $('#d-meta-summary').text('Trans Date: ' + (tx.trans_date ? new Date(tx.trans_date).toLocaleDateString() : '-'));

            $('#d-transacted-by').text(tx.transacted_by || '-');

            var cancellable = (tx.confirmed === null || tx.confirmed === undefined || tx.confirmed == 0) && !tx.deleted && !tx.reversed;
            if (cancellable) {
                $('#modal-cancel-btn').show();
            } else {
                $('#modal-cancel-btn').hide();
            }

            window.__currentDetailTxId = tx.id;

            $('#details-loading').addClass('d-none');
            $('#details-pane').removeClass('d-none');
            $('#cancelDetailsModal').modal('show');
        }

        function fmtAmount(val) {
            if (val === null || val === undefined || val === '') return '-';
            var str = String(val);
            var parts = str.split('.');
            var intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            if (parts.length > 1) {
                return intPart + '.' + parts[1];
            }
            return intPart;
        }

        // Modal Cancel handler
        $(document).on('click', '#modal-cancel-btn', function() {
            var txId = window.__currentDetailTxId;
            if (!txId) return toastr.error('No transaction selected for cancellation');

            var $btn = $(this);
            var original = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Cancelling...');

            $.post(`/cancel/${txId}`, {
                    _token: $('meta[name="csrf-token"]').attr('content')
                })
                .done(function(resp) {
                    if (resp && resp.success) {
                        toastr.success(resp.message || 'Transaction cancelled');
                        $('#cancelDetailsModal').modal('hide');
                        if (typeof window.refreshCancelList === 'function') window.refreshCancelList();
                        else if (typeof window.searchTransactions === 'function') window.searchTransactions();
                        else location.reload();
                    } else {
                        const msg = (resp && (resp.message || resp.error)) || 'Failed to cancel transaction';
                        toastr.error(msg);
                    }
                })
                .fail(function(xhr) {
                    const body = xhr.responseJSON || {};
                    const err = body.message || body.error || 'Failed to cancel transaction';
                    toastr.error(err);
                })
                .always(function() {
                    $btn.prop('disabled', false).html(original);
                });
        });

        // Close button inside modal
        $(document).on('click', '#close-cancel-details', function() {
            $('#cancelDetailsModal').modal('hide');
        });

    });
</script>
@endsection