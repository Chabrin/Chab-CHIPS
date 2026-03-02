@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">NSSF Rates Settings</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_nssf_rates')
                                <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addNssfRateModal">
                                    <span class="material-symbols-outlined">add_circle</span>
                                    <span class="ms-1">Add NSSF Rate</span>
                                </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="nssf-rates-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>From Date</th>
                                        <th>Base</th>
                                        <th>Employee %</th>
                                        <th>Employer %</th>
                                        <th>Max Contribution</th>
                                        <th>Taxable</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Date/Time</th>
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
<!-- Add/Edit NSSF Rate Modal -->
<div class="modal fade" id="addNssfRateModal" tabindex="-1" aria-labelledby="addNssfRateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addNssfRateModalLabel">Add NSSF Rate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="nssfRateForm">
                    <input type="hidden" name="id" id="nssfRateId">
                    <div class="mb-3">
                        <label for="from_date" class="form-label">From Date <span style="color:red">*</span></label>
                        <input type="date" class="form-control" name="from_date" id="from_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="base_id" class="form-label">Base</label>
                        <input type="number" class="form-control" name="base_id" id="base_id" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="employee_perc" class="form-label">Employee % <span style="color:red">*</span></label>
                        <input type="number" class="form-control" name="employee_perc" id="employee_perc" required step="0.01" min="0" max="100">
                    </div>
                    <div class="mb-3">
                        <label for="employer_perc" class="form-label">Employer % <span style="color:red">*</span></label>
                        <input type="number" class="form-control" name="employer_perc" id="employer_perc" required step="0.01" min="0" max="100">
                    </div>
                    <div class="mb-3">
                        <label for="maximum_contribution" class="form-label">Max Contribution <span style="color:red">*</span></label>
                        <input type="number" class="form-control" name="maximum_contribution" id="maximum_contribution" required step="0.01" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="taxable" class="form-label">Taxable <span style="color:red">*</span></label>
                        <select class="form-select" name="taxable" id="taxable" required>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        @permission('create_nssf_rates')
                        <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="saveNssfRateBtn">
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
        let table = $('#nssf-rates-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/hr/nssf/rates/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'nssfrates'
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
                { data: 'from_date', title: 'From Date', render: data => data ? data : '-' },
                { data: 'base_id', title: 'Base', render: data => data !== null ? data : '-' },
                { data: 'employee_perc', title: 'Employee %', render: data => data !== null ? data : '-' },
                { data: 'employer_perc', title: 'Employer %', render: data => data !== null ? data : '-' },
                { data: 'maximum_contribution', title: 'Max Contribution', render: data => data !== null ? data : '-' },
                { data: 'taxable', title: 'Taxable', render: data => data == 1 ? 'Yes' : 'No' },
                { data: 'current_status_id', title: 'Status', render: data => data == 5 ? 'Active' : 'Inactive' },
                { data: 'created_by', title: 'Created By', render: data => data && data.name ? data.name : '-' },
                { data: 'date_time', title: 'Date/Time', render: data => data ? new Date(data).toLocaleString() : '-' },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        if (row.can_update) {
                            buttons += `
                                <button class="btn btn-xs btn-light edit-nssf-rate" 
                                    data-id="${row.id}" 
                                    data-from_date="${row.from_date}" 
                                    data-base_id="${row.base_id}" 
                                    data-employee_perc="${row.employee_perc}" 
                                    data-employer_perc="${row.employer_perc}" 
                                    data-maximum_contribution="${row.maximum_contribution}" 
                                    data-taxable="${row.taxable}" 
                                    style="padding:1px 2px;">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </button>`;
                        }
                        if (row.can_delete) {
                            buttons += `
                                <button class="btn btn-xs btn-light delete-nssf-rate" 
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

        $('#nssfRateForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#nssfRateId').val();
            var url = id ? "{{ url('/ajax/hr/nssf/rates') }}/" + id : "{{ url('/ajax/hr/nssf/rates') }}";
            var method = 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveNssfRateBtn');
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
                        $('#addNssfRateModal').modal('hide');
                        $('#nssfRateForm')[0].reset();
                        $('#nssfRateId').val('');
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

               $(document).on('click', '.edit-nssf-rate', function() {
            $('#nssfRateId').val($(this).data('id'));
            $('#rate').val($(this).data('rate'));
            $('#from_date').val($(this).data('from_date'));
            $('#base_id').val($(this).data('base_id'));
            $('#employee_perc').val($(this).data('employee_perc'));
            $('#employer_perc').val($(this).data('employer_perc'));
            $('#maximum_contribution').val($(this).data('maximum_contribution'));
            $('#taxable').val($(this).data('taxable'));
            $('#addNssfRateModal').modal('show');
        });

        $(document).on('click', '.delete-nssf-rate', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();

            if (!confirm('Are you sure you want to delete this NSSF rate?')) {
                return;
            }

            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ url('/ajax/hr/nssf/rates') }}/" + $btn.data('id'),
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

        $('#addNssfRateModal').on('hidden.bs.modal', function() {
            $('#nssfRateForm')[0].reset();
            $('#nssfRateId').val('');
        });
    });
</script>
