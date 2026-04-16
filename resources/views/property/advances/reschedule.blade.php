@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/chabrin.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    .form-label-sm {
        font-size: 0.8125rem;
        font-weight: 500;
        margin-bottom: 3px;
        display: block;
    }
    .form-control-sm, .form-select-sm {
        font-size: 0.8125rem;
        padding: 0.3rem 0.5rem;
        height: calc(1.5em + 0.6rem + 2px);
    }
    .section-header {
        font-size: 0.875rem;
        font-weight: 600;
        color: #495057;
        margin: 15px 0 10px 0;
        padding-bottom: 5px;
        border-bottom: 1px solid #dee2e6;
    }
    .summary-box {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 16px 20px;
    }
    .summary-box .label { font-size: 0.8rem; color: #6c757d; }
    .summary-box .value { font-size: 1rem; font-weight: 600; color: #343a40; }
    .summary-box .value.outstanding { color: #dc3545; }
    .summary-box .value.paid { color: #198754; }
    .todo-banner {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 10px 14px;
        font-size: 0.82rem;
        color: #664d03;
        margin-bottom: 16px;
        border-radius: 0 4px 4px 0;
    }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-no-border">
                    <div class="card-header pb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Reschedule Advance — {{ $advance->reference_number }}</h5>
                        <a href="{{ route('property.advances.show', $encryptedId) }}" class="btn btn-secondary btn-sm">
                            <i class="fa fa-arrow-left"></i> Back to Advance
                        </a>
                    </div>

                    <div class="card-body">

                        {{-- TODO banner (visible to admin, not landlord-facing) --}}
                        <div class="todo-banner">
                            <strong>⚠ Pending items:</strong>
                            (1) Ledger posting to landlord account on approval is not yet implemented — see <em>docs/advance_reschedule_missing.md</em>.
                            (2) Payment source of truth (schedule rows vs ledger) needs confirmation before go-live.
                        </div>

                        {{-- Original advance summary --}}
                        <div class="section-header">Original Advance Summary</div>
                        <div class="summary-box mb-4">
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <div class="label">Reference</div>
                                    <div class="value">{{ $advance->reference_number }}</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="label">Landlord</div>
                                    <div class="value">{{ $advance->client->names ?? '—' }}</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="label">Original Total</div>
                                    <div class="value">{{ number_format($advance->total_amount, 2) }}</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="label">Amount Paid</div>
                                    <div class="value paid">{{ number_format($amountPaid, 2) }}</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="label">Outstanding Balance</div>
                                    <div class="value outstanding">{{ number_format($outstandingBalance, 2) }}</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="label">Disbursement Date</div>
                                    <div class="value">{{ $advance->disbursement_date ? $advance->disbursement_date->format('d M Y') : '—' }}</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="label">Type</div>
                                    <div class="value">
                                        @php $types = [1=>'Expense',2=>'Client Payment',3=>'Overpayment']; @endphp
                                        {{ $types[$advance->advance_type_id] ?? '—' }}
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="label">Property</div>
                                    <div class="value">{{ $advance->property->property_name ?? '—' }}</div>
                                </div>
                            </div>
                        </div>

                        {{-- Reschedule form --}}
                        <div class="section-header">New Advance Terms</div>

                        {{-- What will happen description --}}
                        <div class="card border-0 bg-light mb-3">
                            <div class="card-body py-3 px-4">
                                <h6 class="mb-2" style="font-size:0.875rem;font-weight:600;">What happens when you reschedule?</h6>
                                <ol class="mb-0 ps-3" style="font-size:0.82rem;line-height:1.8;color:#495057;">
                                    <li>The current advance (<strong>{{ $advance->reference_number }}</strong>) is marked as <span class="badge badge-light-info">Rescheduled</span> and closed — no further payments will be collected against it.</li>
                                    <li>All remaining pending schedule instalments on the current advance are also marked Rescheduled.</li>
                                    <li>A new advance is created with the terms you enter below, pre-filled with the outstanding balance of <strong>{{ number_format($outstandingBalance, 2) }}</strong>.</li>
                                    <li>The new advance is linked back to this one and the full reschedule history is preserved for audit purposes.</li>
                                    <li>The landlord receives an approval email for the new advance. Once approved, an admin can post it.</li>
                                </ol>
                            </div>
                        </div>

                        <form id="rescheduleForm">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label-sm">New Amount <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="1" class="form-control form-control-sm"
                                           id="newAmount" name="amount" value="{{ $outstandingBalance }}" required>
                                    <small class="text-muted">Pre-filled with outstanding balance. You may override.</small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label-sm">Percentage (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm"
                                           id="newInterestRate" name="interest_rate"
                                           value="{{ $advance->interest_rate ?? 0 }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label-sm">Repayment Months</label>
                                    <input type="number" min="1" max="360" class="form-control form-control-sm"
                                           id="newRepaymentMonths" name="repayment_months"
                                           value="{{ $advance->repayment_months ?? '' }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label-sm">New Disbursement Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm"
                                           name="disbursement_date" required
                                           value="{{ now()->format('Y-m-d') }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label-sm">Principal Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm"
                                           name="principal_start_date" required
                                           value="{{ \Carbon\Carbon::parse($advance->principal_start_date ?? now())->format('Y-m-d') }}">
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label-sm">Narration</label>
                                    <textarea class="form-control form-control-sm" name="narration" rows="2"
                                              placeholder="Reason for rescheduling...">Rescheduled from {{ $advance->reference_number }}</textarea>
                                </div>
                            </div>

                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitReschedule">
                                    <i class="fa fa-refresh"></i> Reschedule Advance
                                </button>
                                <a href="{{ route('property.advances.show', $encryptedId) }}" class="btn btn-secondary btn-sm">Cancel</a>
                            </div>
                        </form>

                    </div>{{-- /card-body --}}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
$(document).ready(function () {
    $('#rescheduleForm').on('submit', function (e) {
        e.preventDefault();

        Swal.fire({
            title: 'Confirm Reschedule',
            html: 'This will <strong>close the current advance</strong> (mark it Rescheduled) and create a new one with the updated terms. The landlord will receive an approval email.<br><br>Continue?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Reschedule',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#0d6efd',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            var btn = $('#btnSubmitReschedule');
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

            $.ajax({
                url: '{{ route('property.advances.reschedule.store', $encryptedId) }}',
                method: 'POST',
                data: $('#rescheduleForm').serialize(),
                success: function (res) {
                    if (res.success) {
                        Swal.fire({
                            title: 'Rescheduled!',
                            text: res.message,
                            icon: 'success',
                            confirmButtonText: 'View New Advance'
                        }).then(function () {
                            window.location.href = '{{ url('/property/advances') }}/' + res.new_advance_id;
                        });
                    } else {
                        btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Reschedule Advance');
                        toastr.error(res.message || 'Failed to reschedule.');
                    }
                },
                error: function (xhr) {
                    btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Reschedule Advance');
                    var res = xhr.responseJSON;
                    if (res && res.errors) {
                        var msgs = Object.values(res.errors).flat().join('\n');
                        toastr.error(msgs);
                    } else {
                        toastr.error(res && res.message ? res.message : 'An error occurred.');
                    }
                }
            });
        });
    });
});
</script>
@endsection
