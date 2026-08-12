@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;" id="transactions-confirm-page">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="mb-0">Confirm Transactions</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-xs d-flex align-items-center" id="refresh-transactions">
                                <span class="spinner-border spinner-border-sm d-none me-1" id="refresh-spinner" role="status" aria-hidden="true"></span>
                                <span class="material-symbols-outlined me-1">refresh</span>
                                <span class="ms-1">Refresh</span>
                            </button>
                            @permission('update_transactions')
                            <button id="btn-bulk-confirm" class="btn btn-primary btn-xs d-flex align-items-center">
                                <span class="spinner-border spinner-border-sm d-none me-1" id="bulk-confirm-spinner" role="status" aria-hidden="true"></span>
                                <span class="material-symbols-outlined me-1">verified</span>
                                <span class="ms-1">Bulk Confirm</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar position-relative">
                            <div id="transactions-loading" class="table-loading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#fff;z-index:5;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                                    <div>Loading Transactions...</div>
                                </div>
                            </div>
                            <table class="display" id="transactions-dt">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="select-all" /></th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Mno</th>
                                        <th>Acc No</th>
                                        <th>Names</th>
                                        <th>Amount</th>
                                        <th>Action</th>
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

<!-- View Transaction Modal -->
<div class="modal fade" id="viewTransactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="small text-muted">Trans Date</div>
                        <div id="view-trans-date" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Type</div>
                        <div id="view-trans-type" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">MOP</div>
                        <div id="view-mop" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Member No</div>
                        <div id="view-member-no" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-8">
                        <div class="small text-muted">Member Name</div>
                        <div id="view-member-name" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Account No</div>
                        <div id="view-account-no" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Security Code</div>
                        <div id="view-security-code" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Employer Amt</div>
                        <div id="view-employer-amount" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Employee Amt</div>
                        <div id="view-employee-amount" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Total Amount</div>
                        <div id="view-total-amount" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Employer Units</div>
                        <div id="view-employer-units" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Employee Units</div>
                        <div id="view-employee-units" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">NAV / Price</div>
                        <div id="view-price" class="fw-semibold">-</div>
                    </div>
                    <div class="col-12" id="view-reference-section" style="display:none">
                        <div class="small text-muted">Reference No</div>
                        <div id="view-reference-no" class="fw-semibold">-</div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Status</div>
                        <div id="view-status">-</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {

        var ROUTES = {
            transactionsList: '{{ route("transactions.confirm.list") }}',
            transactionShow: '{{ url("/transactions") }}',
            bulkConfirm: '{{ route("transactions.bulk.confirm") }}',
            reviewView: '{{ route("transactions.confirm.review") }}'
        };

        var dt;

        function showLoading() {
            $('#transactions-loading').show();
        }

        function hideLoading() {
            $('#transactions-loading').hide();
        }

        $(document).ready(function() {
            showLoading();

            // Initialize DataTable
            dt = $('#transactions-dt').DataTable({
                ajax: {
                    url: ROUTES.transactionsList,
                    dataSrc: 'transactions'
                },
                columns: [{
                        data: null,
                        orderable: false,
                        render: function(data, type, row) {
                            if (row.transacted_by === '{{ optional(Auth::user())->username }}') {
                                return '<input type="checkbox" class="select-row" value="' + row.id + '" disabled title="Cannot confirm own transactions" />';
                            }
                            return '<input type="checkbox" class="select-row" value="' + row.id + '" />';
                        }
                    },
                    {
                        data: 'trans_date',
                        render: function(data) {
                            if (!data) return '-';
                            var d = new Date(data);
                            return d.toISOString().split('T')[0];
                        }
                    },
                    {
                        data: 'trans_type'
                    },
                    {
                        data: 'member_no'
                    },
                    {
                        data: 'account_no'
                    },
                    {
                        data: 'member_name'
                    },
                    {
                        data: 'amount',
                        render: function(data) {
                            if (data === null || data === undefined || data === '') return '-';
                            var str = String(data);
                            var parts = str.split('.');
                            var intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                            return parts.length > 1 ? intPart + '.' + parts[1] : intPart;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function(row) {
                            var out = [];
                            // View link
                            out.push('<a href="#" class="view-transaction me-3" style="color: #3598db; font-weight: bold; text-decoration: underline;" data-id="' + row.id + '">View</a>');
                            // Confirm link navigates to split review with id
                            out.push('<a href="' + ROUTES.reviewView + '?id=' + row.id + '" style="color: #3598db; font-weight: bold; text-decoration: underline;">Confirm</a>');
                            return out.join('');
                        }
                    }
                ],
                order: [
                    [1, 'desc']
                ],
                pageLength: 15,
                initComplete: hideLoading
            });

            // Select all functionality
            $('#select-all').on('change', function() {
                $('.select-row:not(:disabled)').prop('checked', this.checked);
            });

            // View Transaction
            $(document).on('click', '.view-transaction', function(e) {
                e.preventDefault();
                var $link = $(this);
                var id = $link.data('id');
                var originalText = $link.text();
                var originalHtml = $link.html();

                // Show loading state with spinner
                $link.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>').css('opacity', '0.8');

                $.get(ROUTES.transactionShow + '/' + id).done(function(res) {
                    if (res && res.transaction) {
                        var t = res.transaction;
                        var formattedDate = '';
                        if (t.trans_date) {
                            var d = new Date(t.trans_date);
                            if (!isNaN(d.getTime())) {
                                formattedDate = d.toLocaleDateString();
                            } else {
                                formattedDate = t.trans_date;
                            }
                        }

                        $('#view-trans-date').text(formattedDate || 'N/A');
                        $('#view-trans-type').text(t.trans_type || 'N/A');
                        $('#view-member-no').text(t.member_no || 'N/A');
                        $('#view-account-no').text(t.account_no || 'N/A');
                        $('#view-member-name').text(t.member_name || 'N/A');
                        $('#view-security-code').text(t.security_code || 'N/A');
                        $('#view-mop').text(t.mop || 'N/A');
                        $('#view-employer-amount').text(t.employer_amount || 'N/A');
                        $('#view-employee-amount').text(t.employee_amount || 'N/A');
                        $('#view-total-amount').text(t.amount || 'N/A');
                        $('#view-employer-units').text(t.employer_units || 'N/A');
                        $('#view-employee-units').text(t.employee_units || 'N/A');
                        $('#view-price').text(t.price || 'N/A');

                        if (t.reference_no) {
                            $('#view-reference-no').text(t.reference_no);
                            $('#view-reference-section').show();
                        } else {
                            $('#view-reference-section').hide();
                        }

                        var statusHtml = t.confirmed === '1' ? '<span class="badge bg-success">Confirmed</span>' : '<span class="badge bg-warning">Pending</span>';
                        $('#view-status').html(statusHtml);

                        $('#viewTransactionModal').modal('show');
                        $link.html(originalHtml).css('opacity', '1');
                    } else {
                        $link.html(originalHtml).css('opacity', '1');
                        toastr.error(res.error || 'Failed to load');
                    }
                }).fail(function() {
                    $link.html(originalHtml).css('opacity', '1');
                    toastr.error('Failed to load transaction details');
                });
            });

            // Bulk confirm using selected checkboxes
            $('#btn-bulk-confirm').on('click', function(e) {
                e.preventDefault();
                var ids = [];
                $('.select-row:checked:not(:disabled)').each(function() {
                    ids.push($(this).val());
                });
                if (!ids.length) return toastr.warning('Please select transactions to confirm');

                var $btn = $(this);
                var $icon = $btn.find('.material-symbols-outlined');
                var $spinner = $btn.find('.spinner-border');

                // Show spinner, hide icon
                $icon.hide();
                $spinner.removeClass('d-none');
                $btn.prop('disabled', true);

                $.post(ROUTES.bulkConfirm, {
                        ids: ids,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    })
                    .done(function(res) {
                        if (res && res.success) {
                            toastr.success(res.message || 'Transactions confirmed');
                            dt.ajax.reload(null, false);
                            $('#select-all').prop('checked', false);
                        } else {
                            toastr.error(res.error || 'Failed to confirm transactions');
                        }
                    }).fail(function() {
                        toastr.error('Failed to confirm transactions');
                    }).always(function() {
                        // Hide spinner, show icon
                        $spinner.addClass('d-none');
                        $icon.show();
                        $btn.prop('disabled', false);
                    });
            });
        });

        // Refresh button functionality
        $(document).on('click', '#refresh-transactions', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined');
            var $spinner = $btn.find('.spinner-border');

            // Show spinner, hide icon
            $icon.hide();
            $spinner.removeClass('d-none');
            $btn.prop('disabled', true);

            // Reload the DataTable
            dt.ajax.reload(function() {
                // Hide spinner, show icon
                $spinner.addClass('d-none');
                $icon.show();
                $btn.prop('disabled', false);

                toastr.success('Transaction list refreshed');
            }, false);
        });
    })();
</script>
@endsection