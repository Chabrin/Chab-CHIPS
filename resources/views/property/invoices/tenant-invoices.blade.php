@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/chabrin.css') }}">
<style>
    .stmt-page-title { margin: 4px 0 16px; color: #0b1b63; font-size: 1.65rem; font-weight: 700; }
    .stmt-filters { padding: 18px 22px; margin-bottom: 18px; border: 1px solid #dfe5ef; border-radius: 8px; background: #fff; }
    .stmt-filter-block { min-width: 0; }
    .stmt-filter-block legend { float: none; margin-bottom: 9px; font-size: .98rem; font-weight: 700; color: #263b64; }
    .stmt-filter-block label:not(.btn) { display: block; margin-bottom: 5px; font-size: .84rem; font-weight: 600; }
    .stmt-filter-period { border-top: 1px solid #e3e8ef; margin-top: 16px; padding-top: 12px; }
    .stmt-filter-period legend { float: left; }
    .stmt-options { margin-bottom: 12px; }
    .stmt-options .btn { font-size: .83rem; }
    .stmt-options .btn-check:checked + .btn { background: #0b1b63; border-color: #0b1b63; color: #fff; }
    .stmt-options .btn-check:focus-visible + .btn { outline: 2px solid #0b1b63; outline-offset: 2px; }
    .stmt-filters .select2-container .select2-selection--single { height: 40px; border: 1px solid #a8b5c8; border-radius: 6px; }
    .stmt-filters .select2-container .select2-selection__rendered { padding-left: 12px; line-height: 38px; }
    .stmt-filters .select2-container .select2-selection__arrow { height: 38px; }
    .stmt-filters .select2-container .select2-selection:focus-visible { outline: 2px solid #0b1b63; outline-offset: 2px; }
    .stmt-field-hint { margin-top: 5px; font-size: .8rem; color: #5b6475; }
    .stmt-retry { padding: 4px 0 0; font-size: .8rem; }
    .stmt-form-error { margin: 10px 0 0; color: #a8232c; font-size: .85rem; font-weight: 600; }
    .stmt-view-btn { min-height: 40px; }

    .line-item-row {
        border-bottom: 1px solid #e9ecef;
        padding: 10px 0;
    }

    .btn-remove-item {
        cursor: pointer;
    }

    /* ── Statement shown on the page (same look as the landlord statement) ───────────── */
    .stmt-sheet { background: #fff; border: 1px solid #e3e8ef; border-radius: 8px; padding: 20px 22px; }
    .stmt-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .stmt-name { font-size: 1.2rem; font-weight: 700; color: #0b1b63; letter-spacing: .2px; }
    .stmt-meta { margin-top: 4px; font-size: .82rem; color: #5b6475; }
    .stmt-context { display: flex; flex-wrap: wrap; gap: 5px 18px; margin: 5px 0 0; color: #344764; font-size: .85rem; }
    .stmt-context div { display: flex; flex-wrap: wrap; gap: 0 5px; }
    .stmt-context dd { margin: 0; }
    .stmt-period { font-size: .95rem; font-weight: 600; color: #176876; }
    .stmt-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
    .stmt-tile { background: #f3f6fb; border: 1px solid #dfe5ef; border-left: 3px solid #12b5c0; border-radius: 6px; padding: 10px 14px; }
    .stmt-tile-label { font-size: .75rem; font-weight: 700; color: #344764; }
    .stmt-tile-value { font-size: 1.15rem; font-weight: 700; color: #0b1b63; margin-top: 2px; font-variant-numeric: tabular-nums; }
    .stmt-tile-closing { background: #0b1b63; border-color: #0b1b63; border-left-color: #12b5c0; }
    .stmt-tile-closing .stmt-tile-label { color: #d3d9f0; }
    .stmt-tile-closing .stmt-tile-value { color: #fff; font-size: 1.35rem; }
    .stmt-tile-note { font-size: .72rem; color: #b9c3e6; margin-top: 1px; min-height: 1em; }
    .stmt-sheet .dr { color: #a8232c; }
    .stmt-sheet .cr { color: #137a46; }
    .stmt-sheet .neg { color: #a8232c; }
    .stmt-transactions-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px 16px; margin-bottom: 10px; }
    .stmt-transactions-head h3 { margin: 0; color: #0b1b63; font-size: 1rem; font-weight: 700; }
    .stmt-sort-label { margin: 3px 0 0; color: #5b6475; font-size: .8rem; }
    .stmt-search { display: flex; align-items: center; gap: 8px; width: min(100%, 320px); margin: 0; padding: 0 12px; background: #f3f6fb; border: 1px solid #dfe5ef; border-radius: 6px; }
    .stmt-search:focus-within { outline: 2px solid #0b1b63; outline-offset: 2px; }
    .stmt-search .material-symbols-outlined { font-size: 18px; color: #8a93a4; }
    .stmt-search input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; padding: 7px 0; font-size: .84rem; color: #0b1b63; }
    .stmt-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .84rem; }
    .stmt-table thead th { position: sticky; top: var(--header-height, 54px); z-index: 3; background: #0b1b63; color: #fff; font-size: .7rem; letter-spacing: .6px; text-transform: uppercase; padding: 9px 10px; text-align: left; white-space: nowrap; }
    .stmt-table th.r, .stmt-table td.r { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .stmt-table td { padding: 8px 10px; border-bottom: 1px solid #e9edf4; vertical-align: top; }
    .stmt-table tbody tr:nth-child(even) td { background: #f8fafd; }
    .stmt-table tbody tr:hover td { background: #eef4fb; }
    .stmt-table .nowrap { white-space: nowrap; }
    .stmt-table .ref { color: #5b6475; font-size: .78rem; white-space: nowrap; }
    .stmt-table td.empty { text-align: center; color: #8a93a4; padding: 28px; background: #fff !important; }
    .stmt-table tfoot td { font-weight: 700; padding: 9px 10px; border-bottom: 0; }
    .stmt-table tfoot tr.totals td { background: #eef1f8; border-top: 2px solid #0b1b63; }
    .stmt-table tfoot tr.final td { background: #0b1b63; color: #fff; }
    .stmt-empty { text-align: center; color: #6b7a90; padding: 56px 12px; background: #fff; border: 1px dashed #d5dce8; border-radius: 8px; }
    .stmt-empty .material-symbols-outlined { font-size: 56px; color: #b8c2d3; }
    #statement_wrap { scroll-margin-top: calc(var(--header-height, 54px) + 12px); }
    @media (max-width: 767px) {
        .stmt-filters { padding: 14px; }
        .stmt-view-btn { width: 100%; }
        .stmt-sheet { padding: 16px 14px; }
        .stmt-tiles { grid-template-columns: 1fr; }
        .stmt-table-wrap { overflow-x: auto; }
        .stmt-table thead th { position: static; }
    }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <h1 class="stmt-page-title">Tenant Statements</h1>

                <form id="searchForm" class="stmt-filters">
                    <fieldset class="stmt-filter-block">
                        <legend>Find a tenant</legend>
                        <div class="btn-group stmt-options" role="group" aria-label="Find tenant by">
                            <input type="radio" class="btn-check" name="search_mode" id="mode_property" value="property" checked autocomplete="off">
                            <label class="btn btn-outline-primary" for="mode_property">By property</label>
                            <input type="radio" class="btn-check" name="search_mode" id="mode_landlord" value="landlord" autocomplete="off">
                            <label class="btn btn-outline-primary" for="mode_landlord">By landlord</label>
                            <input type="radio" class="btn-check" name="search_mode" id="mode_tenant" value="tenant" autocomplete="off">
                            <label class="btn btn-outline-primary" for="mode_tenant">By tenant</label>
                        </div>

                        <div class="row g-3" data-search-mode="property">
                            <div class="col-md-6">
                                <label for="property_select">Property</label>
                                <select id="property_select"><option></option></select>
                            </div>
                            <div class="col-md-6">
                                <label for="tenant_select">Tenant</label>
                                <select id="tenant_select" disabled><option></option></select>
                                <div class="stmt-field-hint" id="property_tenants_status" role="status">Choose a property first.</div>
                                <button type="button" class="btn btn-link stmt-retry" id="retry_property_tenants" hidden>Retry loading tenants</button>
                            </div>
                        </div>

                        <div class="row g-3" data-search-mode="landlord" hidden>
                            <div class="col-md-4">
                                <label for="landlord_direct_select">Landlord</label>
                                <select id="landlord_direct_select"><option></option></select>
                            </div>
                            <div class="col-md-4">
                                <label for="landlord_property_select">Property</label>
                                <select id="landlord_property_select" disabled><option></option></select>
                                <div class="stmt-field-hint" id="landlord_properties_status" role="status">Choose a landlord first.</div>
                                <button type="button" class="btn btn-link stmt-retry" id="retry_landlord_properties" hidden>Retry loading properties</button>
                            </div>
                            <div class="col-md-4">
                                <label for="landlord_tenant_select">Tenant</label>
                                <select id="landlord_tenant_select" disabled><option></option></select>
                                <div class="stmt-field-hint" id="landlord_tenants_status" role="status">Choose a property first.</div>
                                <button type="button" class="btn btn-link stmt-retry" id="retry_landlord_tenants" hidden>Retry loading tenants</button>
                            </div>
                        </div>

                        <div class="row g-3" data-search-mode="tenant" hidden>
                            <div class="col-md-6">
                                <label for="tenant_direct_select">Tenant</label>
                                <select id="tenant_direct_select"><option></option></select>
                                <div class="stmt-field-hint">Search by name, reference, phone, email, ID or unit code.</div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="stmt-filter-block stmt-filter-period">
                        <legend>Statement period</legend>
                        <div class="btn-group stmt-options" role="group" aria-label="Date filter type">
                            <input type="radio" class="btn-check" name="date_type" id="date_type_range" value="range" checked autocomplete="off">
                            <label class="btn btn-outline-primary" for="date_type_range">Date range</label>
                            <input type="radio" class="btn-check" name="date_type" id="date_type_end" value="end" autocomplete="off">
                            <label class="btn btn-outline-primary" for="date_type_end">Through a date</label>
                        </div>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3" id="preset_field">
                                <label for="period_preset">Period</label>
                                <select class="form-select" id="period_preset">
                                    <option value="3m">Last 3 months</option>
                                    <option value="6m">Last 6 months</option>
                                    <option value="12m">Last 12 months</option>
                                    <option value="year">This year</option>
                                    <option value="custom">Custom range</option>
                                </select>
                            </div>
                            <div class="col-md-3" id="date_from_field">
                                <label for="date_from">From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" required>
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" id="date_to_label">To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" required>
                            </div>
                            <div class="col-md-auto">
                                <button type="submit" class="btn btn-primary stmt-view-btn" id="search_btn">View statement</button>
                            </div>
                        </div>
                        <p class="stmt-form-error" id="stmt_form_error" role="alert" hidden></p>
                    </fieldset>
                </form>

                <!-- Statement, shown on the page -->
                <div id="statement_wrap">
                    <div class="stmt-empty" id="sheet_empty">
                        <span class="material-symbols-outlined" aria-hidden="true">description</span>
                        <p class="mt-2 mb-0">Choose a tenant and period to view their statement.</p>
                    </div>

                    <section class="stmt-sheet d-none" id="sheet_content" aria-labelledby="stmt_tenant_name">
                        <div class="stmt-head">
                            <div>
                                <h2 class="stmt-name mb-0" id="stmt_tenant_name" tabindex="-1"></h2>
                                <dl class="stmt-context">
                                    <div><dt>Property</dt><dd id="stmt_property_name"></dd></div>
                                    <div id="stmt_units_context" hidden><dt id="stmt_units_label">Unit</dt><dd id="stmt_units"></dd></div>
                                    <div><dt>Period</dt><dd class="stmt-period" id="stmt_period"></dd></div>
                                </dl>
                                <div class="stmt-meta" id="stmt_meta"></div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="download_pdf_btn" disabled>
                                    <span class="material-symbols-outlined align-middle" aria-hidden="true">picture_as_pdf</span>
                                    Download PDF
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="download_excel_btn" disabled>
                                    <span class="material-symbols-outlined align-middle" aria-hidden="true">download</span>
                                    Download Excel
                                </button>
                            </div>
                        </div>

                        <div class="stmt-tiles">
                            <div class="stmt-tile">
                                <div class="stmt-tile-label">Total debits</div>
                                <div class="stmt-tile-value dr" id="stmt_total_debit">&mdash;</div>
                            </div>
                            <div class="stmt-tile">
                                <div class="stmt-tile-label">Total credits</div>
                                <div class="stmt-tile-value cr" id="stmt_total_credit">&mdash;</div>
                            </div>
                            <div class="stmt-tile stmt-tile-closing">
                                <div class="stmt-tile-label">Closing balance</div>
                                <div class="stmt-tile-value" id="stmt_total_amount">&mdash;</div>
                                <div class="stmt-tile-note" id="stmt_closing_note"></div>
                            </div>
                        </div>

                        <div class="stmt-transactions-head">
                            <div>
                                <h3 id="stmt_transactions_title">Transactions</h3>
                                <p class="stmt-sort-label">Newest first</p>
                            </div>
                            <label class="stmt-search" for="statement_search">
                                <span class="material-symbols-outlined" aria-hidden="true">search</span>
                                <span class="visually-hidden">Search transactions</span>
                                <input type="search" id="statement_search" placeholder="Search transactions" autocomplete="off" disabled>
                            </label>
                        </div>

                        <div class="stmt-table-wrap" role="region" aria-labelledby="stmt_transactions_title" tabindex="0">
                            <table class="stmt-table" id="statement_table" aria-labelledby="stmt_transactions_title">
                                <thead>
                                    <tr>
                                        <th scope="col" aria-sort="descending">Date</th>
                                        <th scope="col">Reference</th>
                                        <th scope="col">Narration</th>
                                        <th scope="col" class="r">Debit</th>
                                        <th scope="col" class="r">Credit</th>
                                        <th scope="col" class="r">Balance</th>
                                    </tr>
                                </thead>
                                <tbody id="statement_tbody"></tbody>
                                <tfoot id="statement_tfoot"></tfoot>
                            </table>
                        </div>
                        <p id="stmt_status" class="visually-hidden" role="status"></p>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Statement Modal -->
<div class="modal fade" id="createStatementModal" tabindex="-1" aria-labelledby="createStatementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createStatementModalLabel">Create Tenant Statement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createStatementForm">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="create_tenant_search" class="form-label">Tenant <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="create_tenant_search" placeholder="Search tenant..." required>
                            <input type="hidden" id="create_tenant_id" name="tenant_id" required>
                            <div id="create_tenant_search_results" class="list-group" style="position: absolute; z-index: 1050; display: none; max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="create_lease_id" class="form-label">Lease <span class="text-danger">*</span></label>
                            <select class="form-select" id="create_lease_id" name="lease_id" required disabled>
                                <option value="">Select tenant first</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="create_date_due" class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="create_date_due" name="date_due" required>
                        </div>
                        <div class="col-md-6">
                            <label for="create_narration" class="form-label">Narration <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="create_narration" name="narration" placeholder="Statement description" required>
                        </div>
                    </div>

                    <hr>
                    <h6 class="mb-3">Line Items</h6>
                    <div id="line_items_container">
                        <div class="line-item-row row mb-2">
                            <div class="col-md-5">
                                <input type="text" class="form-control" name="line_items[0][item_id]" placeholder="Item Description" required>
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control line-item-qty" name="line_items[0][quantity]" placeholder="Qty" value="1" min="1" required>
                            </div>
                            <div class="col-md-3">
                                <input type="number" class="form-control line-item-amount" name="line_items[0][amount]" placeholder="Amount" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-2 text-end">
                                <span class="line-item-total">0.00</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" id="add_line_item">
                        <span class="material-symbols-outlined">add</span> Add Item
                    </button>

                    <div class="mt-3 text-end">
                        <h5>Total: <span id="statement_total">0.00</span></h5>
                    </div>

                    <input type="hidden" id="create_property_id" name="property_id">
                    <input type="hidden" id="create_unit_id" name="unit_id">
                    <input type="hidden" id="create_client_id" name="client_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="create_statement_btn">
                        <span class="spinner-border spinner-border-sm" role="status" id="create_spinner" style="display: none;"></span>
                        Create Statement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        let lineItemIndex = 1;

        const modes = {
            property: { property: '#property_select', tenant: '#tenant_select' },
            landlord: { landlord: '#landlord_direct_select', property: '#landlord_property_select', tenant: '#landlord_tenant_select' },
            tenant: { tenant: '#tenant_direct_select' }
        };

        const propertyOption = property => ({
            id: property.id,
            name: property.property_name,
            text: [property.property_name, property.reference_number].filter(Boolean).join(' \u00b7 ')
        });

        const tenantOption = tenant => ({
            ...tenant,
            id: tenant.encrypted_id,
            text: [tenant.names, (tenant.unit_labels?.length ? tenant.unit_labels : tenant.unit_codes)?.join(', '),
                tenant.mobile_number, tenant.reference_number]
                .filter(Boolean).join(' \u00b7 ')
        });

        function initializePicker(selector, options) {
            const picker = $(selector).select2({ width: '100%', allowClear: true, ...options });
            const label = $('label[for="' + picker.attr('id') + '"]');
            const labelId = picker.attr('id') + '_label';
            label.attr('id', labelId);
            const selection = picker.next('.select2').find('.select2-selection');
            selection.attr('aria-labelledby', labelId + ' ' + selection.attr('aria-labelledby'));
        }

        function searchablePicker(selector, placeholder, url, responseKey, option) {
            initializePicker(selector, {
                placeholder,
                minimumInputLength: 2,
                ajax: {
                    url,
                    delay: 250,
                    dataType: 'json',
                    data: params => ({ search: params.term.trim() }),
                    processResults: response => ({ results: (response[responseKey] || []).map(option) })
                },
                language: {
                    inputTooShort: () => 'Enter at least 2 characters.',
                    errorLoading: () => 'Search is unavailable. Try again.'
                }
            });
        }

        searchablePicker('#property_select', 'Search by property name or reference', '/ajax/statements/properties', 'properties', propertyOption);
        searchablePicker('#landlord_direct_select', 'Search for a landlord', '/ajax/landlord/search-landlords', 'landlords', landlord => ({
            id: landlord.encrypted_id,
            text: [landlord.names, landlord.reference_number].filter(Boolean).join(' \u00b7 ')
        }));
        searchablePicker('#tenant_direct_select', 'Search for a tenant', '/ajax/statements/search-tenants', 'tenants', tenantOption);

        function dependentPicker({ parent, field, status, retry, url, param, responseKey, option, noun, prerequisite, selectSingle }) {
            const picker = $(field);
            const statusMessage = $(status);
            const retryButton = $(retry);
            let pendingRequest;

            initializePicker(field, {
                placeholder: 'Choose ' + noun,
                minimumResultsForSearch: 0,
                matcher: (params, result) => {
                    const record = result.element && $(result.element).data('statementOption');
                    const searchText = [result.text, ...(record?.unit_codes || [])].join(' ').toLowerCase();
                    return searchText.includes((params.term || '').trim().toLowerCase()) ? result : null;
                }
            });

            function load() {
                if (pendingRequest) pendingRequest.abort();

                const parentId = $(parent).val();
                picker.empty().append(new Option()).prop('disabled', true).trigger('change');
                retryButton.prop('hidden', true);
                statusMessage.text(parentId ? 'Loading ' + responseKey + '\u2026' : prerequisite);
                if (!parentId) return;

                pendingRequest = $.getJSON(url, { [param]: parentId }).done(response => {
                    const results = (response[responseKey] || []).map(option);
                    for (const result of results) {
                        const item = new Option(result.text, result.id);
                        $(item).data('statementOption', result);
                        picker.append(item);
                    }
                    picker.prop('disabled', results.length === 0);
                    statusMessage.text(results.length ? '' : 'No ' + responseKey + ' found.');
                    if (selectSingle && results.length === 1) {
                        picker.val(results[0].id).trigger('change');
                    }
                }).fail((xhr, statusText) => {
                    if (statusText === 'abort') return;
                    statusMessage.text('Could not load ' + responseKey + '.');
                    retryButton.prop('hidden', false);
                });
            }

            $(parent).on('change', load);
            retryButton.on('click', load);
        }

        const tenantsForProperty = {
            url: '/ajax/statements/tenants-by-property',
            param: 'property_id',
            responseKey: 'tenants',
            option: tenantOption,
            noun: 'a tenant',
            prerequisite: 'Choose a property first.'
        };
        dependentPicker({
            ...tenantsForProperty,
            parent: '#property_select', field: '#tenant_select',
            status: '#property_tenants_status', retry: '#retry_property_tenants'
        });
        dependentPicker({
            parent: '#landlord_direct_select', field: '#landlord_property_select',
            status: '#landlord_properties_status', retry: '#retry_landlord_properties',
            url: '/ajax/statements/properties-by-landlord', param: 'landlord_id', responseKey: 'properties',
            option: propertyOption, noun: 'a property', prerequisite: 'Choose a landlord first.', selectSingle: true
        });
        dependentPicker({
            ...tenantsForProperty,
            parent: '#landlord_property_select', field: '#landlord_tenant_select',
            status: '#landlord_tenants_status', retry: '#retry_landlord_tenants'
        });

        $('input[name="search_mode"]').on('change', function() {
            $('[data-search-mode]').prop('hidden', true).filter('[data-search-mode="' + this.value + '"]').prop('hidden', false);
        });

        function selectedOption(selector) {
            const selection = $(selector).select2('data')[0];
            return selection?.id ? ($(selection.element).data('statementOption') || selection) : null;
        }

        // Placeholder for create modal tenant search (if modal still exists)
        $(document).on('click', '.tenant-select', function(e) {
            e.preventDefault();
            const tenantId = $(this).data('tenant-id');
            const tenantName = $(this).data('tenant-name');
            const resultsContainer = $(this).data('results-container');
            const idField = $(this).data('id-field');
            const displayField = $(this).data('display-field');

            $(idField).val(tenantId);
            $(displayField).val(tenantName);
            $(resultsContainer).hide().empty();

            // If in create modal, load leases
            if (idField === '#create_tenant_id') {
                loadLeases(tenantId);
            }
        });

        // Load leases for tenant
        function loadLeases(tenantId) {
            $('#create_lease_id').prop('disabled', true).html('<option value="">Loading...</option>');

            $.ajax({
                url: `/ajax/invoices/tenant/${tenantId}/leases`,
                type: 'GET',
                success: function(response) {
                    if (response.success && response.leases.length > 0) {
                        let html = '<option value="">Select lease</option>';
                        response.leases.forEach(function(lease) {
                            html += `<option value="${lease.id}" 
                                        data-property-id="${lease.property_id}" 
                                        data-unit-id="${lease.unit_id}">
                                        ${lease.ref_number} - ${lease.property_name} - ${lease.unit_name}
                                    </option>`;
                        });
                        $('#create_lease_id').html(html).prop('disabled', false);
                    } else {
                        $('#create_lease_id').html('<option value="">No leases found</option>');
                        toastr.warning('No leases found for this tenant');
                    }
                },
                error: function() {
                    $('#create_lease_id').html('<option value="">Error loading leases</option>');
                    toastr.error('Failed to load leases');
                }
            });
        }

        // Set property and unit when lease is selected
        $('#create_lease_id').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            $('#create_property_id').val(selectedOption.data('property-id'));
            $('#create_unit_id').val(selectedOption.data('unit-id'));
        });

        // Add line item
        $('#add_line_item').on('click', function() {
            const html = `
                <div class="line-item-row row mb-2">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="line_items[${lineItemIndex}][item_id]" placeholder="Item Description" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control line-item-qty" name="line_items[${lineItemIndex}][quantity]" placeholder="Qty" value="1" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <input type="number" class="form-control line-item-amount" name="line_items[${lineItemIndex}][amount]" placeholder="Amount" step="0.01" min="0" required>
                    </div>
                    <div class="col-md-2 text-end">
                        <span class="line-item-total">0.00</span>
                        <span class="material-symbols-outlined btn-remove-item text-danger" style="cursor: pointer;">delete</span>
                    </div>
                </div>
            `;
            $('#line_items_container').append(html);
            lineItemIndex++;
        });

        // Remove line item
        $(document).on('click', '.btn-remove-item', function() {
            $(this).closest('.line-item-row').remove();
            calculateTotal();
        });

        // Calculate line item total and statement total
        $(document).on('input', '.line-item-qty, .line-item-amount', function() {
            const row = $(this).closest('.line-item-row');
            const qty = parseFloat(row.find('.line-item-qty').val()) || 0;
            const amount = parseFloat(row.find('.line-item-amount').val()) || 0;
            const total = qty * amount;
            row.find('.line-item-total').text(total.toFixed(2));
            calculateTotal();
        });

        function calculateTotal() {
            let total = 0;
            $('.line-item-total').each(function() {
                total += parseFloat($(this).text()) || 0;
            });
            $('#statement_total').text(total.toFixed(2));
        }

        // ── Period presets (same options as the landlord statement); Date From / Date To start on the last 3 months ──
        const pad = n => String(n).padStart(2, '0');
        const toIso = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());

        // N months back (day clamped to the month end), plus one day
        function monthsBack(n) {
            const today = new Date();
            const t = new Date(today.getFullYear(), today.getMonth() - n, 1);
            t.setDate(Math.min(today.getDate(), new Date(t.getFullYear(), t.getMonth() + 1, 0).getDate()) + 1);
            return t;
        }

        function presetRange(key) {
            const today = new Date();
            if (key === '3m') return [toIso(monthsBack(3)), toIso(today)];
            if (key === '6m') return [toIso(monthsBack(6)), toIso(today)];
            if (key === '12m') return [toIso(monthsBack(12)), toIso(today)];
            if (key === 'year') return [today.getFullYear() + '-01-01', toIso(today)];
            return null;
        }

        // Show the preset the two dates match, or Custom range when they match none
        function syncPreset() {
            if ($('input[name="date_type"]:checked').val() === 'end') return;
            const from = $('#date_from').val(), to = $('#date_to').val();
            const hit = ['3m', '6m', '12m', 'year'].find(k => { const r = presetRange(k); return r[0] === from && r[1] === to; });
            $('#period_preset').val(hit || 'custom');
        }

        function applyPreset(key) {
            const r = presetRange(key);
            if (r) {
                $('#date_from').val(r[0]);
                $('#date_to').val(r[1]);
            }
        }

        $('#period_preset').on('change', function() {
            applyPreset($(this).val());
        });
        $('#date_from, #date_to').on('change', syncPreset);

        applyPreset('3m');
        syncPreset();

        $('input[name="date_type"]').on('change', function() {
            const endOnly = this.value === 'end';
            $('#preset_field, #date_from_field').prop('hidden', endOnly);
            $('#period_preset, #date_from').prop('disabled', endOnly);
            $('#date_to_label').text(endOnly ? 'Through' : 'To');
            syncPreset();
        });

        $('#statement_search').on('input', function() {
            const searchTerm = this.value.trim().toLowerCase();
            let found = false;
            $('#statement_tbody .stmt-transaction').each(function() {
                const matches = this.textContent.toLowerCase().includes(searchTerm);
                $(this).toggle(matches);
                found ||= matches;
            });
            $('#stmt_no_match').toggleClass('d-none', found);
        });

        $('#searchForm').on('change', () => $('#stmt_form_error').prop('hidden', true));
        $('#searchForm').on('submit', function(event) {
            event.preventDefault();

            const mode = modes[$('input[name="search_mode"]:checked').val()];
            const property = mode.property ? selectedOption(mode.property) : null;
            const tenant = selectedOption(mode.tenant);
            const dateType = $('input[name="date_type"]:checked').val();
            const dateFrom = dateType === 'end' ? '' : $('#date_from').val();
            const dateTo = $('#date_to').val();

            if (!tenant) {
                let message = 'Choose a tenant.';
                if (mode.property && !property) message = 'Choose a property, then a tenant.';
                if (mode.landlord && !$(mode.landlord).val()) message = 'Choose a landlord, then a property and tenant.';
                $('#stmt_form_error').text(message).prop('hidden', false);
                return;
            }

            if (dateFrom && dateFrom > dateTo) {
                $('#stmt_form_error').text('The end date must be on or after the start date.').prop('hidden', false);
                $('#date_to').trigger('focus');
                return;
            }

            const periodText = dateType === 'end' ? 'Up to ' + stmtDay(dateTo) : stmtDay(dateFrom) + ' \u2013 ' + stmtDay(dateTo);
            $('#stmt_form_error').prop('hidden', true);
            $('#sheet_empty').addClass('d-none');
            $('#sheet_content').removeClass('d-none');
            fillStatementHead(tenant, property, periodText);
            loadStatement({
                tenant_id: tenant.id,
                property_id: property?.id ?? '',
                date_from: dateFrom,
                date_to: dateTo,
                date_type: dateType
            });

            document.getElementById('stmt_tenant_name').focus({ preventScroll: true });
            document.getElementById('statement_wrap').scrollIntoView({ block: 'start' });
        });

        // The statement sheet: same look as the landlord statement (.stmt-* rules above)
        const stmtEsc = s => $('<div>').text(s == null ? '' : String(s)).html();
        const stmtMoney = v => Number(v || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const stmtDay = ymd => {
            const p = String(ymd).split('-');
            return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'});
        };

        // Fill the header block and blank the tiles while the rows load
        function fillStatementHead(tenant, property, periodText) {
            const units = property ? (tenant.unit_labels || []) : [];
            $('#stmt_tenant_name').text(tenant.names);
            $('#stmt_property_name').text(property?.name || 'All properties');
            $('#stmt_units_context').prop('hidden', units.length === 0);
            $('#stmt_units_label').text(units.length === 1 ? 'Unit on record' : 'Units on record');
            $('#stmt_units').text(units.join(', '));
            $('#stmt_period').text(periodText);

            const contact = [tenant.mobile_number, tenant.email_address, tenant.national_id && 'ID no. ' + tenant.national_id]
                .filter(value => value && value !== 'N/A');
            $('#stmt_meta').text(contact.join(' \u00b7 '));

            $('#stmt_total_debit, #stmt_total_credit, #stmt_total_amount').text('\u2014');
            $('#stmt_closing_note').text('');
            $('#statement_tfoot').empty();
            $('#statement_search').val('').prop('disabled', true);
        }

        function statementMessageRow(icon, text, attributes = '') {
            return `
                <tr ${attributes}>
                    <td colspan="6" class="empty">
                        <span class="material-symbols-outlined d-block mb-1" aria-hidden="true" style="font-size: 40px; opacity: 0.35;">${icon}</span>
                        ${text}
                    </td>
                </tr>
            `;
        }

        let statementRequest;
        let statementData;
        const exportButtons = $('#download_pdf_btn, #download_excel_btn');

        function loadStatement(filters) {
            if (statementRequest) statementRequest.abort();
            statementData = null;
            exportButtons.prop('disabled', true);
            $('#stmt_status').text('Loading statement.');

            function showStatementError() {
                const message = 'Could not load the statement. Select View statement to try again.';
                $('#statement_tbody').html(statementMessageRow('error', message));
                $('#stmt_status').text(message);
            }

            $('#statement_tbody').html(`
                <tr>
                    <td colspan="6" class="empty">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 mb-0">Loading statement...</p>
                    </td>
                </tr>
            `);

            statementRequest = $.ajax({
                url: '/ajax/invoices/tenant/search',
                type: 'POST',
                dataType: 'json',
                data: {
                    ...filters,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (!response.success || !Array.isArray(response.statement)) {
                        showStatementError();
                        return;
                    }
                    statementData = filters;
                    exportButtons.prop('disabled', false);
                    const summary = response.summary;
                    if (response.statement.length > 0) {
                        let html = '';
                        let undatedHtml = '';

                        // Reverse the display while keeping the running balances in the original entries.
                        response.statement.slice().reverse().forEach(function(entry) {
                            const row = `
                                <tr class="stmt-transaction">
                                    <td class="nowrap">${stmtEsc(entry.date_formatted)}</td>
                                    <td class="ref">${entry.reference === '-' ? '' : stmtEsc(entry.reference)}</td>
                                    <td>${stmtEsc(entry.narration)}</td>
                                    <td class="r dr">${entry.debit_formatted === '-' ? '' : stmtEsc(entry.debit_formatted)}</td>
                                    <td class="r cr">${entry.credit_formatted === '-' ? '' : stmtEsc(entry.credit_formatted)}</td>
                                    <td class="r ${entry.balance < 0 ? 'neg' : ''}">${stmtEsc(entry.balance_formatted)}</td>
                                </tr>
                            `;
                            if (entry.date) {
                                html += row;
                            } else {
                                undatedHtml += row;
                            }
                        });

                        const noMatches = statementMessageRow('search', 'No transactions match your search.', 'id="stmt_no_match" class="d-none"');
                        $('#statement_tbody').html(html + undatedHtml + noMatches);
                        $('#statement_search').prop('disabled', false);
                        $('#stmt_status').text('Statement loaded.');
                    } else {
                        $('#statement_tbody').html(statementMessageRow('receipt_long', 'No transactions found for the selected period'));
                        $('#stmt_status').text('No transactions found for the selected period.');
                    }

                    const closingNote = summary.closing_balance < 0 ? 'Owed by tenant' : (summary.closing_balance > 0 ? 'In credit' : 'Nil balance');
                    $('#stmt_total_debit').text(stmtMoney(summary.total_debit));
                    $('#stmt_total_credit').text(stmtMoney(summary.total_credit));
                    $('#stmt_total_amount').text(stmtMoney(summary.closing_balance));
                    $('#stmt_closing_note').text(closingNote);

                    const openingRow = summary.opening_balance === null ? '' : `
                        <tr class="totals">
                            <td colspan="5">Opening balance</td>
                            <td class="r">${stmtMoney(summary.opening_balance)}</td>
                        </tr>
                    `;
                    $('#statement_tfoot').html(`${openingRow}
                        <tr class="totals">
                            <td colspan="3">Period totals</td>
                            <td class="r dr">${stmtMoney(summary.total_debit)}</td>
                            <td class="r cr">${stmtMoney(summary.total_credit)}</td>
                            <td class="r"></td>
                        </tr>
                        <tr class="final">
                            <td colspan="5">Closing balance &mdash; ${closingNote}</td>
                            <td class="r">${stmtMoney(summary.closing_balance)}</td>
                        </tr>
                    `);
                },
                error: function(xhr, status) {
                    if (status === 'abort') return;
                    showStatementError();
                }
            });
        }

        exportButtons.on('click', function() {
            if (!statementData) return;
            const format = this.id === 'download_pdf_btn' ? 'pdf' : 'excel';
            const params = new URLSearchParams(statementData);
            window.location.href = '/invoices/tenant/statement/export/' + format + '?' + params.toString();
        });

        // Placeholder for create modal tenant search (if modal still exists)
        $('#createStatementForm').on('submit', function(e) {
            e.preventDefault();

            const formData = {
                tenant_id: $('#create_tenant_id').val(),
                lease_id: $('#create_lease_id').val(),
                property_id: $('#create_property_id').val(),
                unit_id: $('#create_unit_id').val(),
                client_id: $('#create_client_id').val(),
                date_due: $('#create_date_due').val(),
                narration: $('#create_narration').val(),
                line_items: [],
                _token: '{{ csrf_token() }}'
            };

            // Collect line items
            $('.line-item-row').each(function() {
                const row = $(this);
                formData.line_items.push({
                    item_id: row.find('[name*="[item_id]"]').val(),
                    quantity: row.find('[name*="[quantity]"]').val(),
                    amount: row.find('[name*="[amount]"]').val()
                });
            });

            $('#create_spinner').show();
            $('#create_statement_btn').prop('disabled', true);

            $.ajax({
                url: '{{ route("ajax.invoices.tenant.store") }}',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        $('#createStatementModal').modal('hide');
                        $('#createStatementForm')[0].reset();
                        $('#create_tenant_id').val('');
                        $('#create_lease_id').prop('disabled', true).html('<option value="">Select tenant first</option>');
                        $('#line_items_container').html(`
                            <div class="line-item-row row mb-2">
                                <div class="col-md-5">
                                    <input type="text" class="form-control" name="line_items[0][item_id]" placeholder="Item Description" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control line-item-qty" name="line_items[0][quantity]" placeholder="Qty" value="1" min="1" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" class="form-control line-item-amount" name="line_items[0][amount]" placeholder="Amount" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-2 text-end">
                                    <span class="line-item-total">0.00</span>
                                </div>
                            </div>
                        `);
                        lineItemIndex = 1;
                        calculateTotal();
                    } else {
                        toastr.error(response.message || 'Failed to create statement');
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422 && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        let errorMsg = 'Validation errors:\n';
                        Object.keys(errors).forEach(key => {
                            errorMsg += errors[key][0] + '\n';
                        });
                        toastr.error(errorMsg);
                    } else {
                        toastr.error('Failed to create statement');
                    }
                },
                complete: function() {
                    $('#create_spinner').hide();
                    $('#create_statement_btn').prop('disabled', false);
                }
            });
        });

        // Hide search results when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.form-control, .list-group').length) {
                $('.list-group').hide();
            }
        });
    });
</script>
@endsection
