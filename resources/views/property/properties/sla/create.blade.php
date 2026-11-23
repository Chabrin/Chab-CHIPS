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
                        <div class="sla-header-copy"><div class="sla-header-meta"><span class="sla-eyebrow">SERVICE AGREEMENT</span><span class="sla-header-badge">New agreement</span></div><h1>New service agreement</h1><p>Set the agreement terms, attach documents and configure tenant charges.</p></div>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="serviceAgreementForm" class="btn btn-primary btn-sm d-flex align-items-center sla-save-button" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none sla-save-spinner" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save SLA</span>
                            </button>
                            <a href="{{ $client ? route('property.clients.show', $client->encrypted_id) : url('/property/service-agreements') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <nav class="sla-section-nav" aria-label="Agreement sections">
                        <a href="#sla-property">Client &amp; property</a><a href="#sla-fees">Commission &amp; VAT</a><a href="#sla-dates">Dates &amp; onboarding</a><a href="#sla-documents">Documents</a><a href="#sla-billing">Tenant charges</a><a href="#sla-returns">Returns</a>
                    </nav>
                    <div class="card-body">
                        @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Check the highlighted fields.</strong> Your agreement could not be saved.
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <form action="{{ route('service.agreements.store') }}" method="POST" id="serviceAgreementForm" enctype="multipart/form-data">
                            @csrf

                            @include('property.properties.sla.agreement-details')
                            @include('property.properties.sla.documents')

                            @include('property.properties.sla.billing-settings')
                        </form>
                    </div>
                <div class="sla-action-bar" aria-label="Agreement actions">
                    <div class="sla-action-caption"><strong>Ready to save?</strong><small>Your billing items and selected documents are saved with the agreement.</small></div>
                    <div class="sla-action-buttons"><a href="{{ $client ? route('property.clients.show', $client->encrypted_id) : url('/property/service-agreements') }}" class="btn btn-light">Back to client</a><button type="submit" form="serviceAgreementForm" class="btn btn-primary sla-save-button"><span class="spinner-border spinner-border-sm d-none sla-save-spinner" aria-hidden="true"></span>Save SLA</button></div>
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
        // Set when the page was opened from a clone link; applied once the SLA list has loaded
        const cloneSourceId = @json($cloneSourceId ?? null);
        let cloneApplied = false;

        // Form submission with spinner
        $('#serviceAgreementForm').on('submit', function(e) {
            $('.sla-save-spinner').removeClass('d-none');
            $('.sla-save-button').prop('disabled', true);
        });

        // Load properties via AJAX when page loads (if client is preset)
        const clientIdInput = $('input[name="client_id"]').val();
        if (clientIdInput) {
            loadProperties(clientIdInput);
            loadCloneOptions(clientIdInput);
        } else {
            // Select2 AJAX search for client
            $('#client_id').select2({
                placeholder: 'Type to search client...',
                allowClear: true,
                minimumInputLength: 2,
                width: '100%',
                ajax: {
                    url: '{{ route("ajax.clients.search.select2") }}',
                    dataType: 'json',
                    delay: 300,
                    data: function(params) { return { q: params.term }; },
                    processResults: function(data) { return { results: data.results }; },
                    cache: true
                }
            });

            // Load properties when a client is selected
            $('#client_id').on('change', function() {
                const val = $(this).val();
                if (val) {
                    loadProperties(val);
                    loadCloneOptions(val);
                } else {
                    $('#property_id').html('<option value="">Select client first</option>').prop('disabled', true);
                    resetCloneOptions();
                }
            });
        }

        // Function to load properties for the client
        function loadProperties(clientId) {
            $('#property_id').html('<option value="">Loading properties...</option>').prop('disabled', true);
            
            $.ajax({
                url: `/client/${clientId}/properties`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Properties loaded:', response);
                    $('#property_id').empty().prop('disabled', false);
                    $('#property_id').append('<option value="">Please select</option>');
                    
                    if (response.properties && response.properties.length > 0) {
                        response.properties.forEach(function(property) {
                            $('#property_id').append(new Option(property.property_name, property.id));
                        });
                    } else {
                        $('#property_id').append('<option value="">No properties found for this client</option>');
                    }
                    const restoredProperty = $('#property_id').attr('data-selected-property');
                    if (restoredProperty) {
                        $('#property_id').val(restoredProperty).removeAttr('data-selected-property').trigger('change');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading properties:', xhr.responseText);
                    $('#property_id').empty().prop('disabled', false);
                    $('#property_id').append('<option value="">Error loading properties</option>');
                }
            });
        }

        // Warn immediately if the selected property already has a current SLA,
        // rather than letting the team fill out the whole form only to be
        // rejected on submit. Hard block, not overridable — matches
        // SLAController::findCurrentSlaForProperty()'s server-side check exactly.
        // The registration fee starts ticked for a property's first SLA and unticked for a
        // renewal. Once the user touches the switch themselves, we stop changing it.
        let registrationFeeTouched = false;
        $('#charge_registration_fee').on('change', function() { registrationFeeTouched = true; });

        $('#property_id').on('change', function() {
            const propertyId = $(this).val();
            const $warning = $('#currentSlaWarning');
            const $saveButton = $('.sla-save-button');

            $warning.addClass('d-none').text('');
            $saveButton.prop('disabled', false);
            $('#registrationFeeHint').text('');

            if (!propertyId) return;

            $.ajax({
                url: '{{ route("ajax.sla.check-current") }}',
                method: 'GET',
                data: { property_id: propertyId },
                success: function(response) {
                    if (response.exists) {
                        $warning.removeClass('d-none').text(
                            'This property already has a current SLA (' + response.reference_number +
                            ', ends ' + (response.date_end || 'never') +
                            ') — edit it instead, or wait until it ends to create a new one.'
                        );
                        $saveButton.prop('disabled', true);
                    }

                    if (!registrationFeeTouched) {
                        $('#charge_registration_fee').prop('checked', !response.is_renewal);
                    }
                    $('#registrationFeeHint').text(response.is_renewal
                        ? 'This property already had an SLA, so the registration fee is off by default. Switch it on to charge it.'
                        : '');
                },
                error: function(xhr, status, error) {
                    console.error('Error checking for a current SLA:', xhr.responseText);
                }
            });
        });

        function escapeHtml(str) { return $('<div>').text(str ?? '').html(); }
        @php($submittedBillingItems = old('billing_items_present') ? old('items', []) : null)
        const billingEditor = window.SlaBillingEditor({
            itemsUrl: '{{ route("ajax.sla.tenant-invoiceable-items") }}',
            initialItems: @json([]),
            submittedItems: @json($submittedBillingItems)
        });

        // Copy terms from an existing SLA: the dropdown lists the client's SLAs, picking one
        // fills the form with its commercial terms. Property, documents, dates and the
        // registration fee are left alone for the user to set.
        function resetCloneOptions() {
            $('#cloneSlaBlock').addClass('d-none');
            $('#clone_sla_id').html('<option value="">Start from a blank form</option>');
            $('#cloneSlaNote, #cloneSlaError').text('');
        }

        function loadCloneOptions(clientId) {
            resetCloneOptions();

            $.ajax({
                url: '{{ route("ajax.sla.clone-options") }}',
                method: 'GET',
                data: { client_id: clientId },
                success: function(response) {
                    const slas = response.slas || [];
                    if (slas.length === 0) return;

                    slas.forEach(function(sla) {
                        $('#clone_sla_id').append(`<option value="${sla.id}">${escapeHtml(sla.label)}</option>`);
                    });
                    $('#cloneSlaBlock').removeClass('d-none');

                    if (cloneSourceId && !cloneApplied) {
                        cloneApplied = true;
                        $('#clone_sla_id').val(String(cloneSourceId)).trigger('change');
                    }
                },
                error: function(xhr) {
                    console.error('Error loading SLAs to copy from:', xhr.responseText);
                }
            });
        }

        function applyCloneTerms(terms) {
            $('#commission_percentage').val(terms.commission_percentage);

            // The VAT change handlers reset the VAT fields, so set the VAT values after them
            $('#vat_able').val(terms.vat_able).trigger('change');
            if (!$('#vat_percentage').prop('disabled')) $('#vat_percentage').val(terms.vat_percentage ?? '');
            if (!$('#flat_rate_vat').prop('disabled')) $('#flat_rate_vat').val(terms.flat_rate_vat ?? '');

            $('#auto_renewal').val(terms.auto_renewal);
            $('#move_in_inspection_required').val(terms.move_in_inspection_required);
            $('#payment_date').val(terms.payment_date ?? '');
            $('#duration_years').val(terms.duration_years ?? '').trigger('change');
            $('input[name="returns_combination"]').prop('checked', false);
            // All combined is hidden on new agreements. Use an available choice and explain it below.
            const returnsChoice = terms.returns_combination === 'all_combined' ? 'each_separate' : terms.returns_combination;
            $('input[name="returns_combination"]').filter(function() { return this.value === returnsChoice; }).prop('checked', true);

            billingEditor.setItems(terms.items || []);
        }

        $('#clone_sla_id').on('change', function() {
            const slaId = $(this).val();
            const $note = $('#cloneSlaNote').text('');
            const $error = $('#cloneSlaError').text('');

            if (!slaId) return;

            $.ajax({
                url: '{{ route("ajax.sla.clone-terms", ["id" => "__ID__"]) }}'.replace('__ID__', slaId),
                method: 'GET',
                success: function(response) {
                    applyCloneTerms(response.terms);
                    $note.text('Copied from ' + $('#clone_sla_id option:selected').text() + '. Now pick the property.');
                    if (response.terms.returns_combination === 'all_combined') {
                        $note.append(document.createTextNode(' All combined is unavailable for new agreements, so Returns Combination is set to Each separate. Review this before saving.'));
                    }
                },
                error: function(xhr) {
                    console.error('Error loading SLA terms to copy:', xhr.responseText);
                    $error.text('Could not copy the terms from that SLA. Fill the form in by hand or try again.');
                }
            });
        });

    });
</script>
@endsection
