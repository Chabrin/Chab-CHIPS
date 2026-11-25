@extends('layouts.main')
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/ownership-transfer.css') }}?v={{ filemtime(public_path('assets/css/ownership-transfer.css')) }}">
@endsection
@section('content')
<div class="page-body ownership-transfer" style="padding-top:10px">
    <div class="container-fluid">
        <header class="transfer-page-heading">
            <div><h1>Transfer property ownership</h1><p>Record the handover, incoming landlord and new agreement terms.</p></div>
            <a class="btn btn-light btn-sm" href="{{ route('property-transfers.index') }}">Back to transfers</a>
        </header>
        @include('property.ownership-transfers.messages')
        <div class="transfer-layout">
            <form id="ownership-transfer-form" method="post" action="{{ route('property-transfers.store') }}" enctype="multipart/form-data"
                data-banks-url="{{ url('/property/ownership-transfers/banks') }}" data-old-bank="{{ old('bank_detail_id', '') }}">
                @csrf
                <section class="transfer-section" aria-labelledby="transfer-property-heading">
                    <div class="transfer-section-heading"><span class="transfer-number" aria-hidden="true">1</span><div><h2 id="transfer-property-heading">Property &amp; ownership</h2><p>Search by name or reference to find the right record.</p></div><small class="transfer-required-note">* Required</small></div>
                    <div class="transfer-section-body">
                        <div class="transfer-grid">
                            <div class="transfer-field transfer-full">
                                <label for="property_id">Property <span class="text-danger">*</span></label>
                                <select id="property_id" name="property_id" class="form-select transfer-search" data-placeholder="Search property, reference or current landlord" required aria-describedby="property-context">
                                    <option value="">Search property, reference or current landlord</option>
                                    @foreach($properties as $property)
                                    <option value="{{ $property->id }}" data-owner="{{ $property->client?->names }}" data-reference="{{ $property->reference_number }}" @selected((string) old('property_id', $selectedProperty) === (string) $property->id)>{{ $property->property_name }} ({{ $property->reference_number }}) — {{ $property->client?->names }}</option>
                                    @endforeach
                                </select>
                                <p id="property-context" class="transfer-context" role="status">Select a property to see its current landlord.</p>
                                @error('property_id')<span class="transfer-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="transfer-field">
                                <label for="to_client_id">Incoming landlord <span class="text-danger">*</span></label>
                                <select id="to_client_id" name="to_client_id" class="form-select transfer-search" data-placeholder="Search approved landlord or reference" required>
                                    <option value="">Search approved landlord or reference</option>
                                    @foreach($clients as $client)
                                    <option value="{{ $client->id }}" @selected((string) old('to_client_id') === (string) $client->id)>{{ $client->names }} ({{ $client->reference_number }})</option>
                                    @endforeach
                                </select>
                                <small>Only approved landlords are listed.</small>
                                @error('to_client_id')<span class="transfer-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="transfer-field">
                                <label for="bank_detail_id">Incoming payout account <span class="text-danger">*</span></label>
                                <select id="bank_detail_id" name="bank_detail_id" class="form-select" required disabled aria-describedby="bank-help"><option value="">Select a landlord first</option></select>
                                <small id="bank-help" role="status">Accounts load from the incoming landlord's bank details.</small>
                                <button type="button" id="retry-banks" class="transfer-retry" hidden>Retry loading accounts</button>
                                @error('bank_detail_id')<span class="transfer-error">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                </section>
                <section class="transfer-section" aria-labelledby="transfer-terms-heading">
                    <div class="transfer-section-heading"><span class="transfer-number" aria-hidden="true">2</span><div><h2 id="transfer-terms-heading">Handover &amp; agreement terms</h2><p>Set when ownership changes and who collects existing arrears.</p></div></div>
                    <div class="transfer-section-body transfer-grid">
                        <div class="transfer-field">
                            <label for="effective_date">Effective date <span class="text-danger">*</span></label>
                            <input type="date" id="effective_date" name="effective_date" class="form-control" min="{{ today()->toDateString() }}" value="{{ old('effective_date', today()->addMonthNoOverflow()->startOfMonth()->toDateString()) }}" required aria-describedby="effective-date-help">
                            <small id="effective-date-help">Use the first day of a month. Directors receive a notification on that date to complete the approved transfer before raising invoices.</small>
                            @error('effective_date')<span class="transfer-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="transfer-field">
                            <label for="arrears_policy">Outstanding tenant invoices <span class="text-danger">*</span></label>
                            <select id="arrears_policy" name="arrears_policy" class="form-select" required aria-describedby="arrears-help">
                                <option value="transfer" @selected(old('arrears_policy', 'transfer') === 'transfer')>Incoming landlord collects arrears</option>
                                <option value="retain" @selected(old('arrears_policy') === 'retain')>Outgoing landlord keeps collection rights</option>
                            </select>
                            <small id="arrears-help">Original invoices and payment histories are preserved.</small>
                            @error('arrears_policy')<span class="transfer-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="transfer-field">
                            <label for="commission_percentage">Agreed commission (%) <span class="text-danger">*</span></label>
                            <input id="commission_percentage" name="commission_percentage" type="number" min="0.01" max="100" step="0.01" class="form-control" value="{{ old('commission_percentage') }}" placeholder="Enter agreed percentage" required>
                            @error('commission_percentage')<span class="transfer-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="transfer-field">
                            <label for="duration_years">New SLA duration (years) <span class="text-danger">*</span></label>
                            <input id="duration_years" name="duration_years" type="number" min="1" max="30" class="form-control" value="{{ old('duration_years', 1) }}" required>
                            @error('duration_years')<span class="transfer-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="transfer-note transfer-full"><strong>Existing SLA settings carry forward.</strong> Other terms and configured billing items are copied for review. Registration is not charged again.</div>
                    </div>
                </section>
                <section class="transfer-section" aria-labelledby="transfer-documents-heading">
                    <div class="transfer-section-heading"><span class="transfer-number" aria-hidden="true">3</span><div><h2 id="transfer-documents-heading">Handover instructions &amp; documents</h2><p>Provide the reason for the transfer. Attach supporting files if available.</p></div></div>
                    <div class="transfer-section-body transfer-grid">
                        <div class="transfer-field transfer-full">
                            <label for="reason">Reason and handover instructions <span class="text-danger">*</span></label>
                            <textarea id="reason" name="reason" class="form-control" rows="3" minlength="10" maxlength="4000" placeholder="Explain the ownership change and any instructions for Finance." required>{{ old('reason') }}</textarea>
                            @error('reason')<span class="transfer-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="transfer-field transfer-full transfer-upload">
                            <label for="handover_documents">Transfer authority / incoming SLA documents <span class="transfer-optional">Optional</span></label>
                            <p>You can create the request without attaching documents.</p>
                            <input id="handover_documents" type="file" name="handover_documents[]" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" multiple aria-describedby="documents-help">
                            <small id="documents-help">PDF, Word, JPG or PNG · Up to 10 files, 10 MB each. Stored privately.</small>
                            <ul id="selected-documents" class="transfer-file-list" aria-live="polite" hidden></ul>
                            @error('handover_documents')<span class="transfer-error">{{ $message }}</span>@enderror
                            @foreach($errors->get('handover_documents.*') as $documentErrors)
                                @foreach($documentErrors as $documentError)
                                <span class="transfer-error">{{ $documentError }}</span>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                </section>
                <div class="transfer-actions"><small>Finance or a Director reviews the request before approval.</small><div><a class="btn btn-light" href="{{ route('property-transfers.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Create transfer request</button></div></div>
            </form>
            <aside class="transfer-guide" aria-labelledby="transfer-guide-heading">
                <h2 id="transfer-guide-heading">Before ownership changes</h2>
                <ol><li><strong>Create request</strong><span>Record the property, incoming landlord and terms.</span></li><li><strong>Finance / Director review</strong><span>Reconcile balances, deposits, tenant credits and payment instructions.</span></li><li><strong>Director approval</strong><span>The Director may review and approve from the same account.</span></li><li><strong>Complete on effective date</strong><span>Apply the approved transfer on the first day of the month.</span></li></ol>
                <div class="transfer-guide-note"><strong>What continues</strong><p>Units, tenant accounts, active tenancies and existing deposits continue under the new ownership. Historical records stay available.</p></div>
                <div class="transfer-guide-note"><strong>Settle before completion</strong><p>Landlord advances, outstanding landlord debits and unpaid returns must be settled separately.</p></div>
            </aside>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script src="{{ asset('assets/js/ownership-transfer.js') }}?v={{ filemtime(public_path('assets/js/ownership-transfer.js')) }}"></script>
@endsection
