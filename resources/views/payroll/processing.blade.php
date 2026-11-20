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

    /* The whole dialog scrolls and keeps room underneath, so the Process button is never pinned under something fixed to the bottom of the screen */
    .pr-process-dialog { margin-top: 1rem; margin-bottom: 5rem; }
    /* What changed since the last payroll: long lists scroll inside their own box so the button stays close */
    #changesBox { max-height: 45vh; overflow-y: auto; }
</style>
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <!-- Recent Processed Periods Section -->
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Recent Payroll Runs</h5>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('payroll.monthly') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">fact_check</span>
                                Monthly Items &amp; Checks
                            </a>
                            @permission('create_payroll_processing')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" id="processNewPeriodBtn">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">autorenew</span>
                                Run Payroll
                            </button>
                            @endpermission
                        </div>
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

<div class="modal fade" id="processPeriodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg pr-process-dialog">
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
                    {{-- Filled in when a period is chosen: what has changed since the last payroll and could change pay --}}
                    <div id="changesBox" class="mb-3 d-none" aria-live="polite"></div>
                    {{-- Filled in when the run stops because someone's deductions are over the legal limit --}}
                    <div id="limitBox" class="mb-3 d-none" aria-live="polite"></div>
                    <div class="alert alert-info small mb-3">
                        <span class="material-symbols-outlined" style="font-size:16px;">info</span>
                        Salary and contract elements are prorated by calendar days when an active contract covers only part of this period.
                    </div>
                    <div class="alert alert-light border small mb-3">
                        Before running, look at <a href="{{ route('payroll.monthly') }}">Monthly Items &amp; Checks</a> for staff who would be left out, what changed since last month, and to enter one-time items such as a bonus.
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
        const runStatusLabels = @json(\App\Models\PayrollRun::STATUS_LABELS);
        const runStatusBadges = { approved: 'badge-light-success', paid: 'badge-light-primary' };

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
                    data: 'run_status',
                    render: (status, type, row) => `<span class="badge ${runStatusBadges[status] || 'badge-light-warning'}">${runStatusLabels[status] || 'Draft'}</span>`
                        + (row.processed_by_name ? `<div class="small text-muted">Processed by ${$('<span>').text(row.processed_by_name).html()}</div>` : '')
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: row => `<a class="btn btn-primary btn-sm" href="/payroll/processing/${row.period_id}/review">View Employees</a>`
                }
            ],
            pageLength: 6,
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

        // Choosing a period shows what has changed since the last payroll. It only informs: Process stays available either way.
        let changesRequest = 0;
        function loadChanges(periodId) {
            const $box = $('#changesBox');
            const thisRequest = ++changesRequest;
            if (!periodId) {
                $box.addClass('d-none').empty();
                return;
            }
            $box.removeClass('d-none').html('<div class="text-muted small"><span class="spinner-border spinner-border-sm me-2"></span>Checking what changed since the last payroll...</div>');
            $.ajax({
                url: "{{ url('/ajax/payroll/periods') }}/" + periodId + '/changes',
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (thisRequest === changesRequest) {
                        $box.html(res.html);
                    }
                },
                error: function() {
                    if (thisRequest === changesRequest) {
                        $box.html('<div class="alert alert-warning small mb-0">Could not check what changed since the last payroll. You can still process the payroll.</div>');
                    }
                }
            });
        }
        // Set only when a person allowed to override the legal limit on deductions chose to run the payroll anyway, with a reason
        let overrideData = {};

        $('#period_id').on('change', function() {
            $('#limitBox').addClass('d-none').empty();
            loadChanges($(this).val());
        });

        // The run stopped: someone's deductions are over the legal limit. Say who, which deduction to reduce, and offer the override.
        function showLimitBreaches(data) {
            const $box = $('#limitBox').removeClass('d-none').empty();
            const $card = $('<div class="alert alert-danger small mb-0">');
            $card.append($('<div class="fw-semibold mb-2">').text(data.error));
            const $list = $('<ul class="mb-2 ps-3">');
            (data.limit_breaches || []).forEach(function(breach) {
                const $item = $('<li class="mb-2">');
                $item.append($('<strong>').text(breach.staff + (breach.staff_number ? ' (' + breach.staff_number + ')' : '')));
                (breach.messages || []).forEach(function(message) { $item.append($('<div>').text(message)); });
                (breach.reduce || []).forEach(function(item) {
                    $item.append($('<div>').text('Reduce ' + item.name + ' (KES ' + formatCurrency(item.amount) + ' a month) by KES ' + formatCurrency(item.reduce_by) + '.'));
                });
                $list.append($item);
            });
            $card.append($list);
            if (data.can_override) {
                $card.append('<label class="form-label mb-1" for="override_reason">Or run it anyway. Reason <span style="color:red">*</span></label>');
                $card.append('<textarea class="form-control mb-2" id="override_reason" rows="2" maxlength="1000"></textarea>');
                $card.append('<button type="button" class="btn btn-danger btn-sm" id="overrideRunBtn">Run payroll over the limit</button>');
            }
            $box.append($card);
        }

        $(document).on('click', '#overrideRunBtn', function() {
            const reason = ($('#override_reason').val() || '').trim();
            if (reason.length < 5) {
                toastr.error('Write the reason for running the payroll over the limit (at least 5 characters).');
                return;
            }
            overrideData = { override_limits: 1, override_reason: reason };
            $('#processPeriodForm').trigger('submit');
        });

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

            const extra = overrideData;
            overrideData = {};

            $.ajax({
                url: "{{ route('ajax.payroll.process') }}",
                type: 'POST',
                data: $.extend({
                    period_id: periodId,
                    _token: '{{ csrf_token() }}'
                }, extra),
                success: function(res) {
                    $spinner.addClass('d-none');
                    $btn.prop('disabled', false);

                    if (res.success) {
                        toastr.success(res.message || 'Payroll processed successfully');
                        // Warnings are shown on the review page this redirects to.
                        $('#processPeriodModal').modal('hide');
                        $('#processPeriodForm')[0].reset();
                        window.location = res.review_url;
                    } else {
                        toastr.error(res.error || 'Error processing payroll');
                    }
                },
                error: function(xhr) {
                    $spinner.addClass('d-none');
                    $btn.prop('disabled', false);

                    if (xhr.responseJSON && Array.isArray(xhr.responseJSON.limit_breaches)) {
                        showLimitBreaches(xhr.responseJSON);
                        return;
                    }

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
            overrideData = {};
            $('#limitBox').addClass('d-none').empty();
            $('#processPeriodForm')[0].reset();
            loadChanges('');
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
