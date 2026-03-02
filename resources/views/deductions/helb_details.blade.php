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
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('deductions.helb') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
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
                                <span class="badge badge-light-danger">Suspended</span>
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

                        <!-- Tabs Navigation -->
                        <ul class="nav nav-tabs mb-4" id="helbTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">summarize</span>
                                    Summary
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="deductions-tab" data-bs-toggle="tab" data-bs-target="#deductions" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">receipt_long</span>
                                    Deduction History
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
                                                            <span class="badge badge-light-danger">Suspended</span>
                                                            @else
                                                            <span class="badge badge-light-success">Active</span>
                                                            @endif
                                                        </small>
                                                    </div>

                                                    <div class="col-5"><small class="text-muted">Cleared:</small></div>
                                                    <div class="col-7"><small>{{ $deduction->cleared ? 'Yes' : 'No' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Suspended:</small></div>
                                                    <div class="col-7"><small>{{ $deduction->suspended ? 'Yes' : 'No' }}</small></div>
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