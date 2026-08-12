@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Communication Management</h5>
                        <div class="ms-auto">
                            @permission('create_communication_templates')
                            <a href="/hrsettings/communication/templates/create" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Template</span>
                            </a>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Tabs -->
                        <ul class="nav nav-tabs" id="communicationTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active d-flex align-items-center" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" role="tab">
                                    <span class="material-symbols-outlined me-1" style="font-size:18px;">description</span>
                                    Templates
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link d-flex align-items-center" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                                    <span class="material-symbols-outlined me-1" style="font-size:18px;">history</span>
                                    Communication History
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content mt-3" id="communicationTabsContent">
                            <!-- Templates Tab -->
                            <div class="tab-pane fade show active" id="templates" role="tabpanel">
                                <table class="table table-bordered" id="templates-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Template Name</th>
                                            <th>Type</th>
                                            <th>Method</th>
                                            <th>Subject</th>
                                            <th>Created By</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <!-- Communication History Tab -->
                            <div class="tab-pane fade" id="history" role="tabpanel">
                                <table class="table table-bordered" id="communications-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Type</th>
                                            <th>Recipient Type</th>
                                            <th>Subject</th>
                                            <th>Recipients</th>
                                            <th>Status</th>
                                            <th>Sent At</th>
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

<!-- Communication Details Modal -->
<div class="modal fade" id="communicationDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Communication Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="communicationDetailsBody">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined">close</span>
                    <span class="ms-1">Close</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Templates Table
    let templatesTable = $('#templates-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ url('/ajax/hr-settings/communication-templates/list') }}",
            type: 'GET',
            dataSrc: 'templates'
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
            { data: 'template_name', title: 'Template Name' },
            { 
                data: 'template_type', 
                title: 'Type',
                render: data => {
                    const types = {
                        'general': 'General Communication',
                        'contract': 'Contract',
                        'leave_application': 'Leave Application',
                        'contract_termination': 'Contract Termination',
                        'disciplinary_action': 'Disciplinary Action'
                    };
                    return types[data] || data;
                }
            },
            {
                data: 'communication_method',
                title: 'Method',
                render: data => data === 'email' ? '<span class="badge bg-info">Email</span>' : '<span class="badge bg-success">SMS</span>'
            },
            { data: 'subject', title: 'Subject', render: data => data ? (data.substring(0, 50) + (data.length > 50 ? '...' : '')) : '-' },
            { data: 'created_by', title: 'Created By', render: data => data ? data.name : '-' },
            { data: 'date_time', title: 'Date', render: data => data ? new Date(data).toLocaleDateString() : '-' },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    let buttons = '<div class="d-flex flex-row gap-1">';
                    if (row.can_read) {
                        buttons += `<a href="/hrsettings/communication/templates/show/${row.encrypt_template_id}" class="btn btn-xs btn-light" style="padding:1px 2px;">
                            <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                        </a>`;
                    }
                    if (row.can_update) {
                        buttons += `<a href="/hrsettings/communication/templates/edit/${row.encrypt_template_id}" class="btn btn-xs btn-light" style="padding:1px 2px;">
                            <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                        </a>`;
                    }
                    if (row.can_delete) {
                        buttons += `<button class="btn btn-xs btn-light delete-template" data-id="${row.id}" style="padding:1px 2px;">
                            <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                        </button>`;
                    }
                    buttons += '</div>';
                    return buttons;
                }
            }
        ],
        paging: true,
        info: true,
        lengthChange: true
    });

    // Communications History Table
    let communicationsTable = $('#communications-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ url('/ajax/hr-settings/communications/list') }}",
            type: 'GET',
            dataSrc: 'communications'
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
            {
                data: 'type',
                title: 'Type',
                render: data => data === 'email' ? '<span class="badge bg-info">Email</span>' : '<span class="badge bg-success">SMS</span>'
            },
            {
                data: 'recipient_type',
                title: 'Recipient Type',
                render: data => {
                    const types = {
                        'staff': '<span class="badge bg-primary">Staff</span>',
                        'position': '<span class="badge bg-secondary">Position</span>',
                        'everyone': '<span class="badge bg-dark">Everyone</span>',
                        'system': '<span class="badge bg-warning">System</span>'
                    };
                    return types[data] || data || '-';
                }
            },
            { 
                data: 'subject', 
                title: 'Subject', 
                render: data => data ? (data.substring(0, 40) + (data.length > 40 ? '...' : '')) : '-' 
            },
            {
                data: 'recipients',
                title: 'Recipients',
                render: data => {
                    try {
                        const recipients = JSON.parse(data);
                        return recipients.length + ' recipient(s)';
                    } catch {
                        return '-';
                    }
                }
            },
            {
                data: 'send_status',
                title: 'Status',
                render: data => {
                    if (data === 'sent') return '<span class="badge bg-success">Sent</span>';
                    if (data === 'failed') return '<span class="badge bg-danger">Failed</span>';
                    if (data === 'partial') return '<span class="badge bg-warning">Partial</span>';
                    return '<span class="badge bg-secondary">Unknown</span>';
                }
            },
            {
                data: 'sent_at',
                title: 'Sent At',
                render: data => data ? new Date(data).toLocaleString() : '-'
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    return `
                        <button class="btn btn-xs btn-light view-communication" data-id="${id}" style="padding:1px 2px;">
                            <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                        </button>
                    `;
                }
            }
        ],
        order: [[6, 'desc']], // Sort by sent_at descending
        paging: true,
        info: true,
        lengthChange: true
    });

    // Initialize communications table when tab is shown
    $('button[data-bs-target="#history"]').on('shown.bs.tab', function() {
        communicationsTable.ajax.reload();
    });

    // View Communication Details
    $(document).on('click', '.view-communication', function() {
        let id = $(this).data('id');
        
        $.ajax({
            url: "{{ url('/ajax/hr-settings/communications/show') }}/" + id,
            type: 'GET',
            success: function(res) {
                if (res.success) {
                    let comm = res.communication;
                    let recipients = [];
                    try {
                        recipients = JSON.parse(comm.recipients);
                    } catch {}
                    
                    let content = `
                        <div class="row g-3">
                            <div class="col-md-6">
                                <small class="text-muted">Type:</small><br>
                                <strong>${comm.type === 'email' ? 'Email' : 'SMS'}</strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Recipient Type:</small><br>
                                <strong>${comm.recipient_type || '-'}</strong>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">Subject:</small><br>
                                <strong>${comm.subject || '-'}</strong>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">Message:</small><br>
                                <div class="border p-2 rounded bg-primary text-white">
                                    ${comm.message || '-'}
                                </div>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">Recipients (${recipients.length}):</small><br>
                                <div class="border p-2 rounded">
                                    ${recipients.join(', ')}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Status:</small><br>
                                <strong class="text-${comm.send_status === 'sent' ? 'success' : 'danger'}">${comm.send_status || '-'}</strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Sent At:</small><br>
                                <strong>${comm.sent_at ? new Date(comm.sent_at).toLocaleString() : '-'}</strong>
                            </div>
                            ${comm.send_error ? `
                            <div class="col-12">
                                <small class="text-muted">Error:</small><br>
                                <div class="alert alert-danger mb-0">${comm.send_error}</div>
                            </div>` : ''}
                            ${comm.attachment ? `
                            <div class="col-12">
                                <small class="text-muted">Attachment:</small><br>
                                <a href="/storage/${comm.attachment}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <span class="material-symbols-outlined" style="font-size:16px;">attachment</span>
                                    View Attachment
                                </a>
                            </div>` : ''}
                        </div>
                    `;
                    
                    $('#communicationDetailsBody').html(content);
                    $('#communicationDetailsModal').modal('show');
                } else {
                    toastr.error('Failed to load communication details');
                }
            },
            error: function() {
                toastr.error('Failed to load communication details');
            }
        });
    });

    // Delete Template
    $(document).on('click', '.delete-template', function() {
        if (!confirm('Are you sure you want to delete this template?')) return;
        
        let id = $(this).data('id');
        $.ajax({
            url: "{{ url('/ajax/hr-settings/communication-templates/delete') }}/" + id,
            type: 'DELETE',
            data: { _token: "{{ csrf_token() }}" },
            success: function(res) {
                if (res.success) {
                    templatesTable.ajax.reload();
                    toastr.success('Template deleted successfully');
                } else {
                    toastr.error(res.error || 'Failed to delete template');
                }
            },
            error: function() {
                toastr.error('Failed to delete template');
            }
        });
    });
});
</script>

<style>
.material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 20;
}
</style>

@endsection