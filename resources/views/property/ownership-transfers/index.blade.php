@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px"><div class="container-fluid">
    <div class="card"><div class="card-header d-flex justify-content-between align-items-center">
        <div><h5 class="mb-1">Property Ownership Transfers</h5><p class="text-muted mb-0">Handover, financial review and ownership history.</p></div>
        @if(\App\Services\PropertyTransferAccess::allows(auth()->user(), 'create'))
        <a class="btn btn-primary btn-sm" href="{{ route('property-transfers.create', request()->only('property_id')) }}">New transfer</a>
        @endif
    </div><div class="card-body">
        @include('property.ownership-transfers.messages')
        <form class="d-flex gap-2 mb-3" method="get">
            @if(request('property_id'))<input type="hidden" name="property_id" value="{{ request('property_id') }}">@endif
            <label for="transfer-status" class="align-self-center mb-0">Status</label>
            <select id="transfer-status" name="status" class="form-select form-select-sm" style="max-width:220px">
                <option value="">All statuses</option>
                @foreach(['draft', 'needs_review', 'reviewed', 'approved', 'completed', 'cancelled'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select><button class="btn btn-outline-primary btn-sm">Apply</button>
        </form>
        <div class="table-responsive"><table class="table table-hover align-middle">
            <thead><tr><th>Reference</th><th>Property</th><th>Outgoing landlord</th><th>Incoming landlord</th><th>Effective date</th><th>Status</th></tr></thead>
            <tbody>@forelse($transfers as $transfer)<tr>
                <td><a href="{{ route('property-transfers.show', $transfer) }}">{{ $transfer->reference }}</a></td>
                <td>{{ $transfer->property->property_name }}<small class="d-block text-muted">{{ $transfer->property->reference_number }}</small></td>
                <td>{{ $transfer->fromClient->names }}</td><td>{{ $transfer->toClient->names }}</td>
                <td>{{ $transfer->effective_date->format('d M Y') }}</td><td>{{ ucwords(str_replace('_', ' ', $transfer->status)) }}</td>
            </tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No ownership transfers found.</td></tr>@endforelse</tbody>
        </table></div>{{ $transfers->links() }}
    </div></div>
</div></div>
@endsection
