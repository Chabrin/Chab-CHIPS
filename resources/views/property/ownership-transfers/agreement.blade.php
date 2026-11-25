@extends('layouts.main')
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/ownership-transfer.css') }}?v={{ filemtime(public_path('assets/css/ownership-transfer.css')) }}">
@endsection
@section('content')
@php
    $status = \App\Services\ServiceAgreementCloneService::STATUS_LABELS[(int) $agreement->current_status_id] ?? 'Not recorded';
    $returns = ['each_separate' => 'Each charge separately', 'rent_separate_rest_combined' => 'Rent separately; other charges combined', 'all_combined' => 'All charges combined'];
    $yesNo = fn ($value) => in_array(strtolower((string) $value), ['1', 'yes', 'true'], true) ? 'Yes' : 'No';
    $legacyItems = collect([
        ['selected' => $agreement->rent_selected, 'name' => 'Rent', 'amount' => null],
        ['selected' => $agreement->electricity_bill_selected, 'name' => 'Electricity Bill', 'amount' => $agreement->electricity_bill_cost],
        ['selected' => $agreement->water_bill_selected, 'name' => 'Water Bill', 'amount' => $agreement->water_bill_cost],
        ['selected' => $agreement->service_charges_selected, 'name' => 'Service Charges', 'amount' => $agreement->service_charges_cost],
    ])->where('selected', true);
