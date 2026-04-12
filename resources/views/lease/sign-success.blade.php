<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lease Agreement Signed</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #f4f6fb; font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; min-height: 100vh; }
        .card { max-width: 540px; margin: 0 auto; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        .icon-circle { width: 72px; height: 72px; background: #d1fae5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 2rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="card p-5 text-center">
        <div class="icon-circle">✅</div>
        <h4 class="fw-bold text-success mb-2">Lease Agreement Signed!</h4>
        <p class="text-muted">Thank you, <strong>{{ $signerName }}</strong>.</p>
        <p class="text-muted">Your signature has been recorded for lease <strong>{{ $refNumber }}</strong>.</p>
        <p class="text-muted small mt-3">A copy of the signed lease agreement will be made available by your property manager. You may close this window.</p>
        <hr>
        <p class="text-muted small mb-0">Signed on: {{ now()->format('d M Y, H:i') }}</p>
    </div>
</div>
</body>
</html>
