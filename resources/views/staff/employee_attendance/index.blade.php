@extends('layouts.main')
@section('content')


<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Employee Attendance</h5>
                @permission('create_employee_attendances')
                <a href="{{ route('attendance.sample.download') }}" class="btn btn-outline-primary btn-sm">
                    <span class="material-symbols-outlined" style="font-size:18px;">download</span>
                    Download Sample
                </a>
                @endpermission
            </div>
            <div class="card-body">
                @permission('create_employee_attendances')
                <form id="attendance-upload-form" enctype="multipart/form-data" class="mb-3">
                    @csrf
                    <div class="row g-2 align-items-center">
                        <div class="col-12">
                            <input type="file" name="attendance_file" id="attendance_file" class="form-control" accept=".xlsx,.csv" required>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm" id="upload-attendance-btn">
                                <span id="upload-btn-text" class="d-flex align-items-center"><span class="material-symbols-outlined me-3">upload</span> Upload Attendance</span>
                                <span id="upload-btn-spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                </form>
                @endpermission
                <div class="table-responsive">
                    <table class="table table-bordered" id="attendance-table">
                        <thead>
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
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(document).ready(function() {
    let table = $('#attendance-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('ajax.attendance.list') }}",
            type: 'GET',
            dataSrc: 'records'
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
            { 
                data: 'staff', 
                title: 'Employee Name', 
                render: function(data, type, row) {
                    if (data && data.name) {
                        if (row.can_read && data.encrypted_id) {
                            return `<a href="/hr/employees/${data.encrypted_id}" target="_blank">${data.name}</a>`;
                        }
                        return data.name;
                    }
                    return '-';
                }
            },
            { 
                data: 'employee_attendance_timed', 
                title: 'Time In', 
                render: data => Array.isArray(data) && data[0] && data[0].time_in ? data[0].time_in : '-' 
            },
            { 
                data: 'employee_attendance_timed', 
                title: 'Time Out', 
                render: data => Array.isArray(data) && data[0] && data[0].time_out ? data[0].time_out : '-' 
            },
            { 
                data: 'employee_attendance_timed', 
                title: 'Hours', 
                render: data => Array.isArray(data) && data[0] && data[0].hours ? data[0].hours : '-' 
            },
            { 
                data: 'current_status_id', 
                title: 'Status', 
                render: data => {
                    if (data === 1) return 'Pending';
                    return data || '-';
                }
            },
            { 
                data: 'created_by', 
                title: 'Created By', 
                render: data => data && data.name ? data.name : '-' 
            },
            { 
                data: 'date_time', 
                title: 'Date/Time', 
                render: data => data || '-' 
            }
        ],
        paging: true,
        info: true,
        lengthChange: true
    });

    // Reload table after upload
    $('#attendance-upload-form').on('submit', function(e) {
        e.preventDefault();
        // Show spinner
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
                    alert('Attendance uploaded successfully!');
                    table.ajax.reload();
                } else {
                    alert(res.error || 'Upload failed.');
                }
            },
            error: function() {
                alert('Upload failed.');
            },
            complete: function() {
                // Hide spinner and enable button
                $('#upload-btn-text').removeClass('d-none');
                $('#upload-btn-spinner').addClass('d-none');
                $('#upload-attendance-btn').prop('disabled', false);
            }
        });
    });
});
</script>
@endsection