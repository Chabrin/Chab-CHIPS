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
                        <h5 class="mb-0">Prorated Deductions</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_prorated_deductions')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addProratedModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Prorated Deduction</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="prorated-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Staff Number</th>
                                        <th>Staff Name</th>
                                        <th>Element Name</th>
                                        <th>Monthly (KSh)</th>
                                        <th>Months</th>
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

<!-- Add/Edit Prorated Modal -->
<div class="modal fade" id="addProratedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="proratedModalTitle">Add Prorated Deduction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="proratedForm">
                    <input type="hidden" id="proratedId">
                    <div class="row">
                        <div class="col-lg-6 mb-3" id="staffSelectDiv">
                            <label for="staff_id" class="form-label">Staff Member <span style="color:red">*</span></label>
                            <select class="form-control" id="staff_id" style="width:100%;">
                                <option value="">Select Staff</option>
                            </select>
                        </div>
                        <div class="col-lg-6 mb-3" id="elementSelectDiv">
                            <label for="element_id" class="form-label">Deduction Element <span style="color:red">*</span></label>
                            <select class="form-control" id="element_id" style="width:100%;">
                                <option value="">Select Element</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label for="principal_amount" class="form-label">Principal Amount <span style="color:red">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="principal_amount" required>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="interest_rate" class="form-label">Interest/Charges (%) <span style="color:red">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="interest_rate" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-4 mb-3">
                            <label for="repayment_months" class="form-label">Repayment Months <span style="color:red">*</span></label>
                            <input type="number" class="form-control" id="repayment_months" required min="1">
                            <small class="text-muted">Number of months to repay</small>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label for="monthly_amount" class="form-label">Monthly Amount <span style="color:red">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="monthly_amount" required>
                            <small class="text-muted">Total ÷ Months</small>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label for="initial_deduction" class="form-label">Initial Deduction</label>
                            <input type="number" step="0.01" class="form-control" id="initial_deduction" value="0">
                            <small class="text-muted">One-time at start</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label for="issued_date" class="form-label">Issued Date <span style="color:red">*</span></label>
                            <input type="date" class="form-control" id="issued_date" required>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="deduction_date" class="form-label">Start Deduction Date <span style="color:red">*</span></label>
                            <input type="date" class="form-control" id="deduction_date" required>
                        </div>
                    </div>
                    <div class="alert alert-info small mb-3">
                        <span class="material-symbols-outlined" style="font-size:16px;">calculate</span>
                        Total Amount = Principal + Interest | Monthly Deduction = (Total - Initial) / Months
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm" id="saveProratedBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="prorated_save_spinner"></span>
                            <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        let proratedTable;
        let staffSelect, elementSelect;

        // Initialize Select2 for staff
        staffSelect = $('#staff_id').select2({
            dropdownParent: $('#addProratedModal'),
            placeholder: 'Select Staff',
            allowClear: true
        });

        // Initialize Select2 for element
        elementSelect = $('#element_id').select2({
            dropdownParent: $('#addProratedModal'),
            placeholder: 'Select Element',
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

        // Load elements list
        function loadElementsList() {
            $.ajax({
                url: "{{ url('/ajax/deductions/prorated/elements') }}",
                type: 'GET',
                success: function(res) {
                    elementSelect.empty().append('<option value="">Select Element</option>');
                    if (res.elements && res.elements.length > 0) {
                        res.elements.forEach(function(e) {
                            elementSelect.append(new Option(e.text, e.id, false, false));
                        });
                    }
                    elementSelect.trigger('change');
                },
                error: function() {
                    toastr.error('Failed to load elements list');
                }
            });
        }

        loadStaffList();
        loadElementsList();

        // Initialize Prorated Table
        proratedTable = $('#prorated-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/deductions/prorated/list') }}",
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
                            return `<a href="/payroll/prorated/deductions/${row.encrypted_id}" style="text-decoration:underline; color: #1976d2;">${data}</a>`;
                        }
                        return data || '-';
                    }
                },
                {
                    data: 'element_name',
                    render: data => data || '-'
                },
                {
                    data: 'monthly_deduction',
                    render: data => data ? parseFloat(data).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) : '0.00'
                },
                {
                    data: 'repayment_months',
                    render: data => data || '-'
                },
                {
                    data: 'status',
                    render: function(data, type, row) {
                        return `<span class="badge ${row.status_class}">${data}</span>`;
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        if (row.can_update) {
                            buttons += `<button class="btn btn-xs btn-light edit-prorated" 
                                data-id="${row.id}" 
                                data-principal_amount="${row.principal_amount}"
                                data-interest_rate="${row.interest_rate}"
                                data-repayment_months="${row.repayment_months}"
                                data-monthly_deduction="${row.monthly_deduction}"
                                data-initial_deduction="${row.initial_deduction || 0}"
                                data-issued_date="${row.issued_date}"
                                data-deduction_date="${row.deduction_date}"
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

        // Prorated Form Submit
        $('#proratedForm').on('submit', function(e) {
            e.preventDefault();
            const id = $('#proratedId').val();
            const url = id ? `/ajax/deductions/prorated/${id}` : '/ajax/deductions/prorated';

            const $btn = $('#saveProratedBtn');
            const $spinner = $('#prorated_save_spinner');
            $spinner.removeClass('d-none');
            $btn.prop('disabled', true);

            const formData = {
                principal_amount: $('#principal_amount').val(),
                interest_rate: $('#interest_rate').val(),
                repayment_months: $('#repayment_months').val(),
                monthly_amount: $('#monthly_amount').val(),
                initial_deduction: $('#initial_deduction').val(),
                issued_date: $('#issued_date').val(),
                deduction_date: $('#deduction_date').val(),
                _token: '{{ csrf_token() }}'
            };

            // Add staff_id and element_id only for new records
            if (!id) {
                formData.staff_id = $('#staff_id').val();
                formData.element_id = $('#element_id').val();
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
                        $('#addProratedModal').modal('hide');
                        $('#proratedForm')[0].reset();
                        staffSelect.val(null).trigger('change');
                        elementSelect.val(null).trigger('change');
                        proratedTable.ajax.reload(null, false);
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

        // Edit Prorated
        $(document).on('click', '.edit-prorated', function() {
            const $btn = $(this);
            const data = $btn.data();

            $('#proratedId').val(data.id);
            $('#principal_amount').val(data.principal_amount);
            $('#interest_rate').val(data.interest_rate);
            $('#repayment_months').val(data.repayment_months);
            $('#monthly_amount').val(data.monthly_deduction || data.monthlyDeduction);
            $('#initial_deduction').val(data.initial_deduction);

            // Format dates
            let issuedDate = data.issuedDate || $btn.attr('data-issued_date');
            let deductionDate = data.deductionDate || $btn.attr('data-deduction_date');

            if (issuedDate) {
                const issuedDateObj = new Date(issuedDate);
                if (!isNaN(issuedDateObj.getTime())) {
                    const year = issuedDateObj.getFullYear();
                    const month = String(issuedDateObj.getMonth() + 1).padStart(2, '0');
                    const day = String(issuedDateObj.getDate()).padStart(2, '0');
                    issuedDate = `${year}-${month}-${day}`;
                }
            }
            $('#issued_date').val(issuedDate);

            if (deductionDate) {
                const deductionDateObj = new Date(deductionDate);
                if (!isNaN(deductionDateObj.getTime())) {
                    const year = deductionDateObj.getFullYear();
                    const month = String(deductionDateObj.getMonth() + 1).padStart(2, '0');
                    const day = String(deductionDateObj.getDate()).padStart(2, '0');
                    deductionDate = `${year}-${month}-${day}`;
                }
            }
            $('#deduction_date').val(deductionDate);

            // Hide staff and element selects for edit
            $('#staff_id').removeAttr('required');
            $('#element_id').removeAttr('required');
            $('#staffSelectDiv').hide();
            $('#elementSelectDiv').hide();

            $('#proratedModalTitle').text('Edit Prorated Deduction');
            $('#addProratedModal').modal('show');
        });

        // Reset modal on close
        $('#addProratedModal').on('hidden.bs.modal', function() {
            $('#proratedForm')[0].reset();
            $('#proratedId').val('');
            staffSelect.val(null).trigger('change');
            elementSelect.val(null).trigger('change');
            $('#staff_id').attr('required', 'required');
            $('#element_id').attr('required', 'required');
            $('#staffSelectDiv').show();
            $('#elementSelectDiv').show();
            $('#proratedModalTitle').text('Add Prorated Deduction');
        });
    });
</script>
@endsection