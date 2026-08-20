@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Lease Agreement — {{ $lease->ref_number }}</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="leaseEditForm" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save</span>
                            </button>
                            <a href="{{ route('property.leases.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
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

                        <div class="row mb-4">
                            <div class="col-12 mb-3">
                                <h6 class="text-muted border-bottom pb-2">Lease Details (not editable — use Tenant Movement to reassign)</h6>
                            </div>
                            <div class="col-md-3 mb-2">
                                <small class="text-muted d-block">Tenant</small>
                                <strong>{{ $lease->tenant->names ?? '-' }}</strong>
                            </div>
                            <div class="col-md-3 mb-2">
                                <small class="text-muted d-block">Property</small>
                                <strong>{{ $lease->property->property_name ?? '-' }}</strong>
                            </div>
                            <div class="col-md-3 mb-2">
                                <small class="text-muted d-block">Unit</small>
                                <strong>{{ $lease->unit->unit_code ?? $lease->unit->unit_number ?? '-' }}</strong>
                            </div>
                            <div class="col-md-3 mb-2">
                                <small class="text-muted d-block">Landlord</small>
                                <strong>{{ $lease->client->names ?? '-' }}</strong>
                            </div>
                        </div>

                        <form action="{{ route('property.leases.update', $lease->id) }}" method="POST" id="leaseEditForm">
                            @csrf
                            @method('PUT')

                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Editable Terms</h6>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="date_start" class="form-label">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('date_start') is-invalid @enderror"
                                           id="date_start" name="date_start"
                                           value="{{ old('date_start', optional($lease->date_start)->format('Y-m-d')) }}" required>
                                    @error('date_start')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="years" class="form-label">Duration (Years) <span class="text-danger">*</span></label>
                                    <input type="number" step="1" min="1" max="10" class="form-control @error('years') is-invalid @enderror"
                                           id="years" name="years" value="{{ old('years', $lease->years) }}" required>
                                    <small class="text-muted">End date recalculates from start date + years on save. Current end date: {{ optional($lease->date_end)->format('d-m-Y') ?? '-' }}</small>
                                    @error('years')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="auto_renewal" class="form-label">Auto Renewal <span class="text-danger">*</span></label>
                                    <select class="form-select @error('auto_renewal') is-invalid @enderror" id="auto_renewal" name="auto_renewal" required>
                                        <option value="Yes" {{ old('auto_renewal', $lease->auto_renewal) == 'Yes' ? 'selected' : '' }}>Yes</option>
                                        <option value="No" {{ old('auto_renewal', $lease->auto_renewal) == 'No' ? 'selected' : '' }}>No</option>
                                    </select>
                                    @error('auto_renewal')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="escalation_rate" class="form-label">Escalation Rate (%) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control @error('escalation_rate') is-invalid @enderror"
                                           id="escalation_rate" name="escalation_rate" value="{{ old('escalation_rate', $lease->escalation_rate) }}" required>
                                    @error('escalation_rate')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="escalation_frequency" class="form-label">Escalation Frequency <span class="text-danger">*</span></label>
                                    <select class="form-select @error('escalation_frequency') is-invalid @enderror" id="escalation_frequency" name="escalation_frequency" required>
                                        @foreach(['Annually', 'Semi-Annually', 'Quarterly', 'Monthly'] as $freq)
                                        <option value="{{ $freq }}" {{ old('escalation_frequency', $lease->escalation_frequency) == $freq ? 'selected' : '' }}>{{ $freq }}</option>
                                        @endforeach
                                    </select>
                                    @error('escalation_frequency')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Invoicable Items — this lease's current items, editable in place,
                                 plus an option to replace them wholesale with the property's
                                 SLA-driven billable items (see lease/create.blade.php for that
                                 same SLA-derived row shape). -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <h6 class="text-muted border-bottom pb-2 mb-0 flex-grow-1">Invoicable Items</h6>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="switchToSlaBtn">
                                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">sync_alt</span>
                                        Switch to SLA-driven Billable Items
                                    </button>
                                </div>
                                <div class="col-12">
                                    <div class="text-muted small mb-3" id="noBillableItemsNote" style="display:none;">
                                        No invoiceable items on this lease.
                                    </div>
                                </div>
                                <div id="invoicableItemsContainer"></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function () {
        var invoiceableCycles = [];
        var propertyId = {{ $lease->property_id ?? 'null' }};

        // Seeded from this lease's actual current items (LeasesController::edit()) —
        // unlike create.blade.php, this page doesn't start empty. Already shaped
        // {item_id, name, amount, cycle_id, prepay_client} by the controller — keep
        // this as a plain variable reference, not a reshaping closure: Blade's
        // directive scanner matches the JSON-output directive's name anywhere in the
        // raw file text, even inside JS comments, so a closure argument (or even
        // describing it in a comment with matching parens) breaks compilation.
        var existingItems = @json($invoiceableItems);

        loadInvoiceableCycles();

        function loadInvoiceableCycles() {
            $.ajax({
                url: "{{ route('property.settings.cycles.data') }}",
                method: 'GET',
                dataType: 'json',
                success: function (response) {
                    if (response && response.length > 0) {
                        invoiceableCycles = response;
                        // Cycle dropdowns render blank until this loads — re-render
                        // whatever's currently shown once cycle names are available.
                        renderInvoiceableItems(existingItems);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error loading cycles:', xhr.responseText);
                }
            });
        }

        // Renders items shaped {item_id, name, amount, cycle_id, prepay_client} —
        // whether seeded from this lease's existing rows or fetched fresh from a
        // property's SLA (see switchToSlaBtn below). Unchecking a row's checkbox
        // disables its inputs; disabled fields are natively excluded from the
        // submitted FormData, dropping that row from invocable_items[] on save.
        function renderInvoiceableItems(items) {
            $('#invoicableItemsContainer').empty();
            $('#noBillableItemsNote').toggle(items.length === 0);

            var cycleOptions = '<option value="">Please select</option>';
            invoiceableCycles.forEach(function (cycle) {
                cycleOptions += `<option value="${cycle.id}">${cycle.text}</option>`;
            });

            items.forEach(function (item, index) {
                var rowCycleOptions = cycleOptions;
                if (item.cycle_id) {
                    rowCycleOptions = rowCycleOptions.replace(
                        `value="${item.cycle_id}"`,
                        `value="${item.cycle_id}" selected`
                    );
                }

                const entry = `
                    <div class="billable-item-entry mb-3 p-3 border rounded">
                        <div class="row align-items-end">
                            <div class="col-md-3 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input billable-item-checkbox" type="checkbox"
                                        id="item_enabled_${index}" checked data-target="item-row-${index}">
                                    <label class="form-check-label fw-semibold" for="item_enabled_${index}">${item.name}</label>
                                </div>
                                <input type="hidden" class="item-row-${index}" name="invoicable_items[${index}][item]" value="${item.item_id}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="amount_${index}" class="form-label">Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control item-row-${index}"
                                    id="amount_${index}" name="invoicable_items[${index}][amount]"
                                    value="${item.amount || '0.00'}" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="cycle_${index}" class="form-label">Cycle <span class="text-danger">*</span></label>
                                <select class="form-select item-row-${index}" id="cycle_${index}" name="invoicable_items[${index}][cycle]" required>
                                    ${rowCycleOptions}
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label d-block">Prepay Client</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input item-row-${index}" type="checkbox" id="prepay_client_${index}" name="invoicable_items[${index}][prepay_client]" value="Yes" ${item.prepay_client ? 'checked' : ''}>
                                    <label class="form-check-label" for="prepay_client_${index}">Yes</label>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                $('#invoicableItemsContainer').append(entry);
            });
        }

        $('#invoicableItemsContainer').on('change', '.billable-item-checkbox', function () {
            var target = $(this).data('target');
            $('.' + target).prop('disabled', !this.checked);
        });

        // Replaces the whole list with the property's current SLA-enabled billable
        // items — same endpoint/response create.blade.php's Invoiceable Items uses
        // (SLAController::getSLAForProperty()). Client-side staging only: nothing
        // persists until the page's own Save button is clicked, same as every other
        // field on this form.
        $('#switchToSlaBtn').on('click', function () {
            if (!propertyId) {
                toastr.error('This lease has no property on record — cannot look up its SLA.');
                return;
            }

            Swal.fire({
                title: 'Switch to SLA-driven billable items?',
                html: 'This replaces every item currently listed below with whatever this property\'s SLA has enabled (Electricity/Water/Service Charges). Nothing is saved until you click Save on this page.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Switch',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                $.ajax({
                    url: `/property/${propertyId}/sla`,
                    method: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        var slaItems = (response.billable_items || []).map(function (i) {
                            return { item_id: i.item_id, name: i.name, amount: i.cost, cycle_id: null, prepay_client: false };
                        });
                        renderInvoiceableItems(slaItems);
                        toastr.success('Switched to this property\'s SLA-driven billable items — review and Save to keep it.');
                    },
                    error: function () {
                        toastr.error('Failed to load this property\'s SLA billable items.');
                    }
                });
            });
        });
    });
</script>
@endsection
