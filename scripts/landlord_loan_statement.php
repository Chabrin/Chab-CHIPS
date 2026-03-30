<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Step 1: Find landlords with both loans (0020) and returns (0005)
$candidates = DB::select("
    SELECT landlord_code, landlord_name, property_code,
           SUM(CASE WHEN entry_type_code = '0020' THEN 1 ELSE 0 END) as loan_rows,
           SUM(CASE WHEN entry_type_code = '0005' THEN 1 ELSE 0 END) as return_rows,
           SUM(CASE WHEN entry_type_code = '0020' THEN debit_amount ELSE 0 END) as loan_total,
           SUM(CASE WHEN entry_type_code = '0005' THEN credit_amount ELSE 0 END) as returns_credited
    FROM cb_landlord_account
    WHERE entry_type_code IN ('0020','0005')
    GROUP BY landlord_code, landlord_name, property_code
    HAVING SUM(CASE WHEN entry_type_code = '0020' THEN 1 ELSE 0 END) > 0
       AND SUM(CASE WHEN entry_type_code = '0005' THEN 1 ELSE 0 END) > 0
    ORDER BY loan_rows DESC, return_rows DESC
    LIMIT 8
");

echo "\n==== CANDIDATES WITH LOANS + RETURNS ====\n";
echo str_pad("LANDLORD", 12) . str_pad("PROPERTY", 12) . str_pad("NAME", 32) . str_pad("LOANS", 6) . str_pad("RETURNS", 8) . str_pad("LOAN_AMT", 16) . "CR_RETURNS\n";
echo str_repeat('-', 95) . "\n";
foreach ($candidates as $r) {
    echo str_pad($r->landlord_code, 12)
        . str_pad($r->property_code, 12)
        . str_pad(substr($r->landlord_name, 0, 30), 32)
        . str_pad($r->loan_rows, 6)
        . str_pad($r->return_rows, 8)
        . str_pad(number_format($r->loan_total), 16)
        . number_format($r->returns_credited) . "\n";
}

// Step 2: Pick the top candidate and show full statement
if (empty($candidates)) {
    echo "\nNo candidates found.\n";
    exit;
}

$pick = $candidates[0];
echo "\n\n==== FULL ACCOUNT STATEMENT: {$pick->landlord_code} / {$pick->property_code} ====\n";
echo "Name: {$pick->landlord_name}\n\n";

$rows = DB::select("
    SELECT
        year, month, entry_type_code, entry_description,
        entry_particulars, entry_date::date AS entry_date,
        debit_amount, credit_amount,
        is_recovered, return_code, recovery_year, recovery_month
    FROM cb_landlord_account
    WHERE landlord_code = ?
      AND property_code = ?
    ORDER BY entry_date, id
", [$pick->landlord_code, $pick->property_code]);

echo str_pad("DATE", 12)
    . str_pad("CODE", 7)
    . str_pad("DESCRIPTION", 40)
    . str_pad("DR", 16)
    . str_pad("CR", 16)
    . str_pad("RECOVERED", 10)
    . "RETURN_CODE\n";
echo str_repeat('-', 105) . "\n";

$runningBal = 0;
foreach ($rows as $row) {
    $dr = (float)$row->debit_amount;
    $cr = (float)$row->credit_amount;
    $runningBal += $dr - $cr;

    $tag = '';
    if ($row->entry_type_code === '0020') $tag = ' <<< LOAN';
    if ($row->entry_type_code === '0005') $tag = ' <<< RETURN POSTING';

    echo str_pad($row->entry_date ?? "{$row->year}-{$row->month}", 12)
        . str_pad($row->entry_type_code, 7)
        . str_pad(substr(($row->entry_particulars ?: $row->entry_description) . $tag, 0, 38), 40)
        . str_pad($dr > 0 ? number_format($dr, 2) : '', 16)
        . str_pad($cr > 0 ? number_format($cr, 2) : '', 16)
        . str_pad($row->is_recovered ? 'YES' : '-', 10)
        . ($row->return_code ?? '') . "\n";
}

echo str_repeat('=', 105) . "\n";
echo str_pad("", 55) . str_pad("BALANCE (DR-CR):", 20) . number_format($runningBal, 2) . "\n";
