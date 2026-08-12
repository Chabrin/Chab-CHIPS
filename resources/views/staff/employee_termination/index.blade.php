@extends('layouts.main')
@section('content')

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Employee Terminations</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_employee_terminations')
                                <a class="btn btn-primary btn-sm d-flex align-items-center" href="{{ route('employee.termination.create') }}">
                                    <span class="material-symbols-outlined">add_circle</span>
                                    <span class="ms-1">Add Termination</span>
                                </a>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="employee_termination_table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Staff</th>
                                        <th>Termination Reason</th>
                                        <th>Element</th>
                                        <th>Amount</th>
                                        <th>Created By</th>
                                        <th>Current Status</th>
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
        let table = document.querySelector('#employee_termination_table');
        const terminationReasons = {
            1: 'Retirement',
            2: 'Resignation',
            3: 'Termination'
        };
        $(table).DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('ajax.employee.termination.list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'records'
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
                {
                    data: 'staff',
                    title: 'Staff',
                    render: function(data, type, row) {
                        if (row.can_read && data && data.name && row.staff && row.staff.staff_number) {
                            return `<a href="/hr/employees/termination/${row.encrypted_termination_id}" target="_blank">${data.name}</a>`;
                        }
                        return data && data.name ? data.name : '-';
                    }
                },
                {
                    data: 'termination_reason_id',
                    title: 'Termination Reason',
                    render: function(data, type, row) {
                        return terminationReasons[data] || '-';
                    }
                },
                
                {
                    data: 'elements',
                    title: 'Element',
                    render: function(data, type, row) {
                        if (Array.isArray(data) && data.length) {
                            return data.map(el => el.element_name).join('<br>');
                        }
                        return '-';
                    }
                },
                {
                    data: 'elements',
                    title: 'Amount',
                    render: function(data, type, row) {
                        if (Array.isArray(data) && data.length) {
                            return data.map(el => `KES ${el.amount}`).join('<br>');
                        }
                        return '-';
                    }
                },
                { data: 'created_by', title: 'Created By', render: data => data && data.name ? data.name : '-' },
                { 
                    data: 'current_status_id', 
                    title: 'Current Status',
                    render: function(data) {
                        if (data == 1) {
                            return '<span class="badge bg-warning">Pending</span>';
                        } else if (data == 5) {
                            return '<span class="badge bg-success">Confirmed</span>';
                        }
                        return '-';
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        // View button
                        if (row.can_read && row.staff && row.staff.id) {
                            buttons += `
                                <a class="staff-link" href="/hr/employees/termination/${row.encrypted_termination_id}" title="View">
                                    <span class="material-symbols-outlined view-icon" style="font-size:18px;">visibility</span>
                                </a>
                                <div class="spinner-border spinner-border-sm text-primary row-spinner" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            `;
                        }
                        if (row.can_update && row.staff && row.staff.id) {
                            // Edit button
                            buttons += `
                                <a href="/hr/employee/termination/edit/${id}" title="Edit">
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