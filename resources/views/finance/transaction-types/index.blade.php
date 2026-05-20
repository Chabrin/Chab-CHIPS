@extends('layouts.main')

@section('content')
@php
$canCreateTransactionType = auth()->user()->hasPermission('create_transaction_types');
@endphp

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Transaction Types</h4>
                        @if($canCreateTransactionType)
                        <div class="ms-auto">
                            <a href="{{ route('finance.transaction-types.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add</span>
                                <span class="ms-1">Add New Type</span>
                            </a>
                        </div>
                        @endif
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="transaction-type-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Item Code</th>
                                        <th>Type Name</th>
                                        <th>Category</th>
                                        <th>Debit Account</th>
                                        <th>Credit Account</th>
                                        <th>Amount</th>
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

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(function () {
    var table = $('#transaction-type-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('ajax.finance.transaction-types.data') }}",
            dataSrc: 'data'
        },
        columns: [
            { data: null, orderable: false, render: function(data, type, row, meta) { return meta.row + 1; } },
            { data: 'item_code' },
            { data: 'name' },
            { data: 'category', defaultContent: '-' },
            { data: 'debit_account', defaultContent: '-' },
            { data: 'credit_account', defaultContent: '-' },
            { data: 'amount', className: 'text-end', render: function(data) { return data !== null ? parseFloat(data).toFixed(2) : '-'; } },
            { data: 'current_status_id', render: function(data) { return data === 5 ? 'Active' : (data === 1 ? 'Inactive' : data); } },
            {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        // Edit button
                        if (row.can_edit && row.encrypted_id) {
                            buttons += `
                                <a href="/finance/transaction-types/${row.encrypted_id}/edit" title="Edit">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </a>
                            `;
                        }
                        buttons += '</div>';
                        return buttons;
                    }
                }
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
