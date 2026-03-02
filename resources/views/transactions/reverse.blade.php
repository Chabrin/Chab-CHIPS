@extends('layouts.main')

@section('title', 'Transaction Reversal')

@section('content')
<style>
    .reversal-filters .form-label {
        font-size: .85rem;
        margin-bottom: .25rem;
    }

    .reversal-filters .form-control {
        height: calc(1.6em + .5rem);
        padding: .25rem .5rem;
        font-size: .85rem;
    }

    .btn-icon {
        display: inline-flex;
        align-items: center;
    }

    .btn-icon .material-symbols-outlined {
        font-size: 18px;
    }

    ul.action {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        gap: .5rem;
    }

    ul.action li {
        display: inline-block;
    }

    /* Condensed modal/table styles similar to confirm_split */
    .table-condensed-custom th,
    .table-condensed-custom td {
        padding: .15rem .5rem;
        vertical-align: middle;
    }

    /* Provide a medium modal size when using modal-md (Bootstrap may not define this by default) */
    .modal-dialog.modal-md {
        max-width: 820px;
        /* medium width */
        width: 100%;
        margin: 1.75rem auto;
    }

    #transactionDetailsModal .modal-body {
        font-size: .95rem;
        padding-top: .8rem;
        padding-bottom: .8rem;
    }

    #transactionDetailsModal .fw-semibold {
        font-weight: 600;
    }
</style>

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">
                            <span class="material-symbols-outlined">undo</span> Transaction Reversal
                        </h4>
                        <div class="text-muted ms-auto">
                            <small>Search and initiate transaction reversals</small>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Search Filters -->
                        <form id="reversalFilter" class="row g-2 mb-3 reversal-filters align-items-end">
                            <div class="col-md-2">
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
                                <input type="text" class="form-control form-control-sm" id="search_term" name="search_term"
                                    placeholder="Account No, Member No, Reference...">
                            </div>

                            <div class="col-md-3">
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

                        <!-- No Results Message -->
                        <div id="noResultsMessage" class="alert alert-warning d-none">
                            <!-- No longer needed: toastr will be used instead of this alert. -->
                        </div>

                        <!-- Results Container -->
                        <div id="resultsContainer" class="">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Search Results</h5>
                                <span id="resultsCount" class="badge bg-info"></span>
                            </div>

                            <div class="table-responsive custom-scrollbar">
                                <table class="table table-hover table-sm" id="basic-1" style="font-size: 0.85rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Account</th>
                                            <th>Member No</th>
                                            <th>Reference</th>
                                            <th>Type</th>
                                            <th class="text-end">Amount</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="transactionsTableBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><!-- Transaction Details Modal -->
