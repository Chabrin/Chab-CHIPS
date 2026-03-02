@extends('layouts.main')

@section('title', 'Reversal Confirmations')

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

    /* Modal containment and responsive adjustments for reversal details */
    #reversalDetailsModal .modal-dialog.modal-md {
        max-width: 800px;
    }

    #reversalDetailsModal .modal-body {
        /* Let Bootstrap handle modal height so it behaves like reverse.blade (no internal scroller) */
        padding-bottom: 1rem;
    }

    #reversalDetailsModal .table-condensed-custom td,
    #reversalDetailsModal .table-condensed-custom th {
        white-space: normal;
        /* allow wrapping */
        word-break: break-word;
        vertical-align: middle;
    }

    #reversalDetailsModal #d-totals-line {
        flex-wrap: wrap;
        gap: .5rem;
    }
</style>

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">
                            <span class="material-symbols-outlined">check_circle</span> Reversal Confirmations
                        </h4>
                        <div class="text-muted ms-auto">
                            <small>Review and confirm pending transaction reversals</small>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- No filters on this page - list only -->

                        <!-- No Results Message -->
                        <div id="noResultsMessage" class="alert alert-warning d-none">
                            <span class="material-symbols-outlined">search_off</span>
                            <strong>No pending reversals found</strong> - All reversals may have been processed
                        </div>

                        <!-- Results Container -->
                        <div id="resultsContainer" class="">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Pending Reversals</h5>
                                <span id="resultsCount" class="badge bg-warning"></span>
                            </div>

                            <div class="table-responsive custom-scrollbar">
                                <table class="table table-hover table-sm" id="basic-1" style="font-size: 0.85rem;">
                                    <thead class="table-light">
                                        <tr>

                                            <th>Initiated</th>
                                            <th>Trans Date</th>
                                            <th>Account</th>
                                            <th>Member</th>
                                            <th class="text-end">Amount</th>
                                            <th>By</th>
                                            <th>Reason</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reversalsTableBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><!-- Reversal Details Modal -->
