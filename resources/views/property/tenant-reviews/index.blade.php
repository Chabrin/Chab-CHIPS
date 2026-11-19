@extends('layouts.main')
@section('content')
<div class="page-body container-fluid">
    <h1>New Tenant Approvals</h1>
    <table class="table">
        <thead><tr><th>Tenant</th><th>Lease</th><th>Approval stage</th><th>Accounts</th></tr></thead>
        <tbody>
        @forelse($items as $item)
        <tr>
            <td><a href="{{ route('tenant-reviews.show', $item) }}">{{ $item->tenant->names }}</a></td>
            <td>{{ $item->lease->ref_number }}</td>
            <td>{{ $item->stageLabel() }}</td>
            <td>{{ $item->accounts_pending ? 'Reconciliation required' : '' }}</td>
        </tr>
        @empty
        <tr><td colspan="4">No tenant reviews available.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $items->links() }}
</div>
@endsection
