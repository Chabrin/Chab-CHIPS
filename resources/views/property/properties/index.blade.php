@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Property Listings</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('update_properties')
                            <a href="{{ route('property.activation.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">toggle_on</span>
                                <span class="ms-1">Manage Activation</span>
                            </a>
                            @endpermission
                            @permission('create_properties')
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addPropertyModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Property</span>
                            </button>
                            @endpermission
                        </div>
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

                        <div class="table-responsive custom-scrollbar">
                            <table id="propertiesTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Reference Number</th>
                                        <th>Property Name</th>
                                        {{-- <th>Description</th> --}}
                                        <th>Zone</th>
                                        <th>Client Name</th>
                                        <th>Actions</th>
                                    </tr>
                                    <tr class="column-filters">
                                        <th></th>
                                        <th></th>
                                        <th><input type="text" class="form-control form-control-sm" placeholder="-- Search --" /></th>
                                        <th><input type="text" class="form-control form-control-sm" placeholder="-- Search --" /></th>
                                        <th><input type="text" class="form-control form-control-sm" placeholder="-- Search --" /></th>
                                        <th></th>
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

<script>
    $(document).ready(function() {
        // Initialize DataTable with AJAX
        var table = $('#propertiesTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('property.properties.data') }}",
                type: 'GET',
                dataSrc: 'data'
            },
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'reference_number'
                },
                {
                    data: 'property_name',
                    render: function(data, type, row) {
                        return `<a href="/property/listings/${row.encrypted_id}" class="property-link">${data}</a>`;
                    }
                },
                // { data: 'description' },
                {
                    data: 'zone_name'
                },
                {
                    data: 'client_name',
                    render: function(data, type, row) {
                        if (row.client_encrypted_id && row.can_read_client) {
                            return `<a href="/property/clients/${row.client_encrypted_id}">${data}</a>`;
                        }
                        return data;
                    }
                },
                {
                    data: 'encrypted_id',
                    orderable: false,
                    searchable: false,
                    render: function(encryptedId, type, row) {
                        let actions = '';

                        if (row.can_read) {
                            actions += `
                                <a href="/property/listings/${encryptedId}" class="text-primary property-link" title="View Details">
                                    <span class="material-symbols-outlined view-icon" style="font-size: 20px;">visibility</span>
                                </a>
                                <span class="spinner-border spinner-border-sm text-primary row-spinner ms-2" style="display:none;" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </span>
                            `;
                        }

                        if (row.can_update) {
                            actions += `
                                <a href="/property/properties/${encryptedId}/edit" class="text-success property-link" title="Edit Property">
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
            orderCellsTop: true,
            order: [
                [2, 'asc']
            ]
        });

        // Client-side column filters
        $('#propertiesTable thead .column-filters input').on('keyup change', function() {
            var colIdx = $(this).closest('th').index();
            table.column(colIdx).search(this.value).draw();
        });

        // Handle clicks on property links
        $(document).on('click', '.property-link', function(e) {
            e.preventDefault();

            var $row = $(this).closest('tr');
            var $spinner = $row.find('.row-spinner');
            var $viewIcon = $row.find('.view-icon');
            var $links = $row.find('.property-link');
            var href = $(this).attr('href');

            // Hide view icon and show spinner, disable links
            $viewIcon.hide();
            $spinner.show();
            $links.css('pointer-events', 'none').css('opacity', '0.5');

            // Navigate to the details page
            window.location.href = href;
        });

        $(document).on('propertyCreated', function(e, res) {
            if (res.redirect_url) {
                window.location.href = res.redirect_url;
            } else {
                table.ajax.reload();
            }
        });
    });
</script>

@include('property.properties.partials.add-property-modal')
@endsection