@extends('layouts.main')
@section('content')

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header card-no-border d-flex align-items-center">
                <h4 class="mb-0">My Onboarding</h4>
                <div class="ms-auto">
                    <a href="{{ route('property.clients.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                        <span class="material-symbols-outlined me-2" style="font-size:18px;">add</span>Start with a New Landlord
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if(session('error'))
                <div class="alert alert-light-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif

                <p class="text-muted mb-3" style="font-size:0.875rem;">
                    Everything you've created across the onboarding chain — Landlord → Property → Service Agreement /
                    Unit → Tenant → Lease — with its confirmation status and what to do next.
                </p>

                @if($items->isEmpty())
                <div class="text-center text-muted py-5">
                    <span class="material-symbols-outlined d-block mb-2" style="font-size:32px;">inbox</span>
                    You haven't created any onboarding items yet.
                </div>
                @else
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="my-pipeline-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Item</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Next Step</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                            <tr>
                                <td><span class="badge badge-light-primary">{{ $item['type'] }}</span></td>
                                <td>
                                    <strong>{{ $item['label'] }}</strong>
                                    <br><small class="text-muted">{{ $item['reference'] }}</small>
                                </td>
                                <td>
                                    @if($item['status_id'] == 5)
                                    <span class="badge bg-success">Approved</span>
                                    @elseif($item['status_id'] == 41)
                                    <span class="badge bg-danger">Rejected</span>
                                    @else
                                    <span class="badge bg-warning text-dark">{{ $item['status_label'] }}</span>
                                    @endif
                                </td>
                                <td><small>{{ $item['created_at_formatted'] }}</small></td>
                                <td>
                                    @php $next = $item['next_step']; @endphp

                                    @if($next['type'] === 'link')
                                        @if($next['link']['url'])
                                        <a href="{{ $next['link']['url'] }}" class="btn btn-sm btn-success d-flex align-items-center" style="width:fit-content;">
                                            <span class="material-symbols-outlined me-1" style="font-size:16px;">arrow_forward</span>{{ $next['link']['label'] }}
                                        </a>
                                        @else
                                        <span class="badge badge-light-secondary">No permission for next step</span>
                                        @endif

                                    @elseif($next['type'] === 'links')
                                        <div class="d-flex flex-column gap-1" style="width:fit-content;">
                                            @foreach($next['links'] as $link)
                                                @if($link['url'])
                                                <a href="{{ $link['url'] }}" class="btn btn-sm btn-success d-flex align-items-center">
                                                    <span class="material-symbols-outlined me-1" style="font-size:16px;">arrow_forward</span>{{ $link['label'] }}
                                                </a>
                                                @else
                                                <span class="badge badge-light-secondary">{{ $link['label'] }}: no permission</span>
                                                @endif
                                            @endforeach
                                        </div>

                                    @elseif($next['type'] === 'complete')
                                        <span class="badge bg-success d-flex align-items-center" style="width:fit-content;">
                                            <span class="material-symbols-outlined me-1" style="font-size:14px;">check_circle</span>Onboarding complete
                                        </span>

                                    @elseif($next['value'] === 'rejected')
                                        <span class="badge badge-light-danger">Rejected — cannot proceed</span>
                                    @elseif($next['value'] === 'approved')
                                        <span class="text-muted small">—</span>
                                    @else
                                        <span class="badge badge-light-warning">Awaiting confirmation</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
    #my-pipeline-table td {
        vertical-align: middle;
        padding-top: 0.65rem;
        padding-bottom: 0.65rem;
    }
</style>
@endsection
