<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Benefit Statement</title>
    <style>
        @page {
            size: A4;
            margin: 15mm 20mm 20mm 20mm;
            /* top, right, bottom, left */
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
            color: #0b2e6f;
            line-height: 1.3;
        }

        .page {
            width: 100%;
            /* span full content width */
            margin: 0 auto;
            padding: 0;
            box-sizing: border-box;
        }

        .header-wrapper {
            margin-top: 0;
            margin-bottom: 8px;
            text-align: center;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            padding: 0;
            vertical-align: top;
        }

        .fund-title {
            font-size: 14px;
            color: #12235a;
            font-weight: 700;
        }

        .statement-title {
            font-size: 13px;
            font-weight: 700;
            margin-top: 6px;
            color: #12235a;
        }

        .as-at {
            font-size: 11px;
            margin-top: 6px;
            color: #12235a;
        }

        .divider {
            width: 60%;
            height: 4px;
            background: #12235a;
            margin: 6px auto 0 auto;
        }

        .logo img {
            max-width: 140px;
            max-height: 60px;
            object-fit: contain;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        table,
        tr,
        td,
        th {
            page-break-inside: avoid;
        }

        .member-info td {
            padding: 6px 8px;
            border: 1px solid #12235a;
            vertical-align: top;
            text-align: left;
        }

        .member-info td:first-child {
            font-weight: 600;
            width: 30%;
        }

        .details-table th,
        .details-table td {
            padding: 6px 8px;
            border: 1px solid #12235a;
        }

        .details-table th {
            font-weight: 600;
            text-align: left;
        }

        .currency {
            text-align: right;
            white-space: nowrap;
        }

        .totals-row td {
            font-weight: bold;
            border-top: 2px solid #12235a;
        }

        .footer {
            margin-top: 12px;
            font-size: 11px;
            color: #12235a;
        }

        .footer strong {
            display: block;
            margin-bottom: 6px;
        }

        .small-note {
            font-size: 12px;
            margin: 6px 0;
        }
    </style>
</head>

<body>
    <div class="page">

        <!-- Header -->
        <div class="header-wrapper">
            <table class="header-table">
                <tr>
                    <td style="width:70%; text-align:center;">
                        <div class="fund-title">{{ strtoupper($company->company_name) }}:</div>
                        <div class="statement-title">MEMBERS BENEFIT STATEMENT</div>
                        <div class="as-at">As At : {{ $as_at ?? now()->format('d M Y') }}</div>
                        <div class="divider"></div>
                    </td>
                    <td style="width:30%; text-align:right;" class="logo">
                        @if(isset($company) && !empty($company->logo_data))
                        <img src="{{ $company->logo_data }}" alt="Company Logo">
                        @elseif(isset($company) && !empty($company->logo))
                        <img src="{{ asset('storage/' . $company->logo) }}" alt="Company Logo">
                        @else
                        <!-- No logo available -->
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <!-- Member Info -->
        <table class="member-info">
            <tr>
                <td>Name of Member</td>
                <td>{{ $member->first_name }} {{ $member->last_name }} {{ $member->other_names }}</td>
            </tr>
            <tr>
                <td>Member No<br>Payroll No</td>
                <td>{{ $member->member_no }} <br>{{ $member->payrolid }}</td>
            </tr>
            <tr>
                <td>Date of Birth<br>Date of Employment<br>Date of Joining Scheme</td>
                <td>{{ $member->dob }}<br>{{ $member->employmentdate }}<br>{{ $member->datejoined}}</td>
            </tr>
            <tr>
                <td>Normal Retirement Date<br>Current Age<br>Normal Retirement Age</td>
                <td>{{ $member->retage }}<br>{{ $current_age ?? '«Age»' }}<br>{{ $normal_retirement_age ?? '55' }}</td>
            </tr>
        </table>

        <!-- Note -->
        <p class="small-note">
            This is to certify that you are a member of <b>{{ strtoupper($company->company_name) }}</b>.
            The sum standing to the credit of your account from inception up to and including
            {{ $as_at ?? now()->format('d M Y') }} is as follows:
        </p>

        <!-- Contribution Details -->
        <table class="details-table">
            <thead>
                <tr>
                    <th style="width:48%;"></th>
                    <th style="width:26%;" class="currency">Employee (KES)</th>
                    <th style="width:26%;" class="currency">Employer (KES)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Opening Balance</td>
                    <td class="currency">{{ $opening_year_employee_amt }}</td>
                    <td class="currency">{{ $opening_year_employer_amt}}</td>
                </tr>
                <tr>
                    <td>Current Year Contributions</td>
                    <td class="currency">{{$curr_year_employee_amt}}</td>
                    <td class="currency">{{$curr_year_employer_amt }}</td>
                </tr>
                <tr>
                    <td>Current Year Transfers In</td>
                    <td class="currency">{{ $transfers_in_employee ?? '0.00' }}</td>
                    <td class="currency">{{ $transfers_in_employer ?? '0.00' }}</td>
                </tr>
                <tr>
                    <td>Current Year Transfers Out</td>
                    <td class="currency">{{ $transfers_out_employee ?? '0.00' }}</td>
                    <td class="currency">{{ $transfers_out_employer ?? '0.00' }}</td>
                </tr>
                <tr>
                    <td>Current Year Withdrawals</td>
                    <td class="currency">{{ $withdrawals_net ?? '0.00' }}</td>
                    <td class="currency">0.00</td>
                </tr>
                <tr>
                    <td>Interest on Balances brought forward and Current Years
                        Contributions</td>
                    <td class="currency">{{ $ee_interest}}</td>
                    <td class="currency">{{ $er_interest}}</td>
                </tr>
                <tr class="totals-row">
                    <td style="text-align:right;">Subtotals :</td>
                    @php
                    // Parse numeric values (they may be formatted strings)
                    $openingEe = floatval(str_replace(',', '', $opening_year_employee_amt ?? 0));
                    $currEe = floatval(str_replace(',', '', $curr_year_employee_amt ?? 0));
                    $transInEe = floatval(str_replace(',', '', $transfers_in_employee ?? 0));
                    $transOutEe = floatval(str_replace(',', '', $transfers_out_employee ?? 0));
                    $withdrawalsEe = floatval(str_replace(',', '', $withdrawals_net ?? 0));
                    $interestEe = floatval(str_replace(',', '', $ee_interest ?? 0));
                    $subtotalEe = $openingEe + $currEe + $transInEe + $transOutEe + $withdrawalsEe + $interestEe;

                    $openingEr = floatval(str_replace(',', '', $opening_year_employer_amt ?? 0));
                    $currEr = floatval(str_replace(',', '', $curr_year_employer_amt ?? 0));
                    $transInEr = floatval(str_replace(',', '', $transfers_in_employer ?? 0));
                    $transOutEr = floatval(str_replace(',', '', $transfers_out_employer ?? 0));
                    $withdrawalsEr = floatval(str_replace(',', '', $withdrawals_employer ?? 0));
                    $interestEr = floatval(str_replace(',', '', $er_interest ?? 0));
                    $subtotalEr = $openingEr + $currEr + $transInEr + $transOutEr + $withdrawalsEr + $interestEr;
                    @endphp
                    <td class="currency" style="font-weight:bold;">
                        <span style="display:inline-block;border-bottom:3px double #12235a;padding-bottom:4px;">{{ number_format($subtotalEe, 2) }}</span>
                    </td>
                    <td class="currency" style="font-weight:bold;">
                        <span style="display:inline-block;border-bottom:3px double #12235a;padding-bottom:4px;">{{ number_format($subtotalEr, 2) }}</span>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Monthly Contributions Breakdown -->
        @if(!empty($monthly_contributions) && count($monthly_contributions) > 0)
        @php
        // Get the year from the as_at date
        $statementYear = date('y', strtotime($as_at ?? now()));
        @endphp
        <table class="details-table" style="font-size:9px; margin-top:10px; margin-bottom:10px;">
            <thead>
                <tr>
                    <th style="width:20%; text-align:left; padding:4px 6px;">Contribution Type</th>
                    @foreach($monthly_contributions as $month => $data)
                    <th style="text-align:right; padding:4px 6px;">{{ date('M', mktime(0, 0, 0, $month, 1)) }}-{{ $statementYear }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding:4px 6px;">Employee Reg</td>
                    @foreach($monthly_contributions as $month => $data)
                    <td class="currency" style="padding:4px 6px;">{{ number_format($data['employee_reg'], 2) }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td style="padding:4px 6px;">Employee Unreg</td>
                    @foreach($monthly_contributions as $month => $data)
                    <td class="currency" style="padding:4px 6px;">{{ number_format($data['employee_unreg'], 2) }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td style="padding:4px 6px;">Employer Reg</td>
                    @foreach($monthly_contributions as $month => $data)
                    <td class="currency" style="padding:4px 6px;">{{ number_format($data['employer_reg'], 2) }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td style="padding:4px 6px;">Employer Unreg</td>
                    @foreach($monthly_contributions as $month => $data)
                    <td class="currency" style="padding:4px 6px;">{{ number_format($data['employer_unreg'], 2) }}</td>
                    @endforeach
                </tr>
                <tr class="totals-row">
                    <td style="text-align:right; padding:4px 6px;">Totals:</td>
                    @foreach($monthly_contributions as $month => $data)
                    @php
                    $monthTotal = $data['employee_reg'] + $data['employee_unreg'] + $data['employer_reg'] + $data['employer_unreg'];
                    @endphp
                    <td class="currency" style="padding:4px 6px; font-weight:bold;">{{ number_format($monthTotal, 2) }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
        @endif

        <!-- Footer -->
        <div class="footer">
            @php
            // Recompute subtotals for grand total (ensure consistent parsing)
            $openingEe = floatval(str_replace(',', '', $opening_year_employee_amt ?? 0));
            $currEe = floatval(str_replace(',', '', $curr_year_employee_amt ?? 0));
            $transInEe = floatval(str_replace(',', '', $transfers_in_employee ?? 0));
            $transOutEe = floatval(str_replace(',', '', $transfers_out_employee ?? 0));
            $withdrawalsEe = floatval(str_replace(',', '', $withdrawals_net ?? 0));
            $interestEe = floatval(str_replace(',', '', $ee_interest ?? 0));
            $subtotalEe = $openingEe + $currEe + $transInEe + $transOutEe + $withdrawalsEe + $interestEe;

            $openingEr = floatval(str_replace(',', '', $opening_year_employer_amt ?? 0));
            $currEr = floatval(str_replace(',', '', $curr_year_employer_amt ?? 0));
            $transInEr = floatval(str_replace(',', '', $transfers_in_employer ?? 0));
            $transOutEr = floatval(str_replace(',', '', $transfers_out_employer ?? 0));
            $withdrawalsEr = floatval(str_replace(',', '', $withdrawals_employer ?? 0));
            $interestEr = floatval(str_replace(',', '', $er_interest ?? 0));
            $subtotalEr = $openingEr + $currEr + $transInEr + $transOutEr + $withdrawalsEr + $interestEr;

            $grandTotal = $subtotalEe + $subtotalEr;
            @endphp
            <div style="text-align:right; margin-bottom:8px;">
                Total Balance as at {{ $as_at ?? now()->format('d M Y') }}:
                <span style="font-weight:bold; font-size:1.15em; border-bottom:3px double #12235a; padding-bottom:4px; margin-left:8px;">
                    {{ number_format($grandTotal, 2) }}
                </span>
            </div>

            <div class="small-note">PREPARED ON BEHALF OF THE BOARD OF TRUSTEES.</div>
        </div>
    </div>
</body>

</html>