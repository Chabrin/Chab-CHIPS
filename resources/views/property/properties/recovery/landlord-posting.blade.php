@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Post Recoveries to Landlord Account</h5>
                        <div class="ms-auto d-flex gap-2">
                            <button id="btn-bulk-post" class="btn btn-primary btn-sm d-none align-items-center">
                                <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">account_balance</span>
                                <span>Post Selected to Landlord Account</span>
                            </button>
                            <a href="{{ route('property.recoveries.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Recoveries</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
                            <span class="material-symbols-outlined">info</span>
                            <div>
                                <strong>What this screen is for:</strong> a recovery being created and approved does not,
                                by itself, change what a landlord is paid — it stays a record only until someone
                                deliberately posts it here. This screen lists <strong>Approved</strong> recoveries that
                                have not yet been posted to a landlord's account.
                                <br><br>
                                <strong>What the bulk action does:</strong> selecting one or more recoveries and
                                clicking "Post Selected to Landlord Account" records a debit against each recovery's
                                landlord, for that recovery's amount. This is the only thing that ever moves money —
                                nothing happens automatically when a recovery is created or approved. Once posted,
                                that debit is included the normal way the next time that landlord's return is
                                generated, exactly like any other landlord account charge.
                                <br><br>
                                <strong>This cannot be undone from this screen</strong>, and each recovery can only be
                                posted once — a recovery disappears from this list as soon as it's been posted.
                            </div>
                        </div>
                        <div id="selection-summary" class="mb-2 d-none">
                            <span class="badge bg-secondary me-1">Selected: <span id="selected-total">0</span></span>
                            <span class="badge badge-light-primary">Total Amount: KES <span id="selected-amount">0.00</span></span>
                        </div>
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="landlord-posting-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="select-all"></th>
                                        <th>Ref Number</th>
                                        <th>Recovery Date</th>
                                        <th>Landlord</th>
                                        <th>Property</th>
                                        <th>Narration</th>
                                        <th class="text-end">Amount</th>
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

<!-- Confirm Modal -->
<div class="modal fade" id="postConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Posting to Landlord Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="postConfirmModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="postConfirmModalConfirmBtn">Confirm & Post</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    let ROUTES = {
        list: '{{ route("property.recoveries.landlord-posting.data") }}',
        post: '{{ route("property.recoveries.landlord-posting.post") }}',
    };

    (function() {
        let dt;
        let selected = {}; // id (string) -> {amount, ref_number}

        $(document).ready(function() {
            dt = $('#landlord-posting-table').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: ROUTES.list,
                    dataSrc: 'data'
                },
                columns: [
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return `<input type="checkbox" class="select-row" value="${row.id}">`;
                        }
                    },
                    { data: 'ref_number' },
                    { data: 'recovery_date' },
                    { data: 'client_name' },
                    { data: 'property_name' },
                    { data: 'narration' },
                    {
                        data: 'amount_formatted',
                        className: 'text-end'
                    }
                ],
                order: [[2, 'desc']],
                drawCallback: function() {
                    if (!dt) {
                        return;
                    }
                    dt.rows({ page: 'current' }).every(function() {
                        var rowData = this.data();
                        $(this.node()).find('.select-row').prop('checked', selected.hasOwnProperty(String(rowData.id)));
                    });
                    updateSelectAllState();
                    toggleBulkButton();
                    updateSelectionSummary();
                }
            });

            $('#select-all').on('change', function() {
                var isChecked = $(this).is(':checked');
                if (isChecked) {
                    dt.rows({ search: 'applied' }).every(function() {
                        var rowData = this.data();
                        selected[String(rowData.id)] = { amount: rowData.amount, ref_number: rowData.ref_number };
                    });
                } else {
                    selected = {};
                }
                dt.draw(false);
            });

            $('#landlord-posting-table tbody').on('change', '.select-row', function() {
                var rowData = dt.row($(this).closest('tr')).data();
                var id = String(rowData.id);
                if ($(this).is(':checked')) {
                    selected[id] = { amount: rowData.amount, ref_number: rowData.ref_number };
                } else {
                    delete selected[id];
                }
                updateSelectAllState();
                toggleBulkButton();
                updateSelectionSummary();
            });

            function updateSelectAllState() {
                var pageData = dt.rows({ page: 'current' }).data();
                var total = pageData.count();
                var checkedCount = 0;
                pageData.each(function(rowData) {
                    if (selected.hasOwnProperty(String(rowData.id))) checkedCount++;
                });
                $('#select-all').prop('checked', total > 0 && checkedCount === total);
            }

            function toggleBulkButton() {
                var anyChecked = Object.keys(selected).length > 0;
                $('#btn-bulk-post').toggleClass('d-none', !anyChecked).toggleClass('d-flex', anyChecked);
            }

            function updateSelectionSummary() {
                var ids = Object.keys(selected);
                var total = ids.length;
                var totalAmount = 0;
                ids.forEach(function(id) {
                    totalAmount += parseFloat(selected[id].amount) || 0;
                });
                $('#selected-total').text(total);
                $('#selected-amount').text(totalAmount.toFixed(2));
                $('#selection-summary').toggleClass('d-none', total === 0);
            }

            $('#btn-bulk-post').on('click', function() {
                var ids = Object.keys(selected);
                if (!ids.length) {
                    toastr.warning('Please select at least one recovery.');
                    return;
                }

                var totalAmount = 0;
                var listHtml = '<ul class="mb-0">';
                ids.forEach(function(id) {
                    totalAmount += parseFloat(selected[id].amount) || 0;
                    listHtml += '<li>' + selected[id].ref_number + ' — KES ' + parseFloat(selected[id].amount).toFixed(2) + '</li>';
                });
                listHtml += '</ul>';

                $('#postConfirmModalBody').html(
                    '<p>You are about to post <strong>' + ids.length + '</strong> recover' + (ids.length === 1 ? 'y' : 'ies') +
                    ' totalling <strong>KES ' + totalAmount.toFixed(2) + '</strong> as debits against the relevant landlords\' accounts. ' +
                    'This cannot be undone from this screen.</p>' + listHtml
                );
                $('#postConfirmModal').modal('show');
            });

            $('#postConfirmModalConfirmBtn').on('click', function() {
                var ids = Object.keys(selected);
                if (!ids.length) return;

                var $btn = $(this);
                $btn.prop('disabled', true);

                $.post(ROUTES.post, {
                    ids: ids,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }).done(function(res) {
                    if (res && res.success) {
                        toastr.success(res.message);
                        $('#postConfirmModal').modal('hide');
                        selected = {};
                        dt.ajax.reload(null, false);
                    } else {
                        toastr.error(res.message || 'Failed to post recoveries.');
                    }
                }).fail(function() {
                    toastr.error('Failed to post recoveries.');
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            });
        });
    })();
</script>
@endsection
