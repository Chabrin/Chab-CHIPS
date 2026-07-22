@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Tenant Movements</h5>
                        @permission('create_tenant_movements')
                        <div class="ms-auto">
                            <a href="{{ route('property.movements.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">New Movement</span>
                            </a>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <!-- Loading Indicator -->
                        <div id="movementsLoader" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Loading tenant movements...</p>
                        </div>

                        <!-- Data Table Container -->
                        <div id="movementsTableContainer" style="display: none;">
                            <table id="movementsTable" class="table table-bordered table-striped" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Date</th>
                                        <th>Tenant</th>
                                        <th>Property</th>
                                        <th>From → To</th>
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
        // Initialize DataTable
        var movementsTable = $('#movementsTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('property.movements.data') }}",
                type: 'GET',
                dataSrc: 'data',
                beforeSend: function() {
                    $('#movementsLoader').show();
                    $('#movementsTableContainer').hide();
                },
                complete: function() {
                    $('#movementsLoader').hide();
                    $('#movementsTableContainer').show();
                },
                error: function() {
                    $('#movementsLoader').html('<p class="text-danger">Failed to load data. Please refresh the page.</p>');
                }
            },
            columns: [
                { data: 'reference_number' },
                {
                    data: 'date_time',
                    render: function(data) {
                        return data ? moment(data).format('DD-MM-YYYY HH:mm') : 'N/A';
                    }
                },
                { data: 'tenant_name' },
                { data: 'property_name' },
                {
                    data: null,
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary">${row.unit_from_code}</span>
                            <span class="material-symbols-outlined" style="font-size: 16px;">arrow_forward</span>
                            <span class="badge bg-primary">${row.unit_to_code}</span>
                        </div>`;
                    }
                },
                {
                    data: 'current_status_id',
                    render: function(data) {
                        if (data == 1) {
                            return '<span class="badge bg-warning">Pending</span>';
                        } else if (data == 5) {
                            return '<span class="badge bg-success">Confirmed</span>';
                        } else {
                            return '<span class="badge bg-danger">Denied</span>';
                        }
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        let actions = '<div class="d-flex gap-1">';

                        if (row.can_view_details) {
                        
                            actions += `<a href="/property/movements/${data}" title="View Details">
                                <span class="material-symbols-outlined" style="font-size: 16px;">visibility</span>
                            </a>`;
                        }
                        
                        // Only allow edit if status is pending (1) and user has edit permission
                        if (row.current_status_id == 1 && row.can_edit_movement) {
                            actions += `<a href="/property/movements/${data}/edit" title="Edit">
                                <span class="material-symbols-outlined" style="font-size: 16px;">edit</span>
                            </a>`;
                        }
                        
                        actions += '</div>';
                        return actions;
                    }
                }
            ],
            pageLength: 25,
            responsive: true,
            order: [[0, 'desc']]
        });
    });
</script>
@endsection
