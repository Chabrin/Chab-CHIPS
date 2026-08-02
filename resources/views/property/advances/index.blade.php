@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Client Advances</h5>
                        @permission('create_client_advances')
                        <a href="{{ route('client.advances.create') }}" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus"></i> New Requisition
                        </a>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs mb-3" id="advancesTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-pane" type="button" role="tab">
                                    All Advances
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="confirm-tab" data-bs-toggle="tab" data-bs-target="#confirm-pane" type="button" role="tab">
                                    Pending Confirmation <span class="badge bg-danger ms-1" id="pending-confirm-count" style="display:none;"></span>
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="all-pane" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table class="display" id="advances-table" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Date</th>
                                                <th>Reference</th>
                                                <th>Type</th>
                                                <th>Client</th>
                                                <th>Property</th>
                                                <th class="text-end">Amount</th>
                                                <th class="text-end">Balance</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="confirm-pane" role="tabpanel">
                                <div class="alert alert-info small mb-3">
                                    This tab shows advances that are still waiting for the landlord to approve the email that was sent to them.
                                    <ul class="mb-0 mt-2 ps-3">
                                        <li>
                                            <strong>If the landlord approves it themselves</strong> (by clicking the link in their email), everything
                                            is done automatically - the advance is approved and immediately applied to their account. No further action is needed from anyone.
                                        </li>
                                        <li class="mt-1">
                                            <strong>If the landlord hasn't responded</strong>, someone on the team can click "Confirm" here to approve
                                            it on the landlord's behalf. This only marks it as approved - the money is <u>not</u> applied to the
                                            landlord's account yet. A second person still needs to go to the "All Advances" tab and click "Post" to
                                            actually complete it. This extra step exists so that one person can't both confirm and finalize an advance
                                            on their own - a second, different person always has to sign off before the money moves. You also can't
                                            confirm an advance you created yourself, for the same reason.
                                        </li>
                                    </ul>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table class="display" id="confirm-table" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Date</th>
                                                <th>Reference</th>
                                                <th>Client</th>
                                                <th>Property</th>
                                                <th class="text-end">Amount</th>
                                                <th>Created By</th>
                                                <th>Actions</th>
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
    </div>
