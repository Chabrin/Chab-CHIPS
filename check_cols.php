<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== sys_set_bank_accounts columns ===\n";
$cols = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name='sys_set_bank_accounts' ORDER BY ordinal_position");
foreach ($cols as $c) echo $c->column_name . "\n";
