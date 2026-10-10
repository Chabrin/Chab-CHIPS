@extends('layouts.main')
@section('content')

<style>
    .upload-zone {
        border: 2px dashed #d0d7de;
        border-radius: 8px;
        padding: 18px 24px;
        background: #f8fafc;
        transition: all 0.2s ease;
    }
    .upload-zone:hover {
        border-color: #0d6efd;
        background: #f0f7ff;
    }
    .badge-soft-warning {
        background-color: #fff8e6;
        color: #b78103;
        border: 1px solid #ffe399;
        font-weight: 500;
        padding: 4px 8px;
        border-radius: 6px;
    }
    .badge-soft-success {
        background-color: #eafbf0;
        color: #107e3e;
        border: 1px solid #b7f0c8;
        font-weight: 500;
        padding: 4px 8px;
        border-radius: 6px;
    }
</style>

<div class="page-body pt-3">
    <div class="container-fluid">
        
        <!-- Header Banner & Action Buttons -->
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="mb-1 fw-bold text-dark">Employee Attendance</h5>
                    <p class="text-muted small mb-0">Upload daily attendance sheets, track hours, and monitor staff presence.</p>
                </div>
                <div class="d-flex gap-2">
                    @permission('create_employee_attendances')
                    <a href="{{ route('attendance.sample.download') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:18px;">download</span>
                        <span>Sample Excel</span>
                    </a>
                    @endpermission
                    @permission('read_staff')
                    <button id="btn-export-attendance" class="btn btn-outline-success btn-sm d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:18px;">table_view</span>
                        <span>Export</span>
                    </button>
                    @endpermission
                </div>
            </div>
        </div>

        @permission('create_employee_attendances')
        <!-- Upload Card -->
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-body">
                <form id="attendance-upload-form" enctype="multipart/form-data">
                    @csrf
                    <div class="upload-zone d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-white p-2 rounded-circle shadow-sm border">
                                <span class="material-symbols-outlined text-primary" style="font-size:28px;">upload_file</span>
                            </div>
                            <div>
                                <label for="attendance_file" class="form-label mb-0 fw-semibold text-dark cursor-pointer">
                                    Choose an Excel or CSV file
                                </label>
                                <p class="text-muted small mb-0">Supported formats: .xlsx, .csv (Max 10MB)</p>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0">
                            <input type="file" name="attendance_file" id="attendance_file" class="form-control form-control-sm" accept=".xlsx,.csv" required>
                            <button type="submit" class="btn btn-primary btn-sm px-3 d-flex align-items-center text-nowrap" id="upload-attendance-btn">
                                <span id="upload-btn-text" class="d-flex align-items-center gap-1">
                                    <span class="material-symbols-outlined" style="font-size:16px;">cloud_upload</span>
                                    <span>Upload</span>
                                </span>
                                <span id="upload-btn-spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endpermission

        <!-- Table & Filter Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <!-- Filters Bar -->
                <div class="row g-2 mb-3 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Filter by Date</label>
                        <input type="date" id="filter-date" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Filter by Status</label>
                        <select id="filter-status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="1">Pending</option>
                            <option value="2">Approved</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" id="reset-filters" class="btn btn-reset-filters btn-sm w-100 d-flex align-items-center justify-content-center gap-1">
                            <span class="material-symbols-outlined" style="font-size:18px;">restart_alt</span>
                            <span>Reset Filters</span>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="attendance-table" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Employee Name</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Hours</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
