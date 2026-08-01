@extends('layouts.auth')

@section('title', 'Confirm Advance Approval')

@section('content')
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh; background:#f5f6fa;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card card-no-border">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <i class="fa fa-file-signature fa-4x text-primary"></i>
                        </div>
                        <h4 class="mb-2">Confirm Advance Approval</h4>
                        <p class="text-muted mb-1">
                            Please review the details below before approving this advance.
                        </p>

                        <hr class="my-4">

                        <table class="table table-borderless table-sm mx-auto" style="max-width:400px;">
                            <tr>
                                <td class="text-muted text-start">Reference</td>
                                <td class="fw-bold text-start">{{ $advance->reference_number }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted text-start">Landlord</td>
                                <td class="fw-bold text-start">{{ $advance->client->names ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted text-start">Property</td>
                                <td class="fw-bold text-start">{{ $advance->property->property_name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted text-start">Amount</td>
                                <td class="fw-bold text-start">KSh {{ number_format($advance->amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted text-start">Total (inc. interest &amp; charges)</td>
                                <td class="fw-bold text-start">KSh {{ number_format($advance->total_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted text-start">Disbursement Date</td>
                                <td class="fw-bold text-start">{{ $advance->disbursement_date ? $advance->disbursement_date->format('d M Y') : '—' }}</td>
                            </tr>
                        </table>

                        <form method="POST" action="{{ $confirmUrl }}" class="mt-4">
                            @csrf
                            <button type="submit" class="btn btn-success px-5">
                                <i class="fa fa-check me-1"></i> Approve This Advance
                            </button>
                        </form>

                        <p class="text-muted small mt-4">
                            By clicking Approve, you authorise this advance to be processed. If you did
                            not request this, you may safely close this window and no action will be taken.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
