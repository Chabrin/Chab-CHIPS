@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Approval History</h4>
                    </div>
                    <div class="card-body">
                        @include('components.error_reports')

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="approvalLogsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Final</th>
                                        <th>Initiator</th>
                                        <th>My action</th>
                                        <th>Action Date</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="8" class="text-center">No data available in table</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script src="{{ asset('assets/js/jquery.min.js') }}"></script>

<script>
    $(function() {
        // Initialize DataTable
        var table = $('#approvalLogsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '/approval/logs/list',
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                error: function(xhr, error, code) {
                    console.error('DataTable AJAX error:', error);
                    toastr.error('Failed to load approval history');
                }
            },
            columns: [{
                    data: 'id'
                },
                {
                    data: 'item_type'
                },
                {
                    data: 'status',
                    render: function(data) {
                        if (data === 'Approved') {
                            return '<span class="badge badge-light-success">Approved</span>';
                        } else if (data === 'Rejected') {
                            return '<span class="badge badge-light-danger">Rejected</span>';
                        } else if (data === 'Pending') {
                            return '<span class="badge badge-light-warning">Pending</span>';
                        }
                        return '<span class="badge badge-light-secondary">' + data + '</span>';
                    }
                },
                {
                    data: 'requested_by'
                },
                {
                    data: 'action_type',
                    render: function(data) {
                        if (data === 'approve') {
                            return '<span class="badge badge-light-success">Approved</span>';
                        } else if (data === 'reject') {
                            return '<span class="badge badge-light-danger">Rejected</span>';
                        }
                        return '<span class="badge badge-light-secondary">Pending</span>';
                    }
                },
                {
                    data: 'action_date',
                    render: function(data) {
                        if (data) {
                            return new Date(data).toLocaleString();
                        }
                        return '-';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return `<a href='/approval/logs/details/${row.encrypted_id}' class='btn btn-link'>View</a>`;
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            order: [
                [6, 'desc']
            ]
        });
    });
</script>


@endsection