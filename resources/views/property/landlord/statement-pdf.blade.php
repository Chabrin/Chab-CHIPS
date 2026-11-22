<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Account Statement - {{ $account['reference_number'] }}</title>
    <style>
        @page {
            margin: 30px 34px 60px 34px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8pt;
            color: #1c2433;
            margin: 0;
            padding: 0;
        }

        table {
            border-collapse: collapse;
        }

        /* ── Letterhead ─────────────────────────── */
        .head {
            width: 100%;
        }

        .head td {
            vertical-align: top;
            padding: 0;
        }

        .head .left {
            width: 55%;
        }

        .head .right {
            width: 45%;
            text-align: right;
        }

        .logo {
            height: 44px;
        }

        .company-line {
            margin-top: 5px;
            font-size: 7pt;
            line-height: 1.5;
            color: #5b6475;
        }

        .doc-title {
            font-size: 18pt;
            font-weight: bold;
            letter-spacing: 1.5px;
            color: #0b1b63;
        }

        .doc-period {
            margin-top: 4px;
            font-size: 9pt;
            font-weight: bold;
            color: #12b5c0;
        }

        .doc-meta {
            margin-top: 3px;
            font-size: 7pt;
            color: #5b6475;
        }

        .rule-navy {
            height: 3px;
            background-color: #0b1b63;
            margin-top: 12px;
        }

        .rule-teal {
            height: 1px;
            background-color: #12b5c0;
            margin-top: 2px;
            margin-bottom: 14px;
        }

        /* ── Landlord + summary cards ───────────── */
        .cards {
            width: 100%;
            margin-bottom: 14px;
        }

        .cards td {
            vertical-align: top;
            padding: 0;
        }

        .cards .col-landlord {
            width: 52%;
            padding-right: 12px;
        }

        .cards .col-summary {
            width: 48%;
        }

        .card {
            background-color: #f3f6fb;
            border: 1px solid #dfe5ef;
            border-left: 3px solid #12b5c0;
            padding: 10px 12px;
        }

        .card-label {
            font-size: 6.5pt;
            font-weight: bold;
            letter-spacing: 1.2px;
            color: #12b5c0;
            margin-bottom: 5px;
        }

        .landlord-name {
            font-size: 12pt;
            font-weight: bold;
            color: #0b1b63;
            margin-bottom: 6px;
        }

        .kv {
            width: 100%;
        }

        .kv td {
            padding: 1.5px 0;
            font-size: 7.5pt;
        }

        .kv .k {
            width: 28%;
            color: #5b6475;
        }

        .summary {
            width: 100%;
        }

        .summary td {
            padding: 2.5px 0;
            font-size: 8pt;
        }

        .summary .k {
            color: #5b6475;
        }

        .summary .v {
            text-align: right;
            font-weight: bold;
        }

        .closing td {
            border-top: 1px solid #c9d2e3;
            padding-top: 7px;
        }

        .closing .k {
            font-weight: bold;
            color: #0b1b63;
        }

        .closing .v {
            font-size: 13pt;
            color: #0b1b63;
        }

        .closing-note {
            text-align: right;
            font-size: 6.5pt;
            color: #5b6475;
            padding-top: 0;
        }

        /* ── Transactions ───────────────────────── */
        .txn {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
        }

        .txn thead th {
            background-color: #0b1b63;
            color: #ffffff;
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            padding: 6px 6px;
            text-align: left;
            border-right: 1px solid #0b1b63;
        }

        .txn thead th.r {
            text-align: right;
        }

        .txn td {
            padding: 5px 6px;
            border-bottom: 1px solid #e6ebf3;
            font-size: 7.5pt;
            vertical-align: top;
        }

        .txn td.r {
            text-align: right;
            white-space: nowrap;
        }

        .txn tr {
            page-break-inside: avoid;
        }

        .txn tr.alt td {
            background-color: #f8fafd;
            border-right: 1px solid #f8fafd;
        }

        .txn tr.bf td {
            background-color: #e8f7f8;
            font-weight: bold;
            color: #0b1b63;
            border-bottom: 1px solid #b9e3e7;
            border-right: 1px solid #e8f7f8;
        }

        .txn tr.totals td {
            background-color: #eef1f8;
            font-weight: bold;
            border-top: 1.5px solid #0b1b63;
            border-bottom: none;
            border-right: 1px solid #eef1f8;
        }

        .txn tr.final td {
            background-color: #0b1b63;
            color: #ffffff;
            font-weight: bold;
            border-bottom: none;
            border-right: 1px solid #0b1b63;
            padding: 7px 6px;
        }

        .ref {
            color: #5b6475;
            font-size: 7pt;
        }

        .dr {
            color: #a8232c;
        }

        .cr {
            color: #137a46;
        }

        .txn tr.nc td {
            color: #9aa3b2;
        }

        .nc-tag {
            font-size: 6.5pt;
            color: #8a93a4;
        }

        .neg {
            color: #a8232c;
        }

        .muted {
            color: #8a93a4;
        }

        .empty {
            text-align: center;
            color: #8a93a4;
            padding: 18px 6px;
        }

        .note {
            margin-top: 10px;
            font-size: 6.5pt;
            color: #6b7486;
            line-height: 1.5;
        }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -38px;
            border-top: 1px solid #dfe5ef;
            padding-top: 5px;
            font-size: 6.5pt;
            color: #8a93a4;
        }
    </style>
</head>

