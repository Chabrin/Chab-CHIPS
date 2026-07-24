<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Service - {{ $termination->staff->full_names ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 40px; }
        h2 { text-align: center; font-size: 18px; margin-bottom: 30px; color: #2c3e50; text-transform: uppercase; letter-spacing: 1px; }
        .content { line-height: 1.8; font-size: 13px; }
        .meta-grid { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .meta-grid td { padding: 6px 8px; font-size: 12px; }
        .meta-grid .label { color: #888; width: 160px; }
        .meta-grid .val { font-weight: bold; }
        .footer { margin-top: 60px; font-size: 11px; }
        .signature-line { margin-top: 50px; border-top: 1px solid #333; width: 250px; padding-top: 4px; font-size: 10px; color: #666; }
    </style>
</head>
<body>

<h2>Certificate of Service</h2>

<div class="content">
    This is to certify that:
</div>

<table class="meta-grid">
    <tr>
        <td class="label">Employee Name:</td>
        <td class="val">{{ $termination->staff->full_names ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Staff Number:</td>
        <td class="val">{{ $termination->staff->staff_number ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Position(s) Held:</td>
        <td class="val">{{ $termination->contract->position->position_name ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Date of Engagement:</td>
        <td class="val">{{ $termination->contract && $termination->contract->date_start ? \Carbon\Carbon::parse($termination->contract->date_start)->format('d M Y') : '—' }}</td>
    </tr>
    <tr>
        <td class="label">Date of Separation:</td>
        <td class="val">{{ $termination->date_time ? \Carbon\Carbon::parse($termination->date_time)->format('d M Y') : '—' }}</td>
    </tr>
    <tr>
        <td class="label">Years of Service:</td>
        <td class="val">{{ $termination->years_served ?? '—' }}</td>
    </tr>
</table>

<div class="content">
    was engaged in the service of this organisation for the period stated above. This certificate
    is issued on request to confirm the employee's period of service.
</div>

<div class="signature-line">Authorised Signature</div>

<div class="footer">Issued on {{ now()->format('d M Y') }} by the Chab-CHIPS HR system.</div>

</body>
</html>
