<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== All lines for R1202603LAN-01072 ===\n";
$lines = DB::select("SELECT id, entry_type, entry_description, dr_cr, net_amount, property_code, transaction_number, transaction_ref 
FROM cb_returns_clients WHERE return_code = 'R1202603LAN-01072' ORDER BY id");
foreach ($lines as $l) {
    echo sprintf(
        "  id:%d | %s | %s | %s | %.2f | prop:%s | txn:%s | ref:%s\n",
        $l->id,
        str_pad($l->entry_type ?? '', 35),
        str_pad($l->entry_description ?? '', 15),
        $l->dr_cr,
        $l->net_amount,
        $l->property_code ?? '-',
        $l->transaction_number ?? '-',
        $l->transaction_ref ?? '-'
    );
}

echo "\n=== Summary for R1202603LAN-01072 ===\n";
$summary = DB::selectOne("SELECT * FROM cb_returns_summary WHERE return_code = 'R1202603LAN-01072'");
if ($summary) {
    echo "  return_type: {$summary->return_type}\n";
    echo "  property_code: '{$summary->property_code}'\n";
    echo "  landlord_code: {$summary->landlord_code}\n";
    echo "  total_credit: {$summary->total_credit}\n";
    echo "  total_debit: {$summary->total_debit}\n";
    echo "  net_amount: {$summary->net_amount}\n";
    echo "  commission_amount: {$summary->commission_amount}\n";
    echo "  commission_total: {$summary->commission_total}\n";
    echo "  transaction_ref: {$summary->transaction_ref}\n";
}

echo "\n=== All lines for NEW R1202604LAN-00013 ===\n";
$lines2 = DB::select("SELECT id, entry_type, entry_description, dr_cr, net_amount, property_code, transaction_number, transaction_ref 
FROM cb_returns_clients WHERE return_code = 'R1202604LAN-00013' ORDER BY id");
foreach ($lines2 as $l) {
    echo sprintf(
        "  id:%d | %s | %s | %s | %.2f | prop:%s | txn:%s | ref:%s\n",
        $l->id,
        str_pad($l->entry_type ?? '', 35),
        str_pad($l->entry_description ?? '', 15),
        $l->dr_cr,
        $l->net_amount,
        $l->property_code ?? '-',
        $l->transaction_number ?? '-',
        $l->transaction_ref ?? '-'
    );
}

echo "\n=== Check landlord account for LAN-00013 client in April 2026 ===\n";
$clientId = DB::selectOne("SELECT id FROM sys_pm_clients WHERE reference_number = 'LAN-00013'")->id ?? null;
echo "  client_id: {$clientId}\n";
if ($clientId) {
    $advances = DB::select("SELECT * FROM cb_landlord_account WHERE client_id = ? AND year = 2026 AND month = 4 AND debit_amount > 0 LIMIT 5", [$clientId]);
    echo "  Advances in cb_landlord_account: " . count($advances) . "\n";
    foreach ($advances as $a) {
        echo "    id:{$a->id} | {$a->entry_type_code} | debit:{$a->debit_amount} | recovered:{$a->is_recovered}\n";
    }
}

echo "\n=== DONE ===\n";