<body>
    @php
        $money = fn($v) => number_format((float) $v, 2);
        $amount = fn($v) => abs((float) $v) > 0 ? number_format((float) $v, 2) : '';
        $st = $statement;
        $closing = (float) $st['closing'];
        $closingNote = $closing > 0 ? 'Due to landlord' : ($closing < 0 ? 'Due from landlord' : 'Nil balance');
    @endphp

    <table class="head">
        <tr>
            <td class="left">
                <img class="logo" src="{{ public_path('assets/images/logo/logo_statement.png') }}" alt="Chabrin Agencies Limited">
                <div class="company-line">
                    Property Managing Agents<br>
                    5th Floor, Room 517, NACICO Plaza, Landhies Road<br>
                    P.O. Box 16659-00620 Nairobi, Kenya
                </div>
            </td>
            <td class="right">
                <div class="doc-title">ACCOUNT STATEMENT</div>
                <div class="doc-period">{{ date('d M Y', strtotime($st['from'])) }} &ndash; {{ date('d M Y', strtotime($st['to'])) }}</div>
                <div class="doc-meta">Statement date: {{ $generatedAt }}</div>
            </td>
        </tr>
    </table>
    <div class="rule-navy"></div>
    <div class="rule-teal"></div>

    <table class="cards">
        <tr>
            <td class="col-landlord">
                <div class="card">
                    <div class="card-label">LANDLORD</div>
                    <div class="landlord-name">{{ $account['names'] }}</div>
                    <table class="kv">
                        <tr><td class="k">Account no.</td><td><strong>{{ $account['reference_number'] }}</strong></td></tr>
                        @if(!empty($account['mobile_number']))
                        <tr><td class="k">Phone</td><td>{{ $account['mobile_number'] }}</td></tr>
                        @endif
                        @if(!empty($account['email_address']))
                        <tr><td class="k">Email</td><td>{{ $account['email_address'] }}</td></tr>
                        @endif
                        @if(count($st['properties']))
                        <tr>
                            <td class="k">{{ count($st['properties']) > 1 ? 'Properties' : 'Property' }}</td>
                            <td>{{ implode(', ', array_slice($st['properties'], 0, 3)) }}@if(count($st['properties']) > 3) +{{ count($st['properties']) - 3 }} more @endif</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </td>
            <td class="col-summary">
                <div class="card">
                    <div class="card-label">PERIOD SUMMARY</div>
                    <table class="summary">
                        <tr><td class="k">Opening balance</td><td class="v">{{ $money($st['opening']) }}</td></tr>
                        <tr><td class="k">Total credits</td><td class="v cr">{{ $money($st['credits']) }}</td></tr>
                        <tr><td class="k">Total debits</td><td class="v dr">{{ $money($st['debits']) }}</td></tr>
                        <tr class="closing"><td class="k">Closing balance</td><td class="v">{{ $money($closing) }}</td></tr>
                        <tr><td colspan="2" class="closing-note">{{ $closingNote }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="txn">
        <thead>
            <tr>
                <th style="width: 11%;">DATE</th>
                <th style="width: 13%;">REFERENCE</th>
                <th style="width: 34%;">NARRATION</th>
                <th class="r" style="width: 13%;">DEBIT</th>
                <th class="r" style="width: 13%;">CREDIT</th>
                <th class="r" style="width: 16%;">BALANCE</th>
            </tr>
        </thead>
        <tbody>
            <tr class="bf">
                <td>{{ date('d-m-Y', strtotime($st['from'])) }}</td>
                <td></td>
                <td>Opening balance</td>
                <td class="r"></td>
                <td class="r"></td>
                <td class="r">{{ $money($st['opening']) }}</td>
            </tr>

            @forelse($st['rows'] as $i => $row)
            <tr class="{{ $i % 2 ? 'alt' : '' }} {{ $row['counted'] ? '' : 'nc' }}">
                <td>{{ $row['date_formatted'] }}</td>
                <td class="ref">{{ $row['reference'] !== '-' ? $row['reference'] : '' }}</td>
                <td>{{ $row['narration'] }}@unless($row['counted']) <span class="nc-tag">(not counted)</span>@endunless</td>
                <td class="r dr">{{ $amount($row['debit']) }}</td>
                <td class="r cr">{{ $amount($row['credit']) }}</td>
                <td class="r {{ $row['balance'] < 0 ? 'neg' : '' }}">{{ $money($row['balance']) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="empty">No transactions in this period.</td>
            </tr>
            @endforelse

            <tr class="totals">
                <td colspan="3">Period totals</td>
                <td class="r dr">{{ $money($st['debits']) }}</td>
                <td class="r cr">{{ $money($st['credits']) }}</td>
                <td class="r"></td>
            </tr>
            <tr class="final">
                <td colspan="5">Closing balance &mdash; {{ $closingNote }}</td>
                <td class="r">{{ $money($closing) }}</td>
            </tr>
        </tbody>
    </table>

    @include('property.landlord.partials.charges-breakdown')

    @if($st['has_uncounted'] || $st['undated'] > 0)
    <div class="note">
        @if($st['has_uncounted'])
        Entries marked &ldquo;not counted&rdquo; (such as balance snapshots written by the returns process) are listed for reference only and do not change the balance or the totals.<br>
        @endif
        @if($st['undated'] > 0)
        {{ $st['undated'] }} {{ $st['undated'] === 1 ? 'entry has' : 'entries have' }} no date on the account and {{ $st['undated'] === 1 ? 'is' : 'are' }} not shown.
        @endif
    </div>
    @endif

    <div class="footer">
        Chabrin Agencies Limited &middot; This statement is computer generated and is valid without a signature.
    </div>
</body>

</html>
