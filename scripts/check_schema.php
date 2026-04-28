<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== cb_returns_clients columns ===\n";
$cols = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'cb_returns_clients' ORDER BY ordinal_position");
foreach ($cols as $c) {
    echo $c->column_name . "\n";
}

echo "\n=== cb_returns_summary columns ===\n";
$cols = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'cb_returns_summary' ORDER BY ordinal_position");
foreach ($cols as $c) {
    echo $c->column_name . "\n";
}
