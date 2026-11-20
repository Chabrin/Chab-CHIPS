@php
$err = fn ($field) => $errors->has($field) ? '<small class="error">' . e($errors->first($field)) . '</small>' : '';
$sel = fn ($field, $value) => (string) old($field) === (string) $value ? 'selected' : '';
@endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Staff onboarding</title><style>body{font:16px Arial,sans-serif;background:#f4f6f8;color:#243447}.container{max-width:900px;margin:36px auto;padding:0 16px}.card{background:white;border:1px solid #ddd;border-radius:8px;padding:24px}.row{display:flex;flex-wrap:wrap;gap:14px}.col-md-3{width:23%}.col-md-4{width:31%}.col-md-6{width:48%}.col-12{width:100%}.form-label{display:block;margin:0 0 6px;font-weight:600}.form-control,.form-select{box-sizing:border-box;width:100%;padding:9px;border:1px solid #b8c2cc;border-radius:4px}.mt-4{margin-top:24px}.alert{padding:12px;background:#e8f1fa;border-radius:4px}.alert-danger{background:#fdecea;color:#8a1c1c}.error{display:block;color:#b42318;margin-top:4px}button{padding:10px 16px;background:#1769aa;color:#fff;border:0;border-radius:4px}@media(max-width:700px){.col-md-3,.col-md-4,.col-md-6{width:100%}}</style></head>
<body class="bg-light"><main class="container py-5" style="max-width:900px"><div class="card"><div class="card-body p-4">
    <h2>Staff onboarding</h2><p class="text-muted">This form is private and will be reviewed by HR. Your job and company details will be added by HR.</p>
    @if($errors->any())<div class="alert alert-danger">Please correct the errors shown below and resubmit. For security, any files you selected must be chosen again.</div>@endif
    <form method="POST" enctype="multipart/form-data" data-file-slots action="{{ route('staff.onboarding.submit', ['token'=>$token]) }}">@csrf
    <h5>Personal information</h5><div class="row g-3">
        <div class="col-md-6"><label class="form-label">Full names</label><input name="full_names" class="form-control" value="{{ old('full_names') }}" required>{!! $err('full_names') !!}</div>
        <div class="col-md-3"><label class="form-label">Gender</label><select name="gender" class="form-select" required><option value="">Select</option>@foreach(['Male','Female'] as $o)<option {{ $sel('gender',$o) }}>{{ $o }}</option>@endforeach</select>{!! $err('gender') !!}</div>
        <div class="col-md-3"><label class="form-label">Date of birth</label><input type="date" name="birth_date" value="{{ old('birth_date') }}" max="{{ now()->subDay()->toDateString() }}" class="form-control" required>{!! $err('birth_date') !!}</div>
        <div class="col-md-4"><label class="form-label">Birth place</label><input name="birth_place" value="{{ old('birth_place') }}" class="form-control">{!! $err('birth_place') !!}</div>
        <div class="col-md-4"><label class="form-label" for="national_id">National ID</label><input id="national_id" name="national_id" value="{{ old('national_id') }}" class="form-control" required>{!! $err('national_id') !!}</div>
        <div class="col-md-4"><label class="form-label" for="passport">Passport (if applicable)</label><input id="passport" name="passport" value="{{ old('passport') }}" class="form-control">{!! $err('passport') !!}</div>
        <div class="col-md-4"><label class="form-label">Marital status</label><select name="marital" class="form-select" required><option value="">Select</option>@foreach(['Single','Married','Divorced','Widowed'] as $o)<option {{ $sel('marital',$o) }}>{{ $o }}</option>@endforeach</select>{!! $err('marital') !!}</div>
        <div class="col-md-4"><label class="form-label">Spouse name (if applicable)</label><input name="spouse_name" value="{{ old('spouse_name') }}" class="form-control">{!! $err('spouse_name') !!}</div>
        @include('staff.partials.citizenship-fields', ['nationality' => old('nationality'), 'residency' => old('residency_status'), 'permitNumber' => old('work_permit_number'), 'permitExpiry' => old('work_permit_expiry'), 'requiredAttribute' => 'required'])
        <div class="col-md-4"><label class="form-label">Disability</label><select name="disability" class="form-select" required><option value="">Select</option>@foreach(['YES','NO'] as $o)<option {{ $sel('disability',$o) }}>{{ $o }}</option>@endforeach</select>{!! $err('disability') !!}</div>
    </div>
    <h5 class="mt-4">Contact information</h5><div class="row g-3">
        <div class="col-md-4"><label class="form-label">Mobile number</label><input type="tel" name="mobile_number" value="{{ old('mobile_number') }}" class="form-control" placeholder="+254 7XX XXX XXX" autocomplete="tel" data-mobile-format required><small>Start with the country code, like +254.</small>{!! $err('mobile_number') !!}</div>
        <div class="col-md-4"><label class="form-label">Personal email</label><input type="email" name="personal_email" value="{{ old('personal_email', $invitation->recipient_email) }}" class="form-control" required>{!! $err('personal_email') !!}</div>
        <div class="col-md-4"><label class="form-label">Address</label><input name="address" value="{{ old('address') }}" class="form-control">{!! $err('address') !!}</div>
        <div class="col-md-4"><label class="form-label">District</label><input name="district" value="{{ old('district') }}" class="form-control">{!! $err('district') !!}</div>
        <div class="col-md-4"><label class="form-label">Location</label><input name="location" value="{{ old('location') }}" class="form-control">{!! $err('location') !!}</div>
        <div class="col-md-4"><label class="form-label">Sub location</label><input name="sub_location" value="{{ old('sub_location') }}" class="form-control">{!! $err('sub_location') !!}</div>
        <div class="col-md-6"><label class="form-label">Next of kin names</label><input id="kins_names" name="kins_names" value="{{ old('kins_names') }}" class="form-control">{!! $err('kins_names') !!}</div>
        <div class="col-md-6"><label class="form-label">Relationship to you</label><select id="kins_relationship" name="kins_relationship" class="form-select"><option value="">Select</option>@foreach(\App\Models\Staff::KIN_RELATIONSHIPS as $o)<option {{ $sel('kins_relationship',$o) }}>{{ $o }}</option>@endforeach</select><small>Needed once you enter a next of kin name.</small>{!! $err('kins_relationship') !!}</div>
        <div class="col-md-4"><label class="form-label">Next of kin Mobile number</label><input type="tel" name="mobile_number" value="{{ old('mobile_number') }}" class="form-control" placeholder="+254 7XX XXX XXX" autocomplete="tel" data-mobile-format required><small>Start with the country code, like +254.</small>{!! $err('mobile_number') !!}</div>
    </div>
    <h5 class="mt-4">Staff photo</h5><div class="row g-3"><div class="col-md-6"><label class="form-label">Profile photo (JPG/PNG, max 2 MB)</label><input type="file" name="staff_photo" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png"><small>Optional. HR will add this to your staff record.</small>{!! $err('staff_photo') !!}{!! $err('photo') !!}</div></div>
    <h5 class="mt-4">Signature</h5><p style="margin-top:0;color:#5c6b7a">Optional. Draw your signature or upload a clear image of it.</p>
    @include('staff.partials.signature-input')
    <h5 class="mt-4">Banking &amp; statutory details</h5><div class="row g-3">
        <div class="col-md-4"><label class="form-label">Bank</label><select name="bank_name" class="form-select" required><option value="">Select</option>@foreach($banks as $bank)<option value="{{ $bank->id }}" {{ $sel('bank_name',$bank->id) }}>{{ $bank->bank_name }}</option>@endforeach</select>{!! $err('bank_name') !!}</div>
        <div class="col-md-4"><label class="form-label">Account name</label><input name="account_name" value="{{ old('account_name') }}" class="form-control" required>{!! $err('account_name') !!}</div>
        <div class="col-md-4"><label class="form-label">Account number</label><input name="account_number" value="{{ old('account_number') }}" class="form-control" required>{!! $err('account_number') !!}</div>
        <div class="col-md-4"><label class="form-label">KRA PIN</label><input name="pin_number" value="{{ old('pin_number') }}" class="form-control" required>{!! $err('pin_number') !!}</div>
        <div class="col-md-4"><label class="form-label">SHA number</label><input name="nhif_number" value="{{ old('nhif_number') }}" class="form-control" required>{!! $err('nhif_number') !!}</div>
        <div class="col-md-4"><label class="form-label">NSSF number</label><input name="nssf_number" value="{{ old('nssf_number') }}" class="form-control" required>{!! $err('nssf_number') !!}</div>
    </div>
    <h5 class="mt-4">Required documents</h5><div class="row g-3">
        <div class="col-md-6"><label class="form-label">National ID / Passport copy (PDF/JPG/PNG, max 5 MB)</label><input type="file" name="documents[national_id]" class="form-control" accept=".pdf,.png,.jpg,.jpeg" required>{!! $err('documents.national_id') !!}</div>
        <div class="col-md-6"><label class="form-label">KRA PIN certificate (PDF/JPG/PNG, max 5 MB)</label><input type="file" name="documents[kra_pin]" class="form-control" accept=".pdf,.png,.jpg,.jpeg" required>{!! $err('documents.kra_pin') !!}</div>
        <div class="col-md-6"><label class="form-label">Signed employment contract (optional PDF)</label><input type="file" name="documents[contract]" class="form-control" accept=".pdf">{!! $err('documents.contract') !!}</div>
        <div class="col-md-6"><label class="form-label">CV (optional)</label><input type="file" name="documents[cv]" class="form-control" accept=".pdf,.doc,.docx">{!! $err('documents.cv') !!}</div>
        <div class="col-md-6"><label class="form-label">Academic certificates (optional)</label><input type="file" name="documents[academic]" class="form-control" accept=".pdf,.png,.jpg,.jpeg">{!! $err('documents.academic') !!}</div>
    </div>
    <h5 class="mt-4">HELB</h5><div class="row g-3">
        <div class="col-md-6"><label class="form-label">Do you have a HELB loan (are you a HELB beneficiary)?</label><select name="is_helb_beneficiary" class="form-select" required><option value="">Select</option>@foreach(['YES' => 'Yes', 'NO' => 'No'] as $v => $l)<option value="{{ $v }}" {{ $sel('is_helb_beneficiary', $v) }}>{{ $l }}</option>@endforeach</select>{!! $err('is_helb_beneficiary') !!}</div>
        <div class="col-md-6"><label class="form-label">HELB compliance certificate (PDF/JPG/PNG, max 5 MB)</label><input type="file" name="documents[helb]" class="form-control" accept=".pdf,.png,.jpg,.jpeg">{!! $err('documents.helb') !!}<small class="text-muted">If you have it. HR will ask for it if not.</small></div>
    </div>
    <h5 class="mt-4">Data protection notice</h5>
    @include('staff.partials.data-notice')
    <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" id="data_notice_agreed" name="data_notice_agreed" value="1" required>
        <label class="form-check-label" for="data_notice_agreed">I have read the notice above and I agree.</label>
        {!! $err('data_notice_agreed') !!}
    </div>
    <div class="alert alert-info mt-4">Submitting this form shares your information with the company HR team to set up your employment and payroll records. Documents are stored privately. Do not use a shared device to complete it.</div>
    <button class="btn btn-primary" type="submit">Submit to HR</button></form>
</div></div></main>
<script src="{{ asset('assets/js/staff-contact-format.js') }}?v={{ filemtime(public_path('assets/js/staff-contact-format.js')) }}"></script>
<script src="{{ asset('assets/js/file-slot.js') }}?v={{ filemtime(public_path('assets/js/file-slot.js')) }}"></script>
<script src="{{ asset('assets/js/staff-citizenship.js') }}?v={{ filemtime(public_path('assets/js/staff-citizenship.js')) }}"></script>
<script>
// Choosing the relationship is checked here too, because a rejected form clears the files the staff member picked.
(function () {
    var name = document.getElementById('kins_names'), relationship = document.getElementById('kins_relationship');
    function sync() { relationship.required = name.value.trim() !== ''; }
    name.addEventListener('input', sync);
    sync();
})();
</script></body></html>
