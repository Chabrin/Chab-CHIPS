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
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        var table = $('#advances-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '{{ route("property.advances.data") }}',
                type: 'GET',
                data: function(d) {
                    // No extra filters, just the datatable search
                },
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
                        if (data === 'Expense Advance') {
                            return '<span class="badge bg-warning">' + data + '</span>';
                        } else if (data === 'Client Payment Advance') {
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
                    render: function(data, type, row) {
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
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        var actions = '<a href="{{ route("property.advances.show", "ID") }}" class="text-info me-2" title="View"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span></a>'.replace('ID', row.encrypted_id);

                        if (row.can_update) {
                            actions += '<a href="{{ route("property.advances.edit", "ID") }}" class="text-success me-2" title="Edit"><span class="material-symbols-outlined" style="font-size:18px;">edit_document</span></a>'.replace('ID', row.encrypted_id);
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