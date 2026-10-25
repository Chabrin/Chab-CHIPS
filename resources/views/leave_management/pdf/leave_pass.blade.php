<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Employee Leave Pass</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #206bc4; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; text-transform: uppercase; color: #206bc4; font-size: 18px; }
        .header p { margin: 3px 0 0; font-size: 11px; color: #666; }
        .badge { display: inline-block; padding: 4px 8px; background-color: #2fb344; color: #fff; font-weight: bold; border-radius: 3px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        table th, table td { padding: 8px 10px; text-align: left; vertical-align: top; }
        table th { background-color: #f8f9fa; width: 35%; color: #555; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid #ddd; }
        table td { border-bottom: 1px solid #eee; }
        .signatures { margin-top: 40px; width: 100%; }
        .signatures td { border: none; width: 50%; padding-top: 40px; text-align: center; }
        .line { border-top: 1px dashed #777; width: 80%; margin: 0 auto 5px; }
        .footer { font-size: 9px; color: #999; text-align: center; margin-top: 30px; }
    </style>
</head>
<body>

    <div class="header">
        <h2>CHABRIN LTD.</h2>
        <p>OFFICIAL EMPLOYEE LEAVE PASS / CLEARANCE SLIP</p>
    </div>

    <table>
        <tr>
            <th>Pass / Ref No:</th>
            <td><strong>#LP-{{ str_pad($leave->id, 5, '0', STR_PAD_LEFT) }}</strong></td>
        </tr>
        <tr>
            <th>Staff Name:</th>
            <td>{{ $staff->full_names ?? $staff->name }}</td>
        </tr>
        <tr>
            <th>Staff Number:</th>
            <td>{{ $staff->staff_number ?? '-' }}</td>
        </tr>
        <tr>
            <th>Department / Designation:</th>
            <td>{{ $staff->department->department_name ?? '-' }} / {{ $staff->position->position_name ?? '-' }}</td>
        </tr>
        <tr>
            <th>Leave Type:</th>
            <td>{{ $leave->leaveType->type_name ?? 'Annual Leave' }}</td>
        </tr>
        <tr>
            <th>Leave Period:</th>
            <td>
                {{ \Carbon\Carbon::parse($leave->start_date)->format('d M Y') }} 
                to 
                {{ \Carbon\Carbon::parse($leave->end_date)->format('d M Y') }}
                ({{ $leave->days ?? $leave->requested_days }} Working Days)
            </td>
        </tr>
        <tr>
            <th>Approval Status:</th>
            <td><span class="badge">APPROVED</span></td>
        </tr>
        <tr>
            <th>Approval Date:</th>
            <td>{{ \Carbon\Carbon::parse($approvedAt ?? $leave->date_time)->format('d M Y H:i') }}</td>
        </tr>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <div class="line"></div>
                <strong>Employee Signature</strong>
            </td>
            <td>
                <div class="line"></div>
                <strong>HR / Authorizing Manager</strong>
            </td>
        </tr>
    </table>

    <div class="footer">
        Generated electronically on {{ date('d M Y H:i:s') }}. Valid across security checkpoints and company premises.
    </div>

</body>
</html>