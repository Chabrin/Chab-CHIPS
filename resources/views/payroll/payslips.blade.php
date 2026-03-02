@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/chabrin.css') }}">
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Employee Payslips</h5>
                    </div>
                    <div class="card-body">
                        <!-- Search Form -->
                        <form id="payslip-search-form">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="employee_search_input" class="form-label">Search Employee</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="employee_search_input"
                                            placeholder="Search by Staff ID, Name, Email, or National ID">
                                        <button type="button" class="btn btn-primary" id="search_employee_btn">
                                            <span class="material-symbols-outlined" id="search_icon">search</span>
                                            <span class="spinner-border spinner-border-sm" role="status" id="search_spinner" style="display: none;"></span>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="employee_select" class="form-label">Select Employee</label>
                                    <select class="form-select" id="employee_select" name="staff_id" required>
                                        <option value="">-- Select Staff --</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="payroll_month" class="form-label">Payroll Month</label>
                                    <input type="month" class="form-control" id="payroll_month" name="payroll_month" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="d-flex justify-content-end gap-2">
                                        @permission('read_payslips')
                                        <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="view_payslip_btn">
                                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" id="view_spinner"></span>
                                            <span class="material-symbols-outlined me-1" style="font-size:18px;" id="view_icon">receipt_long</span>
                                            <span>View Payslip</span>
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="email-pdf" style="display: none;">
                                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" id="email_spinner"></span>
                                            <span class="material-symbols-outlined me-1" style="font-size:18px;" id="email_icon">email</span>
                                            <span>Email Payslip</span>
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="download-pdf" style="display: none;">
                                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" id="download_spinner"></span>
                                            <span class="material-symbols-outlined me-1" style="font-size:18px;" id="download_icon">download</span>
                                            <span>Download PDF</span>
                                        </button>
                                        @endpermission
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Payslip Display -->
                        <div id="payslip-container" style="display: none;">
                            <div id="payslip-content"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email Payslip Modal -->
