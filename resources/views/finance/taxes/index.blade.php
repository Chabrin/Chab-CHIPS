@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Finance Tax Setup</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('finance.taxes.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Create Tax</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="financeTaxesTable" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Default Type</th>
                                        <th>Computation</th>
                                        <th>Category</th>
                                        <th>Debit Account</th>
                                        <th>Credit Account</th>
                                        <th class="text-end">Amount</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Created At</th>
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

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(function () {
    var table = $('#financeTaxesTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('ajax.finance.taxes.data') }}",
            dataSrc: 'data'
        },
        columns: [
            { data: null, orderable: false, render: function(data, type, row, meta) { return meta.row + 1; } },
            { data: 'name' },
            { data: 'default_type', defaultContent: '-' },
            { data: 'computation', defaultContent: '-' },
            { data: 'category', defaultContent: '-' },
            { data: 'debit_account', defaultContent: '-' },
            { data: 'credit_account', defaultContent: '-' },
            { data: 'amount', className: 'text-end', render: function(data) { return data !== null ? parseFloat(data).toFixed(2) : '-'; } },
            { data: 'current_status_id', render: function(data) { return data === 5 ? 'Active' : (data === 1 ? 'Inactive' : data); } },
            { data: 'created_by', defaultContent: '-' },
            { data: 'created_at', defaultContent: '-' }
        ],
        paging: true,
        info: true,
        lengthChange: true,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
        order: [[0, 'asc']]
    });
});
</script>

@endsection
