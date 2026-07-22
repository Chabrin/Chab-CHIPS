@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Service Level Agreements</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_service_agreements')
                            <a href="{{ route('service.agreements.move-in-settings') }}" class="btn btn-light border btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined" style="font-size:17px;">search_check</span>
                                <span class="ms-1">Move-in Inspection Settings</span>
                            </a>
                            <a href="{{ route('service.agreements.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add SLA</span>
                            </a>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table id="slaTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Reference Number</th>
                                        <th>Client Name</th>
                                        <th>Property Name</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Commission %</th>
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
        // Initialize DataTable with AJAX
        var table = $('#slaTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('service.agreements.data') }}",
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
                    data: 'client',
                    render: function(data, type, row) {
                        if (!data) return 'N/A';
                        if (row.can_view_client && row.encrypted_client_id) {
                            return `<a href="/property/clients/${row.encrypted_client_id}" class="text-primary sla-link" title="View Client">${data.names}</a>`;
                        }
                        return data.names;
                    }
                },
                {
                    data: 'property',
                    render: function(data, type, row) {
                        if (!data) return 'N/A';
                        if (row.can_view_property && row.encrypted_property_id) {
                            return `<a href="/property/listings/${row.encrypted_property_id}" class="text-primary sla-link" title="View Property">${data.property_name}</a>`;
                        }
                        return data.property_name;
                    }
                },
                {
                    data: 'date_start',
                    render: function(data) {
                        return data ? moment(data).format('MMM D, YYYY') : 'N/A';
                    }
                },
                {
                    data: 'date_end',
                    render: function(data) {
                        return data ? moment(data).format('MMM D, YYYY') : 'N/A';
                    }
                },
                {
                    data: 'commission_percentage',
                    render: function(data) {
                        return data + '%';
                    }
                },
                {
                    data: 'current_status_id',
                    render: function(data, type, row) {
                        // Check if expired
                        if (row.date_end && moment(row.date_end).isBefore(moment(), 'day')) {
                            return '<span class="badge bg-danger">Expired</span>';
                        }
                        // Check if active (status 5)
                        if (data == 5) {
                            return '<span class="badge bg-success">Active</span>';
                        }
                        return '<span class="badge bg-secondary">Inactive</span>';
                    }
                },
                {
                    data: 'encrypted_id',
                    orderable: false,
                    searchable: false,
                    render: function(encryptedId, type, row) {
                        let actions = '';

                        // View details action
                        if (row.can_view_sla) {
                            actions += `
                                <a href="/property/sla/${encryptedId}/show" class="text-primary sla-link me-2" title="View Details">
                                    <span class="material-symbols-outlined view-icon" style="font-size: 18px;">visibility</span>
                                </a>
                            `;
                        }

                        // Edit action (with permission check)
                        if (row.can_edit_sla) {
                            actions += `
                                <a href="/property/sla/${encryptedId}/edit" class="text-success sla-link" title="Edit SLA">
                                    <span class="material-symbols-outlined view-icon" style="font-size: 18px;">edit_document</span>
                                </a>
                            `;
                        }

                        actions += `
                            <span class="spinner-border spinner-border-sm text-primary row-spinner ms-2" style="display:none;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </span>
                        `;

                        return actions || '-';
                    }
                }
            ],
            pageLength: 10,
            responsive: true,
            order: [
                [1, 'asc']
            ]
        });

        // Handle clicks on SLA links
        $(document).on('click', '.sla-link', function(e) {
            e.preventDefault();

            var $row = $(this).closest('tr');
            var $spinner = $row.find('.row-spinner');
            var $viewIcon = $row.find('.view-icon');
            var $links = $row.find('.sla-link');
            var href = $(this).attr('href');

            // Hide view icon and show spinner, disable links
            $viewIcon.hide();
            $spinner.show();
            $links.css('pointer-events', 'none').css('opacity', '0.5');

            // Navigate to the edit page
            window.location.href = href;
        });
    });
</script>
@endsection