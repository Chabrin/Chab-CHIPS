@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Recovery Details</h4>
                        <div class="ms-auto d-flex gap-2">
                            @if($isPaidOwner && $refusal === null)
                            <button type="button" id="po-confirm-btn" class="btn btn-success btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Confirm</span>
                            </button>
                            <button type="button" id="po-reject-btn" class="btn btn-danger btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#po-reject-modal">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">cancel</span>
                                <span>Reject</span>
                            </button>
                            @endif
                            @if((int) $recovery->current_status_id === 1)
                            <a href="{{ route('property.recoveries.edit', Crypt::encrypt($recovery->id)) }}" class="btn btn-warning btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">edit</span>
                                <span>Edit</span>
                            </a>
                            @endif
                            <a href="{{ route('property.recoveries.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Recovery Information -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2 mb-3">Recovery Information</h5>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Reference Number</label>
                                <p class="fw-bold">{{ $recovery->ref_number ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Recovery Date</label>
                                <p class="fw-bold">{{ $recovery->recovery_date ? \Carbon\Carbon::parse($recovery->recovery_date)->format('d-m-Y') : '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Amount</label>
                                <p class="fw-bold">KES {{ number_format($recovery->amount ?? 0, 2) }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Transaction Type</label>
                                <p class="fw-bold">{{ $recovery->transactionType->type_name ?? $recovery->type_id ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Status</label>
                                <p class="fw-bold">
                                    @php($badge = match((int) $recovery->current_status_id) { 5 => 'bg-success', 41 => 'bg-danger', default => 'bg-warning text-dark' })
                                    <span class="badge {{ $badge }}">{{ $statusLabel }}</span>
                                </p>
                            </div>
                        </div>

                        @if($isPaidOwner)
                        <!-- Paid Owner: the invoice, the proof and the confirmation -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2 mb-3">Paid Owner</h5>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Invoice</label>
                                <p class="fw-bold">{{ $invoice->reference_number ?? '-' }}</p>
                                <small class="text-muted">{{ $invoice->narration ?? '' }}</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Invoice total / still to pay</label>
                                <p class="fw-bold">KES {{ number_format((float) ($invoice->total_amount ?? 0), 2) }} / {{ number_format((float) ($invoice->current_balance ?? $invoice->total_amount ?? 0), 2) }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Proof (receipt / acknowledgement)</label>
                                <p class="fw-bold">{{ $recovery->proof_reference ?: '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Who confirms</label>
                                <p class="fw-bold">{{ $confirmer }}</p>
                            </div>
                            @if($actionedBy)
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">{{ (int) $recovery->current_status_id === 41 ? 'Rejected by' : 'Confirmed by (user)' }}</label>
                                <p class="fw-bold">{{ $actionedBy }}</p>
                                <small class="text-muted">{{ $recovery->actioned_at ? $recovery->actioned_at->format('d-m-Y H:i') : '' }}</small>
                            </div>
                            @endif
                            @if($recovery->rejection_reason)
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Rejection reason</label>
                                <p class="fw-bold">{{ $recovery->rejection_reason }}</p>
                            </div>
                            @endif
                            @if((int) $recovery->current_status_id === 1 && $refusal !== null)
                            <div class="col-md-12">
                                <div class="alert alert-info py-2 mb-0">{{ $refusal }}</div>
                            </div>
                            @endif
                        </div>
                        @endif

                        <!-- Client & Property Information -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2 mb-3">Client & Property Information</h5>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Client</label>
                                <p class="fw-bold">{{ $recovery->client->names ?? '-' }}</p>
                                <small class="text-muted">{{ $recovery->client->reference_number ?? '' }}</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Property</label>
                                <p class="fw-bold">{{ $recovery->property->property_name ?? '-' }}</p>
                                <small class="text-muted">{{ $recovery->property->reference_number ?? '' }}</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Zone</label>
                                <p class="fw-bold">{{ $recovery->zone->zone_name ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">SLA Reference</label>
                                <p class="fw-bold">{{ $recovery->sla_id ?? '-' }}</p>
                            </div>
                        </div>

                        <!-- Tenant & Lease Information -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2 mb-3">Tenant & Lease Information</h5>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Unit ID</label>
                                <p class="fw-bold">{{ $recovery->unit_id ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Tenant ID</label>
                                <p class="fw-bold">{{ $recovery->tenant_id ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Lease ID</label>
                                <p class="fw-bold">{{ $recovery->lease_id ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Offset Invoice ID</label>
                                <p class="fw-bold">{{ $recovery->offset_invoice_id ?? '-' }}</p>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2 mb-3">Additional Information</h5>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Commissionable</label>
                                <p class="fw-bold">
                                    @if($recovery->commisionable === 'Yes')
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Return Type</label>
                                <p class="fw-bold">{{ $recovery->return_type_id ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Created By</label>
                                <p class="fw-bold">{{ $recovery->createdBy->name ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Date Created</label>
                                <p class="fw-bold">{{ $recovery->date_time ? \Carbon\Carbon::parse($recovery->date_time)->format('d-m-Y H:i') : '-' }}</p>
                            </div>
                        </div>

                        <!-- Narration -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2 mb-3">Narration</h5>
                            </div>
                            <div class="col-md-12 mb-3">
                                <div class="p-3 bg-light rounded">
                                    {{ $recovery->narration ?? 'No narration provided' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@if($isPaidOwner && $refusal === null)
<div class="modal fade" id="po-reject-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Reject paid-owner recovery</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="po-reject-reason">Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" id="po-reject-reason" rows="3" maxlength="500"></textarea>
                <div id="po-reject-error" class="text-danger small mt-1 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="po-reject-submit">Reject</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var token = '{{ csrf_token() }}';
        var base = '{{ url('/property/recovery/' . Crypt::encrypt($recovery->id)) }}';

        function send(action, body, done) {
            fetch(base + '/' + action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(body || {})
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
              .then(done)
              .catch(function () { done({ ok: false, body: { message: 'The request failed. Please try again.' } }); });
        }

        document.getElementById('po-confirm-btn').addEventListener('click', function () {
            if (!confirm('Confirm this paid-owner recovery? The tenant invoice will be paid down and the client ledger debited. This cannot be undone.')) { return; }
            var button = this;
            button.disabled = true;
            send('confirm', {}, function (res) {
                alert(res.body.message || (res.ok ? 'Confirmed.' : 'Failed.'));
                if (res.ok) { window.location.reload(); } else { button.disabled = false; }
            });
        });

        document.getElementById('po-reject-submit').addEventListener('click', function () {
            var reason = document.getElementById('po-reject-reason').value.trim();
            var error = document.getElementById('po-reject-error');
            if (reason === '') { error.textContent = 'Give the reason for rejecting.'; error.classList.remove('d-none'); return; }
            this.disabled = true;
            var button = this;
            send('reject', { reason: reason }, function (res) {
                if (res.ok) { window.location.reload(); return; }
                error.textContent = res.body.message || 'Failed.'; error.classList.remove('d-none'); button.disabled = false;
            });
        });
    })();
</script>
@endif
@endsection