<div class="modal fade" id="reversalDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header p-2 border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3" id="reversalDetailsContent" style="font-size:0.9rem;">
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

                    <!-- Employee then Employer stacked -->
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

                    <!-- Reversal meta -->
                    <div class="mb-2 small">
                        <div><strong>Initiated By:</strong> <span id="d-reversal-by">-</span></div>
                        <div><strong>Initiated On:</strong> <span id="d-reversal-on">-</span></div>
                        <div><strong>Reason:</strong> <span id="d-reversal-reason">-</span></div>
                    </div>

                    <div class="d-flex justify-content-end align-items-center gap-2 mt-2">
                        <button class="btn btn-success btn-sm d-flex align-items-center" id="confirm-reversal-btn" style="display:none;">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Confirm Reversal</span>
                        </button>
                        <button class="btn btn-outline-secondary btn-sm d-flex align-items-center" id="close-reversal-details">
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
    $(document).ready(function() {
        let currentReversalId = null;

        // Load pending reversals on page load (no filters)
        loadReversals();

        // Click handler for Details link
        $(document).on('click', '.view-details-btn', function(e) {
            e.preventDefault();
            const id = $(this).data('reversal-id');
            if (!id) return;
            // show spinner in link
            const $btn = $(this);
            const original = $btn.html();
            $btn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...');
            fetchAndShowDetails(id, $btn, original);
        });

        // Close details
        $(document).on('click', '#close-reversal-details', function() {
            $('#reversalDetailsModal').modal('hide');
        });

        // Confirm reversal from modal
        $(document).on('click', '#confirm-reversal-btn', function() {
            const id = currentReversalId;
            if (!id) return showAlert('warning', 'No reversal selected');
            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="material-symbols-outlined">sync</span> Processing...');

            $.post(`/reversal/${id}/confirm`, {
                    _token: $('meta[name="csrf-token"]').attr('content')
                })
                .done(function(resp) {
                    if (resp && resp.success) {
                        toastr.success(resp.message || 'Reversal confirmed');
                        $('#reversalDetailsModal').modal('hide');
                        loadReversals();
                    } else {
                        showAlert('danger', resp.message || 'Failed to confirm reversal');
                    }
                })
                .fail(function(xhr) {
                    const body = xhr.responseJSON || {};
                    // Prefer explicit message, then error, then generic fallback
                    const err = body.message || body.error || (xhr.responseText ? xhr.responseText : null) || 'Failed to confirm reversal';
                    showAlert('danger', err);
                })
                .always(function() {
                    $btn.prop('disabled', false).html('<span class="material-symbols-outlined">check_circle</span> Confirm Reversal');
                });
        });

        function loadReversals() {
            $.get('/search/reversal-confirmations')
                .done(function(resp) {
                    if (resp && resp.success && resp.reversals && resp.reversals.length) {
                        renderReversalsTable(resp.reversals);
                        $('#resultsCount').text(resp.reversals.length + ' pending reversal(s)');
                    } else {
                        $('#reversalsTableBody').empty();
                        $('#resultsCount').text('0');
                        toastr.info('No pending reversals found');
                    }
                })
                .fail(function(xhr) {
                    showAlert('danger', 'Failed to load pending reversals');
                });
        }

        function renderReversalsTable(reversals) {
            const tbody = $('#reversalsTableBody');
            tbody.empty();
            reversals.forEach(function(r) {
                const amount = r.net_amount || r.amount || 0;
                const initiated = r.reversal_initiated_on ? new Date(r.reversal_initiated_on).toLocaleDateString() : '-';
                const transDate = r.trans_date ? new Date(r.trans_date).toLocaleDateString() : '-';
                // Show member_no in the Member column per request
                const member = r.member_no || r.member_no === 0 ? (r.member_no) : (r.member_name || (r.member ? (r.member.member_no || 'N/A') : 'N/A'));

                const action = `<a href="#" class="view-details-btn small" data-reversal-id="${r.id}">Details</a>`;

                const row = `
                    <tr>
                        <td>${initiated}</td>
                        <td>${transDate}</td>
                        <td>${r.account_no}</td>
                        <td>${member}</td>
                        <td class="text-end">${amount}</td>
                        <td>${r.reversal_initiated_by || 'System'}</td>
                        <td><span class="text-truncate d-inline-block" style="max-width:200px;">${r.reversal_reason || ''}</span></td>
                        <td class="text-center">${action}</td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        // Fetch transaction details using the same endpoint as /reverse/transaction
        function fetchAndShowDetails(id, $btn, originalHtml) {
            $.get(`/transactions/${id}`)
                .done(function(response) {
                    let tx = null;
                    if (response && response.transaction) tx = response.transaction;
                    else if (response && response.success && response.transaction) tx = response.transaction;
                    else if (response && response.id) tx = response;

                    if (!tx) {
                        toastr.warning('No details available for this reversal');
                        return;
                    }

                    // Clear any previous details and set current id
                    currentReversalId = tx.id;
                    resetDetailsModalState();

                    // Populate modal fields (reuse IDs from reverse view)
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

                    // reversal metadata
                    $('#d-reversal-by').text(tx.reversal_initiated_by || tx.reversal_initiated_by || '-');
                    $('#d-reversal-on').text(tx.reversal_initiated_on ? new Date(tx.reversal_initiated_on).toLocaleString() : '-');
                    $('#d-reversal-reason').text(tx.reversal_reason || '-');

                    // Show confirm button if reversal initiated and not yet confirmed
                    const canConfirm = tx.reversal_initiated && !tx.reversal_confirmed && !tx.reversed;
                    $('#confirm-reversal-btn').toggle(canConfirm);

                    $('#details-loading').addClass('d-none');
                    $('#details-pane').removeClass('d-none');
                    $('#reversalDetailsModal').modal('show');
                })
                .fail(function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Failed to load details';
                    showAlert('danger', msg);
                })
                .always(function() {
                    if ($btn && originalHtml) $btn.html(originalHtml);
                });
        }

        // small helpers copied from reverse view
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

        function resetDetailsModalState() {
            // Clear modal panes and validation states
            $('#details-loading').removeClass('d-none');
            $('#details-pane').addClass('d-none');
            $('#d-member-summary').text('-');
            $('#d-employee-amount, #d-employee-units, #d-employee-registered-amount, #d-employee-unregistered-amount, #d-employee-registered-units, #d-employee-unregistered-units').text('-');
            $('#d-employer-amount, #d-employer-units, #d-employer-registered-amount, #d-employer-unregistered-amount, #d-employer-registered-units, #d-employer-unregistered-units').text('-');
            $('#d-total-amount, #d-total-units, #d-meta-summary').text('-');
            $('#d-reversal-by, #d-reversal-on, #d-reversal-reason').text('-');
        }

        function showAlert(type, message) {
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