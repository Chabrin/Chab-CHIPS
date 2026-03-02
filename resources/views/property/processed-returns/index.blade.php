@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Processed Returns</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table table-sm" id="returns-table" style="font-size: 0.72rem;">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Client</th>
                                        <th>Property</th>
                                        <th>Type</th>
                                        <th class="text-end">Gross</th>
                                        <th class="text-end">Comms</th>
                                        <th class="text-end">VAT</th>
                                        <th class="text-end">Rec</th>
                                        <th class="text-end">Net</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Loaded via Ajax -->
                                </tbody>
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
    $(document).ready(function() {
        let returnsTable;

        function loadReturns() {
            if ($.fn.DataTable.isDataTable('#returns-table')) {
                $('#returns-table').DataTable().clear().destroy();
            }

            returnsTable = $('#returns-table').DataTable({
                processing: true,
                ajax: {
                    url: '{{ route("ajax.processed.returns.data") }}',
                    dataSrc: 'data'
                },
                columns: [{
                        data: 'period'
                    },
                    {
                        data: 'client_name',
                        render: function(data, type, row) {
                            return `<a href="/processed/returns/${row.encrypted_id}/details" class="text-primary" style="cursor:pointer;">${data}</a>`;
                        }
                    },
                    {
                        data: 'property'
                    },
                    {
                        data: 'return_type'
                    },
                    {
                        data: 'gross_amount',
                        className: 'text-end'
                    },
                    {
                        data: 'commission',
                        className: 'text-end'
                    },
                    {
                        data: 'vat',
                        className: 'text-end'
                    },
                    {
                        data: 'recoveries',
                        className: 'text-end'
                    },
                    {
                        data: 'net_amount',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return '<strong>' + data + '</strong>';
                        }
                    },
                    {
                        data: 'status',
                        render: function(data, type, row) {
                            if (data === 'Pending') {
                                return '<span class="badge badge-light-info">Pending</span>';
                            } else if (data === 'Approved') {
                                return '<span class="badge badge-light-success">Approved</span>';
                            } else if (data === 'Processed') {
                                return '<span class="badge badge-light-success">OK</span>';
                            }
                            return '<span class="badge bg-secondary">' + data + '</span>';
                        }
                    }
                ],
                order: [
                    [0, 'desc']
                ],
                pageLength: 25,
                lengthMenu: [
                    [25, 50, 100, -1],
                    [25, 50, 100, "All"]
                ]
            });
        }

        loadReturns();
    });
</script>
@endsection