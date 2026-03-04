<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================\n";
echo "LEASE 34054 RETURNS ANALYSIS\n";
echo "========================================\n\n";

// Get all returns for lease 34054
$returns = DB::table('sys_pm_clients_returns as r')
    ->join('sys_pm_debtors_invoices as inv', 'r.invoice_id', '=', 'inv.id')
    ->where('r.lease_id', 34054)
    ->orderBy('r.return_date', 'asc')
    ->select([
        'r.return_date',
        'r.threshold_percentage',
        'r.abstract_threshold',
        'inv.item_type_id',
        'inv.commisionable_amount',
        'inv.current_balance',
        'inv.date_due'
    ])
    ->get();

echo "Total Returns Found: " . $returns->count() . "\n\n";

foreach ($returns as $r) {
    $calculated = $r->commisionable_amount > 0
        ? round(($r->current_balance / $r->commisionable_amount) * 100, 2)
        : 0;

    echo sprintf(
        "Return: %s | Item: %s | Threshold: %.2f%% | Abstract: %.2f%% | Due: %s\n",
        $r->return_date,
        $r->item_type_id,
        $r->threshold_percentage,
        $r->abstract_threshold,
        $r->date_due
    );
    echo sprintf(
        "  Commisionable: %.2f | Current Balance: %.2f | Calculated: %.2f%%\n\n",
        $r->commisionable_amount,
        $r->current_balance,
        $calculated
    );
}

// Calculate historical average for returns before November 2025
echo "\n========================================\n";
echo "HISTORICAL AVERAGE CALCULATION (Before Nov 2025)\n";
echo "========================================\n\n";

$historical = DB::table('sys_pm_clients_returns as r')
    ->join('sys_pm_debtors_invoices as inv', 'r.invoice_id', '=', 'inv.id')
    ->where('r.lease_id', 34054)
    ->where('r.return_date', '<', '2025-11-01')
    ->where('r.current_status_id', 1)
    ->where('inv.commisionable_amount', '>', 0)
    ->select([
        'r.return_date',
        'inv.item_type_id',
        'inv.commisionable_amount',
        'inv.current_balance',
        DB::raw('ROUND(((inv.commisionable_amount - inv.current_balance) / inv.commisionable_amount) * 100, 2) as calc_threshold')
    ])
    ->get();

echo "Records used in historical calculation: " . $historical->count() . "\n\n";

$sum = 0;
foreach ($historical as $h) {
    $capped = $h->calc_threshold > 100 ? 100 : $h->calc_threshold;
    echo sprintf(
        "%s | Item: %s | Comm: %.2f | Balance: %.2f | Calc: %.2f%% | Capped: %.2f%%\n",
        $h->return_date,
        $h->item_type_id,
        $h->commisionable_amount,
        $h->current_balance,
        $h->calc_threshold,
        $capped
    );
    $sum += $capped;
}

if ($historical->count() > 0) {
    $avg = round($sum / $historical->count(), 2);
    echo "\nAVERAGE (with cap): " . $avg . "%\n";
}

// Check October 2025 specifically
echo "\n========================================\n";
echo "OCTOBER 2025 RETURN (Previous Month)\n";
echo "========================================\n\n";

$october = DB::table('sys_pm_clients_returns as r')
    ->join('sys_pm_debtors_invoices as inv', 'r.invoice_id', '=', 'inv.id')
    ->where('r.lease_id', 34054)
    ->where('r.return_date', '>=', '2025-10-01')
    ->where('r.return_date', '<=', '2025-10-31')
    ->where('r.current_status_id', 1)
    ->select([
        'r.return_date',
        'r.threshold_percentage',
        'inv.item_type_id',
        'inv.commisionable_amount',
        'inv.current_balance'
    ])
    ->get();

foreach ($october as $oct) {
    echo sprintf(
        "Oct Return | Item: %s | Threshold: %.2f%% | Comm: %.2f | Balance: %.2f\n",
        $oct->item_type_id,
        $oct->threshold_percentage,
        $oct->commisionable_amount,
        $oct->current_balance
    );
}
