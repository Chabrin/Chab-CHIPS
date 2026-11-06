@extends('layouts.main')
@section('content')
@php
    $statusBadges = [
        'draft' => 'badge-light-secondary',
        'with_accountant' => 'badge-light-warning',
        'returned' => 'badge-light-danger',
        'approved' => 'badge-light-success',
        'reversed' => 'badge-light-danger',
    ];
    $actionLabels = [
        'processed' => 'Processed',
        'submitted' => 'Submitted to accountant',
        'approved' => 'Approved',
        'returned' => 'Returned to HR',
        'reversed' => 'Reversed',
        'eft_test_file' => 'Downloaded test EFT file',
    ];
    $money = fn ($amount) => number_format((float) $amount, 2);
@endphp
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card mb-3">
            <div class="card-header card-no-border d-flex align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">Payroll Review: {{ $period->start_date->format('F Y') }}</h5>
                    <span class="badge {{ $statusBadges[$run->status] ?? 'badge-light-secondary' }}">{{ $run->status_label }}</span>
                    <small class="text-muted ms-2">
                        {{ $period->start_date->format('d M Y') }} to {{ $period->end_date->format('d M Y') }}
                        @if ($run->processed_by)
                            | Processed by {{ $userNames[$run->processed_by] ?? 'Unknown' }}
                        @endif
                        @if ($run->approved_by)
                            | Approved by {{ $userNames[$run->approved_by] ?? 'Unknown' }}
                        @endif
                    </small>
                </div>
                <div class="ms-auto d-flex flex-wrap gap-2">
                    <a href="{{ route('payroll.processing') }}" class="btn btn-light btn-sm">Back</a>
                    @if ((int) $totals->employees > 0)
                        <a href="{{ route('payroll.review.export', $period->id) }}" class="btn btn-light btn-sm">Download Excel</a>
                    @endif
                    @if ($canDownloadEft)
                        <a href="{{ route('payroll.review.eft', $period->id) }}" class="btn btn-light btn-sm">Download Test EFT File</a>
                    @endif
                    @if ($canSubmit)
                        <button type="button" class="btn btn-primary btn-sm" id="submitRunBtn">Submit to Accountant</button>
                    @endif
                    @if ($canDiscard)
                        <button type="button" class="btn btn-danger btn-sm comment-action" data-action="reverse" data-title="Discard and re-run" data-label="Reason for discarding this run">Discard &amp; Re-run</button>
                    @endif
                    @if ($canDecide)
                        <button type="button" class="btn btn-success btn-sm" id="approveRunBtn">Approve</button>
                        <button type="button" class="btn btn-danger btn-sm comment-action" data-action="return" data-title="Return to HR" data-label="What needs to change?">Return to HR</button>
                    @endif
                    @if ($canReverseApproved)
                        <button type="button" class="btn btn-danger btn-sm comment-action" data-action="reverse" data-title="Reverse approved payroll" data-label="Reason for reversing this approved run">Reverse</button>
                    @endif
                </div>
            </div>
            <div class="card-body pt-0">
                @if ($run->status === 'returned' && $run->return_comment)
                    <div class="alert alert-danger mb-3"><strong>Returned by the accountant:</strong> {{ $run->return_comment }}</div>
                @endif
                @if ($isOwnRun && $run->status === 'with_accountant')
                    <div class="alert alert-warning mb-3">You processed or submitted this payroll, so another accountant must review it.</div>
                @endif
                @foreach ($warnings as $warning)
                    <div class="alert alert-warning py-2 mb-2">{{ $warning }}</div>
                @endforeach

                <div class="row g-2 mt-1">
                    @foreach ([
                        'Employees' => number_format((int) $totals->employees),
                        'Gross' => $money($totals->gross),
                        'PAYE' => $money($totals->paye),
                        'NSSF' => $money($totals->nssf),
                        'SHA' => $money($totals->sha),
                        'Housing Levy' => $money($totals->housing_levy),
                        'Total Deductions' => $money($totals->total_deductions),
                        'Net Pay' => $money($totals->net_pay),
                    ] as $label => $value)
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-2 h-100">
                                <small class="text-muted d-block">{{ $label }}</small>
                                <strong>{{ $value }}</strong>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header card-no-border">
                <h5 class="mb-0">Employees</h5>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive custom-scrollbar">
                    <table class="display" id="run-employees-table">
                        <thead>
                            <tr>
                                <th>Staff No</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th class="text-end">Basic</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">PAYE</th>
                                <th class="text-end">NSSF</th>
                                <th class="text-end">SHA</th>
                                <th class="text-end">Housing Levy</th>
                                <th class="text-end">Other</th>
                                <th class="text-end">Net Pay</th>
                                <th class="text-end">Change</th>
                                <th>Flags</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header card-no-border">
                <h5 class="mb-0">History</h5>
            </div>
            <div class="card-body pt-0">
                @forelse ($run->logs as $log)
                    <div class="border-bottom py-2">
                        <strong>{{ $actionLabels[$log->action] ?? $log->action }}</strong>
                        <small class="text-muted">by {{ $log->user?->name ?? 'System' }} on {{ $log->created_at?->format('d M Y H:i') }}</small>
                        @if ($log->comment)
                            <div>{{ $log->comment }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">No activity recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="payslipModal" tabindex="-1" aria-labelledby="payslipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="payslipModalLabel">Payslip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="payslipModalBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="commentModal" tabindex="-1" aria-labelledby="commentModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="commentForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="commentModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="commentText" class="form-label" id="commentLabel"></label>
                    <textarea class="form-control" id="commentText" rows="4" maxlength="1000" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="commentSubmitBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        const periodId = @json($period->id);
        const csrfToken = '{{ csrf_token() }}';
        const actionUrls = {
            submit: "{{ route('ajax.payroll.submit', $period->id) }}",
            approve: "{{ route('ajax.payroll.approve', $period->id) }}",
            return: "{{ route('ajax.payroll.return', $period->id) }}",
            reverse: "{{ route('ajax.payroll.reverse', $period->id) }}",
        };
        const money = amount => Number(amount || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const moneyColumn = key => ({ data: key, className: 'text-end', render: (value, type) => type === 'display' ? money(value) : value });
        const escapeHtml = value => $('<span>').text(value ?? '').html();
        let commentAction = null;

        $('#run-employees-table').DataTable({
            ajax: {
                url: "{{ route('ajax.payroll.employees', $period->id) }}",
                dataSrc: 'employees'
            },
            columns: [
                { data: 'staff_number', render: value => escapeHtml(value) },
                { data: 'name', render: value => escapeHtml(value) },
                { data: 'department', render: value => escapeHtml(value) },
                moneyColumn('basic'),
                moneyColumn('gross'),
                moneyColumn('paye'),
                moneyColumn('nssf'),
                moneyColumn('sha'),
                moneyColumn('housing_levy'),
                moneyColumn('other_deductions'),
                {
                    data: 'net_pay',
                    className: 'text-end',
                    render: (value, type) => type === 'display'
                        ? `<strong class="${value < 0 ? 'text-danger' : ''}">${money(value)}</strong>`
                        : value
                },
                {
                    data: 'change',
                    className: 'text-end',
                    render: (value, type) => {
                        if (type !== 'display') return value ?? 0;
                        return value === null ? '-' : money(value);
                    }
                },
                {
                    data: 'flags',
                    render: flags => flags.map(flag => `<span class="badge badge-light-danger">${escapeHtml(flag)}</span>`).join(' ') || '-'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: row => `<button type="button" class="btn btn-primary btn-sm view-payslip" data-id="${row.id}" data-name="${escapeHtml(row.name)}">View</button>`
                }
            ],
            pageLength: 25,
            order: [[1, 'asc']]
        });

        $('#run-employees-table').on('click', '.view-payslip', function() {
            const $button = $(this);
            $('#payslipModalLabel').text($button.data('name'));
            $('#payslipModalBody').html('<p class="text-muted mb-0">Loading...</p>');
            $('#payslipModal').modal('show');
            $.get(`/ajax/payroll/summaries/${$button.data('id')}/payslip`)
                .done(res => $('#payslipModalBody').html(res.html))
                .fail(() => $('#payslipModalBody').html('<p class="text-danger mb-0">Could not load this payslip.</p>'));
        });

        function runAction(action, data, $button) {
            $button.prop('disabled', true);
            $.ajax({
                url: actionUrls[action],
                type: 'POST',
                data: Object.assign({ _token: csrfToken }, data),
                success: function(res) {
                    toastr.success(res.message);
                    if (action === 'reverse') {
                        window.location = "{{ route('payroll.processing') }}";
                        return;
                    }
                    window.location.reload();
                },
                error: function(xhr) {
                    $button.prop('disabled', false);
                    const errors = xhr.responseJSON?.errors;
                    toastr.error(errors ? Object.values(errors).join(' ') : (xhr.responseJSON?.error || 'Could not update this payroll run'));
                }
            });
        }

        $('#submitRunBtn').on('click', function() {
            if (confirm('Submit this payroll to the accountant? It will be locked while they review it.')) {
                runAction('submit', {}, $(this));
            }
        });

        $('#approveRunBtn').on('click', function() {
            if (confirm('Approve this payroll? Payslips will open and loan balances will update.')) {
                runAction('approve', {}, $(this));
            }
        });

        $('.comment-action').on('click', function() {
            commentAction = $(this).data('action');
            $('#commentModalTitle').text($(this).data('title'));
            $('#commentLabel').text($(this).data('label'));
            $('#commentText').val('');
            $('#commentModal').modal('show');
        });

        $('#commentForm').on('submit', function(e) {
            e.preventDefault();
            runAction(commentAction, { comment: $('#commentText').val() }, $('#commentSubmitBtn'));
        });
    });
</script>
@endsection
