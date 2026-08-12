@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Create Recurrent Client Remittance</h4>
                        <div class="ms-auto">
                            <a href="{{ route('finance.recurrent-client-remittances.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to list</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="remittanceForm">
                            {{-- ── Row 1: Client / Property / SLA / Type ── --}}
                            <div class="row gy-3">
                                <div class="col-md-3">
                                    <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                                    <select class="form-control" id="client_id" name="client_id" required>
                                        <option value="">— Select Client —</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-control" id="property_id" name="property_id" required>
                                        <option value="">— Select Property —</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="sla_id" class="form-label">SLA</label>
                                    <select class="form-control" id="sla_id" name="sla_id">
                                        <option value="">— Select SLA —</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="remittance_type_id" class="form-label">Type <span class="text-danger">*</span></label>
                                    <select class="form-control" id="remittance_type_id" name="remittance_type_id" required>
                                        <option value="1">Clients Bills</option>
                                        <option value="2">Property Team Remittance</option>
                                    </select>
                                </div>
                            </div>

                            {{-- ── Row 2: Start Date / Every Date / Amount / Narration ── --}}
                            <div class="row gy-3 mt-0">
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="every_date" class="form-label">Every (Days) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="every_date" name="every_date" required min="1" value="25">
                                </div>
                                <div class="col-md-3">
                                    <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="amount" name="amount" required step="0.01" min="0" value="0.00">
                                </div>
                                <div class="col-md-3">
                                    <label for="narration" class="form-label">Narration</label>
                                    <input type="text" class="form-control" id="narration" name="narration" maxlength="500">
                                </div>
                            </div>

                            {{-- ── Row 3: Begin Interest Date / Interest Rate ── --}}
                            <div class="row gy-3 mt-0">
                                <div class="col-md-3">
                                    <label for="begin_interest_date" class="form-label">Begin Interest Date</label>
                                    <input type="date" class="form-control" id="begin_interest_date" name="begin_interest_date">
                                </div>
                                <div class="col-md-3">
                                    <label for="interest_rate" class="form-label">Interest Rate</label>
                                    <input type="number" class="form-control" id="interest_rate" name="interest_rate" step="0.01" min="0" value="0.00">
                                </div>
                            </div>

                            <hr class="my-4">

                            {{-- ── Clients Bills Section ── --}}
                            <div id="clientsBillsSection">
                                <h6 class="mb-3 fw-semibold">Clients Bills</h6>
                                <div class="row gy-3">
                                    <div class="col-md-3">
                                        <label for="item_id" class="form-label">Item Type</label>
                                        <select class="form-control" id="item_id" name="item_id">
                                            <option value="">— Select Item Type —</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="supplier_id" class="form-label">Supplier</label>
                                        <select class="form-control" id="supplier_id" name="supplier_id">
                                            <option value="">— Select Supplier —</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="bank_id" class="form-label">Default Bank</label>
                                        <select class="form-control" id="bank_id" name="bank_id">
                                            <option value="">— Select Bank —</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="bank_branch_id" class="form-label">Bank Branch</label>
                                        <select class="form-control" id="bank_branch_id" name="bank_branch_id">
                                            <option value="">— Select Branch —</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="account_number" class="form-label">Account Number</label>
                                        <input type="text" class="form-control" id="account_number" name="account_number" maxlength="50">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="bill_account_number" class="form-label">Bill Account Number</label>
                                        <input type="text" class="form-control" id="bill_account_number" name="bill_account_number" maxlength="50">
                                    </div>
                                </div>
                            </div>

                            {{-- ── Property Team Remittances Section ── --}}
                            <div id="teamRemittanceSection" style="display:none;">
                                <h6 class="mb-3 fw-semibold">Property Team Remittances</h6>
                                <div class="row gy-3">
                                    <div class="col-md-3">
                                        <label for="caretaker_id" class="form-label">Team Member <span class="text-danger">*</span></label>
                                        <select class="form-control" id="caretaker_id" name="caretaker_id">
                                            <option value="">— Select Team Member —</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 d-flex justify-content-end gap-2">
                                <a href="{{ route('finance.recurrent-client-remittances.index') }}" class="btn btn-secondary d-flex align-items-center">
                                    <span class="material-symbols-outlined">close</span>
                                    <span class="ms-1">Cancel</span>
                                </a>
                                <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveBtn">
                                    <span class="material-symbols-outlined" id="saveIcon">check_circle</span>
                                    <span class="ms-1" id="saveText">Save</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {

    $('select').select2({ allowClear: true, width: '100%' });

    // ── Toggle type-specific sections ─────────────────────────────────────────
    function toggleTypeSections() {
        var type = $('#remittance_type_id').val();
        if (type == '1') {
            $('#clientsBillsSection').show();
            $('#teamRemittanceSection').hide();
            $('#caretaker_id').removeAttr('required');
        } else {
            $('#clientsBillsSection').hide();
            $('#teamRemittanceSection').show();
            $('#caretaker_id').attr('required', 'required');
        }
    }

    $('#remittance_type_id').on('change', toggleTypeSections);
    toggleTypeSections();

    // ── Clients ───────────────────────────────────────────────────────────────
    $('#client_id').select2({
        placeholder: 'Search for a client/landlord...',
        allowClear: true,
        width: '100%',
        minimumInputLength: 2,
        ajax: {
            url: "{{ route('ajax.clients.list') }}",
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    client_type: 'Landlord',
                    search: params.term
                };
            },
            processResults: function(response) {
                return {
                    results: response.data.map(function(client) {
                        return {
                            id: client.id,
                            text: `${client.names} (${client.reference_number})`
                        };
                    })
                };
            },
            cache: true
        }
    });

    // ── Properties: on client change ──────────────────────────────────────────
    $('#client_id').on('change', function () {
        var propSel = $('#property_id');
        var slaSel  = $('#sla_id');
        propSel.find('option:gt(0)').remove().end().trigger('change');
        slaSel.find('option:gt(0)').remove().end().trigger('change');

        if (!$(this).val()) return;

        $.get("{{ route('ajax.finance.recurrent-client-remittances.properties') }}", { client_id: $(this).val() }, function (res) {
            if (!res.success) { toastr.error(res.error || 'Unable to load properties'); return; }
            (res.properties || []).forEach(function (p) {
                propSel.append('<option value="' + p.id + '">' + p.label + '</option>');
            });
        }).fail(function () { toastr.error('Failed to load properties'); });
    });
    

    // ── SLAs: on property change ──────────────────────────────────────────────
    $('#property_id').on('change', function () {
        var slaSel = $('#sla_id');
        slaSel.find('option:gt(0)').remove().end().trigger('change');

        if (!$(this).val()) return;

        $.get("{{ route('ajax.finance.recurrent-client-remittances.slas') }}", { property_id: $(this).val() }, function (res) {
            if (!res.success) { toastr.error(res.error || 'Unable to load SLAs'); return; }
            (res.slas || []).forEach(function (s) {
                slaSel.append('<option value="' + s.id + '">' + s.label + '</option>');
            });
        }).fail(function () { toastr.error('Failed to load SLAs'); });
    });

    // ── Item Types ────────────────────────────────────────────────────────────
    $.get("{{ route('ajax.finance.recurrent-client-remittances.invoiceable-items') }}", function (res) {
        if (!res.success) { toastr.error(res.error || 'Unable to load item types'); return; }
        (res.items || []).forEach(function (i) {
            $('#item_id').append('<option value="' + i.id + '">' + i.label + '</option>');
        });
    }).fail(function () { toastr.error('Failed to load item types'); });

    // ── Suppliers ─────────────────────────────────────────────────────────────
    $.get("{{ route('ajax.finance.recurrent-client-remittances.providers') }}", function (res) {
        if (!res.success) { toastr.error(res.error || 'Unable to load suppliers'); return; }
        (res.providers || []).forEach(function (p) {
            $('#supplier_id').append('<option value="' + p.id + '">' + p.label + '</option>');
        });
    }).fail(function () { toastr.error('Failed to load suppliers'); });

    // ── Banks ─────────────────────────────────────────────────────────────────
    $.get("{{ route('ajax.finance.recurrent-client-remittances.banks') }}", function (res) {
        if (!res.success) { toastr.error(res.error || 'Unable to load banks'); return; }
        (res.banks || []).forEach(function (b) {
            $('#bank_id').append('<option value="' + b.id + '">' + b.label + '</option>');
        });
    }).fail(function () { toastr.error('Failed to load banks'); });

    // ── Bank Branches: on bank change ─────────────────────────────────────────
    $('#bank_id').on('change', function () {
        var branchSel = $('#bank_branch_id');
        branchSel.find('option:gt(0)').remove().end().trigger('change');

        if (!$(this).val()) return;

        $.get("{{ route('ajax.finance.recurrent-client-remittances.bank-branches') }}", { bank_id: $(this).val() }, function (res) {
            if (!res.success) { toastr.error(res.error || 'Unable to load branches'); return; }
            (res.branches || []).forEach(function (b) {
                branchSel.append('<option value="' + b.id + '">' + b.label + '</option>');
            });
        }).fail(function () { toastr.error('Failed to load bank branches'); });
    });

    // ── Team Members ──────────────────────────────────────────────────────────
    $.get("{{ route('ajax.finance.recurrent-client-remittances.team-members') }}", function (res) {
        if (!res.success) { toastr.error(res.error || 'Unable to load team members'); return; }
        (res.members || []).forEach(function (m) {
            $('#caretaker_id').append('<option value="' + m.id + '">' + m.label + '</option>');
        });
    }).fail(function () { toastr.error('Failed to load team members'); });

    // ── Submit ────────────────────────────────────────────────────────────────
    $('#remittanceForm').on('submit', function (e) {
        e.preventDefault();
        var $btn  = $('#saveBtn');
        var $icon = $('#saveIcon');
        var $text = $('#saveText');
        $icon.text('sync').addClass('spin');
        $text.text('Saving...');
        $btn.prop('disabled', true);

        $.ajax({
            url: '{{ route('finance.recurrent-client-remittances.store') }}',
            method: 'POST',
            data: $(this).serialize() + '&_token={{ csrf_token() }}',
            success: function (res) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Save');
                $btn.prop('disabled', false);
                if (res.success) {
                    toastr.success(res.message || 'Saved successfully');
                    window.location.href = '{{ route('finance.recurrent-client-remittances.index') }}';
                } else {
                    toastr.error(res.error || 'Failed to save');
                }
            },
            error: function (xhr) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Save');
                $btn.prop('disabled', false);
                var msg = 'Validation error';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                } else if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                toastr.error(msg);
            }
        });
    });
});
</script>
@endsection