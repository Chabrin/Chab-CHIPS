@extends('layouts.main')
@section('content')
@php($statusLabels = ['sent' => 'Link sent', 'submitted' => 'Awaiting review', 'reviewed' => 'Approved – staff record not yet created', 'approved' => 'Staff created', 'revoked' => 'Cancelled', 'mail_failed' => 'Email failed'])
<div class="container py-4"><div class="d-flex justify-content-between align-items-center mb-3"><h3>Staff onboarding invitations</h3><a class="btn btn-primary" href="{{ route('staff.onboarding.create') }}">Invite staff</a></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Email</th><th>Status</th><th>Sent</th><th>Expires</th><th>Action</th></tr></thead><tbody>
@forelse($invitations as $invite)<tr>
    <td>{{ $invite->recipient_email }}</td>
    <td>{{ $statusLabels[$invite->status] ?? ucfirst(str_replace('_',' ',$invite->status)) }}</td>
    <td>{{ $invite->created_at?->format('d M Y H:i') }}</td>
    <td>{{ in_array($invite->status, ['sent', 'submitted'], true) ? $invite->expires_at->format('d M Y H:i') : '—' }}</td>
    <td class="d-flex gap-2">
        @if($invite->status==='submitted')<a class="btn btn-sm btn-outline-primary" href="{{ route('staff.onboarding.review',$invite) }}">Review submission</a>
        @elseif($invite->status==='reviewed')<a class="btn btn-sm btn-primary" href="{{ route('staff.create', ['onboarding' => $invite->id]) }}">Continue to Add Staff</a>
        @elseif($invite->status==='approved')<span class="text-success">Staff created</span>@endif
        @if(in_array($invite->status, ['sent', 'submitted', 'reviewed'], true))<form method="POST" action="{{ route('staff.onboarding.revoke',$invite) }}" onsubmit="return confirm('Cancel this onboarding? Any submitted details and files will be deleted.')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ $invite->status === 'sent' ? 'Revoke' : 'Reject' }}</button></form>@endif
    </td>
</tr>@empty<tr><td colspan="5" class="text-center">No invitations yet.</td></tr>@endforelse
</tbody></table></div>{{ $invitations->links() }}</div>
@endsection
