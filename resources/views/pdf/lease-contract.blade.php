<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Lease Agreement – {{ $lease->ref_number ?? '' }}</title>
    <style>
        @page {
            margin: 22mm 20mm 22mm 20mm;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11pt;
            color: #1a1a1a;
            line-height: 1.6;
        }
        .page-header {
            text-align: center;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .page-header h1 {
            font-size: 16pt;
            color: #2c3e50;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .page-header p {
            font-size: 9pt;
            color: #666;
            margin-top: 4px;
        }
        .ref-badge {
            display: inline-block;
            background: #2c3e50;
            color: #fff;
            padding: 3px 12px;
            font-size: 9pt;
            border-radius: 3px;
            margin-top: 6px;
        }
        .body-content {
            margin-bottom: 30px;
        }
        .signature-block {
            margin-top: 40px;
            border-top: 2px solid #2c3e50;
            padding-top: 20px;
        }
        .signature-block h3 {
            font-size: 12pt;
            color: #2c3e50;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .sig-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sig-table td {
            width: 50%;
            padding: 8px 12px;
            vertical-align: top;
        }
        .sig-line {
            border-bottom: 1px solid #333;
            min-height: 24px;
            margin-top: 6px;
            padding-bottom: 4px;
            font-weight: bold;
            color: #1a1a1a;
        }
        .sig-label {
            font-size: 9pt;
            color: #666;
            margin-top: 4px;
        }
        .unsigned-notice {
            text-align: center;
            color: #888;
            font-style: italic;
            font-size: 9pt;
            margin-top: 10px;
        }
        .page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #aaa;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }
    </style>
</head>
<body>

    <div class="page-header">
        <h1>Lease Agreement</h1>
        <p>This document is a legally binding lease agreement.</p>
        <div class="ref-badge">Ref: {{ $lease->ref_number ?? 'PENDING' }}</div>
    </div>

    <div class="body-content">
        {!! $bodyHtml !!}
    </div>

    <!-- Signature Block -->
    <div class="signature-block">
        <h3>Signatures</h3>
        <table class="sig-table">
            <tr>
                <!-- Tenant Signature -->
                <td>
                    <div class="sig-label">Tenant / Lessee</div>
                    <div class="sig-line">{{ $signerName ?: '' }}</div>
                    <div class="sig-label">Full Name</div>

                    <div class="sig-line" style="margin-top:16px;">{{ $signedDate ?: '' }}</div>
                    <div class="sig-label">Date Signed</div>

                    @if(!$signerName)
                        <p class="unsigned-notice" style="margin-top:8px;">— Pending tenant signature —</p>
                    @endif
                </td>
                <!-- Landlord / Agent Signature -->
                <td>
                    <div class="sig-label">Authorised Agent / Landlord</div>
                    <div class="sig-line"></div>
                    <div class="sig-label">Full Name &amp; Title</div>

                    <div class="sig-line" style="margin-top:16px;"></div>
                    <div class="sig-label">Date Signed</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="page-footer">
        Lease Agreement · {{ $lease->ref_number ?? '' }} · Generated {{ now()->format('d M Y') }}
    </div>

</body>
</html>
