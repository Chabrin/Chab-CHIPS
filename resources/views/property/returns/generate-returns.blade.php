@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/chabrin.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <!-- Generate Returns Card -->
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Generate Client Returns</h5>
                        <div class="ms-auto">
                            <a href="{{ route('returns.batches.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Info Alert -->
                        <div class="info-alert">
                            <div class="d-flex align-items-start">
                                <span class="material-symbols-outlined me-2" style="color: #2196F3;">info</span>
                                <div>
                                    <strong>Client Returns Generation</strong>
                                    <p class="mb-0 mt-1">Select a property and returns month to generate client returns from approved invoices.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Form -->
                        <form id="generateReturnsForm" data-current-year="{{ date('Y') }}" data-current-month="{{ date('n') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <span class="material-symbols-outlined align-middle" style="font-size: 18px;">filter_alt</span>
                                            Property Selection <span class="text-danger">*</span>
                                        </label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="property_selection" id="all_properties" value="all" checked>
                                                <label class="form-check-label" for="all_properties">
                                                    All Properties
                                                </label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="property_selection" id="one_property" value="one">
                                                <label class="form-check-label" for="one_property">
                                                    Specific Property
                                                </label>
                                            </div>
                                        </div>
                                        <small class="text-muted">Choose to generate returns for all properties or a specific one</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="returns_month" class="form-label">
                                            <span class="material-symbols-outlined align-middle" style="font-size: 18px;">event</span>
                                            Returns Month <span class="text-danger">*</span>
                                        </label>
                                        <input type="month" class="form-control month-select" id="returns_month" name="returns_month" required>
                                        <small class="text-muted">Select the month for returns generation (last 3 months available)</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row" id="property_selector" style="display: none;">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="property_search_input" class="form-label">
                                            <span class="material-symbols-outlined align-middle" style="font-size: 18px;">home</span>
                                            Search Property
                                        </label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="property_search_input"
                                                placeholder="Search by property name">
                                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="search_property_btn">
                                                <span class="material-symbols-outlined" id="search_property_icon">search</span>
                                                <span class="spinner-border spinner-border-sm" role="status" id="search_property_spinner" style="display: none;"></span>
                                            </button>
                                        </div>
                                        <small class="text-muted">Enter at least 2 characters to search</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="property_select" class="form-label">Select Property <span class="text-danger">*</span></label>
                                        <select class="form-select" id="property_select" name="property_id" disabled>
                                            <option value="">-- Select Property --</option>
                                        </select>
                                        <input type="hidden" id="property_id" name="property_id">
                                        <small class="text-muted d-block mt-1">
                                            <strong>Selected:</strong> <span id="selected_property_name">-</span>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <!-- Generation Settings -->
                                    <div class="mb-3">
                                        <h6 class="mb-2">
                                            <span class="material-symbols-outlined align-middle" style="font-size: 18px;">info</span>
                                            Generation Settings
                                        </h6>
                                        <small class="text-muted d-block">
                                            <span class="material-symbols-outlined align-middle" style="font-size: 16px;">check_circle</span>
                                            Only approved invoices (Status 5) will be included
                                        </small>
                                        <small class="text-muted d-block">
                                            <span class="material-symbols-outlined align-middle" style="font-size: 16px;">check_circle</span>
                                            Invoices without existing returns will be processed
                                        </small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <!-- Action Button -->
                                    <div class="mb-3">
                                        <label class="form-label d-block">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary" id="generate-btn">
                                            <span class="material-symbols-outlined align-middle" style="font-size: 18px;">autorenew</span>
                                            Generate Returns
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .info-alert {
        background-color: #e3f2fd;
        border-left: 4px solid #2196F3;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }

    .month-select {
        font-size: 1.1rem;
        padding: 10px;
    }
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        const currentYear = parseInt($('#generateReturnsForm').data('current-year'));
        const currentMonth = parseInt($('#generateReturnsForm').data('current-month'));

        // Calculate min month (3 months back)
        let minYear = currentYear;
        let minMonth = currentMonth - 3;
        if (minMonth <= 0) {
            minMonth = 12 + minMonth;
            minYear = currentYear - 1;
        }

        // Set month restrictions - allow 1 month ahead for testing
        let maxMonth = currentMonth + 1;
        let maxYear = currentYear;
        if (maxMonth > 12) {
            maxMonth = 1;
            maxYear = currentYear + 1;
        }
        const minDate = `${minYear}-${String(minMonth).padStart(2, '0')}`;
        const maxDate = `${maxYear}-${String(maxMonth).padStart(2, '0')}`;

        $('#returns_month').attr('min', minDate);
        $('#returns_month').attr('max', maxDate);
        $('#returns_month').val(maxDate);

        // Handle property selection radio buttons
        $('input[name="property_selection"]').on('change', function() {
            if ($(this).val() === 'one') {
                $('#property_selector').show();
                $('#property_select').prop('required', true);
            } else {
                $('#property_selector').hide();
                $('#property_select').prop('required', false);
                $('#property_select').val('');
                $('#property_id').val('');
                $('#selected_property_name').text('-');
            }
        });

        // Property search functionality
        $('#search_property_btn').on('click', function() {
            const searchTerm = $('#property_search_input').val().trim();

            if (searchTerm.length < 2) {
                toastr.warning('Please enter at least 2 characters to search');
                return;
            }

            // Show loading state
            $('#search_property_icon').hide();
            $('#search_property_spinner').show();
            $('#search_property_btn').prop('disabled', true);
            $('#property_search_input').prop('disabled', true);

            $.ajax({
                url: '/ajax/returns/properties/search',
                type: 'GET',
                data: {
                    search: searchTerm
                },
                success: function(response) {
                    if (response.success && response.properties && response.properties.length > 0) {
                        $('#property_select').empty().append('<option value="">-- Select Property --</option>');

                        $.each(response.properties, function(index, property) {
                            let displayText = property.property_name;
                            if (property.description && property.description !== property.property_name) {
                                displayText += ' - ' + property.description;
                            }
                            if (property.reference_number) {
                                displayText += ' (' + property.reference_number + ')';
                            }

                            $('#property_select').append(
                                `<option value="${property.id}" data-name="${property.property_name}">
                                    ${displayText}
                                </option>`
                            );
                        });

                        $('#property_select').prop('disabled', false);
                        toastr.success(`Found ${response.properties.length} property(ies)`);
                    } else {
                        toastr.info('No properties found');
                        $('#property_select').empty().append('<option value="">-- No Results --</option>').prop('disabled', true);
                    }
                },
                error: function(xhr) {
                    toastr.error('Error searching properties');
                    console.error(xhr);
                },
                complete: function() {
                    $('#search_property_icon').show();
                    $('#search_property_spinner').hide();
                    $('#search_property_btn').prop('disabled', false);
                    $('#property_search_input').prop('disabled', false);
                }
            });
        });

        // Allow search with Enter key
        $('#property_search_input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#search_property_btn').click();
            }
        });

        // When property is selected from dropdown
        $('#property_select').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const propertyId = $(this).val();

            if (propertyId) {
                $('#selected_property_name').text(selectedOption.data('name'));
                $('#property_id').val(propertyId);
            } else {
                $('#selected_property_name').text('-');
                $('#property_id').val('');
            }
        });
    });

    // Submit form
    $('#generateReturnsForm').on('submit', function(e) {
        e.preventDefault();

        const propertySelection = $('input[name="property_selection"]:checked').val();
        const propertyId = $('#property_id').val();
        const returnsMonth = $('#returns_month').val();

        // Validate based on selection
        if (propertySelection === 'one' && !propertyId) {
            Swal.fire({
                icon: 'warning',
                title: 'Validation Error',
                text: 'Please select a property',
                allowOutsideClick: false
            });
            return;
        }

        if (!returnsMonth) {
            Swal.fire({
                icon: 'warning',
                title: 'Validation Error',
                text: 'Please select a returns month',
                allowOutsideClick: false
            });
            return;
        }

        // Show processing alert
        Swal.fire({
            title: 'Generating Returns',
            html: propertySelection === 'all' ?
                'Please wait while we generate client returns for all properties...' : 'Please wait while we generate client returns from approved invoices...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            allowEnterKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Prepare data
        let requestData = {
            _token: '{{ csrf_token() }}',
            property_selection: propertySelection,
            returns_month: returnsMonth
        };

        // Add property_id only if specific property is selected
        if (propertySelection === 'one') {
            requestData.property_id = propertyId;
        }

        // Submit the request
        $.ajax({
            url: '{{ route("returns.generate.process") }}',
            method: 'POST',
            data: requestData,
            success: function(response) {
                if (response.success) {
                    // Build message with merge batch info if present
                    let detailsHtml = `<span style=\"font-family:Roboto,Arial,sans-serif;font-size:1rem;\"><strong>${response.message}</strong></span>`;

                    if (response.merge_batch) {
                        detailsHtml += `<br><br><div style="background:#e3f2fd;padding:10px;border-radius:6px;text-align:left;">
                            <span style="font-family:Roboto,Arial,sans-serif;font-size:0.9rem;display:flex;align-items:center;">
                                <span class="material-symbols-outlined" style="color:#1976d2;font-size:1.3em;margin-right:6px;">merge</span>
                                <strong>Merge Batch Detected!</strong>
                            </span>
                            <span style="font-family:Roboto,Arial,sans-serif;font-size:0.85rem;color:#555;display:block;margin-top:4px;">
                                Batch: <strong>${response.merge_batch.batch_reference}</strong><br>
                                ${response.merge_batch.properties_count} properties regenerated together
                            </span>
                        </div>`;
                    }

                    detailsHtml += `<br><span style=\"font-family:Roboto,Arial,sans-serif;font-size:1rem;display:flex;align-items:center;\"><span class=\"material-symbols-outlined\" style=\"color:#2196F3;font-size:1.2em;margin-right:4px;vertical-align:middle;\">receipt_long</span>Total returns generated: <b>${response.count}</b></span>`;

                    Swal.fire({
                        icon: 'success',
                        title: '<span style="font-family:Roboto,Arial,sans-serif;font-weight:600;font-size:1.1rem;letter-spacing:0.5px;display:flex;align-items:center;"><span class="material-symbols-outlined" style="color:#2196F3;font-size:1.5em;margin-right:6px;vertical-align:middle;">check_circle</span>Success!</span>',
                        html: detailsHtml,
                        allowOutsideClick: false,
                        confirmButtonText: '<span style="display:flex;align-items:center;"><span class="material-symbols-outlined" style="font-size:1.2em;margin-right:4px;vertical-align:middle;">done</span>OK</span>',
                        customClass: {
                            popup: 'swal2-bluish',
                            confirmButton: 'swal2-bluish-btn'
                        },
                        buttonsStyling: false
                    }).then(() => {
                        // Redirect to list page
                        window.location.href = '{{ route("returns.batches.index") }}';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Generation Failed',
                        text: response.message || 'Failed to generate returns',
                        allowOutsideClick: false
                    });
                }
            },
            error: function(xhr) {
                let errorMessage = 'Failed to generate returns. Please try again.';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMessage,
                    allowOutsideClick: false
                });
            }
        });
    });
</script>
@endsection