@endphp
<div class="page-body ownership-transfer transfer-review transfer-sla-view" style="padding-top:10px">
    <div class="container-fluid">
        <header class="transfer-page-heading">
            <div><div class="transfer-reference">{{ $transfer->reference }} · {{ ucfirst($side) }} agreement</div><h1>Service level agreement</h1><p>{{ $agreement->reference_number ?: 'Reference not recorded' }}</p></div>
            <div class="transfer-header-actions"><a class="btn btn-light" href="{{ route('property-transfers.show', $transfer) }}#transfer-agreements">Back to ownership transfer</a><button type="button" class="btn btn-outline-primary" onclick="window.print()">Print / save PDF</button></div>
        </header>
        <div class="transfer-purpose"><strong>Read-only agreement record</strong><span>This is the {{ $side === 'outgoing' ? 'existing agreement for the outgoing landlord' : 'approved agreement created for the incoming landlord' }}. Opening this page does not change the SLA or approve the transfer.</span></div>
        <section class="transfer-section" aria-labelledby="sla-parties-title">
            <div class="transfer-section-heading"><div><h2 id="sla-parties-title">Agreement details</h2><p>{{ $property->property_name }} · {{ $property->reference_number }}</p></div><span class="transfer-status">{{ $status }}</span></div>
            <div class="transfer-section-body">
                <dl class="transfer-facts transfer-facts-two">
                    <div><dt>Landlord</dt><dd>{{ $landlord->names }}<small>{{ $landlord->reference_number }}</small></dd></div>
                    <div><dt>Property</dt><dd>{{ $property->property_name }}<small>{{ $property->reference_number }}</small></dd></div>
                    <div><dt>Start date</dt><dd>{{ $agreement->date_start ? \Carbon\Carbon::parse($agreement->date_start)->format('d M Y') : 'Not recorded' }}</dd></div>
                    <div><dt>End date</dt><dd>{{ $agreement->date_end ? \Carbon\Carbon::parse($agreement->date_end)->format('d M Y') : 'Not recorded' }}</dd></div>
                    <div><dt>Duration</dt><dd>{{ $agreement->years ? $agreement->years.' year(s)' : 'Not recorded' }}</dd></div>
                    <div><dt>Auto renewal</dt><dd>{{ $yesNo($agreement->auto_renewal) }}</dd></div>
                    <div><dt>Created by</dt><dd>{{ $agreement->createdBy?->name ?? 'Not recorded' }}</dd></div>
                    <div><dt>Created on</dt><dd>{{ $agreement->date_time?->format('d M Y · H:i') ?? 'Not recorded' }}</dd></div>
                </dl>
            </div>
        </section>
        <section class="transfer-section" aria-labelledby="sla-terms-title">
            <div class="transfer-section-heading"><h2 id="sla-terms-title">Commercial terms &amp; returns</h2></div>
            <div class="transfer-section-body"><dl class="transfer-facts transfer-facts-two">
                <div><dt>Commission</dt><dd>{{ number_format((float) $agreement->commission_percentage, 2) }}%</dd></div>
                <div><dt>VAT</dt><dd>{{ $agreement->vat ?: 'No' }}{{ $agreement->vat === 'Yes' ? ' · '.number_format((float) $agreement->vat_per, 2).'%' : '' }}{{ $agreement->vat === 'Flat Rate' ? ' · '.number_format((float) $agreement->flat_rate_vat, 2) : '' }}</dd></div>
                <div><dt>Remittance day</dt><dd>{{ $agreement->landlord_payment_date ?: 'Not recorded' }}</dd></div>
                <div><dt>Registration fee charged</dt><dd>{{ $agreement->charge_registration_fee ? 'Yes' : 'No' }}</dd></div>
                <div><dt>Move-in inspection required</dt><dd>{{ $agreement->move_in_inspection_required ? 'Yes' : 'No' }}</dd></div>
                <div><dt>Returns layout</dt><dd>{{ $returns[$agreement->returns_combination] ?? 'Not recorded' }}</dd></div>
            </dl></div>
        </section>
        <section class="transfer-section" aria-labelledby="sla-billing-title">
            <div class="transfer-section-heading"><div><h2 id="sla-billing-title">Tenant billing items</h2><p>Charges configured on this agreement, including any legacy billing settings.</p></div></div>
            <div class="transfer-section-body table-responsive"><table class="table table-sm transfer-table"><thead><tr><th>Billing item</th><th>Code</th><th>Billing cycle</th><th>Advance payable</th><th class="text-end">Value</th></tr></thead><tbody>
                @foreach($items as $picked)
                <tr><td>{{ $picked->item?->item_name ?? 'Item no longer available' }}</td><td>{{ $picked->item?->billing_code ?? '—' }}</td><td>{{ $picked->item?->cycle?->cycle_name ?? 'Not set' }}</td><td>{{ $yesNo($picked->item?->advance_payable) }}</td><td class="text-end">{{ $picked->amount !== null ? number_format((float) $picked->amount, 2) : ($picked->percentage_of_rent !== null ? number_format((float) $picked->percentage_of_rent, 2).'% of rent' : 'Value not set') }}</td></tr>
                @endforeach
                @foreach($legacyItems as $legacy)
                <tr><td>{{ $legacy['name'] }} <small class="text-muted">(legacy setting)</small></td><td>—</td><td>Not set</td><td>Not recorded</td><td class="text-end">{{ $legacy['amount'] !== null ? number_format((float) $legacy['amount'], 2) : 'Value not set' }}</td></tr>
                @endforeach
                @if($items->isEmpty() && $legacyItems->isEmpty())
                <tr><td colspan="5" class="transfer-empty">No tenant billing items recorded on this agreement.</td></tr>
                @endif
            </tbody></table></div>
        </section>
        <section class="transfer-section" aria-labelledby="sla-files-title">
            <div class="transfer-section-heading"><h2 id="sla-files-title">SLA documents</h2></div>
            <div class="transfer-section-body"><ul class="transfer-document-list">
                @forelse($agreement->serviceAgreementDocuments as $document)
                <li><span>{{ $document->sla_documents ? basename($document->sla_documents) : 'Unnamed document' }}</span>
                    @if($document->sla_documents && \Illuminate\Support\Facades\Storage::disk('public')->exists($document->sla_documents))
                    <a href="{{ route('property-transfers.agreement-document', [$transfer, $side, $document->id]) }}">Download</a>
                    @else
                    <small>File unavailable</small>
                    @endif
                </li>
                @empty
                <li class="transfer-empty">No SLA documents attached.</li>
                @endforelse
            </ul></div>
        </section>
    </div>
</div>
@endsection