<div class="modal fade" id="emailPayslipModal" tabindex="-1" aria-labelledby="emailPayslipModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailPayslipModalLabel">Email Payslip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="employee_email" class="form-label">Employee Email</label>
                    <input type="email" class="form-control" id="employee_email" readonly>
                </div>
                <div class="mb-3">
                    <label for="email_message" class="form-label">Message Preview</label>
                    <textarea class="form-control" id="email_message" rows="4" readonly></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="send_email_btn">
                    <span class="spinner-border spinner-border-sm me-2 d-none" role="status" id="send_spinner"></span>
                    <span class="material-symbols-outlined me-1" style="font-size:18px;" id="send_icon">send</span>
                    <span>Send Email</span>
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        // Search employee button click
        $('#search_employee_btn').on('click', function() {
            const searchTerm = $('#employee_search_input').val().trim();
            const $btn = $(this);
            const $icon = $('#search_icon');
            const $spinner = $('#search_spinner');

            if (searchTerm.length < 2) {
                toastr.error('Please enter at least 2 characters to search');
                return;
            }

            // Disable button, hide icon, show spinner
            $btn.prop('disabled', true);
            $icon.addClass('d-none');
            $spinner.removeClass('d-none');

            $.ajax({
                url: '/ajax/payslips/search-employees',
                method: 'GET',
                data: {
                    search: searchTerm
                },
                success: function(response) {
                    if (response.employees && response.employees.length > 0) {
                        let options = '<option value="">-- Select Staff --</option>';
                        response.employees.forEach(function(emp) {
                            options += `<option value="${emp.staff_id}">
                            ${emp.full_name} - Staff ID: ${emp.staff_number || 'N/A'} - Email: ${emp.email || 'N/A'}
                        </option>`;
                        });
                        $('#employee_select').html(options);
                        toastr.success(`Found ${response.employees.length} employee(s)`);
                    } else {
                        toastr.error('No employees found');
                        $('#employee_select').html('<option value="">-- Select Staff --</option>');
                    }
                },
                error: function() {
                    toastr.error('Search failed');
                },
                complete: function() {
                    // Re-enable button, hide spinner, show icon
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                    $icon.removeClass('d-none');
                }
            });
        });

        // Allow search on Enter key
        $('#employee_search_input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#search_employee_btn').click();
            }
        });

        // Search payslip
        $('#payslip-search-form').on('submit', function(e) {
            e.preventDefault();

            const staffId = $('#employee_select').val();
            if (!staffId) {
                toastr.error('Please select an employee');
                return;
            }

            const month = $('#payroll_month').val();
            if (!month) {
                toastr.error('Please select a payroll month');
                return;
            }

            const $btn = $('#view_payslip_btn');
            const $icon = $('#view_icon');
            const $spinner = $('#view_spinner');

            // Disable button, hide icon, show spinner
            $btn.prop('disabled', true);
            $icon.addClass('d-none');
            $spinner.removeClass('d-none');

            $.ajax({
                url: '/ajax/payslips/view',
                method: 'POST',
                data: {
                    staff_id: staffId,
                    payroll_month: month,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        $('#payslip-content').html(response.html);
                        $('#payslip-container').show();
                        $('#download-pdf').show();
                        $('#email-pdf').show();

                        // Store employee data for email
                        $('#email-pdf').data('employee-email', response.employee_email);
                        $('#email-pdf').data('employee-name', response.employee_name);
                        $('#email-pdf').data('payroll-month', response.payroll_month);
                    } else {
                        toastr.error(response.message || 'Payslip not found');
                        $('#payslip-container').hide();
                        $('#download-pdf').hide();
                        $('#email-pdf').hide();
                    }
                },
                error: function() {
                    toastr.error('Failed to load payslip');
                    $('#payslip-container').hide();
                    $('#download-pdf').hide();
                    $('#email-pdf').hide();
                },
                complete: function() {
                    // Re-enable button, hide spinner, show icon
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                    $icon.removeClass('d-none');
                }
            });
        });

        // Download PDF
        $('#download-pdf').on('click', function() {
            const staffId = $('#employee_select').val();
            const month = $('#payroll_month').val();

            if (!staffId || !month) {
                toastr.error('Please select an employee and month first');
                return;
            }

            const $btn = $(this);
            const $icon = $('#download_icon');
            const $spinner = $('#download_spinner');

            // Disable button, hide icon, show spinner
            $btn.prop('disabled', true);
            $icon.addClass('d-none');
            $spinner.removeClass('d-none');

            // Create hidden link and trigger download
            const link = document.createElement('a');
            link.href = `/payslips/download/${staffId}/${month}`;
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Re-enable button after short delay
            setTimeout(function() {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                $icon.removeClass('d-none');
                toastr.success('Payslip download started');
            }, 1000);
        });

        // Email Payslip
        $('#email-pdf').on('click', function() {
            const employeeEmail = $(this).data('employee-email');
            const employeeName = $(this).data('employee-name');
            const payrollMonth = $(this).data('payroll-month');

            if (!employeeEmail) {
                toastr.error('Employee email not found');
                return;
            }

            // Set modal data
            $('#employee_email').val(employeeEmail);
            $('#email_message').val(`Dear ${employeeName},\n\nPlease find attached your payslip for ${payrollMonth}.\n\nRegards,\nHR Department`);

            // Show modal
            $('#emailPayslipModal').modal('show');
        });

        // Send Email
        $('#send_email_btn').on('click', function() {
            const staffId = $('#employee_select').val();
            const month = $('#payroll_month').val();
            const email = $('#employee_email').val();

            if (!staffId || !month || !email) {
                toastr.error('Missing required information');
                return;
            }

            const $btn = $(this);
            const $icon = $('#send_icon');
            const $spinner = $('#send_spinner');

            // Disable button, hide icon, show spinner
            $btn.prop('disabled', true);
            $icon.addClass('d-none');
            $spinner.removeClass('d-none');

            $.ajax({
                url: '/ajax/payslips/email',
                method: 'POST',
                data: {
                    staff_id: staffId,
                    payroll_month: month,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message || 'Payslip email queued successfully');
                        $('#emailPayslipModal').modal('hide');
                    } else {
                        toastr.error(response.message || 'Failed to send email');
                    }
                },
                error: function() {
                    toastr.error('Failed to queue email');
                },
                complete: function() {
                    // Re-enable button, hide spinner, show icon
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                    $icon.removeClass('d-none');
                }
            });
        });
    });
</script>