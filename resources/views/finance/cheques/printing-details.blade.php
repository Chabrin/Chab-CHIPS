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
#lines-table td, #lines-table th { font-size: 0.8rem; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">

        {{-- Page Header --}}
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="mb-0">Cheque Printing — {{ $voucher->voucher_number }}</h5>
                <small class="text-muted">{{ $voucher->voucher_date?->format('d M Y') }} &bull; {{ $voucher->vendor_name }}</small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('cheque.printing.index') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">arrow_back</span> Back
                </a>
                <a href="{{ route('payment.vouchers.details', encrypt($voucher->id)) }}" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">receipt_long</span> Voucher Details
                </a>
            </div>
        </div>

        {{-- Summary Card --}}
        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="row g-3 flex-grow-1 pe-3">
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
                            <div class="hdr-value">{{ $voucher->vendor_type ?? '-' }}</div>
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
                            <div class="hdr-value">{{ $voucher->voucher_status ?? 'Pending' }}</div>
                        </div>
                        <div class="col-auto">
                            <div class="hdr-label">Printed</div>
                            <div class="hdr-value">{{ $voucher->is_printed ? 'Yes' : 'No' }}</div>
                        </div>
                        <div class="col-auto">
                            <div class="hdr-label">Prepared By</div>
                            <div class="hdr-value">{{ $voucher->prepared_by ?? '-' }}</div>
                        </div>
                        @if($approvalRequest)
                        <div class="col-auto">
                            <div class="hdr-label">Approval</div>
                            <div class="hdr-value">{{ ucfirst(str_replace('_', ' ', $approvalRequest->status)) }}</div>
                        </div>
                        @endif
                    </div>
                    {{-- Action buttons --}}
                    @permission('update_cheques_printing')
                    <div class="d-flex flex-column gap-2 flex-shrink-0 ms-3">
                        <button type="button" id="printVoucherBtn" class="btn btn-sm btn-primary">
                            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">receipt_long</span> Print Voucher
                        </button>
                        <button type="button" id="printChequeBtn" class="btn btn-sm btn-primary">
                            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">payments</span> Print Cheque
                        </button>
                        @if($approvalRequest)
                        <small class="text-muted d-flex align-items-center gap-1" style="font-size:0.75rem;">
                            <span class="material-symbols-outlined" style="font-size:14px;">check_circle</span>
                            Submitted for Approval
                        </small>
                        @else
                        <button type="button" id="submitApprovalBtn" class="btn btn-sm btn-primary">
                            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">send</span> Submit for Approval
                        </button>
                        @endif
                    </div>
                    @endpermission
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12">

                {{-- Lines Table --}}
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between py-2">
                        <h6 class="mb-0">Voucher Lines <span class="badge badge-light-primary ms-1">{{ $lines->count() }}</span></h6>
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
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {

    var voucherNumber     = '{{ $voucher->voucher_number }}';
    var urlDetails        = '{{ route("payment.vouchers.details", encrypt($voucher->id)) }}';
    var urlSubmitApproval = '{{ route("cheque.printing.submit.approval", encrypt($voucher->id)) }}';
    var csrfToken         = '{{ csrf_token() }}';

    $('#printVoucherBtn').on('click', function () {
        window.open(urlDetails + '?print=voucher', '_blank');
    });

    $('#printChequeBtn').on('click', function () {
        window.open(urlDetails + '?print=cheque', '_blank');
    });

    $('#submitApprovalBtn').on('click', function () {
        if (!confirm('Submit voucher ' + voucherNumber + ' to the approval workflow?')) return;

        var btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

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
                    btn.prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">send</span> Submit for Approval');
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
                btn.prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">send</span> Submit for Approval');
            }
        });
    });

});
</script>
@endsection
