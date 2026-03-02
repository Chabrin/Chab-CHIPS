@extends('layouts.main')
@section('content')

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Lease Escalations</h5>
                        @permission('create_lease_escalations')
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('property.escalations.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">New Escalation</span>
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

                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs mb-3" id="escalationTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-escalations" type="button">
                                    <span class="material-symbols-outlined" style="font-size:18px;">list</span>
                                    All Escalations
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-escalations" type="button">
                                    <span class="material-symbols-outlined" style="font-size:18px;">pending_actions</span>
                                    Pending Approval
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved-escalations" type="button">
                                    <span class="material-symbols-outlined" style="font-size:18px;">check_circle</span>
                                    Approved
                                </button>
                            </li>
                        </ul>

                        <!-- Tab panes -->
                        <div class="tab-content" id="escalationTabContent">
                            <!-- All Escalations Tab -->
                            <div class="tab-pane fade show active" id="all-escalations" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="allEscalationsTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Date</th>
                                                <th>Landlord</th>
                                                <th>Property</th>
                                                <th>Units</th>
                                                <th>Type</th>
                                                <th>Value</th>
                                                <th>Prev. Total</th>
                                                <th>New Total</th>
                                                <th>Effective</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Pending Approval Tab -->
                            <div class="tab-pane fade" id="pending-escalations" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="pendingEscalationsTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Date</th>
                                                <th>Landlord</th>
                                                <th>Property</th>
                                                <th>Units</th>
                                                <th>Type</th>
                                                <th>Value</th>
                                                <th>Prev. Total</th>
                                                <th>New Total</th>
                                                <th>Status</th>
                                                <th>Created By</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Approved Tab -->
                            <div class="tab-pane fade" id="approved-escalations" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="approvedEscalationsTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Date</th>
                                                <th>Landlord</th>
                                                <th>Property</th>
                                                <th>Units</th>
                                                <th>Type</th>
                                                <th>Value</th>
                                                <th>Prev. Total</th>
                                                <th>New Total</th>
                                                <th>Effective</th>
                                                <th>Approved By</th>
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
</div>

<!-- ─── Approval Modal ─── -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approvalModalLabel">Escalation Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="approvalForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="approval_escalation_id">
                    <input type="hidden" id="approval_action">

                    <!-- Escalation Summary -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <h6 class="text-muted border-bottom pb-2">Escalation Details</h6>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Landlord</small>
                            <p class="mb-1 fw-bold" id="modal_landlord"></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Property</small>
                            <p class="mb-1 fw-bold" id="modal_property"></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Unit</small>
                            <p class="mb-1 fw-bold" id="modal_unit"></p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Type</small>
                            <p class="mb-1" id="modal_type"></p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Value</small>
                            <p class="mb-1" id="modal_value"></p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Previous Amount</small>
                            <p class="mb-1" id="modal_previous"></p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">New Amount</small>
                            <p class="mb-1 fw-bold text-success" id="modal_new"></p>
                        </div>
                    </div>

                    <!-- Approval Trail -->
                    <div class="row mb-3" id="approvalTrailSection">
                        <div class="col-12">
                            <h6 class="text-muted border-bottom pb-2">Approval Trail</h6>
                        </div>
                        <div class="col-12">
                            <div id="approvalTrailContent"></div>
                        </div>
                    </div>

                    <!-- Action inputs -->
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-muted border-bottom pb-2" id="actionSectionTitle">Your Action</h6>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="approval_comments" class="form-label">Comments <span class="text-danger" id="commentsRequiredMark" style="display:none;">*</span></label>
                            <textarea class="form-control" id="approval_comments" rows="3" placeholder="Enter your comments..."></textarea>
                        </div>
                        <div class="col-md-12 mb-3" id="documentsUploadSection">
                            <label for="approval_documents" class="form-label">Upload Documents (Proof of Authority)</label>
                            <input type="file" class="form-control" id="approval_documents" name="documents[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small class="text-muted">PDF, DOC, DOCX, JPG, PNG — max 10MB each</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="rejectBtn" onclick="submitApproval('reject')">
                        <span class="material-symbols-outlined" style="font-size:18px;">cancel</span> Reject
                    </button>
                    <button type="button" class="btn btn-success" id="approveBtn" onclick="submitApproval('approve')">
                        <span class="material-symbols-outlined" style="font-size:18px;">check_circle</span> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ─── Detail Modal (View Only) ─── -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">Escalation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detailModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(document).ready(function () {

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Group a flat escalation array by ref_number.
     * Returns one "parent" object per ref_number with a `children` array.
     */
    function groupByRef(data) {
        const map = {};
        const order = [];

        data.forEach(row => {
            const key = row.ref_number || ('no-ref-' + row.id);
            if (!map[key]) {
                map[key] = {
                    // Shared fields (take from first row in group)
                    ref_number:       row.ref_number,
                    date_time:        row.date_time,
                    client_name:      row.client_name,
                    property_name:    row.property_name,
                    escalation_type:  row.escalation_type,
                    escalation_value: row.escalation_value,
                    effective_date:   row.effective_date,
                    status:           row.status,
                    status_label:     row.status_label,
                    status_badge:     row.status_badge,
                    created_by:       row.created_by,
                    // Aggregates (will be summed)
                    previous_total:   0,
                    new_total:        0,
                    children:         [],
                };
                order.push(key);
            }
            map[key].previous_total += parseFloat(row.previous_amount) || 0;
            map[key].new_total      += parseFloat(row.new_amount) || 0;
            map[key].children.push(row);
        });

        return order.map(k => map[k]);
    }

    // ── Child sub-table renderer ─────────────────────────────────────

    function buildChildTable(children, showApprovedBy) {
        let cols = `<th class="bg-light text-secondary small">Unit</th>
                    <th class="bg-light text-secondary small">Lease Ref</th>
                    <th class="bg-light text-secondary small">Scope</th>
                    <th class="bg-light text-secondary small">Previous</th>
                    <th class="bg-light text-secondary small">New</th>
                    <th class="bg-light text-secondary small">Status</th>
                    <th class="bg-light text-secondary small">Actions</th>`;
        if (showApprovedBy) {
            cols = `<th class="bg-light text-secondary small">Unit</th>
                    <th class="bg-light text-secondary small">Lease Ref</th>
                    <th class="bg-light text-secondary small">Scope</th>
                    <th class="bg-light text-secondary small">Previous</th>
                    <th class="bg-light text-secondary small">New</th>
                    <th class="bg-light text-secondary small">Approved By</th>`;
        }

        let rows = '';
        children.forEach(c => {
            const prev = parseFloat(c.previous_amount).toLocaleString();
            const newA = parseFloat(c.new_amount).toLocaleString();

            if (showApprovedBy) {
                const pm = c.approvals && c.approvals.find(a => a.step === 3 && a.action === 'approved');
                rows += `<tr>
                    <td class="small fw-semibold">${c.unit_code}</td>
                    <td class="small">${c.lease_ref || 'N/A'}</td>
                    <td class="small">${c.scope || ''}</td>
                    <td class="small">${prev}</td>
                    <td class="small text-success fw-bold">${newA}</td>
                    <td class="small">${pm ? pm.user : 'N/A'}</td>
                </tr>`;
            } else {
                rows += `<tr>
                    <td class="small fw-semibold">${c.unit_code}</td>
                    <td class="small">${c.lease_ref || 'N/A'}</td>
                    <td class="small">${c.scope || ''}</td>
                    <td class="small">${prev}</td>
                    <td class="small text-success fw-bold">${newA}</td>
                    <td class="small"><span class="badge bg-${c.status_badge}">${c.status_label}</span></td>
                    <td class="small d-flex justify-content-center">${renderActions(c)}</td>
                </tr>`;
            }
        });

        return `<div class="ps-4 py-2 border-start border-2">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead><tr>${cols}</tr></thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
    }

    // ── Toggle row expansion ─────────────────────────────────────────

    function attachToggle(tableEl, tableObj, showApprovedBy) {
        $(tableEl).on('click', 'tr.group-parent-row', function () {
            const tr    = $(this);
            const dtRow = tableObj.row(this);
            const data  = dtRow.data();
            if (!data) return;

            const icon = tr.find('.toggle-chevron');

            if (dtRow.child.isShown()) {
                dtRow.child.hide();
                icon.text('chevron_right');
                tr.removeClass('table-primary');
                tr.addClass('table-light');
            } else {
                dtRow.child(buildChildTable(data.children, showApprovedBy)).show();
                icon.text('expand_more');
                tr.removeClass('table-light');
                tr.addClass('table-primary');
            }
        });
    }

    // ── Shared render helpers ────────────────────────────────────────

    function fmtDate(d) {
        return d ? moment(d).format('MMM D, YYYY') : '';
    }

    function fmtValue(group) {
        return group.escalation_type === 'Percentage'
            ? group.escalation_value + '%'
            : parseFloat(group.escalation_value).toLocaleString();
    }

    function renderActions(data) {
        let html = '';
        html += `<button class="btn btn-sm btn-outline-info me-1" onclick="viewDetail(${data.id})" title="View Details">
                    <span class="material-symbols-outlined" style="font-size:16px;">visibility</span>
                 </button>`;
        if (data.status === 'pending_zm' || data.status === 'pending_pm') {
            html += `<button class="btn btn-sm btn-outline-success me-1" onclick="openApprovalModal(${data.id}, 'approve')" title="Approve">
                        <span class="material-symbols-outlined" style="font-size:16px;">check</span>
                     </button>`;
            html += `<button class="btn btn-sm btn-outline-danger" onclick="openApprovalModal(${data.id}, 'reject')" title="Reject">
                        <span class="material-symbols-outlined" style="font-size:16px;">close</span>
                     </button>`;
        }
        return html;
    }

    function groupStatusBadge(children) {
        // Show distinct statuses in the group
        const statuses = [...new Map(children.map(c => [c.status, c])).values()];
        return statuses.map(c => `<span class="badge bg-${c.status_badge}">${c.status_label}</span>`).join(' ');
    }

    function groupActionsBtns(children) {
        // Collect unique pending IDs for approve/reject, show count
        const pending = children.filter(c => c.status === 'pending_zm' || c.status === 'pending_pm');
        if (!pending.length) return '';
        return `<small class="text-muted">${pending.length} unit(s) pending</small>`;
    }

    // ── Ref-number toggle cell HTML ──────────────────────────────────

    function refCell(group) {
        const count = group.children.length;
        return `<span class="d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined text-primary toggle-chevron" style="font-size:18px;">chevron_right</span>
                    <span class="fw-semibold text-primary small">${group.ref_number || 'N/A'}</span>
                    <span class="badge rounded-pill bg-primary ms-1">${count} unit${count > 1 ? 's' : ''}</span>
                </span>`;
    }

    // ── ALL ESCALATIONS TABLE ────────────────────────────────────────

    const allTable = $('#allEscalationsTable').DataTable({
        ajax: {
            url: '{{ route("property.escalations.data") }}',
            dataSrc: function (json) {
                return groupByRef(json.data);
            }
        },
        columns: [
            { data: null, render: (d, t, r, m) => m.row + 1 },
            { data: 'date_time',        render: (d) => fmtDate(d) },
            { data: 'client_name' },
            { data: 'property_name' },
            { data: null,               render: (d) => refCell(d) },     // Units / ref
            { data: 'escalation_type' },
            { data: null,               render: (d) => fmtValue(d) },
            { data: 'previous_total',   render: d => parseFloat(d).toLocaleString() },
            { data: 'new_total',        render: d => parseFloat(d).toLocaleString() },
            { data: 'effective_date',   render: (d) => fmtDate(d) },
            { data: null,               render: (d) => groupStatusBadge(d.children) },
            { data: null,               render: (d) => groupActionsBtns(d.children) },
        ],
        createdRow: function (row) {
            $(row).addClass('group-parent-row table-light').css('cursor', 'pointer');
        },
        order: [[0, 'desc']],
        pageLength: 25
    });

    attachToggle('#allEscalationsTable', allTable, false);

    // ── PENDING TABLE ────────────────────────────────────────────────

    const pendingTable = $('#pendingEscalationsTable').DataTable({
        ajax: {
            url: '{{ route("property.escalations.data") }}',
            dataSrc: function (json) {
                const filtered = json.data.filter(d => d.status === 'pending_zm' || d.status === 'pending_pm');
                return groupByRef(filtered);
            }
        },
        columns: [
            { data: null, render: (d, t, r, m) => m.row + 1 },
            { data: 'date_time',        render: (d) => fmtDate(d) },
            { data: 'client_name' },
            { data: 'property_name' },
            { data: null,               render: (d) => refCell(d) },
            { data: 'escalation_type' },
            { data: null,               render: (d) => fmtValue(d) },
            { data: 'previous_total',   render: d => parseFloat(d).toLocaleString() },
            { data: 'new_total',        render: d => parseFloat(d).toLocaleString() },
            { data: null,               render: (d) => groupStatusBadge(d.children) },
            { data: 'created_by' },
            { data: null,               render: (d) => groupActionsBtns(d.children) },
        ],
        createdRow: function (row) {
            $(row).addClass('group-parent-row table-light').css('cursor', 'pointer');
        },
        order: [[0, 'desc']],
        pageLength: 25
    });

    attachToggle('#pendingEscalationsTable', pendingTable, false);

    // ── APPROVED TABLE ───────────────────────────────────────────────

    const approvedTable = $('#approvedEscalationsTable').DataTable({
        ajax: {
            url: '{{ route("property.escalations.data") }}',
            dataSrc: function (json) {
                return groupByRef(json.data);
            }
        },
        columns: [
            { data: null, render: (d, t, r, m) => m.row + 1 },
            { data: 'date_time',        render: (d) => fmtDate(d) },
            { data: 'client_name' },
            { data: 'property_name' },
            { data: null,               render: (d) => refCell(d) },
            { data: 'escalation_type' },
            { data: null,               render: (d) => fmtValue(d) },
            { data: 'previous_total',   render: d => parseFloat(d).toLocaleString() },
            { data: 'new_total',        render: d => parseFloat(d).toLocaleString() },
            { data: 'effective_date',   render: (d) => fmtDate(d) },
            { data: null, render: (d) => {
                const pm = d.children.flatMap(c => c.approvals || []).find(a => a.step === 3 && a.action === 'approved');
                return pm ? pm.user : 'N/A';
            }}
        ],
        createdRow: function (row) {
            $(row).addClass('group-parent-row table-light').css('cursor', 'pointer');
        },
        order: [[0, 'desc']],
        pageLength: 25
    });

    attachToggle('#approvedEscalationsTable', approvedTable, true);

    // Reload on tab switch
    $('button[data-bs-target="#pending-escalations"]').on('shown.bs.tab', () => pendingTable.ajax.reload());
    $('button[data-bs-target="#approved-escalations"]').on('shown.bs.tab', () => approvedTable.ajax.reload());

    // ── Store escalation data for modals ─────────────────────────────
    window.escalationCache = {};

    window.cacheEscalation = function (id) {
        let row = null;
        [allTable, pendingTable, approvedTable].forEach(table => {
            table.rows().every(function () {
                const d = this.data();
                if (d && d.children) {
                    const found = d.children.find(c => c.id === id);
                    if (found) row = found;
                }
            });
        });
        if (row) window.escalationCache[id] = row;
        return row;
    };

    // ── Open Approval Modal ──────────────────────────────────────────
    window.openApprovalModal = function (id, action) {
        const data = cacheEscalation(id);
        if (!data) { toastr.error('Escalation data not found'); return; }

        $('#approval_escalation_id').val(id);
        $('#approval_action').val(action);
        $('#approval_comments').val('');
        $('#approval_documents').val('');

        $('#modal_landlord').text(data.client_name);
        $('#modal_property').text(data.property_name);
        $('#modal_unit').text(data.unit_code);
        $('#modal_type').text(data.escalation_type);
        $('#modal_value').text(data.escalation_type === 'Percentage' ? data.escalation_value + '%' : parseFloat(data.escalation_value).toLocaleString());
        $('#modal_previous').text(parseFloat(data.previous_amount).toLocaleString());
        $('#modal_new').text(parseFloat(data.new_amount).toLocaleString());

        let trailHtml = '';
        if (data.approvals && data.approvals.length > 0) {
            data.approvals.forEach(a => {
                const badgeClass = a.action === 'approved' ? 'success' : (a.action === 'rejected' ? 'danger' : 'primary');
                trailHtml += `<div class="d-flex align-items-start mb-2 p-2 border rounded">
                    <span class="badge bg-${badgeClass} me-2 mt-1">${a.label}</span>
                    <div>
                        <strong>${a.user}</strong> — <span class="badge bg-${badgeClass}">${a.action}</span>
                        <br><small class="text-muted">${a.date ? moment(a.date).format('MMM D, YYYY h:mm A') : ''}</small>
                        ${a.comments ? '<br><em>' + a.comments + '</em>' : ''}
                        ${a.documents ? '<br><a href="/storage/' + a.documents.split(',')[0] + '" target="_blank" class="text-primary"><span class="material-symbols-outlined" style="font-size:14px;">attach_file</span> View Document</a>' : ''}
                    </div>
                </div>`;
            });
        } else {
            trailHtml = '<p class="text-muted">No approval history yet.</p>';
        }
        $('#approvalTrailContent').html(trailHtml);

        const isPmStep = data.status === 'pending_pm';
        $('#documentsUploadSection').toggle(isPmStep);
        if (action === 'reject') {
            $('#commentsRequiredMark').show();
            $('#approveBtn').hide();
            $('#rejectBtn').show();
            $('#actionSectionTitle').text('Rejection');
        } else {
            $('#commentsRequiredMark').hide();
            $('#approveBtn').show();
            $('#rejectBtn').show();
            $('#actionSectionTitle').text('Your Action');
        }

        $('#approvalModal').modal('show');
    };

    // ── Submit Approval/Rejection ────────────────────────────────────
    window.submitApproval = function (action) {
        const id = $('#approval_escalation_id').val();
        const comments = $('#approval_comments').val();

        if (action === 'reject' && !comments.trim()) {
            toastr.error('Comments are required when rejecting.');
            return;
        }

        const formData = new FormData();
        formData.append('comments', comments);

        const fileInput = document.getElementById('approval_documents');
        if (fileInput && fileInput.files.length > 0) {
            for (let i = 0; i < fileInput.files.length; i++) {
                formData.append('documents[]', fileInput.files[i]);
            }
        }

        const url = action === 'approve'
            ? `/property/escalations/${id}/approve`
            : `/property/escalations/${id}/reject`;

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            beforeSend: function () {
                $('#approveBtn, #rejectBtn').prop('disabled', true);
            },
            success: function (res) {
                $('#approvalModal').modal('hide');
                toastr.success(res.message || 'Action completed successfully.');
                allTable.ajax.reload();
                pendingTable.ajax.reload();
                approvedTable.ajax.reload();
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.error || xhr.responseJSON?.message || 'Action failed.';
                toastr.error(msg);
            },
            complete: function () {
                $('#approveBtn, #rejectBtn').prop('disabled', false);
            }
        });
    };

    // ── View Detail Modal ────────────────────────────────────────────
    window.viewDetail = function (id) {
        const data = cacheEscalation(id);
        if (!data) { toastr.error('Escalation data not found'); return; }

        let html = `
            <div class="row mb-3">
                <div class="col-md-4"><small class="text-muted">Landlord</small><p class="fw-bold">${data.client_name}</p></div>
                <div class="col-md-4"><small class="text-muted">Property</small><p class="fw-bold">${data.property_name}</p></div>
                <div class="col-md-4"><small class="text-muted">Unit</small><p class="fw-bold">${data.unit_code}</p></div>
                <div class="col-md-3"><small class="text-muted">Lease Ref</small><p>${data.lease_ref}</p></div>
                <div class="col-md-3"><small class="text-muted">Scope</small><p>${data.scope}</p></div>
                <div class="col-md-3"><small class="text-muted">Type</small><p>${data.escalation_type}</p></div>
                <div class="col-md-3"><small class="text-muted">Value</small><p>${data.escalation_type === 'Percentage' ? data.escalation_value + '%' : parseFloat(data.escalation_value).toLocaleString()}</p></div>
                <div class="col-md-3"><small class="text-muted">Previous Amount</small><p>${parseFloat(data.previous_amount).toLocaleString()}</p></div>
                <div class="col-md-3"><small class="text-muted">New Amount</small><p class="fw-bold text-success">${parseFloat(data.new_amount).toLocaleString()}</p></div>
                <div class="col-md-3"><small class="text-muted">Effective Date</small><p>${data.effective_date ? moment(data.effective_date).format('MMM D, YYYY') : 'N/A'}</p></div>
                <div class="col-md-3"><small class="text-muted">Status</small><p><span class="badge bg-${data.status_badge}">${data.status_label}</span></p></div>
                <div class="col-md-6"><small class="text-muted">Created By</small><p>${data.created_by}</p></div>
                <div class="col-md-6"><small class="text-muted">Date Created</small><p>${data.date_time ? moment(data.date_time).format('MMM D, YYYY h:mm A') : ''}</p></div>
            </div>
            <h6 class="text-muted border-bottom pb-2">Approval Trail</h6>`;

        if (data.approvals && data.approvals.length > 0) {
            data.approvals.forEach(a => {
                const badgeClass = a.action === 'approved' ? 'success' : (a.action === 'rejected' ? 'danger' : 'primary');
                html += `<div class="d-flex align-items-start mb-2 p-2 border rounded">
                    <span class="badge bg-${badgeClass} me-2 mt-1">${a.label}</span>
                    <div>
                        <strong>${a.user}</strong> — <span class="badge bg-${badgeClass}">${a.action}</span>
                        <br><small class="text-muted">${a.date ? moment(a.date).format('MMM D, YYYY h:mm A') : ''}</small>
                        ${a.comments ? '<br><em>' + a.comments + '</em>' : ''}
                        ${a.documents ? '<br><a href="/storage/' + a.documents.split(',')[0] + '" target="_blank" class="text-primary"><span class="material-symbols-outlined" style="font-size:14px;">attach_file</span> View Document</a>' : ''}
                    </div>
                </div>`;
            });
        } else {
            html += '<p class="text-muted">No approval history.</p>';
        }

        $('#detailModalBody').html(html);
        $('#detailModal').modal('show');
    };
});
</script>
@endsection