@extends('layouts.main')
@section('content')
<div class="page-body container-fluid">
    <a href="{{ route('tenant-reviews.index') }}">New Tenant Approvals</a>
    <h1>{{ $review->tenant->names }}</h1>
    <p>{{ $review->stageLabel() }} · Revision {{ $review->revision }}</p>
    <p>Approval setup version {{ $review->workflowVersion->number ?? 1 }} · {{ implode(' → ', array_column($review->steps(), 'label')) }}</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <p>
        <a href="{{ route('property.tenants.show', Crypt::encrypt($review->tenant_id)) }}">Tenant details and documents</a>
        · Lease {{ $review->lease->ref_number }}
        · Start {{ $review->lease->date_start?->format('d M Y') }}
        · Unit {{ $review->lease->unit_id }}
    </p>
    <dl>
        <dt>Mobile</dt><dd>{{ $review->tenant->mobile_number }}</dd>
        <dt>Email</dt><dd>{{ $review->tenant->email_address }}</dd>
        <dt>National ID / Passport</dt><dd>{{ $review->tenant->national_id ?: $review->tenant->passport_number }}</dd>
        <dt>Rent</dt><dd>{{ number_format($review->tenant->rent_amount, 2) }}</dd>
    </dl>
    <h2>Documents</h2>
    <ul>@forelse($review->tenant->getRelation('documents') as $document)
        <li>{{ $document->file_type }} @if($document->documents)<a href="{{ Storage::disk('public')->url($document->documents) }}" target="_blank" rel="noopener">Open document</a>@endif</li>
        @empty<li>No documents recorded.</li>@endforelse
    </ul>
    <h2>Bank and next of kin</h2>
    <ul>@foreach($review->tenant->tenantBankDetails as $bank)<li>{{ $bank->bank->bank_name ?? '' }} · {{ $bank->account_name }} · {{ $bank->account_number }}</li>@endforeach
    @foreach($review->tenant->tenantNextOfKin as $kin)<li>{{ $kin->names }} · {{ $kin->mobile }}</li>@endforeach</ul>
    <h2>Move-in inspections</h2>
    <ul>@forelse($inspections as $inspection)<li>{{ $inspection->inspection_date }} · {{ $inspection->inspector_name }} · {{ $inspection->overall_condition }} · {{ $inspection->notes }}</li>
    @empty<li>No move-in inspection recorded.</li>@endforelse</ul>
    <h2>Invoices</h2>
    <table class="table"><thead><tr><th>Reference</th><th>Amount billed</th><th>Balance</th><th>Status</th></tr></thead><tbody>
    @foreach($invoices as $invoice)<tr><td>{{ $invoice->reference_number }}</td><td>{{ $invoice->total_amount }}</td><td>{{ $invoice->current_balance }}</td><td>{{ $invoice->status_name }}</td></tr>@endforeach
    </tbody></table>
    @if($canCorrect)
    <a class="btn btn-outline-primary" href="{{ route('units.tenants.edit', Crypt::encrypt($review->tenant_id)) }}">Correct tenant details</a>
    <a class="btn btn-outline-primary" href="{{ route('move.in.inspections.create', ['tenant' => Crypt::encrypt($review->tenant_id)]) }}">Record move-in inspection</a>
    <form method="POST" action="{{ route('tenant-reviews.resubmit', $review) }}" class="mt-3">
        @csrf
        <input type="hidden" name="revision" value="{{ $review->revision }}">
        <button class="btn btn-primary">Sign corrections and send to {{ $review->steps()[1]['label'] }}</button>
    </form>
    @endif
    @if($canAct)
    <form method="POST" action="{{ route('tenant-reviews.decide', $review) }}">
        @csrf
        <input type="hidden" name="revision" value="{{ $review->revision }}">
        <input type="hidden" name="stage" value="{{ $review->stage }}">
        <label for="reason">Review comments or reason for return/rejection</label>
        <textarea id="reason" name="reason" class="form-control" maxlength="2000">{{ old('reason') }}</textarea>
        <div class="my-3">
            <button name="action" value="approve" class="btn btn-primary">{{ $review->nextStep() ? 'Sign and send to '.$review->nextStep()['label'] : 'Approve tenant and lease' }}</button>
            <button name="action" value="return" class="btn btn-outline-secondary">Return to Field Officer</button>
            <button name="action" value="reject" class="btn btn-outline-danger">Reject onboarding</button>
        </div>
    </form>
    @endif
    @if($review->accounts_pending)
    <div class="alert alert-warning">This rejected onboarding needs Accounts reconciliation because it has payments, balance adjustments, or an invoice posted in a bulk journal. Accounts must complete the accounting adjustment or refund and record the outcome here.</div>
    <a href="{{ route('deposits.tenant.index') }}">Tenant deposits</a>
    @endif
    @if($canAccounts)
    <form method="POST" action="{{ route('tenant-reviews.reconcile', $review) }}">
        @csrf
        <input type="hidden" name="revision" value="{{ $review->revision }}">
        <label for="accounts-reason">Reconciliation outcome and accounting reference</label>
        <textarea id="accounts-reason" name="reason" required class="form-control" maxlength="2000"></textarea>
        <button class="btn btn-primary my-2">Record completed reconciliation</button>
    </form>
    @endif
    <h2>Approval history</h2>
    <table class="table"><thead><tr><th>Date</th><th>Revision</th><th>Staff user</th><th>Stage</th><th>Decision</th><th>Comments</th></tr></thead><tbody>
    @foreach($review->events as $event)
    <tr><td>{{ $event->created_at }}</td><td>{{ $event->revision }}</td><td>{{ $event->actor->name ?? $event->actor_id }}</td><td>{{ str_replace('_', ' ', $event->stage) }}</td><td>{{ str_replace('_', ' ', $event->action) }}</td><td>{{ $event->reason }}</td></tr>
    @endforeach
    </tbody></table>
</div>
@endsection
