@php
// simple table layout for PDF export
@endphp
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 4px;
        }

        th {
            background: #eee;
        }
    </style>
</head>

<body>
    <h3>EOD Reports</h3>
    <table>
        <thead>
            <tr>
                <th>Trans Date</th>
                <th>Member No</th>
                <th>Account No</th>
                <th>Security Code</th>
                <th>Fund</th>
                <th>Company</th>
                <th>Total Amount</th>
                <th>Total Net Amount</th>
                <th>Total Units</th>
                <th>Employer Amount</th>
                <th>Employer Units</th>
                <th>Employee Amount</th>
                <th>Employee Units</th>
                <th>Nav</th>
                <th>Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach($results as $r)
            <tr>
                <td>{{ $r->trans_date }}</td>
                <td>{{ $r->member_no }}</td>
                <td>{{ $r->account_no }}</td>
                <td>{{ $r->security_code }}</td>
                <td>{{ $r->fund_name }}</td>
                <td>{{ $r->company_name ?? $r->comp_name }}</td>
                <td>{{ $r->total_amount }}</td>
                <td>{{ $r->total_net_amount }}</td>
                <td>{{ $r->total_no_of_units }}</td>
                <td>{{ $r->employer_amount }}</td>
                <td>{{ $r->employer_units }}</td>
                <td>{{ $r->employee_amount }}</td>
                <td>{{ $r->employee_units }}</td>
                <td>{{ $r->nav }}</td>
                <td>{{ $r->price }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>