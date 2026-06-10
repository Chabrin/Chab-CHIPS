@extends('layouts.main')

@section('styles')
<style>
.hdr-label {
    font-size: 0.67rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.5px; color: #8e9bae; margin-bottom: 2px;
}
.hdr-value { font-size: 0.85rem; color: #222; }
.section-label {
    font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.7px; color: #3598db; margin-bottom: 8px;
}
.action-card { border: 1px solid #e3eaf3; border-radius: 8px; padding: 16px 18px; }
.action-card .card-icon {
    width: 36px; height: 36px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; margin-bottom: 10px;
}
#lines-table td, #lines-table th { font-size: 0.8rem; }
.print-status-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;
}
.print-status-pill.unprinted { background: #fff3cd; color: #856404; }
.print-status-pill.printed   { background: #d1e7dd; color: #0a3622; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">

        {{-- Page Header --}}
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="mb-0">Cheque Printing — {{ $voucher->voucher_number }}</h5>
                <small class="text-muted">Review, print, then send to approval</small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('cheque.printing.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span> Back to List
                </a>
                <a href="{{ route('payment.vouchers.details', encrypt($voucher->id)) }}" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:16px;">receipt_long</span> Voucher Details
                </a>
            </div>
        </div>

        <div class="row g-3">

            {{-- Left: Voucher Summary + Lines --}}
            <div class="col-lg-8">

                {{-- Summary Card --}}
                <div class="card mb-3">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="section-label">Voucher Summary</div>
                            <div>
                                @if($voucher->is_printed)
                                <span class="print-status-pill printed">
                                    <span class="material-symbols-outlined" style="font-size:14px;">check_circle</span> Printed
                                </span>
                                @else
                                <span class="print-status-pill unprinted">
                                    <span class="material-symbols-outlined" style="font-size:14px;">hourglass_empty</span> Not Printed
                                </span>
                                @endif
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-auto">
                                <div class="hdr-label">Voucher No.</div>
                                <div class="hdr-value fw-bold">{{ $voucher->voucher_number }}</div>
                            </div>
                            <div class="col-auto">
                                <div class="hdr-label">Date</div>
                                <div class="hdr-value">{{ $voucher->voucher_date?->format('d M Y') ?? '-' }}</div>
                            </div>
                            <div class="col-auto">
                                <div class="hdr-label">Period</div>
                                <div class="hdr-value">
                                    @php $monthName = \Carbon\Carbon::createFromDate(null, $voucher->month, null)->format('F'); @endphp
                                    {{ $monthName }} {{ $voucher->year }}
                                </div>
                            </div>
                            <div class="col">
                                <div class="hdr-label">Payee</div>
                                <div class="hdr-value fw-semibold">{{ $voucher->vendor_name ?? '-' }}</div>
                            </div>
                            <div class="col-auto">
                                <div class="hdr-label">Type</div>
                                <div class="hdr-value">
                                    <span class="badge badge-light-info">{{ $voucher->vendor_type ?? '-' }}</span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="hdr-label">Amount</div>
                                <div class="hdr-value fw-bold fs-6">{{ number_format($voucher->debit_amount ?? 0, 2) }}</div>
                            </div>
                            @if($voucher->cheque_number)
                            <div class="col-auto">
                                <div class="hdr-label">Cheque No.</div>
                                <div class="hdr-value"><code>{{ $voucher->cheque_number }}</code></div>
                            </div>
                            @endif
                            <div class="col-auto">
                                <div class="hdr-label">Status</div>
                                <div class="hdr-value">
                                    @if($voucher->voucher_status === 'Approved')
                                        <span class="badge badge-light-primary">Approved</span>
                                    @elseif($voucher->voucher_status === 'Paid')
                                        <span class="badge badge-light-success">Paid</span>
                                    @else
                                        <span class="badge badge-light-warning">Pending</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="hdr-label">Prepared By</div>
                                <div class="hdr-value">{{ $voucher->prepared_by ?? '-' }}</div>
                            </div>
                        </div>

                        @if($approvalRequest)
                        <div class="mt-3 pt-3 border-top">
                            <div class="section-label">Approval Status</div>
                            <div class="d-flex align-items-center gap-3">
                                <div>
                                    <div class="hdr-label">Workflow Status</div>
                                    <div class="hdr-value">
                                        @if($approvalRequest->status === 'approved')
                                            <span class="badge badge-light-success">Approved</span>
                                        @elseif($approvalRequest->status === 'rejected')
                                            <span class="badge badge-light-danger">Rejected</span>
                                        @elseif($approvalRequest->status === 'in_progress')
                                            <span class="badge badge-light-primary">In Progress</span>
                                        @else
                                            <span class="badge badge-light-warning">{{ ucfirst($approvalRequest->status) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="hdr-label">Current Level</div>
                                    <div class="hdr-value">{{ $approvalRequest->current_level ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Lines Table --}}
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between py-2">
                        <h6 class="mb-0">
                            Voucher Lines
                            <span class="badge badge-light-primary ms-1">{{ $lines->count() }}</span>
                        </h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table table-sm table-hover mb-0" id="lines-table">
                                <thead style="border-bottom:2px solid #dee2e6;">
                                    <tr>
                                        <th>#</th>
                                        <th>Description</th>
                                        <th>Payee</th>
                                        <th>Account No.</th>
                                        <th>Bank</th>
                                        <th>Cheque No.</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lines as $i => $line)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $line->entry_description ?? '-' }}</td>
                                        <td class="fw-semibold">{{ $line->payee_name ?? '-' }}</td>
                                        <td>
                                            @if($line->account_number)
                                                <code style="font-size:0.78rem;">{{ $line->account_number }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $line->account_bank ?? '—' }}</td>
                                        <td>
                                            @if($line->cheque_number)
                                                <code style="font-size:0.78rem;">{{ $line->cheque_number }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">{{ number_format($line->amount ?? 0, 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr style="border-top:2px solid #dee2e6;">
                                        <td colspan="6" class="text-end fw-bold">Total</td>
                                        <td class="text-end fw-bold">{{ number_format($voucher->debit_amount ?? 0, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right: Actions --}}
            <div class="col-lg-4">

                {{-- Print Actions Card --}}
                @permission('update_cheques_printing')
                <div class="card mb-3">
                    <div class="card-header card-no-border py-2">
                        <h6 class="mb-0">Print Documents</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted" style="font-size:0.82rem; margin-bottom:14px;">
                            Print the cheque and/or payment voucher document before submitting for approval.
                        </p>

                        <div class="d-grid gap-2 mb-3">
                            <button type="button" id="printChequeBtn" class="btn btn-outline-primary d-flex align-items-center justify-content-center gap-2">
                                <span class="material-symbols-outlined" style="font-size:18px;">payments</span>
                                Print Cheque
                            </button>
                            <button type="button" id="printVoucherBtn" class="btn btn-outline-secondary d-flex align-items-center justify-content-center gap-2">
                                <span class="material-symbols-outlined" style="font-size:18px;">receipt_long</span>
                                Print Voucher
                            </button>
                        </div>

                        <hr class="my-3">

                        {{-- Mark Printed + Send to Approval --}}
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sendToApprovalCheck"
                                    {{ $voucher->is_printed ? 'disabled' : '' }}>
                                <label class="form-check-label" for="sendToApprovalCheck" style="font-size:0.85rem;">
                                    Send to approval process after marking as printed
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size:0.75rem;">
                                When checked, marking as printed will automatically initiate the approval workflow.
                            </small>
                        </div>

                        @if(!$voucher->is_printed)
                        <button type="button" id="markPrintedBtn"
                            class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                            <span class="material-symbols-outlined" style="font-size:18px;">check_circle</span>
                            Mark as Printed
                        </button>
                        @else
                        <div class="alert alert-primary py-2 mb-0" style="font-size:0.82rem;">
                            <span class="material-symbols-outlined" style="font-size:15px; vertical-align:middle;">check_circle</span>
                            This voucher has been marked as printed.
                        </div>
                        @endif
                    </div>
                </div>
                @endpermission

                {{-- Submit to Approval (always visible, regardless of print status) --}}
                @permission('update_cheques_printing')
                <div class="card">
                    <div class="card-header card-no-border py-2">
                        <h6 class="mb-0">Approval Workflow</h6>
                    </div>
                    <div class="card-body">
                        @if($approvalRequest)
                        <div class="alert alert-info py-2 mb-0" style="font-size:0.82rem;">
                            <span class="material-symbols-outlined" style="font-size:15px; vertical-align:middle;">info</span>
                            This voucher is already in the approval workflow
                            <strong>({{ ucfirst($approvalRequest->status) }})</strong>.
                        </div>
                        @else
                        <p class="text-muted" style="font-size:0.82rem; margin-bottom:14px;">
                            You can submit this voucher to the approval process at any time, regardless of print status.
                        </p>
                        <button type="button" id="submitApprovalBtn"
                            class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                            <span class="material-symbols-outlined" style="font-size:18px;">send</span>
                            Submit to Approval
                        </button>
                        @endif
                    </div>
                </div>
                @endpermission

            </div>

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {

    var voucherNumber    = '{{ $voucher->voucher_number }}';
    var isPrinted        = {{ $voucher->is_printed ? 'true' : 'false' }};
    var hasApproval      = {{ $approvalRequest ? 'true' : 'false' }};
    var urlDetails       = '{{ route("payment.vouchers.details", encrypt($voucher->id)) }}';
    var urlMarkPrinted   = '{{ route("cheque.printing.mark.printed", encrypt($voucher->id)) }}';
    var urlSubmitApproval = '{{ route("cheque.printing.submit.approval", encrypt($voucher->id)) }}';
    var csrfToken        = '{{ csrf_token() }}';

    // Print cheque
    $('#printChequeBtn').on('click', function () {
        toastr.info('Opening cheque for printing...');
        window.open(urlDetails + '?print=cheque', '_blank');
    });

    // Print voucher document
    $('#printVoucherBtn').on('click', function () {
        toastr.info('Opening voucher document for printing...');
        window.open(urlDetails + '?print=voucher', '_blank');
    });

    // Mark as printed
    if (!isPrinted) {
        $('#markPrintedBtn').on('click', function () {
            var sendToApproval = $('#sendToApprovalCheck').is(':checked');
            var btn = $(this);
            var label = sendToApproval ? 'Mark as Printed & Send to Approval?' : 'Mark this voucher as printed?';

            if (!confirm(label + '\n\nVoucher: ' + voucherNumber)) return;

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');

            $.ajax({
                url: urlMarkPrinted,
                method: 'POST',
                data: { _token: csrfToken, send_to_approval: sendToApproval ? 1 : 0 },
                success: function (r) {
                    if (r.success) {
                        toastr.success(r.message);
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        toastr.error(r.message || 'Failed to update');
                        btn.prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:18px;">check_circle</span> Mark as Printed');
                    }
                },
                error: function () {
                    toastr.error('An error occurred. Please try again.');
                    btn.prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:18px;">check_circle</span> Mark as Printed');
                }
            });
        });
    }

    // Submit to approval (independent of print status)
    if (!hasApproval) {
        $('#submitApprovalBtn').on('click', function () {
            if (!confirm('Submit voucher ' + voucherNumber + ' to the approval workflow?')) return;

            var btn = $(this);
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Submitting...');

            $.ajax({
                url: urlSubmitApproval,
                method: 'POST',
                data: { _token: csrfToken },
                success: function (r) {
                    if (r.success) {
                        toastr.success(r.message);
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        toastr.error(r.message || 'Failed to submit');
                        btn.prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:18px;">send</span> Submit to Approval');
                    }
                },
                error: function (xhr) {
                    var msg = xhr.responseJSON?.message || 'An error occurred';
                    toastr.error(msg);
                    btn.prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:18px;">send</span> Submit to Approval');
                }
            });
        });
    }

});
</script>
@endsection
