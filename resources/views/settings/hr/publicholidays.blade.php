@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Public Holidays Settings</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_public_holiday')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addPublicHolidayModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Public Holiday</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="publicholidays-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Title</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Pay Rate (%)</th>
                                        <th>Recurrent</th>
                                        <th>Created By</th>
                                        <th>Current Status</th>
                                        <th>Created Date/Time</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data loaded via DataTables AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Add/Edit Public Holiday Modal -->
<div class="modal fade" id="addPublicHolidayModal" tabindex="-1" aria-labelledby="addPublicHolidayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPublicHolidayModalLabel">Public Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="publicHolidayForm">
                    <input type="hidden" name="id" id="publicHolidayId">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="title" id="title" required placeholder="Enter holiday title">
                    </div>
                    <div class="mb-3">
                        <label for="start_date" class="form-label">Start Date <span style="color:red">*</span></label>
                        <input type="date" class="form-control" name="start_date" id="start_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="end_date" class="form-label">End Date <span style="color:red">*</span></label>
                        <input type="date" class="form-control" name="end_date" id="end_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="pay_rate" class="form-label">Pay Rate (%) <span style="color:red">*</span></label>
                        <input type="number" class="form-control" name="pay_rate" id="pay_rate" required min="0" max="100" step="0.01" placeholder="Enter pay rate">
                    </div>
                    <div class="mb-3">
                        <label for="recurrent" class="form-label">Recurrent <span style="color:red">*</span></label>
                        <select class="form-select" name="recurrent" id="recurrent" required>
                            <option value="" disabled selected>Select</option>
                            <option value="1">Yes</option>
                            <option value="2">No</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        @permission('create_public_holiday')
                        <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="savePublicHolidayBtn">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Save</span>
                        </button>
                        @endpermission
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    // use toastr for notifications
    $(document).ready(function() {
        let table = $('#publicholidays-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/hr/publicholidays/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'publicholidays'
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { data: 'title', title: 'Title' },
                { 
                    data: 'start_date', 
                    title: 'Start Date',
                    render: function(data) {
                        return data ? new Date(data).toLocaleDateString() : '-';
                    }
                },
                { 
                    data: 'end_date', 
                    title: 'End Date',
                    render: function(data) {
                        return data ? new Date(data).toLocaleDateString() : '-';
                    }
                },
                { 
                    data: 'pay_rate', 
                    title: 'Pay Rate (%)',
                    render: function(data) {
                        return data ? data + '%' : '-';
                    }
                },
                {
                    data: 'recurrent',
                    title: 'Recurrent',
                    render: function(data) {
                        return data == 1 ? 'Yes' : 'No';
                    }
                },
                { 
                    data: 'created_by',
                    title: 'Created By',
                    render: function(data) {
                        return data && data.name ? data.name : '-';
                    }
                },
                {
                    data: 'current_status_id',
                    render: function(data, type, row) {
                        // Map status id to name
                        const status = {
                            "1": "Inactive",
                            "5": "Active"
                        };
                        return status[row.current_status_id] || row.current_status_id;
                    }
                },
                { 
                    data: 'created_at',
                    title: 'Created Date/Time',
                    render: function(data) {
                        return data ? new Date(data).toLocaleString() : '-';
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        if (row.can_update) {
                            buttons += `
                                <button class="btn btn-xs btn-light edit-publicholiday" 
                                    data-id="${row.id}" 
                                    data-title="${row.title}" 
                                    data-start_date="${row.start_date}" 
                                    data-end_date="${row.end_date}" 
                                    data-pay_rate="${row.pay_rate}" 
                                    data-recurrent="${row.recurrent}" 
                                    style="padding:1px 2px;">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </button>`;
                        }
                        if (row.can_delete) {
                            buttons += `
                                <button class="btn btn-xs btn-light delete-publicholiday" 
                                    data-id="${row.id}" style="padding:1px 2px;">
                                    <span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>
                                    <span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>
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

        $('#publicHolidayForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#publicHolidayId').val();
            var url = id ? "{{ url('/ajax/hr/publicholidays') }}/" + id : "{{ url('/ajax/hr/publicholidays') }}";
            var method = 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#savePublicHolidayBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();

            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);

            $.ajax({
                url: url,
                type: method,
                data: formData,
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);

                    if (res.success) {
                        toastr.success('Saved successfully');
                        $('#addPublicHolidayModal').modal('hide');
                        $('#publicHolidayForm')[0].reset();
                        $('#publicHolidayId').val('');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving');
                    }
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);

                    let msg = 'Validation error';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).join('<br>');
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $(document).on('click', '.edit-publicholiday', function() {
            $('#publicHolidayId').val($(this).data('id'));
            $('#title').val($(this).data('title'));
        
            let startDate = $(this).data('start_date');
            let endDate = $(this).data('end_date');
            if (startDate) {
                startDate = startDate.substring(0, 10); // Converts "2023-12-25T00:00:00.000000Z" to "2023-12-25"
            }
            if (endDate) {
                endDate = endDate.substring(0, 10);
            }
            $('#start_date').val(startDate);
            $('#end_date').val(endDate);
        
            $('#pay_rate').val($(this).data('pay_rate'));
        
            // Map "yes"/"no" to "1"/"2" for dropdown
            let recurrent = $(this).data('recurrent');
            if (recurrent === "yes" || recurrent === 1) recurrent = "1";
            if (recurrent === "no" || recurrent === 2) recurrent = "2";
            $('#recurrent').val(recurrent);
        
            $('#addPublicHolidayModal').modal('show');
        });

        $(document).on('click', '.delete-publicholiday', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();

            if (!confirm('Are you sure you want to delete this public holiday?')) {
                return;
            }

            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ url('/ajax/hr/publicholidays') }}/" + $btn.data('id'),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);

                    if (res.success) {
                        toastr.success('Deleted');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error deleting');
                    }
                },
                error: function(xhr) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);

                    let msg = 'Delete error';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $('#addPublicHolidayModal').on('hidden.bs.modal', function() {
            $('#publicHolidayForm')[0].reset();
            $('#publicHolidayId').val('');
        });
    });
</script>
