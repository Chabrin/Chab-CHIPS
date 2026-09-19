@extends('layouts.auth')

@section('title', 'Tenant Refund Approval')

@section('content')
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh; background:#f5f6fa;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card card-no-border">
                    <div class="card-body text-center py-5">
                        @if($consent->isApproved())
                            <div class="mb-4">
                                <i class="fa fa-check-circle fa-4x text-success"></i>
                            </div>
                            <h4 class="mb-2 text-success">Deduction Approved</h4>
                            <p class="text-muted">
                                Thank you. You have approved deducting
                                <strong>KSh {{ number_format($consent->refund_amount, 2) }}</strong>
                                from your next payout to fund this tenant's refund.
                            </p>
                        @else
                            <div class="mb-4">
                                <i class="fa fa-times-circle fa-4x text-danger"></i>
                            </div>
                            <h4 class="mb-2 text-danger">Deduction Rejected</h4>
                            <p class="text-muted">
                                You have rejected this deduction. Our team will contact you to resolve it
                                before the exit settlement can be finalised.
                            </p>
                        @endif

                        <hr class="my-4">

                        <table class="table table-borderless table-sm mx-auto" style="max-width:400px;">
                            <tr>
                                <td class="text-muted text-start">Property</td>
                                <td class="fw-bold text-start">{{ $consent->exit?->property?->property_name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted text-start">Tenant</td>
                                <td class="fw-bold text-start">{{ $consent->tenant->names ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted text-start">Refund Amount</td>
                                <td class="fw-bold text-start">KSh {{ number_format($consent->refund_amount, 2) }}</td>
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
