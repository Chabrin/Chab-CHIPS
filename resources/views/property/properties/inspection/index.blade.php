@extends('layouts.main')
@section('content')

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Property Inspections</h5>
                        <div class="ms-auto d-flex gap-2">
                            {{-- @permission('create_property_inspections') --}}
                                <a class="btn btn-primary btn-sm d-flex align-items-center" href="{{ route('property.inspection.create') }}">
                                    <span class="material-symbols-outlined">add_circle</span>
                                    <span class="ms-1">Add Inspection</span>
                                </a>
                            {{-- @endpermission --}}
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="property_inspection_table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Zone</th>
                                        <th>Property</th>
                                        <th>Visited Date/Time</th>
                                        <th>Units Inspected</th>
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
        let table = document.querySelector('#property_inspection_table');
        
        $(table).DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('ajax.property.inspection.list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'records'
            },
            columns: [
                { 
                    data: null, 
                    orderable: false, 
                    searchable: false, 
                    render: (data, type, row, meta) => meta.row + 1 
                },
                {
                    data: 'zone',
                    title: 'Zone',
                    render: function(data, type, row) {
                        return data && data.zone_name ? data.zone_name : '-';
                    }
                },
                {
                    data: 'property',
                    title: 'Property',
                    render: function(data, type, row) {
                        if (row.can_read && data && data.property_name) {
                            return `<a href="/property/inspections/${row.encrypted_id}" target="_blank">${data.reference_number} - ${data.property_name}</a>`;
                        }
                        return data && data.property_name ? `${data.reference_number} - ${data.property_name}` : '-';
                    }
                },
                {
                    data: 'visited_date_time',
                    title: 'Visited Date/Time',
                    render: function(data, type, row) {
                        if (data) {
                            const date = new Date(data);
                            return date.toLocaleString('en-GB', {
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                        }
                        return '-';
                    }
                },
                {
                    data: 'units_count',
                    title: 'Units Inspected',
                    render: function(data, type, row) {
                        return data ? `<span class="badge bg-info">${data} units</span>` : '<span class="badge bg-secondary">0 units</span>';
                    }
                },
                { 
                    data: 'created_by', 
                    title: 'Created By', 
                    render: data => data && data.name ? data.name : '-' 
                },
                { 
                    data: 'current_status_id', 
                    title: 'Current Status',
                    render: function(data) {
                        if (data == 1) {
                            return '<span class="badge bg-warning">Draft</span>';
                        } else if (data == 5) {
                            return '<span class="badge bg-success">Completed</span>';
                        } else if (data == 2) {
                            return '<span class="badge bg-info">In Progress</span>';
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
                        if (row.can_read) {
                            buttons += `
                                <a class="inspection-link" href="/property/inspections/${row.encrypted_id}" title="View">
                                    <span class="material-symbols-outlined view-icon" style="font-size:18px;">visibility</span>
                                </a>
                            `;
                        }
                        
                        // Edit button
                        if (row.can_update) {
                            buttons += `
                                <a href="/property/inspections/${row.encrypted_id}/edit" title="Edit">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </a>
                            `;
                        }
                        
                        // Delete button
                        if (row.can_delete) {
                            buttons += `
                                <a href="#" class="delete-inspection" data-id="${id}" title="Delete">
                                    <span class="material-symbols-outlined text-danger" style="font-size:18px;">delete</span>
                                </a>
                            `;
                        }
                        
                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            order: [[3, 'desc']], // Order by visited_datetime descending
            paging: true,
            info: true,
            lengthChange: true
        });

        // Delete inspection handler
        $(document).on('click', '.delete-inspection', function(e) {
            e.preventDefault();
            let inspectionId = $(this).data('id');
            
            if (confirm('Are you sure you want to delete this inspection?')) {
                $.ajax({
                    url: `/property/inspection/${inspectionId}`,
                    type: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        $(table).DataTable().ajax.reload();
                        alert('Inspection deleted successfully');
                    },
                    error: function(xhr) {
                        alert('Error deleting inspection');
                    }
                });
            }
        });
    });
</script>

@endsection