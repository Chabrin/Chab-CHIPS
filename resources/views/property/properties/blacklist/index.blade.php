@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Blacklisted Tenants</h4>
                        @permission('create_tenant_blacklist')
                        <div class="ms-auto">
                            <a href="{{ route('property.blacklist.create') }}" class="btn btn-success btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add</span>
                                <span class="ms-1">Add</span>
                            </a>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="blacklistTable">
                                <thead>
                                    <tr>
                                        <th>Blacklist Date</th>
                                        <th>Client</th>
                                        <th>Tenant</th>
                                        <th>Property</th>
                                        <th>Unit</th>
                                        <th>Reason</th>
                                        <th>Current Balance</th>
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
        $('#blacklistTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('property.blacklist.data') }}",
                dataSrc: 'data'
            },
            columns: [
                {
                    data: 'blacklist_date',
                    render: function(data) {
                        return data ? moment(data).format('DD-MM-YYYY') : 'N/A';
                    }
                },
                { 
                    data: 'client_name',
                    render: function(data, type, row) {
                        if (row.can_read_clients && row.encrypted_client_id) {
                            return `<a href="/property/clients/${row.encrypted_client_id}" class="text-primary">${data}</a>`;
                        }
                        return data;
                    }
                },
                { 
                    data: 'tenant_name',
                    render: function(data, type, row) {
                        if (row.can_read_tenants && row.encrypted_tenant_id) {
                            return `<a href="/tenants/${row.encrypted_tenant_id}" class="text-primary">${data}</a>`;
                        }
                        return data;
                    }
                },
                
                { data: 'property_name' },
                { data: 'unit_code' },
                { 
                    data: 'reason',
                    render: function(data) {
                        return `<span class="badge bg-danger">${data}</span>`;
                    }
                },
                {
                    data: 'current_balance',
                    render: function(data) {
                        return '<span class="text-danger fw-bold">' + parseFloat(data).toFixed(2) + '</span>';
                    }
                },
                {
                    data: 'current_status_id',
                    render: function(data) {
                        if (data == 17) {
                            return '<span class="badge bg-success">Processed</span>';
                        } else if (data == 1) {
                            return '<span class="badge bg-warning">Pending</span>';
                        }
                        return '<span class="badge bg-secondary">Unknown</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        let actions = '<div class="btn-group btn-group-sm" role="group">';
                        
                        if (row.can_view_details) {
                            actions += `<a href="/property/blacklist/${row.encrypted_id}" title="View Details">
                                <span class="material-symbols-outlined">visibility</span>
                            </a>`;
                        }
                        
                        if (row.can_edit_blacklist && row.current_status_id == 17) {
                            actions += `<a href="/property/blacklist/${row.encrypted_id}/edit" title="Edit">
                                <span class="material-symbols-outlined">edit</span>
                            </a>`;
                        }
                        
                        actions += '</div>';
                        return actions;
                    }
                }
            ],
            order: [[0, 'desc']],
            pageLength: 15,
            language: {
                emptyTable: "No blacklisted tenants found"
            }
        });
    });
</script>
@endsection
