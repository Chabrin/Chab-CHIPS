@extends('layouts.main')
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/client-create.css') }}?v={{ filemtime(public_path('assets/css/client-create.css')) }}">
@endsection
@section('content')
<div class="page-body" id="client-create" data-client-type="{{ old('client_type_id', '1') }}" data-repeaters-url="{{ url('/ajax/clients/draft-repeaters') }}" data-banks-url="{{ url('/ajax/banks-and-branches') }}" data-identifier-url="{{ route('property.clients.check-identifier') }}" data-clients-url="{{ route('property.clients.index') }}">
    <div class="container-fluid">
        <div class="card client-create-card">
            <header class="card-header client-create-header">
                <div><span class="client-create-eyebrow">Management · Clients</span><h1>Add New Client</h1><p>Enter the client’s details, add the required contacts and accounts, then save the client.</p></div>
                <div class="client-create-actions">
                    <a href="{{ route('property.clients.index') }}" class="btn btn-light d-inline-flex align-items-center gap-1"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Back to List</a>
                </div>
            </header>
            <div class="card-body client-create-body">
                <div id="clientCreateErrors" class="client-error-summary" role="alert" tabindex="-1" @if(!$errors->any()) hidden @endif>
                    <strong>Client not saved. Please correct the following:</strong>
                    <ul>@foreach($errors->getMessages() as $errorKey => $messages)@foreach($messages as $message)<li><button type="button" class="client-error-link" data-error-target="{{ $errorKey }}">{{ $message }}</button></li>@endforeach @endforeach</ul>
                </div>
                <div id="clientCreateStatus" class="client-save-status" role="status" aria-live="polite" @if(!session('client_create_error')) hidden @endif>{{ session('client_create_error') }}</div>
                <form action="{{ route('property.clients.store') }}" method="POST" id="clientCreateForm" enctype="multipart/form-data" novalidate>
                    @csrf
                    <input type="hidden" name="draft_token" id="draft_token" value="{{ old('draft_token', $draftToken) }}">
                    <ul class="nav nav-tabs client-create-tabs" id="clientCreateTabs" role="tablist" aria-label="New client details">
                        <li class="nav-item" role="presentation"><button class="nav-link active" id="personal-tab" data-step-label="Personal Details" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="true"><span class="material-symbols-outlined" aria-hidden="true">person</span>Personal Details</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="banking-tab" data-step-label="Bank Details" data-bs-toggle="tab" data-bs-target="#banking" type="button" role="tab" aria-controls="banking" aria-selected="false"><span class="material-symbols-outlined" aria-hidden="true">account_balance</span>Bank Details<span class="client-tab-required" aria-label="required">*</span></button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="nextofkin-tab" data-step-label="Next of Kin" data-bs-toggle="tab" data-bs-target="#nextofkin" type="button" role="tab" aria-controls="nextofkin" aria-selected="false"><span class="material-symbols-outlined" aria-hidden="true">family_restroom</span>Next of Kin<span class="client-tab-required kin-required-star" aria-label="required">*</span></button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="contacts-tab" data-step-label="Other Contacts" data-bs-toggle="tab" data-bs-target="#contacts" type="button" role="tab" aria-controls="contacts" aria-selected="false"><span class="material-symbols-outlined" aria-hidden="true">contact_phone</span>Other Contacts</button></li>
                    </ul>
                    <div class="client-create-guidance"><span class="material-symbols-outlined" aria-hidden="true">info</span><span id="clientRequirementsHelp">A bank account and next of kin are required for an individual client. Other contacts are optional.</span></div>
                    <div class="tab-content" id="clientCreateTabsContent">
                        @include('property.clients.include.create-personal')
                        <div class="tab-pane fade" id="banking" role="tabpanel" aria-labelledby="banking-tab">@include('property.clients.include.create-repeater', ['kind' => 'bank'])</div>
                        <div class="tab-pane fade" id="nextofkin" role="tabpanel" aria-labelledby="nextofkin-tab">@include('property.clients.include.create-repeater', ['kind' => 'nextofkin'])</div>
                        <div class="tab-pane fade" id="contacts" role="tabpanel" aria-labelledby="contacts-tab">@include('property.clients.include.create-repeater', ['kind' => 'contact'])</div>
                    </div>
                    <footer class="client-create-footer">
                        <button type="button" id="clientNextStep" class="btn btn-primary d-inline-flex align-items-center gap-2" aria-controls="banking"><span class="client-next-label">Next: Bank Details</span><span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></button>
                        <button type="submit" id="createButton" class="btn btn-primary client-submit-button d-inline-flex align-items-center gap-2" hidden disabled><span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span><span class="client-submit-label">Save Client</span></button>
                    </footer>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('assets/js/client-create.js') }}?v={{ filemtime(public_path('assets/js/client-create.js')) }}"></script>
@endsection
