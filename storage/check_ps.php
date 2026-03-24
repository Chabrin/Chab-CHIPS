<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$rows = \App\Models\ReturnClient::select('id', 'payable_status', 'entry_type', 'dr_cr')
    ->whereNotNull('payable_status')
    ->where('payable_status', '!=', 0)
    ->limit(10)
    ->get();

foreach ($rows as $r) {
    echo "id={$r->id} et={$r->entry_type} dc={$r->dr_cr} ps=" . var_export($r->payable_status, true) . " type=" . gettype($r->payable_status) . PHP_EOL;
}