$(document).ready(function() {
    let table = $('#attendance-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('ajax.attendance.list') }}",
            type: 'GET',
            data: function(d) {
                d.date = $('#filter-date').val();
                d.status = $('#filter-status').val();
            },
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
                data: 'staff', 
                title: 'Employee Name', 
                render: function(data, type, row) {
                    if (data && data.name) {
                        if (row.can_read && data.encrypted_id) {
                            return `<a href="/hr/employees/${data.encrypted_id}" class="text-primary fw-semibold text-decoration-none" target="_blank">${data.name}</a>`;
                        }
                        return `<span class="fw-semibold text-dark">${data.name}</span>`;
                    }
                    return '<span class="text-muted">—</span>';
                }
            },
            { 
                data: 'employee_attendance_timed', 
                title: 'Time In', 
                render: data => (Array.isArray(data) && data[0]?.time_in) ? `<span class="badge bg-light text-dark border">${data[0].time_in}</span>` : '<span class="text-muted">—</span>' 
            },
            { 
                data: 'employee_attendance_timed', 
                title: 'Time Out', 
                render: data => (Array.isArray(data) && data[0]?.time_out) ? `<span class="badge bg-light text-dark border">${data[0].time_out}</span>` : '<span class="text-muted">—</span>' 
            },
            { 
                data: 'employee_attendance_timed', 
                title: 'Hours', 
                render: data => (Array.isArray(data) && data[0]?.hours) ? `<strong>${data[0].hours} hrs</strong>` : '<span class="text-muted">—</span>' 
            },
            { 
                data: 'current_status_id', 
                title: 'Status', 
                render: function(data) {
                    if (data === 1) return '<span class="badge-soft-warning">Pending</span>';
                    if (data === 2) return '<span class="badge-soft-success">Approved</span>';
                    return data ? `<span class="badge bg-light text-secondary border">${data}</span>` : '<span class="text-muted">—</span>';
                }
            },
            { 
                data: 'created_by', 
                title: 'Created By', 
                render: data => (data && data.name) ? data.name : '<span class="text-muted">—</span>' 
            },
            { 
                data: 'date_time', 
                title: 'Date/Time', 
                render: data => data ? `<span class="text-secondary small">${data}</span>` : '<span class="text-muted">—</span>' 
            }
        ],
        paging: true,
        info: true,
        lengthChange: true,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search attendance...",
            emptyTable: "No attendance records found"
        }
    });

    // Filter Listeners
    $('#filter-date, #filter-status').on('change', function() {
        table.ajax.reload();
    });

    $('#reset-filters').on('click', function() {
        $('#filter-date').val('');
        $('#filter-status').val('');
        table.ajax.reload();
    });

    // Export Handler
$('#btn-export-attendance').on('click', function() {
    const params = $.param({
        date: $('#filter-date').val(),
        status: $('#filter-status').val()
    });
    window.location.href = "{{ route('attendance.export') }}?" + params;
});

    // Upload Handler
    $('#attendance-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        let fileInput = $('#attendance_file')[0];
        if (!fileInput.files.length) return;

        $('#upload-btn-text').addClass('d-none');
        $('#upload-btn-spinner').removeClass('d-none');
        $('#upload-attendance-btn').prop('disabled', true);
    
        let formData = new FormData(this);
        $.ajax({
            url: "{{ route('ajax.attendance.upload') }}",
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.success) {
                    $('#attendance_file').val('');
                    // the job runs in the background, so poll for its summary
                    let tries = 0;
                    let poll = setInterval(() => {
                        $.get("{{ route('ajax.attendance.upload.result') }}", function(r) {
                            if (!r.done && ++tries < 40) return;
                            clearInterval(poll);
                            table.ajax.reload(null, false);
                            if (!r.done) {
                                alert('Upload is still processing. Refresh the page in a moment to see the records.');
                                return;
                            }
                            let msg = 'Imported ' + r.imported + ' record(s), skipped ' + r.skipped + '.';
                            if (r.reasons && r.reasons.length) {
                                msg += '\n\n' + r.reasons.join('\n');
                                if (r.more > 0) msg += '\n...and ' + r.more + ' more (see the log).';
                            }
                            alert(msg);
                        });
                    }, 3000);
                } else {
                    alert(res.message || 'Upload failed.');
                }
            },
            error: function(xhr) {
                let msg = 'Upload failed.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            },
            complete: function() {
                $('#upload-btn-text').removeClass('d-none');
                $('#upload-btn-spinner').addClass('d-none');
                $('#upload-attendance-btn').prop('disabled', false);
            }
        });
    });
});
</script>
@endsection