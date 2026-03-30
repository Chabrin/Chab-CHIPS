<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// 1. Check Property model columns relevant to linking
echo "=== sys_pm_properties sample 3 ===\n";
$props = DB::table('sys_pm_properties')->select('id', 'property_name', 'reference_number')->limit(3)->get();
foreach ($props as $p) {
    echo "id={$p->id} ref={$p->reference_number} name={$p->property_name}\n";
}

// 2. Check cb_landlord_account columns for property linking
echo "\n=== cb_landlord_account: property_code samples (NULL property_id rows) ===\n";
$nullProp = DB::table('cb_landlord_account')
    ->whereNull('property_id')
    ->whereNotNull('property_code')
    ->select('id', 'client_id', 'property_id', 'property_code', 'landlord_code')
    ->limit(5)->get();
foreach ($nullProp as $r) {
    echo "id={$r->id} client_id={$r->client_id} property_id=NULL property_code={$r->property_code}\n";
}

// 3. Try cross-matching property_code in cb_landlord_account with reference_number in sys_pm_properties
echo "\n=== Cross-match: landlord_account.property_code vs properties.reference_number ===\n";
$cross = DB::table('cb_landlord_account as la')
    ->join('sys_pm_properties as p', 'p.reference_number', '=', 'la.property_code')
    ->whereNotNull('la.client_id')
    ->select('p.id as prop_id', 'p.reference_number', 'la.client_id', 'la.landlord_code')
    ->distinct()
    ->limit(5)->get();
foreach ($cross as $r) {
    echo "prop_id={$r->prop_id} ref={$r->reference_number} client_id={$r->client_id} landlord={$r->landlord_code}\n";
}
echo "Count=" . DB::table('cb_landlord_account as la')
    ->join('sys_pm_properties as p', 'p.reference_number', '=', 'la.property_code')
    ->whereNotNull('la.client_id')->count() . "\n";
