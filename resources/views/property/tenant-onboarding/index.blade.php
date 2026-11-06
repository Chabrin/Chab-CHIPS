@extends('layouts.main')
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tenant-onboarding.css') }}?v={{ filemtime(public_path('assets/css/tenant-onboarding.css')) }}">
@endsection
@section('content')
<div class="page-body tenant-onboarding-page"><div class="container-fluid"><div class="card">
    <div class="card-header onboarding-heading d-flex flex-wrap gap-2 justify-content-between"><h4>New Tenant Onboarding</h4>@permission('create_tenants')<a href="{{ route('units.tenants.create') }}" class="btn btn-primary">Add Tenant</a>@endpermission</div>
    <div class="card-body">
        <p>Field Officer → Zone Manager → Internal Auditor → Assistant Auditor confirms and effects.</p>
        <div class="table-responsive"><table class="table table-bordered bg-white"><thead class="table-primary"><tr><th>Submission</th><th>Tenant</th><th>Status</th><th>Submitted</th><th>Review</th></tr></thead><tbody>
        @forelse($submissions as $submission)
        <tr><td>NT-{{ $submission->id }}</td><td>{{ ($submission->payload['company_name'] ?? '') ?: ($submission->payload['names'] ?? '') }}</td><td>{{ $submission->statusLabel() }}</td><td>{{ $submission->created_at->format('d M Y') }}</td><td><a href="{{ route('tenant-onboarding.show', $submission) }}">Open submission</a></td></tr>
        @empty<tr><td colspan="5">No submissions are currently assigned to you.</td></tr>@endforelse
        </tbody></table></div>{{ $submissions->links() }}
    </div>
</div></div></div>
@endsection
