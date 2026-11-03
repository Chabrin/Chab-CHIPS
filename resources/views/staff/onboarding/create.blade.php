@extends('layouts.main')
@section('content')
<div class="container py-4" style="max-width: 920px">
    <h3>Invite staff to onboard</h3>
    <p class="text-muted">Send one private, expiring link to the confirmed company email. The email contains no staff details.</p>
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <form method="POST" action="{{ route('staff.onboarding.send') }}" class="card card-body">
        @csrf
        <h5>Company details (HR only)</h5>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Company email to invite</label><input type="email" name="recipient_email" value="{{ old('recipient_email') }}" class="form-control" required>@error('recipient_email')<div class="text-danger">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Confirm company email</label><input type="email" name="recipient_email_confirmation" value="{{ old('recipient_email_confirmation') }}" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Employment category</label><select name="employment_type" id="employment_type" class="form-select" required><option>Permanent</option><option>Temporary</option><option>Intern</option><option>Casual</option></select></div>
            <div class="col-md-4"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">Select</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->department_name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Position</label><select name="position_id" class="form-select"><option value="">Select</option>@foreach($positions as $p)<option value="{{ $p->id }}">{{ $p->position_name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Hire date</label><input type="date" name="hire_date" value="{{ old('hire_date') }}" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Zone</label><select name="zone_id" class="form-select"><option value="">Select</option>@foreach($zones as $z)<option value="{{ $z->id }}">{{ $z->zone_name }}</option>@endforeach</select></div>
            <div class="col-md-4" id="role-wrap"><label class="form-label">System role (non-Casual staff)</label><select name="role_id" id="role_id" class="form-select"><option value="">Select</option>@foreach($roles as $r)<option value="{{ $r->id }}">{{ $r->display_name }}</option>@endforeach</select>@error('role_id')<div class="text-danger">{{ $message }}</div>@enderror</div>
        </div>
        <div class="alert alert-warning mt-3 mb-0">Confirm the spelling of the recipient address before sending. A link sent to the wrong mailbox could expose the onboarding form.</div>
        <div class="mt-3"><button class="btn btn-primary" type="submit">Send onboarding link</button> <a class="btn btn-outline-secondary" href="{{ route('staff.onboarding.index') }}">Invitations</a></div>
    </form>
</div>
<script>document.getElementById('employment_type').addEventListener('change', function(){var casual=this.value==='Casual';document.getElementById('role-wrap').classList.toggle('d-none',casual);document.getElementById('role_id').required=!casual;});document.getElementById('employment_type').dispatchEvent(new Event('change'));</script>
@endsection
