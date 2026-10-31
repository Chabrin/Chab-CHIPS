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

<div class="modal fade" id="slaDetailsModal" tabindex="-1" aria-labelledby="slaDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="slaDetailsModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">description</span>
                    Service Agreement Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="slaDetailsModalBody"></div>
        </div>
    </div>
</div>

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
                                <a href="#" class="text-primary sla-view-link me-2" data-id="${encryptedId}" title="View Details">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">visibility</span>
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

                        // Clone action: opens the Add SLA form with this SLA's terms copied in
                        if (row.can_create_sla) {
                            actions += `
                                <a href="{{ route('service.agreements.create') }}?clone=${encodeURIComponent(encryptedId)}" class="text-info ms-2" title="Clone SLA">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">content_copy</span>
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

        // Handle clicks on the edit link
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

        // Handle clicks on the view link - load details into a modal
        $(document).on('click', '.sla-view-link', function(e) {
            e.preventDefault();
            loadSlaDetailsModal($(this).data('id'));
        });
    });

    function loadSlaDetailsModal(encryptedId) {
        var $modal = $('#slaDetailsModal');
        var $body = $('#slaDetailsModalBody');

        $body.html('<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span></div>');
        $modal.modal('show');

        $.ajax({
            url: `/property/sla/${encryptedId}/show`,
            method: 'GET',
            dataType: 'json',
            success: function(sla) {
                $body.html(renderSlaDetails(sla));
            },
            error: function() {
                $body.html('<div class="text-danger">Failed to load service agreement details.</div>');
            }
        });
    }

    function renderSlaDetails(sla) {
        var statusBadge;
        if (sla.is_expired) {
            statusBadge = '<span class="badge bg-danger">Expired</span>';
        } else if (sla.current_status_id == 5) {
            statusBadge = '<span class="badge bg-success">Active</span>';
        } else {
            statusBadge = '<span class="badge bg-secondary">Inactive</span>';
        }

        function infoRow(label1, value1, label2, value2) {
            return `
                <tr>
                    <td class="text-muted" style="width:20%;font-size:12px;">${label1}</td>
                    <td class="fw-semibold" style="width:30%;font-size:12px;">${value1}</td>
                    <td class="text-muted" style="width:20%;font-size:12px;">${label2}</td>
                    <td class="fw-semibold" style="width:30%;font-size:12px;">${value2}</td>
                </tr>
            `;
        }

        function sectionLabel(title) {
            return `<div class="text-muted text-uppercase" style="font-size:10px; letter-spacing:.5px;">${title}</div>`;
        }

        var itemsRows = (sla.tenant_invoiceable_items || []).map(function(item) {
            var value = (item.amount !== null && item.amount !== undefined)
                ? Number(item.amount).toFixed(2)
                : ((item.percentage_of_rent !== null && item.percentage_of_rent !== undefined)
                    ? Number(item.percentage_of_rent).toFixed(2) + '% of Rent'
                    : '-');
            return `<tr><td style="font-size:12px;">${item.name}</td><td style="font-size:12px;" class="fw-semibold">${value}</td></tr>`;
        }).join('');

        var itemsTable = itemsRows
            ? `<table class="table table-sm mb-0"><tbody>${itemsRows}</tbody></table>`
            : '<span class="text-muted" style="font-size:12px;">No items added</span>';

        var documentsList = sla.documents.length ? sla.documents.map(function(doc) {
            return `
                <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                    <span style="font-size:12px;">${doc.name}</span>
                    ${doc.url ? `<a href="${doc.url}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2">Download</a>` : '<span class="text-muted" style="font-size:11px;">Not available</span>'}
                </div>
            `;
        }).join('') : '<div class="text-muted" style="font-size:12px;">No documents uploaded</div>';

        return `
            <div class="card">
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                ${infoRow('Reference Number', sla.reference_number, 'Status', statusBadge)}
                                ${infoRow('Start Date', sla.date_start, 'End Date', sla.date_end)}
                                ${infoRow('Duration (Years)', sla.years, 'Auto Renewal', sla.auto_renewal)}
                                ${infoRow('Client Name', sla.client_name, 'Client Reference', sla.client_reference)}
                                ${infoRow('Property', sla.property_name, 'Property Reference', sla.property_reference)}
                                ${infoRow('Zone', sla.zone_name, 'Commission', sla.commission_percentage + '%')}
                                ${infoRow('VAT', sla.vat, 'VAT Percentage', sla.vat_per + '%')}
                                ${infoRow('Flat Rate VAT', sla.flat_rate_vat, 'Registration Fee', sla.charge_registration_fee ? 'Yes' : 'No')}
                                ${infoRow('Created On', sla.date_time, 'Created By', sla.created_by)}
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-2">

                    <div class="row g-2">
                        <div class="col-md-6">
                            ${sectionLabel('Tenant Invoiceable Items')}
                            <div class="mt-1">${itemsTable}</div>
                        </div>
                        <div class="col-md-6">
                            ${sectionLabel('Returns Combination')}
                            <div style="font-size:12px;" class="mt-1">${sla.returns_combination_label}</div>
                        </div>
                    </div>

                    <hr class="my-2">

                    ${sectionLabel('Documents')}
                    <div class="mt-1">${documentsList}</div>
                </div>
            </div>
        `;
    }
</script>
@endsection