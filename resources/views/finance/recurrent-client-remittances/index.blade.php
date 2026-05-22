@extends('layouts.main')

@section('content')
@php
$canCreate = auth()->user()->isAbleTo('create_recurrent_client_remittances');
@endphp

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Recurrent Clients Remittances Settings</h4>
                        @if($canCreate)
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('finance.recurrent-client-remittances.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add New</span>
                            </a>
                        </div>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="remittancesTable" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Client</th>
                                        <th>Property</th>
                                        <th>SLA</th>
                                        <th>Type</th>
                                        <th>Start Date</th>
                                        <th>Every (Days)</th>
                                        <th class="text-end">Amount</th>
                                        <th>Narration</th>
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
    var table = $('#remittancesTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('ajax.finance.recurrent-client-remittances.data') }}",
            dataSrc: 'data'
        },
        columns: [
            { data: null, orderable: false, render: function(data, type, row, meta) { return meta.row + 1; } },
            { data: 'client', defaultContent: '-' },
            { data: 'property', defaultContent: '-' },
            { data: 'sla', defaultContent: '-' },
            {
                data: 'type',
                render: function(data) {
                    if (!data) return '-';
                    var cls = data === 'Clients Bills' ? 'bg-primary' : 'bg-info';
                    return '<span class="badge ' + cls + '">' + data + '</span>';
                }
            },
            { data: 'start_date', defaultContent: '-' },
            { data: 'every_date', defaultContent: '-' },
            {
                data: 'amount',
                className: 'text-end',
                render: function(data) { return data !== null ? parseFloat(data).toFixed(2) : '-'; }
            },
            { data: 'narration', defaultContent: '-' },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    let buttons = '<div class="d-flex flex-row gap-2">';
                    if (row.can_edit && row.encrypted_id) {
                        buttons += `
                            <a href="/finance/recurrent-client-remittances/${row.encrypted_id}/edit" title="Edit">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                            </a>
                        `;
                    }
                    buttons += `
                        <a href="#" class="text-danger delete-remittance" data-id="${row.encrypted_id}" title="Delete">
                            <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                        </a>
                    `;
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

    $(document).on('click', '.delete-remittance', function(e) {
        e.preventDefault();
        var encryptedId = $(this).data('id');
        if (confirm('Are you sure you want to delete this remittance?')) {
            $.ajax({
                url: '/finance/recurrent-client-remittances/' + encryptedId,
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message || 'Deleted successfully');
                        table.ajax.reload();
                    } else {
                        toastr.error(res.error || 'Failed to delete');
                    }
                },
                error: function() {
                    toastr.error('Error deleting remittance');
                }
            });
        }
    });
});
</script>
@endsection