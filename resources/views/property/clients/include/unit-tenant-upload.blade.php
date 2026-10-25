{{-- Units & tenants bulk upload sub-tab: download the template, upload, pick a batch to load its rows below, click a row for details. --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted small mb-0">Upload units and their tenants together for the property selected above. Only clean files are staged; nothing is created until you submit.</p>
    <div class="d-flex flex-shrink-0 ms-2 gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center" id="utuDownloadBtn" disabled>
            <span class="material-symbols-outlined me-1" style="font-size:18px;">download</span> Download Template
        </button>
        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="utuUploadBtn" data-bs-toggle="modal" data-bs-target="#utuUploadModal" disabled>
            <span class="material-symbols-outlined me-1" style="font-size:18px;">upload_file</span> Upload Units &amp; Tenants
        </button>
    </div>
</div>

<div class="table-responsive custom-scrollbar">
    <table id="utuBatchesTable" class="display" style="width:100%;">
        <thead>
            <tr>
                <th>#</th>
                <th>File</th>
                <th>Status</th>
                <th>Units</th>
                <th>Tenants</th>
                <th>Needs attention</th>
                <th>Uploaded</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<div class="card mt-4 d-none" id="utuDetailSection">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">Batch details: <span id="utuReviewTitle"></span> <span id="utuReviewStatus" class="badge ms-2"></span> <span class="text-muted small ms-2" id="utuCounts"></span></h6>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-danger btn-sm" id="utuDeleteBatchBtn">Delete Batch</button>
            <button type="button" class="btn btn-primary btn-sm" id="utuSubmitBatchBtn">
                <span class="spinner-border spinner-border-sm me-2 d-none" id="utuSubmitSpinner"></span>Submit Batch
            </button>
        </div>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-2">Each row is a unit with its tenant. Click a row to see its full details.</p>
        <div class="table-responsive"><table class="table table-sm table-hover align-middle" id="utuRowsTable"></table></div>
    </div>
</div>

<div class="modal fade" id="utuUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Units &amp; Tenants</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="utuUploadForm">
                <div class="modal-body">
                    <p class="small text-muted mb-2">Property: <strong id="utuUploadPropertyName"></strong></p>
                    <input type="file" class="form-control" id="utuFile" accept=".xlsx" required>
                    <small class="text-muted">Use the downloaded template (.xlsx). A file with any error is rejected and nothing is saved.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="utuUploadSubmit">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="utuUploadSpinner"></span>Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="utuRowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="utuRowTitle">Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="utuRowBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger btn-sm d-none" id="utuRowDelete">Delete Unit &amp; Tenant</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="utuEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="utuEditTitle">Edit row</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="utuEditForm">
                <div class="modal-body"><div class="row g-2" id="utuEditFields"></div></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        var utuBase = "{{ url('property/unit-tenant-upload') }}";
        var utuToken = '{{ csrf_token() }}';
        var utuCurrent = null;
        var utuProperty = null;
        var utuEdit = null;
        var utuBadge = {staged: 'bg-warning text-dark', submitting: 'bg-info', submitted: 'bg-success'};

        function esc(v) {
            return String(v === null || v === undefined ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function utuError(xhr, fallback) {
            toastr.error(esc((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : fallback));
        }

        function utuRejected(xhr) {
            var r = xhr.responseJSON || {};
            if (!r.errors || !r.errors.length) { utuError(xhr, 'Upload failed.'); return; }
            var rows = '';
            $.each(r.errors, function(i, e) {
                rows += '<tr><td class="text-nowrap">' + esc(e.sheet || 'file') + '</td><td>' + esc(e.row || '') + '</td><td>' + esc(e.unit_number || '') + '</td><td class="text-danger">' + esc(e.message) + '</td></tr>';
            });
            $('#utuUploadModal').modal('hide');
            $('#utuUploadForm')[0].reset();
            Swal.fire({icon: 'error', width: 900, title: 'Fix these and upload again',
                html: '<p class="small text-muted">' + esc(r.message) + '</p><div style="max-height:380px;overflow:auto;text-align:left;"><table class="table table-sm"><thead><tr><th>Sheet</th><th>Row</th><th>Unit</th><th>Problem</th></tr></thead><tbody>' + rows + '</tbody></table></div>',
                confirmButtonText: 'Close'});
        }

        var utuTable = $('#utuBatchesTable').DataTable({
            data: [],
            order: [[0, 'desc']],
            columns: [
                {data: 'id'},
                {data: 'file_name', render: function(n) { return esc(n); }},
                {data: 'status', render: function(s) { return '<span class="badge ' + (utuBadge[s] || 'bg-secondary') + '">' + esc(s) + '</span>'; }},
                {data: null, render: function(r) { return r.total_units + (r.submitted_units ? ' (' + r.submitted_units + ' submitted)' : ''); }},
                {data: 'total_tenants'},
                {data: 'error_rows', render: function(n) { return n ? '<span class="text-danger fw-semibold">' + n + '</span>' : '0'; }},
                {data: 'created_at'},
                {data: 'id', orderable: false, render: function(id) { return '<a href="#" class="text-primary utu-review-btn" data-id="' + id + '">View</a>'; }}
            ],
            createdRow: function(row) { $(row).css('cursor', 'pointer'); },
            language: {emptyTable: 'Select a property above to see its uploads'}
        });

        var utuListSeq = 0;

        function utuLoadBatches() {
            var pid = $('#tenantPropertySelect').val();
            var seq = ++utuListSeq;
            if (!pid) { utuTable.clear().draw(); utuClose(); return; }
            $.getJSON(utuBase + '/batches', {property_id: pid}, function(r) {
                if (seq !== utuListSeq) { return; }
                utuTable.clear().rows.add(r.data).draw();
                if (utuCurrent) { $('#utuBatchesTable tbody tr').each(function() { if (utuTable.row(this).data().id === utuCurrent.batch.id) { $(this).addClass('table-active'); } }); }
            }).fail(function(xhr) { utuError(xhr, 'Failed to load uploads.'); });
        }

        function utuSync() {
            var $sel = $('#tenantPropertySelect');
            $('#utuDownloadBtn, #utuUploadBtn').prop('disabled', !$sel.val());
            $('#utuUploadPropertyName').text($sel.find('option:selected').text());
            if ($sel.val() !== utuProperty) {
                utuClose();
                utuProperty = $sel.val();
            }
            utuLoadBatches();
        }

        $('#tenantPropertySelect').on('change', utuSync);
        $('#tenants-bulk-upload-subtab').on('shown.bs.tab', function() { utuTable.columns.adjust(); utuSync(); });

        $('#utuDownloadBtn').on('click', function() {
            window.location.href = utuBase + '/template?property_id=' + $('#tenantPropertySelect').val();
        });

        $('#utuUploadForm').on('submit', function(e) {
            e.preventDefault();
            var file = $('#utuFile')[0].files[0];
            if (!file) { return; }
            var fd = new FormData();
            fd.append('_token', utuToken);
            fd.append('property_id', $('#tenantPropertySelect').val());
            fd.append('bulk_file', file);
            $('#utuUploadSubmit').prop('disabled', true);
            $('#utuUploadSpinner').removeClass('d-none');
            $.ajax({url: utuBase + '/import', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
                success: function(r) {
                    toastr.success(r.message);
                    $('#utuUploadModal').modal('hide');
                    $('#utuUploadForm')[0].reset();
                    utuOpen(r.batch_id);
                },
                error: function(xhr) { utuRejected(xhr); },
                complete: function() { $('#utuUploadSubmit').prop('disabled', false); $('#utuUploadSpinner').addClass('d-none'); }
            });
        });

        function utuClose() {
            utuCurrent = null;
            $('#utuDetailSection').addClass('d-none');
            $('#utuBatchesTable tbody tr').removeClass('table-active');
        }

        function utuRefresh(id) {
            if (utuCurrent && utuCurrent.batch.id === id) { utuOpen(id); }
        }

        function utuOpen(id) {
            $.getJSON(utuBase + '/batches/' + id, function(r) {
                utuCurrent = r;
                utuRender();
                utuLoadBatches();
                var $s = $('#utuDetailSection').removeClass('d-none');
                $s[0].scrollIntoView({behavior: 'smooth', block: 'nearest'});
            }).fail(function(xhr) { utuError(xhr, 'Failed to load the batch.'); });
        }

        function utuState(row) {
            if (row.state === 'submitted') { return '<span class="badge bg-success">Submitted</span>'; }
            if (row.state === 'error') { return '<span class="badge bg-danger">Error</span><div class="small text-danger">' + esc(row.error) + '</div>'; }
            return '<span class="badge bg-primary">Ready</span>';
        }

        function utuEditable(row) {
            return utuCurrent.batch.status === 'staged' && row.state !== 'submitted';
        }

        var utuPairs = [];

        function utuBuildPairs() {
            var byUnit = {};
            $.each(utuCurrent.tenants, function(i, t) { byUnit[(t.unit_number || '').toLowerCase()] = t; });
            var used = {};
            utuPairs = [];
            $.each(utuCurrent.units, function(i, u) {
                var t = byUnit[(u.unit_number || '').toLowerCase()] || null;
                if (t) { used[t.id] = true; }
                utuPairs.push({unit: u, tenant: t});
            });
            $.each(utuCurrent.tenants, function(i, t) { if (!used[t.id]) { utuPairs.push({unit: null, tenant: t}); } });
        }

        function utuPairState(pair) {
            var parts = [pair.unit, pair.tenant].filter(Boolean);
            var errors = parts.filter(function(r) { return r.state === 'error'; }).map(function(r) { return r.error; });
            errors = errors.filter(function(e, i) { return errors.indexOf(e) === i; });
            if (errors.length) { return {state: 'error', error: errors.join('; ')}; }
            if (parts.every(function(r) { return r.state === 'submitted'; })) { return {state: 'submitted'}; }
            return {state: 'ready'};
        }

        function utuRender() {
            var b = utuCurrent.batch;
            $('#utuReviewTitle').text(b.file_name);
            $('#utuReviewStatus').attr('class', 'badge ms-2 ' + (utuBadge[b.status] || 'bg-secondary')).text(b.status);
            $('#utuCounts').text(utuCurrent.units.length + ' unit(s) · ' + utuCurrent.tenants.length + ' tenant(s)');
            utuBuildPairs();

            var h = '<thead><tr><th>Row</th><th>Unit No</th><th>Unit Code</th><th>Topology</th><th>Rent</th><th>Tenant</th><th>Type</th><th>Mobile</th><th>Lease start</th><th>Lease end</th><th>Status</th></tr></thead><tbody>';
            $.each(utuPairs, function(i, pair) {
                var u = pair.unit || {};
                var t = pair.tenant || {};
                var type = pair.tenant ? (t.tenant_type || (t.sheet === 'corporate' ? 'COMPANY' : 'INDIVIDUAL')) : '';
                h += '<tr class="utu-row" data-index="' + i + '" style="cursor:pointer;"><td>' + (u.row_no || t.row_no) + '</td><td>' + esc(u.unit_number || t.unit_number) + '</td><td>' + esc(u.unit_code) + '</td><td>' + esc(u.topology) + '</td><td>' + esc(u.rent_amount) + '</td><td>' + (pair.tenant ? esc(t.names || t.company_name) : '<span class="text-muted">Vacant</span>') + '</td><td>' + esc(type) + '</td><td>' + esc(t.mobile_no) + '</td><td>' + esc(t.lease_start_date) + '</td><td>' + (pair.tenant ? (t.lease_end_date ? esc(t.lease_end_date) : '<span class="text-muted">Open-ended</span>') : '') + '</td><td>' + utuState(utuPairState(pair)) + '</td></tr>';
            });
            $('#utuRowsTable').html(h + '</tbody>');

            $('#utuSubmitBatchBtn').prop('disabled', b.status !== 'staged');
            $('#utuDeleteBatchBtn').prop('disabled', b.status === 'submitted' || b.status === 'submitting' || utuCurrent.units.concat(utuCurrent.tenants).some(function(r) { return r.state === 'submitted'; }));
        }

        $('#utuBatchesTable').on('click', 'tbody tr', function(e) {
            e.preventDefault();
            var data = utuTable.row(this).data();
            if (data) { utuOpen(data.id); }
        });

        function utuRow(kind, id) {
            var list = kind === 'units' ? utuCurrent.units : utuCurrent.tenants;
            return list.filter(function(r) { return r.id == id; })[0];
        }

        function utuLabel(field) {
            return field.replace(/_/g, ' ');
        }

        function utuSection(title, kind, row, buttons) {
            var html = '<div class="d-flex justify-content-between align-items-center mt-3 mb-2"><h6 class="mb-0">' + title + '</h6><div class="d-flex gap-2">' + buttons + '</div></div><dl class="row mb-0">';
            $.each(utuCurrent.columns[kind], function(field, optional) {
                var val = row[field];
                html += '<dt class="col-sm-4 text-capitalize text-muted">' + utuLabel(field) + (optional ? '' : ' *') + '</dt><dd class="col-sm-8">' + (val === null || val === undefined || val === '' ? '<span class="text-muted">-</span>' : esc(val)) + '</dd>';
            });
            html += '<dt class="col-sm-4 text-muted">Row in file</dt><dd class="col-sm-8">' + row.row_no + '</dd>';
            $.each({unit_id: 'Created unit id', tenant_id: 'Created tenant id', lease_id: 'Created lease id'}, function(key, label) {
                if (row[key]) { html += '<dt class="col-sm-4 text-muted">' + label + '</dt><dd class="col-sm-8">' + row[key] + '</dd>'; }
            });
            return html + '</dl>';
        }

        $('#utuDetailSection').on('click', '.utu-row', function() {
            var pair = utuPairs[$(this).data('index')];
            var u = pair.unit;
            var t = pair.tenant;
            utuEdit = {unitId: u ? u.id : null};
            var unitBtns = u && utuEditable(u) ? '<button type="button" class="btn btn-outline-primary btn-sm utu-modal-edit" data-kind="units" data-id="' + u.id + '">Edit unit</button>' : '';
            var html = '<div class="mb-1">' + utuState(utuPairState(pair)) + '</div>';
            if (u) { html += utuSection('Unit', 'units', u, unitBtns); }
            if (t) {
                var tenantBtns = utuEditable(t) ? '<button type="button" class="btn btn-outline-primary btn-sm utu-modal-edit" data-kind="' + t.sheet + '" data-id="' + t.id + '">Edit tenant</button><button type="button" class="btn btn-outline-danger btn-sm utu-modal-del-tenant" data-id="' + t.id + '">Delete tenant</button>' : '';
                html += utuSection('Tenant (' + t.sheet + ')', t.sheet, t, tenantBtns);
            } else {
                html += '<p class="text-muted mt-3 mb-0">No tenant: this unit is created vacant.</p>';
            }
            $('#utuRowTitle').text(u ? 'Unit ' + (u.unit_number || '') : 'Tenant ' + (t.names || t.company_name || ''));
            $('#utuRowBody').html(html);
            $('#utuRowDelete').toggleClass('d-none', !(u && utuEditable(u)));
            $('#utuRowModal').modal('show');
        });

        function utuOpenEdit(kind, id) {
            var row = utuRow(kind, id);
            utuEdit = {kind: kind, id: row.id};
            var html = '';
            $.each(utuCurrent.columns[kind], function(field, optional) {
                if (kind !== 'units' && field === 'unit_number') { return; }
                var label = utuLabel(field) + (optional ? '' : ' *');
                var val = row[field] === null || row[field] === undefined ? '' : row[field];
                var input;
                if (utuCurrent.options[field]) {
                    input = '<select class="form-select form-select-sm" name="' + field + '"><option value=""></option>';
                    $.each(utuCurrent.options[field], function(i, o) { input += '<option' + (o === val ? ' selected' : '') + '>' + esc(o) + '</option>'; });
                    input += '</select>';
                } else {
                    input = '<input class="form-control form-control-sm" name="' + field + '" value="' + esc(val) + '"' + (field === 'lease_start_date' ? ' placeholder="YYYY-MM-DD"' : (field === 'lease_end_date' ? ' placeholder="YYYY-MM-DD (blank = open-ended)"' : '')) + '>';
                }
                html += '<div class="col-md-4"><label class="form-label small mb-1 text-capitalize">' + label + '</label>' + input + '</div>';
            });
            $('#utuEditFields').html(html);
            $('#utuEditTitle').text('Edit row ' + row.row_no);
            var $rowModal = $('#utuRowModal');
            if ($rowModal.hasClass('show')) {
                $rowModal.one('hidden.bs.modal', function() { $('#utuEditModal').modal('show'); });
                $rowModal.modal('hide');
            } else {
                $('#utuEditModal').modal('show');
            }
        }

        $('#utuRowModal').on('click', '.utu-modal-edit', function() { utuOpenEdit($(this).data('kind'), $(this).data('id')); });
        $('#utuRowModal').on('click', '.utu-modal-del-tenant', function() { utuDeleteRow('tenants', $(this).data('id')); });
        $('#utuRowDelete').on('click', function() { utuDeleteRow('units', utuEdit.unitId); });

        $('#utuEditForm').on('submit', function(e) {
            e.preventDefault();
            var data = {_token: utuToken};
            var bid = utuCurrent.batch.id;
            $.each($(this).serializeArray(), function(i, f) { data[f.name] = f.value; });
            $.ajax({url: utuBase + '/' + (utuEdit.kind === 'units' ? 'units' : 'tenants') + '/' + utuEdit.id, type: 'PUT', data: data, dataType: 'json',
                success: function(r) { toastr.success(r.message); $('#utuEditModal').modal('hide'); utuRefresh(bid); },
                error: function(xhr) { utuError(xhr, 'Failed to update the row.'); }
            });
        });

        function utuConfirm(title, text, button, url, method, after) {
            Swal.fire({title: title, text: text, icon: 'warning', showCancelButton: true, confirmButtonColor: '#3598db', cancelButtonColor: '#8a94a3', confirmButtonText: button}).then(function(res) {
                if (!res.isConfirmed) { return; }
                $.ajax({url: url, type: method, data: {_token: utuToken}, dataType: 'json',
                    success: function(r) { toastr.success(r.message); after(); },
                    error: function(xhr) { utuError(xhr, 'Action failed.'); }
                });
            });
        }

        function utuDeleteRow(kind, id) {
            var bid = utuCurrent.batch.id;
            utuConfirm('Delete this row?', kind === 'units' ? 'It is permanently deleted along with its tenant row. Nothing is created.' : 'It is permanently deleted. The unit stays and is created vacant.', 'Yes, Delete',
                utuBase + '/' + (kind === 'units' ? 'units' : 'tenants') + '/' + id, 'DELETE', function() { $('#utuRowModal').modal('hide'); utuRefresh(bid); });
        }


        $('#utuSubmitBatchBtn').on('click', function() {
            Swal.fire({title: 'Submit this batch?', text: 'Ready rows create the units, tenants and 1-year leases. Rows that fail stay staged.', icon: 'question', showCancelButton: true, confirmButtonColor: '#3598db', confirmButtonText: 'Submit'}).then(function(res) {
                if (!res.isConfirmed) { return; }
                var bid = utuCurrent.batch.id;
                $('#utuSubmitSpinner').removeClass('d-none');
                $('#utuSubmitBatchBtn').prop('disabled', true);
                $.ajax({url: utuBase + '/batches/' + bid + '/submit', type: 'POST', data: {_token: utuToken}, dataType: 'json',
                    success: function(r) { toastr.success(r.message); },
                    error: function(xhr) { utuError(xhr, 'Submit failed.'); },
                    complete: function() { $('#utuSubmitSpinner').addClass('d-none'); utuRefresh(bid); utuLoadBatches(); }
                });
            });
        });

        $('#utuDeleteBatchBtn').on('click', function() {
            utuConfirm('Delete this batch?', 'The batch and all its rows are permanently deleted. A batch with submitted rows cannot be deleted.', 'Yes, Delete', utuBase + '/batches/' + utuCurrent.batch.id, 'DELETE', function() { utuClose(); utuLoadBatches(); });
        });
    });
</script>
