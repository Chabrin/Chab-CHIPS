@extends('layouts.main')
@section('content')

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header card-no-border d-flex align-items-center">
                <h4 class="mb-0">Property Management Confirmations</h4>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <select class="form-select" id="confirmation-section" data-allow-clear="true">
                        <option value="">-- Select Confirmation Section --</option>
                        @foreach($approvalSections as $section)
                            @permission($section['permission'])
                                <option value="{{ $section['label'] }}">{{ $section['label'] }}</option>
                            @endpermission
                        @endforeach
                    </select>
                </div>
                <hr class="mt-4 mb-4">
                <div class="mb-2 d-flex justify-content-end">
                    <button class="btn btn-primary btn-sm me-4 d-none d-flex align-items-center" id="bulk-approve">
                        <span class="material-symbols-outlined me-2" style="font-size:18px;">check</span>Bulk Approve
                    </button>
                    <button class="btn btn-danger btn-sm d-none d-flex align-items-center" id="bulk-reject">
                        <span class="material-symbols-outlined me-2" style="font-size:18px;">close</span>Bulk Reject
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="property-confirmations-table">
                        <thead>
                            <tr>
                                <th width="30">
                                    <input type="checkbox" id="select-all">
                                </th>
                                <th width="40">#</th>
                                <th>Type</th>
                                <th>Details</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #property-confirmations-table td {
        vertical-align: middle;
        padding-top: 0.65rem;
        padding-bottom: 0.65rem;
    }

    /* Softer, lighter row striping — the theme default was too dark and fought with the badges/text */
    #property-confirmations-table.table-striped > tbody > tr:nth-of-type(odd) > * {
        background-color: #f8f9fb;
    }

    #property-confirmations-table.table-hover > tbody > tr:hover > * {
        background-color: #eef2f7;
    }

    .confirmation-field-list {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }

    .confirmation-field-row {
        display: flex;
        gap: 0.35rem;
        font-size: 0.8rem;
        line-height: 1.4;
    }

    .confirmation-field-row .text-muted {
        flex: 0 0 auto;
        white-space: nowrap;
    }

    .confirmation-field-value {
        font-weight: 600;
        word-break: break-word;
    }
</style>

<!-- Confirm Action Modal (replaces native confirm()) -->
<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmActionModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmActionModalBody">Are you sure?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined me-2" style="font-size:18px;">close</span>Cancel
                </button>
                <button type="button" class="btn d-flex align-items-center" id="confirmActionModalConfirmBtn">
                    <span class="material-symbols-outlined me-2" style="font-size:18px;">check</span>Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Reason Modal (replaces native prompt()) -->
