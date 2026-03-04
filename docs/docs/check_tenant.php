<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check the structure of the items table
echo "=== ITEMS TABLE STRUCTURE ===\n";
$item = DB::table('sys_reports_returns_items')->first();
echo json_encode($item, JSON_PRETTY_PRINT);
echo "\n\n";

// Get a sample return ID
$returnId = 1;

// First check raw data
echo "=== RAW DATA CHECK ===\n";
$items = DB::table('sys_reports_returns_items as ri')
    ->select(['ri.unit_code', 'ri.invoice_id', 'ri.property_report_id'])
    ->where('ri.property_report_id', $returnId)
    ->limit(3)
    ->get();

echo "Sample items:\n";
echo json_encode($items, JSON_PRETTY_PRINT);
echo "\n\n";

// Check with grouped query like the actual one
echo "=== GROUPED QUERY (ACTUAL) ===\n";
$units = DB::table('sys_reports_returns_items as ri')
    ->select([
        'ri.unit_code',
        DB::raw("MAX(t.names) as tenant_name"),
        DB::raw("MAX(inv.tenant_id) as tenant_id"),
        DB::raw("COUNT(*) as item_count"),
    ])
    ->join('sys_pm_units as u', 'u.id', '=', 'ri.unit_id')
    ->leftJoin('sys_pm_debtors_invoices as inv', 'inv.id', '=', 'ri.invoice_id')
    ->leftJoin('sys_pm_clients as t', 't.id', '=', 'inv.tenant_id')
    ->where('ri.property_report_id', $returnId)
    ->groupBy('ri.unit_code', 'u.id')
    ->limit(5)
    ->get();

echo "Grouped units:\n";
echo json_encode($units, JSON_PRETTY_PRINT);
echo "\n\n";

// Check if there's data in the items table
$itemsCount = DB::table('sys_reports_returns_items')
    ->where('property_report_id', $returnId)
    ->count();

echo "Items count for return $returnId: $itemsCount\n";

// Check if tenant_id is populated in invoices
$invoiceCheck = DB::table('sys_reports_returns_items as ri')
    ->join('sys_pm_debtors_invoices as inv', 'inv.id', '=', 'ri.invoice_id')
    ->where('ri.property_report_id', $returnId)
    ->select(['ri.invoice_id', 'inv.tenant_id'])
    ->limit(5)
    ->get();

echo "\nInvoice tenant mapping:\n";
echo json_encode($invoiceCheck, JSON_PRETTY_PRINT);
