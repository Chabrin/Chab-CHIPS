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

<!-- Statement Modal -->
<div class="modal fade" id="statementModal" tabindex="-1" aria-labelledby="statementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
            <!-- Thin Header with Actions -->
            <div class="modal-header py-2 px-3 bg-white border-bottom">
                <div class="d-flex align-items-center gap-2 w-100">
                    <span class="material-symbols-outlined text-dark">description</span>
                    <h6 class="mb-0 flex-grow-1 text-dark">Tenant Statement</h6>
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

            <!-- Modal Body with Search and Table -->
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
                                <strong>Tenant:</strong> <span id="stmt_tenant_name"></span>
                            </small>
                        </div>
                        <div class="col-6 text-end">
                            <small class="d-block" style="font-size: 0.75rem;">
                                <strong>Total:</strong> <span id="stmt_total_amount" class="fw-bold text-dark"></span>
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

        // Date type change handler - using radio buttons
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

            $('#statementModal').modal('show');
            $('#stmt_tenant_name').text($('#selected_tenant_name').text());

            let propertyId = '';
            if (mode === 'property') propertyId = $('#property_id').val();
            else if (mode === 'landlord') propertyId = $('#landlord_property_select').val() || '';

            loadStatement(tenantId, dateFrom, dateTo, dateType, propertyId);
        });

        function loadStatement(tenantId, dateFrom, dateTo, dateType, propertyId) {
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

                        // Get final balance from last entry
                        if (response.statement.length > 0) {
                            const lastEntry = response.statement[response.statement.length - 1];
                            $('#stmt_total_amount').text(lastEntry.balance_formatted);
                        }

                        // Store data for export
                        window.statementData = {
                            tenant_id: tenantId,
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
                        $('#stmt_total_amount').text('Ksh 0.00');
                    }
                },
                error: function(xhr) {
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