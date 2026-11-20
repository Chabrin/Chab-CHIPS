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
                        <h5 class="mb-0">HELB Loan Details</h5>
                        <div class="ms-auto d-flex flex-wrap gap-2">
                            <a href="{{ route('deductions.helb') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                            @permission('update_helb_loans')
                                @if($deduction->cleared)
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reopenModal">Reopen</button>
                                @else
                                    @if($deduction->suspended)
                                        <form method="POST" action="{{ route('deductions.helb.resume', $encryptedId) }}" class="d-inline">@csrf
                                            <button type="submit" class="btn btn-outline-success btn-sm">Resume</button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#pauseModal">Pause</button>
                                    @endif
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#clearModal">Close with HELB clearance</button>
                                @endif
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                        <div class="alert alert-light-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif
                        @if(session('error'))
                        <div class="alert alert-light-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <!-- Summary Header -->
                        <div class="mb-3">
                            <h5 class="mb-1 d-inline"><b>{{ $deduction->staff->full_names ?? 'N/A' }}</b></h5>
                            <span class="text-muted ms-2" style="font-size: 0.875rem;">
                                <strong>Staff #:</strong> {{ $deduction->staff->staff_number ?? 'N/A' }} |
                                <strong>Status:</strong>
                                @if($deduction->cleared)
                                <span class="badge badge-light-danger">Cleared</span>
                                @elseif($deduction->suspended)
                                <span class="badge badge-light-warning">Paused</span>
                                @else
                                <span class="badge badge-light-success">Active</span>
                                @endif
                            </span>
                            <p class="text-muted mb-0" style="font-size: 0.8rem;">
                                <strong>Loan Balance:</strong> KSh {{ number_format($deduction->loan_balance, 2) }} |
                                <strong>Monthly Deduction:</strong> KSh {{ number_format($deduction->monthly_deduction, 2) }} |
                                <strong>Start Date:</strong> {{ $deduction->start_deduction_date ? \Carbon\Carbon::parse($deduction->start_deduction_date)->format('d M Y') : '-' }}
                            </p>
                        </div>

                        @if($deduction->cleared)
                        <div class="alert alert-secondary py-2">
                            <strong>Closed{{ $deduction->cleared_at ? ' on ' . $deduction->cleared_at->format('d M Y') : '' }}.</strong> Nothing more is taken.
                            @if($deduction->cleared_reason) Note: {{ $deduction->cleared_reason }} @endif
                            @if($deduction->hasClearance())
                                <a href="{{ route('deductions.helb.clearance', $encryptedId) }}" target="_blank" class="ms-1">View HELB's clearance ({{ $deduction->clearance_name }})</a>
                            @else
                                <span class="text-muted">(closed before clearances were kept, so there is no file)</span>
                            @endif
                        </div>
                        @elseif($deduction->suspended)
                        <div class="alert alert-warning py-2">
                            <strong>Paused{{ $deduction->suspended_at ? ' on ' . $deduction->suspended_at->format('d M Y') : '' }}.</strong> Nothing is taken until it is resumed.
                            Reason: {{ $deduction->suspended_reason ?: 'not recorded (paused before reasons were kept)' }}
                        </div>
                        @endif

                        @if(!empty($schedule['problems']))
                        <div class="alert alert-danger py-2">{{ implode(' ', $schedule['problems']) }}</div>
                        @endif

                        <!-- Tabs Navigation -->
                        <ul class="nav nav-tabs mb-4" id="helbTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">summarize</span>
                                    Summary
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">calendar_month</span>
                                    Schedule
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="deductions-tab" data-bs-toggle="tab" data-bs-target="#deductions" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">receipt_long</span>
                                    Deduction History
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="changes-tab" data-bs-toggle="tab" data-bs-target="#changes" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">history</span>
                                    Changes
                                </button>
                            </li>
                        </ul>

                        <!-- Tabs Content -->
                        <div class="tab-content" id="helbTabsContent">
                            <!-- Summary Tab -->
                            <div class="tab-pane fade show active" id="summary" role="tabpanel" aria-labelledby="summary-tab">
                                <div class="row g-3">
                                    <!-- Loan Information Card -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">account_balance_wallet</span>
                                                    Loan Information
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Loan Balance:</small></div>
                                                    <div class="col-7"><small><strong>KSh {{ number_format($deduction->loan_balance, 2) }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Monthly Deduction:</small></div>
                                                    <div class="col-7"><small>KSh {{ number_format($deduction->monthly_deduction, 2) }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Start Date:</small></div>
                                                    <div class="col-7"><small>{{ $deduction->start_deduction_date ? \Carbon\Carbon::parse($deduction->start_deduction_date)->format('d M Y') : '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Status:</small></div>
                                                    <div class="col-7">
                                                        <small>
                                                            @if($deduction->cleared)
                                                            <span class="badge badge-light-danger">Cleared</span>
                                                            @elseif($deduction->suspended)
                                                            <span class="badge badge-light-warning">Paused</span>
                                                            @else
                                                            <span class="badge badge-light-success">Active</span>
                                                            @endif
                                                        </small>
                                                    </div>

                                                    <div class="col-5"><small class="text-muted">Months left:</small></div>
                                                    <div class="col-7"><small>{{ $schedule['months_left'] }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Last month:</small></div>
                                                    <div class="col-7"><small>{{ $schedule['end_month'] ? \Carbon\Carbon::parse($schedule['end_month'] . '-01')->format('M Y') : '-' }}</small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Staff Information Card -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">person</span>
                                                    Staff Information
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Full Name:</small></div>
                                                    <div class="col-7"><small><strong>{{ $deduction->staff->full_names ?? 'N/A' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Staff Number:</small></div>
                                                    <div class="col-7"><small>{{ $deduction->staff->staff_number ?? 'N/A' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Email:</small></div>
                                                    <div class="col-7"><small style="text-decoration:underline; color: #1976d2;">{{ $deduction->staff->email_address ?? 'N/A' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Mobile:</small></div>
                                                    <div class="col-7"><small>{{ $deduction->staff->mobile_number ?? 'N/A' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Department:</small></div>
                                                    <div class="col-7"><small>{{ $deduction->staff->department->department_name ?? 'N/A' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Position:</small></div>
                                                    <div class="col-7"><small>{{ $deduction->staff->position->position_name ?? 'N/A' }}</small></div>

                                                    @if($staffStatus)
                                                    <div class="col-5"><small class="text-muted">HELB beneficiary:</small></div>
                                                    <div class="col-7"><small>{{ is_null($staffStatus['beneficiary']) ? 'Not answered' : ($staffStatus['beneficiary'] ? 'Yes' : 'No') }}</small></div>

                                                    <div class="col-5"><small class="text-muted">HELB certificate:</small></div>
                                                    <div class="col-7"><small>
                                                        @if($staffStatus['certificate'] === 'on_file') On file
                                                        @elseif($staffStatus['certificate'] === 'exception') Waived: {{ $staffStatus['exception_reason'] }}
                                                        @else Not on file @endif
                                                    </small></div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Record Information Card -->
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">info</span>
                                                    Record Information
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-md-2"><small class="text-muted">Created Date:</small></div>
                                                    <div class="col-md-4"><small>{{ $deduction->date_time ? \Carbon\Carbon::parse($deduction->date_time)->format('d M Y H:i') : '-' }}</small></div>

                                                    <div class="col-md-2"><small class="text-muted">Created By:</small></div>
                                                    <div class="col-md-4"><small>{{ $deduction->createdBy->name ?? 'System' }}</small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Schedule Tab: the months still to come -->
                            <div class="tab-pane fade" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
                                @if(empty($schedule['rows']))
                                    <p class="text-muted mb-0">Nothing is left to take.</p>
                                @else
                                    <div class="table-responsive" style="max-height: 26rem; overflow-y: auto;">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr><th>Month</th><th class="text-end">Amount (KSh)</th><th class="text-end">Still owed after (KSh)</th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach($schedule['rows'] as $row)
                                                <tr>
                                                    <td>{{ $row['label'] }}</td>
                                                    <td class="text-end">{{ number_format($row['amount'], 2) }}</td>
                                                    <td class="text-end">{{ number_format($row['balance'], 2) }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @if($deduction->suspended)
                                        <small class="text-muted d-block mt-2">The loan is paused, so these months start once it is resumed.</small>
                                    @endif
                                @endif
                            </div>

                            <!-- Changes Tab: what was done to this loan, with who and why -->
                            <div class="tab-pane fade" id="changes" role="tabpanel" aria-labelledby="changes-tab">
                                @forelse($events as $event)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between">
                                            <strong>{{ $event->label }}</strong>
                                            <small class="text-muted">{{ $event->created_at?->format('d M Y H:i') }}</small>
                                        </div>
                                        @if($event->details)<div class="small">{{ $event->details }}</div>@endif
                                        @if($event->reason)<div class="small">Reason: {{ $event->reason }}</div>@endif
                                        <div class="small text-muted">{{ $event->user?->name ?? 'The system' }}</div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">Nothing has been recorded for this loan yet. Changes made from now on are listed here.</p>
                                @endforelse
                            </div>

                            <!-- Deduction History Tab -->
                            <div class="tab-pane fade" id="deductions" role="tabpanel" aria-labelledby="deductions-tab">
                                <div class="table-responsive custom-scrollbar">
                                    <table class="display" id="deductions-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Deduction Date</th>
                                                <th>Amount Deducted</th>
                                                <th>Remaining Balance</th>
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
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        let deductionsTable;

        // Initialize Deductions Table
        deductionsTable = $('#deductions-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/deductions/helb/' . $deduction->id . '/repeats') }}",
                dataSrc: 'repeats'
            },
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (data, type, row, meta) => meta.row + 1
                },
                {
                    data: 'deduction_date',
                    render: data => data ? new Date(data).toLocaleDateString('en-GB', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    }) : '-'
                },
                {
                    data: 'amount',
                    render: data => data ? 'KSh ' + parseFloat(data).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) : 'KSh 0.00'
                },
                {
                    data: 'loan_balance',
                    render: data => data ? 'KSh ' + parseFloat(data).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) : 'KSh 0.00'
                }
            ],
            pageLength: 10,
            order: [
                [1, 'desc']
            ]
        });
    });
</script>
@endsection

@section('modals')
@permission('update_helb_loans')
@if(!$deduction->cleared)
@if(!$deduction->suspended)
<div class="modal fade" id="pauseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md"><div class="modal-content">
        <form method="POST" action="{{ route('deductions.helb.pause', $encryptedId) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Pause this HELB deduction</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">Nothing is taken while it is paused. The months paused are not made up later: the loan simply finishes later.</p>
                <label for="pause_reason" class="form-label">Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" name="reason" id="pause_reason" rows="2" required minlength="5" maxlength="1000"></textarea>
                @error('reason')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="modal-footer justify-content-between"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-secondary">Pause</button></div>
        </form>
    </div></div>
</div>
@endif

<div class="modal fade" id="clearModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md"><div class="modal-content">
        <form method="POST" action="{{ route('deductions.helb.clear', $encryptedId) }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Close with HELB clearance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">Use this when HELB has sent its clearance showing the loan is paid in full. Nothing more is taken, and the clearance is kept with the loan.
                    KSh {{ number_format((float) $deduction->loan_balance, 2) }} is still shown as owed here.</p>
                <label for="clearance_file" class="form-label">HELB clearance (PDF) <span class="text-danger">*</span></label>
                <input type="file" class="form-control" name="clearance" id="clearance_file" accept="application/pdf,.pdf" required>
                @error('clearance')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                <label for="clear_note" class="form-label mt-3">Note (optional)</label>
                <textarea class="form-control" name="note" id="clear_note" rows="2" maxlength="1000"></textarea>
                @error('reason')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="modal-footer justify-content-between"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Close the loan</button></div>
        </form>
    </div></div>
</div>
@else
<div class="modal fade" id="reopenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md"><div class="modal-content">
        <form method="POST" action="{{ route('deductions.helb.reopen', $encryptedId) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Reopen this HELB loan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">Deductions start again from the next payroll. The clearance that was added stays on file.</p>
                <label for="reopen_reason" class="form-label">Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" name="reason" id="reopen_reason" rows="2" required minlength="5" maxlength="1000"></textarea>
                @error('reason')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="modal-footer justify-content-between"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Reopen</button></div>
        </form>
    </div></div>
</div>
@endif
@endpermission
@endsection