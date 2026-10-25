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
                        <div class="row">
                            <!-- LEFT: Search & Employee Info (col-5) -->
                            <div class="col-md-5">
                                <form id="payslip-search-form">
                                    <!-- Search -->
                                    <div class="mb-2">
                                        <label for="employee_search_input" class="form-label mb-1">Search Employee</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-sm" id="employee_search_input"
                                                placeholder="-- Search --">
                                            <button type="button" class="btn btn-primary btn-sm" id="search_employee_btn">
                                                <span class="material-symbols-outlined" id="search_icon" style="font-size:18px;">search</span>
                                                <span class="spinner-border spinner-border-sm" role="status" id="search_spinner" style="display:none;"></span>
                                            </button>
                                        </div>
                                        <small class="text-muted">Search by staff number, email, national ID or names</small>
                                    </div>

                                    <!-- Select from results -->
                                    <div class="mb-2">
                                        <label for="employee_select" class="form-label mb-1">Select Employee</label>
                                        <select class="form-select form-select-sm" id="employee_select" name="staff_id" required>
                                            <option value="">-- Select Staff --</option>
                                        </select>
                                    </div>

                                    <!-- Employee details -->
                                    <table class="mb-2" style="font-size:12px;width:100%;" id="emp_details_table">
                                        <tr>
                                            <td class="text-muted" style="width:80px;padding:2px 4px;">Names</td>
                                            <td style="padding:2px 4px;" id="emp_full_name">-</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted" style="padding:2px 4px;">ID No.</td>
                                            <td style="padding:2px 4px;" id="emp_national_id">-</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted" style="padding:2px 4px;">Email</td>
                                            <td style="padding:2px 4px;" id="emp_email">-</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted" style="padding:2px 4px;">Phone</td>
                                            <td style="padding:2px 4px;" id="emp_phone">-</td>
                                        </tr>
                                    </table>

                                    <!-- Payroll Month -->
                                    <div class="mb-2">
                                        <label for="payroll_month" class="form-label mb-1">Payroll Month</label>
                                        <input type="month" class="form-control form-control-sm" id="payroll_month" name="payroll_month" required>
                                    </div>

                                    <!-- Buttons -->
                                    <div class="d-flex gap-2 mt-3">
                                        @permission('read_payslips')
                                        <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="view_payslip_btn">
                                            <span class="spinner-border spinner-border-sm me-1 d-none" role="status" id="view_spinner"></span>
                                            <span class="material-symbols-outlined me-1" style="font-size:18px;" id="view_icon">receipt_long</span>
                                            <span>View</span>
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="email-pdf" style="display:none;">
                                            <span class="spinner-border spinner-border-sm me-1 d-none" role="status" id="email_spinner"></span>
                                            <span class="material-symbols-outlined me-1" style="font-size:18px;" id="email_icon">email</span>
                                            <span>Email</span>
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="download-pdf" style="display:none;">
                                            <span class="spinner-border spinner-border-sm me-1 d-none" role="status" id="download_spinner"></span>
                                            <span class="material-symbols-outlined me-1" style="font-size:18px;" id="download_icon">download</span>
                                            <span>Download</span>
                                        </button>
                                        @endpermission
                                    </div>
                                </form>
                            </div>

                            <!-- RIGHT: Payslip Display (col-7) -->
                            <div class="col-md-7" style="border-left:1px solid #dee2e6;">
                                <h6 class="text-muted mb-2" style="font-size:13px;">Payslip Info</h6>
                                <div id="payslip-error" style="display:none;"></div>
                                <div id="payslip-container" style="display:none;">
                                    <div id="payslip-content"></div>
                                </div>
                            </div>
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



<script>
    // Mirrors PayslipsController's read_all_payslips check — lets Email/Download
    // fail fast client-side with the real message instead of falling through to
    // unrelated checks (e.g. "Employee email not found") or starting a doomed
    // download when the user was never going to be allowed to proceed.
    const canReadAllPayslips = @json(Auth::user()->isAbleTo('read_all_payslips'));

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
                            options += '<option value="' + emp.staff_id + '"' +
                                ' data-name="' + (emp.full_name || '') + '"' +
                                ' data-id="' + (emp.national_id || '') + '"' +
                                ' data-email="' + (emp.email_address || '') + '"' +
                                ' data-phone="' + (emp.mobile_number || '') + '">' +
                                (emp.full_name || '') + ' - ' + (emp.staff_number || 'N/A') +
                                '</option>';
                        });
                        $('#employee_select').html(options);
                        toastr.success('Found ' + response.employees.length + ' employee(s)');
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

        // Populate employee details when selected
        $('#employee_select').on('change', function() {
            var $sel = $(this).find(':selected');
            if ($(this).val()) {
                $('#emp_full_name').text($sel.data('name') || '-');
                $('#emp_national_id').text($sel.data('id') || '-');
                $('#emp_email').text($sel.data('email') || '-');
                $('#emp_phone').text($sel.data('phone') || '-');
            } else {
                $('#emp_full_name, #emp_national_id, #emp_email, #emp_phone').text('-');
            }
        });

        // Allow search on Enter key
        $('#employee_search_input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#search_employee_btn').click();
            }
        });

        // Shows a closeable danger alert in the right-side payslip panel —
        // used for both validation failures (success:false, e.g. "not found")
        // and HTTP-level errors (e.g. 403 permission denied), so either way
        // the real server message reaches the user instead of a generic one.
        function showPayslipError(message) {
            $('#payslip-container').hide();
            $('#download-pdf').hide();
            $('#email-pdf').hide();
            $('#payslip-error').html(
                '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                    $('<div>').text(message).html() +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                '</div>'
            ).show();
        }

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
            $('#payslip-error').hide().empty();

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

                        // Scroll to top of payslip card
                        setTimeout(function() {
                            var card = document.querySelector('.card-header');
                            if (card) {
                                var rect = card.getBoundingClientRect();
                                window.scrollTo({
                                    top: window.pageYOffset + rect.top - 50,
                                    behavior: 'smooth'
                                });
                            }
                        }, 300);

                        // Store employee data for email
                        $('#email-pdf').data('employee-email', response.employee_email);
                        $('#email-pdf').data('employee-name', response.employee_name);
                        $('#email-pdf').data('payroll-month', response.payroll_month);
                    } else {
                        showPayslipError(response.message || 'Payslip not found');
                    }
                },
                error: function(xhr) {
                    const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to load payslip';
                    showPayslipError(message);
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
            if (!canReadAllPayslips) {
                toastr.error('You do not have permission to download payslips.');
                return;
            }

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
            if (!canReadAllPayslips) {
                toastr.error('You do not have permission to email payslips.');
                return;
            }

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
                error: function(xhr) {
                    const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to queue email';
                    toastr.error(message);
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
@endsection
