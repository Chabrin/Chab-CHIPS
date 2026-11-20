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
                        {{-- <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <label for="helbStatusFilter" class="form-label mb-0">Show</label>
                            <select id="helbStatusFilter" class="form-select form-select-sm" style="width:auto; min-width:14rem;">
                                <option value="open" selected>Open (active and paused)</option>
                                <option value="Active">Active</option>
                                <option value="Paused">Paused</option>
                                <option value="Cleared">Cleared</option>
                                <option value="all">All</option>
                            </select>
                        </div> --}}
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
                                        <th>Ends</th>
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
                        {{-- What onboarding recorded about this person and HELB, so HR can see whether the profile agrees --}}
                        <div id="helbStaffStatus" class="mt-2 d-none"></div>
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
                    {{-- Shown when an existing loan is edited: changing the balance, the monthly amount or the start date needs a reason.
                         Pausing and closing the loan are done from its details page. --}}
                    <div class="mb-3" id="reasonDiv" style="display:none;">
                        <label for="change_reason" class="form-label">Reason for the change <span style="color:red">*</span></label>
                        <textarea class="form-control" id="change_reason" rows="2" maxlength="1000"></textarea>
                        <small class="text-muted">For example: HELB changed the monthly rate on the Employer Portal. Needed when the balance, monthly amount or start date changes.</small>
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
<script>
    $(document).ready(function() {
        const canCreateHelb = @json(Auth::user()->isAbleTo('create_helb_loans'));
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

        if (canCreateHelb) loadStaffList();

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
                    // The last month it will be taken, worked out from what is still owed and the monthly amount
                    data: 'end_month',
                    render: function(data, type, row) {
                        if (!data) return '-';
                        return data + ' (' + row.months_left + (row.months_left == 1 ? ' month' : ' months') + ')';
                    }
                },
                {
                    data: 'status',
                    render: function(data, type) {
                        // Searching and sorting use the plain word, so the filter above can match it exactly
                        if (type !== 'display') return data;
                        if (data === 'Cleared') {
                            return '<span class="badge badge-light-danger">Cleared</span>';
                        } else if (data === 'Paused') {
                            return '<span class="badge badge-light-warning">Paused</span>';
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
                        // A closed loan is changed only after it is reopened, from its details page
                        if (row.can_update && row.cleared != 1) {
                            buttons += `<button class="btn btn-xs btn-light edit-helb"
                                data-id="${row.id}"
                                data-staff_id="${row.staff_id}"
                                data-loan_balance="${row.loan_balance}"
                                data-monthly_deduction="${row.monthly_deduction}"
                                data-start_date="${row.start_deduction_date}"
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
            ],
            // Start on the loans that are still running; the Status column is the 8th (index 7)
            searchCols: [null, null, null, null, null, null, null, { search: '^(Active|Paused)$', regex: true, smart: false }, null]
        });

        // The status filter: open loans (active and paused), one status, or all
        $('#helbStatusFilter').on('change', function() {
            const value = $(this).val();
            const pattern = value === 'open' ? '^(Active|Paused)$' : (value === 'all' ? '' : '^' + value + '$');
            helbTable.column(7).search(pattern, true, false).draw();
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

            // The reason for changing an existing loan (needed when the balance, monthly amount or start date changes)
            if (id) {
                formData.reason = $('#change_reason').val();
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
            $('#change_reason').val('');

            // Hide the staff select and remove required, ask for the reason of the change
            $('#staff_id').removeAttr('required');
            $('#staffSelectDiv').hide();
            $('#reasonDiv').show();

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
            $('#reasonDiv').hide();
            $('#helbStaffStatus').addClass('d-none').empty();
            $('#helbModalTitle').text('Add HELB Loan');
        });

        // When a staff member is chosen, show what onboarding recorded about them and HELB (a note, not a block)
        staffSelect.on('change', function() {
            const staffId = $(this).val();
            const $box = $('#helbStaffStatus').addClass('d-none').empty();
            if (!staffId || $('#helbId').val()) return;
            $.ajax({
                url: '/ajax/deductions/helb/staff/' + staffId + '/status',
                type: 'GET',
                success: function(status) {
                    if ($('#staff_id').val() != staffId) return; // a different person was chosen meanwhile
                    const beneficiary = status.beneficiary === null ? 'not answered' : (status.beneficiary ? 'yes' : 'no');
                    let certificate = 'not on file';
                    if (status.certificate === 'on_file') certificate = 'on file';
                    if (status.certificate === 'exception') certificate = 'waived by the HR Manager (' + (status.exception_reason || 'no reason recorded') + ')';
                    const fine = status.beneficiary === true && status.certificate !== 'missing';
                    const $note = $('<div class="alert py-2 mb-0 small">').addClass(fine ? 'alert-success' : 'alert-warning');
                    $note.append($('<div>').text('On their profile: HELB loan beneficiary: ' + beneficiary + '. HELB certificate: ' + certificate + '.'));
                    if (status.running_loan) {
                        $note.append($('<div class="fw-semibold">').text('This staff member already has a HELB loan that is not closed.'));
                    }
                    if (!fine) {
                        $note.append($('<div>').text('The profile does not show a HELB beneficiary with a certificate. You can still add the loan, but check the profile first.'));
                    }
                    $box.append($note).removeClass('d-none');
                }
            });
        });
    });
</script>
@endsection
