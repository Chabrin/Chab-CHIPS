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
        <!-- Recent Processed Periods Section -->
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Recent Payroll Runs</h5>
                        @permission('create_payroll_processing')
                        <div class="ms-auto">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" id="processNewPeriodBtn">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">autorenew</span>
                                Run Payroll
                            </button>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="recent-periods-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Employees</th>
                                        <th>Total Basic</th>
                                        <th>Total Gross</th>
                                        <th>Total Deductions</th>
                                        <th>Total Net</th>
                                        <th>Run Status</th>
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

<!-- Run details modal: per-employee breakdown for audit before approval -->
<div class="modal fade" id="runDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payroll Run Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Staff No</th><th>Name</th><th class="text-end">Basic</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net</th></tr>
                        </thead>
                        <tbody id="run-details-body"></tbody>
                        <tfoot id="run-details-foot"></tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Process Period Modal -->
<div class="modal fade" id="processPeriodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Process Payroll Period</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="processPeriodForm">
                    <div class="mb-3">
                        <label for="period_id" class="form-label">Select Unprocessed Period <span style="color:red">*</span></label>
                        <select class="form-select" id="period_id" required>
                            <option value="">Loading periods...</option>
                        </select>
                        <small class="text-muted">Only periods that haven't been processed are shown</small>
                    </div>
                    <div class="alert alert-info small mb-3">
                        <span class="material-symbols-outlined" style="font-size:16px;">info</span>
                        Salary and contract elements are prorated by calendar days when an active contract covers only part of this period.
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span> Cancel
                        </button>
                        @permission('create_payroll_processing')
                        <button type="submit" class="btn btn-primary btn-sm" id="processPeriodBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="process_spinner"></span>
                            <span class="material-symbols-outlined me-1" style="font-size:18px;">autorenew</span>
                            Process Payroll
                        </button>
                        @endpermission

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
        let recentPeriodsTable;
        const canApprovePayroll = @json(Auth::user()->isAbleTo('approve_payroll_processing'));
        const currentUserId = {{ (int) Auth::id() }};

        // Initialize Recent Periods Table
        recentPeriodsTable = $('#recent-periods-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('ajax.payroll.recentPeriods') }}",
                dataSrc: 'periods'
            },
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (data, type, row, meta) => meta.row + 1
                },
                {
                    data: 'start_date',
                    render: data => data ? new Date(data).toLocaleDateString() : '-'
                },
                {
                    data: 'end_date',
                    render: data => data ? new Date(data).toLocaleDateString() : '-'
                },
                {
                    data: 'employee_count',
                    render: data => `<span class="badge badge-light-success">${data || 0}</span>`
                },
                {
                    data: 'total_basic_salary',
                    render: data => formatCurrency(data || 0)
                },
                {
                    data: 'total_gross_salary',
                    render: data => formatCurrency(data || 0)
                },
                {
                    data: 'total_deductions',
                    render: data => formatCurrency(data || 0)
                },
                {
                    data: 'total_net_salary',
                    render: data => '<strong>' + formatCurrency(data || 0) + '</strong>'
                },
                {
                    data: 'run_status_id',
                    render: (status, type, row) => Number(status) === 1
                        ? '<span class="badge badge-light-warning">Pending review</span>'
                            + (row.processed_by_name ? `<div class="small text-muted">Processed by ${$('<span>').text(row.processed_by_name).html()}</div>` : '')
                        : '<span class="badge badge-light-success">Approved</span>'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: row => {
                        const id = row.period_id;
                        const pending = Number(row.run_status_id) === 1;
                        const ownRun = row.processed_by && Number(row.processed_by) === currentUserId;
                        let html = `<button type="button" class="btn btn-light btn-sm view-payroll me-1" data-period-id="${id}">View</button>`;
                        if (canApprovePayroll && pending) {
                            html += ownRun
                                ? '<span class="small text-muted me-1">Needs another approver</span>'
                                : `<button type="button" class="btn btn-success btn-sm approve-payroll me-1" data-period-id="${id}">Approve</button>`;
                            html += `<button type="button" class="btn btn-outline-danger btn-sm reject-payroll" data-period-id="${id}">Reject</button>`;
                        }
                        return html;
                    }
                }
            ],
            pageLength: 6,
            order: [
                [1, 'desc']
            ]
        });

        // View the per-employee breakdown of a run (audit before approval)
        $('#recent-periods-table').on('click', '.view-payroll', function() {
            const periodId = $(this).data('period-id');
            $('#run-details-body').html('<tr><td colspan="6" class="text-center">Loading...</td></tr>');
            $('#run-details-foot').empty();
            $('#runDetailsModal').modal('show');
            $.get(`/ajax/payroll/periods/${periodId}/details`, function(res) {
                const esc = text => $('<span>').text(text ?? '').html();
                const rows = res.rows.map(r => `<tr>
                    <td>${esc(r.staff_number)}</td><td>${esc(r.name)}</td>
                    <td class="text-end">${formatCurrency(r.basic_salary)}</td>
                    <td class="text-end">${formatCurrency(r.gross)}</td>
                    <td class="text-end">${formatCurrency(r.deductions)}</td>
                    <td class="text-end"><strong>${formatCurrency(r.net)}</strong></td></tr>`).join('');
                $('#run-details-body').html(rows || '<tr><td colspan="6" class="text-center">No rows</td></tr>');
                $('#run-details-foot').html(`<tr class="fw-bold"><td colspan="2">Totals (${res.rows.length} employees)</td>
                    <td class="text-end">${formatCurrency(res.totals.basic_salary)}</td>
                    <td class="text-end">${formatCurrency(res.totals.gross)}</td>
                    <td class="text-end">${formatCurrency(res.totals.deductions)}</td>
                    <td class="text-end">${formatCurrency(res.totals.net)}</td></tr>`);
            }).fail(function(xhr) {
                $('#runDetailsModal').modal('hide');
                toastr.error(xhr.responseJSON?.message || 'Could not load the payroll run');
            });
        });

        // Reject a pending run (deletes it and restores deduction balances)
        $('#recent-periods-table').on('click', '.reject-payroll', function() {
            const $button = $(this);
            if (!confirm('Reject this payroll run? It will be deleted, HELB and loan deduction balances restored, and the period can be processed again.')) return;
            $button.prop('disabled', true);
            $.ajax({
                url: `/ajax/payroll/periods/${$button.data('period-id')}/reject`,
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    toastr.success(res.message || 'Payroll run rejected');
                    recentPeriodsTable.ajax.reload(null, false);
                },
                error: function(xhr) {
                    $button.prop('disabled', false);
                    toastr.error(xhr.responseJSON?.error || 'Could not reject the payroll run');
                }
            });
        });

        $('#recent-periods-table').on('click', '.approve-payroll', function() {
            const $button = $(this);
            if (!confirm('Approve this payroll run and make its payslips available?')) return;
            $button.prop('disabled', true);
            $.ajax({
                url: `/ajax/payroll/periods/${$button.data('period-id')}/approve`,
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    toastr.success(res.message || 'Payroll approved');
                    recentPeriodsTable.ajax.reload(null, false);
                },
                error: function(xhr) {
                    $button.prop('disabled', false);
                    toastr.error(xhr.responseJSON?.error || 'Could not approve payroll');
                }
            });
        });

        // Process New Period Button
        $('#processNewPeriodBtn').on('click', function() {
            loadUnprocessedPeriods();
            $('#processPeriodModal').modal('show');
        });

        // Load Unprocessed Periods
        function loadUnprocessedPeriods() {
            $('#period_id').html('<option value="">Loading...</option>');

            $.ajax({
                url: "{{ route('ajax.payroll.unprocessedPeriods') }}",
                type: 'GET',
                success: function(res) {
                    let options = '<option value="">Select a period</option>';
                    if (res.periods && res.periods.length > 0) {
                        res.periods.forEach(function(period) {
                            options += `<option value="${period.id}">${period.formatted_period} (${period.period_month})</option>`;
                        });
                    } else {
                        options = '<option value="">No complete unprocessed periods. Set start and end dates in Payroll Calendar.</option>';
                    }
                    $('#period_id').html(options);
                },
                error: function() {
                    toastr.error('Failed to load unprocessed periods');
                    $('#period_id').html('<option value="">Error loading periods</option>');
                }
            });
        }

        // Process Period Form Submit
        $('#processPeriodForm').on('submit', function(e) {
            e.preventDefault();

            const periodId = $('#period_id').val();
            if (!periodId) {
                toastr.error('Please select a period');
                return;
            }

            const $btn = $('#processPeriodBtn');
            const $spinner = $('#process_spinner');
            $spinner.removeClass('d-none');
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ route('ajax.payroll.process') }}",
                type: 'POST',
                data: {
                    period_id: periodId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $spinner.addClass('d-none');
                    $btn.prop('disabled', false);

                    if (res.success) {
                        toastr.success(res.message || 'Payroll processed successfully');
                        (res.warnings || []).forEach(function(warning) {
                            toastr.warning($('<span>').text(warning).html(), 'Check this', { timeOut: 0, extendedTimeOut: 0, closeButton: true });
                        });
                        $('#processPeriodModal').modal('hide');
                        $('#processPeriodForm')[0].reset();
                        recentPeriodsTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error processing payroll');
                    }
                },
                error: function(xhr) {
                    $spinner.addClass('d-none');
                    $btn.prop('disabled', false);

                    let msg = 'Error processing payroll';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).join('<br>');
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    if (Array.isArray(xhr.responseJSON?.employees) && xhr.responseJSON.employees.length) {
                        const employeeNames = xhr.responseJSON.employees
                            .map(name => $('<span>').text(name).html())
                            .join(', ');
                        msg += `<br><strong>Affected employees:</strong> ${employeeNames}`;
                    }
                    toastr.error(msg);
                }
            });
        });

        // Reset modal on close
        $('#processPeriodModal').on('hidden.bs.modal', function() {
            $('#processPeriodForm')[0].reset();
        });

        // Format currency helper
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }
    });
</script>
@endsection
