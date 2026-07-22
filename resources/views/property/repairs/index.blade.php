@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Repairs & Maintenance Requests</h5>
                        @permission('create_repair_requests')
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('property.repairs.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">New Repair Request</span>
                            </a>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table id="repairRequestsTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Ref Number</th>
                                        <th>Client</th>
                                        <th>Property</th>
                                        <th>Visited Date</th>
                                        <th>Created By</th>
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
$(document).ready(function() {
    var table = $('#repairRequestsTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('property.repairs.data') }}",
            type: 'GET',
            dataSrc: 'data'
        },
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row, meta) {
                    return meta.row + 1;
                }
            },
            { data: 'ref_number' },
            { 
                data: 'client_name',
                render: function(data, type, row) {
                    if (row.can_view_client && row.encrypted_client_id) {
                        return `<a href="/property/clients/${row.encrypted_client_id}" class="text-primary">${data}</a>`;
                    }
                    return data;
                }
            },
            { 
                data: 'property_name',
                render: function(data, type, row) {
                    if (row.can_view_property && row.encrypted_property_id) {
                        return `<a href="/property/listings/${row.encrypted_property_id}" class="text-primary">${data}</a>`;
                    }
                    return data;
                }
            },
            { 
                data: 'visited_date_time',
                render: function(data) {
                    if (!data) return '-';
                    // Replace space with 'T' for proper ISO format
                    const date = new Date(data.replace(' ', 'T'));
                    if (isNaN(date.getTime())) return data; // Return original if invalid
                    const dateOptions = { year: 'numeric', month: 'short', day: '2-digit' };
                    const timeOptions = { hour: '2-digit', minute: '2-digit', hour12: true };
                    const formattedDate = date.toLocaleDateString('en-GB', dateOptions);
                    const formattedTime = date.toLocaleTimeString('en-GB', timeOptions);
                    return formattedDate + ' ' + formattedTime;
                }
            },
            { data: 'created_by_name' },
            { 
                data: 'current_status_id',
                render: function(data) {
                    if (data == 1) {
                        return '<span class="badge bg-warning">Pending</span>';
                    } else if (data == 5) {
                        return '<span class="badge bg-success">Approved</span>';
                    } else if (data == 41) {
                        return '<span class="badge bg-danger">Rejected</span>';
                    }
                    return '<span class="badge bg-secondary">Unknown</span>';
                }
            },
            {
                data: 'encrypted_id',
                orderable: false,
                searchable: false,
                render: function(encryptedId, type, row) {
                    let actions = '';
                    
                    if (row.can_view) {
                        actions += `
                            <a href="/property/repairs/${encryptedId}" class="text-primary" title="View Details">
                                <span class="material-symbols-outlined" style="font-size: 20px;">visibility</span>
                            </a>
                        `;
                    }
                    
                    if (row.can_edit) {
                        actions += `
                            <a href="/property/repairs/${encryptedId}/edit" class="text-success" title="Edit">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                            </a>
                        `;
                    }
                    
                    return actions || '-';
                }
            }
        ],
        pageLength: 10,
        responsive: true,
        order: [[5, 'desc']]
    });
});
</script>
@endsection
