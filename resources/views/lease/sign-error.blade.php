<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signing Link Error</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #f4f6fb; font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; min-height: 100vh; }
        .card { max-width: 540px; margin: 0 auto; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        .icon-circle { width: 72px; height: 72px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 2rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="card p-5 text-center">
        <div class="icon-circle">⚠️</div>
        <h4 class="fw-bold text-danger mb-2">{{ $title ?? 'Invalid Signing Link' }}</h4>
        <p class="text-muted">{{ $message ?? 'This signing link is invalid or has already been used.' }}</p>
        <p class="text-muted small mt-3">If you believe this is an error, please contact your property manager to request a new signing link.</p>
    </div>
</div>
</body>
</html>
