@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="mb-0">Confirm Interests</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="/dashboard" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back_ios_new</span>
                                <span class="ms-1">Back</span>
                            </a>
                            <button id="confirmAllBtn" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">check_circle</span>
                                <span class="ms-1">Confirm All</span>
                                <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                            </button>
                            <button id="cancelAllBtn" class="btn btn-secondary btn-sm d-flex align-items-center me-2">
                                <span class="material-symbols-outlined">cancel</span>
                                <span class="ms-1">Cancel All</span>
                                <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table id="basic-1" class="table table-sm table-condensed table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Account No</th>
                                        <th>Member No</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Tax Amount</th>
                                        <th class="text-end">Net Amount</th>
                                        <th>Transacted By</th>
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
@endsection

@section('scripts')
<script>
    $(function() {
        // Ensure CSRF for posts
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        var $tableBody = $('#basic-1 tbody');

        function formatCurrency(v, digits) {
            digits = digits || 2;
            if (v === null || typeof v === 'undefined' || v === '') return '';
            var n = Number(v);
            if (isNaN(n)) return '';
            return new Intl.NumberFormat(undefined, {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits
            }).format(n);
        }

        function renderConfirmRows(rows) {
            var dt = null;
            try {
                dt = $('#basic-1').DataTable ? $('#basic-1').DataTable() : null;
            } catch (e) {
                dt = null;
            }

            function rowToArray(r, idx) {
                return [
                    idx,
                    r.account_no || '',
                    r.member_no || '',
                    formatCurrency(r.amount, 2),
                    formatCurrency(r.tax_amt, 2),
                    formatCurrency(r.net_amount, 2),
                    r.transacted_by || ''
                ];
            }

            if (!rows || rows.length === 0) {
                if (dt) {
                    dt.clear().draw();
                } else {
                    $tableBody.html('<tr><td colspan="7">No records</td></tr>');
                }
                return;
            }

            if (dt) {
                dt.clear();
                var i = 0;
                rows.forEach(function(r) {
                    i++;
                    dt.row.add(rowToArray(r, i));
                });
                dt.draw();
            } else {
                $tableBody.empty();
                var i = 0;
                rows.forEach(function(r) {
                    i++;
                    var a = rowToArray(r, i);
                    $tableBody.append('<tr>' +
                        '<td>' + a[0] + '</td>' +
                        '<td>' + a[1] + '</td>' +
                        '<td>' + a[2] + '</td>' +
                        '<td class="text-end">' + a[3] + '</td>' +
                        '<td class="text-end">' + a[4] + '</td>' +
                        '<td class="text-end">' + a[5] + '</td>' +
                        '<td>' + a[6] + '</td>' +
                        '</tr>');
                });
            }
        }

        function loadConfirm() {
            $tableBody.empty();
            $tableBody.append('<tr><td colspan="7">Loading...</td></tr>');
            $.get('/confirm/interests/list')
                .done(function(resp) {
                    if (!resp) {
                        toastr.error('No response');
                        $tableBody.empty();
                        return;
                    }
                    if (resp.error) {
                        toastr.error(resp.error);
                        $tableBody.empty();
                        return;
                    }
                    var rows = Array.isArray(resp.data) ? resp.data : [];
                    renderConfirmRows(rows);
                })
                .fail(function() {
                    toastr.error('Failed to load records');
                    $tableBody.empty();
                });
        }

        $('#confirmAllBtn').on('click', function() {
            var $btn = $(this),
                $spinner = $btn.find('.spinner-border');
            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            $.post('/confirm/interests/bulk-confirm')
                .done(function(resp) {
                    if (resp && resp.success) toastr.success(resp.message || 'Confirmed');
                    else if (resp && resp.error) toastr.error(resp.error);
                    loadConfirm();
                })
                .fail(function(xhr) {
                    var msg = 'Failed to confirm';
                    try {
                        if (xhr && xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                    } catch (e) {}
                    toastr.error(msg);
                })
                .always(function() {
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                });
        });

        $('#cancelAllBtn').on('click', function() {
            var $btn = $(this),
                $spinner = $btn.find('.spinner-border');
            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            $.post('/confirm/interests/bulk-cancel')
                .done(function(resp) {
                    if (resp && resp.success) toastr.success(resp.message || 'Cancelled');
                    else if (resp && resp.error) toastr.error(resp.error);
                    loadConfirm();
                })
                .fail(function(xhr) {
                    var msg = 'Failed to cancel';
                    try {
                        if (xhr && xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                    } catch (e) {}
                    toastr.error(msg);
                })
                .always(function() {
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                });
        });

        // initial load
        loadConfirm();
    });
</script>
@endsection