<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Get columns
$cols = DB::select("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = 'sys_pm_tenants_accounts_trans' ORDER BY ordinal_position");
foreach ($cols as $c) {
    echo str_pad($c->column_name, 35) . "| " . str_pad($c->data_type, 25) . "| null:" . $c->is_nullable . " | def:" . $c->column_default . "\n";
}

// Get a sample row
echo "\n-- SAMPLE ROW --\n";
$row = DB::table('sys_pm_tenants_accounts_trans')->first();
if ($row) {
    foreach ((array)$row as $k => $v) {
        echo str_pad($k, 35) . "=> " . $v . "\n";
    }
} else {
    echo "(no rows)\n";
}
