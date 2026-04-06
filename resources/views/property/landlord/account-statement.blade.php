@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/chabrin.css') }}">
<style>
    .card-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.85);
        z-index: 10;
        display: none;
        justify-content: center;
        align-items: center;
    }

    .card-overlay.active {
        display: flex;
    }

    .card.position-relative {
        position: relative;
    }

    .btn-outline-primary.date-filter-radio {
        color: #3598db;
        border-color: #3598db;
    }

    .btn-outline-primary.date-filter-radio:hover {
        color: #fff;
        background-color: #3598db;
        border-color: #3598db;
    }

    .btn-check:checked+.btn-outline-primary.date-filter-radio {
        color: #fff;
        background-color: #3598db;
        border-color: #3598db;
    }

    .sticky-top {
        position: sticky;
        top: 0;
        z-index: 10;
        background: white;
    }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card position-relative">
                    <div class="card-overlay" id="card-overlay">
                        <div class="text-center">
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Processing...</p>
                        </div>
                    </div>
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Landlord Account Statement</h5>
                    </div>
                    <div class="card-body">
                        <form id="searchForm">
                            <!-- Search Mode Toggle -->
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label d-block">Search By <span class="text-danger">*</span></label>
                                    <div class="btn-group" role="group" aria-label="Search mode">
                                        <input type="radio" class="btn-check" name="search_mode" id="mode_property" value="property" checked autocomplete="off">
                                        <label class="btn btn-outline-primary date-filter-radio" for="mode_property">
                                            <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">apartment</span>
                                            Property
                                        </label>
                                        <input type="radio" class="btn-check" name="search_mode" id="mode_landlord" value="landlord" autocomplete="off">
                                        <label class="btn btn-outline-primary date-filter-radio" for="mode_landlord">
                                            <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">person</span>
                                            Landlord
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- MODE: Search by Property -->
                            <div id="property_mode_section">
                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <label for="property_search_input" class="form-label mb-1">Search Property</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-sm" id="property_search_input"
                                                placeholder="-- Search --">
                                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="search_property_btn">
                                                <span class="material-symbols-outlined" id="search_property_icon" style="font-size:18px;">search</span>
                                                <span class="spinner-border spinner-border-sm" role="status" id="search_property_spinner" style="display:none;"></span>
                                            </button>
                                        </div>
                                        <small class="text-muted">Search by property name or reference</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="property_select" class="form-label mb-1">Select Property</label>
                                        <select class="form-select form-select-sm" id="property_select" disabled>
                                            <option value="">-- Select Property --</option>
                                        </select>
                                        <input type="hidden" id="property_id" name="property_id">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <label for="landlord_select" class="form-label mb-1">Select Landlord <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="landlord_select" name="landlord_id" disabled required style="width:100%;">
                                            <option value="">-- Select Property First --</option>
                                        </select>
                                        <input type="hidden" id="landlord_id" name="landlord_id">
                                    </div>
                                </div>
                            </div>

                            <!-- MODE: Search by Landlord -->
                            <div id="landlord_mode_section" style="display:none;">
                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <label for="landlord_search_input" class="form-label mb-1">Search Landlord</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-sm" id="landlord_search_input"
                                                placeholder="-- Search --">
                                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="search_landlord_btn">
                                                <span class="material-symbols-outlined" id="search_landlord_icon" style="font-size:18px;">search</span>
                                                <span class="spinner-border spinner-border-sm" role="status" id="search_landlord_spinner" style="display:none;"></span>
                                            </button>
                                        </div>
                                        <small class="text-muted">Search by name, reference, phone, email or ID number</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="landlord_direct_select" class="form-label mb-1">Select Landlord <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="landlord_direct_select" disabled>
                                            <option value="">-- Search First --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Landlord Details (shared) -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <small class="text-muted">
                                        <strong>Name:</strong> <span id="selected_landlord_name">-</span> |
                                        <strong>Phone:</strong> <span id="selected_landlord_mobile">-</span> |
                                        <strong>Email:</strong> <span id="selected_landlord_email">-</span> |
                                        <strong>ID Number:</strong> <span id="selected_landlord_id">-</span>
                                    </small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label d-block">Date Filter Type <span class="text-danger">*</span></label>
                                    <div class="btn-group" role="group" aria-label="Date filter type">
                                        <input type="radio" class="btn-check" name="date_type" id="date_type_range" value="range" checked autocomplete="off">
                                        <label class="btn btn-outline-primary date-filter-radio" for="date_type_range">
                                            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">date_range</span>
                                            Date Range
                                        </label>

                                        <input type="radio" class="btn-check" name="date_type" id="date_type_end" value="end" autocomplete="off">
                                        <label class="btn btn-outline-primary date-filter-radio" for="date_type_end">
                                            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event</span>
                                            End Date Only
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="date_from" class="form-label">Date From <span class="text-danger" id="date_from_required">*</span></label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="date_to" class="form-label">Date To <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_to" name="date_to" required>
                                </div>
                                <div class="col-md-4 d-flex align-items-end gap-2">
                                    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="search_btn">
                                        <span class="material-symbols-outlined" id="search_icon">search</span>
                                        <span class="spinner-border spinner-border-sm" role="status" id="search_spinner" style="display: none;"></span>
                                        <span class="ms-1">View Statement</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statement Modal -->
