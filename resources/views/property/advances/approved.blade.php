@extends('layouts.auth')

@section('title', 'Advance Approval')

@section('content')
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh; background:#f5f6fa;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card card-no-border">
                    <div class="card-body text-center py-5">
                        @if($alreadyProcessed ?? false)
                            <div class="mb-4">
                                <i class="fa fa-info-circle fa-4x text-info"></i>
                            </div>
                            <h4 class="mb-2">Already Processed</h4>
                            <p class="text-muted">
                                Advance <strong>{{ $advance->reference_number }}</strong> has already been
                                <strong>{{ $statusLabel ?? 'processed' }}</strong> and requires no further action.
                            </p>
                        @else
                            <div class="mb-4">
                                <i class="fa fa-check-circle fa-4x text-success"></i>
                            </div>
                            <h4 class="mb-2 text-success">Advance Approved!</h4>
                            <p class="text-muted mb-1">
                                Thank you. Advance <strong>{{ $advance->reference_number }}</strong> for
                                <strong>KSh {{ number_format($advance->amount, 2) }}</strong> has been approved.
                            </p>
                            <p class="text-muted">
                                Our team will process the disbursement and reach out to you shortly.
                            </p>
                        @endif

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
                                <td class="text-muted text-start">Amount</td>
                                <td class="fw-bold text-start">KSh {{ number_format($advance->amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted text-start">Disbursement Date</td>
                                <td class="fw-bold text-start">{{ $advance->disbursement_date ? $advance->disbursement_date->format('d M Y') : '—' }}</td>
                            </tr>
                        </table>

                        <p class="text-muted small mt-3">
                            You may close this window. If you have questions, please contact your property manager.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
