@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-no-border">
                    <div class="card-header pb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Client Advance Details</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('property.advances.schedule.pdf', Crypt::encrypt($advance->id)) }}" class="btn btn-danger btn-sm" target="_blank">
                                <i class="fa fa-file-pdf-o"></i> PDF
                            </a>
                            <a href="{{ route('property.advances.schedule.excel', Crypt::encrypt($advance->id)) }}" class="btn btn-success btn-sm">
                                <i class="fa fa-file-excel-o"></i> Excel
                            </a>
                            <button type="button" class="btn btn-info btn-sm" id="btnEmailSchedule">
                                <i class="fa fa-envelope"></i> Email Landlord
                            </button>
                            @permission('update_client_advances')
                            @if($advance->current_status_id == 5)
                            <button type="button" class="btn btn-warning btn-sm" id="btnPostAdvance">
                                <i class="fa fa-check-circle"></i> Post Advance
                            </button>
                            @endif
                            @if($advance->current_status_id == 6)
                            <a href="{{ route('property.advances.reschedule', Crypt::encrypt($advance->id)) }}" class="btn btn-dark btn-sm">
                                <i class="fa fa-refresh"></i> Reschedule
                            </a>
                            @endif
                            @endpermission
                            <a href="{{ route('client.advances.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fa fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                    <div class="card-body custom-scrollbar">
                        <!-- Basic Information -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Basic Information</h6>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Reference Number</label>
                                <div class="fw-bold">{{ $advance->reference_number ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Date & Time</label>
                                <div class="fw-bold">{{ $advance->date_time ? $advance->date_time->format('Y-m-d H:i:s') : '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Requisition Date</label>
                                <div class="fw-bold">{{ $advance->requisition_date ? $advance->requisition_date->format('Y-m-d') : '-' }}</div>
                            </div>
                        </div>

                        <!-- Client & Property Information -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Client & Property Information</h6>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Client Name</label>
                                <div class="fw-bold">{{ $advance->client->names ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Client Reference</label>
                                <div class="fw-bold">{{ $advance->client->reference_number ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Property</label>
                                <div class="fw-bold">{{ $advance->property->property_name ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Zone</label>
                                <div class="fw-bold">{{ $advance->zone->zone_name ?? '-' }}</div>
                            </div>
                        </div>

                        <!-- Financial Information -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Financial Information</h6>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Amount</label>
                                <div class="fw-bold text-primary">{{ $advance->amount ? number_format($advance->amount, 2) : '0.00' }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Percentage</label>
                                <div class="fw-bold">{{ $advance->interest_rate ?? '0' }}%</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Interest Amount</label>
                                <div class="fw-bold">{{ $advance->interest_amount ? number_format($advance->interest_amount, 2) : '0.00' }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Total Amount</label>
                                <div class="fw-bold">{{ $advance->total_amount ? number_format($advance->total_amount, 2) : '0.00' }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Current Balance</label>
                                <div class="fw-bold">{{ $advance->current_balance ? number_format($advance->current_balance, 2) : '0.00' }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Repayment Months</label>
                                <div class="fw-bold">{{ $advance->repayment_months ?? '-' }}</div>
                            </div>
                        </div>

                        <!-- Charges & Fees -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Charges & Fees</h6>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Miscellaneous Charge</label>
                                <div class="fw-bold">{{ $advance->invoiceableitems->item_name ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Charge Amount</label>
                                <div class="fw-bold">{{ $advance->charge_amount ? number_format($advance->charge_amount, 2) : '0.00' }}</div>
                            </div>
                        </div>

                        <!-- Dates -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Important Dates</h6>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Principal Start Date</label>
                                <div class="fw-bold">{{ $advance->principal_start_date ? $advance->principal_start_date->format('Y-m-d') : '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Disbursement Date</label>
                                <div class="fw-bold">{{ $advance->disbursement_date ? $advance->disbursement_date->format('Y-m-d') : '-' }}</div>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Additional Information</h6>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Advance Type</label>
                                <div class="fw-bold">
                                    @if($advance->advance_type_id == 1)
                                    <span class="badge bg-warning">Expense Advance</span>
                                    @elseif($advance->advance_type_id == 2)
                                    <span class="badge bg-primary">Client Payment Advance</span>
                                    @elseif($advance->advance_type_id == 3)
                                    <span class="badge bg-info">Overpayment Advance</span>
                                    @else
                                    -
                                    @endif
                                </div>
                            </div>
                            @if($advance->advance_type_id == 1 && $advance->expense_category)
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Expense Category</label>
                                <div class="fw-bold">{{ str_replace('_', ' ', ucwords($advance->expense_category, '_')) }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Charge Billed To</label>
                                <div class="fw-bold">{{ $advance->charge_billed_to ?? '-' }}</div>
                            </div>
                            @endif
                            @if($advance->advance_type_id == 2)
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Payment Method</label>
                                <div class="fw-bold">{{ $advance->payment_method == 'bank_transfer' ? 'Direct Bank Transfer' : ($advance->payment_method == 'alternative' ? 'Alternative Instructions' : '-') }}</div>
                            </div>
                            @if($advance->payment_method == 'alternative' && $advance->payment_instructions)
                            <div class="col-md-6 mb-2">
                                <label class="text-muted small">Payment Instructions</label>
                                <div class="fw-bold">{{ $advance->payment_instructions }}</div>
                            </div>
                            @endif
                            @endif
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Status</label>
                                <div class="fw-bold">
                                    @if($advance->current_status_id == 1)
                                    <span class="badge badge-light-warning">Pending</span>
                                    @elseif($advance->current_status_id == 2)
                                    <span class="badge badge-light-success">Cleared</span>
                                    @elseif($advance->current_status_id == 3)
                                    <span class="badge badge-light-danger">Cancelled</span>
                                    @elseif($advance->current_status_id == 4)
                                    <span class="badge badge-light-danger">Suspended</span>
                                    @elseif($advance->current_status_id == 5)
                                    <span class="badge badge-light-success">Approved</span>
                                    @elseif($advance->current_status_id == 6)
                                    <span class="badge badge-light-success">Posted</span>
                                    @elseif($advance->current_status_id == 8)
                                    <span class="badge badge-light-info">Rescheduled</span>
                                    @else
                                    <span class="badge bg-secondary">Unknown</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Created By</label>
                                <div class="fw-bold">{{ $advance->createdBy->name ?? '-' }}</div>
                            </div>
                        </div>

                        <!-- Narration -->
                        @if($advance->narration)
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Narration</h6>
                            </div>
                            <div class="col-12">
                                <div class="p-3 bg-light text-dark rounded">
                                    {{ $advance->narration }}
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Repayment Schedule -->
                        <div class="row mb-2 mt-3">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Repayment Schedule</h6>
                            </div>
                            <div class="col-12">
                                @php
                                    $schedStatusLabels = [1=>'Pending',2=>'Cleared',3=>'Cancelled',4=>'Suspended',5=>'Approved',6=>'Posted',8=>'Rescheduled'];
                                @endphp
                                @if(!empty($schedule))
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered text-center mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Due Date</th>
                                                <th>Principal (KSh)</th>
                                                <th>Interest (KSh)</th>
                                                <th>Payment (KSh)</th>
                                                <th>Balance (KSh)</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($schedule as $idx => $entry)
                                            <tr>
                                                <td>{{ $idx + 1 }}</td>
                                                <td>{{ $entry['payment_date'] }}</td>
                                                <td>{{ number_format($entry['principal_amount'], 2) }}</td>
                                                <td>{{ number_format($entry['interest'], 2) }}</td>
                                                <td>{{ number_format($entry['total_amount'], 2) }}</td>
                                                <td>{{ number_format($entry['loan_balance'], 2) }}</td>
                                                <td>
                                                    @php $st = $entry['status_id'] ?? 1; @endphp
                                                    @if($st == 1)<span class="badge badge-light-warning">Pending</span>
                                                    @elseif($st == 2)<span class="badge badge-light-success">Cleared</span>
                                                    @elseif($st == 3)<span class="badge badge-light-danger">Cancelled</span>
                                                    @elseif($st == 6)<span class="badge badge-light-success">Posted</span>
                                                    @else<span class="badge bg-secondary">{{ $schedStatusLabels[$st] ?? 'Unknown' }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="fw-bold table-light">
                                                <td colspan="2">Totals</td>
                                                <td>{{ number_format(array_sum(array_column($schedule, 'principal_amount')), 2) }}</td>
                                                <td>{{ number_format(array_sum(array_column($schedule, 'interest')), 2) }}</td>
                                                <td>{{ number_format(array_sum(array_column($schedule, 'total_amount')), 2) }}</td>
                                                <td>&mdash;</td>
                                                <td>&mdash;</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                @else
                                <p class="text-muted small">No schedule data available.</p>
                                @endif
                            </div>
                        </div>

                        <!-- Reschedule History -->
                        @php
                            $historyChain = [];
                            $cursor = $advance;
                            $seen = [];
                            while ($cursor->previousAdvance && !in_array($cursor->previousAdvance->id, $seen)) {
                                $seen[] = $cursor->id;
                                $historyChain[] = $cursor->rescheduleHistory->first();
                                $cursor = $cursor->previousAdvance;
                            }
                            $historyChain = array_filter($historyChain);
                        @endphp
                        @if(count($historyChain) > 0)
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Reschedule History</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0 small">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>#</th>
                                                <th>From Reference</th>
                                                <th>To Reference</th>
                                                <th>Paid at Reschedule</th>
                                                <th>Outstanding at Reschedule</th>
                                                <th>Rescheduled By</th>
                                                <th>Date</th>
                                                <th>Narration</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($historyChain as $i => $hist)
                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                <td>
                                                    <a href="{{ route('property.advances.show', Crypt::encrypt($hist->from_advance_id)) }}">
                                                        {{ $hist->fromAdvance->reference_number ?? $hist->from_advance_id }}
                                                    </a>
                                                </td>
                                                <td>
                                                    <a href="{{ route('property.advances.show', Crypt::encrypt($hist->to_advance_id)) }}">
                                                        {{ $hist->toAdvance->reference_number ?? $hist->to_advance_id }}
                                                    </a>
                                                </td>
                                                <td>{{ number_format($hist->amount_paid_at_reschedule, 2) }}</td>
                                                <td>{{ number_format($hist->outstanding_balance_at_reschedule, 2) }}</td>
                                                <td>{{ $hist->rescheduledBy->name ?? '—' }}</td>
                                                <td>{{ $hist->rescheduled_at ? $hist->rescheduled_at->format('d M Y') : '—' }}</td>
                                                <td>{{ $hist->narration ?? '—' }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="row mt-3">
                            <div class="col-12 d-flex gap-2">
                                @permission('update_client_advances')
                                <a href="{{ route('property.advances.edit', Crypt::encrypt($advance->id)) }}" class="btn btn-primary btn-sm">
                                    <i class="fa fa-edit"></i> Edit Advance
                                </a>
                                @endpermission
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(document).ready(function() {
    var encryptedId = '{{ Crypt::encrypt($advance->id) }}';
    var csrfToken   = '{{ csrf_token() }}';
    var clientEmail = '{{ $advance->client->email_address ?? '' }}';

    // Email schedule to landlord
    $('#btnEmailSchedule').on('click', function() {
        var label = clientEmail ? clientEmail : 'the landlord';
        if (!confirm('Send repayment schedule to ' + label + '?')) return;

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');

        $.ajax({
            url: '/property/advances/' + encryptedId + '/schedule/email',
            method: 'POST',
            data: { _token: csrfToken },
            success: function(response) {
                toastr.success(response.message || 'Email sent successfully');
            },
            error: function(xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to send email');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fa fa-envelope"></i> Email Landlord');
            }
        });
    });

    // Post advance
    $('#btnPostAdvance').on('click', function() {
        if (!confirm('Post this advance? This confirms disbursement and cannot be reversed.')) return;

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Posting...');

        $.ajax({
            url: '/property/advances/' + encryptedId + '/post',
            method: 'POST',
            data: { _token: csrfToken },
            success: function(response) {
                toastr.success(response.message || 'Advance posted successfully');
                setTimeout(function() { location.reload(); }, 1500);
            },
            error: function(xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to post advance');
                btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Post Advance');
            }
        });
    });
});
</script>

@endsection