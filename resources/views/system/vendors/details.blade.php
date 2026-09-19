@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Vendor - {{ $vendor->vendor_name }}</h5>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('vendors.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4" style="font-size:0.875rem;">
                            <strong>Vendor Code:</strong> {{ $vendor->vendor_code }} |
                            <strong>Status:</strong> {{ $vendor->status }} |
                            <strong>Balance:</strong> {{ number_format($vendor->current_balance ?? 0, 2) }}
                        </p>

                        <ul class="nav nav-tabs mb-4" id="vendorTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">store</span> Overview
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="bank-details-tab" data-bs-toggle="tab" data-bs-target="#bank-details" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">account_balance</span> Bank Details
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="vendorTabsContent">
                            <!-- Overview Tab -->
                            <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                                <form id="vendorOverviewForm">
                                    <div class="row g-3" style="max-width:800px;">
                                        <div class="col-md-6">
                                            <label class="form-label">Vendor Code <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="ovVendorCode" value="{{ $vendor->vendor_code }}" @permission('update_vendors') @else disabled @endpermission required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="ovVendorName" value="{{ $vendor->vendor_name }}" @permission('update_vendors') @else disabled @endpermission required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Vendor Type <span class="text-danger">*</span></label>
                                            <select class="form-select" id="ovVendorType" @permission('update_vendors') @else disabled @endpermission required>
                                                @foreach($vendorTypes as $code => $label)
                                                    <option value="{{ $code }}" @selected($vendor->vendor_type === $code)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12"><hr class="my-1"></div>
                                        <div class="col-md-6">
                                            <label class="form-label">Address Line 1</label>
                                            <input type="text" class="form-control" id="ovAddress1" value="{{ $vendor->address_line_1 }}" @permission('update_vendors') @else disabled @endpermission>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Address Line 2</label>
                                            <input type="text" class="form-control" id="ovAddress2" value="{{ $vendor->address_line_2 }}" @permission('update_vendors') @else disabled @endpermission>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Address Line 3</label>
                                            <input type="text" class="form-control" id="ovAddress3" value="{{ $vendor->address_line_3 }}" @permission('update_vendors') @else disabled @endpermission>
                                        </div>
                                    </div>

                                    <div id="ovError" class="alert alert-danger small mt-3" style="display:none; max-width:800px;"></div>

                                    @permission('update_vendors')
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary d-flex align-items-center" id="ovSaveBtn">
                                            <span class="spinner-border spinner-border-sm me-2 d-none" id="ovSaveSpinner"></span>
                                            <span class="material-symbols-outlined me-1" style="font-size:18px;">save</span>
                                            Save Changes
                                        </button>
                                    </div>
                                    @endpermission
                                </form>
                            </div>

                            <!-- Bank Details Tab -->
                            <div class="tab-pane fade" id="bank-details" role="tabpanel" aria-labelledby="bank-details-tab">
                                <form id="vendorBankForm">
                                    <div class="row g-3" style="max-width:800px;">
                                        <div class="col-md-6">
                                            <label class="form-label">Bank Name</label>
                                            <input type="text" class="form-control" id="bankName" value="{{ $vendor->bank_name }}" @permission('update_vendors') @else disabled @endpermission>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Bank Branch</label>
                                            <input type="text" class="form-control" id="bankBranch" value="{{ $vendor->bank_branch }}" @permission('update_vendors') @else disabled @endpermission>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Account Number</label>
                                            <input type="text" class="form-control" id="bankAccountNumber" value="{{ $vendor->account_number }}" @permission('update_vendors') @else disabled @endpermission>
                                        </div>
                                    </div>

                                    <div id="bankError" class="alert alert-danger small mt-3" style="display:none; max-width:800px;"></div>

                                    @permission('update_vendors')
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary d-flex align-items-center" id="bankSaveBtn">
                                            <span class="spinner-border spinner-border-sm me-2 d-none" id="bankSaveSpinner"></span>
                                            <span class="material-symbols-outlined me-1" style="font-size:18px;">save</span>
                                            Save Changes
                                        </button>
                                    </div>
                                    @endpermission
                                </form>
                            </div>
                        </div>
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
        var vendorUrl = '{{ url('system/vendors') }}/{{ $vendor->encrypted_id }}';

        $('#vendorOverviewForm').on('submit', function (e) {
            e.preventDefault();

            var btn = $('#ovSaveBtn');
            var spinner = $('#ovSaveSpinner');
            var errorBox = $('#ovError');
            btn.prop('disabled', true);
            spinner.removeClass('d-none');
            errorBox.hide().empty();

            $.ajax({
                url: vendorUrl,
                type: 'PUT',
                data: {
                    _token: '{{ csrf_token() }}',
                    vendor_code: $('#ovVendorCode').val(),
                    vendor_name: $('#ovVendorName').val(),
                    vendor_type: $('#ovVendorType').val(),
                    address_line_1: $('#ovAddress1').val(),
                    address_line_2: $('#ovAddress2').val(),
                    address_line_3: $('#ovAddress3').val()
                },
                dataType: 'json',
                success: function (response) {
                    toastr.success(response.message);
                },
                error: function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to update vendor.';
                    errorBox.text(message).show();
                },
                complete: function () {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        $('#vendorBankForm').on('submit', function (e) {
            e.preventDefault();

            var btn = $('#bankSaveBtn');
            var spinner = $('#bankSaveSpinner');
            var errorBox = $('#bankError');
            btn.prop('disabled', true);
            spinner.removeClass('d-none');
            errorBox.hide().empty();

            $.ajax({
                url: vendorUrl + '/bank-details',
                type: 'PUT',
                data: {
                    _token: '{{ csrf_token() }}',
                    bank_name: $('#bankName').val(),
                    bank_branch: $('#bankBranch').val(),
                    account_number: $('#bankAccountNumber').val()
                },
                dataType: 'json',
                success: function (response) {
                    toastr.success(response.message);
                },
                error: function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to update bank details.';
                    errorBox.text(message).show();
                },
                complete: function () {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });
    });
</script>
@endsection
