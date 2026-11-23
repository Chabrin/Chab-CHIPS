@extends('layouts.main')
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tenant-review.css') }}?v={{ filemtime(public_path('assets/css/tenant-review.css')) }}">
@endsection
@section('content')
@php
    $lease = $review->lease;
    $tenant = $review->tenant;
    $steps = $review->steps();
    $events = $review->events;
    $directApproval = $events->where('revision', $review->revision)->firstWhere('action', 'direct_approved');
    $signedStages = $events->where('revision', $review->revision)->whereIn('action', ['saved_and_signed', 'corrected_and_signed', 'signed'])->pluck('stage')->all();
    $currentStage = $review->stage === 'correction' ? 'field_officer' : $review->stage;
    $statusTone = match($review->stage) { 'approved' => 'success', 'rejected' => 'danger', 'correction' => 'warning', default => 'pending' };
    $stageLabels = array_column($steps, 'label', 'stage');
    $stageLabels['director_testing'] = 'Director (testing)';
    $stageLabels['director_override'] = 'Director';
    $actionLabels = ['saved_and_signed' => 'Prepared and signed', 'director_prepared_and_signed' => 'Prepared and signed by Director', 'corrected_and_signed' => 'Corrections signed', 'signed' => 'Signed', 'direct_approved' => 'Direct approval by Director', 'return' => 'Returned for correction', 'reject' => 'Rejected', 'reconciled' => 'Accounts reconciled'];
