@extends('layouts.main')
@section('content')
<div class="container py-4"><div class="d-flex justify-content-between align-items-center mb-3"><h3>Staff onboarding invitations</h3><a class="btn btn-primary" href="{{ route('staff.onboarding.create') }}">Invite staff</a></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Company email</th><th>Category</th><th>Status</th><th>Sent</th><th>Expires</th><th>Action</th></tr></thead><tbody>
@forelse($invitations as $invite)<tr><td>{{ $invite->recipient_email }}</td><td>{{ $invite->employment_type }}</td><td>{{ ucfirst(str_replace('_',' ',$invite->status)) }}</td><td>{{ $invite->created_at?->format('d M Y H:i') }}</td><td>{{ $invite->expires_at->format('d M Y H:i') }}</td><td class="d-flex gap-2">@if($invite->status==='submitted')<a class="btn btn-sm btn-outline-primary" href="{{ route('staff.onboarding.review',$invite) }}">Review</a>@endif @if(in_array($invite->status,['sent','submitted']))<form method="POST" action="{{ route('staff.onboarding.revoke',$invite) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Revoke</button></form>@endif</td></tr>@empty<tr><td colspan="6" class="text-center">No invitations yet.</td></tr>@endforelse
</tbody></table></div>{{ $invitations->links() }}</div>
@endsection
