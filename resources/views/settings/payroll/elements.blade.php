@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Payroll Elements</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_payroll_elements')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addPayrollElementsModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Payroll Element</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="elements-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Element Name</th>
                                        <th>Type</th>
                                        <th>How often</th>
                                        <th>Applies to</th>
                                        <th>Amount</th>
                                        <th>Account</th>
                                        <th>Status</th>
                                        <th>Payslip Position</th>
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
<!-- Add/Edit Payroll Elements Modal -->
<div class="modal fade" id="addPayrollElementsModal" tabindex="-1" aria-labelledby="addPayrollElementsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPayrollElementsModalLabel">Payroll Elements</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="payrollElementsForm">
                    <input type="hidden" name="id" id="elementId">
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label for="element_name" class="form-label">Element Name <span style="color:red">*</span></label>
                            <input type="text" class="form-control" name="element_name" id="element_name" required placeholder="Enter element name">
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="account" class="form-label">Account <span style="color:red">*</span></label>
                            <select class="form-select" name="account" id="account" required>
                                <option value="" disabled selected>Select account</option>
                                <!-- Options to be populated dynamically -->
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label for="element_type" class="form-label">Type <span style="color:red">*</span></label>
                            <select class="form-select" name="element_type" id="element_type" required>
                                <option value="" disabled selected>Select type</option>
                                <option value="1">Deduction</option>
                                <option value="2">Earnings</option>
                            </select>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="formula" class="form-label">Formula <span style="color:red">*</span></label>
                            <select class="form-select" name="formula" id="formula" required>
                                <option value="" disabled selected>Select formula</option>
                                <option value="1">Fixed</option>
                                <option value="2">Percent of basic salary</option>
                                <option value="3">Percent of gross payment</option>
                                <option value="4">Use NSSF settings (built in)</option>
                                <option value="5">Use PAYE settings (built in)</option>
                                <option value="6">Use SHA settings (built in)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label for="recurrence" class="form-label">How often <span style="color:red">*</span></label>
                            <select class="form-select" name="recurrence" id="recurrence" required>
                                <option value="recurring" selected>Recurring (every month)</option>
                                <option value="one_time">One-time (a single month)</option>
                            </select>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="applies_to" class="form-label">Applies to <span style="color:red">*</span></label>
                            <select class="form-select" name="applies_to" id="applies_to" required>
                                <option value="specific" selected>Specific staff</option>
                                <option value="all">All staff</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="alert alert-light border mb-0 small" id="settingsHelp"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label for="amount" class="form-label">Amount <span style="color:red">*</span></label>
                            <input type="number" class="form-control" name="amount" id="amount" required placeholder="Enter amount">
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="staff_positions" class="form-label">Staff Positions <span style="color:red">*</span></label>
                            <select class="form-select" name="staff_positions" id="staff_positions" required>
                                <option value="" disabled selected>Select staff positions</option>
                                <!-- Options to be populated dynamically -->
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label for="payslip_position" class="form-label">Payslip Position <span style="color:red">*</span></label>
                            <input type="number" class="form-control" name="payslip_position" id="payslip_position" required placeholder="Enter payslip position">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        @permission('create_payroll_elements')
                        <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="saveDepartmentBtn">
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
    $(document).ready(function() {
        let table = $('#elements-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/payroll/elements/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'elements'
            },
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'element_name',
                    render: function(data) {
                        return data || '-';
                    }
                },
                {
                    data: 'element_type_name',
                    render: function(data, type, row) {
                        if (row.element_type == '1') {
                            return 'Deduction';
                        } else if (row.element_type == '2') {
                            return 'Earnings';
                        }
                        return 'Unknown';
                    }
                },
                {
                    data: 'recurrence',
                    render: function(data) {
                        return data === 'one_time' ? 'One-time' : 'Recurring';
                    }
                },
                {
                    data: 'applies_to',
                    render: function(data) {
                        return data === 'all' ? 'All staff' : 'Specific staff';
                    }
                },
                {
                    data: 'formatted_amount',
                    render: function(data, type, row) {
                        return data || '0.00';
                    }
                },
                {
                    data: 'account_name',
                    render: function(data) {
                        return data || '-';
                    }
                },
                {
                    data: 'status_name',
                    render: function(data, type, row) {
                        if (row.current_status_id == 5) {
                            return '<span class="badge badge-light-success">Approved</span>';
                        }
                        return '<span class="badge badge-light-danger">Pending</span>';
                    }
                },
                {
                    data: 'payslip_position',
                    render: function(data) {
                        return data || '-';
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
                                <button class="btn btn-xs btn-light edit-element" 
                                    data-id="${row.id}" 
                                    data-element_name="${row.element_name}" 
                                    data-account_id="${row.account_id}" 
                                    data-element_type="${row.element_type}" 
                                    data-formula_id="${row.formula_id}"
                                    data-recurrence="${row.recurrence || 'recurring'}"
                                    data-applies_to="${row.applies_to || 'specific'}" 
                                    data-amount="${row.amount}" 
                                    data-staff_positions_id="${row.staff_positions_id}" 
                                    data-payslip_position="${row.payslip_position}"
                                    style="padding:1px 2px;">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </button>`;
                        }

                        if (row.can_delete) {
                            buttons += `
                                <button class="btn btn-xs btn-light delete-element" 
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
            order: [
                [1, 'asc']
            ],
            paging: true,
            info: true,
            lengthChange: true
        });

        $('#addPayrollElementsModal').on('show.bs.modal', function() {
            $.ajax({
                url: "{{ url('/ajax/payroll/elements/accounts/list') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let $account = $('#account');
                    $account.empty();
                    $account.append($('<option>', {
                        value: '',
                        text: 'Select account',
                        disabled: true,
                        selected: true
                    }));
                    if (response.accounts) {
                        $.each(response.accounts, function(groupTitle, accounts) {
                            let $optgroup = $('<optgroup>').attr('label', groupTitle);
                            accounts.forEach(function(account) {
                                $optgroup.append($('<option>', {
                                    value: account.id,
                                    text: account.name
                                }));
                            });
                            $account.append($optgroup);
                        });
                    }
                },
                error: function() {
                    toastr.error('Failed to load accounts.');
                }
            });
        });

        $('#addPayrollElementsModal').on('show.bs.modal', function() {
            $.ajax({
                url: "{{ url('/ajax/payroll/elements/staff_positions/list') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let $staff_positions = $('#staff_positions');
                    $staff_positions.empty();
                    $staff_positions.append($('<option>', {
                        value: '',
                        text: 'Select staff positions',
                        disabled: true,
                        selected: true
                    }));
                    if (response.staff_positions && Array.isArray(response.staff_positions)) {
                        response.staff_positions.forEach(function(position) {
                            $staff_positions.append($('<option>', {
                                value: position.id,
                                text: position.position_name
                            }));
                        });
                    }

                    // Check if Select2 is already initialized and destroy it
                    if ($staff_positions.hasClass("select2-hidden-accessible")) {
                        $staff_positions.select2('destroy');
                    }

                    // Initialize Select2
                    $staff_positions.select2({
                        dropdownParent: $('#addPayrollElementsModal'),
                        placeholder: 'Select staff positions',
                        allowClear: true
                    });

                    // Edit mode: apply the position chosen when the edit button was clicked
                    let pending = $staff_positions.data('pending');
                    if (pending) {
                        $staff_positions.val(pending).trigger('change');
                    }
                },
                error: function() {
                    toastr.error('Failed to load staff positions.');
                }
            });
        });

        $('#payrollElementsForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#elementId').val();
            var url = id ? "{{ url('/ajax/payroll/elements') }}/" + id : "{{ url('/ajax/save/payroll/elements') }}";
            var method = 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveDepartmentBtn');
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
                        $('#addPayrollElementsModal').modal('hide');
                        $('#payrollElementsForm')[0].reset();
                        $('#elementId').val('');
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

        $(document).on('click', '.edit-element', function() {
            // Set all form fields except dropdowns
            $('#elementId').val($(this).data('id'));
            $('#element_name').val($(this).data('element_name'));
            $('#element_type').val($(this).data('element_type'));
            $('#formula').val($(this).data('formula_id'));
            $('#recurrence').val($(this).data('recurrence'));
            $('#applies_to').val($(this).data('applies_to'));
            updateSettingsHelp();
            $('#amount').val($(this).data('amount'));
            $('#payslip_position').val($(this).data('payslip_position'));

            // The modal's show handler loads the positions list and applies this selection
            $('#staff_positions').data('pending', $(this).data('staff_positions_id'));

            // Show modal first
            $('#addPayrollElementsModal').modal('show');

            // Populate accounts and set selected value
            $.ajax({
                url: "{{ url('/ajax/payroll/elements/accounts/list') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let $account = $('#account');
                    $account.empty();
                    $account.append($('<option>', {
                        value: '',
                        text: 'Select account',
                        disabled: true,
                        selected: true
                    }));
                    if (response.accounts) {
                        $.each(response.accounts, function(groupTitle, accounts) {
                            let $optgroup = $('<optgroup>').attr('label', groupTitle);
                            accounts.forEach(function(account) {
                                $optgroup.append($('<option>', {
                                    value: account.id,
                                    text: account.name
                                }));
                            });
                            $account.append($optgroup);
                        });
                    }
                    // Set selected value after populating
                    $account.val($(this).data('account_id'));
                }.bind(this), // bind 'this' so $(this).data() works
                error: function() {
                    toastr.error('Failed to load accounts.');
                }
            });

        });

        $(document).on('click', '.delete-element', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();

            if (!confirm('Are you sure you want to delete this payroll element?')) {
                return;
            }

            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ url('/ajax/payroll/elements') }}/" + $btn.data('id'),
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

        // What the two settings mean, in words, for whoever is filling the form in
        function updateSettingsHelp() {
            var how = $('#recurrence').val();
            var who = $('#applies_to').val();
            var text;
            if (how === 'one_time') {
                text = 'Entered for a single payroll month on Monthly Payroll Items, then it stops. One-time items are not kept on contracts.';
            } else if (who === 'all') {
                text = 'Applied to every staff member in every run using the formula and amount here (Fixed, Percent of basic salary or Percent of gross payment). A contract can hold a row for it to give one person a different amount.';
            } else {
                text = 'Added to each staff member\'s contract by HR, with their own amount, and repeats every month.';
            }
            $('#settingsHelp').text(text);
        }
        $('#recurrence, #applies_to').on('change', updateSettingsHelp);
        $('#addPayrollElementsModal').on('show.bs.modal', updateSettingsHelp);

        $('#addPayrollElementsModal').on('hidden.bs.modal', function() {
            $('#payrollElementsForm')[0].reset();
            $('#elementId').val('');
            $('#staff_positions').removeData('pending').val(null).trigger('change');
        });
    });
</script>
@endsection
