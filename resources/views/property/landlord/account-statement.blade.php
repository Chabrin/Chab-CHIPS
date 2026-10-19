@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/chabrin.css') }}">
<style>
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

    /* ── Statement shown on the page ───────────── */
    .stmt-sheet { background: #fff; border: 1px solid #e3e8ef; border-radius: 8px; padding: 20px 22px; }
    .stmt-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .stmt-name { font-size: 1.2rem; font-weight: 700; color: #0b1b63; letter-spacing: .2px; }
    .stmt-meta { margin-top: 4px; font-size: .82rem; color: #5b6475; }
    .stmt-meta span + span::before { content: '\00b7'; margin: 0 8px; color: #b0b7c3; }
    .stmt-period { font-size: .95rem; font-weight: 600; color: #12b5c0; white-space: nowrap; }
    .stmt-tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
    .stmt-tile { background: #f3f6fb; border: 1px solid #dfe5ef; border-left: 3px solid #12b5c0; border-radius: 6px; padding: 10px 14px; }
    .stmt-tile-label { font-size: .68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #12b5c0; }
    .stmt-tile-value { font-size: 1.15rem; font-weight: 700; color: #0b1b63; margin-top: 2px; font-variant-numeric: tabular-nums; }
    .stmt-tile-closing { background: #0b1b63; border-color: #0b1b63; border-left-color: #12b5c0; }
    .stmt-tile-closing .stmt-tile-value { color: #fff; font-size: 1.35rem; }
    .stmt-tile-note { font-size: .72rem; color: #b9c3e6; margin-top: 1px; }
    .stmt-sheet .dr { color: #a8232c; }
    .stmt-sheet .cr { color: #137a46; }
    .stmt-sheet .neg { color: #a8232c; }
    .stmt-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .84rem; }
    .stmt-table thead th { position: sticky; top: var(--header-height, 54px); z-index: 3; background: #0b1b63; color: #fff; font-size: .7rem; letter-spacing: .6px; text-transform: uppercase; padding: 9px 10px; text-align: left; white-space: nowrap; }
    .stmt-table th.r, .stmt-table td.r { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .stmt-table td { padding: 8px 10px; border-bottom: 1px solid #e9edf4; vertical-align: top; }
    .stmt-table tbody tr:nth-child(even) td { background: #f8fafd; }
    .stmt-table tbody tr:hover td { background: #eef4fb; }
    .stmt-table tbody tr.bf td { background: #e8f7f8; color: #0b1b63; font-weight: 700; border-bottom-color: #b9e3e7; }
    .stmt-table .nowrap { white-space: nowrap; }
    .stmt-table .ref { color: #5b6475; font-size: .78rem; white-space: nowrap; }
    .stmt-table td.empty { text-align: center; color: #8a93a4; padding: 22px; }
    .stmt-table tfoot td { font-weight: 700; padding: 9px 10px; border-bottom: 0; }
    .stmt-table tfoot tr.totals td { background: #eef1f8; border-top: 2px solid #0b1b63; }
    .stmt-table tfoot tr.final td { background: #0b1b63; color: #fff; }
    .stmt-note { margin-top: 12px; font-size: .75rem; color: #6b7486; line-height: 1.5; }
    .stmt-empty { text-align: center; color: #6b7a90; padding: 56px 12px; background: #fff; border: 1px dashed #d5dce8; border-radius: 8px; }
    .stmt-empty .material-symbols-outlined { font-size: 56px; color: #b8c2d3; }
    #statement_area { position: relative; min-height: 180px; }
    #sheet_content.is-loading { opacity: .35; pointer-events: none; }
    #sheet_loading { position: absolute; inset: 0; display: none; align-items: flex-start; justify-content: center; padding-top: 70px; z-index: 5; }
    @media (max-width: 991px) { .stmt-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .stmt-table-wrap { overflow-x: auto; } .stmt-table thead th { position: static; } }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">

                <!-- Selection -->
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Landlord Account Statement</h5>
                    </div>
                    <div class="card-body pt-2">
                        <form id="searchForm" onsubmit="return false;">
                            @if(!empty($preselected))
                            <div class="d-flex align-items-center mb-3" id="picked_summary">
                                <span class="material-symbols-outlined me-2 text-primary">person</span>
                                <div>
                                    <strong>{{ $preselected['names'] }}</strong>
                                    <span class="text-muted">&middot; {{ $preselected['reference_number'] }}</span>
                                </div>
                                <button type="button" class="btn btn-link btn-sm ms-2 p-0" id="change_landlord_btn">Change landlord</button>
                            </div>
                            @endif

                            <div id="picker_block" class="{{ !empty($preselected) ? 'd-none' : '' }}">
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


                            </div>

                            <div class="row mb-1 align-items-end">
                                <div class="col-md-3 mb-2">
                                    <label for="period_preset" class="form-label mb-1">Period</label>
                                    <select class="form-select form-select-sm" id="period_preset">
                                        <option value="3m">Last 3 months</option>
                                        <option value="6m">Last 6 months</option>
                                        <option value="12m">Last 12 months</option>
                                        <option value="year">This year</option>
                                        <option value="custom">Custom range</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label for="date_from" class="form-label mb-1">From</label>
                                    <input type="date" class="form-control form-control-sm" id="date_from" value="{{ $from }}">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label for="date_to" class="form-label mb-1">To</label>
                                    <input type="date" class="form-control form-control-sm" id="date_to" value="{{ $to }}">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="search_btn">
                                        <span class="material-symbols-outlined" style="font-size:18px;">picture_as_pdf</span>
                                        <span class="ms-1">View Statement</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statement, shown on the page -->
                <div id="sheet_toolbar" class="d-none justify-content-end flex-wrap gap-2 mb-2">
                    <a id="open_tab_btn" class="btn btn-outline-primary btn-sm d-flex align-items-center" href="#" target="_blank" rel="noopener">
                        <span class="material-symbols-outlined" style="font-size:17px;">open_in_new</span><span class="ms-1">Open</span>
                    </a>
                    <a id="download_pdf_btn" class="btn btn-sm d-flex align-items-center" href="#" style="background-color:#3598db;color:#fff;">
                        <span class="material-symbols-outlined" style="font-size:17px;">picture_as_pdf</span><span class="ms-1">PDF</span>
                    </a>
                    <a id="download_excel_btn" class="btn btn-success btn-sm d-flex align-items-center" href="#">
                        <span class="material-symbols-outlined" style="font-size:17px;">download</span><span class="ms-1">Excel</span>
                    </a>
                </div>

                <div id="statement_area">
                    <div id="sheet_loading">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                    </div>
                    <div id="sheet_content">
                        <div class="stmt-empty" id="sheet_empty">
                            <span class="material-symbols-outlined">description</span>
                            <p class="mt-2 mb-0">Choose a landlord and period, then View Statement.</p>
                        </div>
                    </div>
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


        // ── Period + statement ───────────────────────────────────────────
        const pdfUrl = '{{ route("landlord.statement.export.pdf") }}';
        const excelUrl = '{{ route("landlord.statement.export.excel") }}';
        const sheetUrl = '{{ route("ajax.landlord.statement.view") }}';
        const pad = n => String(n).padStart(2, '0');
        const ymd = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());

        // Same rule as the server's default period: N months back (day clamped to month end), plus one day
        function monthsBack(n) {
            const today = new Date();
            const t = new Date(today.getFullYear(), today.getMonth() - n, 1);
            t.setDate(Math.min(today.getDate(), new Date(t.getFullYear(), t.getMonth() + 1, 0).getDate()) + 1);
            return t;
        }

        function presetRange(key) {
            const today = new Date();
            if (key === '3m') return [ymd(monthsBack(3)), ymd(today)];
            if (key === '6m') return [ymd(monthsBack(6)), ymd(today)];
            if (key === '12m') return [ymd(monthsBack(12)), ymd(today)];
            if (key === 'year') return [today.getFullYear() + '-01-01', ymd(today)];
            return null;
        }

        function syncPreset() {
            const from = $('#date_from').val(), to = $('#date_to').val();
            const hit = ['3m', '6m', '12m', 'year'].find(k => { const r = presetRange(k); return r[0] === from && r[1] === to; });
            $('#period_preset').val(hit || 'custom');
        }

        $('#period_preset').on('change', function() {
            const r = presetRange($(this).val());
            if (r) {
                $('#date_from').val(r[0]);
                $('#date_to').val(r[1]);
            }
        });
        $('#date_from, #date_to').on('change', syncPreset);
        syncPreset();

        let sheetRequest = null;

        function showStatement() {
            const landlordId = $('#landlord_id').val();
            const from = $('#date_from').val(), to = $('#date_to').val();

            if (!landlordId) {
                toastr.warning('Please select a landlord');
                return;
            }
            if (!from || !to) {
                toastr.warning('Please choose both dates');
                return;
            }
            if (from > to) {
                toastr.warning('The From date must not be after the To date');
                return;
            }

            const qs = 'landlord=' + encodeURIComponent(landlordId) + '&from=' + from + '&to=' + to;
            $('#open_tab_btn').attr('href', pdfUrl + '?' + qs + '&inline=1');
            $('#download_pdf_btn').attr('href', pdfUrl + '?' + qs);
            $('#download_excel_btn').attr('href', excelUrl + '?' + qs);
            $('#sheet_toolbar').removeClass('d-none').addClass('d-flex');

            if (sheetRequest) sheetRequest.abort();
            $('#sheet_content').addClass('is-loading');
            $('#sheet_loading').css('display', 'flex');

            sheetRequest = $.ajax({
                url: sheetUrl,
                method: 'GET',
                dataType: 'html',
                data: { landlord: landlordId, from: from, to: to },
                success: function(html) {
                    $('#sheet_content').html(html);
                },
                error: function(xhr, status) {
                    if (status === 'abort') return;
                    $('#sheet_content').html('<div class="stmt-empty"><span class="material-symbols-outlined">error</span><p class="mt-2 mb-0">The statement could not be loaded.</p></div>');
                    toastr.error('Error loading the statement');
                },
                complete: function(xhr, status) {
                    if (status === 'abort') return;
                    $('#sheet_content').removeClass('is-loading');
                    $('#sheet_loading').hide();
                }
            });

            try { history.replaceState(null, '', '?' + qs); } catch (e) {}
        }

        $('#search_btn').on('click', showStatement);

        $('#change_landlord_btn').on('click', function() {
            $('#picked_summary').removeClass('d-flex').addClass('d-none');
            $('#picker_block').removeClass('d-none');
        });

        // Opened from Client Ledgers or the client page: preselect that landlord and load the statement
        @if(!empty($preselected))
        (function() {
            const l = @json($preselected);
            $('#mode_landlord').prop('checked', true).trigger('change');
            $('#landlord_direct_select').empty().append(
                $('<option>').val(l.encrypted_id)
                    .attr('data-name', l.names || '')
                    .attr('data-mobile', l.mobile_number || 'N/A')
                    .attr('data-email', l.email_address || 'N/A')
                    .attr('data-id-number', l.national_id || 'N/A')
                    .text((l.names || '') + ' - ' + (l.reference_number || ''))
            ).prop('disabled', false).val(l.encrypted_id).trigger('change');
            showStatement();
        })();
        @endif
    });
</script>
@endsection
