{{--
    The signed contract box on the contract forms. One copy of the signed contract is kept: when a file is already on record the box
    shows it (name, date, who uploaded it) with View and Remove and hides the upload button; Remove asks first, keeps the file in
    history, and then the upload button appears. public/assets/js/signed-contract.js does the showing and hiding.

    $document  the file on record as ['id', 'name', 'uploaded', 'by', 'attachedOnSave'], or null (the Add form fills it in when a staff member is picked)
    $optional  whether to say the upload is optional
--}}
@php
    $document = $document ?? null;
    $user = auth()->user();
    // Only the HR role and the Directors can open or remove staff documents (config/staff_documents.php)
    $access = app(\App\Services\StaffDocumentAccess::class);
    $canView = $access->allows($user);
    $canRemove = $access->allowsChanges($user);
    // "Uploaded 12 Nov 2026 by Hannah", with whatever part is known
    $meta = '';
    if ($document && (!empty($document['uploaded']) || !empty($document['by']))) {
        $meta = trim('Uploaded ' . ($document['uploaded'] ?? '') . (!empty($document['by']) ? ' by ' . $document['by'] : ''));
    }
@endphp
<label for="signed_contract" class="form-label">Signed Contract (PDF)@if($optional ?? false) <span class="text-muted small">- optional</span>@endif</label>

<div id="signed_contract_card" class="border rounded p-2 mb-2 {{ $document ? '' : 'd-none' }}" data-documents-url="{{ url('/documents') }}" data-can-remove="{{ $canRemove ? '1' : '0' }}">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="material-symbols-outlined text-primary" aria-hidden="true">description</span>
        <div class="me-auto" style="min-width:0;">
            <div class="fw-semibold text-break" id="signed_contract_name">{{ $document['name'] ?? '' }}</div>
            <div class="small text-muted" id="signed_contract_meta">{{ $meta }}</div>
            <div class="small text-muted {{ !empty($document['attachedOnSave']) ? '' : 'd-none' }}" id="signed_contract_note">It is attached to this contract when you save.</div>
        </div>
        @if($canView)
        <a class="btn btn-sm btn-outline-primary" id="signed_contract_view" target="_blank" rel="noopener" href="{{ $document ? route('documents.view', $document['id']) : '#' }}">View</a>
        @endif
        @if($canRemove)
        <button type="button" class="btn btn-sm btn-outline-danger" id="signed_contract_remove" data-document-id="{{ $document['id'] ?? '' }}">Remove</button>
        @endif
    </div>
</div>

<div id="signed_contract_upload" class="{{ $document ? 'd-none' : '' }}">
    <input type="file" class="form-control @error('signed_contract') is-invalid @enderror" name="signed_contract" id="signed_contract" accept=".pdf,application/pdf" @if($document) disabled @endif>
    <small class="text-muted">Upload the physically signed contract as a PDF (max 5MB). Only one copy is kept: to replace it, remove the current one first.</small>
    @error('signed_contract')
    <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
