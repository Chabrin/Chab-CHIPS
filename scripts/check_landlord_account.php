<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== cb_landlord_account columns ===\n";
$cols = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'cb_landlord_account' ORDER BY ordinal_position");
foreach ($cols as $c) {
    echo $c->column_name . "\n";
}

echo "\n=== cb_landlord_account data for LAN-00324 ===\n";
$data = DB::select("SELECT * FROM cb_landlord_account WHERE landlord_code = 'LAN-00324' AND year = 2026 AND month IN (3, 4) ORDER BY month, id LIMIT 50");
foreach ($data as $row) {
    print_r($row);
}

echo "\n\n=== sys_set_invoiceable_items (0009, 0013, 0014) ===\n";
$items = DB::select("SELECT * FROM sys_set_invoiceable_items WHERE item_code IN ('0009', '0013', '0014')");
foreach ($items as $item) {
    print_r($item);
}
