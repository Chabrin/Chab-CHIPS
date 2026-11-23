<section class="sla-section sla-details-section" id="sla-documents" aria-labelledby="sla-documents-title">
    <div class="sla-section-heading"><div class="sla-heading-with-icon"><span class="sla-section-icon material-symbols-outlined" aria-hidden="true">description</span><div><span class="sla-eyebrow">SUPPORTING DOCUMENTS</span><h2 id="sla-documents-title">Agreement documents</h2><p>Attach the signed agreement and any supporting files.</p></div></div><span class="sla-optional">Optional</span></div>
    <div class="sla-section-content">
        @if(isset($sla) && $sla->serviceAgreementDocuments->isNotEmpty())
        <div class="sla-existing-documents"><h3>Saved documents</h3>
            @foreach($sla->serviceAgreementDocuments as $document)
            <div class="sla-saved-document existing-document" data-document-id="{{ $document->id }}"><span class="material-symbols-outlined" aria-hidden="true">draft</span><span>{{ $document->title }}</span><button type="button" class="btn btn-link text-danger delete-existing-document" data-document-id="{{ $document->id }}" aria-label="Delete {{ $document->title }}">Delete</button></div>
            @endforeach
        </div>
        @endif
        <div class="sla-document-picker">
            <label for="sla_documents" class="form-label">{{ isset($sla) ? 'Add documents' : 'Choose documents' }}</label>
            <p class="form-text" id="sla-documents-help">PDF, DOC or DOCX, up to 10 MB each. You can select multiple files.</p>
            <input type="file" class="form-control @error('sla_documents.*') is-invalid @enderror" id="sla_documents" name="sla_documents[]" multiple accept=".pdf,.doc,.docx" aria-describedby="sla-documents-help">
            @error('sla_documents')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @error('sla_documents.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</section>
