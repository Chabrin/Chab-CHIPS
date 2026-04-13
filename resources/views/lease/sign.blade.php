<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Lease Agreement – {{ $lease->ref_number }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #f4f6fb; font-family: 'Segoe UI', sans-serif; }
        .sign-card { max-width: 780px; margin: 40px auto; background: #fff; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,.08); overflow: hidden; }
        .sign-header { background: #2c3e50; color: #fff; padding: 24px 32px; }
        .sign-header h4 { margin: 0; font-weight: 700; letter-spacing: .5px; }
        .sign-header p { margin: 4px 0 0; opacity: .75; font-size: .9rem; }
        .sign-body { padding: 32px; }
        .meta-row { display: flex; flex-wrap: wrap; gap: 8px 24px; margin-bottom: 24px; font-size: .9rem; }
        .meta-item strong { color: #2c3e50; }
        .pdf-frame { width: 100%; height: 480px; border: 1px solid #dee2e6; border-radius: 6px; }
        .form-section { border-top: 1px solid #dee2e6; margin-top: 24px; padding-top: 24px; }
        .btn-sign { background: #2c3e50; color: #fff; border: none; padding: 10px 32px; font-size: 1rem; border-radius: 6px; }
        .btn-sign:hover { background: #1a252f; color: #fff; }
    </style>
</head>
<body>

<div class="sign-card">
    <div class="sign-header">
        <h4>Lease Agreement — Digital Signing</h4>
        <p>Please review your lease agreement below and provide your digital signature to confirm acceptance.</p>
    </div>

    <div class="sign-body">
        <!-- Lease meta info -->
        <div class="meta-row">
            <div class="meta-item"><strong>Reference:</strong> {{ $lease->ref_number }}</div>
            <div class="meta-item"><strong>Tenant:</strong> {{ $lease->tenant->names ?? 'N/A' }}</div>
            <div class="meta-item"><strong>Property:</strong> {{ $lease->property->property_name ?? 'N/A' }}</div>
            <div class="meta-item"><strong>Unit:</strong> {{ $lease->unit->unit_code ?? 'N/A' }}</div>
            <div class="meta-item"><strong>Period:</strong> {{ $lease->date_start ? $lease->date_start->format('d M Y') : '' }} – {{ $lease->date_end ? $lease->date_end->format('d M Y') : '' }}</div>
        </div>

        <!-- PDF embed -->
        @if($lease->unsigned_pdf_path)
        <p class="text-muted small mb-2">Scroll through the full document before signing:</p>
        <iframe class="pdf-frame" src="{{ asset('storage/' . $lease->unsigned_pdf_path) }}#toolbar=1&navpanes=0" title="Lease Agreement PDF"></iframe>
        @else
        <div class="alert alert-warning">The lease contract document is not yet available. Please contact your property manager.</div>
        @endif

        <!-- Signing Form -->
        <div class="form-section">
            <h6 class="fw-bold mb-3">Add Your Digital Signature</h6>

            @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
            </div>
            @endif

            <form method="POST" action="{{ route('lease.contract.sign.submit', ['token' => $token]) }}">
                @csrf

                <div class="mb-3">
                    <label for="signer_name" class="form-label fw-semibold">Your Full Legal Name <span class="text-danger">*</span></label>
                    <input type="text" id="signer_name" name="signer_name" class="form-control form-control-lg @error('signer_name') is-invalid @enderror"
                           placeholder="Type your full name exactly as it appears on your ID"
                           value="{{ old('signer_name') }}" required maxlength="200" autocomplete="name">
                    @error('signer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">By typing your name, you are digitally signing this lease agreement.</small>
                </div>

                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="consent" name="consent" required>
                        <label class="form-check-label" for="consent">
                            I confirm that I have read and understood the full lease agreement above, and I agree to be bound by its terms and conditions.
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-sign">
                    ✍ Sign &amp; Submit
                </button>

                <p class="text-muted small mt-3">
                    This digital signature is legally binding. The signed document will be emailed to your property manager.
                    Signing date: <strong>{{ now()->format('d M Y') }}</strong>.
                </p>
            </form>
        </div>
    </div>
</div>

</body>
</html>
