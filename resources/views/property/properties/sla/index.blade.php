@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/property-listing.css') }}?v={{ filemtime(public_path('assets/css/property-listing.css')) }}">
@endsection

@section('content')
<div class="page-body" id="property-listing">
    <div class="container-fluid">
        <section class="card property-directory" aria-labelledby="sla-directory-title">
            <header class="property-directory-header">
                <div>
                    <p class="property-directory-eyebrow">Management</p>
                    <h1 id="sla-directory-title">Service Level Agreements</h1>
                </div>
                @permission('create_service_agreements')
                <div class="property-directory-actions">
                    <a href="{{ route('service.agreements.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2">
                        <span class="material-symbols-outlined" aria-hidden="true">add_circle</span>
                        Add SLA
                    </a>
                    <a href="{{ route('service.agreements.move-in-settings') }}" class="btn property-secondary-button d-inline-flex align-items-center gap-2">
                        <span class="material-symbols-outlined" aria-hidden="true">fact_check</span>
                        Move-in Inspection Settings
                    </a>
                </div>
                @endpermission
            </header>
            <div class="property-directory-body">
                <div class="property-directory-filters sla-directory-filters" role="group" aria-label="Filter service agreements">
                    <label for="sla-search">Search agreements
                        <input id="sla-search" class="form-control" type="search" placeholder="Reference, client or property" aria-controls="slaTable">
                    </label>
                    <label for="sla-status">Status
                        <select id="sla-status" class="form-select" aria-controls="slaTable">
                            <option value="">All statuses</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Expired">Expired</option>
                        </select>
                    </label>
                </div>
                <div class="table-responsive custom-scrollbar">
                    <table id="slaTable" class="display" aria-label="Service Level Agreements" style="width:100%;">
                        <thead>
                            <tr>
                                <th scope="col">Reference</th>
                                <th scope="col">Client</th>
                                <th scope="col">Property</th>
                                <th scope="col">Start date</th>
                                <th scope="col">End date</th>
                                <th scope="col">Commission</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>
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
        const escapeText = $.fn.dataTable.render.text().display;
        const statusColors = { Active: 'text-success', Inactive: 'text-muted', Expired: 'text-danger' };

        function linkedName(name, type, allowed, path, id) {
            if (type !== 'display') return name || '';
            if (!name) return '—';
            const label = escapeText(name);
            return allowed && id ? '<a href="' + path + encodeURIComponent(id) + '">' + label + '</a>' : label;
        }

        function renderDate(date, type) {
            if (type === 'sort' || type === 'type') return date || '';
            return date ? moment(date).format('MMM D, YYYY') : '—';
        }

        const table = $('#slaTable').DataTable({
            processing: true,
            ajax: {
                url: "{{ route('service.agreements.data') }}",
                dataSrc: 'data'
            },
            columns: [
                {
                    data: 'reference_number',
                    render: function(reference, type, row) {
                        if (type !== 'display') return reference || '';
                        const label = reference ? escapeText(reference) : 'View agreement';
                        if (!row.can_view_sla) return reference ? label : '—';
                        return '<button type="button" class="btn btn-link sla-view-link" data-id="' +
                            encodeURIComponent(row.encrypted_id) + '">' + label + '</button>';
                    }
                },
                {
                    data: 'client',
                    render: function(client, type, row) {
                        return linkedName(client?.names, type, row.can_view_client, '/property/clients/', row.encrypted_client_id);
                    }
                },
                {
                    data: 'property',
                    render: function(property, type, row) {
                        return linkedName(property?.property_name, type, row.can_view_property, '/property/listings/', row.encrypted_property_id);
                    }
                },
                { data: 'date_start', render: renderDate, className: 'text-nowrap' },
                { data: 'date_end', render: renderDate, className: 'text-nowrap' },
                {
                    data: 'commission_percentage',
                    render: function(commission, type) {
                        if (type === 'sort' || type === 'type') return commission;
                        return commission == null ? '—' : escapeText(String(commission)) + '%';
                    }
                },
                {
                    data: 'current_status_id',
                    name: 'status',
                    render: function(statusId, type, row) {
                        const expired = row.date_end && moment(row.date_end).isBefore(moment(), 'day');
                        const status = expired ? 'Expired' : Number(statusId) === 5 ? 'Active' : 'Inactive';
                        return type === 'display' ? '<span class="' + statusColors[status] + '">' + status + '</span>' : status;
                    }
                },
                {
                    data: 'encrypted_id',
                    orderable: false,
                    searchable: false,
                    render: function(encryptedId, type, row) {
                        if (type !== 'display') return '';
                        const id = encodeURIComponent(encryptedId);
                        const actions = [];
                        if (row.can_view_sla) {
                            actions.push('<button type="button" class="directory-icon-action sla-view-link" data-id="' + id +
                                '" title="View SLA" aria-label="View SLA">' +
                                '<span class="material-symbols-outlined" aria-hidden="true">visibility</span></button>');
                        }
                        if (row.can_edit_sla) {
                            actions.push('<a class="directory-icon-action" href="/property/sla/' + id +
                                '/edit" title="Edit SLA" aria-label="Edit SLA">' +
                                '<span class="material-symbols-outlined" aria-hidden="true">edit_square</span></a>');
                        }
                        if (row.can_create_sla) {
                            actions.push('<a class="directory-icon-action" href="{{ route('service.agreements.create') }}?clone=' + id +
                                '" title="Clone SLA" aria-label="Clone SLA">' +
                                '<span class="material-symbols-outlined" aria-hidden="true">content_copy</span></a>');
                        }
                        return actions.length ? '<div class="directory-row-actions">' + actions.join('') + '</div>' : '—';
                    }
                }
            ],
            pageLength: 10,
            layout: { topStart: null, topEnd: null, bottomStart: 'info', bottomEnd: ['pageLength', 'paging'] },
            language: {
                lengthMenu: 'Rows per page _MENU_',
                info: '_START_–_END_ of _TOTAL_ agreements',
                infoEmpty: '0 agreements',
                emptyTable: 'No service agreements are available.',
                zeroRecords: 'No agreements match your search and status filter.'
            },
            order: [[0, 'asc']]
        });

        $('#sla-search').on('input', function() {
            table.search(this.value).draw();
        });

        $('#sla-status').on('change', function() {
            table.column('status:name').search(this.value, { exact: true }).draw();
        });

        $('#slaTable').on('click', '.sla-view-link', function() {
            loadSlaDetailsModal(this.dataset.id);
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
                    <td class="text-muted" style="width:20%;">${label1}</td>
                    <td class="fw-semibold" style="width:30%;">${value1}</td>
                    <td class="text-muted" style="width:20%;">${label2}</td>
                    <td class="fw-semibold" style="width:30%;">${value2}</td>
                </tr>
            `;
        }

        function sectionLabel(title) {
            return `<div class="text-muted">${title}</div>`;
        }

        var itemsRows = (sla.tenant_invoiceable_items || []).map(function(item) {
            var value = (item.amount !== null && item.amount !== undefined)
                ? Number(item.amount).toFixed(2)
                : ((item.percentage_of_rent !== null && item.percentage_of_rent !== undefined)
                    ? Number(item.percentage_of_rent).toFixed(2) + '% of Rent'
                    : '-');
            return `<tr><td>${item.name}</td><td class="fw-semibold">${value}</td></tr>`;
        }).join('');

        var itemsTable = itemsRows
            ? `<table class="table table-sm mb-0"><tbody>${itemsRows}</tbody></table>`
            : '<span class="text-muted">No items added</span>';

        var documentsList = sla.documents.length ? sla.documents.map(function(doc) {
            return `
                <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                    <span>${doc.name}</span>
                    ${doc.url ? `<a href="${doc.url}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2">Download</a>` : '<span class="text-muted">Not available</span>'}
                </div>
            `;
        }).join('') : '<div class="text-muted">No documents uploaded</div>';

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
                            <div class="mt-1">${sla.returns_combination_label}</div>
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