<div class="modal fade" id="rejectReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reason for Rejection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label for="rejectReasonInput" class="form-label form-label-sm">Please provide a reason:</label>
                <textarea class="form-control" id="rejectReasonInput" rows="3" placeholder="Rejection reason…"></textarea>
                <div class="invalid-feedback" id="rejectReasonError">A reason is required.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined me-2" style="font-size:18px;">close</span>Cancel
                </button>
                <button type="button" class="btn btn-danger d-flex align-items-center" id="rejectReasonConfirmBtn">
                    <span class="material-symbols-outlined me-2" style="font-size:18px;">check</span>Confirm Rejection
                </button>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(document).ready(function() {
    let currentSection = '';
    let selectedIds = new Set();
    
    let table = $('#property-confirmations-table').DataTable({
        processing: true,
        serverSide: true,
        ordering: false,
        searching: true,
        ajax: {
            url: "{{ route('property.confirmations.list') }}",
            type: 'GET',
            data: function(d) {
                d.section = $('#confirmation-section').val();
            },
            dataSrc: 'records'
        },
        columns: [
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    return `<input type="checkbox" class="select-row" value="${id}">`;
                }
            },
            { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
            { data: null, render: function(data) { return sectionBadge($('#confirmation-section').val()); } },
            {
                data: null,
                render: function(data) {
                    return renderDetails($('#confirmation-section').val(), data);
                }
            },
            { data: 'created_by_name', render: data => data || '-' },
            { data: 'created_at', render: data => data || '-' },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    return `
                        <button class="btn btn-success btn-sm approve-btn d-flex align-items-center" data-id="${id}">
                            <span class="material-symbols-outlined me-1" style="font-size:16px;">check</span>
                        </button>
                        <button class="btn btn-danger btn-sm reject-btn d-flex align-items-center mt-1" data-id="${id}">
                            <span class="material-symbols-outlined me-1" style="font-size:16px;">close</span>
                        </button>
                    `;
                }
            }
        ],
        paging: true,
        info: true,
        lengthChange: true
    });

    // Badge color per section, cycling through the app's existing badge-light-* palette
    const sectionBadgeClasses = [
        'badge-light-primary', 'badge-light-success', 'badge-light-warning',
        'badge-light-danger', 'badge-light-info', 'badge-light-secondary'
    ];
    function sectionBadge(section) {
        if (!section) return '-';
        let sections = $('#confirmation-section option').map(function() { return this.value; }).get().filter(Boolean);
        let idx = Math.max(0, sections.indexOf(section)) % sectionBadgeClasses.length;
        return `<span class="badge ${sectionBadgeClasses[idx]}">${section}</span>`;
    }

    // Compact "Label: value" list, one field per line — shared by the Details column
    // and the details modal. Deliberately not a side-by-side grid: a DataTable cell is
    // too narrow for label/value columns to sit side by side without wrapping badly.
    function fieldGrid(pairs) {
        return `<div class="confirmation-field-list">` + pairs.map(([label, value]) => `
                <div class="confirmation-field-row">
                    <small class="text-muted">${label}:</small>
                    <span class="confirmation-field-value">${value ?? '-'}</span>
                </div>
            `).join('') + `</div>`;
    }

    // Render details based on section
    function renderDetails(section, data) {
        switch(section) {
            case 'Clients':
                return fieldGrid([
                    ['Ref', data.reference_number], ['Name', data.names], ['Email', data.email],
                    ['Phone', data.phone_number], ['Type', data.client_type], ['Zone', data.zone]
                ]);
            case 'Properties':
                return fieldGrid([
                    ['Ref', data.reference_number], ['Name', data.property_name], ['Client', data.client],
                    ['Zone', data.zone], ['Type', data.property_type]
                ]);
            case 'Units':
                return fieldGrid([
                    ['Code', data.unit_code], ['Number', data.unit_number], ['Property', data.property],
                    ['Client', data.client], ['Type', data.unit_type], ['Status', data.occupancy_status]
                ]);
            case 'Tenants':
                return fieldGrid([
                    ['Ref', data.reference_number], ['Name', data.names], ['Email', data.email],
                    ['Phone', data.phone_number], ['ID Number', data.id_number]
                ]);
            case 'Service Agreements':
                return fieldGrid([
                    ['Ref', data.ref_number], ['Property', data.property], ['Client', data.client],
                    ['Start Date', data.date_start], ['End Date', data.date_end], ['Duration', data.duration_years ? data.duration_years + ' years' : null]
                ]);
            case 'Recoveries':
                return fieldGrid([
                    ['Ref', data.ref_number], ['Date', data.recovery_date], ['Amount', data.amount],
                    ['Client', data.client], ['Property', data.property], ['Zone', data.zone],
                    ['Commissionable', data.commisionable]
                ]);
            case 'Lease Agreements':
                return fieldGrid([
                    ['Ref', data.ref_number], ['Tenant', data.tenant], ['Property', data.property],
                    ['Unit', data.unit], ['Start Date', data.date_start], ['End Date', data.date_end],
                    ['Rent', data.rent_amount]
                ]);
            case 'Inspections':
                return fieldGrid([
                    ['Date', data.inspection_date], ['Zone', data.zone], ['Property', data.property],
                    ['Type', data.inspection_type], ['Remarks', data.general_remarks]
                ]);
            case 'Tenant Movements':
                return fieldGrid([
                    ['Ref', data.ref_number], ['Client', data.client], ['Property', data.property],
                    ['Unit From', data.unit_from], ['Unit To', data.unit_to], ['Lease', data.lease],
                    ['Effective Date', data.effective_date]
                ]);
            case 'Tenant Blacklist':
                return fieldGrid([
                    ['Date', data.blacklist_date], ['Tenant', data.tenant], ['Property', data.property],
                    ['Unit', data.unit], ['Lease', data.lease], ['Reason', data.reason],
                    ['Balance', data.current_balance], ['Description', data.description]
                ]);
            case 'Repairs & Maintenance':
                return fieldGrid([
                    ['Ref', data.ref_number], ['Property', data.property], ['Client', data.client],
                    ['Zone', data.zone], ['Visited Date', data.visited_date], ['Description', data.description]
                ]);
            default:
                return 'N/A';
        }
    }

    $('#confirmation-section').select2({
        placeholder: "-- Select Confirmation Section --",
        allowClear: true,
        width: '100%'
    });

    // Reload table on section change
    $('#confirmation-section').on('change', function() {
        currentSection = $(this).val();
        selectedIds.clear();
        table.ajax.reload();
    });
    
    function toggleBulkButtons() {
        if (selectedIds.size > 0) {
            $('#bulk-approve').removeClass('d-none');
            $('#bulk-reject').removeClass('d-none');
        } else {
            $('#bulk-approve').addClass('d-none');
            $('#bulk-reject').addClass('d-none');
        }
    }
    
    // Handle row checkbox change
    $(document).on('change', '.select-row', function(e) {
        e.stopPropagation();
        let id = $(this).val();
        
        if ($(this).is(':checked')) {
            selectedIds.add(id);
        } else {
            selectedIds.delete(id);
        }
        toggleBulkButtons();
    });
    
    // Handle select-all checkbox
    $(document).on('change', '#select-all', function() {
        let checked = this.checked;
        
        if (checked) {
            // Add all IDs on the current page to selectedIds (server-side paging — other pages aren't loaded client-side)
            table.rows().every(function() {
                let data = this.data();
                if (data && data.id) {
                    selectedIds.add(data.id.toString());
                }
            });
        } else {
            // Clear all selections
            selectedIds.clear();
        }
        
        // Update visible checkboxes
        $('.select-row').prop('checked', checked);
        toggleBulkButtons();
    });
    
    // On table redraw, re-check selected checkboxes
    table.on('draw', function() {
        let allChecked = true;
        let hasRows = false;
        
        $('.select-row').each(function() {
            hasRows = true;
            let id = $(this).val();
            if (selectedIds.has(id)) {
                $(this).prop('checked', true);
            } else {
                $(this).prop('checked', false);
                allChecked = false;
            }
        });
        
        // Update select-all checkbox state
        $('#select-all').prop('checked', allChecked && hasRows);
        toggleBulkButtons();
    });

    // Show details modal on row click
    $('#property-confirmations-table tbody').on('click', 'tr', function(e) {
        if ($(e.target).closest('input[type="checkbox"], button').length) return;
        
        let data = table.row(this).data();
        if (!data) return;
        
        let section = $('#confirmation-section').val();
        let detailsContent = `
            <div class="row g-3">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light py-2">
                            <h6 class="mb-0" style="font-size:0.9rem;">Record Information</h6>
                        </div>
                        <div class="card-body py-2">
                            ${fieldGrid([
                                ['Type', sectionBadge(section)],
                                ['Created By', data.created_by_name],
                                ['Created At', data.created_at]
                            ])}
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light py-2">
                            <h6 class="mb-0" style="font-size:0.9rem;">Details</h6>
                        </div>
                        <div class="card-body py-2">
                            ${renderDetails(section, data)}
                        </div>
                    </div>
                </div>
            </div>
        `;

        let modalContent = `
            <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Record Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">${detailsContent}</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                                <span class="material-symbols-outlined me-2" style="font-size:18px;">close</span>Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#detailsModal').remove();
        $('body').append(modalContent);
        $('#detailsModal').modal('show');
        $('#detailsModal').on('hidden.bs.modal', function() {
            $(this).remove();
        });
    });

    $('#property-confirmations-table tbody').css('cursor', 'pointer');

    function setButtonLoading($btn, loading = true) {
        if (loading) {
            $btn.prop('disabled', true);
            let spinner = '<span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true"></span>';
            if ($btn.find('.spinner-border').length === 0) {
                $btn.append(spinner);
            }
        } else {
            $btn.prop('disabled', false);
            $btn.find('.spinner-border').remove();
        }
    }
    
    // Confirm Action modal (replaces native confirm())
    function confirmAction(title, body, confirmClass = 'btn-primary') {
        return new Promise(resolve => {
            $('#confirmActionModalTitle').text(title);
            $('#confirmActionModalBody').text(body);
            let $confirmBtn = $('#confirmActionModalConfirmBtn')
                .removeClass('btn-primary btn-success btn-danger')
                .addClass(confirmClass);
            let modalEl = document.getElementById('confirmActionModal');
            let modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            let settled = false;

            $confirmBtn.off('click').on('click', function() {
                settled = true;
                modal.hide();
                resolve(true);
            });
            $(modalEl).off('hidden.bs.modal').on('hidden.bs.modal', function() {
                if (!settled) resolve(false);
            });
            modal.show();
        });
    }

    // Rejection Reason modal (replaces native prompt())
    function promptRejectReason() {
        return new Promise(resolve => {
            $('#rejectReasonInput').val('').removeClass('is-invalid');
            let modalEl = document.getElementById('rejectReasonModal');
            let modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            let settled = false;

            $('#rejectReasonConfirmBtn').off('click').on('click', function() {
                let reason = $('#rejectReasonInput').val().trim();
                if (!reason) {
                    $('#rejectReasonInput').addClass('is-invalid');
                    return;
                }
                settled = true;
                modal.hide();
                resolve(reason);
            });
            $(modalEl).off('hidden.bs.modal').on('hidden.bs.modal', function() {
                if (!settled) resolve(null);
            });
            modal.show();
        });
    }

    // Approve single
    $(document).on('click', '.approve-btn', async function(e) {
        e.stopPropagation();
        let $btn = $(this);
        let id = $btn.data('id');
        let section = $('#confirmation-section').val();
        let ok = await confirmAction('Approve Record', 'Are you sure you want to approve this item?', 'btn-success');
        if (!ok) return;
        setButtonLoading($btn, true);
        $.post(`/property/confirmations/${id}/approve`, {
            _token: $('meta[name="csrf-token"]').attr('content'),
            section: section
        }, function(res) {
            if (res.success) {
                toastr.success('Approved successfully');
                selectedIds.delete(id);
                table.ajax.reload();
            } else {
                toastr.error(res.error || 'Failed to approve');
            }
        }).always(function() {
            setButtonLoading($btn, false);
        });
    });

    // Reject single
    $(document).on('click', '.reject-btn', async function(e) {
        e.stopPropagation();
        let $btn = $(this);
        let id = $btn.data('id');
        let section = $('#confirmation-section').val();
        let reason = await promptRejectReason();
        if (!reason) return;
        setButtonLoading($btn, true);
        $.post(`/property/confirmations/${id}/reject`, {
            _token: $('meta[name="csrf-token"]').attr('content'),
            section: section,
            rejection_reason: reason
        }, function(res) {
            if (res.success) {
                toastr.success('Rejected successfully');
                selectedIds.delete(id);
                table.ajax.reload();
            } else {
                toastr.error(res.error || 'Failed to reject');
            }
        }).always(function() {
            setButtonLoading($btn, false);
        });
    });

    // Bulk Approve
    $('#bulk-approve').on('click', async function() {
        let $btn = $(this);
        let section = $('#confirmation-section').val();
        let ids = Array.from(selectedIds);
        if (ids.length === 0) return toastr.warning('Select at least one item.');
        let ok = await confirmAction('Approve Selected Records', 'Are you sure you want to approve the selected items?', 'btn-success');
        if (!ok) return;
        setButtonLoading($btn, true);
        $.post('/property/confirmations/bulk-approve', {
            _token: $('meta[name="csrf-token"]').attr('content'),
            ids: ids,
            section: section
        }, function(res) {
            if (res.success) {
                toastr.success('Bulk approved successfully');
                selectedIds.clear();
                table.ajax.reload();
            } else {
                toastr.error(res.error || 'Failed to bulk approve');
            }
        }).always(function() {
            setButtonLoading($btn, false);
        });
    });

    // Bulk Reject
    $('#bulk-reject').on('click', async function() {
        let $btn = $(this);
        let section = $('#confirmation-section').val();
        let ids = Array.from(selectedIds);
        if (ids.length === 0) return toastr.warning('Select at least one item.');
        let reason = await promptRejectReason();
        if (!reason) return;
        setButtonLoading($btn, true);
        $.post('/property/confirmations/bulk-reject', {
            _token: $('meta[name="csrf-token"]').attr('content'),
            ids: ids,
            section: section,
            rejection_reason: reason
        }, function(res) {
            if (res.success) {
                toastr.success('Bulk rejected successfully');
                selectedIds.clear();
                table.ajax.reload();
            } else {
                toastr.error(res.error || 'Failed to bulk reject');
            }
        }).always(function() {
            setButtonLoading($btn, false);
        });
    });
});
</script>
@endsection
           