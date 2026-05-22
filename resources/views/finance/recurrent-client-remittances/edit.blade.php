@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Recurrent Client Remittance</h4>
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
                                <div class="row col-md-12">
                                    <label for="client_id" class="form-label">Client <span class="text-danger">*</span> &nbsp;&nbsp;&nbsp;&nbsp; {{ $client->names }} </label>
                                    <input type="number" id='client_id' name="client_id" value="{{ $client->id ?? '' }}" hidden class="mb-5">
                                    <div class="col-md-4 mt-2">
                                        <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                        <select class="form-control" id="property_id" name="property_id" required>
                                            <option value="">— Select Property —</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mt-2">
                                        <label for="sla_id" class="form-label">SLA</label>
                                        <select class="form-control" id="sla_id" name="sla_id">
                                            <option value="">— Select SLA —</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mt-2">
                                        <label for="remittance_type_id" class="form-label">Type <span class="text-danger">*</span></label>
                                        <select class="form-control" id="remittance_type_id" name="remittance_type_id" required>
                                            <option value="1" {{ $remittance->remittance_type_id == 1 ? 'selected' : '' }}>Clients Bills</option>
                                            <option value="2" {{ $remittance->remittance_type_id == 2 ? 'selected' : '' }}>Property Team Remittance</option>
                                        </select>
                                    </div>
                                </div>
                                
                                
                            </div>

                            {{-- ── Row 2: Start Date / Every Date / Amount / Narration ── --}}
                            <div class="row gy-3 mt-0">
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required
                                        value="{{ $remittance->start_date ? \Carbon\Carbon::parse($remittance->start_date)->format('Y-m-d') : '' }}">
                                </div>
                                <div class="col-md-3">
                                    <label for="every_date" class="form-label">Every (Days) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="every_date" name="every_date" required min="1"
                                        value="{{ $remittance->every_date ?? 25 }}">
                                </div>
                                <div class="col-md-3">
                                    <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="amount" name="amount" required step="0.01" min="0"
                                        value="{{ $remittance->amount ?? '0.00' }}">
                                </div>
                                <div class="col-md-3">
                                    <label for="narration" class="form-label">Narration</label>
                                    <input type="text" class="form-control" id="narration" name="narration" maxlength="500"
                                        value="{{ $remittance->narration }}">
                                </div>
                            </div>

                            {{-- ── Row 3: Begin Interest Date / Interest Rate ── --}}
                            <div class="row gy-3 mt-0">
                                <div class="col-md-3">
                                    <label for="begin_interest_date" class="form-label">Begin Interest Date</label>
                                    <input type="date" class="form-control" id="begin_interest_date" name="begin_interest_date"
                                        value="{{ $remittance->begin_interest_date ? \Carbon\Carbon::parse($remittance->begin_interest_date)->format('Y-m-d') : '' }}">
                                </div>
                                <div class="col-md-3">
                                    <label for="interest_rate" class="form-label">Interest Rate</label>
                                    <input type="number" class="form-control" id="interest_rate" name="interest_rate" step="0.01" min="0"
                                        value="{{ $remittance->interest_rate ?? '0.00' }}">
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
                                        <input type="text" class="form-control" id="account_number" name="account_number" maxlength="50"
                                            value="{{ $remittance->account_number }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="bill_account_number" class="form-label">Bill Account Number</label>
                                        <input type="text" class="form-control" id="bill_account_number" name="bill_account_number" maxlength="50"
                                            value="{{ $remittance->bill_account_number }}">
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
                                    <span class="ms-1" id="saveText">Update</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(function () {

    $('select').select2({ allowClear: true, width: '100%' });

    // Saved values to restore after async loads
    var saved = {
        client_id:       {{ $remittance->client_id   ?? 'null' }},
        property_id:     {{ $remittance->property_id ?? 'null' }},
        sla_id:          {{ ($remittance->sla_id && $remittance->sla_id != 0) ? $remittance->sla_id : 'null' }},
        item_id:         {{ $remittance->item_id      ?? 'null' }},
        supplier_id:     {{ $remittance->supplier_id  ?? 'null' }},
        bank_id:         {{ $remittance->bank_id      ?? 'null' }},
        bank_branch_id:  {{ $remittance->bank_branch_id ?? 'null' }},
        caretaker_id:    {{ ($remittance->caretaker_id && $remittance->caretaker_id != 0) ? $remittance->caretaker_id : 'null' }},
    };

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
    

    // ── Properties: on client change, restore saved value ─────────────────────
    var propSel = $('#property_id');
    var slaSel  = $('#sla_id');
    propSel.find('option:gt(0)').remove().end().trigger('change');
    slaSel.find('option:gt(0)').remove().end().trigger('change');


    $.get("{{ route('ajax.finance.recurrent-client-remittances.properties') }}", { client_id: saved.client_id }, function (res) {
        if (!res.success) { toastr.error(res.error || 'Unable to load properties'); return; }
        (res.properties || []).forEach(function (p) {
            propSel.append('<option value="' + p.id + '">' + p.label + '</option>');
        });
        propSel.val(saved.property_id).trigger('change');
    }).fail(function () { toastr.error('Failed to load properties'); });

    // ── SLAs: on property change, restore saved value ──────────────────────────
    $('#property_id').on('change', function () {
        var slaSel = $('#sla_id');
        slaSel.find('option:gt(0)').remove().end().trigger('change');

        if (!$(this).val()) return;

        $.get("{{ route('ajax.finance.recurrent-client-remittances.slas') }}", { property_id: $(this).val() }, function (res) {
            if (!res.success) { toastr.error(res.error || 'Unable to load SLAs'); return; }
            (res.slas || []).forEach(function (s) {
                slaSel.append('<option value="' + s.id + '">' + s.label + '</option>');
            });
            slaSel.val(saved.sla_id).trigger('change');
        }).fail(function () { toastr.error('Failed to load SLAs'); });
    });

    // ── Item Types ────────────────────────────────────────────────────────────
    $.get("{{ route('ajax.finance.recurrent-client-remittances.invoiceable-items') }}", function (res) {
        if (!res.success) { toastr.error(res.error || 'Unable to load item types'); return; }
        var sel = $('#item_id');
        (res.items || []).forEach(function (i) {
            sel.append('<option value="' + i.id + '">' + i.label + '</option>');
        });
        sel.val(saved.item_id).trigger('change');
    }).fail(function () { toastr.error('Failed to load item types'); });

    // ── Suppliers ─────────────────────────────────────────────────────────────
    $.get("{{ route('ajax.finance.recurrent-client-remittances.providers') }}", function (res) {
        if (!res.success) { toastr.error(res.error || 'Unable to load suppliers'); return; }
        var sel = $('#supplier_id');
        (res.providers || []).forEach(function (p) {
            sel.append('<option value="' + p.id + '">' + p.label + '</option>');
        });
        sel.val(saved.supplier_id).trigger('change');
    }).fail(function () { toastr.error('Failed to load suppliers'); });

    // ── Banks ─────────────────────────────────────────────────────────────────
    $.get("{{ route('ajax.finance.recurrent-client-remittances.banks') }}", function (res) {
        if (!res.success) { toastr.error(res.error || 'Unable to load banks'); return; }
        var sel = $('#bank_id');
        (res.banks || []).forEach(function (b) {
            sel.append('<option value="' + b.id + '">' + b.label + '</option>');
        });
        sel.val(saved.bank_id).trigger('change');
    }).fail(function () { toastr.error('Failed to load banks'); });

    // ── Bank Branches: on bank change, restore saved value ────────────────────
    $('#bank_id').on('change', function () {
        var branchSel = $('#bank_branch_id');
        branchSel.find('option:gt(0)').remove().end().trigger('change');

        if (!$(this).val()) return;

        $.get("{{ route('ajax.finance.recurrent-client-remittances.bank-branches') }}", { bank_id: $(this).val() }, function (res) {
            if (!res.success) { toastr.error(res.error || 'Unable to load branches'); return; }
            (res.branches || []).forEach(function (b) {
                branchSel.append('<option value="' + b.id + '">' + b.label + '</option>');
            });
            branchSel.val(saved.bank_branch_id).trigger('change');
        }).fail(function () { toastr.error('Failed to load bank branches'); });
    });

    // ── Team Members ──────────────────────────────────────────────────────────
    $.get("{{ route('ajax.finance.recurrent-client-remittances.team-members') }}", function (res) {
        if (!res.success) { toastr.error(res.error || 'Unable to load team members'); return; }
        var sel = $('#caretaker_id');
        (res.members || []).forEach(function (m) {
            sel.append('<option value="' + m.id + '">' + m.label + '</option>');
        });
        sel.val(saved.caretaker_id).trigger('change');
    }).fail(function () { toastr.error('Failed to load team members'); });

    // ── Submit ────────────────────────────────────────────────────────────────
    $('#remittanceForm').on('submit', function (e) {
        e.preventDefault();
        var $btn  = $('#saveBtn');
        var $icon = $('#saveIcon');
        var $text = $('#saveText');
        $icon.text('sync').addClass('spin');
        $text.text('Updating...');
        $btn.prop('disabled', true);

        $.ajax({
            url: '{{ route('finance.recurrent-client-remittances.update', $remittance->id) }}',
            method: 'POST',
            data: $(this).serialize() + '&_token={{ csrf_token() }}&_method=PUT',
            success: function (res) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Update');
                $btn.prop('disabled', false);
                if (res.success) {
                    toastr.success(res.message || 'Updated successfully');
                    window.location.href = '{{ route('finance.recurrent-client-remittances.index') }}';
                } else {
                    toastr.error(res.error || 'Failed to update');
                }
            },
            error: function (xhr) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Update');
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