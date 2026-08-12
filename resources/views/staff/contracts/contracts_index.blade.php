@extends('layouts.main')
@section('content')

@php
$canReadStaff = auth()->user()->hasPermission('read_contracts');
$canUpdateStaff = auth()->user()->hasPermission('update_contracts');
@endphp

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Contracts</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('update_contracts')
                                <a class="btn btn-secondary btn-sm d-flex align-items-center" href="{{ route('contract.bulk.edit') }}">
                                    <span class="material-symbols-outlined" style="font-size:18px;">table_edit</span>
                                    <span class="ms-1">Bulk Edit</span>
                                </a>
                            @endpermission
                            @permission('create_contracts')
                                <a class="btn btn-primary btn-sm d-flex align-items-center" href="{{ route('contract.create') }}">
                                    <span class="material-symbols-outlined">add_circle</span>
                                    <span class="ms-1">Add Contracts</span>
                                </a>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="contracts_table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date/Time</th>
                                        <th>Staff</th>
                                        <th>Position</th>
                                        <th>Date Start</th>
                                        <th>Date End</th>
                                        <th>Basic Salary</th>
                                        <th>Created By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data loaded via DataTables AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let table = document.querySelector('#contracts_table');
        
        $(table).DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/hr/contracts/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'contracts'
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
                { data: 'date_time', title: 'Date/Time', render: data => data ? new Date(data).toLocaleString() : '-' },
                { 
                    data: null, 
                    title: 'Staff', 
                    render: function(data, type, row) {
                        let staffNumber = row.staff_number ? row.staff_number : '-';
                        let staffName = row.employee && row.employee.name ? row.employee.name : '-';
                        return `${staffNumber} - ${staffName}`;
                    }
                },
                { data: 'position', title: 'Position', render: data => data && data.position_name ? data.position_name : '-' },
                { data: 'date_start', title: 'Date Start', render: data => data ? data : '-' },
                { data: 'date_end', title: 'Date End', render: data => data ? data : '-' },
                { data: 'basic_salary', title: 'Basic Salary', render: data => data !== undefined ? `KES ${data}` : '-' },
                { data: 'created_by', title: 'Created By', render: data => data && data.name ? data.name : '-' },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        // View button
                        if (row.can_read && row.employee && row.employee.encrypted_id) {
                            buttons += `
                                <a class="staff-link" href="/hr/employees/${row.employee.encrypted_id}" title="View">
                                    <span class="material-symbols-outlined view-icon" style="font-size:18px;">visibility</span>
                                </a>
                                <div class="spinner-border spinner-border-sm text-primary row-spinner" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            `;
                        }
                        // Edit button
                        if (row.can_update && row.employee && row.employee.encrypted_id) {
                            buttons += `
                                <a href="/hr/contracts/${row.employee.encrypted_id}/edit" title="Edit">
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
            lengthChange: true
        });
    });
</script>

@endsection

