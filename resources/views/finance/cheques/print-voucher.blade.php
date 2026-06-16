<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Voucher - {{ $voucher->voucher_number }}</title>
<style>
    @page { size: A4; margin: 15mm; }
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; color: #1a1a1a; margin: 0; background: #f3f4f6; }
    .doc { max-width: 760px; margin: 24px auto; background: #fff; padding: 28px 34px; box-shadow: 0 1px 4px rgba(0,0,0,0.12); }

    .doc-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1a1a1a; padding-bottom: 12px; margin-bottom: 20px; }
    .company-name { font-size: 1.25rem; font-weight: 700; letter-spacing: 0.3px; }
    .company-sub { font-size: 0.7rem; color: #777; margin-top: 2px; }
    .doc-title { text-align: right; }
    .doc-title h2 { margin: 0; font-size: 1.05rem; letter-spacing: 1.2px; text-transform: uppercase; color: #2b3a4a; }
    .doc-title .vno { font-size: 0.95rem; font-weight: 700; margin-top: 4px; }

    .meta-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px 18px; margin-bottom: 20px; }
    .meta-item .lbl { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 2px; }
    .meta-item .val { font-size: 0.85rem; font-weight: 600; }

    table.lines { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    table.lines th, table.lines td { border: 1px solid #d8dadd; padding: 7px 9px; font-size: 0.78rem; }
    table.lines th { background: #f3f4f6; text-transform: uppercase; font-size: 0.62rem; letter-spacing: 0.4px; color: #555; text-align: left; }
    table.lines td.amt, table.lines th.amt { text-align: right; }
    .total-row td { font-weight: 700; background: #fafafa; }

    .words-box { margin: 16px 0 30px; padding: 10px 14px; border: 1px dashed #aab; font-size: 0.82rem; background: #fafbfc; }
    .words-box .lbl { font-size: 0.6rem; text-transform: uppercase; color: #888; margin-bottom: 3px; letter-spacing: 0.4px; }

    .sign-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 55px; }
    .sign-box { text-align: center; }
    .sign-line { border-top: 1px solid #444; margin-top: 44px; padding-top: 6px; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.4px; color: #666; }
    .sign-name { font-size: 0.8rem; font-weight: 600; margin-top: 2px; min-height: 1em; }

    .no-print { text-align: center; margin: 20px 0; }
    .no-print button { padding: 8px 20px; font-size: 0.85rem; cursor: pointer; border: 1px solid #2b3a4a; background: #2b3a4a; color: #fff; border-radius: 4px; }

    @media print {
        body { background: #fff; }
        .doc { box-shadow: none; margin: 0; max-width: none; }
        .no-print { display: none; }
    }
</style>
</head>
<body onload="window.print();">
<div class="doc">

    <div class="doc-header">
        <div>
            <div class="company-name">{{ config('app.company_name', config('app.name')) }}</div>
            <div class="company-sub">Payment Voucher Document</div>
        </div>
        <div class="doc-title">
            <h2>Payment Voucher</h2>
            <div class="vno">{{ $voucher->voucher_number }}</div>
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-item">
            <div class="lbl">Date</div>
            <div class="val">{{ $voucher->voucher_date?->format('d M Y') ?? '-' }}</div>
        </div>
        <div class="meta-item">
            <div class="lbl">Period</div>
            <div class="val">
                @php $monthName = \Carbon\Carbon::createFromDate(null, $voucher->month, null)->format('F'); @endphp
                {{ $monthName }} {{ $voucher->year }}
            </div>
        </div>
        <div class="meta-item">
            <div class="lbl">Payee</div>
            <div class="val">{{ $voucher->vendor_name ?? '-' }}</div>
        </div>
        <div class="meta-item">
            <div class="lbl">Payee Type</div>
            <div class="val">{{ $voucher->vendor_type ?? '-' }}</div>
        </div>
        <div class="meta-item">
            <div class="lbl">Cheque No.</div>
            <div class="val">{{ $voucher->cheque_number ?? 'Pending' }}</div>
        </div>
        <div class="meta-item">
            <div class="lbl">Status</div>
            <div class="val">{{ $voucher->voucher_status ?? '-' }}</div>
        </div>
        <div class="meta-item" style="grid-column: span 2;">
            <div class="lbl">Description</div>
            <div class="val">{{ $voucher->description ?? '-' }}</div>
        </div>
        <div class="meta-item">
            <div class="lbl">Prepared By</div>
            <div class="val">{{ $voucher->prepared_by ?? '-' }}</div>
        </div>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th>Payee</th>
                <th>Account No.</th>
                <th>Bank</th>
                <th class="amt">Amount</th>
            </tr>
        </thead>
        <tbody>
        @foreach($lines as $i => $line)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $line->entry_description ?? '-' }}</td>
                <td>{{ $line->payee_name ?? '-' }}</td>
                <td>{{ $line->account_number ?? '-' }}</td>
                <td>{{ $line->account_bank ?? '-' }}</td>
                <td class="amt">{{ number_format($line->amount ?? 0, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" style="text-align: right;">Total</td>
                <td class="amt">{{ number_format($voucher->debit_amount ?? 0, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="words-box">
        <div class="lbl">Amount in Words</div>
        {{ $amountInWords }}
    </div>

    <div class="sign-grid">
        <div class="sign-box">
            <div class="sign-line">Prepared By</div>
            <div class="sign-name">{{ $voucher->prepared_by ?? '' }}</div>
        </div>
        <div class="sign-box">
            <div class="sign-line">Checked By</div>
            <div class="sign-name">{{ $voucher->checked_by ?? '' }}</div>
        </div>
        <div class="sign-box">
            <div class="sign-line">Authorized By</div>
            <div class="sign-name">{{ $voucher->authorized_by ?? '' }}</div>
        </div>
    </div>

</div>

<div class="no-print">
    <button onclick="window.print()">Print</button>
</div>
</body>
</html>