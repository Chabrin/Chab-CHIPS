<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$rows = DB::select("SELECT table_schema FROM information_schema.tables WHERE table_name='sys_set_bank_accounts'");
foreach ($rows as $r) echo "schema: " . $r->table_schema . "\n";

$rows2 = DB::select("SELECT table_schema FROM information_schema.tables WHERE table_name='sys_set_banks'");
foreach ($rows2 as $r) echo "sys_set_banks schema: " . $r->table_schema . "\n";
