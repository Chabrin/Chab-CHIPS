@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Transaction Type</h4>
                        <div class="ms-auto">
                            <a href="{{ route('ledger.accounts.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to list</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="transactionTypeForm">
                            <div class="row gy-3">
                                <div class="col-md-4">
                                    <label for="type_name" class="form-label">Type Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="type_name" name="type_name" required maxlength="255" value="{{ $transactionType->type_name }}">
                                </div>
                                <div class="col-md-4">
                                    <label for="item_code" class="form-label">Item Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="item_code" name="item_code" required maxlength="50" value="{{ $transactionType->item_code }}">
                                </div>
                                <div class="col-md-4">
                                    <label for="default_amount" class="form-label">Default Amount</label>
                                    <input type="number" class="form-control" id="default_amount" name="default_amount" step="0.01" min="0" value="{{ $transactionType->default_amount ?? '0.00' }}">
                                </div>
                                <div class="col-md-4">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-control" id="category_id" name="category_id">
                                        <option value="">— Select Category —</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="debit_account_id" class="form-label">Debit Account</label>
                                    <select class="form-control" id="debit_account_id" name="debit_account_id">
                                        <option value="">— Select Debit Account —</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="credit_account_id" class="form-label">Credit Account</label>
                                    <select class="form-control" id="credit_account_id" name="credit_account_id">
                                        <option value="">— Select Credit Account —</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4 d-flex justify-content-end gap-2">
                                <a href="{{ route('ledger.accounts.index') }}" class="btn btn-secondary d-flex align-items-center">
                                    <span class="material-symbols-outlined">close</span>
                                    <span class="ms-1">Cancel</span>
                                </a>
                                <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveTypeBtn">
                                    <span class="material-symbols-outlined" id="saveTypeIcon">check_circle</span>
                                    <span class="ms-1" id="saveTypeText">Update Type</span>
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
$(function() {

    $('select').select2({
        allowClear: true,
        width: '100%'
    });

    // ── Shared with Tax Setup: pulls categories from the same options endpoint ──
    function loadOptions() {
        $.get("{{ route('ajax.finance.taxes.options') }}", function(res) {
            if (!res.success) {
                toastr.error(res.error || 'Unable to load options');
                return;
            }

            var category = $('#category_id');
            category.find('option:gt(0)').remove();

            (res.data.categories || []).forEach(function(item) {
                category.append('<option value="' + item.id + '">' + item.category_name + '</option>');
            });

            category.val({{ $transactionType->category_id ?? 'null' }}).trigger('change');
        }).fail(function() {
            toastr.error('Failed to load categories');
        });
    }

    // ── Shared with Tax Setup: pulls debit/credit accounts from the same endpoint ──
    function loadAccounts() {
        var debitPromise = $.get("{{ url('/ajax/finance/taxes/accounts') }}?type=debit", function(res) {
            if (!res.success) {
                toastr.error(res.error || 'Unable to load debit accounts');
                return;
            }
            var debit = $('#debit_account_id');
            debit.find('option:gt(0)').remove();
            (res.accounts || []).forEach(function(account) {
                debit.append('<option value="' + account.id + '">' + account.label + '</option>');
            });
            debit.val({{ $transactionType->debit_account_id ?? 'null' }}).trigger('change');
        });

        var creditPromise = $.get("{{ url('/ajax/finance/taxes/accounts') }}?type=credit", function(res) {
            if (!res.success) {
                toastr.error(res.error || 'Unable to load credit accounts');
                return;
            }
            var credit = $('#credit_account_id');
            credit.find('option:gt(0)').remove();
            (res.accounts || []).forEach(function(account) {
                credit.append('<option value="' + account.id + '">' + account.label + '</option>');
            });
            credit.val({{ $transactionType->credit_account_id ?? 'null' }}).trigger('change');
        });

        return $.when(debitPromise, creditPromise);
    }

    loadOptions();
    loadAccounts();

    $('#transactionTypeForm').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#saveTypeBtn');
        var $icon = $('#saveTypeIcon');
        var $text = $('#saveTypeText');
        $icon.text('sync').addClass('spin');
        $text.text('Updating...');
        $btn.prop('disabled', true);

        var formData = $(this).serializeArray();
        formData.push({ name: '_token', value: $('meta[name="csrf-token"]').attr('content') });
        formData.push({ name: '_method', value: 'PUT' });

        var checkboxes = {
            'is_commissionable': 0,
            'is_recovery': 0,
            'is_client_expense': 0
        };

        $.each(checkboxes, function(key) {
            formData = formData.filter(function(item) { return item.name !== key; });
            formData.push({ name: key, value: $('#' + key).is(':checked') ? 1 : 0 });
        });

        $.ajax({
            url: '{{ route('finance.transaction-types.update', $transactionType->id) }}',
            method: 'POST',
            data: formData,
            success: function(res) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Update Type');
                $btn.prop('disabled', false);
                if (res.success) {
                    toastr.success(res.message || 'Transaction type updated successfully');
                    window.location.href = '{{ route('ledger.accounts.index') }}';
                } else {
                    toastr.error(res.error || 'Failed to update transaction type');
                }
            },
            error: function(xhr) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Update Type');
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