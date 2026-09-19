@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <h5 class="mb-0">Search Results</h5>
                        @if($term !== '')
                            <p class="mb-0 text-muted">Showing results for "{{ $term }}"</p>
                        @endif
                    </div>
                    <div class="card-body">
                        @if(strlen($term) < 2)
                            <p class="text-muted mb-0">Type at least 2 characters to search.</p>
                        @elseif($clients->isEmpty() && $staff->isEmpty() && $properties->isEmpty() && $invoices->isEmpty())
                            <p class="text-muted mb-0">No results found for "{{ $term }}".</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($clients->isNotEmpty())
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <h6 class="mb-0">Clients &amp; Tenants ({{ $clients->count() }})</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Reference Number</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($clients as $client)
                                    <tr>
                                        <td>
                                            <a style="color:#3598db" href="{{ route('property.clients.show', $client->encrypted_id) }}">
                                                {{ trim("{$client->names} {$client->second_name} {$client->last_name}") }}
                                            </a>
                                        </td>
                                        <td>{{ $client->reference_number }}</td>
                                        <td>{{ $client->mobile_number }}</td>
                                        <td>{{ $client->email_address }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($staff->isNotEmpty())
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <h6 class="mb-0">Staff ({{ $staff->count() }})</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Staff Number</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($staff as $member)
                                    <tr>
                                        <td>
                                            <a style="color:#3598db" href="{{ route('staff.show', $member->encrypted_id) }}">
                                                {{ $member->full_names }}
                                            </a>
                                        </td>
                                        <td>{{ $member->staff_number }}</td>
                                        <td>{{ $member->mobile_number }}</td>
                                        <td>{{ $member->email_address }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($properties->isNotEmpty())
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <h6 class="mb-0">Properties ({{ $properties->count() }})</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Property Name</th>
                                        <th>Reference Number</th>
                                        <th>LR Number</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($properties as $property)
                                    <tr>
                                        <td>
                                            <a style="color:#3598db" href="{{ route('property.properties.show', $property->encrypted_id) }}">
                                                {{ $property->property_name }}
                                            </a>
                                        </td>
                                        <td>{{ $property->reference_number }}</td>
                                        <td>{{ $property->lr_number }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($invoices->isNotEmpty())
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <h6 class="mb-0">Invoices ({{ $invoices->count() }})</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Reference Number</th>
                                        <th>Tenant</th>
                                        <th>Total Amount</th>
                                        <th>Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoices as $invoice)
                                    <tr>
                                        <td>
                                            @if($invoice->tenant)
                                                <a style="color:#3598db" href="{{ route('property.clients.show', $invoice->tenant->encrypted_id) }}">
                                                    {{ $invoice->reference_number }}
                                                </a>
                                            @else
                                                {{ $invoice->reference_number }}
                                            @endif
                                        </td>
                                        <td>{{ $invoice->tenant->names ?? $invoice->client->names ?? '-' }}</td>
                                        <td>{{ number_format($invoice->total_amount ?? 0, 2) }}</td>
                                        <td>{{ number_format($invoice->current_balance ?? 0, 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
