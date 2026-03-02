@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Approval Requests</h4>
                    </div>
                    <div class="card-body">
                        @include('components.error_reports')

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="approvalRequestsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Final</th>
                                        <th>Initiator</th>
                                        <th>Initiated On</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="6" class="text-center">No data available in table</td>
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
        var table = $('#approvalRequestsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '/approval/requests/list',
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                error: function(xhr, error, code) {
                    console.error('DataTable AJAX error:', error);
                    toastr.error('Failed to load approval requests');
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
                    render: function(data, type, row) {
                        var status = (data || '').toString().toLowerCase();
                        if (status === 'approved') {
                            return "<span class='badge badge-light-success'>Approved</span>";
                        } else if (status === 'rejected') {
                            return "<span class='badge badge-light-danger'>Rejected</span>";
                        } else {
                            return "<span class='badge badge-light-danger'>Pending</span>";
                        }
                    }
                },
                {
                    data: 'requested_by',
                    render: function(data) {
                        return data;
                    }
                },
                {
                    data: 'requested_on',
                    render: function(data) {
                        return data;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return `<a href='/approval/requests/details/${row.encrypted_id}' class='btn btn-link'>Details</a>`;
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
                [0, 'desc']
            ]
        });

        // Handle approve/reject actions
        $(document).on('click', '.approve-btn, .reject-btn', function() {
            var id = $(this).data('id');
            var type = $(this).hasClass('approve-btn') ? 'approve' : 'reject';
            var remarks = prompt('Add remarks (optional)');
            $.post(`/approval/requests/${id}/action`, {
                action_type: type,
                remarks: remarks,
                _token: $('meta[name="csrf-token"]').attr('content')
            }, function(resp) {
                if (resp.success) {
                    toastr.success('Action recorded');
                    table.ajax.reload(null, false);
                } else {
                    toastr.error(resp.error || 'Failed to record action');
                }
            }, 'json');
        });
    });
</script>


@endsection