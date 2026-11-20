@extends('layouts.main')
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tenant-review.css') }}?v={{ filemtime(public_path('assets/css/tenant-review.css')) }}">
@endsection
@section('content')
<div class="page-body tenant-review"><div class="container-fluid">
    <header class="tr-list-header">
        <div><p class="tr-eyebrow">APPROVAL CONTROL</p><h1>New Tenant Approvals</h1><p class="tr-list-description">Track tenant onboarding and open a review to see its details, documents and approval history.</p></div>
        <span class="tr-list-total">{{ number_format($items->total()) }} {{ $items->total() === 1 ? 'review' : 'reviews' }}</span>
    </header>
    @if($accountsOnly ?? request()->boolean('accounts'))
    <div class="tr-list-filter" role="status"><span>Showing applications that need Accounts reconciliation.</span><a href="{{ route('tenant-reviews.index') }}">View all reviews</a></div>
    @endif
    <section class="tr-panel tr-list-panel" aria-labelledby="tr-list-title">
        <div class="tr-section-heading"><h2 id="tr-list-title">Onboarding reviews</h2><span class="tr-muted">Most recent first</span></div>
        <div class="table-responsive"><table class="table tr-table tr-review-list">
        <thead><tr><th scope="col">Tenant</th><th scope="col">Property / unit</th><th scope="col">Lease</th><th scope="col">Approval stage</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
        <tbody>
        @forelse($items as $item)
        @php($tone = match($item->stage) { 'approved' => 'success', 'rejected' => 'danger', 'correction' => 'warning', default => 'pending' })
        <tr>
            <td><a class="tr-tenant-link" href="{{ route('tenant-reviews.show', $item) }}">{{ $item->tenant->names }}</a>@if($item->tenant->reference_number)<small class="tr-cell-detail">{{ $item->tenant->reference_number }}</small>@endif</td>
            <td data-label="Property / unit"><strong class="tr-cell-title">{{ $item->lease->property?->property_name ?: 'Property not recorded' }}</strong><small class="tr-cell-detail">Unit {{ $item->lease->unit?->unit_code ?: ($item->lease->unit?->unit_number ?: 'not recorded') }}</small></td>
            <td data-label="Lease"><span class="tr-lease-reference">{{ $item->lease->ref_number ?: 'Not recorded' }}</span><small class="tr-cell-detail">Starts {{ $item->lease->date_start?->format('d M Y') ?: 'Not recorded' }}</small></td>
            <td data-label="Approval stage"><span class="tr-status tr-status-{{ $tone }}">{{ $item->stageLabel() }}</span>@if($item->accounts_pending)<span class="tr-accounts-flag">Accounts reconciliation required</span>@else<small class="tr-cell-detail">Revision {{ $item->revision }}</small>@endif</td>
            <td class="tr-review-action"><a class="btn btn-outline-primary" href="{{ route('tenant-reviews.show', $item) }}" aria-label="Open review for {{ $item->tenant->names }}">Open review <span aria-hidden="true">→</span></a></td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="tr-list-empty"><span class="material-symbols-outlined" aria-hidden="true">fact_check</span><h3>No tenant reviews available</h3><p>{{ ($accountsOnly ?? request()->boolean('accounts')) ? 'There are no applications requiring Accounts reconciliation in this list.' : 'Applications you can access will appear here after the preparing Field Officer saves and signs them.' }}</p></div></td></tr>
        @endforelse
        </tbody>
        </table></div>
        <footer class="tr-list-footer"><p class="tr-muted mb-0">@if($items->total())Showing {{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ number_format($items->total()) }}@else 0 reviews @endif</p><div>{{ $items->onEachSide(1)->links() }}</div></footer>
    </section>
</div></div>
@endsection
