@extends('layouts.main')
@section('content')
<style>
    .spin {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
</style>
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">HELB Loan Deductions</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_helb_loans')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addHelbModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add HELB Loan</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="helb-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Staff Number</th>
                                        <th>Staff Name</th>
                                        <th>Loan Balance</th>
                                        <th>Monthly Deduction</th>
                                        <th>Start Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
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

<!-- Add/Edit HELB Modal -->
<div class="modal fade" id="addHelbModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="helbModalTitle">Add HELB Loan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="helbForm">
                    <input type="hidden" id="helbId">
                    <div class="mb-3" id="staffSelectDiv">
                        <label for="staff_id" class="form-label">Staff Member <span style="color:red">*</span></label>
                        <select class="form-control" id="staff_id" style="width:100%;">
                            <option value="">Select Staff</option>
                        </select>
                        <small class="text-muted">Select the staff member for this loan</small>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label for="loan_balance" class="form-label">Loan Balance <span style="color:red">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="loan_balance" required>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="monthly_deduction" class="form-label">Monthly Deduction <span style="color:red">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="monthly_deduction" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="start_deduction_date" class="form-label">Start Deduction Date <span style="color:red">*</span></label>
                        <input type="date" class="form-control" id="start_deduction_date" required>
                    </div>
                    <div class="row" id="statusDiv" style="display:none;">
                        <div class="col-lg-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cleared">
                                <label class="form-check-label" for="cleared">
                                    Cleared
                                </label>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="suspended">
                                <label class="form-check-label" for="suspended">
                                    Suspended
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm" id="saveHelbBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="helb_save_spinner"></span>
                            <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        let helbTable;
        let staffSelect;

        // Initialize Select2 for staff
        staffSelect = $('#staff_id').select2({
            dropdownParent: $('#addHelbModal'),
            placeholder: 'Select Staff',
            allowClear: true
        });

        // Load staff list
        function loadStaffList() {
            $.ajax({
                url: "{{ url('/ajax/deductions/helb/staff') }}",
                type: 'GET',
                success: function(res) {
                    staffSelect.empty().append('<option value="">Select Staff</option>');
                    if (res.staff && res.staff.length > 0) {
                        res.staff.forEach(function(s) {
                            staffSelect.append(new Option(s.text, s.id, false, false));
                        });
                    }
                    staffSelect.trigger('change');
                },
                error: function() {
                    toastr.error('Failed to load staff list');
                }
            });
        }

        loadStaffList();

        // Initialize HELB Table
        helbTable = $('#helb-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/deductions/helb/list') }}",
                dataSrc: 'deductions'
            },
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (data, type, row, meta) => meta.row + 1
                },
                {
                    data: 'staff_number',
                    render: data => data || '-'
                },
                {
                    data: 'staff_name',
                    render: function(data, type, row) {
                        if (data && data !== 'N/A' && row.encrypted_id) {
                            return `<a href="/payroll/helbloans/${row.encrypted_id}" style="text-decoration:underline; color: #1976d2;">${data}</a>`;
                        }
                        return data || '-';
                    }
                },
                {
                    data: 'loan_balance',
                    render: data => data ? parseFloat(data).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) : '0.00'
                },
                {
                    data: 'monthly_deduction',
                    render: data => data ? parseFloat(data).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) : '0.00'
                },
                {
                    data: 'start_deduction_date',
                    render: data => data ? new Date(data).toLocaleDateString() : '-'
                },
                {
                    data: 'status',
                    render: function(data) {
                        if (data === 'Cleared') {
                            return '<span class="badge badge-light-danger">Cleared</span>';
                        } else if (data === 'Suspended') {
                            return '<span class="badge badge-light-danger">Suspended</span>';
                        } else {
                            return '<span class="badge badge-light-success">Active</span>';
                        }
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        if (row.can_update) {
                            buttons += `<button class="btn btn-xs btn-light edit-helb" 
                                data-id="${row.id}" 
                                data-staff_id="${row.staff_id}"
                                data-loan_balance="${row.loan_balance}"
                                data-monthly_deduction="${row.monthly_deduction}"
                                data-start_date="${row.start_deduction_date}"
                                data-cleared="${row.cleared}"
                                data-suspended="${row.suspended}"
                                style="padding:1px 2px;">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                            </button>`;
                        }
                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            pageLength: 10,
            order: [
                [1, 'asc']
            ]
        });

        // HELB Form Submit
        $('#helbForm').on('submit', function(e) {
            e.preventDefault();
            const id = $('#helbId').val();
            const url = id ? `/ajax/deductions/helb/${id}` : '/ajax/deductions/helb';

            const $btn = $('#saveHelbBtn');
            const $spinner = $('#helb_save_spinner');
            $spinner.removeClass('d-none');
            $btn.prop('disabled', true);

            const formData = {
                loan_balance: $('#loan_balance').val(),
                monthly_deduction: $('#monthly_deduction').val(),
                start_deduction_date: $('#start_deduction_date').val(),
                _token: '{{ csrf_token() }}'
            };

            // Add staff_id only for new records
            if (!id) {
                formData.staff_id = $('#staff_id').val();
            }

            // Add status flags only for edit
            if (id) {
                formData.cleared = $('#cleared').is(':checked') ? 1 : 0;
                formData.suspended = $('#suspended').is(':checked') ? 1 : 0;
            }

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                success: function(res) {
                    $spinner.addClass('d-none');
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Saved successfully');
                        $('#addHelbModal').modal('hide');
                        $('#helbForm')[0].reset();
                        staffSelect.val(null).trigger('change');
                        helbTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving');
                    }
                },
                error: function(xhr) {
                    $spinner.addClass('d-none');
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

        // Edit HELB
        $(document).on('click', '.edit-helb', function() {
            const $btn = $(this);
            const data = $btn.data();

            $('#helbId').val(data.id);
            $('#loan_balance').val(data.loan_balance);
            $('#monthly_deduction').val(data.monthly_deduction);

            // Format date for date input (YYYY-MM-DD)
            let startDate = data.startDate || $btn.attr('data-start_date');
            if (startDate) {
                // Convert to Date object and format as YYYY-MM-DD
                const dateObj = new Date(startDate);
                if (!isNaN(dateObj.getTime())) {
                    const year = dateObj.getFullYear();
                    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                    const day = String(dateObj.getDate()).padStart(2, '0');
                    startDate = `${year}-${month}-${day}`;
                }
            }
            $('#start_deduction_date').val(startDate);

            $('#cleared').prop('checked', data.cleared == 1);
            $('#suspended').prop('checked', data.suspended == 1);

            // Hide staff select and remove required, show status checkboxes for edit
            $('#staff_id').removeAttr('required');
            $('#staffSelectDiv').hide();
            $('#statusDiv').show();

            $('#helbModalTitle').text('Edit HELB Loan');
            $('#addHelbModal').modal('show');
        });

        // Reset modal on close
        $('#addHelbModal').on('hidden.bs.modal', function() {
            $('#helbForm')[0].reset();
            $('#helbId').val('');
            staffSelect.val(null).trigger('change');
            $('#staff_id').attr('required', 'required');
            $('#staffSelectDiv').show();
            $('#statusDiv').hide();
            $('#helbModalTitle').text('Add HELB Loan');
        });
    });
</script>
@endsection