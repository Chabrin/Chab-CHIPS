@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <div>
                            <h4 class="mb-0">My Payslip</h4>
                            @if($staffLinked)
                            <small class="text-muted">{{ $staffName }}</small>
                            @endif
                        </div>
                    </div>

                    <div class="card-body">

                        @if(!$staffLinked)
                        {{-- ── Not linked ─────────────────────────────────────────────── --}}
                        <div class="alert alert-warning d-flex align-items-center gap-2">
                            <span class="material-symbols-outlined">warning</span>
                            <div>
                                Your user account is not linked to a staff record.
                                Please contact HR to have your account linked.
                            </div>
                        </div>

                        @else
                        {{-- ── Month picker + controls ──────────────────────────────── --}}
                        <div class="row mb-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Select Month</label>
                                <input type="month" class="form-control" id="payslip_month" value="{{ date('Y-m') }}">
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary w-100" id="viewPayslipBtn">
                                    <span class="spinner-border spinner-border-sm me-1 d-none" id="payslipSpinner"></span>
                                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">visibility</span>
                                    View Payslip
                                </button>
                            </div>
                            <div class="col-md-3 d-none" id="payslipDownloadWrapper">
                                <a href="#" class="btn btn-outline-success w-100" id="payslipDownloadLink" target="_blank">
                                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">download</span>
                                    Download PDF
                                </a>
                            </div>
                        </div>

                        <div id="payslipPreviewWrapper" class="d-none">
                            <div id="payslipPreviewContent"></div>
                        </div>
                        <div id="payslipNoResult" class="alert alert-info d-none">
                            No payslip found for the selected period.
                        </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@if($staffLinked)
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(function () {
    var staffId = {{ $staffId }};

    $('#viewPayslipBtn').on('click', function () {
        var month = $('#payslip_month').val();
        if (!month) { toastr.warning('Please select a month.'); return; }

        $('#payslipSpinner').removeClass('d-none');
        $('#viewPayslipBtn').prop('disabled', true);
        $('#payslipPreviewWrapper, #payslipNoResult, #payslipDownloadWrapper').addClass('d-none');

        $.ajax({
            url: "{{ route('ajax.payslips.view') }}",
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { staff_id: staffId, payroll_month: month },
            success: function (res) {
                if (res.success) {
                    $('#payslipPreviewContent').html(res.html);
                    $('#payslipPreviewWrapper').removeClass('d-none');
                    $('#payslipDownloadLink').attr('href', "{{ url('/payslips/download') }}/" + staffId + '/' + month);
                    $('#payslipDownloadWrapper').removeClass('d-none');
                } else {
                    $('#payslipNoResult').removeClass('d-none');
                }
            },
            error: function () {
                toastr.error('Failed to load payslip. Please try again.');
            },
            complete: function () {
                $('#payslipSpinner').addClass('d-none');
                $('#viewPayslipBtn').prop('disabled', false);
            }
        });
    });
});
</script>
@endif
@endsection
