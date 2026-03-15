@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Property Merges</h5>
                        @permission('create_property_merge')
                        <div class="ms-auto">
                            <a href="{{ route('property.merge.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">New Merge</span>
                            </a>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                        <div class="alert alert-light-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif
                        @if(session('error'))
                        <div class="alert alert-light-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Filter by Status</label>
                                <select class="form-select form-select-sm" id="statusFilter">
                                    <option value="">All Statuses</option>
                                    <option value="1">Pending</option>
                                    <option value="2">Completed</option>
                                    <option value="3">Reversed</option>
                                </select>
                            </div>
                        </div>

                        <!-- Loading Indicator -->
                        <div id="mergeLoader" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Loading property merges...</p>
                        </div>

                        <!-- Data Table -->
                        <div id="mergeTableContainer" style="display: none;">
                            <div class="table-responsive custom-scrollbar">
                                <table id="mergesTable" class="display" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Reference</th>
                                            <th>Client</th>
                                            <th>Merge Type</th>
                                            <th>Merged Property</th>
                                            <th>Target Property</th>
                                            <th>Items</th>
                                            <th>Status</th>
                                            <th>Created By</th>
                                            <th>Date</th>
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
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(document).ready(function() {
    var mergesTable = $('#mergesTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('ajax.property.merge.list') }}",
            type: 'GET',
            dataSrc: 'data',
            beforeSend: function() {
                $('#mergeLoader').show();
                $('#mergeTableContainer').hide();
            },
            complete: function() {
                $('#mergeLoader').hide();
                $('#mergeTableContainer').show();
            },
            error: function() {
                $('#mergeLoader').html('<p class="text-danger">Failed to load data. Please refresh the page.</p>');
            }
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
            { data: 'merge_reference' },
            { data: 'client_name' },
            { data: 'merge_type' },
            { data: 'merged_property_name' },
            { data: 'target_property' },
            {
                data: 'items_count',
                render: function(data) {
                    return '<span class="badge bg-info">' + data + '</span>';
                }
            },
            {
                data: 'current_status_id',
                render: function(data, type, row) {
                    if (data == 1) return '<span class="badge bg-warning">Pending</span>';
                    if (data == 2) return '<span class="badge bg-success">Completed</span>';
                    if (data == 3) return '<span class="badge bg-danger">Reversed</span>';
                    return '<span class="badge bg-secondary">Unknown</span>';
                }
            },
            { data: 'created_by' },
            { data: 'created_at' },
            {
                data: 'encrypted_id',
                orderable: false,
                searchable: false,
                render: function(encryptedId, type, row) {
                    let actions = '';
                    actions += `
                        <a href="/property/merge/${encryptedId}" class="text-primary me-2" title="View Details">
                            <span class="material-symbols-outlined" style="font-size: 20px;">visibility</span>
                        </a>
                    `;
                    return actions || '-';
                }
            }
        ],
        pageLength: 10,
        responsive: true,
        order: [[9, 'desc']]
    });

    // Status filter
    $('#statusFilter').on('change', function() {
        var status = $(this).val();
        mergesTable.ajax.url("{{ route('ajax.property.merge.list') }}?status=" + status).load();
    });
});
</script>
@endsection