@endphp
<div class="page-body tenant-review"><div class="container-fluid">
    <a class="tr-back" href="{{ route('tenant-reviews.index') }}"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>New Tenant Approvals</a>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>The action could not be completed.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <header class="tr-panel tr-hero">
        <div class="tr-heading"><div><p class="tr-eyebrow">TENANT ONBOARDING</p><h1>{{ $tenant->names }}</h1><p class="tr-subtitle">Lease {{ $lease->ref_number ?: 'Not recorded' }}</p></div><span class="tr-status tr-status-{{ $statusTone }}">{{ $review->stageLabel() }}</span></div>
        <dl class="tr-overview">
            <div><dt>Property</dt><dd>{{ $lease->property?->property_name ?: 'Not recorded' }}@if($lease->property?->reference_number)<small>{{ $lease->property->reference_number }}</small>@endif</dd></div>
            <div><dt>Unit</dt><dd>{{ $lease->unit?->unit_code ?: ($lease->unit?->unit_number ?: 'Not recorded') }}</dd></div>
            <div><dt>Lease starts</dt><dd>{{ $lease->date_start?->format('d M Y') ?: 'Not recorded' }}</dd></div>
            <div><dt>Monthly rent</dt><dd class="tr-rent">{{ number_format($tenant->rent_amount, 2) }}</dd></div>
        </dl>
    </header>
    <section class="tr-panel tr-progress" aria-labelledby="tr-progress-title">
        <div class="tr-section-heading"><h2 id="tr-progress-title">Approval progress</h2><span class="tr-muted">Revision {{ $review->revision }}</span></div>
        @if($directApproval)<p class="tr-muted">Approved directly by {{ $directApproval->actor->name ?? 'a Director' }}{{ $directApproval->stage === 'director_testing' ? ' for testing' : '' }}. Unsigned workflow steps were bypassed.</p>@endif
        <ol class="tr-steps">@foreach($steps as $step)
            @php
                $isCurrent = $step['stage'] === $currentStage;
                $isDone = in_array($step['stage'], $signedStages, true) && !$isCurrent;
                $stepState = $isCurrent ? ($review->stage === 'correction' ? 'Correction needed' : 'Awaiting review') : ($isDone ? 'Signed' : ($directApproval ? ($directApproval->stage === 'director_testing' ? 'Bypassed for testing' : 'Bypassed by Director') : ($review->stage === 'rejected' ? 'Stopped' : 'Not reached')));
            @endphp
            <li class="{{ $isCurrent ? 'is-current' : ($isDone ? 'is-complete' : '') }}" @if($isCurrent) aria-current="step" @endif><span class="tr-step-number" aria-hidden="true">@if($isDone)<span class="material-symbols-outlined">check</span>@else{{ $loop->iteration }}@endif</span><div><strong>{{ $step['label'] }}</strong><span>{{ $stepState }}</span></div></li>
        @endforeach</ol>
    </section>
    <nav class="tr-section-nav" aria-label="Onboarding sections"><a href="#tr-tenant">Tenant details</a><a href="#tr-documents">Documents</a><a href="#tr-invoices">Invoices</a><a href="#tr-history">Approval history</a></nav>
    <div class="tr-layout"><main class="tr-details">
        <section class="tr-panel" id="tr-tenant" aria-labelledby="tr-tenant-title">
            <div class="tr-section-heading"><h2 id="tr-tenant-title">Tenant details</h2><a href="{{ route('property.tenants.show', Crypt::encrypt($review->tenant_id)) }}">Open full details ↗</a></div>
            <dl class="tr-fields"><div><dt>Mobile number</dt><dd>{{ $tenant->mobile_number ?: 'Not provided' }}</dd></div><div><dt>Email address</dt><dd>{{ $tenant->email_address ?: 'Not provided' }}</dd></div><div><dt>National ID / Passport</dt><dd>{{ $tenant->national_id ?: ($tenant->passport_number ?: 'Not provided') }}</dd></div><div><dt>Lease ends</dt><dd>{{ $lease->date_end?->format('d M Y') ?: 'Open-ended' }}</dd></div></dl>
        </section>
        <section class="tr-panel" id="tr-documents" aria-labelledby="tr-documents-title">
            <div class="tr-section-heading"><h2 id="tr-documents-title">Documents</h2><span class="tr-count">{{ $tenant->getRelation('documents')->count() }}</span></div>
            <ul class="tr-records">@forelse($tenant->getRelation('documents') as $document)<li><strong>{{ $document->file_type ?: 'Tenant document' }}</strong>@if($document->documents)<a href="{{ Storage::disk('public')->url($document->documents) }}" target="_blank" rel="noopener">Open document ↗</a>@else<span class="tr-muted">File unavailable</span>@endif</li>@empty<li class="tr-empty">No documents recorded.</li>@endforelse</ul>
        </section>
        <div class="tr-two-cards">
            <section class="tr-panel" aria-labelledby="tr-bank-title"><div class="tr-section-heading"><h2 id="tr-bank-title">Bank details</h2></div>
                @forelse($tenant->tenantBankDetails as $bank)<dl class="tr-fields tr-fields-single"><div><dt>Bank</dt><dd>{{ $bank->bank->bank_name ?? 'Not recorded' }}</dd></div><div><dt>Account name</dt><dd>{{ $bank->account_name ?: 'Not provided' }}</dd></div><div><dt>Account number</dt><dd>{{ $bank->account_number ?: 'Not provided' }}</dd></div></dl>@empty<p class="tr-empty">No bank details recorded.</p>@endforelse
            </section>
            <section class="tr-panel" aria-labelledby="tr-kin-title"><div class="tr-section-heading"><h2 id="tr-kin-title">Next of kin</h2></div>
                @forelse($tenant->tenantNextOfKin as $kin)<dl class="tr-fields tr-fields-single"><div><dt>Name</dt><dd>{{ $kin->kin_names ?: 'Not provided' }}</dd></div><div><dt>Mobile number</dt><dd>{{ $kin->mobile_number ?: 'Not provided' }}</dd></div><div><dt>Relationship</dt><dd>{{ $kin->relationship ?: 'Not provided' }}</dd></div></dl>@empty<p class="tr-empty">No next of kin recorded.</p>@endforelse
            </section>
        </div>
        <section class="tr-panel" aria-labelledby="tr-inspection-title"><div class="tr-section-heading"><h2 id="tr-inspection-title">Move-in inspection</h2></div>
            @forelse($inspections as $inspection)<dl class="tr-fields"><div><dt>Inspection date</dt><dd>{{ $inspection->inspection_date?->format('d M Y') ?: 'Not recorded' }}</dd></div><div><dt>Inspector</dt><dd>{{ $inspection->inspector_name ?: 'Not recorded' }}</dd></div><div><dt>Overall condition</dt><dd>{{ $inspection->overall_condition ?: 'Not recorded' }}</dd></div><div><dt>Notes</dt><dd>{{ $inspection->notes ?: 'No notes recorded' }}</dd></div></dl>@empty<p class="tr-empty">No move-in inspection recorded.</p>@endforelse
        </section>
        <section class="tr-panel" id="tr-invoices" aria-labelledby="tr-invoices-title"><div class="tr-section-heading"><h2 id="tr-invoices-title">Invoices</h2><span class="tr-count">{{ $invoices->count() }}</span></div>
            <div class="table-responsive"><table class="table tr-table"><thead><tr><th scope="col">Reference</th><th scope="col" class="text-end">Amount billed</th><th scope="col" class="text-end">Balance</th><th scope="col">Status</th></tr></thead><tbody>@forelse($invoices as $invoice)<tr><td>{{ $invoice->reference_number ?: 'Not recorded' }}</td><td class="text-end tr-amount">{{ number_format($invoice->total_amount, 2) }}</td><td class="text-end tr-amount">{{ number_format($invoice->current_balance, 2) }}</td><td>{{ $invoice->status_name }}</td></tr>@empty<tr><td colspan="4" class="tr-empty">No invoices recorded.</td></tr>@endforelse</tbody></table></div>
        </section>
        <section class="tr-panel" id="tr-history" aria-labelledby="tr-history-title"><div class="tr-section-heading"><h2 id="tr-history-title">Approval history</h2><span class="tr-muted">All revisions</span></div>
            <div class="table-responsive"><table class="table tr-table tr-history-table"><thead><tr><th scope="col">Date &amp; staff member</th><th scope="col">Stage</th><th scope="col">Decision</th><th scope="col">Comments</th></tr></thead><tbody>@forelse($events->sortByDesc('id') as $event)<tr><td><strong>{{ $event->actor->name ?? 'Staff account unavailable' }}</strong><small>{{ $event->created_at?->format('d M Y, H:i') }} · Revision {{ $event->revision }}</small></td><td>{{ $stageLabels[$event->stage] ?? ucfirst(str_replace('_', ' ', $event->stage)) }}</td><td>{{ $actionLabels[$event->action] ?? ucfirst(str_replace('_', ' ', $event->action)) }}</td><td>{{ $event->reason ?: '—' }}</td></tr>@empty<tr><td colspan="4" class="tr-empty">No approval history recorded.</td></tr>@endforelse</tbody></table></div>
        </section>
    </main><aside class="tr-sidebar" aria-label="Review actions">
        @if($canDirectApprove ?? false)
        <section class="tr-panel tr-action-card">
            <div class="tr-section-heading"><h2>Director approval</h2></div>
            <p class="tr-muted">Approve this tenant and lease directly, skipping the remaining workflow steps. The property's SLA must be approved. Your decision and reason will be recorded.</p>
            <form method="POST" action="{{ route('tenant-reviews.decide', $review) }}">
                @csrf
                <input type="hidden" name="revision" value="{{ $review->revision }}">
                <input type="hidden" name="stage" value="{{ $review->stage }}">
                <label for="direct-approval-reason" class="form-label">Direct approval reason</label>
                <textarea id="direct-approval-reason" name="reason" required class="form-control" rows="3" maxlength="2000">{{ old('reason') }}</textarea>
                <button name="action" value="direct_approve" class="btn btn-primary mt-3 w-100">Approve tenant and lease directly</button>
            </form>
        </section>
        @endif
        <section class="tr-panel tr-action-card"><div class="tr-section-heading"><h2>{{ $canAct ? 'Your review' : ($canCorrect ? 'Corrections required' : 'Onboarding status') }}</h2></div>
            @if($canAct)
            <p class="tr-muted">Review the tenant details and supporting records before signing.</p>
            <form method="POST" action="{{ route('tenant-reviews.decide', $review) }}">@csrf<input type="hidden" name="revision" value="{{ $review->revision }}"><input type="hidden" name="stage" value="{{ $review->stage }}">
                <label for="reason" class="form-label">Review comments</label><textarea id="reason" name="reason" class="form-control" rows="4" maxlength="2000" aria-describedby="tr-reason-help">{{ old('reason') }}</textarea><p class="tr-help" id="tr-reason-help">A reason is required when returning or rejecting onboarding.</p>
                <div class="tr-action-buttons"><button name="action" value="approve" class="btn btn-primary">{{ $review->nextStep() ? 'Sign and send to '.$review->nextStep()['label'] : 'Approve tenant and lease' }}</button><button name="action" value="return" class="btn btn-outline-secondary">Return to Field Officer</button><button name="action" value="reject" class="btn btn-outline-danger">Reject onboarding</button></div>
            </form>
            @elseif($canCorrect)
            <p class="tr-muted">Update the returned details, then sign and send them through approval again.</p>
            @php($returned = $events->where('action', 'return')->sortByDesc('id')->first())
            @if($returned?->reason)<blockquote class="tr-return-note">{{ $returned->reason }}</blockquote>@endif
            <div class="tr-action-buttons"><a class="btn btn-outline-primary" href="{{ route('units.tenants.edit', Crypt::encrypt($review->tenant_id)) }}">Correct tenant details</a><a class="btn btn-outline-secondary" href="{{ route('move.in.inspections.create', ['tenant' => Crypt::encrypt($review->tenant_id)]) }}">Record move-in inspection</a></div>
            <form method="POST" action="{{ route('tenant-reviews.resubmit', $review) }}" class="mt-3">@csrf<input type="hidden" name="revision" value="{{ $review->revision }}"><button class="btn btn-primary w-100">Sign corrections and send to {{ $steps[1]['label'] }}</button></form>
            @else
            <p class="tr-status-message">{{ $review->stageLabel() }}</p><p class="tr-muted mb-0">{{ match($review->stage) { 'approved' => 'The tenant and lease have completed approval.', 'rejected' => 'This application has been rejected. The decision and reason remain in the approval history.', 'correction' => 'The preparing Field Officer needs to correct and sign the details again.', default => 'The tenant has been saved and signed. The assigned reviewer will take the next action.' } }}</p>
            @endif
            <p class="tr-version">Approval setup version {{ $review->workflowVersion->number ?? 1 }} · Revision {{ $review->revision }}</p>
        </section>
        @if($review->accounts_pending)
        <section class="tr-panel tr-accounts"><div class="tr-section-heading"><h2>Accounts reconciliation</h2></div><p>Payments, balance adjustments or a bulk-posted invoice need reconciliation. Accounts must complete the adjustment or refund and record the outcome.</p><a href="{{ route('deposits.tenant.index') }}">Open tenant deposits ↗</a>
            @if($canAccounts)<form method="POST" action="{{ route('tenant-reviews.reconcile', $review) }}" class="mt-3">@csrf<input type="hidden" name="revision" value="{{ $review->revision }}"><label for="accounts-reason" class="form-label">Outcome and accounting reference</label><textarea id="accounts-reason" name="reason" required class="form-control" rows="4" maxlength="2000">{{ old('reason') }}</textarea><button class="btn btn-primary mt-3 w-100">Record completed reconciliation</button></form>@endif
        </section>
        @endif
    </aside></div>
</div></div>
@endsection
