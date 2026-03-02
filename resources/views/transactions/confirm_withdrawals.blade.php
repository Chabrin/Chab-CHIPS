@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;" id="withdrawals-confirm-page">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="mb-0">Confirm Withdrawals</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" id="refresh-withdrawals">
                                <span class="spinner-border spinner-border-sm d-none me-1" id="refresh-spinner" role="status" aria-hidden="true"></span>
                                <span class="material-symbols-outlined me-1">refresh</span>
                                <span class="ms-1">Refresh</span>
                            </button>
                            @permission('update_withdrawals')
                            <button id="btn-bulk-confirm" class="btn btn-success btn-sm d-flex align-items-center">
                                <span class="spinner-border spinner-border-sm d-none me-1" id="bulk-confirm-spinner" role="status" aria-hidden="true"></span>
                                <span class="material-symbols-outlined me-1">verified</span>
                                <span class="ms-1">Bulk Confirm</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar position-relative">
                            <div id="withdrawals-loading" class="table-loading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#fff;z-index:5;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                                    <div>Loading Withdrawals...</div>
                                </div>
                            </div>
                            <table class="display" id="withdrawals-dt">
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
@endsection
@section('scripts')
<script>
    let ROUTES = {
        list: '{{ route("withdrawals.confirm.list") }}',
        show: '{{ url("/withdrawals") }}',
        confirm: '{{ url("/withdrawals") }}',
        bulkConfirm: '{{ route("withdrawals.bulk.confirm") }}',
        reviewView: '{{ route("withdrawals.confirm.review") }}'
    };

    (function() {
        let dt;

        // Initialize DataTable
        $(document).ready(function() {
            dt = $('#withdrawals-dt').DataTable({
                ajax: {
                    url: ROUTES.list,
                    type: 'GET',
                    dataSrc: 'transactions'
                },
                processing: true,
                serverSide: false,
                pageLength: 25,
                order: [
                    [1, 'desc']
                ],
                columnDefs: [{
                    targets: [0, 7],
                    orderable: false,
                    searchable: false
                }],
                columns: [{
                        data: null,
                        render: function(data, type, row) {
                            return `<input type="checkbox" class="select-row" value="${row.id}">`;
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
                            var wholePart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                            return parts.length > 1 ? wholePart + '.' + parts[1] : wholePart;
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return `
                                <div class="d-flex gap-1">
                                    <a href="#" class="withdrawal-view-link text-decoration-none" 
                                       style="color: #3598db; font-weight: bold;" 
                                       data-id="${row.id}">View</a>
                                    <span style="color: #dee2e6;">|</span>
                                    <a href="${ROUTES.reviewView}?id=${row.id}" 
                                       style="color: #3598db; font-weight: bold; text-decoration: none;">Confirm</a>
                                </div>
                            `;
                        }
                    }
                ],
                initComplete: function() {
                    $('#withdrawals-loading').hide();
                }
            });

            // Select all checkbox functionality
            $('#select-all').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('.select-row:not(:disabled)').prop('checked', isChecked);
            });

            // Individual checkbox change
            $(document).on('change', '.select-row', function() {
                var totalCheckboxes = $('.select-row:not(:disabled)').length;
                var checkedCheckboxes = $('.select-row:checked:not(:disabled)').length;
                $('#select-all').prop('checked', totalCheckboxes === checkedCheckboxes);
            });

            // View withdrawal
            $(document).on('click', '.withdrawal-view-link', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var $link = $(this);
                var originalHtml = $link.html();

                $link.html('<span class="spinner-border spinner-border-sm" role="status"></span>').css('opacity', '0.6');

                $.get(ROUTES.show + '/' + id).done(function(res) {
                    if (res && res.transaction) {
                        var t = res.transaction;
                        var modalContent = `
                            <div class="modal fade" id="withdrawalModal" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Withdrawal Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-6"><strong>Member:</strong> ${t.member_name || 'N/A'}</div>
                                                <div class="col-md-6"><strong>Member No:</strong> ${t.member_no || 'N/A'}</div>
                                                <div class="col-md-6"><strong>Account:</strong> ${t.account_no || 'N/A'}</div>
                                                <div class="col-md-6"><strong>Fund:</strong> ${t.fund_name || 'N/A'}</div>
                                                <div class="col-md-6"><strong>Date:</strong> ${t.trans_date || 'N/A'}</div>
                                                <div class="col-md-6"><strong>Amount:</strong> ${t.amount || 'N/A'}</div>
                                                <div class="col-md-6"><strong>Units:</strong> ${t.employee_units || 'N/A'}</div>
                                                <div class="col-md-6"><strong>Price:</strong> ${t.price || 'N/A'}</div>
                                                <div class="col-md-6"><strong>MOP:</strong> ${t.mop || 'N/A'}</div>
                                                <div class="col-md-6"><strong>By:</strong> ${t.transacted_by || 'N/A'}</div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        $('body').append(modalContent);
                        $('#withdrawalModal').modal('show').on('hidden.bs.modal', function() {
                            $(this).remove();
                        });
                    }
                    $link.html(originalHtml).css('opacity', '1');
                }).fail(function() {
                    $link.html(originalHtml).css('opacity', '1');
                    toastr.error('Failed to load withdrawal details');
                });
            });

            // Bulk confirm using selected checkboxes
            $('#btn-bulk-confirm').on('click', function(e) {
                e.preventDefault();
                var ids = [];
                $('.select-row:checked:not(:disabled)').each(function() {
                    ids.push($(this).val());
                });
                if (!ids.length) return toastr.warning('Please select withdrawals to confirm');

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
                            toastr.success(res.message || 'Withdrawals confirmation initiated');
                            dt.ajax.reload(null, false);
                            $('#select-all').prop('checked', false);
                        } else {
                            toastr.error(res.error || 'Failed to confirm withdrawals');
                        }
                    }).fail(function() {
                        toastr.error('Failed to confirm withdrawals');
                    }).always(function() {
                        // Hide spinner, show icon
                        $spinner.addClass('d-none');
                        $icon.show();
                        $btn.prop('disabled', false);
                    });
            });
        });

        // Refresh button functionality
        $(document).on('click', '#refresh-withdrawals', function() {
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

                toastr.success('Withdrawal list refreshed');
            }, false);
        });

    })();
</script>
@endsection