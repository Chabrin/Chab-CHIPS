@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Service Agreement - {{ $sla->reference_number }}</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="serviceAgreementForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">save</span>
                                <span>Update Agreement</span>
                            </button>
                            <a href="{{ route('property.clients.show', Crypt::encrypt($client->id)) }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Client</span>
                            </a>
                        </div>
                    </div>
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
                            
                            <!-- Hidden Fields -->
                            <input type="hidden" name="client_id" value="{{ $client->id }}">

                            <!-- Basic Information Section -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Agreement Details</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="client_name" class="form-label">Client <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" value="{{ $client->names }}" disabled>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                            <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required>
                                                <option value="">Select Property</option>
                                                @foreach($properties as $property)
                                                <option value="{{ $property->id }}" {{ $sla->property_id == $property->id ? 'selected' : '' }}>{{ $property->property_name }}</option>
                                                @endforeach
                                            </select>
                                            @error('property_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-danger d-none" id="currentSlaWarning"></small>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="commission_percentage" class="form-label">Commission % <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" class="form-control @error('commission_percentage') is-invalid @enderror" id="commission_percentage" name="commission_percentage" value="{{ old('commission_percentage', $sla->commission_percentage) }}" required>
                                            @error('commission_percentage')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="vat_able" class="form-label">VATable <span class="text-danger">*</span></label>
                                            <select class="form-select @error('vat_able') is-invalid @enderror" id="vat_able" name="vat_able" required>
                                                <option value="No" {{ $sla->vat == 'No' ? 'selected' : '' }}>No</option>
                                                <option value="Yes" {{ $sla->vat == 'Yes' ? 'selected' : '' }}>Yes</option>
                                                <option value="Flat Rate" {{ $sla->vat == 'Flat Rate' ? 'selected' : '' }}>Flat Rate</option>
                                            </select>
                                            @error('vat_able')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="vat_percentage" class="form-label">VAT %</label>
                                            <input type="number" step="0.01" class="form-control @error('vat_percentage') is-invalid @enderror" id="vat_percentage" name="vat_percentage" value="{{ old('vat_percentage', $sla->vat_per) }}">
                                            @error('vat_percentage')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="flat_rate_vat" class="form-label">Flat Rate VAT</label>
                                            <input type="number" step="0.01" class="form-control @error('flat_rate_vat') is-invalid @enderror" id="flat_rate_vat" name="flat_rate_vat" value="{{ old('flat_rate_vat', $sla->flat_rate_vat) }}">
                                            @error('flat_rate_vat')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="auto_renewal" class="form-label">Auto Renewal <span class="text-danger">*</span></label>
                                            <select class="form-select @error('auto_renewal') is-invalid @enderror" id="auto_renewal" name="auto_renewal" required>
                                                <option value="No" {{ $sla->auto_renewal == 'No' ? 'selected' : '' }}>No</option>
                                                <option value="Yes" {{ $sla->auto_renewal == 'Yes' ? 'selected' : '' }}>Yes</option>
                                            </select>
                                            @error('auto_renewal')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="move_in_inspection_required" class="form-label">Move-in Inspection Required</label>
                                            <select class="form-select" id="move_in_inspection_required" name="move_in_inspection_required">
                                                <option value="0" {{ !$sla->move_in_inspection_required ? 'selected' : '' }}>No — Optional</option>
                                                <option value="1" {{ $sla->move_in_inspection_required  ? 'selected' : '' }}>Yes — Mandatory</option>
                                            </select>
                                            <div class="form-text text-muted" style="font-size:0.75rem;">When enabled, a move-in inspection must be completed during tenant onboarding.</div>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="payment_date" class="form-label">Payment Date (Day of Month) <span class="text-danger">*</span></label>
                                            <input type="number" min="1" max="31" class="form-control @error('payment_date') is-invalid @enderror" id="payment_date" name="payment_date" value="{{ old('payment_date', $sla->landlord_payment_date) }}" required>
                                            @error('payment_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="date_start" class="form-label">Start Date</label>
                                            <input type="date" class="form-control @error('date_start') is-invalid @enderror" id="date_start" name="date_start" value="{{ old('date_start', $sla->date_start) }}">
                                            @error('date_start')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="duration_years" class="form-label">Duration (Years)</label>
                                            <input type="number" min="1" class="form-control @error('duration_years') is-invalid @enderror" id="duration_years" name="duration_years" value="{{ old('duration_years', $sla->years) }}">
                                            @error('duration_years')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-12">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                       id="charge_registration_fee" name="charge_registration_fee" value="1"
                                                       {{ old('charge_registration_fee', $sla->charge_registration_fee) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="charge_registration_fee">
                                                    Charge Registration Fee
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Documents Section -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">SLA Documents</h6>
                                </div>
                                <div class="card-body">
                                    @if($sla->serviceAgreementDocuments && $sla->serviceAgreementDocuments->count() > 0)
                                    <div class="mb-3">
                                        <label class="form-label">Existing Documents</label>
                                        <div class="list-group">
                                            @foreach($sla->serviceAgreementDocuments as $document)
                                            <div class="list-group-item d-flex justify-content-between align-items-center existing-document" data-document-id="{{ $document->id }}">
                                                <span>{{ $document->title }}</span>
                                                <button type="button" class="btn btn-danger btn-sm delete-existing-document" data-document-id="{{ $document->id }}">
                                                    <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                </button>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    <div class="row">
                                        <div class="col-md-12">
                                            <label for="sla_documents" class="form-label">Upload New Documents</label>
                                            <input type="file" class="form-control @error('sla_documents.*') is-invalid @enderror" id="sla_documents" name="sla_documents[]" multiple accept=".pdf,.doc,.docx">
                                            <small class="text-muted">Accepted formats: PDF, DOC, DOCX (Max 10MB each)</small>
                                            @error('sla_documents.*')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tenant Invoiceable Items & Returns Combination -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h6 class="text-muted border-bottom pb-2 d-flex align-items-center">
                                        <span>Tenant Invoiceable Items</span>
                                        <button type="button" class="btn btn-primary btn-sm ms-auto d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addInvoiceableItemModal">
                                            <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span>
                                            <span class="ms-1">Add Item</span>
                                        </button>
                                    </h6>
                                    <div id="invoiceableItemsHiddenInputs"></div>
                                    <div class="table-responsive custom-scrollbar">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Billing Code</th>
                                                    <th>Default Cycle</th>
                                                    <th>Advance Payable</th>
                                                    <th>Amount</th>
                                                    <th>% of Rent</th>
                                                    <th style="width:40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="selectedInvoiceableItemsBody">
                                                <tr id="noInvoiceableItemsRow">
                                                    <td colspan="7" class="text-muted text-center py-3">No items added yet.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h6 class="text-muted border-bottom pb-2">Returns Combination</h6>
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td style="width:32px;">
                                                    <input class="form-check-input" type="radio" name="returns_combination" id="rc_rent_separate"
                                                           value="rent_separate_rest_combined" {{ old('returns_combination', $sla->returns_combination) == 'rent_separate_rest_combined' ? 'checked' : '' }}>
                                                </td>
                                                <td><label for="rc_rent_separate">Rent (Electricity Bill, Water Bill, Service Charges)</label></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <input class="form-check-input" type="radio" name="returns_combination" id="rc_each_separate"
                                                           value="each_separate" {{ old('returns_combination', $sla->returns_combination) == 'each_separate' ? 'checked' : '' }}>
                                                </td>
                                                <td><label for="rc_each_separate">Rent, Electricity Bill, Water Bill, Service Charges (each separate)</label></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <input class="form-check-input" type="radio" name="returns_combination" id="rc_all_combined"
                                                           value="all_combined" {{ old('returns_combination', $sla->returns_combination) == 'all_combined' ? 'checked' : '' }}>
                                                </td>
                                                <td><label for="rc_all_combined">Rent, Electricity Bill, Water Bill, Service Charges (all combined)</label></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Tenant Invoiceable Item Modal -->
<div class="modal fade" id="addInvoiceableItemModal" tabindex="-1" aria-labelledby="addInvoiceableItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addInvoiceableItemModalLabel">Add Tenant Invoiceable Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="modal_item_select" class="form-label">Item <span class="text-danger">*</span></label>
                    <select class="form-select" id="modal_item_select">
                        <option value="">Loading items...</option>
                    </select>
                    <div class="invalid-feedback d-block d-none" id="modal_item_select_error"></div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small text-muted">Billing Code</label>
                        <input type="text" class="form-control form-control-sm" id="modal_item_billing_code" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted">Default Cycle</label>
                        <input type="text" class="form-control form-control-sm" id="modal_item_cycle" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted">Advance Payable</label>
                        <input type="text" class="form-control form-control-sm" id="modal_item_advance" readonly>
                    </div>
                </div>
                <div class="mb-1">
                    <label class="form-label">Value <span class="text-muted small">(optional — choose one)</span></label>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="form-check form-check-inline mb-0" style="width:110px;">
                            <input class="form-check-input" type="radio" name="modal_item_choice" id="modal_choice_amount" value="amount">
                            <label class="form-check-label" for="modal_choice_amount">Amount</label>
                        </div>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" style="width:160px;" id="modal_item_amount" placeholder="Amount" disabled>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="form-check form-check-inline mb-0" style="width:110px;">
                            <input class="form-check-input" type="radio" name="modal_item_choice" id="modal_choice_pct" value="percentage">
                            <label class="form-check-label" for="modal_choice_pct">% of Rent</label>
                        </div>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" style="width:160px;" id="modal_item_pct" placeholder="e.g. 300" disabled>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addInvoiceableItemBtn">
                    <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                    <span>Add</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // VATable change handler
        $('#vat_able').on('change', function() {
            const vatValue = $(this).val();
            
            if (vatValue === 'No') {
                $('#vat_percentage').val('0').prop('disabled', true);
                $('#flat_rate_vat').val('0').prop('disabled', true);
            } else if (vatValue === 'Yes') {
                $('#vat_percentage').val('16').prop('disabled', false);
                $('#flat_rate_vat').val('0').prop('disabled', true);
            } else if (vatValue === 'Flat Rate') {
                $('#vat_percentage').val('0').prop('disabled', true);
                $('#flat_rate_vat').val('').prop('disabled', false);
            }
        });

        // Initialize VAT fields on page load
        $('#vat_able').trigger('change');

        // Warn immediately if the selected property already has a DIFFERENT
        // current SLA — excludes this SLA's own id so picking the property it's
        // already on (the default) never false-positives against itself. Hard
        // block, not overridable — matches SLAController::
        // findCurrentSlaForProperty()'s server-side check exactly.
        $('#property_id').on('change', function() {
            const propertyId = $(this).val();
            const $warning = $('#currentSlaWarning');
            const $saveButton = $('#saveButton');

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
            $('#submitSpinner').removeClass('d-none');
            $('#saveButton').prop('disabled', true);
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

        // Tenant Invoiceable Items: add-one-at-a-time via modal, staged in a
        // table, submitted as hidden inputs matching the server's items[] shape.
        function escapeHtml(str) {
            return $('<div>').text(str ?? '').html();
        }

        let invoiceableItemOptions = []; // [{id, item_name, billing_code, default_cycle_name}]
        let selectedInvoiceableItems = @json($existingInvoiceableItems); // [{item_id, item_name, billing_code, default_cycle_name, choice, amount, percentage_of_rent}]

        function loadInvoiceableItemOptions() {
            $.ajax({
                url: '{{ route("ajax.sla.tenant-invoiceable-items") }}',
                method: 'GET',
                success: function(response) {
                    invoiceableItemOptions = response.items || [];
                    const $select = $('#modal_item_select');
                    $select.html('<option value="">Select an item...</option>');
                    invoiceableItemOptions.forEach(function(item) {
                        $select.append(`<option value="${item.id}">${escapeHtml(item.item_name)}</option>`);
                    });
                },
                error: function(xhr) {
                    console.error('Error loading tenant invoiceable items:', xhr.responseText);
                    $('#modal_item_select').html('<option value="">Failed to load items</option>');
                }
            });
        }

        loadInvoiceableItemOptions();

        // Populate the Billing Code / Default Cycle readonly fields when an item is picked
        $('#modal_item_select').on('change', function() {
            const itemId = $(this).val();
            const item = invoiceableItemOptions.find(i => String(i.id) === String(itemId));
            $('#modal_item_billing_code').val(item ? (item.billing_code || '-') : '');
            $('#modal_item_cycle').val(item ? (item.default_cycle_name || '-') : '');
            $('#modal_item_advance').val(item ? (item.advance_payable || 'No') : '');
        });

        // Amount / % of Rent are mutually exclusive in the modal too
        $('input[name="modal_item_choice"]').on('change', function() {
            const choice = $(this).val();
            $('#modal_item_amount').prop('disabled', choice !== 'amount').val('');
            $('#modal_item_pct').prop('disabled', choice !== 'percentage').val('');
        });

        function resetAddInvoiceableItemModal() {
            $('#modal_item_select').val('');
            $('#modal_item_billing_code, #modal_item_cycle, #modal_item_advance').val('');
            $('input[name="modal_item_choice"]').prop('checked', false);
            $('#modal_item_amount, #modal_item_pct').val('').prop('disabled', true);
            $('#modal_item_select_error').addClass('d-none').text('');
        }

        $('#addInvoiceableItemModal').on('hidden.bs.modal', resetAddInvoiceableItemModal);

        function renderSelectedInvoiceableItems() {
            const $body = $('#selectedInvoiceableItemsBody');
            const $hidden = $('#invoiceableItemsHiddenInputs');

            if (selectedInvoiceableItems.length === 0) {
                $body.html('<tr id="noInvoiceableItemsRow"><td colspan="7" class="text-muted text-center py-3">No items added yet.</td></tr>');
                $hidden.empty();
                return;
            }

            const rows = selectedInvoiceableItems.map(function(entry, index) {
                return `<tr>
                    <td>${escapeHtml(entry.item_name)}</td>
                    <td>${escapeHtml(entry.billing_code || '-')}</td>
                    <td>${escapeHtml(entry.default_cycle_name || '-')}</td>
                    <td>${escapeHtml(entry.advance_payable || 'No')}</td>
                    <td>${entry.choice === 'amount' ? escapeHtml(entry.amount) : '-'}</td>
                    <td>${entry.choice === 'percentage' ? escapeHtml(entry.percentage_of_rent) + '%' : '-'}</td>
                    <td>
                        <a href="#" class="remove-invoiceable-item text-decoration-none" data-index="${index}" title="Remove">
                            <span class="material-symbols-outlined" style="font-size:18px;color:#dc3545;">delete</span>
                        </a>
                    </td>
                </tr>`;
            });
            $body.html(rows.join(''));

            const hiddenInputs = selectedInvoiceableItems.map(function(entry) {
                let html = `<input type="hidden" name="items[${entry.item_id}][selected]" value="1">`;
                if (entry.choice === 'amount') {
                    html += `<input type="hidden" name="items[${entry.item_id}][choice]" value="amount">`;
                    html += `<input type="hidden" name="items[${entry.item_id}][amount]" value="${escapeHtml(entry.amount)}">`;
                } else if (entry.choice === 'percentage') {
                    html += `<input type="hidden" name="items[${entry.item_id}][choice]" value="percentage">`;
                    html += `<input type="hidden" name="items[${entry.item_id}][percentage_of_rent]" value="${escapeHtml(entry.percentage_of_rent)}">`;
                }
                return html;
            });
            $hidden.html(hiddenInputs.join(''));
        }

        renderSelectedInvoiceableItems();

        $('#addInvoiceableItemBtn').on('click', function() {
            const itemId = $('#modal_item_select').val();
            const $error = $('#modal_item_select_error');
            $error.addClass('d-none').text('');

            if (!itemId) {
                $error.removeClass('d-none').text('Please select an item.');
                return;
            }

            if (selectedInvoiceableItems.some(e => String(e.item_id) === String(itemId))) {
                $error.removeClass('d-none').text('This item has already been added.');
                return;
            }

            const item = invoiceableItemOptions.find(i => String(i.id) === String(itemId));
            const choice = $('input[name="modal_item_choice"]:checked').val() || null;
            const amount = choice === 'amount' ? $('#modal_item_amount').val() : null;
            const percentageOfRent = choice === 'percentage' ? $('#modal_item_pct').val() : null;

            selectedInvoiceableItems.push({
                item_id: itemId,
                item_name: item ? item.item_name : '',
                billing_code: item ? item.billing_code : '',
                default_cycle_name: item ? item.default_cycle_name : '',
                advance_payable: item ? (item.advance_payable || 'No') : 'No',
                choice: choice,
                amount: amount,
                percentage_of_rent: percentageOfRent,
            });

            renderSelectedInvoiceableItems();
            $('#addInvoiceableItemModal').modal('hide');
        });

        $(document).on('click', '.remove-invoiceable-item', function(e) {
            e.preventDefault();
            const index = $(this).data('index');
            selectedInvoiceableItems.splice(index, 1);
            renderSelectedInvoiceableItems();
        });

    });
</script>
@endsection