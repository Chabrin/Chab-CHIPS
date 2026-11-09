@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">SHA Rates Settings</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_shif_rates')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addShifRateModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add SHA Rate</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="shif-rates-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Rate (%)</th>
                                        <th>Date/time</th>
                                        <th>From Date</th>
                                        <th>Created By</th>
                                        <th>Current Status</th>
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
<!-- Add/Edit SHA Rate Modal -->
<div class="modal fade" id="addShifRateModal" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addShifRateModalLabel">Add SHA Rate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="shifRateForm">
                    <input type="hidden" name="id" id="shifRateId">
                    <div class="mb-3">
                        <label for="from_date" class="form-label">From Date <span style="color:red">*</span></label>
                        <input type="date" class="form-control" name="from_date" id="from_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="rate" class="form-label">Rate (%) <span style="color:red">*</span></label>
                        <input type="number" class="form-control" name="rate" id="rate" required placeholder="Enter rate" step="0.01" min="0">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        @permission('create_shif_rates')
                        <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="saveShifRateBtn">
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

<script>
    // use toastr for notifications
    $(document).ready(function() {
        let table = $('#shif-rates-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/hr/shif/rates/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'sharates'
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
                { data: 'rate', title: 'Rate' },
                { 
                    data: 'date_time', 
                    title: 'Date/time',
                    render: function(data) {
                        return data ? new Date(data).toLocaleString() : '-';
                    }
                },
                { 
                    data: 'from_date', 
                    title: 'From Date',
                    render: function(data) {
                        return data ? data.substring(0, 10) : '-';
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
                    title: 'Current Status',
                    render: function(data) {
                        return data == 5 ? 'Active' : 'Inactive';
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    title: 'Action',
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        if (row.can_update) {
                            buttons += `
                                <button class="btn btn-xs btn-light edit-shif-rate" 
                                    data-id="${row.id}" 
                                    data-rate="${row.rate}" 
                                    data-from_date="${row.from_date ? row.from_date.substring(0, 10) : ''}" 
                                    style="padding:1px 2px;">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </button>`;
                        }
                        if (row.can_delete) {
                            buttons += `
                                <button class="btn btn-xs btn-light delete-shif-rate" 
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
    
        $('#shifRateForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#shifRateId').val();
            var url = id ? "{{ url('/ajax/hr/shif/rates') }}/" + id : "{{ url('/ajax/hr/shif/rates') }}";
            var method = 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveShifRateBtn');
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
                        $('#addShifRateModal').modal('hide');
                        $('#shifRateForm')[0].reset();
                        $('#shifRateId').val('');
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
    
        $(document).on('click', '.edit-shif-rate', function() {
            $('#shifRateId').val($(this).data('id'));
            $('#from_date').val($(this).data('from_date'));
            $('#rate').val($(this).data('rate'));
            $('#addShifRateModal').modal('show');
        });
    
        $(document).on('click', '.delete-shif-rate', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();
    
            if (!confirm('Are you sure you want to delete this SHA rate?')) {
                return;
            }
    
            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);
    
            $.ajax({
                url: "{{ url('/ajax/hr/shif/rates') }}/" + $btn.data('id'),
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
    
        $('#addShifRateModal').on('hidden.bs.modal', function() {
            $('#shifRateForm')[0].reset();
            $('#shifRateId').val('');
        });
    });
</script>
@endsection
