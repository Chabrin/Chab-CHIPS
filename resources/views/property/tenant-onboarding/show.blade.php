@extends('layouts.main')
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tenant-onboarding.css') }}?v={{ filemtime(public_path('assets/css/tenant-onboarding.css')) }}">
@endsection
@section('content')
<div class="page-body tenant-onboarding-page"><div class="container-fluid"><div class="card">
    <div class="card-header onboarding-heading d-flex flex-wrap gap-2 justify-content-between"><div><h4>New Tenant · NT-{{ $submission->id }}</h4><strong>{{ $submission->statusLabel() }}</strong></div><a href="{{ route('tenant-onboarding.index') }}" class="btn btn-light">Back to submissions</a></div>
    <div class="card-body">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach</div>@endif
        <ol class="onboarding-stages" aria-label="Approval stages">
            @foreach(\App\Models\TenantOnboarding::STAGES as $stage => $label)
                @php
                    $completed = $history->where('revision', $submission->revision)->where('stage', $stage)->whereIn('action', ['signed', 'effected'])->isNotEmpty();
                    $current = $submission->state === 'pending' && $submission->stage === $stage;
                @endphp
                <li @class(['is-current' => $current, 'is-complete' => $completed]) @if($current) aria-current="step" @endif>
                    <strong>{{ $label }}</strong>
                    <span>{{ $completed ? 'Complete' : ($current ? 'Awaiting action' : 'Waiting') }}</span>
                </li>
            @endforeach
        </ol>
        @if($submission->state === 'pending')
            <p class="onboarding-reviewer"><strong>Current reviewer:</strong> {{ implode(', ', $reviewers) ?: 'No eligible reviewer is assigned. Check the staff assignment and role membership.' }}</p>
        @endif
        <h5>Submission summary</h5>
        <dl class="onboarding-facts">
            <div><dt>Property / unit</dt><dd>{{ $property->property_name }} · {{ $unit->unit_code }}</dd></div>
            <div><dt>Form month</dt><dd>{{ $payload['effective_month'] ?? '—' }}</dd></div>
            <div><dt>Tenant</dt><dd>{{ ($payload['company_name'] ?? '') ?: ($payload['names'] ?? '') }}</dd></div>
            <div><dt>Phone</dt><dd>{{ $payload['mobile_number'] }}</dd></div>
            <div><dt>ID / passport / registration</dt><dd>{{ ($payload['national_id'] ?? '') ?: (($payload['passport_number'] ?? '') ?: ($payload['company_registration_number'] ?? '')) }}</dd></div>
            <div><dt>Previous tenant vacated</dt><dd>{{ $payload['previous_tenant_vacated'] ?? '—' }}</dd></div>
            <div><dt>Monthly rent / first bill</dt><dd>{{ number_format($payload['rent_amount'], 2) }}</dd></div>
            <div><dt>Deposit payable</dt><dd>{{ number_format($payload['rent_amount'] * ($payload['deposit_months'] ?? 0), 2) }}</dd></div>
            <div><dt>Lease term</dt><dd>{{ !empty($payload['lease_open_ended']) ? 'Open ended' : (!empty($payload['lease_term_days']) ? $payload['lease_term_days'].' days' : $payload['lease_years'].' year(s)') }}</dd></div>
            <div><dt>Lease begins</dt><dd>{{ $submission->effected_at?->format('d M Y') ?? 'When the Assistant Auditor confirms and effects' }}</dd></div>
        </dl>
        <p>The first rent invoice uses the full monthly rent. Deposit payment handling is a later phase.</p>
        <details class="mb-3"><summary>All submitted details · version {{ $submission->revision }}</summary>
            @include('property.tenant-onboarding.fields', ['values' => $payload, 'prefix' => '', 'editable' => false])
        </details>
        <h5>Attachments</h5>
        <ul>@forelse($submission->documents as $document)<li><a href="{{ route('tenant-onboarding.document', [$submission, $loop->index]) }}">{{ $document['name'] }}</a></li>@empty<li>No attachments submitted.</li>@endforelse</ul>

        @if($canAct)
        <form method="post" action="{{ route('tenant-onboarding.decide', $submission) }}" class="border rounded p-3 mb-3">
            @csrf<input type="hidden" name="revision" value="{{ $submission->revision }}"><input type="hidden" name="stage" value="{{ $submission->stage }}">
            <label for="review-comment" class="form-label">Review comments · required for a return or rejection</label><textarea class="form-control mb-3" name="comment" id="review-comment" maxlength="3000">{{ old('comment') }}</textarea>
            @if($submission->stage === 'assistant_auditor')
                <button class="btn btn-primary" formaction="{{ route('tenant-onboarding.effect', $submission) }}">Confirm and Effect · Raise First Rent Bill</button>
            @else
                <button class="btn btn-primary" name="action" value="sign">Sign and Forward</button>
            @endif
            <button class="btn btn-outline-primary" name="action" value="return">Return for Correction</button>
            <button class="btn btn-outline-danger" name="action" value="reject">Reject</button>
        </form>
        @endif
        @if($submission->state === 'changes_requested' && $submission->prepared_by === (int) auth()->id())
        <a class="btn btn-primary mb-3" href="{{ route('tenant-onboarding.correct', $submission) }}">Correct and Resubmit</a>
        @endif
        @if($submission->state === 'effected')
        <div class="alert alert-success">Effected on {{ $submission->effected_at->format('d M Y H:i') }}. Tenant {{ $submission->tenant_id }} · Lease {{ $submission->lease_id }} · First rent invoice {{ $submission->invoice_id }}.</div>
        @endif
        <h5>Signature and decision history</h5>
        <div class="table-responsive"><table class="table table-bordered bg-white"><thead class="table-primary"><tr><th>Date</th><th>Version</th><th>Stage</th><th>Staff member</th><th>Action / comments</th></tr></thead><tbody>
        @foreach($history as $event)<tr><td>{{ $event->created_at }}</td><td>{{ $event->revision }}</td><td>{{ \App\Models\TenantOnboarding::STAGES[$event->stage] }}</td><td>{{ $event->actor_name }}</td><td>{{ ucfirst($event->action) }}<p class="mb-0">{{ $event->comment }}</p></td></tr>@endforeach
        </tbody></table></div>
        @foreach($history->where('action', 'submitted') as $event)
        <details class="mb-2"><summary>Submitted version {{ $event->revision }}</summary>@php $snapshot = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($event->snapshot), true); @endphp
        @include('property.tenant-onboarding.fields', ['values' => $snapshot['payload'], 'prefix' => '', 'editable' => false])</details>
        @endforeach
    </div>
</div></div></div>
@endsection
