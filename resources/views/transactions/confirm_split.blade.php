@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;" id="transactions-confirm-page">
    <div class="container-fluid">
        <!-- Stats bar -->
        <div class="row mb-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-body bg-primary text-white d-flex align-items-center py-2 px-3">
                        <span class="material-symbols-outlined me-2">query_stats</span>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="d-flex align-items-center gap-2">
                                <a href="{{ route('transactions.confirm.view') }}" class="text-white text-decoration-underline d-flex align-items-center">
                                    <span class="material-symbols-outlined me-1">arrow_back</span>
                                    Pending Transactions
                                </a>
                                <span class="badge bg-light text-dark" id="pending-count">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Split view -->
        <div class="row g-3">
            <!-- Left queue (DataTable) -->
            <div class="col-12 col-lg-5">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center py-2">
                        <h5 class="mb-0">Queue</h5>
                        <div class="ms-auto">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" id="refresh-queue">
                                <span class="spinner-border spinner-border-sm d-none me-1" id="refresh-spinner" role="status" aria-hidden="true"></span>
                                <span class="material-symbols-outlined me-1">refresh</span>
                                <span class="ms-1">Refresh</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 65vh; overflow:auto;">
                            <table id="queue-dt" class="table table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th>Date</th>
                                        <th>Member</th>
                                        <th>Mno</th>
                                        <th>Type</th>
                                        <th class="text-end">Amt</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right details -->
            <div class="col-12 col-lg-7">
                <div class="card h-100">
                    <div class="card-body position-relative">
                        <!-- Loading overlay for details -->
                        <div id="details-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(255,255,255,0.7); z-index: 10;">
                            <div class="d-flex align-items-center gap-2">
                                <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                                <div>Loading...</div>
                            </div>
                        </div>
                        <div id="details-empty" class="text-muted small">Select a transaction from the queue to view details.</div>
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
                                            <th>Totals</th>
                                            <th>Registered</th>
                                            <th>Unregistered</th>
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
                                            <th>Totals</th>
                                            <th>Registered</th>
                                            <th>Unregistered</th>
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

                            <!-- Totals -->
                            <div class="mb-2">
                                <div class="fw-semibold mb-1">Totals</div>
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted small" style="width: 30%">Total Amount</td>
                                            <td id="d-total-amount">-</td>
                                            <td class="text-muted small" style="width: 20%">Total Units</td>
                                            <td id="d-total-units">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Meta summary (compact, no label) -->
                            <div class="mb-2">
                                <div id="d-meta-summary" class="small text-muted">-</div>
                            </div>

                            <!-- Actions -->
                            <div class="d-flex justify-content-end align-items-center gap-2">
                                @permission('update_transactions')
                                <button class="btn btn-primary btn-sm d-flex align-items-center" id="d-confirm-btn">
                                    <span class="spinner-border spinner-border-sm d-none me-2" id="d-confirm-spinner"></span>
                                    <span class="material-symbols-outlined">verified</span>
                                    <span class="ms-1">Confirm</span>
                                </button>
                                @endpermission
                                <button class="btn btn-primary btn-sm d-flex align-items-center" id="d-skip-btn">
                                    <span class="material-symbols-outlined">navigate_next</span>
                                    <span class="ms-1">Next</span>
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function() {
        var ROUTES = {
            pendingCount: '{{ route("transactions.confirm.count") }}',
            queue: '{{ route("transactions.confirm.queue") }}',
            transactionsList: '{{ route("transactions.confirm.list") }}',
            transactionShow: '{{ url("/transactions") }}',
            transactionConfirm: '{{ url("/transactions") }}'
        };

        var queueDt = null;
        var selectedId = null;
        var initialLoadDone = false;
        var preloadId = null;

        function getQueryParam(name) {
            var params = new URLSearchParams(window.location.search);
            return params.get(name);
        }

        function fmtDate(val) {
            if (!val) return '';
            var d = new Date(val);
            return isNaN(d) ? val : d.toISOString().split('T')[0];
        }

        function fmtAmount(val) {
            if (val === null || val === undefined || val === '') return '-';
            // preserve decimals from string input if possible
            var str = String(val);
            var parts = str.split('.');
            var intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            if (parts.length > 1) {
                return intPart + '.' + parts[1];
            }
            return intPart;
        }

        function loadPendingCount() {
            $.get(ROUTES.pendingCount).done(function(res) {
                if (res && typeof res.pending !== 'undefined') {
                    $('#pending-count').text(res.pending);
                }
            });
        }

        function initQueueTable() {
            if (queueDt) return;
            var currentUser = '{{ optional(Auth::user())->username }}';
            queueDt = $('#queue-dt').DataTable({
                ajax: {
                    url: ROUTES.transactionsList,
                    dataSrc: 'transactions'
                },
                columns: [{
                        data: 'trans_date',
                        render: function(data) {
                            return fmtDate(data) || '-';
                        }
                    },
                    {
                        data: 'member_name',
                        defaultContent: 'Unknown'
                    },
                    {
                        data: 'member_no',
                        defaultContent: ''
                    },
                    {
                        data: 'trans_type',
                        defaultContent: ''
                    },
                    {
                        data: 'amount',
                        className: 'text-end',
                        render: function(data) {
                            return fmtAmount(data);
                        }
                    }
                ],
                paging: true,
                searching: true,
                info: true,
                ordering: true,
                order: [
                    [0, 'desc']
                ],
                pageLength: 15,
                deferRender: true,
                rowCallback: function(row, data) {
                    $(row).attr('data-id', data.id).css('cursor', 'pointer');
                },
                initComplete: function() {
                    maybePreload();
                },
                drawCallback: function() {
                    if (!initialLoadDone) {
                        maybePreload();
                    }
                }
            });
        }

        function reloadQueue(done) {
            if (!queueDt) {
                if (typeof done === 'function') done();
                return;
            }
            queueDt.ajax.reload(function() {
                loadPendingCount();
                if (typeof done === 'function') done();
            }, false);
        }

        function maybePreload() {
            var data = queueDt ? queueDt.rows({
                page: 'current'
            }).data().toArray() : [];
            if (preloadId) {
                showDetails(preloadId);
                highlightSelected(preloadId);
                initialLoadDone = true;
                return;
            }
            if (!initialLoadDone && data.length) {
                showDetails(data[0].id);
                highlightSelected(data[0].id);
                initialLoadDone = true;
            }
        }

        function highlightSelected(id) {
            $('#queue-dt tbody tr').removeClass('table-active');
            $('#queue-dt tbody tr[data-id="' + id + '"]').addClass('table-active');
        }

        function showDetails(id) {
            selectedId = id;
            $('#details-empty').addClass('d-none');
            $('#details-pane').removeClass('d-none');
            $('#details-loading').removeClass('d-none');
            $('#d-member-summary,#d-member-name,#d-account-no,#d-member-no,#d-member-email,#d-fund-name,#d-security-code,#d-employee-amount,#d-employee-units,#d-employee-registered-amount,#d-employee-unregistered-amount,#d-employee-registered-units,#d-employee-unregistered-units,#d-employer-amount,#d-employer-units,#d-employer-registered-amount,#d-employer-unregistered-amount,#d-employer-registered-units,#d-employer-unregistered-units,#d-total-amount,#d-total-units,#d-meta-summary').text('');

            $.get(ROUTES.transactionShow + '/' + id).done(function(res) {
                if (res && res.transaction) {
                    var t = res.transaction;
                    var summaryParts = [];
                    summaryParts.push(t.member_name || '-');
                    summaryParts.push('Acc: ' + (t.account_no || '-'));
                    summaryParts.push('Mno: ' + (t.member_no || '-'));
                    summaryParts.push(t.member_email || '-');
                    summaryParts.push((t.fund_name || t.security_code || '-'));
                    summaryParts.push('Sec: ' + (t.security_code || '-'));
                    $('#d-member-summary').text(summaryParts.join(' | '));

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

                    $('#d-total-amount').text(fmtAmount(t.amount));
                    $('#d-total-units').text(fmtAmount(t.total_units));

                    var metaParts = [];
                    metaParts.push('Trans Date: ' + (fmtDate(t.trans_date) || '-'));
                    metaParts.push('Transacted By: ' + (t.transacted_by || '-'));
                    $('#d-meta-summary').text(metaParts.join(' | '));

                    var currentUser = '{{ optional(Auth::user())->username }}';
                    var disabled = (t.transacted_by === currentUser);
                    $('#d-confirm-btn').prop('disabled', disabled);
                }
            }).always(function() {
                $('#details-loading').addClass('d-none');
            });
        }

        $(document).ready(function() {
            preloadId = getQueryParam('id');
            initQueueTable();
            reloadQueue();
            loadPendingCount();

            // Row click to load details
            $('#queue-dt tbody').on('click', 'tr', function(e) {
                var id = $(this).data('id');
                if (!id) return;
                showDetails(id);
                highlightSelected(id);
            });

            $('#d-skip-btn').on('click', function() {
                if (!queueDt) return;
                var data = queueDt.rows({
                    page: 'current'
                }).data().toArray();
                if (!data.length) return;
                var idx = data.findIndex(function(x) {
                    return String(x.id) === String(selectedId);
                });
                var next = (idx >= 0 && idx < data.length - 1) ? data[idx + 1] : data[0];
                showDetails(next.id);
                highlightSelected(next.id);
            });

            $('#refresh-queue').on('click', function() {
                var $btn = $(this);
                var $sp = $('#refresh-spinner');
                $btn.prop('disabled', true);
                $sp.removeClass('d-none');
                reloadQueue(function() {
                    $sp.addClass('d-none');
                    $btn.prop('disabled', false);
                });
            });

            $('#d-confirm-btn').on('click', function() {
                var id = selectedId;
                if (!id) return toastr.error('No transaction selected');
                var $btn = $(this);
                if ($btn.prop('disabled')) return toastr.error('You cannot confirm this transaction');
                $btn.prop('disabled', true);
                $('#d-confirm-spinner').removeClass('d-none');

                $.post(ROUTES.transactionConfirm + '/' + id + '/confirm', {
                    _token: $('meta[name="csrf-token"]').attr('content')
                }).done(function(res) {
                    if (res && res.success) {
                        toastr.success(res.message || 'Transaction confirmed');
                        reloadQueue();
                        $('#details-empty').removeClass('d-none');
                        $('#details-pane').addClass('d-none');
                        selectedId = null;
                    } else {
                        toastr.error(res.error || 'Failed to confirm');
                    }
                }).fail(function(xhr) {
                    toastr.error((xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to confirm');
                }).always(function() {
                    $('#d-confirm-spinner').addClass('d-none');
                    $btn.prop('disabled', false);
                });
            });
        });
    })();
</script>
<style>
    /* Condensed tables in details pane */
    #details-pane .table-condensed-custom th,
    #details-pane .table-condensed-custom td {
        padding: 0.15rem 0.25rem;
        vertical-align: middle;
        font-size: 0.8rem;
        line-height: 1.1;
    }

    #details-pane .table-condensed-custom thead th {
        color: #6c757d;
        font-weight: 600;
        font-size: 0.8rem;
    }

    /* Right-align numeric column headers (Totals, Registered, Unregistered) */
    #details-pane .table-condensed-custom thead th:not(:first-child) {
        text-align: right;
    }

    /* Align label column to the right for better vertical alignment with right-aligned values */
    #details-pane .table-condensed-custom td:first-child,
    #details-pane .table-condensed-custom th:first-child {
        text-align: right;
        white-space: nowrap;
    }

    #details-pane .table-borderless> :not(caption)>*>* {
        border-bottom-width: 0;
    }

    #details-pane .fw-semibold {
        font-size: 0.95rem;
    }

    #details-pane .small {
        font-size: 0.825rem;
    }

    #details-loading .spinner-border {
        width: 1.2rem;
        height: 1.2rem;
    }

    /* Bold the Totals column (second column) in condensed tables */
    #details-pane .table-condensed-custom thead th:nth-child(2),
    #details-pane .table-condensed-custom tbody td:nth-child(2) {
        font-weight: 700;
    }
</style>
@endsection