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
                        This will calculate payroll for all active employees in the selected period
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
                }
            ],
            pageLength: 3,
            order: [
                [1, 'desc']
            ]
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
                        options = '<option value="">No unprocessed periods available</option>';
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
                        toastr.success('Payroll processed successfully');
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