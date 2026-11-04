<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Voucher</title>
<style>
    @page { size: A4 landscape; margin: 6mm; }
    * { box-sizing: border-box; }
    body { 
        font-family: Arial, sans-serif; 
        font-size: 10.5px; 
        color: #000; 
        margin: 0; 
        padding: 0;
    }
    .doc { 
        width: 100%; 
        max-width: 970px; 
        margin: 10px auto; 
        padding: 12px 28px 20px;
        position: relative;
        background: white;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .logo-icon {
        width: 58px;
        height: 52px;
        background: #1e3a8a;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: bold;
    }
    .company-name {
        font-size: 14.5px;
        font-weight: 800;
        color: #1e3a8a;
        line-height: 1.05;
    }
    .tagline {
        font-size: 8.5px;
        color: #333;
    }

    .address {
        text-align: right;
        font-size: 9px;
        line-height: 1.3;
    }

    .header-right {
        text-align: right;
    }

    .voucher-no {
        font-size: 33px;
        font-weight: lighter;
        margin-bottom: 4px;
    }

    .title {
        text-align: center;
        font-size: 15.5px;
        font-weight: bold;
        text-transform: uppercase;
        margin: 12px 0 2px;
        letter-spacing: 1px;
    }

    .voucher-number {
        text-align: center;
        font-size: 12px;
        font-weight: 700;
        color: #333;
        margin-bottom: 10px;
    }

    .name-row {
        border: 1px solid #000;
        padding: 6px 10px;
        margin-bottom: 12px;
        font-size: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }
    th, td {
        border: 1px solid #000;
        padding: 4px 5px;
        vertical-align: top;
    }
    th {
        background: #f0f0f0;
        font-weight: bold;
        font-size: 9px;
    }
    .desc { width: 52%; }
    .amt { text-align: right; font-weight: 600; width: 13%; }

    .total {
        font-weight: bold;
        background: #f8f8f8;
    }

    .words {
        border: 1px solid #000;
        padding: 7px 10px;
        margin: 12px 0;
        font-size: 11px;
    }

    .signatures {
        display: flex;
        gap: 40px;
        margin-top: 15px;
    }
    .left-sig { flex: 1; }
    .right-sig { flex: 1; }

    .sig-label {
        font-size: 9.5px;
        margin-bottom: 2px;
    }
    .sig-line {
        border-bottom: 1px solid #000;
        height: 24px;
        margin-bottom: 12px;
    }

    .stamp {
        position: absolute;
        top: 195px;
        right: 85px;
        transform: rotate(-15deg);
        color: #c00;
        border: 3px solid #c00;
        padding: 6px 14px;
        font-size: 17px;
        font-weight: bold;
        border-radius: 50%;
        opacity: 0.9;
    }
</style>
</head>
<body onload="window.print();">

<div class="doc">

    <div class="header">
        <div class="logo">
            <div class="logo-icon">CA</div>
            <div>
                <div class="company-name">CHABRIN<br>AGENCIES LTD</div>
                <div class="tagline">Registered Property Managers & Consultants</div>
            </div>
        </div>

        <div class="header-right">
            <span><div class="voucher-no">{{ $voucher->id }}</div>No. </span>
            <div class="address">
                MACHO PLAZA, LACHI ROAD<br>
                SYFREBOOK, NAIROBI<br>
                P.O. BOX 1663-00100<br>
                TEL: 0723 164366<br>
                EMAIL: info@chabrin.co.ke
            </div>
        </div>
    </div>

    <div class="title">PAYMENT VOUCHER</div>
    <div class="voucher-number">{{ $voucher->voucher_number }}</div>

    <div class="name-row">
        <span><strong>NAME:</strong> {{ $voucher->vendor_name }}</span>
        <span><strong>DATE:</strong> {{ now()->format('d M Y') }}</span>
    </div>

    <!-- Dynamic Table -->
    <table>
        <thead>
            <tr>
                <th style="border-right:none;"></th>
                <th style="border-right:none;border-left:none;">Bank</th>
                <th style="border-left:none;">CHQ No.</th>
                <th class="amt">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $key => $line)
            <tr @if($loop->last) style="height:200px;" @endif>
                <td style="border-right:none; text-align: center;">{{ $line->entry_description ?? '-' }}</td>
                <td style="border-right:none;border-left:none; text-align: center;">{{ $line->account_bank ?: '-' }}</td>
                <td style="border-left:none; text-align: center;">{{ $line->cheque_number ?: '-' }}</td>
                <td class="amt">{{ number_format($line->amount ?? 0, 2) }}</td>
            </tr>
            @endforeach
            <tr>
                {{-- Amount in words on the left, no borders --}}
                <td colspan="2" style="font-weight:600; text-transform:uppercase; border-left:none; border-bottom:none;">
                    {{ strtoupper($amountInWords) }}
                </td>

                {{-- Grand Total label --}}
                <td style="font-weight:600; text-align:right; border-top:1px solid #000;">
                    GRAND TOTAL
                </td>

                {{-- Grand Total amount --}}
                <td class="amt" style="font-weight:700; border-top:1px solid #000;">
                    {{ number_format($lines->sum('amount'), 2) }}
                </td>
            </tr>

        </tbody>
        
    </table>

    <div class="signatures">
        <div class="left-sig">
            <div>
                <div class="sig-label">Prepared By:</div>
                <div class="sig-line"></div>
            </div>
            <div>
                <div class="sig-label">Checked By:</div>
                <div class="sig-line"></div>
            </div>
            <div>
                <div class="sig-label">Authorized By:</div>
                <div class="sig-line"></div>
            </div>
        </div>

        <div class="right-sig">
            <div>
                <div class="sig-label">Received By:</div>
                <div class="sig-line"></div>
            </div>
            <div>
                <div class="sig-label">Signature:</div>
                <div class="sig-line"></div>
            </div>
            <div>
                <div class="sig-label">Date:</div>
                <div class="sig-line"></div>
            </div>
        </div>
    </div>

</div>

</body>
</html>