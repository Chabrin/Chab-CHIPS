@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Employee Termination Details</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('update_employee_terminations')
                            <a href="{{ route('employee.termination.edit', $record->id) }}" class="btn btn-warning btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">edit</span>
                                <span class="ms-1">Edit</span>
                            </a>
                            @endpermission
                            <a href="{{ route('employee.termination.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($record->disciplineCase)
                        <div class="alert alert-warning d-flex align-items-center justify-content-between">
                            <div>
                                <span class="material-symbols-outlined" style="vertical-align:middle;">gavel</span>
                                This separation originates from disciplinary case
                                <a href="{{ route('discipline.tracker.show', \Crypt::encrypt($record->disciplineCase->id)) }}">{{ $record->disciplineCase->case_number }}</a>.
                            </div>
                            <a href="{{ route('discipline.tracker.decision.letter', $record->disciplineCase->id) }}" target="_blank" class="btn btn-sm btn-outline-dark">
                                <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">description</span>
                                Decision Letter
                            </a>
                        </div>
                        @endif

                        <!-- Employee Information -->
                        <div class="card mb-3">
                            <div class="card-header bg-light py-2">
                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">person</span>
                                    Employee Information
                                </h6>
                            </div>
                            <div class="card-body py-2">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <small class="text-muted">Employee Name:</small><br>
                                        <strong>{{ $record->staff->full_names ?? '-' }}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Staff Number:</small><br>
                                        <strong>{{ $record->staff->staff_number ?? '-' }}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">National ID:</small><br>
                                        <strong>{{ $record->staff->national_id ?? '-' }}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Email:</small><br>
                                        <strong>{{ $record->staff->email_address ?? '-' }}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Mobile:</small><br>
                                        <strong>{{ $record->staff->mobile_number ?? '-' }}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Hire Date:</small><br>
                                        <strong>{{ $record->staff->hire_date ? \Carbon\Carbon::parse($record->staff->hire_date)->format('d M Y') : '-' }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Termination Details -->
                        <div class="card mb-3">
                            <div class="card-header bg-light py-2">
                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">work_off</span>
                                    Termination Details
                                </h6>
                            </div>
                            <div class="card-body py-2">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <small class="text-muted">Years Served:</small><br>
                                        <strong>{{ $record->years_served ?? '-' }}</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Pay Per Year:</small><br>
                                        <strong>KES {{ $record->pay_per_year ? number_format($record->pay_per_year, 2) : '-' }}</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Severance Payment:</small><br>
                                        <strong>KES {{ $record->severerance_payment ? number_format($record->severerance_payment, 2) : '-' }}</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Termination Reason ID:</small><br>
                                        <strong>{{ $record->termination_reason_id ?? '-' }}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Status:</small><br>
                                        <strong>
                                            @if($record->current_status_id == 1)
                                                <span class="badge bg-warning">Pending</span>
                                            @elseif($record->current_status_id == 5)
                                                <span class="badge bg-success">Confirmed</span>
                                            @else
                                                {{ $record->current_status_id }}
                                            @endif
                                        </strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Created By:</small><br>
                                        <strong>{{ $record->createdBy->name ?? '-' }}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Date Created:</small><br>
                                        <strong>{{ $record->date_time ? \Carbon\Carbon::parse($record->date_time)->format('d M Y H:i') : '-' }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contract Information -->
                        @if($record->contract)
                        <div class="card mb-3">
                            <div class="card-header bg-light py-2">
                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">description</span>
                                    Contract Information
                                </h6>
                            </div>
                            <div class="card-body py-2">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <small class="text-muted">Position:</small><br>
                                        <strong>{{ $record->contract->position->position_name ?? '-' }}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Basic Salary:</small><br>
                                        <strong>KES {{ $record->contract->basic_salary ? number_format($record->contract->basic_salary, 2) : '-' }}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Contract Period:</small><br>
                                        <strong>
                                            {{ $record->contract->date_start ? \Carbon\Carbon::parse($record->contract->date_start)->format('d M Y') : '-' }}
                                            to
                                            {{ $record->contract->date_end ? \Carbon\Carbon::parse($record->contract->date_end)->format('d M Y') : '-' }}
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Severance Elements -->
                        @if($record->elements && $record->elements->count() > 0)
                        <div class="card mb-3">
                            <div class="card-header bg-light py-2">
                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">payments</span>
                                    Severance Elements
                                </h6>
                            </div>
                            <div class="card-body py-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Element</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($record->elements as $i => $element)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $element->element->element_name ?? '-' }}</td>
                                            <td>KES {{ number_format($element->amount, 2) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="2" class="text-end">Total:</th>
                                            <th>KES {{ number_format($record->elements->sum('amount'), 2) }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        @endif

                        <!-- Documents -->
                        @if($record->documentTrackers && $record->documentTrackers->count() > 0)
                        <div class="card mb-3">
                            <div class="card-header bg-light py-2">
                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">attach_file</span>
                                    Documents
                                </h6>
                            </div>
                            <div class="card-body py-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Document Name</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($record->documentTrackers as $i => $doc)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $doc->documents }}</td>
                                            <td>
                                                <a href="{{ asset('storage/' . $doc->params) }}" target="_blank" class="btn btn-xs btn-light" style="padding:1px 2px;">
                                                    <span class="material-symbols-outlined" style="font-size:18px;">download</span>
                                                </a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif

                        <!-- Final Dues -->
                        <div class="card mb-3">
                            <div class="card-header bg-light py-2 d-flex align-items-center justify-content-between">
                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">calculate</span>
                                    Final Dues Settlement
                                </h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="$('#finalDuesForm').toggleClass('d-none')">
                                    {{ $record->finalDues ? 'Edit' : 'Compute' }}
                                </button>
                            </div>
                            <div class="card-body py-2">
                                @if($record->finalDues)
                                <div class="row g-2 mb-2">
                                    <div class="col-md-3"><small class="text-muted">Unpaid Salary:</small><br><strong>KES {{ number_format($record->finalDues->unpaid_salary, 2) }}</strong></div>
                                    <div class="col-md-3"><small class="text-muted">Leave Payout:</small><br><strong>KES {{ number_format($record->finalDues->leave_payout, 2) }}</strong></div>
                                    <div class="col-md-3"><small class="text-muted">Other Earnings:</small><br><strong>KES {{ number_format($record->finalDues->other_earnings, 2) }}</strong></div>
                                    <div class="col-md-3"><small class="text-muted">Deductions:</small><br><strong>KES {{ number_format($record->finalDues->deductions, 2) }}</strong></div>
                                    <div class="col-md-3"><small class="text-muted">Severance:</small><br><strong>KES {{ number_format($record->finalDues->severance_payment, 2) }}</strong></div>
                                    <div class="col-md-3"><small class="text-muted">Total Payable:</small><br><strong class="text-success">KES {{ number_format($record->finalDues->total_amount, 2) }}</strong></div>
                                </div>
                                @endif
                                <form id="finalDuesForm" class="row g-2 {{ $record->finalDues ? 'd-none' : '' }}">
                                    @csrf
                                    <div class="col-md-4"><label class="form-label">Unpaid Salary</label><input type="number" step="0.01" class="form-control" name="unpaid_salary" value="{{ $record->finalDues->unpaid_salary ?? 0 }}"></div>
                                    <div class="col-md-4"><label class="form-label">Leave Payout</label><input type="number" step="0.01" class="form-control" name="leave_payout" value="{{ $record->finalDues->leave_payout ?? 0 }}"></div>
                                    <div class="col-md-4"><label class="form-label">Other Earnings</label><input type="number" step="0.01" class="form-control" name="other_earnings" value="{{ $record->finalDues->other_earnings ?? 0 }}"></div>
                                    <div class="col-md-4"><label class="form-label">Deductions</label><input type="number" step="0.01" class="form-control" name="deductions" value="{{ $record->finalDues->deductions ?? 0 }}"></div>
                                    <div class="col-md-4"><label class="form-label">Severance Payment</label><input type="number" step="0.01" class="form-control" name="severance_payment" value="{{ $record->finalDues->severance_payment ?? $record->severerance_payment ?? 0 }}"></div>
                                    <div class="col-md-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2">{{ $record->finalDues->notes ?? '' }}</textarea></div>
                                    <div class="col-md-12"><button type="submit" class="btn btn-success btn-sm">Save Final Dues</button></div>
                                </form>
                            </div>
                        </div>

                        <!-- Exit Interview -->
                        <div class="card mb-3">
                            <div class="card-header bg-light py-2 d-flex align-items-center justify-content-between">
                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">forum</span>
                                    Exit Interview
                                </h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="$('#exitInterviewForm').toggleClass('d-none')">
                                    {{ $record->exitInterview ? 'Edit' : 'Record' }}
                                </button>
                            </div>
                            <div class="card-body py-2">
                                @if($record->exitInterview)
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6"><small class="text-muted">Assets Returned:</small><br><strong>{{ $record->exitInterview->assets_returned ? 'Yes' : 'No' }}</strong></div>
                                    <div class="col-md-6"><small class="text-muted">Would Recommend (1-5):</small><br><strong>{{ $record->exitInterview->would_recommend_rating ?? '-' }}</strong></div>
                                    <div class="col-md-12"><small class="text-muted">Feedback:</small><br>{{ $record->exitInterview->feedback ?? '-' }}</div>
                                </div>
                                @endif
                                <form id="exitInterviewForm" class="row g-2 {{ $record->exitInterview ? 'd-none' : '' }}">
                                    @csrf
                                    <div class="col-md-12"><label class="form-label">Reason Confirmation</label><textarea class="form-control" name="reason_confirmation" rows="2">{{ $record->exitInterview->reason_confirmation ?? '' }}</textarea></div>
                                    <div class="col-md-12"><label class="form-label">Feedback</label><textarea class="form-control" name="feedback" rows="3">{{ $record->exitInterview->feedback ?? '' }}</textarea></div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="assets_returned" value="1" id="assets_returned" {{ ($record->exitInterview->assets_returned ?? false) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="assets_returned">Company assets returned</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Would Recommend (1-5)</label>
                                        <select class="form-select" name="would_recommend_rating">
                                            <option value="">-</option>
                                            @for($i = 1; $i <= 5; $i++)
                                            <option value="{{ $i }}" {{ ($record->exitInterview->would_recommend_rating ?? null) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-12"><button type="submit" class="btn btn-success btn-sm">Save Exit Interview</button></div>
                                </form>
                            </div>
                        </div>

                        @if($record->current_status_id == 5)
                        <div class="text-end mb-3">
                            <a href="{{ route('discipline.tracker.certificate.of.service', $record->id) }}" target="_blank" class="btn btn-outline-dark btn-sm">
                                <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">workspace_premium</span>
                                Download Certificate of Service
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#finalDuesForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: "{{ url('/discipline/tracker/termination') }}/{{ $record->id }}/final-dues",
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    setTimeout(function() { location.reload(); }, 1000);
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.error || 'Failed to save final dues');
            }
        });
    });

    $('#exitInterviewForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: "{{ url('/discipline/tracker/termination') }}/{{ $record->id }}/exit-interview",
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    setTimeout(function() { location.reload(); }, 1000);
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.error || 'Failed to save exit interview');
            }
        });
    });
});
</script>

<style>
    .card-header {
        border-bottom: 1px solid #e0e0e0;
    }

    .card-body .row.g-2>div {
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
        line-height: 1.5;
    }

    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 20;
    }

    .card {
        border: 1px solid #e0e0e0;
    }
</style>
@endsection