<div class="modal fade" id="transactionDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header p-2 border-0">
                <!-- Heading removed to maximize space -->
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2" id="transactionDetailsContent" style="font-size:0.85rem;">
                <!-- Content will be loaded via AJAX -->
                <div id="details-loading" class="d-flex align-items-center justify-content-center py-4">
                    <div class="d-flex align-items-center gap-2">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div>Loading transaction details...</div>
                    </div>
                </div>

                <div id="details-pane" class="d-none">
                    <!-- Member Details -->
                    <div class="mb-2">
                        <div class="fw-semibold mb-1">Member Details</div>
                        <div id="d-member-summary" class="small">-</div>
                    </div>

                    <!-- Employee Section -->
                    <div class="mb-2">
                        <div class="fw-semibold mb-1">Employee</div>
                        <table class="table table-sm table-borderless mb-0 table-condensed-custom">
                            <thead>
                                <tr>
                                    <th style="width: 20%"></th>
                                    <th class="text-end">Totals</th>
                                    <th class="text-end">Registered</th>
                                    <th class="text-end">Unregistered</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-muted small">Amount</td>
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

                    <!-- Employer Section -->
                    <div class="mb-2">
                        <div class="fw-semibold mb-1">Employer</div>
                        <table class="table table-sm table-borderless mb-0 table-condensed-custom">
                            <thead>
                                <tr>
                                    <th style="width: 20%"></th>
                                    <th class="text-end">Totals</th>
                                    <th class="text-end">Registered</th>
                                    <th class="text-end">Unregistered</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-muted small">Amount</td>
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

                    <!-- Totals + Meta (single-line compact) -->
                    <div class="mb-2">
                        <div id="d-totals-line" class="d-flex justify-content-between align-items-center small">
                            <div id="d-totals-left" class="text-start">
                                <span class="text-muted">Totals</span>
                                &nbsp;&nbsp;
                                <strong>Total Amount:</strong>
                                <span id="d-total-amount">-</span>
                                &nbsp;&nbsp;
                                <strong>Total Units:</strong>
                                <span id="d-total-units">-</span>
                            </div>
                            <div id="d-meta-summary" class="text-end text-muted small">-</div>
                        </div>
                    </div>

                    <!-- Actions (Reverse Reason + Cancel) - embedded to avoid duplicate modal -->
                    <div class="mt-2">
                        <label for="detail_reversal_reason" class="form-label small mb-1">Reason for Reversal <span class="text-danger">*</span></label>
                        <textarea id="detail_reversal_reason" class="form-control form-control-sm" rows="2" placeholder="Provide reason for reversal..."></textarea>
                        <div id="detail_reversal_reason_error" class="invalid-feedback d-none small"></div>
                    </div>
                    <div class="d-flex justify-content-end align-items-center gap-2 mt-2">
                        <button class="btn btn-danger btn-sm d-flex align-items-center" id="d-confirm-reversal-btn" style="display:none;">
                            <span class="material-symbols-outlined">undo</span>
                            <span class="ms-1">Initiate Reversal</span>
                        </button>
                        <button class="btn btn-outline-secondary btn-sm d-flex align-items-center" id="d-cancel-btn">
                            <span class="material-symbols-outlined">cancel</span>
                            <span class="ms-1">Close</span>
                        </button>
                    </div>
                </div>
            </div>
            <!-- Footer reduced; actions are inside body to save space -->
            <div class="modal-footer p-2 border-0">
                <!-- Footer actions intentionally minimal; primary actions are in the modal body -->
                <button type="button" class="btn btn-danger btn-sm d-none" id="initiateReversalBtn">
                    <span class="material-symbols-outlined">undo</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        let currentTransactionId = null;

        // Search button click
        $('#searchBtn').click(function() {
            // Client-side validation: ensure company and dates are selected
            const companyId = $('#company_id').val();
            const dateFrom = $('#date_from').val();
            const dateTo = $('#date_to').val();
            if (!companyId) {
                toastr.error('Please select a company before searching', 'Validation');
                return;
            }
            if (!dateFrom || !dateTo) {
                toastr.error('Please select both From and To dates', 'Validation');
                return;
            }
            searchTransactions();
        });

        // Clear button click
        $('#clearBtn').click(function() {
            $('#company_id').val('');
            $('#date_from').val('');
            $('#date_to').val('');
            $('#search_term').val('');
            clearResults();
        });

        // Enter key in search field
        $('#search_term').keypress(function(e) {
            if (e.which == 13) {
                searchTransactions();
            }
        });

        // View details button click: show inline spinner, fetch, then open modal on success
        $(document).on('click', '.view-details-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const transactionId = $btn.data('transaction-id');
            // avoid duplicate clicks
            if ($btn.data('loading')) return;
            fetchAndShowTransactionDetails(transactionId, $btn);
        });

        // Confirm reversal from details modal (inline)
        $(document).on('click', '#d-confirm-reversal-btn', function() {
            initiateReversalFromDetails();
        });

        function toggleSpinner(on) {
            // Reserve spinner space to avoid layout shift when toggling
            if (on) {
                $('#searchSpinner').removeClass('d-none').css('display', 'inline-block');
                $('#searchBtn').prop('disabled', true);
            } else {
                $('#searchSpinner').addClass('d-none').css('display', 'inline-block');
                $('#searchBtn').prop('disabled', false);
            }
        }

        function clearResults() {
            // Clear only the table body and UI counters
            $('#transactionsTableBody').empty();
            $('#resultsCount').text('');
            $('#noResultsMessage').addClass('d-none');
        }

        function searchTransactions() {
            const formData = {
                company_id: $('#company_id').val(),
                date_from: $('#date_from').val(),
                date_to: $('#date_to').val(),
                search_term: $('#search_term').val(),
                _token: $('meta[name="csrf-token"]').attr('content')
            };

            toggleSpinner(true);
            // Keep current rows visible until new results arrive to avoid flicker
            $('#resultsCount').text('');
            $('#noResultsMessage').addClass('d-none');

            $.post('/search/reversal', formData)
                .done(function(response) {
                    toggleSpinner(false);

                    if (response.success && response.transactions && response.transactions.length > 0) {
                        displayTransactions(response.transactions);
                        $('#resultsCount').text(response.transactions.length + ' transaction(s) found');
                    } else {
                        // Clear previous rows to show 'no results' state
                        $('#transactionsTableBody').empty();
                        toastr.warning('No transactions found - Try adjusting your search criteria', 'Search');
                    }
                })
                .fail(function(xhr) {
                    toggleSpinner(false);
                    const errorMsg = xhr.responseJSON?.message || 'Search failed. Please try again.';
                    showAlert('danger', errorMsg);
                });
        }

        function displayTransactions(transactions) {
            const tbody = $('#transactionsTableBody');
            tbody.empty();

            transactions.forEach(function(transaction) {
                // Show amount directly from DB (use amount, fallback to net_amount)
                const amount = transaction.amount !== undefined ? transaction.amount : transaction.net_amount;

                // Always show a Details link that opens the transaction details modal
                const actionBtn = `<a href="#" class="view-details-btn small" data-transaction-id="${transaction.id}">Details</a>`;

                const row = `
                <tr>
                    <td>${new Date(transaction.trans_date).toLocaleDateString()}</td>
                    <td>${transaction.account_no}</td>
                    <td>${transaction.member_no || '<span class="text-muted">N/A</span>'}</td>
                    <td>${transaction.reference_no || 'N/A'}</td>
                    <td>${transaction.trans_type}</td>
                    <td class="text-end">${amount}</td>
                    <td class="text-center">${actionBtn}</td>
                </tr>
            `;
                tbody.append(row);
            });

            // If DataTables is present, redraw to keep pagination and length controls intact
            const dt = $.fn.DataTable.isDataTable('#basic-1') ? $('#basic-1').DataTable() : null;
            if (dt) {
                dt.clear().draw();
                // Append rows to DataTable body via native API to avoid reinitialization
                transactions.forEach(function(transaction) {
                    // Show amount directly from DB (use amount, fallback to net_amount)
                    const amount = transaction.amount !== undefined ? transaction.amount : transaction.net_amount;
                    const actionBtn = `<a href="#" class="view-details-btn small" data-transaction-id="${transaction.id}">Details</a>`;
                    dt.row.add([
                        new Date(transaction.trans_date).toLocaleDateString(),
                        transaction.account_no,
                        transaction.member_no || 'N/A',
                        transaction.reference_no || 'N/A',
                        transaction.trans_type,
                        amount,
                        actionBtn
                    ]).draw(false);
                });
            }
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

        // Render a successful response into the details modal (does not perform fetch)
        function renderTransactionDetails(payload) {
            const t = (payload && payload.transaction) ? payload.transaction : payload;
            const member = payload.member || null;

            // Build member summary from available sources: payload.member OR transaction.member_name/member_email
            let memberSummary = '';
            if (member) {
                memberSummary = `${member.first_name || ''} ${member.last_name || ''}`.trim();
                memberSummary += ` | Acc: ${t.account_no || '-'} | Mno: ${member.member_no || '-'} | ${member.e_mail || member.email || ''} | ${t.fund_name || t.security_code || '-'} | Sec: ${t.security_code || '-'} `;
            } else if (t.member_name || t.member_email) {
                memberSummary = `${t.member_name || ''}`.trim();
                memberSummary += ` | Acc: ${t.account_no || '-'} | Mno: ${t.member_no || '-'} | ${t.member_email || '-'} | ${t.fund_name || t.security_code || '-'} | Sec: ${t.security_code || '-'} `;
            } else {
                memberSummary = '<span class="text-muted">No member information available</span>';
            }
            $('#d-member-summary').html(memberSummary);

            // Populate split fields (employee/employer/totals)
            $('#d-employee-amount').text(fmtAmount(t.employee_amount));
            $('#d-employee-units').text(fmtAmount(t.employee_units));
            $('#d-employee-registered-amount').text(fmtAmount(t.employee_registered_amount));
            $('#d-employee-unregistered-amount').text(fmtAmount(t.employee_unregistered_amount));
            $('#d-employee-registered-units').text(fmtAmount(t.employee_registered_units));
            $('#d-employee-unregistered-units').text(fmtAmount(t.employee_unregistered_units));

            $('#d-employer-amount').text(fmtAmount(t.employer_amount));
            $('#d-employer-units').text(fmtAmount(t.employer_units));
            $('#d-employer-registered-amount').text(fmtAmount(t.employer_registered_amount));
            $('#d-employer-unregistered-amount').text(fmtAmount(t.employer_unregistered_amount));
            $('#d-employer-registered-units').text(fmtAmount(t.employer_registered_units));
            $('#d-employer-unregistered-units').text(fmtAmount(t.employer_unregistered_units));

            $('#d-total-amount').text(fmtAmount(t.total_amount || t.amount));
            $('#d-total-units').text(fmtAmount(t.total_units));

            // Meta summary
            var metaParts = [];
            metaParts.push('Trans Date: ' + (t.trans_date ? new Date(t.trans_date).toLocaleDateString() : '-'));
            metaParts.push('Transacted By: ' + (t.transacted_by || '-'));
            $('#d-meta-summary').text(metaParts.join(' | '));

            // Show/hide inline confirm button (condensed)
            const canReverse = (!t.reversal_initiated && (t.status === 'confirmed' || t.confirmed == 1 || t.confirmed === 1));
            $('#d-confirm-reversal-btn').toggle(canReverse);

            // reduce some visual weight for compact modal
            $('#transactionDetailsContent').css('font-size', '0.82rem');
            // switch to medium modal size (use modal-md as requested)
            $('#transactionDetailsModal .modal-dialog').removeClass('modal-xl modal-lg').addClass('modal-md');

            // Hide loading and show details
            $('#details-loading').addClass('d-none');
            $('#details-pane').removeClass('d-none');
        }

        // Cancel button in details pane
        $(document).on('click', '#d-cancel-btn', function() {
            $('#transactionDetailsModal').modal('hide');
        });

        // Fetch transaction details and only show modal on success. Show inline spinner on the clicked button.
        function fetchAndShowTransactionDetails(transactionId, $btn) {
            if (!transactionId) return;
            $btn.data('loading', true);
            const originalHtml = $btn.html();
            // insert a small spinner inside the link
            $btn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...');

            $.get(`/transactions/${transactionId}`)
                .done(function(response) {
                    // Accept multiple shapes:
                    // 1) { transaction: { ... } }
                    // 2) { success: true, transaction: { ... } }
                    // 3) raw transaction object
                    let tx = null;
                    if (response && response.transaction) tx = response.transaction;
                    else if (response && response.success && response.transaction) tx = response.transaction;
                    else if (response && response.id && response.trans_type) tx = response; // raw

                    if (tx) {
                        // prepare modal and render
                        // Clear any previous reason or validation state first
                        resetDetailsModalState();
                        $('#details-loading').removeClass('d-none');
                        $('#details-pane').addClass('d-none');
                        renderTransactionDetails(tx);
                        // ensure currentTransactionId is set for reversal flow
                        currentTransactionId = tx.id || currentTransactionId;
                        $('#transactionDetailsModal').modal('show');
                    } else {
                        const msg = (response && (response.error || response.message)) || 'No details found for this transaction.';
                        toastr.warning(msg, 'Details');
                    }
                })
                .fail(function(xhr) {
                    const errorMsg = xhr.responseJSON?.message || 'Failed to load transaction details.';
                    toastr.error(errorMsg, 'Error');
                })
                .always(function() {
                    $btn.data('loading', false);
                    $btn.html(originalHtml);
                });
        }
        // Reset reason and validation state inside the details modal
        function resetDetailsModalState() {
            $('#detail_reversal_reason').val('');
            $('#detail_reversal_reason').removeClass('is-invalid');
            $('#detail_reversal_reason_error').addClass('d-none').text('');
        }

        // Initiate reversal directly from details modal
        function initiateReversalFromDetails() {
            const transactionId = currentTransactionId;
            const reason = $('#detail_reversal_reason').val() || '';
            if (!transactionId) return showAlert('warning', 'No transaction selected for reversal.');
            if (!reason.trim()) return showAlert('warning', 'Please provide a reason for the reversal.');

            const $btn = $('#d-confirm-reversal-btn');
            $btn.prop('disabled', true).html('<span class="material-symbols-outlined">sync</span> Processing...');

            const formData = {
                transaction_id: transactionId,
                reversal_reason: reason,
                _token: $('meta[name="csrf-token"]').attr('content')
            };

            // clear previous validation state
            $('#detail_reversal_reason').removeClass('is-invalid');
            $('#detail_reversal_reason_error').addClass('d-none').text('');

            $.post(`/transaction/${transactionId}/initiate-reversal`, formData)
                .done(function(response) {
                    if (response && response.success) {
                        $('#transactionDetailsModal').modal('hide');
                        showAlert('success', response.message || 'Reversal initiated');
                        // refresh list
                        searchTransactions();
                        // clear reason
                        $('#detail_reversal_reason').val('');
                    } else {
                        const msg = (response && (response.message || response.error)) || 'Failed to initiate reversal.';
                        // If server returned validation errors in response.errors, show them
                        if (response && response.errors && response.errors.reversal_reason) {
                            const err = response.errors.reversal_reason.join(' ');
                            $('#detail_reversal_reason').addClass('is-invalid');
                            $('#detail_reversal_reason_error').removeClass('d-none').text(err);
                        }
                        showAlert('danger', msg);
                    }
                })
                .fail(function(xhr) {
                    // Try to parse validation errors
                    const body = xhr.responseJSON || {};
                    if (body.errors && body.errors.reversal_reason) {
                        const err = body.errors.reversal_reason.join(' ');
                        $('#detail_reversal_reason').addClass('is-invalid');
                        $('#detail_reversal_reason_error').removeClass('d-none').text(err);
                        toastr.error(err, 'Validation');
                    } else {
                        const errorMsg = body.message || 'Failed to initiate reversal.';
                        showAlert('danger', errorMsg);
                    }
                })
                .always(function() {
                    $btn.prop('disabled', false).html('<span class="material-symbols-outlined">undo</span> Initiate Reversal');
                });
        }

        function showAlert(type, message) {
            // Use toastr instead of bootstrap alerts
            switch (type) {
                case 'success':
                    toastr.success(message);
                    break;
                case 'danger':
                case 'error':
                    toastr.error(message);
                    break;
                case 'warning':
                    toastr.warning(message);
                    break;
                case 'info':
                    toastr.info(message);
                    break;
                default:
                    toastr.info(message);
            }
        }
    });
</script>
@endsection