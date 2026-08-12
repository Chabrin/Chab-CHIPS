@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Tax Setup</h4>
                        <div class="ms-auto">
                            <a href="{{ route('finance.taxes.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to list</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="taxSetupForm">
                            <div class="row gy-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Tax Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required maxlength="255" value="{{ $tax->name }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="amount" name="amount" required step="0.01" min="0" value="{{ $tax->amount }}">
                                </div>
                                <div class="col-md-4">
                                    <label for="default_type_id" class="form-label">Default Type</label>
                                    <select class="form-control" id="default_type_id" name="default_type_id">
                                        <option value="">— Select Default Type —</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="computation_id" class="form-label">Computation</label>
                                    <select class="form-control" id="computation_id" name="computation_id">
                                        <option value="">— Select Computation —</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-control" id="category_id" name="category_id">
                                        <option value="">— Select Category —</option>
                                    </select>
                                </div>
                                <div class="col-md-12"></div>
                                <div class="col-md-6">
                                    <label for="debit_account_id" class="form-label">Debit Account</label>
                                    <select class="form-control" id="debit_account_id" name="debit_account_id">
                                        <option value="">— Select Debit Account —</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="credit_account_id" class="form-label">Credit Account</label>
                                    <select class="form-control" id="credit_account_id" name="credit_account_id">
                                        <option value="">— Select Credit Account —</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4 d-flex justify-content-end gap-2">
                                <a href="{{ route('finance.taxes.index') }}" class="btn btn-secondary d-flex align-items-center">
                                    <span class="material-symbols-outlined">close</span>
                                    <span class="ms-1">Cancel</span>
                                </a>
                                <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveTaxBtn">
                                    <span class="material-symbols-outlined" id="saveTaxIcon">check_circle</span>
                                    <span class="ms-1" id="saveTaxText">Update Tax</span>
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
    var currentTaxId = {{ $tax->id }};
    var currentDefaultTypeId = {{ $tax->default_type_id ?? 'null' }};
    var currentComputationId = {{ $tax->computation_id ?? 'null' }};
    var currentCategoryId = {{ $tax->category_id ?? 'null' }};
    var currentDebitAccountId = {{ $tax->debit_account_id ?? 'null' }};
    var currentCreditAccountId = {{ $tax->credit_account_id ?? 'null' }};

    $('select').select2({
        allowClear: true
    });

    function loadTaxOptions() {
        $.get("{{ route('ajax.finance.taxes.options') }}", function(res) {
            if (!res.success) {
                toastr.error(res.error || 'Unable to load tax options');
                return;
            }

            var defaultType = $('#default_type_id');
            var computation = $('#computation_id');
            var category = $('#category_id');

            defaultType.find('option:gt(0)').remove();
            computation.find('option:gt(0)').remove();
            category.find('option:gt(0)').remove();

            (res.data.default_types || []).forEach(function(item) {
                defaultType.append('<option value="' + item.id + '">' + item.type_name + '</option>');
            });
            (res.data.computations || []).forEach(function(item) {
                computation.append('<option value="' + item.id + '">' + item.computation_name + '</option>');
            });
            (res.data.categories || []).forEach(function(item) {
                category.append('<option value="' + item.id + '">' + item.category_name + '</option>');
            });

            // Set current values
            if (currentDefaultTypeId) defaultType.val(currentDefaultTypeId);
            if (currentComputationId) computation.val(currentComputationId);
            if (currentCategoryId) category.val(currentCategoryId);
        }).fail(function() {
            toastr.error('Failed to load tax options');
        });
    }

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
            if (currentDebitAccountId) debit.val(currentDebitAccountId);
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
            if (currentCreditAccountId) credit.val(currentCreditAccountId);
        });

        return $.when(debitPromise, creditPromise);
    }

    loadTaxOptions();
    loadAccounts();

    $('#taxSetupForm').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#saveTaxBtn');
        var $icon = $('#saveTaxIcon');
        var $text = $('#saveTaxText');
        $icon.text('sync').addClass('spin');
        $text.text('Updating...');
        $btn.prop('disabled', true);

        $.ajax({
            url: '{{ route('ajax.finance.taxes.update', encrypt($tax->id)) }}',
            method: 'POST',
            data: $(this).serialize() + '&_token={{ csrf_token() }}',
            success: function(res) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Update Tax');
                $btn.prop('disabled', false);
                if (res.success) {
                    toastr.success(res.message || 'Tax updated successfully');
                    window.location.href = '{{ route('finance.taxes.index') }}';
                } else {
                    toastr.error(res.error || 'Failed to update tax');
                }
            },
            error: function(xhr) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Update Tax');
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
