<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$landlordCode = 'LAN-00324';

echo "=== cb_returns_clients for {$landlordCode} (March & April 2026) ===\n\n";

// Get March data
$marchClients = DB::select("
    SELECT return_code, return_period, entry_type, entry_description, dr_cr, 
           net_amount, commission_amount, is_commissionable, transaction_source
    FROM cb_returns_clients 
    WHERE return_code LIKE '%{$landlordCode}%'
    AND return_period = 202603
    ORDER BY entry_type, dr_cr
");

echo "--- MARCH 2026 (202603) ---\n";
echo sprintf(
    "%-25s %-10s %-40s %-5s %12s %12s %-5s %-15s\n",
    "Return Code",
    "Entry",
    "Description",
    "DR/CR",
    "Net Amount",
    "Commission",
    "Comm?",
    "Source"
);
echo str_repeat("-", 140) . "\n";

$marchDR = 0;
$marchCR = 0;
$marchComm = 0;
foreach ($marchClients as $row) {
    echo sprintf(
        "%-25s %-10s %-40s %-5s %12.2f %12.2f %-5s %-15s\n",
        substr($row->return_code, 0, 25),
        $row->entry_type,
        substr($row->entry_description ?? '', 0, 40),
        $row->dr_cr,
        $row->net_amount ?? 0,
        $row->commission_amount ?? 0,
        $row->is_commissionable ?? '',
        $row->transaction_source ?? ''
    );
    if ($row->dr_cr == 'DR') $marchDR += $row->net_amount ?? 0;
    if ($row->dr_cr == 'CR') $marchCR += $row->net_amount ?? 0;
    $marchComm += $row->commission_amount ?? 0;
}
echo "\nMarch Totals: DR={$marchDR}, CR={$marchCR}, Commission={$marchComm}\n";
echo "March Line count: " . count($marchClients) . "\n\n";

// Get April data  
$aprilClients = DB::select("
    SELECT return_code, return_period, entry_type, entry_description, dr_cr, 
           net_amount, commission_amount, is_commissionable, transaction_source
    FROM cb_returns_clients 
    WHERE return_code LIKE '%{$landlordCode}%'
    AND return_period = 202604
    ORDER BY entry_type, dr_cr
");

echo "--- APRIL 2026 (202604) ---\n";
echo sprintf(
    "%-25s %-10s %-40s %-5s %12s %12s %-5s %-15s\n",
    "Return Code",
    "Entry",
    "Description",
    "DR/CR",
    "Net Amount",
    "Commission",
    "Comm?",
    "Source"
);
echo str_repeat("-", 140) . "\n";

$aprilDR = 0;
$aprilCR = 0;
$aprilComm = 0;
foreach ($aprilClients as $row) {
    echo sprintf(
        "%-25s %-10s %-40s %-5s %12.2f %12.2f %-5s %-15s\n",
        substr($row->return_code, 0, 25),
        $row->entry_type,
        substr($row->entry_description ?? '', 0, 40),
        $row->dr_cr,
        $row->net_amount ?? 0,
        $row->commission_amount ?? 0,
        $row->is_commissionable ?? '',
        $row->transaction_source ?? ''
    );
    if ($row->dr_cr == 'DR') $aprilDR += $row->net_amount ?? 0;
    if ($row->dr_cr == 'CR') $aprilCR += $row->net_amount ?? 0;
    $aprilComm += $row->commission_amount ?? 0;
}
echo "\nApril Totals: DR={$aprilDR}, CR={$aprilCR}, Commission={$aprilComm}\n";
echo "April Line count: " . count($aprilClients) . "\n\n";

echo "\n=== cb_returns_summary for {$landlordCode} (MAINRETURN) ===\n";
$summaries = DB::select("
    SELECT return_code, return_period, year, month, return_type,
           total_returns, rent_amount, gross_commission, vat_commission, 
           net_commission_paid, deductions, other_collections, net_amount_payable,
           total_credit, total_debit, net_amount
    FROM cb_returns_summary 
    WHERE landlord_code = '{$landlordCode}'
    AND year = 2026 AND month IN (3, 4) 
    AND return_type = 'MAINRETURN'
    ORDER BY month
");

foreach ($summaries as $s) {
    echo "\n-- Month {$s->month} --\n";
    echo "Return Code: {$s->return_code}\n";
    echo "Total Returns: {$s->total_returns}, Rent Amount: {$s->rent_amount}\n";
    echo "Gross Commission: {$s->gross_commission}, VAT: {$s->vat_commission}, Net Comm: {$s->net_commission_paid}\n";
    echo "Deductions: {$s->deductions}, Other Collections: {$s->other_collections}\n";
    echo "Net Amount Payable: {$s->net_amount_payable}\n";
    echo "Legacy - Total Credit: {$s->total_credit}, Total Debit: {$s->total_debit}, Net: {$s->net_amount}\n";
}

echo "\n\n=== cb_landlord_account for {$landlordCode} (March & April 2026) ===\n";
$landlordAcc = DB::select("
    SELECT id, landlord_code, year, month, transaction_date, entry_type, entry_code,
           entry_description, dr_cr, amount, balance, transaction_ref, narration
    FROM cb_landlord_account 
    WHERE landlord_code = '{$landlordCode}'
    AND year = 2026 AND month IN (3, 4)
    ORDER BY month, id
");

echo sprintf(
    "%-6s %-6s %-12s %-10s %-6s %-40s %-5s %12s %12s\n",
    "ID",
    "Month",
    "Date",
    "EntryCode",
    "Type",
    "Description",
    "DR/CR",
    "Amount",
    "Balance"
);
echo str_repeat("-", 140) . "\n";

foreach ($landlordAcc as $row) {
    echo sprintf(
        "%-6d %-6d %-12s %-10s %-6s %-40s %-5s %12.2f %12.2f\n",
        $row->id,
        $row->month,
        $row->transaction_date ?? '',
        $row->entry_code ?? '',
        $row->entry_type ?? '',
        substr($row->entry_description ?? '', 0, 40),
        $row->dr_cr ?? '',
        $row->amount ?? 0,
        $row->balance ?? 0
    );
}

echo "\n\n=== sys_set_invoiceable_items (0009, 0013, 0014) ===\n";
$items = DB::select("
    SELECT id, item_code, item_name, item_type, dr_cr, is_commissionable, deduction_point
    FROM sys_set_invoiceable_items 
    WHERE item_code IN ('0009', '0013', '0014')
");

foreach ($items as $item) {
    echo "Code: {$item->item_code}, Name: {$item->item_name}, Type: {$item->item_type}, ";
    echo "DR/CR: {$item->dr_cr}, Commissionable: {$item->is_commissionable}, Deduct: {$item->deduction_point}\n";
}
