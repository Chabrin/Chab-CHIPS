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
        color: #6c757d;
    }

    .info-value {
        font-size: 0.8125rem;
        font-weight: 600;
    }

    .section-header {
        font-size: 0.875rem;
        font-weight: 600;
        color: #495057;
        margin: 15px 0 10px 0;
        padding-bottom: 5px;
        border-bottom: 1px solid #dee2e6;
    }

    .cs-compact-table th,
    .cs-compact-table td {
        padding: 0.25rem 0.4rem;
        font-size: 0.75rem;
        white-space: nowrap;
    }

    #editAdvanceModal input.form-control-sm {
        font-size: 0.8125rem;
        padding: 0.3rem 0.5rem;
        height: calc(1.5em + 0.6rem + 2px);
    }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center flex-wrap gap-2" style="padding: 10px 15px;">
                        <h5 class="mb-0" style="font-size: 1rem;">Advance Details &mdash; {{ $advance->reference_number ?? '-' }}</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('property.advances.schedule.pdf', Crypt::encrypt($advance->id)) }}" class="btn btn-outline-primary btn-sm" target="_blank">
                                <span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">picture_as_pdf</span> PDF
                            </a>
                            <a href="{{ route('property.advances.schedule.excel', Crypt::encrypt($advance->id)) }}" class="btn btn-outline-primary btn-sm">
                                <span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">table_chart</span> Excel
                            </a>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnEmailSchedule">
                                <span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">mail</span> Email Landlord
                            </button>
                            @permission('update_client_advances')
                            @if($advance->current_status_id == 5)
                            <button type="button" class="btn btn-primary btn-sm" id="btnPostAdvance">
                                <span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">check_circle</span> Post Advance
                            </button>
                            @endif
                            @if($advance->current_status_id != 6)
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editAdvanceModal">
                                <span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">edit</span> Edit
                            </button>
                            @endif
                            @endpermission
                            <a href="{{ route('client.advances.index') }}" class="btn btn-outline-primary btn-sm">
                                <span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">arrow_back</span> Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 15px;">

                        <ul class="nav nav-tabs mb-3" id="advanceTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details-pane" type="button" role="tab">
                                    Advance Request Details
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger-pane" type="button" role="tab">
                                    Landlord Ledger &amp; Restructure
                                    <span class="badge bg-secondary ms-1">{{ $advance->landlordAccountEntries->count() }}</span>
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="advanceTabsContent">
                        <div class="tab-pane fade show active" id="details-pane" role="tabpanel">

                        <!-- Client & Property (shown first) -->
                        <div class="row g-2 mb-2">
                            <div class="col-md-3">
                                <label class="form-label-sm">Client</label>
                                <div class="info-value">{{ $advance->client->names ?? '-' }}</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-sm">Client Reference</label>
                                <div class="info-value">{{ $advance->client->reference_number ?? '-' }}</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-sm">Property</label>
                                <div class="info-value">{{ $advance->property->property_name ?? '-' }}</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-sm">Zone</label>
                                <div class="info-value">{{ $advance->zone->zone_name ?? '-' }}</div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-3 mb-3" style="font-size:0.75rem; color:#6c757d;">
                            <span>
                                @if($advance->advance_type_id == 2)
                                    <span class="badge bg-primary">Client Payment Advance</span>
                                @elseif($advance->advance_type_id == 3)
                                    <span class="badge bg-primary">Overpayment Advance</span>
                                @else
                                    -
                                @endif
                            </span>
                            <span>
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
                            </span>
                            <span><strong>Created By:</strong> {{ $advance->createdBy->name ?? '-' }}</span>
                            <span><strong>Date &amp; Time:</strong> {{ $advance->date_time ? $advance->date_time->format('Y-m-d H:i') : '-' }}</span>
                        </div>

                        <!-- Two-column layout, same as the create screen -->
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <div class="section-header">Requisition Details</div>

                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label-sm">Requisition Date</label>
                                        <div class="info-value">{{ $advance->requisition_date ? $advance->requisition_date->format('Y-m-d') : '-' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-sm">Disbursement Date</label>
                                        <div class="info-value">{{ $advance->disbursement_date ? $advance->disbursement_date->format('Y-m-d') : '-' }}</div>
                                    </div>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label-sm">Amount</label>
                                        <div class="info-value">{{ number_format($advance->amount ?? 0, 2) }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-sm">Percentage</label>
                                        <div class="info-value">{{ $advance->interest_rate ?? '0' }}%</div>
                                    </div>
                                </div>

                                @if($advance->repayment_months)
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label-sm">Repayment Months</label>
                                        <div class="info-value">{{ $advance->repayment_months }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-sm">Payment Start Date</label>
                                        <div class="info-value">{{ $advance->principal_start_date ? $advance->principal_start_date->format('Y-m-d') : '-' }}</div>
                                    </div>
                                </div>
                                @endif

                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label-sm">Cheque Processing Charge (KES)</label>
                                        <div class="info-value">{{ number_format($advance->charge_amount ?? 0, 2) }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-sm">Miscellaneous Charge</label>
                                        <div class="info-value">{{ $advance->invoiceableitems->item_name ?? '-' }}</div>
                                    </div>
                                </div>

                                @if($advance->narration)
                                <div class="row g-2 mb-2">
                                    <div class="col-md-12">
                                        <label class="form-label-sm">Narration</label>
                                        <div class="info-value fw-normal">{{ $advance->narration }}</div>
                                    </div>
                                </div>
                                @endif
                            </div>

                            <div class="col-lg-7">
                                <div class="p-3 h-100" style="border-radius:4px; background:#fff;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-semibold" style="font-size:0.875rem;">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">table_chart</span>
                                            Repayment Schedule
                                        </span>
                                    </div>

                                    @php
                                        $schedStatusLabels = [1=>'Pending',2=>'Cleared',3=>'Cancelled',4=>'Suspended',5=>'Approved',6=>'Posted',8=>'Rescheduled'];
                                    @endphp
                                    @if(!empty($schedule))
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered text-center mb-0 cs-compact-table">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Due Date</th>
                                                        <th>Principal</th>
                                                        <th>Interest</th>
                                                        <th>Payment</th>
                                                        <th>Repaid</th>
                                                        <th>Balance</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($schedule as $idx => $entry)
                                                    <tr>
                                                        <td>{{ $idx + 1 }}</td>
                                                        <td>{{ $entry['payment_date'] }}@if($entry['restructured'] ?? false) <span class="badge bg-secondary">restructured</span>@endif</td>
                                                        <td>{{ number_format($entry['principal_amount'], 2) }}</td>
                                                        <td>{{ number_format($entry['interest'], 2) }}</td>
                                                        <td>{{ number_format($entry['total_amount'], 2) }}</td>
                                                        <td>
                                                            @if(($entry['repaid_amount'] ?? 0) > 0)
                                                                {{ number_format($entry['repaid_amount'], 2) }}
                                                            @else
                                                                &mdash;
                                                            @endif
                                                        </td>
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
                                                        <td>{{ number_format(array_sum(array_column($schedule, 'repaid_amount')), 2) }}</td>
                                                        <td>&mdash;</td>
                                                        <td>&mdash;</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    @else
                                        <p class="text-muted small mb-0">No schedule data available.</p>
                                    @endif
                                </div>
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
                        <div class="section-header">Reschedule History</div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 cs-compact-table">
                                <thead class="table-light">
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
                                            <a href="{{ route('property.advances.show', Crypt::encrypt($hist->from_advance_id)) }}" class="text-primary">
                                                {{ $hist->fromAdvance->reference_number ?? $hist->from_advance_id }}
                                            </a>
                                        </td>
                                        <td>
                                            <a href="{{ route('property.advances.show', Crypt::encrypt($hist->to_advance_id)) }}" class="text-primary">
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
                        @endif

                        </div><!-- /details-pane -->

                        <div class="tab-pane fade" id="ledger-pane" role="tabpanel">
                            <div class="section-header">Landlord Ledger Entries (cb_landlord_account)</div>

                            @if($advance->landlordAccountEntries->isEmpty())
                            <p class="text-muted small">No ledger entries yet — this advance has not been posted to the landlord account.</p>
                            @else
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered text-center mb-0 cs-compact-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Particulars</th>
                                            <th>Debit</th>
                                            <th>Credit</th>
                                            <th>GL Account</th>
                                            <th>Recovered</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($advance->landlordAccountEntries as $i => $le)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $le->entry_date ? $le->entry_date->format('Y-m-d') : '-' }}</td>
                                            <td class="text-start">{{ $le->entry_particulars ?? '-' }}</td>
                                            <td>{{ number_format($le->debit_amount ?? 0, 2) }}</td>
                                            <td>{{ number_format($le->credit_amount ?? 0, 2) }}</td>
                                            <td>{{ $le->gl_account ?? '-' }}</td>
                                            <td>
                                                @if((int) $le->is_recovered === 1)
                                                    <span class="badge badge-light-success">Yes</span>
                                                @else
                                                    <span class="badge badge-light-warning">No</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif

                            <div class="section-header">Restructure Advance Payments</div>
                            @permission('update_client_advances')
                                @if($canRestructure)
                                <p class="text-muted small">
                                    @if($advance->advance_type_id == 3)
                                        Already-recovered installments are left untouched. The remaining principal of
                                        <strong>{{ number_format($advance->remainingPendingPrincipal(), 2) }}</strong> can be restructured —
                                        dates and repayment months can be changed freely, but that principal can never be reduced,
                                        and the interest rate can only be held or lowered — never raised.
                                    @else
                                        Nothing has been recovered against this advance yet, so it can still be restructured. Dates
                                        can be changed freely. The amount is locked, and the interest rate can only be held or lowered — never raised.
                                    @endif
                                </p>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#restructureModal">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">tune</span> Restructure
                                </button>
                                @else
                                <p class="text-muted small mb-0">
                                    @if($advance->advance_type_id == 3)
                                        This advance can no longer be restructured — every installment has already been recovered in the landlord ledger.
                                    @else
                                        This advance can no longer be restructured — an amount has already been recovered against it in the landlord ledger.
                                    @endif
                                </p>
                                @endif
                            @endpermission
                        </div><!-- /ledger-pane -->

                        </div><!-- /tab-content -->

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Advance Modal — only start dates, percentage, and amount are editable -->
<div class="modal fade" id="editAdvanceModal" tabindex="-1" aria-labelledby="editAdvanceModalLabel" aria-hidden="true">
    <div class="modal-dialog {{ $advance->advance_type_id == 3 ? 'modal-lg' : '' }}">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAdvanceModalLabel" style="font-size:1rem;">Edit Advance &mdash; {{ $advance->reference_number ?? '-' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editAdvanceForm">
                <input type="hidden" id="editAdvanceType" value="{{ $advance->advance_type_id }}">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="{{ $advance->advance_type_id == 3 ? 'col-lg-5' : 'col-12' }}">
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label-sm">Disbursement Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm" id="editDisbursementDate" name="disbursement_date" value="{{ $advance->disbursement_date ? $advance->disbursement_date->format('Y-m-d') : '' }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-sm">Payment Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm" id="editPrincipalStartDate" name="principal_start_date" value="{{ $advance->principal_start_date ? $advance->principal_start_date->format('Y-m-d') : '' }}" required>
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label-sm">Amount <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" id="editAmount" name="amount" step="0.01" min="1" value="{{ $advance->amount ?? '' }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-sm">Percentage (%) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" id="editInterestRate" name="interest_rate" step="0.01" min="0" max="100" value="{{ $advance->interest_rate ?? 0 }}" required>
                                </div>
                            </div>
                            @if($advance->advance_type_id == 3)
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label-sm">Repayment Months <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" id="editRepaymentMonths" name="repayment_months" min="1" max="360" value="{{ $advance->repayment_months ?? 3 }}" required>
                                </div>
                            </div>
                            @endif
                        </div>

                        @if($advance->advance_type_id == 3)
                        <div class="col-lg-7">
                            <div class="p-2" style="border-radius:4px; background:#f8f9fa;">
                                <div class="text-muted small mb-1">Repayment Schedule Preview</div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered text-center mb-0 cs-compact-table">
                                        <thead class="table-light">
                                            <tr><th>#</th><th>Due Date</th><th>Principal</th><th>Interest</th><th>Payment</th></tr>
                                        </thead>
                                        <tbody id="editScheduleBody"></tbody>
                                        <tfoot id="editScheduleFoot"></tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnSaveEditAdvance">
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">save</span> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($canRestructure)
<!-- Restructure Modal — dates (and, for Overpayment, repayment months) only. Amount is locked;
     interest rate may only be held or lowered. Allowed only while nothing has been recovered
     against this advance in cb_landlord_account. -->
<div class="modal fade" id="restructureModal" tabindex="-1" aria-labelledby="restructureModalLabel" aria-hidden="true">
    <div class="modal-dialog {{ $advance->advance_type_id == 3 ? 'modal-lg' : '' }}">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="restructureModalLabel" style="font-size:1rem;">Restructure Advance &mdash; {{ $advance->reference_number ?? '-' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="restructureForm">
                <input type="hidden" id="restructureAdvanceType" value="{{ $advance->advance_type_id }}">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="{{ $advance->advance_type_id == 3 ? 'col-lg-5' : 'col-12' }}">
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label-sm">{{ $advance->advance_type_id == 3 ? 'Remaining Principal (locked)' : 'Amount (locked)' }}</label>
                                    <input type="text" class="form-control form-control-sm" value="{{ number_format($advance->advance_type_id == 3 ? $advance->remainingPendingPrincipal() : ($advance->amount ?? 0), 2) }}" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-sm">Percentage (%) <span class="text-danger">*</span> <small class="text-muted">(max {{ $advance->interest_rate ?? 0 }}%)</small></label>
                                    <input type="number" class="form-control form-control-sm" id="restructureInterestRate" name="interest_rate" step="0.01" min="0" max="{{ $advance->interest_rate ?? 0 }}" value="{{ $advance->interest_rate ?? 0 }}" required>
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label-sm">Disbursement Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm" id="restructureDisbursementDate" name="disbursement_date" value="{{ $advance->disbursement_date ? $advance->disbursement_date->format('Y-m-d') : '' }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-sm">Payment Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm" id="restructurePrincipalStartDate" name="principal_start_date" value="{{ $advance->principal_start_date ? $advance->principal_start_date->format('Y-m-d') : '' }}" required>
                                </div>
                            </div>
                            @if($advance->advance_type_id == 3)
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label-sm">Repayment Months <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" id="restructureRepaymentMonths" name="repayment_months" min="1" max="360" value="{{ $advance->repayment_months ?? 3 }}" required>
                                </div>
                            </div>
                            @endif
                            <div class="row g-2 mb-2">
                                <div class="col-md-12">
                                    <label class="form-label-sm">Narration (optional)</label>
                                    <textarea class="form-control form-control-sm" id="restructureNarration" name="narration" rows="2" placeholder="Reason for restructuring"></textarea>
                                </div>
                            </div>
                        </div>

                        @if($advance->advance_type_id == 3)
                        <div class="col-lg-7">
                            <div class="p-2" style="border-radius:4px; background:#f8f9fa;">
                                <div class="text-muted small mb-1">Repayment Schedule Preview</div>
                                <input type="hidden" id="restructureRecoveredRows" value='@json($recoveredScheduleRows)'>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered text-center mb-0 cs-compact-table">
                                        <thead class="table-light">
                                            <tr><th>#</th><th>Due Date</th><th>Principal</th><th>Interest</th><th>Payment</th><th>Repaid</th></tr>
                                        </thead>
                                        <tbody id="restructureScheduleBody"></tbody>
                                        <tfoot id="restructureScheduleFoot"></tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnSaveRestructure">
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">tune</span> Save Restructure
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        var encryptedId = '{{ Crypt::encrypt($advance->id) }}';
        var csrfToken   = '{{ csrf_token() }}';
        var clientEmail = '{{ $advance->client->email_address ?? '' }}';

        // Email schedule to landlord
        $('#btnEmailSchedule').on('click', function() {
            var label = clientEmail ? clientEmail : 'the landlord';
            var btn = $(this);

            Swal.fire({
                title: 'Send Repayment Schedule?',
                text: 'This will email the schedule to ' + label,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Send',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#3598db',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (!result.isConfirmed) return;

                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Sending...');

                $.ajax({
                    url: '/property/advances/' + encryptedId + '/schedule/email',
                    method: 'POST',
                    data: { _token: csrfToken },
                    success: function(response) {
                        Swal.fire({ icon: 'success', title: 'Sent', text: response.message || 'Email sent successfully', confirmButtonColor: '#3598db' });
                    },
                    error: function(xhr) {
                        Swal.fire({ icon: 'error', title: 'Error', text: (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to send email', confirmButtonColor: '#3598db' });
                    },
                    complete: function() {
                        btn.prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">mail</span> Email Landlord');
                    }
                });
            });
        });

        // Post advance
        $('#btnPostAdvance').on('click', function() {
            var btn = $(this);

            Swal.fire({
                title: 'Post this Advance?',
                text: 'This confirms disbursement and cannot be reversed.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Post',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#3598db',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (!result.isConfirmed) return;

                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Posting...');

                $.ajax({
                    url: '/property/advances/' + encryptedId + '/post',
                    method: 'POST',
                    data: { _token: csrfToken },
                    success: function(response) {
                        Swal.fire({ icon: 'success', title: 'Posted', text: response.message || 'Advance posted successfully', confirmButtonColor: '#3598db' })
                            .then(() => location.reload());
                    },
                    error: function(xhr) {
                        Swal.fire({ icon: 'error', title: 'Error', text: (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to post advance', confirmButtonColor: '#3598db' });
                        btn.prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">check_circle</span> Post Advance');
                    }
                });
            });
        });

        // Live repayment-schedule preview inside the Edit modal (Overpayment Advance only)
        function fmtEdit(n) {
            return parseFloat(n || 0).toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function calcEditAmortization(amount, rate, months, disbursementDate, principalStartDate) {
            var schedule = [];
            var balance = amount;
            var principalPerMonth = amount / months;
            var disbursement = new Date(disbursementDate);
            var principalStart = new Date(principalStartDate);
            var disbYM = disbursement.getFullYear() * 12 + disbursement.getMonth();
            var startYM = principalStart.getFullYear() * 12 + principalStart.getMonth();
            var gracePeriodMonths = Math.max(0, startYM - disbYM);

            var currentDate = new Date(disbursement);
            for (var i = 0; i < gracePeriodMonths - 1; i++) {
                currentDate.setMonth(currentDate.getMonth() + 1);
                currentDate.setDate(1);
                var gInterest = Math.round(balance * rate / 100 * 100) / 100;
                schedule.push({ payment_date: currentDate.toISOString().slice(0,10), principal_amount: 0, interest: gInterest, total_amount: gInterest });
            }
            currentDate = new Date(principalStart);
            for (var month = 1; month <= months; month++) {
                if (month > 1) {
                    currentDate.setMonth(currentDate.getMonth() + 1);
                    currentDate.setDate(1);
                }
                var pInterest = Math.round(balance * rate / 100 * 100) / 100;
                var principal = Math.round(principalPerMonth * 100) / 100;
                if (month === months) principal = Math.round(balance * 100) / 100;
                balance = Math.round((balance - principal) * 100) / 100;
                schedule.push({ payment_date: currentDate.toISOString().slice(0,10), principal_amount: principal, interest: pInterest, total_amount: Math.round((principal + pInterest) * 100) / 100 });
            }

            // Prorate the first installment's interest based on the disbursement day-of-month (mirrors backend)
            var dDay = disbursement.getDate();
            var dLast = new Date(disbursement.getFullYear(), disbursement.getMonth() + 1, 0).getDate();
            var dRem = dLast - dDay;
            if (dDay < 25 && schedule.length > 0) {
                var prorated = Math.round(schedule[0].interest * (dRem / dLast) * 100) / 100;
                schedule[0].interest = prorated;
                schedule[0].total_amount = Math.round((schedule[0].principal_amount + prorated) * 100) / 100;
            }

            return schedule;
        }

        function renderEditSchedulePreview() {
            if ($('#editAdvanceType').val() != '3') return;

            var amount = parseFloat($('#editAmount').val()) || 0;
            var rate = parseFloat($('#editInterestRate').val()) || 0;
            var months = parseInt($('#editRepaymentMonths').val()) || 0;
            var disbursementDate = $('#editDisbursementDate').val();
            var principalStart = $('#editPrincipalStartDate').val();

            if (!amount || !months || !disbursementDate || !principalStart) {
                $('#editScheduleBody, #editScheduleFoot').html('');
                return;
            }

            var schedule = calcEditAmortization(amount, rate, months, disbursementDate, principalStart);
            var tbody = '', tPrin = 0, tInt = 0, tPay = 0;
            schedule.forEach(function(row, i) {
                tPrin += row.principal_amount; tInt += row.interest; tPay += row.total_amount;
                tbody += '<tr><td>' + (i + 1) + '</td><td>' + row.payment_date + '</td><td>' +
                    fmtEdit(row.principal_amount) + '</td><td>' + fmtEdit(row.interest) + '</td><td>' + fmtEdit(row.total_amount) + '</td></tr>';
            });
            var tfoot = '<tr class="fw-bold table-light"><td colspan="2">Totals</td><td>' +
                fmtEdit(tPrin) + '</td><td>' + fmtEdit(tInt) + '</td><td>' + fmtEdit(tPay) + '</td></tr>';
            $('#editScheduleBody').html(tbody);
            $('#editScheduleFoot').html(tfoot);
        }

        $('#editAmount, #editInterestRate, #editRepaymentMonths, #editDisbursementDate, #editPrincipalStartDate').on('input change', renderEditSchedulePreview);
        $('#editAdvanceModal').on('shown.bs.modal', renderEditSchedulePreview);

        // Edit advance (start dates, percentage, amount, and — for Overpayment — repayment months)
        $('#editAdvanceForm').on('submit', function(e) {
            e.preventDefault();

            var btn = $('#btnSaveEditAdvance');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

            var payload = {
                _token: csrfToken,
                disbursement_date: $('#editDisbursementDate').val(),
                principal_start_date: $('#editPrincipalStartDate').val(),
                amount: $('#editAmount').val(),
                interest_rate: $('#editInterestRate').val()
            };
            if ($('#editAdvanceType').val() == '3') {
                payload.repayment_months = $('#editRepaymentMonths').val();
            }

            $.ajax({
                url: '/property/advances/' + encryptedId,
                method: 'PUT',
                data: payload,
                success: function(response) {
                    $('#editAdvanceModal').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Saved', text: response.message || 'Advance updated successfully', confirmButtonColor: '#3598db' })
                        .then(() => location.reload());
                },
                error: function(xhr) {
                    var msg = 'Failed to update advance';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors)[0][0];
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonColor: '#3598db' });
                },
                complete: function() {
                    btn.prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">save</span> Save Changes');
                }
            });
        });

        // Live repayment-schedule preview inside the Restructure modal (Overpayment Advance only)
        function renderRestructureSchedulePreview() {
            if ($('#restructureAdvanceType').val() != '3') return;

            var amount = {{ (float) $advance->remainingPendingPrincipal() }};
            var rate = parseFloat($('#restructureInterestRate').val()) || 0;
            var months = parseInt($('#restructureRepaymentMonths').val()) || 0;
            var disbursementDate = $('#restructureDisbursementDate').val();
            var principalStart = $('#restructurePrincipalStartDate').val();

            var recoveredRows = [];
            try { recoveredRows = JSON.parse($('#restructureRecoveredRows').val() || '[]'); } catch (e) { recoveredRows = []; }

            // Merge already-recovered installments (context, untouched) with the newly
            // computed pending ones, then sort the combined set by due date ascending.
            var rows = recoveredRows.map(function(row) {
                return { payment_date: row.payment_date, principal_amount: row.principal_amount, interest: row.interest, total_amount: row.total_amount, repaid: true };
            });

            if (amount && months && disbursementDate && principalStart) {
                var schedule = calcEditAmortization(amount, rate, months, disbursementDate, principalStart);
                schedule.forEach(function(row) {
                    rows.push({ payment_date: row.payment_date, principal_amount: row.principal_amount, interest: row.interest, total_amount: row.total_amount, repaid: false });
                });
            }

            if (rows.length === 0) {
                $('#restructureScheduleBody, #restructureScheduleFoot').html('');
                return;
            }

            rows.sort(function(a, b) { return a.payment_date < b.payment_date ? -1 : (a.payment_date > b.payment_date ? 1 : 0); });

            var tPrin = 0, tInt = 0, tPay = 0, tRepaid = 0, tbody = '';
            rows.forEach(function(row, i) {
                tPrin += row.principal_amount; tInt += row.interest; tPay += row.total_amount;
                if (row.repaid) tRepaid += row.total_amount;
                tbody += '<tr' + (row.repaid ? ' class="table-success bg-opacity-25"' : '') + '><td>' + (i + 1) + '</td><td>' + row.payment_date + '</td><td>' +
                    fmtEdit(row.principal_amount) + '</td><td>' + fmtEdit(row.interest) + '</td><td>' + fmtEdit(row.total_amount) +
                    '</td><td>' + (row.repaid ? fmtEdit(row.total_amount) : '&mdash;') + '</td></tr>';
            });

            var tfoot = '<tr class="fw-bold table-light"><td colspan="2">Totals</td><td>' +
                fmtEdit(tPrin) + '</td><td>' + fmtEdit(tInt) + '</td><td>' + fmtEdit(tPay) + '</td><td>' + fmtEdit(tRepaid) + '</td></tr>';
            $('#restructureScheduleBody').html(tbody);
            $('#restructureScheduleFoot').html(tfoot);
        }

        $('#restructureInterestRate, #restructureRepaymentMonths, #restructureDisbursementDate, #restructurePrincipalStartDate').on('input change', renderRestructureSchedulePreview);
        $('#restructureModal').on('shown.bs.modal', renderRestructureSchedulePreview);

        // Restructure advance (dates, repayment months, interest rate down-only — amount locked)
        $('#restructureForm').on('submit', function(e) {
            e.preventDefault();

            var btn = $('#btnSaveRestructure');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

            var payload = {
                _token: csrfToken,
                disbursement_date: $('#restructureDisbursementDate').val(),
                principal_start_date: $('#restructurePrincipalStartDate').val(),
                interest_rate: $('#restructureInterestRate').val(),
                narration: $('#restructureNarration').val()
            };
            if ($('#restructureAdvanceType').val() == '3') {
                payload.repayment_months = $('#restructureRepaymentMonths').val();
            }

            $.ajax({
                url: '/property/advances/' + encryptedId + '/restructure',
                method: 'POST',
                data: payload,
                success: function(response) {
                    $('#restructureModal').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Restructured', text: response.message || 'Advance restructured successfully', confirmButtonColor: '#3598db' })
                        .then(() => location.reload());
                },
                error: function(xhr) {
                    var msg = 'Failed to restructure advance';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors)[0][0];
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonColor: '#3598db' });
                },
                complete: function() {
                    btn.prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:16px; vertical-align:bottom;">tune</span> Save Restructure');
                }
            });
        });
    });
</script>
@endsection
