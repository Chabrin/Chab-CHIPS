@extends('layouts.main')
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/sla-form.css') }}?v={{ filemtime(public_path('assets/css/sla-form.css')) }}">
@endsection
@section('content')
<div class="page-body sla-editor" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card sla-editor-shell">
                    <div class="card-header card-no-border d-flex align-items-center sla-editor-header">
                        <div class="sla-header-copy"><div class="sla-header-meta"><span class="sla-eyebrow">SERVICE AGREEMENT</span><span class="sla-header-badge">{{ match((int) $sla->current_status_id) { 1 => 'Pending approval', 5 => 'Approved', 41 => 'Rejected', default => 'Agreement' } }}</span></div><h1>Edit service agreement</h1><p>{{ $sla->reference_number }} &middot; Review the terms and tenant billing settings.</p></div>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="serviceAgreementForm" class="btn btn-primary btn-sm d-flex align-items-center sla-save-button" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none sla-save-spinner" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">save</span>
                                <span>Update Agreement</span>
                            </button>
                            <a href="{{ route('property.clients.show', Crypt::encrypt($client->id)) }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Client</span>
                            </a>
                        </div>
                    </div>
                    <nav class="sla-section-nav" aria-label="Agreement sections">
                        <a href="#sla-property">Client &amp; property</a><a href="#sla-fees">Commission &amp; VAT</a><a href="#sla-dates">Dates &amp; onboarding</a><a href="#sla-documents">Documents</a><a href="#sla-billing">Tenant charges</a><a href="#sla-returns">Returns</a>
                    </nav>
                    <div class="card-body">
                        @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> Please correct the following errors:
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <form action="{{ route('property.sla.update', Crypt::encrypt($sla->id)) }}" method="POST" enctype="multipart/form-data" id="serviceAgreementForm">
                            @csrf
                            @method('PUT')
                            
                            @include('property.properties.sla.agreement-details')
                            @include('property.properties.sla.documents')

                            @include('property.properties.sla.billing-settings')
                        </form>
                    </div>
                <div class="sla-action-bar" aria-label="Agreement actions">
                    <div class="sla-action-caption"><strong>Ready to save?</strong><small>Your billing items and selected documents are saved with the agreement.</small></div>
                    <div class="sla-action-buttons"><a href="{{ route('property.clients.show', Crypt::encrypt($client->id)) }}" class="btn btn-light">Back to client</a><button type="submit" form="serviceAgreementForm" class="btn btn-primary sla-save-button"><span class="spinner-border spinner-border-sm d-none sla-save-spinner" aria-hidden="true"></span>Update agreement</button></div>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('property.properties.sla.billing-item-modal')
@endsection
@section('scripts')
<script src="{{ asset('assets/js/sla-form.js') }}?v={{ filemtime(public_path('assets/js/sla-form.js')) }}"></script>
<script>
    $(document).ready(function() {
        // Warn immediately if the selected property already has a DIFFERENT
        // current SLA — excludes this SLA's own id so picking the property it's
        // already on (the default) never false-positives against itself. Hard
        // block, not overridable — matches SLAController::
        // findCurrentSlaForProperty()'s server-side check exactly.
        $('#property_id').on('change', function() {
            const propertyId = $(this).val();
            const $warning = $('#currentSlaWarning');
            const $saveButton = $('.sla-save-button');

            $warning.addClass('d-none').text('');
            $saveButton.prop('disabled', false);

            if (!propertyId) return;

            $.ajax({
                url: '{{ route("ajax.sla.check-current") }}',
                method: 'GET',
                data: { property_id: propertyId, exclude_id: {{ $sla->id }} },
                success: function(response) {
                    if (response.exists) {
                        $warning.removeClass('d-none').text(
                            'This property already has a current SLA (' + response.reference_number +
                            ', ends ' + (response.date_end || 'never') +
                            ') — edit it instead, or wait until it ends to create a new one.'
                        );
                        $saveButton.prop('disabled', true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error checking for a current SLA:', xhr.responseText);
                }
            });
        });

        // Form submission with spinner
        $('#serviceAgreementForm').on('submit', function(e) {
            $('.sla-save-spinner').removeClass('d-none');
            $('.sla-save-button').prop('disabled', true);
        });

        // Delete existing document AJAX
        $(document).on('click', '.delete-existing-document', function() {
            if (!confirm('Are you sure you want to delete this document?')) {
                return;
            }

            const documentId = $(this).data('document-id');
            const documentItem = $(this).closest('.existing-document');

            $.ajax({
                url: `/property/sla/documents/${documentId}`,
                type: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    documentItem.fadeOut(300, function() {
                        $(this).remove();
                    });
                    toastr.success('Document deleted successfully');
                },
                error: function(xhr) {
                    console.error('Error deleting document:', xhr);
                    toastr.error('Failed to delete document');
                }
            });
        });

        function escapeHtml(str) { return $('<div>').text(str ?? '').html(); }
        @php($submittedBillingItems = old('billing_items_present') ? old('items', []) : null)
        const billingEditor = window.SlaBillingEditor({
            itemsUrl: '{{ route("ajax.sla.tenant-invoiceable-items") }}',
            initialItems: @json($existingInvoiceableItems),
            submittedItems: @json($submittedBillingItems)
        });


    });
</script>
@endsection
