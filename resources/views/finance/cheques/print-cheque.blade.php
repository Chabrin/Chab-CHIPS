<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cheque - {{ $voucher->voucher_number }}</title>
<style>
    @page { size: A4; margin: 10mm; }
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; color: #1a1a1a; background: #f3f4f6; }
    .wrap { display: flex; justify-content: center; padding-top: 40px; }
    .wrap + .wrap { page-break-before: always; }

    .cheque {
        width: 180mm;
        height: 85mm;
        border: 1.5px solid #2b3a4a;
        border-radius: 6px;
        position: relative;
        padding: 10mm 12mm;
        background: #fff
            repeating-linear-gradient(135deg, rgba(43,58,74,0.035) 0px, rgba(43,58,74,0.035) 2px, transparent 2px, transparent 14px);
    }

    .bank-row { display: flex; justify-content: space-between; align-items: flex-start; }
    .bank-name { font-size: 1.05rem; font-weight: 700; letter-spacing: 0.3px; }
    .bank-sub { font-size: 0.65rem; color: #666; margin-top: 2px; }

    .date-box { text-align: right; }
    .date-lbl { font-size: 0.6rem; text-transform: uppercase; color: #777; letter-spacing: 0.4px; }
    .date-cells { display: flex; gap: 3px; margin-top: 3px; justify-content: flex-end; }
    .date-cells span { display: inline-block; width: 14px; height: 18px; border: 1px solid #888; text-align: center; font-size: 0.75rem; line-height: 18px; background: #fff; }

    .amt-box-row { position: absolute; top: 10mm; right: 12mm; text-align: right; }
    .amt-box { border: 1.5px solid #2b3a4a; padding: 4px 10px; font-weight: 700; font-size: 1rem; border-radius: 3px; margin-top: 30px; background: #fff; }

    .pay-row { margin-top: 30px; display: flex; align-items: baseline; gap: 8px; }
    .pay-row .lbl { font-size: 0.7rem; color: #555; white-space: nowrap; }
    .pay-row .line { flex: 1; border-bottom: 1px solid #444; padding-bottom: 2px; font-weight: 600; font-size: 0.9rem; }

    .words-row { margin-top: 14px; display: flex; align-items: baseline; gap: 8px; }
    .words-row .lbl { font-size: 0.7rem; color: #555; white-space: nowrap; }
    .words-row .line { flex: 1; border-bottom: 1px solid #444; padding-bottom: 2px; font-size: 0.82rem; }

    .bottom-row { position: absolute; bottom: 9mm; left: 12mm; right: 12mm; display: flex; justify-content: space-between; align-items: flex-end; }
    .micr { font-family: 'Courier New', monospace; letter-spacing: 2px; font-size: 0.85rem; color: #2b3a4a; }
    .sig-box { text-align: center; }
    .sig-line { border-top: 1px solid #444; width: 140px; padding-top: 4px; font-size: 0.62rem; color: #666; text-transform: uppercase; letter-spacing: 0.4px; }

    .no-print { text-align: center; margin-top: 24px; }
    .no-print button { padding: 8px 20px; font-size: 0.85rem; cursor: pointer; border: 1px solid #2b3a4a; background: #2b3a4a; color: #fff; border-radius: 4px; }

    @media print {
        body { background: #fff; }
        .wrap { padding-top: 0; }
        .no-print { display: none; }
    }
</style>
</head>
<body onload="window.print();">

@foreach($cheques as $entry)
    @php $line = $entry['line']; @endphp
    <div class="wrap">
        <div class="cheque">

            <div class="bank-row">
                <div>
                    <div class="bank-name">{{ config('app.company_name', config('app.name')) }}</div>
                    <div class="bank-sub">Account Payee Only &bull; Not Transferable</div>
                </div>
                <div class="date-box">
                    <div class="date-lbl">Date</div>
                    @php
                        $d = $voucher->voucher_date ?? now();
                        $dateDigits = $d->format('dmY');
                    @endphp
                    <div class="date-cells">
                        @foreach(str_split($dateDigits) as $digit)
                            <span>{{ $digit }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="amt-box-row">
                <div class="date-lbl">Kshs.</div>
                <div class="amt-box">{{ number_format($line->amount ?? 0, 2) }}</div>
            </div>

            <div class="pay-row">
                <div class="lbl">Pay</div>
                <div class="line">{{ $line->payee_name ?? $voucher->vendor_name ?? '-' }}</div>
                <div class="lbl">or order</div>
            </div>

            <div class="words-row">
                <div class="lbl">The Sum of</div>
                <div class="line">{{ $entry['amount_in_words'] }}</div>
            </div>

            <div class="bottom-row">
                <div class="micr">{{ $line->cheque_number ?? '000000' }} &nbsp;&nbsp; {{ $voucher->voucher_number }}</div>
                <div class="sig-box">
                    <div class="sig-line">Authorized Signature</div>
                </div>
            </div>

        </div>
    </div>
@endforeach

<div class="no-print">
    <button onclick="window.print()">Print {{ $cheques->count() > 1 ? 'Cheques' : 'Cheque' }}</button>
</div>
</body>
</html>