</div>
@endsection
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        function statusBadge(data) {
            if (data === 'Pending') {
                return '<span class="badge badge-light-danger">Pending</span>';
            } else if (data === 'Approved') {
                return '<span class="badge badge-light-success">Approved</span>';
            } else if (data === 'Posted' || data === 'Disbursed') {
                return '<span class="badge badge-light-success">Posted</span>';
            } else if (data === 'Cleared') {
                return '<span class="badge badge-light-success">Cleared</span>';
            } else if (data === 'Cancelled') {
                return '<span class="badge badge-light-danger">Cancelled</span>';
            } else if (data === 'Suspended') {
                return '<span class="badge badge-light-danger">Suspended</span>';
            } else if (data === 'Rescheduled') {
                return '<span class="badge badge-light-info">Rescheduled</span>';
            }
            return '<span class="badge bg-secondary">' + data + '</span>';
        }

        var table = $('#advances-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '{{ route("property.advances.data") }}',
                type: 'GET',
                dataSrc: 'data',
                error: function(xhr) {
                    console.error('Error loading advances:', xhr);
                }
            },
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'requisition_date'
                },
                {
                    data: 'reference_number'
                },
                {
                    data: 'advance_type',
                    render: function(data) {
                        if (data === 'Client Payment Advance') {
                            return '<span class="badge bg-primary">' + data + '</span>';
                        } else if (data === 'Overpayment Advance') {
                            return '<span class="badge bg-info">' + data + '</span>';
                        }
                        return '<span class="badge bg-secondary">' + data + '</span>';
                    }
                },
                {
                    data: 'client_name'
                },
                {
                    data: 'property_name'
                },
                {
                    data: 'amount',
                    className: 'text-end',
                    render: function(data, type, row) {
                        return data;
                    }
                },
                {
                    data: 'current_balance',
                    className: 'text-end',
                    render: function(data, type, row) {
                        return data;
                    }
                },
                {
                    data: 'status',
                    render: statusBadge
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        var actions = '<a href="{{ route("property.advances.show", "ID") }}" class="text-info me-2" title="View"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span></a>'.replace('ID', row.encrypted_id);

                        if (row.can_update && row.status_id !== 6) {
                            actions += '<a href="{{ route("property.advances.show", "ID") }}" class="text-primary me-2" title="Edit"><span class="material-symbols-outlined" style="font-size:18px;">edit_document</span></a>'.replace('ID', row.encrypted_id);
                        }

                        if (row.can_update && row.status_id === 6) {
                            actions += '<a href="{{ route("property.advances.reschedule", "ID") }}" class="text-warning me-2" title="Reschedule"><span class="material-symbols-outlined" style="font-size:18px;">event_repeat</span></a>'.replace('ID', row.encrypted_id);
                        }

                        if (row.can_post) {
                            actions += '<a href="javascript:void(0)" class="text-primary me-2 post-advance" data-id="' + row.encrypted_id + '" title="Post to landlord ledger"><span class="material-symbols-outlined" style="font-size:18px;">send</span></a>';
                        }

                        // if (row.can_delete) {
                        //     actions += '<a href="javascript:void(0)" class="text-danger delete-advance" data-id="' + row.encrypted_id + '" data-client="' + row.client_name + '" title="Delete"><span class="material-symbols-outlined" style="font-size:18px;">delete</span></a>';
                        // }

                        return actions;
                    }
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

        var confirmTable = $('#confirm-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '{{ route("property.advances.data") }}',
                type: 'GET',
                data: { status_id: 1 },
                dataSrc: function(response) {
                    var count = (response.data || []).length;
                    var badge = $('#pending-confirm-count');
                    if (count > 0) {
                        badge.text(count).show();
                    } else {
                        badge.hide();
                    }
                    return response.data;
                },
                error: function(xhr) {
                    console.error('Error loading pending advances:', xhr);
                }
            },
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { data: 'requisition_date' },
                { data: 'reference_number' },
                { data: 'client_name' },
                { data: 'property_name' },
                {
                    data: 'amount',
                    className: 'text-end'
                },
                { data: 'created_by_name' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        var actions = '<a href="{{ route("property.advances.show", "ID") }}" class="text-info me-2" title="View"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span></a>'.replace('ID', row.encrypted_id);

                        if (row.can_confirm) {
                            actions += '<button type="button" class="btn btn-success btn-sm confirm-advance" data-id="' + row.encrypted_id + '">Confirm</button>';
                        } else if (row.is_own_requisition) {
                            actions += '<span class="text-muted small">You created this - another user must confirm it</span>';
                        }

                        return actions;
                    }
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

        // Confirm advance (in lieu of the landlord)
        $('#confirm-table').on('click', '.confirm-advance', function() {
            var id = $(this).data('id');

            if (!confirm('Confirm this advance on behalf of the landlord? It will move to Approved and can then be posted.')) {
                return;
            }

            $.ajax({
                url: '{{ route("property.advances.confirm", "ID") }}'.replace('ID', id),
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    toastr.success(response.message || 'Advance confirmed');
                    confirmTable.ajax.reload();
                    table.ajax.reload();
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to confirm advance');
                }
            });
        });

        // Post advance to the landlord ledger
        $('#advances-table').on('click', '.post-advance', function() {
            var id = $(this).data('id');

            if (!confirm('Post this advance to the landlord ledger? This cannot be undone.')) {
                return;
            }

            $.ajax({
                url: '{{ route("property.advances.post", "ID") }}'.replace('ID', id),
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    toastr.success(response.message || 'Advance posted');
                    table.ajax.reload();
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to post advance');
                }
            });
        });

        // Delete Advance
        $('#advances-table').on('click', '.delete-advance', function() {
            var id = $(this).data('id');
            var client = $(this).data('client');

            if (confirm('Are you sure you want to delete this advance for "' + client + '"?')) {
                $.ajax({
                    url: '{{ route("property.advances.destroy", "ID") }}'.replace('ID', id),
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        table.ajax.reload();
                        toastr.success(response.message || 'Advance deleted successfully');
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Failed to delete advance');
                    }
                });
            }
        });
    });
</script>
