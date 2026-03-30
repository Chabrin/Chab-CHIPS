<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$landlord = 'LAN-00581';
$property = '242F';

// Show ALL balance-tracking fields for every return
$rows = DB::select("
    SELECT year, month, return_code,
           total_credit, total_debit, commission_amount,
           net_amount,          -- NETAMT: the month result
           net_balance,         -- NETBAL: CHRHMS running carry field
           transaction_amount,  -- TRXAMT
           amount_paid,         -- AMTPAYD (what was actually paid out)
           amount_overdraft,    -- AMTOVD  (overdraft amount)
           overdraft_percentage,-- OVDPER
           paid_status, approved_status, voucher_number
    FROM cb_returns_summary
    WHERE landlord_code = ? AND property_code = ?
    ORDER BY year, month
", [$landlord, $property]);

echo "\n==== BALANCE TRACKING FIELDS: {$landlord} / {$property} ====\n";
echo str_pad('PERIOD', 8)
    . str_pad('TOTAL_CR', 12)
    . str_pad('TOTAL_DR', 12)
    . str_pad('NET_AMT', 12)
    . str_pad('NET_BAL', 12)
    . str_pad('AMT_PAID', 12)
    . str_pad('OVERDRAFT', 12)
    . str_pad('OVD_%', 8)
    . "VOUCHER\n";
echo str_repeat('-', 90) . "\n";

foreach ($rows as $r) {
    $highlight = ($r->net_amount < 0) ? ' <<< NEGATIVE' : '';
    echo str_pad($r->month . '/' . $r->year, 8)
        . str_pad(number_format($r->total_credit), 12)
        . str_pad(number_format($r->total_debit), 12)
        . str_pad(number_format($r->net_amount), 12)
        . str_pad(number_format($r->net_balance), 12)
        . str_pad(number_format($r->amount_paid), 12)
        . str_pad(number_format($r->amount_overdraft), 12)
        . str_pad($r->overdraft_percentage, 8)
        . ($r->voucher_number ?? '-') . $highlight . "\n";
}

// Show the CHRHMS original comment context
echo "\n\nField meanings from DDL:\n";
echo "  net_amount        = NETAMT  — the month's result (CR - DR - commission - VAT)\n";
echo "  net_balance       = NETBAL  — migrated from CHRHMS; should carry running balance\n";
echo "  amount_paid       = AMTPAYD — what was physically paid out via voucher\n";
echo "  amount_overdraft  = AMTOVD  — overdraft amount when net is negative\n";
echo "  overdraft_percent = OVDPER  — overdraft percentage\n";

// Also check if cb_landlord_account has any credit entry (type 0005) 
// that equals the negative months — to see if CHRHMS posted a running balance credit
$neg = DB::select("
    SELECT rs.year, rs.month, rs.net_amount,
           la.debit_amount as la_dr, la.credit_amount as la_cr,
           la.entry_type_code
    FROM cb_returns_summary rs
    LEFT JOIN cb_landlord_account la
        ON la.return_code = rs.return_code
        AND la.entry_type_code = '0005'
    WHERE rs.landlord_code = ? AND rs.property_code = ?
    ORDER BY rs.year, rs.month
", [$landlord, $property]);

echo "\n\n==== cb_landlord_account 0005 CREDIT per return ====\n";
echo str_pad('PERIOD', 8) . str_pad('NET_AMT', 14) . str_pad('0005_CR_POSTED', 16) . "MATCH?\n";
echo str_repeat('-', 45) . "\n";
foreach ($neg as $r) {
    $match = (abs((float)$r->net_amount - (float)$r->la_cr) < 1) ? 'YES' : 'DIFF ' . (number_format((float)$r->la_cr));
    echo str_pad($r->month . '/' . $r->year, 8)
        . str_pad(number_format($r->net_amount), 14)
        . str_pad(number_format($r->la_cr ?? 0), 16)
        . $match . "\n";
}
