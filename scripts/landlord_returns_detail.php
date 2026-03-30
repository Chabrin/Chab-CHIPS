<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$landlord = 'LAN-00581';
$property = '242F';

// ── 1. cb_returns_summary ──────────────────────────────────────────
$summaries = DB::select("
    SELECT return_code, year, month,
           total_credit, total_debit, commission_amount,
           net_amount, paid_status, approved_status, voucher_number
    FROM cb_returns_summary
    WHERE landlord_code = ? AND property_code = ?
    ORDER BY year, month
", [$landlord, $property]);

echo "\n==== cb_returns_summary: {$landlord} / {$property} (GLADYS WANJIRU MACHARIA) ====\n";
echo str_pad('PERIOD', 8) . str_pad('TOTAL_CR', 14) . str_pad('LOAN_DR(+others)', 18) . str_pad('COMMISSION', 12) . str_pad('NET_AMT', 14) . str_pad('PAID', 6) . str_pad('APPR', 6) . "VOUCHER\n";
echo str_repeat('-', 82) . "\n";
foreach ($summaries as $s) {
    echo str_pad($s->month . '/' . $s->year, 8)
        . str_pad(number_format($s->total_credit), 14)
        . str_pad(number_format($s->total_debit), 18)
        . str_pad(number_format($s->commission_amount), 12)
        . str_pad(number_format($s->net_amount), 14)
        . str_pad($s->paid_status, 6)
        . str_pad($s->approved_status, 6)
        . ($s->voucher_number ?? '-') . "\n";
}

// ── 2. Detail lines for Jan 2026 (a month with a loan) ──────────────
$sample_code = 'R1202601LAN-00581242F';
echo "\n\n==== cb_returns_clients lines for {$sample_code} ====\n";
$lines = DB::select("
    SELECT entry_type, dr_cr, net_amount, narration
    FROM cb_returns_clients
    WHERE return_code = ?
    ORDER BY dr_cr DESC, entry_type
", [$sample_code]);

if (empty($lines)) {
    echo "(No cb_returns_clients rows — this return is from the old system)\n";
} else {
    echo str_pad('DR/CR', 6) . str_pad('ENTRY_TYPE', 40) . str_pad('NET_AMT', 14) . "NARRATION\n";
    echo str_repeat('-', 90) . "\n";
    $totalCR = 0;
    $totalDR = 0;
    foreach ($lines as $l) {
        echo str_pad($l->dr_cr, 6)
            . str_pad($l->entry_type, 40)
            . str_pad(number_format($l->net_amount), 14)
            . substr($l->narration ?? '', 0, 35) . "\n";
        if ($l->dr_cr === 'CR') $totalCR += $l->net_amount;
        else $totalDR += $l->net_amount;
    }
    echo str_repeat('-', 90) . "\n";
    echo str_pad('', 6) . str_pad('TOTAL CR', 40) . number_format($totalCR) . "\n";
    echo str_pad('', 6) . str_pad('TOTAL DR', 40) . number_format($totalDR) . "\n";
    echo str_pad('', 6) . str_pad('NET (CR-DR)', 40) . number_format($totalCR - $totalDR) . "\n";
}

// ── 3. What the cb_landlord_account shows for Jan 2026 ─────────────
echo "\n\n==== cb_landlord_account entries FOR Jan 2026 (return R1202601LAN-00581242F) ====\n";
$laccs = DB::select("
    SELECT entry_type_code, entry_description, entry_particulars,
           debit_amount, credit_amount, is_recovered, return_code
    FROM cb_landlord_account
    WHERE landlord_code = ? AND property_code = ?
      AND return_code = 'R1202601LAN-00581242F'
    ORDER BY entry_type_code, id
", [$landlord, $property]);

echo str_pad('CODE', 7) . str_pad('DR', 14) . str_pad('CR', 14) . str_pad('RCVD', 6) . "DESCRIPTION\n";
echo str_repeat('-', 75) . "\n";
foreach ($laccs as $r) {
    echo str_pad($r->entry_type_code, 7)
        . str_pad($r->debit_amount > 0 ? number_format($r->debit_amount) : '', 14)
        . str_pad($r->credit_amount > 0 ? number_format($r->credit_amount) : '', 14)
        . str_pad($r->is_recovered ? 'YES' : '-', 6)
        . substr($r->entry_particulars ?: $r->entry_description, 0, 40) . "\n";
}
