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

    .results-table {
        margin-top: 20px;
    }

    /* Custom styling for date type radio buttons */
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

    .line-item-row {
        border-bottom: 1px solid #e9ecef;
        padding: 10px 0;
    }

    .btn-remove-item {
        cursor: pointer;
    }

    #tenant_select option {
        font-size: 0.875rem;
    }

    .sticky-top {
        position: sticky;
        top: 0;
        z-index: 10;
        background: white;
    }

    /* ── Statement shown on the page (same look as the landlord statement) ───────────── */
    .stmt-sheet { background: #fff; border: 1px solid #e3e8ef; border-radius: 8px; padding: 20px 22px; }
    .stmt-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .stmt-name { font-size: 1.2rem; font-weight: 700; color: #0b1b63; letter-spacing: .2px; }
    .stmt-meta { margin-top: 4px; font-size: .82rem; color: #5b6475; }
    .stmt-meta span + span::before { content: '\00b7'; margin: 0 8px; color: #b0b7c3; }
    .stmt-period { font-size: .95rem; font-weight: 600; color: #12b5c0; white-space: nowrap; }
    .stmt-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
    .stmt-tile { background: #f3f6fb; border: 1px solid #dfe5ef; border-left: 3px solid #12b5c0; border-radius: 6px; padding: 10px 14px; }
    .stmt-tile-label { font-size: .68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #12b5c0; }
    .stmt-tile-value { font-size: 1.15rem; font-weight: 700; color: #0b1b63; margin-top: 2px; font-variant-numeric: tabular-nums; }
    .stmt-tile-closing { background: #0b1b63; border-color: #0b1b63; border-left-color: #12b5c0; }
    .stmt-tile-closing .stmt-tile-value { color: #fff; font-size: 1.35rem; }
    .stmt-tile-note { font-size: .72rem; color: #b9c3e6; margin-top: 1px; min-height: 1em; }
    .stmt-sheet .dr { color: #a8232c; }
    .stmt-sheet .cr { color: #137a46; }
    .stmt-sheet .neg { color: #a8232c; }
    .stmt-search { display: flex; align-items: center; gap: 8px; max-width: 340px; margin: 0 0 10px auto; padding: 0 12px; background: #f3f6fb; border: 1px solid #dfe5ef; border-radius: 6px; }
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
        .stmt-sheet { padding: 16px 14px; }
        .stmt-tiles { grid-template-columns: 1fr; }
        .stmt-search { max-width: none; }
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
                <!-- Search Card -->
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
                        <h5 class="mb-0">Tenant Statements</h5>
                    </div>
                    <div class="card-body">
                        <!-- Search Form -->
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
                                        <input type="radio" class="btn-check" name="search_mode" id="mode_tenant" value="tenant" autocomplete="off">
                                        <label class="btn btn-outline-primary date-filter-radio" for="mode_tenant">
                                            <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">group</span>
                                            Tenant
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
                                        <label for="tenant_select" class="form-label mb-1">Select Tenant <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="tenant_select" name="tenant_id" disabled required style="width:100%;">
                                            <option value="">-- Select Property First --</option>
                                        </select>
                                        <input type="hidden" id="tenant_id" name="tenant_id">
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
                                        <label for="landlord_direct_select" class="form-label mb-1">Select Landlord</label>
                                        <select class="form-select form-select-sm" id="landlord_direct_select" disabled>
                                            <option value="">-- Search First --</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <label for="landlord_property_select" class="form-label mb-1">Select Property</label>
                                        <select class="form-select form-select-sm" id="landlord_property_select" disabled>
                                            <option value="">-- Select Landlord First --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="landlord_tenant_select" class="form-label mb-1">Select Tenant <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="landlord_tenant_select" disabled style="width:100%;">
                                            <option value="">-- Select Property First --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- MODE: Search by Tenant -->
                            <div id="tenant_mode_section" style="display:none;">
                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <label for="tenant_search_input" class="form-label mb-1">Search Tenant</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-sm" id="tenant_search_input"
                                                placeholder="-- Search --">
                                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="search_tenant_btn">
                                                <span class="material-symbols-outlined" id="search_tenant_icon" style="font-size:18px;">search</span>
                                                <span class="spinner-border spinner-border-sm" role="status" id="search_tenant_spinner" style="display:none;"></span>
                                            </button>
                                        </div>
                                        <small class="text-muted">Search by email, ID, phone, reference or room code</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="tenant_direct_select" class="form-label mb-1">Select Tenant <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="tenant_direct_select" disabled>
                                            <option value="">-- Search First --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Tenant Details (shared) -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <small class="text-muted">
                                        <strong>Name:</strong> <span id="selected_tenant_name">-</span> |
                                        <strong>Phone:</strong> <span id="selected_tenant_mobile">-</span> |
                                        <strong>Email:</strong> <span id="selected_tenant_email">-</span> |
                                        <strong>ID Number:</strong> <span id="selected_tenant_id">-</span>
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
                                <div class="col-md-3 mb-2">
                                    <label for="period_preset" class="form-label">Period</label>
                                    <select class="form-select" id="period_preset">
                                        <option value="3m">Last 3 months</option>
                                        <option value="6m">Last 6 months</option>
                                        <option value="12m">Last 12 months</option>
                                        <option value="year">This year</option>
                                        <option value="custom">Custom range</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label for="date_from" class="form-label">Date From <span class="text-danger" id="date_from_required">*</span></label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" required>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label for="date_to" class="form-label">Date To <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_to" name="date_to" required>
                                </div>
                                <div class="col-md-3 mb-2 d-flex align-items-end gap-2">
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

                <!-- Statement, shown on the page -->
                <div id="statement_wrap">
                    <div id="sheet_toolbar" class="d-none justify-content-end flex-wrap gap-2 mb-2">
                        <button type="button" class="btn btn-sm d-flex align-items-center" id="download_pdf_btn" style="background-color:#3598db;color:#fff;">
                            <span class="material-symbols-outlined" id="pdf_icon" style="font-size:17px;">picture_as_pdf</span>
                            <span class="spinner-border spinner-border-sm" id="pdf_spinner" role="status" style="display: none; width: 16px; height: 16px;"></span>
                            <span class="ms-1">PDF</span>
                        </button>
                        <button type="button" class="btn btn-success btn-sm d-flex align-items-center" id="download_excel_btn">
                            <span class="material-symbols-outlined" id="excel_icon" style="font-size:17px;">download</span>
                            <span class="spinner-border spinner-border-sm" id="excel_spinner" role="status" style="display: none; width: 16px; height: 16px;"></span>
                            <span class="ms-1">Excel</span>
                        </button>
                    </div>

                    <div class="stmt-empty" id="sheet_empty">
                        <span class="material-symbols-outlined">description</span>
                        <p class="mt-2 mb-0">Choose a tenant and period, then View Statement.</p>
                    </div>

                    <div class="stmt-sheet d-none" id="sheet_content">
                        <div class="stmt-head">
                            <div>
                                <div class="stmt-name" id="stmt_tenant_name"></div>
                                <div class="stmt-meta" id="stmt_meta"></div>
                            </div>
                            <div class="stmt-period" id="stmt_period"></div>
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

                        <div class="stmt-search">
                            <span class="material-symbols-outlined">search</span>
                            <input type="text" id="statement_search" placeholder="Search transactions..." autocomplete="off">
                        </div>

                        <div class="stmt-table-wrap">
                            <table class="stmt-table" id="statement_table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Reference</th>
                                        <th>Narration</th>
                                        <th class="r">Debit</th>
                                        <th class="r">Credit</th>
                                        <th class="r">Balance</th>
                                    </tr>
                                </thead>
                                <tbody id="statement_tbody"></tbody>
                                <tfoot id="statement_tfoot"></tfoot>
                            </table>
                        </div>
                    </div>
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

        // Helper to clear tenant details
        function clearTenantDetails() {
            $('#selected_tenant_name').text('-');
            $('#selected_tenant_mobile').text('-');
            $('#selected_tenant_email').text('-');
            $('#selected_tenant_id').text('-');
            $('#tenant_id').val('');
        }

        // Helper to set tenant details from a selected option
        function setTenantDetails(opt) {
            $('#selected_tenant_name').text(opt.data('name'));
            $('#selected_tenant_mobile').text(opt.data('mobile'));
            $('#selected_tenant_email').text(opt.data('email'));
            $('#selected_tenant_id').text(opt.data('id-number'));
            $('#tenant_id').val(opt.val());
        }

        // ─── Mode toggle ───────────────────────────────────
        $('input[name="search_mode"]').on('change', function() {
            var mode = $(this).val();
            clearTenantDetails();
            $('#property_mode_section, #landlord_mode_section, #tenant_mode_section').hide();
            if (mode === 'property') $('#property_mode_section').show();
            if (mode === 'landlord') $('#landlord_mode_section').show();
            if (mode === 'tenant') $('#tenant_mode_section').show();
        });

        // ═══════════════════════════════════════════════════
        // MODE 1: SEARCH BY PROPERTY (existing)
        // ═══════════════════════════════════════════════════
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
                url: '/ajax/statements/properties',
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

        // Property selected → load tenants
        $('#property_select').on('change', function() {
            const propertyId = $(this).val();
            $('#property_id').val(propertyId);

            if ($('#tenant_select').hasClass('select2-hidden-accessible')) {
                $('#tenant_select').select2('destroy');
            }
            $('#tenant_select').empty().append('<option value="">-- Select Property First --</option>').prop('disabled', true);
            clearTenantDetails();
            if (!propertyId) return;

            $('#tenant_select').empty().append('<option value="">Loading...</option>');
            $.ajax({
                url: '/ajax/statements/tenants-by-property',
                method: 'GET',
                data: {
                    property_id: propertyId
                },
                success: function(response) {
                    $('#tenant_select').empty().append('<option value="">-- Search / Select Tenant --</option>');
                    if (response.success && response.tenants && response.tenants.length > 0) {
                        $.each(response.tenants, function(index, tenant) {
                            $('#tenant_select').append(
                                `<option value="${tenant.encrypted_id}" 
                                         data-name="${tenant.names}" 
                                         data-mobile="${tenant.mobile_number || 'N/A'}" 
                                         data-email="${tenant.email_address || 'N/A'}" 
                                         data-id-number="${tenant.national_id || 'N/A'}">
                                    ${tenant.names} - Unit: ${tenant.unit_code || 'N/A'} - ${tenant.mobile_number || ''}
                                </option>`
                            );
                        });
                        toastr.success(`${response.tenants.length} tenant(s) loaded`);
                    } else {
                        toastr.info('No tenants found for this property');
                    }
                    $('#tenant_select').prop('disabled', false);
                    $('#tenant_select').select2({
                        placeholder: '-- Search / Select Tenant --',
                        allowClear: true,
                        width: '100%',
                        minimumResultsForSearch: 0
                    });
                },
                error: function() {
                    toastr.error('Error loading tenants');
                    $('#tenant_select').empty().append('<option value="">-- Error --</option>');
                }
            });
        });

        // Property mode: tenant selected
        $('#tenant_select').on('change', function() {
            var $sel = $(this).find('option:selected');
            if ($(this).val()) {
                setTenantDetails($sel);
            } else {
                clearTenantDetails();
            }
        });

        // ═══════════════════════════════════════════════════
        // MODE 2: SEARCH BY LANDLORD
        // ═══════════════════════════════════════════════════
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
                                ' data-name="' + (l.names || '') + '">' +
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

        // Landlord selected → load their properties
        $('#landlord_direct_select').on('change', function() {
            var landlordId = $(this).val();
            // Reset downstream
            $('#landlord_property_select').empty().append('<option value="">-- Select Landlord First --</option>').prop('disabled', true);
            if ($('#landlord_tenant_select').hasClass('select2-hidden-accessible')) {
                $('#landlord_tenant_select').select2('destroy');
            }
            $('#landlord_tenant_select').empty().append('<option value="">-- Select Property First --</option>').prop('disabled', true);
            clearTenantDetails();
            if (!landlordId) return;

            $('#landlord_property_select').empty().append('<option value="">Loading...</option>');
            $.ajax({
                url: '/ajax/statements/properties-by-landlord',
                method: 'GET',
                data: {
                    landlord_id: landlordId
                },
                success: function(response) {
                    $('#landlord_property_select').empty().append('<option value="">-- Select Property --</option>');
                    if (response.success && response.properties && response.properties.length > 0) {
                        $.each(response.properties, function(i, prop) {
                            $('#landlord_property_select').append(
                                '<option value="' + prop.id + '">' + prop.property_name + ' - ' + (prop.reference_number || '') + '</option>'
                            );
                        });
                        $('#landlord_property_select').prop('disabled', false);
                        toastr.success(response.properties.length + ' property(ies) found');
                        // Auto-select if only one
                        if (response.properties.length === 1) {
                            $('#landlord_property_select').val(response.properties[0].id).trigger('change');
                        }
                    } else {
                        toastr.info('No properties found for this landlord');
                    }
                },
                error: function() {
                    toastr.error('Error loading properties');
                }
            });
        });

        // Landlord mode: property selected → load tenants
        $('#landlord_property_select').on('change', function() {
            var propertyId = $(this).val();
            if ($('#landlord_tenant_select').hasClass('select2-hidden-accessible')) {
                $('#landlord_tenant_select').select2('destroy');
            }
            $('#landlord_tenant_select').empty().append('<option value="">-- Select Property First --</option>').prop('disabled', true);
            clearTenantDetails();
            if (!propertyId) return;

            $('#landlord_tenant_select').empty().append('<option value="">Loading...</option>');
            $.ajax({
                url: '/ajax/statements/tenants-by-property',
                method: 'GET',
                data: {
                    property_id: propertyId
                },
                success: function(response) {
                    $('#landlord_tenant_select').empty().append('<option value="">-- Search / Select Tenant --</option>');
                    if (response.success && response.tenants && response.tenants.length > 0) {
                        $.each(response.tenants, function(i, t) {
                            $('#landlord_tenant_select').append(
                                `<option value="${t.encrypted_id}" 
                                         data-name="${t.names}" 
                                         data-mobile="${t.mobile_number || 'N/A'}" 
                                         data-email="${t.email_address || 'N/A'}" 
                                         data-id-number="${t.national_id || 'N/A'}">
                                    ${t.names} - Unit: ${t.unit_code || 'N/A'} - ${t.mobile_number || ''}
                                </option>`
                            );
                        });
                        toastr.success(response.tenants.length + ' tenant(s) loaded');
                    } else {
                        toastr.info('No tenants found for this property');
                    }
                    $('#landlord_tenant_select').prop('disabled', false);
                    $('#landlord_tenant_select').select2({
                        placeholder: '-- Search / Select Tenant --',
                        allowClear: true,
                        width: '100%',
                        minimumResultsForSearch: 0
                    });
                },
                error: function() {
                    toastr.error('Error loading tenants');
                    $('#landlord_tenant_select').empty().append('<option value="">-- Error --</option>');
                }
            });
        });

        // Landlord mode: tenant selected
        $('#landlord_tenant_select').on('change', function() {
            var $sel = $(this).find('option:selected');
            if ($(this).val()) {
                setTenantDetails($sel);
            } else {
                clearTenantDetails();
            }
        });

        // ═══════════════════════════════════════════════════
        // MODE 3: SEARCH BY TENANT DIRECTLY
        // ═══════════════════════════════════════════════════
        $('#search_tenant_btn').on('click', function() {
            var searchTerm = $('#tenant_search_input').val().trim();
            if (searchTerm.length < 2) {
                toastr.warning('Please enter at least 2 characters to search');
                return;
            }
            $('#search_tenant_icon').hide();
            $('#search_tenant_spinner').show();
            $('#search_tenant_btn').prop('disabled', true);

            $.ajax({
                url: '/ajax/statements/search-tenants',
                method: 'GET',
                data: {
                    search: searchTerm
                },
                success: function(response) {
                    $('#tenant_direct_select').empty().append('<option value="">-- Select Tenant --</option>');
                    if (response.success && response.tenants && response.tenants.length > 0) {
                        $.each(response.tenants, function(i, t) {
                            var roomCodes = (t.unit_codes && t.unit_codes.length) ? ' [' + t.unit_codes.join(', ') + ']' : '';
                            $('#tenant_direct_select').append(
                                '<option value="' + t.encrypted_id + '"' +
                                ' data-name="' + (t.names || '') + '"' +
                                ' data-mobile="' + (t.mobile_number || 'N/A') + '"' +
                                ' data-email="' + (t.email_address || 'N/A') + '"' +
                                ' data-id-number="' + (t.national_id || 'N/A') + '">' +
                                (t.names || '') + ' - ' + (t.reference_number || '') + roomCodes +
                                '</option>'
                            );
                        });
                        $('#tenant_direct_select').prop('disabled', false);
                        toastr.success('Found ' + response.tenants.length + ' tenant(s)');
                    } else {
                        toastr.info('No tenants found');
                        $('#tenant_direct_select').prop('disabled', true);
                    }
                },
                error: function() {
                    toastr.error('Error searching tenants');
                },
                complete: function() {
                    $('#search_tenant_icon').show();
                    $('#search_tenant_spinner').hide();
                    $('#search_tenant_btn').prop('disabled', false);
                }
            });
        });
        $('#tenant_search_input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#search_tenant_btn').click();
            }
        });

        // Tenant mode: tenant selected
        $('#tenant_direct_select').on('change', function() {
            var $sel = $(this).find(':selected');
            if ($(this).val()) {
                setTenantDetails($sel);
            } else {
                clearTenantDetails();
            }
        });

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

        // Date type change handler - using radio buttons
        $('input[name="date_type"]').on('change', function() {
            const dateType = $(this).val();
            if (dateType === 'end') {
                $('#date_from').prop('disabled', true).prop('required', false).val('');
                $('#date_from_required').hide();
                $('#period_preset').prop('disabled', true);
            } else {
                $('#date_from').prop('disabled', false).prop('required', true);
                $('#date_from_required').show();
                $('#period_preset').prop('disabled', false);
                applyPreset('3m');
                syncPreset();
            }
        });

        // Statement search functionality
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
            const tenantId = $('#tenant_id').val();
            const dateType = $('input[name="date_type"]:checked').val();
            const dateFrom = $('#date_from').val();
            const dateTo = $('#date_to').val();

            if (!tenantId) {
                toastr.warning('Please select a tenant');
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

            const periodText = dateType === 'end' ? 'Up to ' + stmtDay(dateTo) : stmtDay(dateFrom) + ' \u2013 ' + stmtDay(dateTo);
            $('#sheet_empty').addClass('d-none');
            $('#sheet_content').removeClass('d-none');
            $('#sheet_toolbar').removeClass('d-none').addClass('d-flex');
            fillStatementHead($('#selected_tenant_name').text(), periodText);

            let propertyId = '';
            if (mode === 'property') propertyId = $('#property_id').val();
            else if (mode === 'landlord') propertyId = $('#landlord_property_select').val() || '';

            loadStatement(tenantId, dateFrom, dateTo, dateType, propertyId);

            const wrap = document.getElementById('statement_wrap');
            if (wrap && wrap.scrollIntoView) wrap.scrollIntoView({behavior: 'smooth', block: 'start'});
        });

        // The statement sheet: same look as the landlord statement (.stmt-* rules above)
        const stmtEsc = s => $('<div>').text(s == null ? '' : String(s)).html();
        const stmtMoney = v => Number(v || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const stmtDay = ymd => {
            const p = String(ymd).split('-');
            return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'});
        };

        // Fill the header block and blank the tiles while the rows load
        function fillStatementHead(tenantName, periodText) {
            $('#stmt_tenant_name').text(tenantName);
            $('#stmt_period').text(periodText);

            const meta = $('#stmt_meta').empty();
            [['Phone', '#selected_tenant_mobile'], ['Email', '#selected_tenant_email'], ['ID no.', '#selected_tenant_id']].forEach(function(m) {
                const v = $(m[1]).text().trim();
                if (v && v !== '-' && v !== 'N/A') {
                    meta.append($('<span>').text(m[0] + ' ' + v));
                }
            });

            $('#stmt_total_debit, #stmt_total_amount').text('—');
            $('#stmt_total_credit').text('—');
            $('#stmt_closing_note').text('');
            $('#statement_tfoot').empty();
            $('#statement_search').val('');
        }

        function statementMessageRow(icon, text, extraClass) {
            return `
                <tr>
                    <td colspan="6" class="empty ${extraClass || ''}">
                        <span class="material-symbols-outlined d-block mb-1" style="font-size: 40px; opacity: 0.35;">${icon}</span>
                        ${text}
                    </td>
                </tr>
            `;
        }

        let statementRequest = null;

        function loadStatement(tenantId, dateFrom, dateTo, dateType, propertyId) {
            // What Excel and PDF export: always the statement now on the page, even when it came back empty
            window.statementData = {
                tenant_id: tenantId,
                property_id: propertyId,
                date_from: dateFrom,
                date_to: dateTo,
                date_type: dateType
            };

            if (statementRequest) statementRequest.abort();

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
                data: {
                    tenant_id: tenantId,
                    property_id: propertyId,
                    date_from: dateFrom,
                    date_to: dateTo,
                    date_type: dateType,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success && response.statement && response.statement.length > 0) {
                        let html = '';
                        let totalDebit = 0;
                        let totalCredit = 0;

                        response.statement.forEach(function(entry) {
                            totalDebit += Number(entry.debit) || 0;
                            totalCredit += Number(entry.credit) || 0;

                            html += `
                                <tr>
                                    <td class="nowrap">${stmtEsc(entry.date_formatted)}</td>
                                    <td class="ref">${entry.reference === '-' ? '' : stmtEsc(entry.reference)}</td>
                                    <td>${stmtEsc(entry.narration)}</td>
                                    <td class="r dr">${entry.debit_formatted === '-' ? '' : stmtEsc(entry.debit_formatted)}</td>
                                    <td class="r cr">${entry.credit_formatted === '-' ? '' : stmtEsc(entry.credit_formatted)}</td>
                                    <td class="r ${entry.balance < 0 ? 'neg' : ''}">${stmtEsc(entry.balance_formatted)}</td>
                                </tr>
                            `;
                        });

                        $('#statement_tbody').html(html);

                        // Closing balance is the last entry's running balance
                        const lastEntry = response.statement[response.statement.length - 1];
                        const closingNote = lastEntry.balance < 0 ? 'Owed by tenant' : (lastEntry.balance > 0 ? 'In credit' : 'Nil balance');

                        $('#stmt_total_debit').text(stmtMoney(totalDebit));
                        $('#stmt_total_credit').text(stmtMoney(totalCredit));
                        $('#stmt_total_amount').text(lastEntry.balance_formatted);
                        $('#stmt_closing_note').text(closingNote);

                        $('#statement_tfoot').html(`
                            <tr class="totals">
                                <td colspan="3">Period totals</td>
                                <td class="r dr">${stmtMoney(totalDebit)}</td>
                                <td class="r cr">${stmtMoney(totalCredit)}</td>
                                <td class="r"></td>
                            </tr>
                            <tr class="final">
                                <td colspan="5">Closing balance &mdash; ${closingNote}</td>
                                <td class="r">${stmtEsc(lastEntry.balance_formatted)}</td>
                            </tr>
                        `);

                    } else {
                        $('#statement_tbody').html(statementMessageRow('receipt_long', 'No transactions found for the selected period'));
                        $('#stmt_total_debit, #stmt_total_credit').text('0.00');
                        $('#stmt_total_amount').text('0.00');
                        $('#stmt_closing_note').text('Nil balance');
                    }
                },
                error: function(xhr, status) {
                    if (status === 'abort') return;
                    toastr.error('Failed to load statement');
                    $('#statement_tbody').html(statementMessageRow('error', 'Error loading statement', 'text-danger'));
                }
            });
        }

        // Download Excel
        $('#download_excel_btn').on('click', function() {
            if (!window.statementData) {
                toastr.warning('No statement data to export');
                return;
            }

            // Show spinner
            $('#excel_icon').hide();
            $('#excel_spinner').show();
            $('#download_excel_btn').prop('disabled', true);

            const params = new URLSearchParams(window.statementData);
            window.location.href = '/invoices/tenant/statement/export/excel?' + params.toString();

            // Reset button after delay
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

            // Show spinner
            $('#pdf_icon').hide();
            $('#pdf_spinner').show();
            $('#download_pdf_btn').prop('disabled', true);

            const params = new URLSearchParams(window.statementData);
            window.location.href = '/invoices/tenant/statement/export/pdf?' + params.toString();

            // Reset button after delay
            setTimeout(function() {
                $('#pdf_spinner').hide();
                $('#pdf_icon').show();
                $('#download_pdf_btn').prop('disabled', false);
            }, 2000);
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