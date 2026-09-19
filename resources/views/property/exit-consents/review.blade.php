@extends('layouts.auth')

@section('title', 'Tenant Refund Approval')

@section('content')
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh; background:#f5f6fa;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card card-no-border">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <i class="fa fa-file-invoice-dollar fa-4x text-primary"></i>
                        </div>
                        <h4 class="mb-2">Tenant Refund Approval</h4>
                        <p class="text-muted mb-1">
                            A tenant has vacated one of your units. Please review the refund below —
                            approving it means this amount will be deducted from your next payout.
                        </p>

                        <hr class="my-4">

                        <table class="table table-borderless table-sm mx-auto" style="max-width:400px;">
                            <tr>
                                <td class="text-muted text-start">Property</td>
                                <td class="fw-bold text-start">{{ $consent->exit?->property?->property_name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted text-start">Unit</td>
                                <td class="fw-bold text-start">{{ $consent->exit?->unit?->unit_name ?? '—' }}</td>
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

                        <form method="POST" action="{{ $confirmUrl }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="decision" value="approve">
                            <button type="submit" class="btn btn-success px-4 me-2">
                                <i class="fa fa-check me-1"></i> Approve Deduction
                            </button>
                        </form>

                        <form method="POST" action="{{ $confirmUrl }}" class="mt-2" onsubmit="return TenantExitConsentReject(event, this);">
                            @csrf
                            <input type="hidden" name="decision" value="reject">
                            <input type="hidden" name="rejection_reason" class="js-rejection-reason">
                            <button type="submit" class="btn btn-outline-danger px-4">
                                <i class="fa fa-times me-1"></i> Reject
                            </button>
                        </form>

                        <p class="text-muted small mt-4">
                            If you reject this deduction, our team will contact you to resolve it before the
                            exit settlement can be finalised.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function TenantExitConsentReject(event, form) {
    var reason = window.prompt('Please briefly explain why you are rejecting this deduction:');
    if (reason === null || reason.trim() === '') {
        event.preventDefault();
        return false;
    }
    form.querySelector('.js-rejection-reason').value = reason.trim();
    return true;
}
</script>
@endsection
