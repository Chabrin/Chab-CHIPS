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

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(document).ready(function() {
    let currentSection = '';
    let selectedIds = new Set();
    
    let table = $('#property-confirmations-table').DataTable({
        processing: true,
        serverSide: false,
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
            { data: null, render: function(data) { return $('#confirmation-section').val() || '-'; } },
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

    // Render details based on section
    function renderDetails(section, data) {
        switch(section) {
            case 'Clients':
                return `<strong>Ref:</strong> ${data.reference_number}<br>
                        <strong>Name:</strong> ${data.names}<br>
                        <strong>Email:</strong> ${data.email}<br>
                        <strong>Phone:</strong> ${data.phone_number}<br>
                        <strong>Type:</strong> ${data.client_type}<br>
                        <strong>Zone:</strong> ${data.zone}`;
            case 'Properties':
                return `<strong>Ref:</strong> ${data.reference_number}<br>
                        <strong>Name:</strong> ${data.property_name}<br>
                        <strong>Client:</strong> ${data.client}<br>
                        <strong>Zone:</strong> ${data.zone}<br>
                        <strong>Type:</strong> ${data.property_type}`;
            case 'Units':
                return `<strong>Code:</strong> ${data.unit_code}<br>
                        <strong>Number:</strong> ${data.unit_number}<br>
                        <strong>Property:</strong> ${data.property}<br>
                        <strong>Client:</strong> ${data.client}<br>
                        <strong>Type:</strong> ${data.unit_type}<br>
                        <strong>Status:</strong> ${data.occupancy_status}`;
            case 'Tenants':
                return `<strong>Ref:</strong> ${data.reference_number}<br>
                        <strong>Name:</strong> ${data.names}<br>
                        <strong>Email:</strong> ${data.email}<br>
                        <strong>Phone:</strong> ${data.phone_number}<br>
                        <strong>ID Number:</strong> ${data.id_number}`;
            case 'Service Agreements':
                return `<strong>Ref:</strong> ${data.ref_number}<br>
                        <strong>Property:</strong> ${data.property}<br>
                        <strong>Client:</strong> ${data.client}<br>
                        <strong>Start Date:</strong> ${data.date_start}<br>
                        <strong>End Date:</strong> ${data.date_end}<br>
                        <strong>Duration:</strong> ${data.duration_years} years`;
            case 'Recoveries':
                return `<strong>Ref:</strong> ${data.ref_number}<br>
                        <strong>Date:</strong> ${data.recovery_date}<br>
                        <strong>Amount:</strong> ${data.amount}<br>
                        <strong>Client:</strong> ${data.client}<br>
                        <strong>Property:</strong> ${data.property}<br>
                        <strong>Zone:</strong> ${data.zone}<br>
                        <strong>Commissionable:</strong> ${data.commisionable}`;
            case 'Lease Agreements':
                return `<strong>Ref:</strong> ${data.ref_number}<br>
                        <strong>Tenant:</strong> ${data.tenant}<br>
                        <strong>Property:</strong> ${data.property}<br>
                        <strong>Unit:</strong> ${data.unit}<br>
                        <strong>Start Date:</strong> ${data.date_start}<br>
                        <strong>End Date:</strong> ${data.date_end}<br>
                        <strong>Rent:</strong> ${data.rent_amount}`;
            case 'Inspections':
                return `<strong>Date:</strong> ${data.inspection_date}<br>
                        <strong>Zone:</strong> ${data.zone}<br>
                        <strong>Property:</strong> ${data.property}<br>
                        <strong>Type:</strong> ${data.inspection_type}<br>
                        <strong>Remarks:</strong> ${data.general_remarks}`;
            case 'Tenant Movements':
                return `<strong>Ref:</strong> ${data.ref_number}<br>
                        <strong>Client:</strong> ${data.client}<br>
                        <strong>Property:</strong> ${data.property}<br>
                        <strong>Unit From:</strong> ${data.unit_from}<br>
                        <strong>Unit To:</strong> ${data.unit_to}<br>
                        <strong>Lease:</strong> ${data.lease}<br>
                        <strong>Effective Date:</strong> ${data.effective_date}`;
            case 'Tenant Blacklist':
                return `<strong>Date:</strong> ${data.blacklist_date}<br>
                        <strong>Tenant:</strong> ${data.tenant}<br>
                        <strong>Property:</strong> ${data.property}<br>
                        <strong>Unit:</strong> ${data.unit}<br>
                        <strong>Lease:</strong> ${data.lease}<br>
                        <strong>Reason:</strong> ${data.reason}<br>
                        <strong>Balance:</strong> ${data.current_balance}<br>
                        <strong>Description:</strong> ${data.description}`;
            case 'Repairs & Maintenance':
                return `<strong>Ref:</strong> ${data.ref_number}<br>
                        <strong>Property:</strong> ${data.property}<br>
                        <strong>Client:</strong> ${data.client}<br>
                        <strong>Zone:</strong> ${data.zone}<br>
                        <strong>Visited Date:</strong> ${data.visited_date}<br>
                        <strong>Description:</strong> ${data.description}`;
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
            // Add all IDs from all pages to selectedIds
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
            <div class="row g-2">
                <div class="col-12">
                    <small class="text-muted">Type:</small><br>
                    <strong>${section || '-'}</strong>
                </div>
                <div class="col-12">
                    <small class="text-muted">Created By:</small><br>
                    <strong>${data.created_by_name || '-'}</strong>
                </div>
                <div class="col-12">
                    <small class="text-muted">Created At:</small><br>
                    <strong>${data.created_at || '-'}</strong>
                </div>
                <div class="col-12">
                    <small class="text-muted">Details:</small><br>
                    <div class="border p-2 rounded">
                        ${renderDetails(section, data)}
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
    
    // Approve single
    $(document).on('click', '.approve-btn', function(e) {
        e.stopPropagation();
        let $btn = $(this);
        let id = $btn.data('id');
        let section = $('#confirmation-section').val();
        if (!confirm('Are you sure you want to approve this item?')) return;
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
    $(document).on('click', '.reject-btn', function(e) {
        e.stopPropagation();
        let $btn = $(this);
        let id = $btn.data('id');
        let section = $('#confirmation-section').val();
        let reason = prompt('Please provide a reason for rejection:');
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
    $('#bulk-approve').on('click', function() {
        let $btn = $(this);
        let section = $('#confirmation-section').val();
        let ids = Array.from(selectedIds);
        if (ids.length === 0) return alert('Select at least one item.');
        if (!confirm('Are you sure you want to approve the selected items?')) return;
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
    $('#bulk-reject').on('click', function() {
        let $btn = $(this);
        let section = $('#confirmation-section').val();
        let ids = Array.from(selectedIds);
        if (ids.length === 0) return alert('Select at least one item.');
        let reason = prompt('Please provide a reason for rejection:');
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
           