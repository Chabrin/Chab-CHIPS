@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Company</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('listcompanies') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back_ios_new</span>
                                <span class="ms-1">Close</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('components.error_reports')

                        @if(isset($company))
                        <form method="POST" action="{{ route('updatecompany', $company->id) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="company_name">Company Name <span style="color:red">*</span></label>
                                    <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $company->company_name) }}" required placeholder="Enter company name">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="company_code">Company Code <span style="color:red">*</span></label>
                                    <input type="text" name="company_code" id="company_code" class="form-control" value="{{ old('company_code', $company->company_code) }}" required placeholder="Enter company code" minlength="2" maxlength="7" style="text-transform:uppercase;">
                                    <small class="form-text text-muted">Must be between 2 and 7 characters.</small>
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="fund_manager_id">Fund Manager</label>
                                    <select name="fund_manager_id" id="fund_manager_id" class="form-control">
                                        <option value="">-- Select fund manager --</option>
                                        @if(isset($fundManagers) && count($fundManagers))
                                        @foreach($fundManagers as $fm)
                                        <option value="{{ $fm->id }}" {{ (string)old('fund_manager_id', $company->fund_manager_id) === (string)$fm->id ? 'selected' : '' }}>{{ $fm->fund_manager_name }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="address">Address</label>
                                    <input type="text" name="address" class="form-control" value="{{ old('address', $company->address) }}" placeholder="Enter address">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="town">Town</label>
                                    <input type="text" name="town" class="form-control" value="{{ old('town', $company->town) }}" placeholder="Enter town">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="fax">Fax</label>
                                    <input type="text" name="fax" class="form-control" value="{{ old('fax', $company->fax) }}" placeholder="Enter fax">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="tel">Tel</label>
                                    <input type="text" name="tel" class="form-control" value="{{ old('tel', $company->tel) }}" placeholder="Enter telephone">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="tel2">Tel2</label>
                                    <input type="text" name="tel2" class="form-control" value="{{ old('tel2', $company->tel2) }}" placeholder="Enter secondary telephone">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="mobile">Mobile</label>
                                    <input type="text" name="mobile" class="form-control" value="{{ old('mobile', $company->mobile) }}" placeholder="Enter mobile">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="email">Email <span style="color:red">*</span></label>
                                    <input type="email" name="email" class="form-control" value="{{ old('email', $company->email) }}" required placeholder="Enter email">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="website">Website</label>
                                    <input type="text" name="website" class="form-control" value="{{ old('website', $company->website) }}" placeholder="Enter website">
                                </div>
                                {{-- duplicate fund manager select removed; options are server-rendered earlier in the form --}}
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="physical_add">Physical Address</label>
                                    <input type="text" name="physical_add" class="form-control" value="{{ old('physical_add', $company->physical_add) }}" placeholder="Enter physical address">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="reg_cert">Reg Cert</label>
                                    <input type="text" name="reg_cert" class="form-control" value="{{ old('reg_cert', $company->reg_cert) }}" placeholder="Enter registration certificate">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="vat_no">VAT No</label>
                                    <input type="text" name="vat_no" class="form-control" value="{{ old('vat_no', $company->vat_no) }}" placeholder="Enter VAT number">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="pin_no">PIN No <span style="color:red">*</span></label>
                                    <input type="text" name="pin_no" class="form-control" value="{{ old('pin_no', $company->pin_no) }}" required placeholder="Enter PIN number">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="contact_name">Contact Name</label>
                                    <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name', $company->contact_name) }}" placeholder="Enter contact name">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="fin_start">Financial Start</label>
                                    <input type="date" name="fin_start" class="form-control" value="{{ old('fin_start', $company->fin_start ? date('Y-m-d', strtotime($company->fin_start)) : '') }}" placeholder="Select financial start date">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="other_names">Other Names</label>
                                    <input type="text" name="other_names" class="form-control" value="{{ old('other_names', $company->other_names) }}" placeholder="Enter other names">
                                </div>
                            </div>
                            <!-- Logo row moved to its own full-width row -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <label for="logo">Logo</label>
                                    <div class="d-flex gap-3 align-items-start">
                                        <div style="flex:0 0 auto;">
                                            <div id="editLogoPreviewWrapper" class="mb-2">
                                                @if($company->logo)
                                                <img id="editLogoPreviewImg" src="{{ asset('storage/' . $company->logo) }}" alt="Logo" style="max-width:160px;max-height:120px;object-fit:contain;border:1px solid #eaeaea;padding:6px;">
                                                @else
                                                <div id="editLogoPreviewImg" style="width:160px;height:120px;display:flex;align-items:center;justify-content:center;border:1px solid #eaeaea;"> <span class="text-muted small">No file</span></div>
                                                @endif
                                            </div>
                                        </div>
                                        <div style="flex:1 1 auto;">
                                            <input type="file" id="editLogoFileInput" name="logo_file" class="form-control" accept="image/*">
                                            <!-- legacy field preserved -->
                                            <input type="hidden" name="logo" value="{{ old('logo', $company->logo) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @permission('update_companies')
                            <button type="submit" class="btn btn-primary d-flex align-items-center">
                                <span class="material-symbols-outlined">update</span>
                                Update Company</button>
                            @endpermission
                        </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script>
    (function() {
        function readPreviewEdit(input, imgSelector) {
            var $imgWrap = $(imgSelector);
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    // if an <img> exists replace src otherwise replace inner html
                    if ($imgWrap.is('img')) {
                        $imgWrap.attr('src', e.target.result);
                    } else {
                        $imgWrap.html('<img src="' + e.target.result + '" style="max-width:120px;max-height:80px;object-fit:contain;">');
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        $('#editLogoFileInput').on('change', function() {
            readPreviewEdit(this, '#editLogoPreviewImg');
        });
    })();
    // fund manager options are server-rendered in the view
</script>
@endsection