<div class="modal fade" id="statementModal" tabindex="-1" aria-labelledby="statementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
            <!-- Thin Header with Actions -->
            <div class="modal-header py-2 px-3 bg-white border-bottom">
                <div class="d-flex align-items-center gap-2 w-100">
                    <span class="material-symbols-outlined text-dark">description</span>
                    <h6 class="mb-0 flex-grow-1 text-dark">Landlord Account Statement</h6>
                    <button type="button" class="btn btn-sm d-flex align-items-center" id="download_excel_btn" style="padding: 2px 8px; background-color: #3598db !important; color: white !important; border-color: #3598db !important;">
                        <span class="material-symbols-outlined" id="excel_icon" style="font-size: 18px;">download</span>
                        <span class="spinner-border spinner-border-sm" id="excel_spinner" role="status" style="display: none; font-size: 12px; width: 16px; height: 16px;"></span>
                        <span class="ms-1" style="font-size: 0.75rem;">Excel</span>
                    </button>
                    <button type="button" class="btn btn-sm d-flex align-items-center" id="download_pdf_btn" style="padding: 2px 8px; background-color: #3598db !important; color: white !important; border-color: #3598db !important;">
                        <span class="material-symbols-outlined" id="pdf_icon" style="font-size: 18px;">picture_as_pdf</span>
                        <span class="spinner-border spinner-border-sm" id="pdf_spinner" role="status" style="display: none; font-size: 12px; width: 16px; height: 16px;"></span>
                        <span class="ms-1" style="font-size: 0.75rem;">PDF</span>
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="padding: 4px; font-size: 0.875rem;"></button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-0" style="background-color: #f8f9fa;">
                <!-- Search Bar -->
                <div class="p-3 bg-white border-bottom">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-0">
                            <span class="material-symbols-outlined" style="font-size: 18px;">search</span>
                        </span>
                        <input type="text" class="form-control border-0 bg-light" id="statement_search" placeholder="Search transactions...">
                    </div>
                </div>

                <!-- Statement Info Banner -->
                <div class="px-3 py-2 bg-white border-bottom">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                <strong>Landlord:</strong> <span id="stmt_landlord_name"></span>
                            </small>
                        </div>
                        <div class="col-6 text-end">
                            <small class="d-block" style="font-size: 0.75rem;">
                                <strong>Balance:</strong> <span id="stmt_total_amount" class="fw-bold text-dark"></span>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Scrollable Table -->
                <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                    <table class="table table-hover table-sm table-borderless mb-0" id="statement_table" style="font-size: 0.82rem; line-height: 1.2;">
                        <thead class="sticky-top" style="background-color: #e9ecef; border-bottom: 2px solid #dee2e6; font-size: 0.82rem;">
                            <tr>
                                <th class="py-1 px-2" style="white-space:nowrap;">Date</th>
                                <th class="py-1 px-2" style="white-space:nowrap;">Reference</th>
                                <th class="py-1 px-2">Narration</th>
                                <th class="py-1 px-2 text-end" style="white-space:nowrap;">Dr</th>
                                <th class="py-1 px-2 text-end" style="white-space:nowrap;">Cr</th>
                                <th class="py-1 px-2 text-end" style="white-space:nowrap;">Balance</th>
                            </tr>
                        </thead>
                        <tbody id="statement_tbody" class="bg-white">
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted mb-0" style="font-size: 0.875rem;">Loading statement...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Helper to clear landlord details
        function clearLandlordDetails() {
            $('#selected_landlord_name').text('-');
            $('#selected_landlord_mobile').text('-');
            $('#selected_landlord_email').text('-');
            $('#selected_landlord_id').text('-');
            $('#landlord_id').val('');
        }

        // Mode toggle
        $('input[name="search_mode"]').on('change', function() {
            var mode = $(this).val();
            clearLandlordDetails();
            if (mode === 'property') {
                $('#property_mode_section').show();
                $('#landlord_mode_section').hide();
            } else {
                $('#property_mode_section').hide();
                $('#landlord_mode_section').show();
            }
        });

        // Search landlord directly
        $('#search_landlord_btn').on('click', function() {
            var searchTerm = $('#landlord_search_input').val().trim();
            if (searchTerm.length < 2) {
                toastr.warning('Please enter at least 2 characters to search');
                return;
            }

            $('#search_landlord_icon').hide();
            $('#search_landlord_spinner').show();
            $('#search_landlord_btn').prop('disabled', true);

            $.ajax({
                url: '/ajax/landlord/search-landlords',
                method: 'GET',
                data: {
                    search: searchTerm
                },
                success: function(response) {
                    $('#landlord_direct_select').empty().append('<option value="">-- Select Landlord --</option>');
                    if (response.success && response.landlords && response.landlords.length > 0) {
                        $.each(response.landlords, function(i, l) {
                            $('#landlord_direct_select').append(
                                '<option value="' + l.encrypted_id + '"' +
                                ' data-name="' + (l.names || '') + '"' +
                                ' data-mobile="' + (l.mobile_number || 'N/A') + '"' +
                                ' data-email="' + (l.email_address || 'N/A') + '"' +
                                ' data-id-number="' + (l.national_id || 'N/A') + '">' +
                                (l.names || '') + ' - ' + (l.reference_number || '') +
                                '</option>'
                            );
                        });
                        $('#landlord_direct_select').prop('disabled', false);
                        toastr.success('Found ' + response.landlords.length + ' landlord(s)');
                    } else {
                        toastr.info('No landlords found');
                        $('#landlord_direct_select').prop('disabled', true);
                    }
                },
                error: function() {
                    toastr.error('Error searching landlords');
                },
                complete: function() {
                    $('#search_landlord_icon').show();
                    $('#search_landlord_spinner').hide();
                    $('#search_landlord_btn').prop('disabled', false);
                }
            });
        });

        $('#landlord_search_input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#search_landlord_btn').click();
            }
        });

        // When landlord selected from direct search
        $('#landlord_direct_select').on('change', function() {
            var $sel = $(this).find(':selected');
            if ($(this).val()) {
                $('#selected_landlord_name').text($sel.data('name'));
                $('#selected_landlord_mobile').text($sel.data('mobile'));
                $('#selected_landlord_email').text($sel.data('email'));
                $('#selected_landlord_id').text($sel.data('id-number'));
                $('#landlord_id').val($(this).val());
            } else {
                clearLandlordDetails();
            }
        });

        // Search property
        $('#search_property_btn').on('click', function() {
            const searchTerm = $('#property_search_input').val().trim();
            if (searchTerm.length < 2) {
                toastr.warning('Please enter at least 2 characters to search');
                return;
            }

            $('#search_property_icon').hide();
            $('#search_property_spinner').show();
            $('#search_property_btn').prop('disabled', true);

            $.ajax({
                url: '/ajax/landlord/properties',
                method: 'GET',
                data: {
                    search: searchTerm
                },
                success: function(response) {
                    if (response.success && response.properties && response.properties.length > 0) {
                        $('#property_select').empty().append('<option value="">-- Select Property --</option>');
                        $.each(response.properties, function(index, prop) {
                            $('#property_select').append(
                                `<option value="${prop.id}">${prop.property_name} - ${prop.reference_number || ''}</option>`
                            );
                        });
                        $('#property_select').prop('disabled', false);
                        toastr.success(`Found ${response.properties.length} property(ies)`);
                    } else {
                        toastr.info('No properties found');
                        $('#property_select').empty().append('<option value="">-- No Results --</option>').prop('disabled', true);
                    }
                },
                error: function() {
                    toastr.error('Error searching properties');
                },
                complete: function() {
                    $('#search_property_icon').show();
                    $('#search_property_spinner').hide();
                    $('#search_property_btn').prop('disabled', false);
                }
            });
        });

        $('#property_search_input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#search_property_btn').click();
            }
        });

        // When property is selected, load landlords
        $('#property_select').on('change', function() {
            const propertyId = $(this).val();
            $('#property_id').val(propertyId);

            // Destroy existing Select2 if any
            if ($('#landlord_select').hasClass('select2-hidden-accessible')) {
                $('#landlord_select').select2('destroy');
            }

            $('#landlord_select').empty().append('<option value="">-- Select Property First --</option>').prop('disabled', true);
            $('#landlord_id').val('');
            $('#selected_landlord_name').text('-');
            $('#selected_landlord_mobile').text('-');
            $('#selected_landlord_email').text('-');
            $('#selected_landlord_id').text('-');

            if (!propertyId) return;

            $('#landlord_select').empty().append('<option value="">Loading...</option>');

            $.ajax({
                url: '/ajax/landlord/landlords-by-property',
                method: 'GET',
                data: {
                    property_id: propertyId
                },
                success: function(response) {
                    $('#landlord_select').empty().append('<option value="">-- Search / Select Landlord --</option>');
                    if (response.success && response.landlords && response.landlords.length > 0) {
                        $.each(response.landlords, function(index, landlord) {
                            $('#landlord_select').append(
                                `<option value="${landlord.encrypted_id}" 
                                         data-name="${landlord.names}" 
                                         data-mobile="${landlord.mobile_number || 'N/A'}" 
                                         data-email="${landlord.email_address || 'N/A'}" 
                                         data-id-number="${landlord.national_id || 'N/A'}">
                                    ${landlord.names} - ${landlord.reference_number || ''} - ${landlord.mobile_number || ''}
                                </option>`
                            );
                        });
                        toastr.success(`${response.landlords.length} landlord(s) loaded`);
                    } else {
                        toastr.info('No landlords found for this property');
                    }
                    $('#landlord_select').prop('disabled', false);
                    $('#landlord_select').select2({
                        placeholder: '-- Search / Select Landlord --',
                        allowClear: true,
                        width: '100%',
                        minimumResultsForSearch: 0
                    });

                    // Auto-select if only one landlord for this property
                    if (response.landlords.length === 1) {
                        var firstOpt = $('#landlord_select option:not([value=""])').first();
                        $('#landlord_select').val(firstOpt.val()).trigger('change');
                    }
                },
                error: function() {
                    toastr.error('Error loading landlords');
                    $('#landlord_select').empty().append('<option value="">-- Error --</option>');
                }
            });
        });

        // When landlord is selected
        $('#landlord_select').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const landlordId = $(this).val();

            if (landlordId) {
                $('#selected_landlord_name').text(selectedOption.data('name'));
                $('#selected_landlord_mobile').text(selectedOption.data('mobile'));
                $('#selected_landlord_email').text(selectedOption.data('email'));
                $('#selected_landlord_id').text(selectedOption.data('id-number'));
                $('#landlord_id').val(landlordId);
            } else {
                $('#selected_landlord_name').text('-');
                $('#selected_landlord_mobile').text('-');
                $('#selected_landlord_email').text('-');
                $('#selected_landlord_id').text('-');
                $('#landlord_id').val('');
            }
        });

        // Date type toggle
        $('input[name="date_type"]').on('change', function() {
            const dateType = $(this).val();
            if (dateType === 'end') {
                $('#date_from').prop('disabled', true).prop('required', false).val('');
                $('#date_from_required').hide();
            } else {
                $('#date_from').prop('disabled', false).prop('required', true);
                $('#date_from_required').show();
            }
        });

        // Statement search filter
        $('#statement_search').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            $('#statement_tbody tr').each(function() {
                const rowText = $(this).text().toLowerCase();
                $(this).toggle(rowText.indexOf(searchTerm) > -1);
            });
        });

        // View Statement
        $('#search_btn').on('click', function() {
            const mode = $('input[name="search_mode"]:checked').val();
            const landlordId = $('#landlord_id').val();
            const dateType = $('input[name="date_type"]:checked').val();
            const dateFrom = $('#date_from').val();
            const dateTo = $('#date_to').val();

            if (!landlordId) {
                toastr.warning('Please select a landlord');
                return;
            }
            if (dateType === 'range' && !dateFrom) {
                toastr.warning('Please select Date From');
                return;
            }
            if (!dateTo) {
                toastr.warning('Please select Date To');
                return;
            }

            $('#statementModal').modal('show');
            $('#stmt_landlord_name').text($('#selected_landlord_name').text());

            const propertyId = (mode === 'property') ? $('#property_id').val() : '';
            loadStatement(landlordId, dateFrom, dateTo, dateType, propertyId);
        });

        function loadStatement(landlordId, dateFrom, dateTo, dateType, propertyId) {
            $('#statement_tbody').html(`
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted mb-0" style="font-size: 0.875rem;">Loading statement...</p>
                    </td>
                </tr>
            `);

            $.ajax({
                url: '/ajax/landlord/statement/search',
                type: 'POST',
                data: {
                    landlord_id: landlordId,
                    property_id: propertyId,
                    date_from: dateFrom,
                    date_to: dateTo,
                    date_type: dateType,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success && response.statement && response.statement.length > 0) {
                        let html = '';
                        response.statement.forEach(function(entry) {
                            html += `
                                <tr>
                                    <td class="py-1 px-2" style="white-space:nowrap;">${entry.date_formatted}</td>
                                    <td class="py-1 px-2" style="white-space:nowrap;">${entry.reference}</td>
                                    <td class="py-1 px-2" style="word-wrap:break-word;white-space:normal;">${entry.narration}</td>
                                    <td class="py-1 px-2 text-end" style="white-space:nowrap;">${entry.debit_formatted}</td>
                                    <td class="py-1 px-2 text-end" style="white-space:nowrap;">${entry.credit_formatted}</td>
                                    <td class="py-1 px-2 text-end fw-semibold" style="white-space:nowrap;">${entry.balance_formatted}</td>
                                </tr>
                            `;
                        });
                        $('#statement_tbody').html(html);

                        if (response.statement.length > 0) {
                            const lastEntry = response.statement[response.statement.length - 1];
                            $('#stmt_total_amount').text(lastEntry.balance_formatted);
                        }

                        window.statementData = {
                            landlord_id: landlordId,
                            property_id: propertyId,
                            date_from: dateFrom,
                            date_to: dateTo,
                            date_type: dateType
                        };
                    } else {
                        $('#statement_tbody').html(`
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px; opacity: 0.3;">receipt_long</span>
                                    No transactions found for the selected period
                                </td>
                            </tr>
                        `);
                        $('#stmt_total_amount').text('0.00');
                    }
                },
                error: function() {
                    toastr.error('Failed to load statement');
                    $('#statement_tbody').html(`
                        <tr>
                            <td colspan="6" class="text-center py-5 text-danger">
                                <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px; opacity: 0.3;">error</span>
                                Error loading statement
                            </td>
                        </tr>
                    `);
                }
            });
        }

        // Download Excel
        $('#download_excel_btn').on('click', function() {
            if (!window.statementData) {
                toastr.warning('No statement data to export');
                return;
            }
            $('#excel_icon').hide();
            $('#excel_spinner').show();
            $('#download_excel_btn').prop('disabled', true);

            const params = new URLSearchParams(window.statementData);
            window.location.href = '/landlord/account/statement/export/excel?' + params.toString();

            setTimeout(function() {
                $('#excel_spinner').hide();
                $('#excel_icon').show();
                $('#download_excel_btn').prop('disabled', false);
            }, 2000);
        });

        // Download PDF
        $('#download_pdf_btn').on('click', function() {
            if (!window.statementData) {
                toastr.warning('No statement data to export');
                return;
            }
            $('#pdf_icon').hide();
            $('#pdf_spinner').show();
            $('#download_pdf_btn').prop('disabled', true);

            const params = new URLSearchParams(window.statementData);
            window.location.href = '/landlord/account/statement/export/pdf?' + params.toString();

            setTimeout(function() {
                $('#pdf_spinner').hide();
                $('#pdf_icon').show();
                $('#download_pdf_btn').prop('disabled', false);
            }, 2000);
        });
    });
</script>
@endsection