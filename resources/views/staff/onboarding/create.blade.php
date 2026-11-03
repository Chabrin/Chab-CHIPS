@extends('layouts.main')
@section('content')
<div class="container py-4" style="max-width: 720px">
    <h3>Invite staff to onboard</h3>
    <p class="text-muted">Send one private, expiring link. The new staff member fills in their personal, contact, banking and document details. After you review and approve them, you complete the company details on the Add Staff form.</p>
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <form method="POST" action="{{ route('staff.onboarding.send') }}" class="card card-body">
        @csrf
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Email to send the link to</label><input type="email" name="recipient_email" value="{{ old('recipient_email') }}" class="form-control" required>@error('recipient_email')<div class="text-danger">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Confirm email</label><input type="email" name="recipient_email_confirmation" value="{{ old('recipient_email_confirmation') }}" class="form-control" required></div>
        </div>
        <div class="alert alert-warning mt-3 mb-0">Confirm the spelling of the recipient address before sending. A link sent to the wrong mailbox could expose the onboarding form.</div>
        <div class="mt-3"><button class="btn btn-primary" type="submit">Send onboarding link</button> <a class="btn btn-outline-secondary" href="{{ route('staff.onboarding.index') }}">Invitations</a></div>
    </form>
</div>
@endsection
