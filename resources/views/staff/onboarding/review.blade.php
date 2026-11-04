@extends('layouts.main')
@section('content')
@php($fileLabels = ['photo' => 'Staff Photo', 'signature' => 'Signature'] +collect(\App\Models\StaffOnboardingInvitation::DOCUMENT_TYPES)->mapWithKeys(fn ($title, $key) => ["documents.$key" => $title])->all())
<div class="container py-4" style="max-width:1100px">
    <h3>Review onboarding submission</h3>
    <p class="text-muted">Submitted by <strong>{{ $invitation->recipient_email }}</strong> on {{ $invitation->submitted_at?->format('d M Y H:i') }}. Approving takes you to the Add Staff form with these details filled in, where you add the company details and save.</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @foreach($sections as $title => $fields)
        <div class="card card-body mb-3">
            <h5>{{ $title }}</h5>
            <div class="row">
                @foreach($fields as $label => $value)
                    <div class="col-md-4 mb-2"><small class="text-muted d-block">{{ $label }}</small>{{ filled($value) ? $value : '—' }}</div>
                @endforeach
            </div>
        </div>
    @endforeach
    <div class="card card-body mb-3">
        <h5>Documents</h5>
        @forelse($files as $field => $file)
            <p class="mb-2">{{ $fileLabels[$field] ?? $field }} — {{ $file['name'] }}
                <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="{{ route('staff.onboarding.document', [$invitation, hash('sha256', $file['path'])]) }}">Preview</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('staff.onboarding.document', [$invitation, hash('sha256', $file['path']), 'download' => 1]) }}">Download</a>
            </p>
        @empty<p>No files were uploaded.</p>@endforelse
        @if(isset($files['signature']))
            <img src="{{ route('staff.onboarding.document', [$invitation, hash('sha256', $files['signature']['path'])]) }}" alt="Submitted signature" style="max-height:90px;max-width:100%;border:1px solid #e3e7eb;border-radius:4px;padding:4px;background:#fff">
        @endif
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('staff.onboarding.approve', $invitation) }}">@csrf<button class="btn btn-success">{{ $invitation->status === 'reviewed' ? 'Continue to Add Staff' : 'Approve & Continue to Add Staff' }}</button></form>
        <form method="POST" action="{{ route('staff.onboarding.revoke', $invitation) }}" onsubmit="return confirm('Reject this submission? The details and files will be deleted and the link cannot be reused.')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Reject</button></form>
        <a href="{{ route('staff.onboarding.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
</div>
@endsection
