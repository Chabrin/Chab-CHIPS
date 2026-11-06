@extends('layouts.main')
@section('content')
<div class="page-body"><div class="container-fluid"><div class="card">
    <div class="card-header d-flex justify-content-between"><div><h4>New Tenant · NT-{{ $submission->id }}</h4><strong>{{ $submission->statusLabel() }}</strong></div><a href="{{ route('tenant-onboarding.index') }}" class="btn btn-light">Back to submissions</a></div>
    <div class="card-body">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach</div>@endif
        <p>Field Officer → Zone Manager → Internal Auditor → Assistant Auditor confirms and effects.</p>
        <div class="table-responsive"><table class="table table-bordered bg-white"><tbody>
            <tr><th>Property / unit</th><td>{{ $property->property_name }} · {{ $unit->unit_code }}</td><th>Form month</th><td>{{ $payload['effective_month'] ?? '—' }}</td></tr>
            <tr><th>Tenant</th><td>{{ ($payload['company_name'] ?? '') ?: ($payload['names'] ?? '') }}</td><th>Phone</th><td>{{ $payload['mobile_number'] }}</td></tr>
            <tr><th>ID / passport / registration</th><td>{{ ($payload['national_id'] ?? '') ?: (($payload['passport_number'] ?? '') ?: ($payload['company_registration_number'] ?? '')) }}</td><th>Previous tenant vacated</th><td>{{ $payload['previous_tenant_vacated'] ?? '—' }}</td></tr>
            <tr><th>Monthly rent / first bill</th><td>{{ number_format($payload['rent_amount'], 2) }}</td><th>Deposit payable</th><td>{{ number_format($payload['rent_amount'] * ($payload['deposit_months'] ?? 0), 2) }}</td></tr>
            <tr><th>Lease term</th><td>{{ !empty($payload['lease_open_ended']) ? 'Open ended' : (!empty($payload['lease_term_days']) ? $payload['lease_term_days'].' days' : $payload['lease_years'].' year(s)') }}</td><th>Lease begins</th><td>{{ $submission->effected_at?->format('d M Y') ?? 'When the Assistant Auditor confirms and effects' }}</td></tr>
        </tbody></table></div>
        <p>The first rent invoice uses the full monthly rent. Deposit payment handling is a later phase.</p>
        <details class="mb-3"><summary>All submitted details · version {{ $submission->revision }}</summary>
            @include('property.tenant-onboarding.fields', ['values' => $payload, 'prefix' => '', 'editable' => false])
        </details>
        <h5>Attachments</h5>
        <ul>@forelse($submission->documents as $document)<li><a href="{{ route('tenant-onboarding.document', [$submission, $loop->index]) }}">{{ $document['name'] }}</a></li>@empty<li>No attachments submitted.</li>@endforelse</ul>

        @if($submission->state === 'pending')
        <p><strong>Current reviewer</strong> {{ implode(', ', $reviewers) ?: 'No eligible reviewer is assigned. Check the staff assignment and role membership.' }}</p>
        @endif
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
