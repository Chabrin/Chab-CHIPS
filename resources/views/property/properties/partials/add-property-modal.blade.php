{{--
    Reusable "Add Property" modal — replaces the old standalone create page.
    Include with an optional $presetClient (a Client model, with bankAccounts.bank
    eager-loaded) to lock the Client field for a known client (e.g. from the client
    details page); omit/null it for a generic context with a client search field
    (e.g. the Properties listing page).

    On success, dispatches a `propertyCreated` event on document with the response
    payload, so the including page can decide what to do next (reload a table,
    redirect, etc.) instead of this partial assuming one behavior for every page.
--}}
@php($presetClient = $presetClient ?? null)
<div class="modal fade" id="addPropertyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Property</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addPropertyForm">
                    @csrf
                    <input type="hidden" id="ap_client_id" name="client_id" value="{{ $presetClient->id ?? '' }}">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Client <span class="text-danger">*</span></label>
                            @if($presetClient)
                                <input type="text" class="form-control" value="{{ $presetClient->names }}" readonly>
                            @else
                                <div class="position-relative">
                                    <input type="text" class="form-control" id="ap_client_search" placeholder="Type to search client..." autocomplete="off">
                                    <div id="ap_client_search_results" class="position-absolute w-100 bg-white border rounded shadow-sm" style="display:none; max-height:250px; overflow-y:auto; z-index:1000;"></div>
                                </div>
                            @endif
                            <div class="invalid-feedback" id="ap_client_id_error"></div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="ap_property_name" class="form-label">Property Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ap_property_name" name="property_name">
                            <div class="invalid-feedback" id="ap_property_name_error"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="ap_zone_id" class="form-label">Zone <span class="text-danger">*</span></label>
                            <select class="form-select" id="ap_zone_id" name="zone_id">
                                <option value="">Please select</option>
                            </select>
                            <div class="invalid-feedback" id="ap_zone_id_error"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="ap_zone_area_id" class="form-label">Zone Area <span class="text-danger">*</span></label>
                            <select class="form-select" id="ap_zone_area_id" name="zone_area_id">
                                <option value="">Please select</option>
                            </select>
                            <div class="invalid-feedback" id="ap_zone_area_id_error"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="ap_property_type_id" class="form-label">Property Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="ap_property_type_id" name="property_type_id">
                                <option value="">Please select</option>
                                <option value="1">Commercial</option>
                                <option value="3">Hybrid</option>
                                <option value="2">Residential</option>
                            </select>
                            <div class="invalid-feedback" id="ap_property_type_id_error"></div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Zone Manager</label>
                            <input type="text" class="form-control" id="ap_zone_manager_id" value="Select a zone first" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Zone Supervisor</label>
                            <input type="text" class="form-control" id="ap_zone_supervisor_id" value="Select a zone first" readonly>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="ap_lr_number" class="form-label">LR Number</label>
                            <input type="text" class="form-control" id="ap_lr_number" name="lr_number">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="ap_acquisition_date" class="form-label">Acquisition Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="ap_acquisition_date" name="acquisition_date" value="{{ now()->format('Y-m-d') }}">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="ap_settlement_account_id" class="form-label">Settlement Account</label>
                            <select class="form-select" id="ap_settlement_account_id" name="settlement_account_id">
                                <option value="">Please select</option>
                                @if($presetClient && $presetClient->bankAccounts)
                                    @foreach($presetClient->bankAccounts as $bankAccount)
                                        <option value="{{ $bankAccount->id }}">{{ $bankAccount->account_name }} - {{ $bankAccount->account_number }} ({{ $bankAccount->bank->bank_name ?? 'N/A' }})</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="ap_latitude" class="form-label">Latitude</label>
                            <input type="number" step="any" class="form-control" id="ap_latitude" name="latitude" placeholder="e.g. -1.2921">
                            <div class="invalid-feedback" id="ap_latitude_error"></div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="ap_longitude" class="form-label">Longitude</label>
                            <input type="number" step="any" class="form-control" id="ap_longitude" name="longitude" placeholder="e.g. 36.8219">
                            <div class="invalid-feedback" id="ap_longitude_error"></div>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="ap_description" class="form-label">Description</label>
                            <textarea class="form-control" id="ap_description" name="description" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary d-flex align-items-center" id="savePropertyBtn">
                    <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                    Save Property
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var apZonesData = [];
    var apZonesLoaded = false;
    var apSelectedClient = {!! $presetClient ? json_encode([
        'id' => $presetClient->id,
        'bank_accounts' => $presetClient->bankAccounts->map(fn($b) => [
            'id' => $b->id, 'account_name' => $b->account_name,
            'account_number' => $b->account_number, 'bank_name' => $b->bank->bank_name ?? null,
        ])->values(),
    ]) : 'null' !!};
    var apModalEl = document.getElementById('addPropertyModal');

    function apLoadZones() {
        $.get("{{ route('ajax.zones.details') }}", function(response) {
            apZonesData = response.zones || [];
            apZonesLoaded = true;
            var sel = $('#ap_zone_id');
            sel.empty().append('<option value="">Please select</option>');
            apZonesData.forEach(function(z) {
                sel.append('<option value="' + z.id + '">' + z.zone_name + '</option>');
            });
        }).fail(function() {
            toastr.error('Failed to load zones.');
        });
    }

    // Bound via native addEventListener, not jQuery's .on() — Bootstrap 5 dispatches
    // these as plain native CustomEvents (literal type "show.bs.modal" etc., not a
    // jQuery-style base-type-plus-namespace pair), so jQuery's own dot-based event
    // parsing doesn't reliably catch them.
    apModalEl.addEventListener('show.bs.modal', function() {
        if (!apZonesLoaded) apLoadZones();
    });

    $('#ap_zone_id').on('change', function() {
        var zoneId = $(this).val();
        var areaSel = $('#ap_zone_area_id');
        areaSel.empty().append('<option value="">Please select</option>');
        $('#ap_zone_manager_id').val('Not assigned');
        $('#ap_zone_supervisor_id').val('Not assigned');
        if (zoneId) {
            var zone = apZonesData.find(function(z) { return z.id == zoneId; });
            if (zone) {
                (zone.areas || []).forEach(function(a) {
                    areaSel.append('<option value="' + a.id + '">' + a.area_name + '</option>');
                });
                $('#ap_zone_manager_id').val(zone.manager_name || 'Not assigned');
                $('#ap_zone_supervisor_id').val(zone.supervisor_name || 'Not assigned');
            }
        }
    });

    @if(!$presetClient)
    var apSearchTimeout;
    var apLastSearchResults = [];

    $('#ap_client_search').on('input', function() {
        clearTimeout(apSearchTimeout);
        var q = $(this).val().trim();
        if (q.length < 2) {
            $('#ap_client_search_results').hide().empty();
            return;
        }
        apSearchTimeout = setTimeout(function() {
            $.get("{{ route('ajax.clients.search') }}", { search: q }, function(response) {
                apLastSearchResults = response.clients || [];
                var html = '';
                if (!apLastSearchResults.length) {
                    html = '<div class="p-2 text-muted">No clients found</div>';
                } else {
                    apLastSearchResults.forEach(function(c) {
                        html += '<div class="ap-client-result-item p-2 border-bottom" style="cursor:pointer;" data-id="' + c.id + '" data-name="' + c.names + '">' +
                            '<div class="fw-bold">' + c.names + '</div><small class="text-muted">' + (c.email || '-') + ' | ' + (c.mobile_number || '-') + '</small></div>';
                    });
                }
                $('#ap_client_search_results').html(html).show();
            });
        }, 500);
    });

    $(document).on('click', '.ap-client-result-item', function() {
        var id = $(this).data('id');
        $('#ap_client_id').val(id);
        $('#ap_client_search').val($(this).data('name'));
        apSelectedClient = apLastSearchResults.find(function(c) { return c.id == id; }) || null;
        apPopulateSettlement();
        $('#ap_client_search_results').hide();
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#ap_client_search, #ap_client_search_results').length) {
            $('#ap_client_search_results').hide();
        }
    });
    @endif

    function apPopulateSettlement() {
        var sel = $('#ap_settlement_account_id');
        sel.empty().append('<option value="">Please select</option>');
        if (apSelectedClient && apSelectedClient.bank_accounts && apSelectedClient.bank_accounts.length) {
            apSelectedClient.bank_accounts.forEach(function(a) {
                sel.append('<option value="' + a.id + '">' + (a.account_name || 'N/A') + ' - ' + (a.account_number || 'N/A') + ' (' + (a.bank_name || 'N/A') + ')</option>');
            });
        }
    }

    function apClearErrors() {
        $('#addPropertyModal .is-invalid').removeClass('is-invalid');
        $('#addPropertyModal .invalid-feedback').text('');
    }

    function apResetForm() {
        $('#addPropertyForm')[0].reset();
        $('#ap_zone_area_id').empty().append('<option value="">Please select</option>');
        $('#ap_zone_manager_id, #ap_zone_supervisor_id').val('Select a zone first');
        apClearErrors();
        @if($presetClient)
        $('#ap_client_id').val('{{ $presetClient->id }}');
        @else
        $('#ap_client_id').val('');
        $('#ap_client_search').val('');
        apSelectedClient = null;
        @endif
        apPopulateSettlement();
    }

    apModalEl.addEventListener('hidden.bs.modal', apResetForm);

    $('#savePropertyBtn').on('click', function() {
        apClearErrors();
        var $btn = $(this);
        var $spinner = $btn.find('.spinner-border');
        $spinner.removeClass('d-none');
        $btn.prop('disabled', true);

        $.ajax({
            url: "{{ route('property.properties.store') }}",
            type: 'POST',
            data: $('#addPropertyForm').serialize(),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Property created successfully');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('addPropertyModal')).hide();
                    $(document).trigger('propertyCreated', [res]);
                } else {
                    toastr.error(res.message || 'Failed to create property.');
                }
            },
            error: function(xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    var fieldMap = {
                        client_id: 'ap_client_id', property_name: 'ap_property_name',
                        zone_id: 'ap_zone_id', zone_area_id: 'ap_zone_area_id',
                        property_type_id: 'ap_property_type_id', latitude: 'ap_latitude',
                        longitude: 'ap_longitude'
                    };
                    Object.keys(errors).forEach(function(field) {
                        var id = fieldMap[field];
                        if (id) {
                            $('#' + id).addClass('is-invalid');
                            $('#' + id + '_error').text(errors[field][0]);
                        }
                    });
                    toastr.error('Please correct the errors and try again.');
                } else {
                    toastr.error('Failed to create property.');
                }
            },
            complete: function() {
                $spinner.addClass('d-none');
                $btn.prop('disabled', false);
            }
        });
    });
})();
</script>
