<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Columns in cb_returns_merge_batches ===\n";
$cols = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name='cb_returns_merge_batches' ORDER BY ordinal_position");
foreach ($cols as $c) {
    echo "  - {$c->column_name}\n";
}

echo "\n=== Sample data ===\n";
$sample = DB::selectOne("SELECT * FROM cb_returns_merge_batches LIMIT 1");
if ($sample) {
    print_r($sample);
}

echo "\n=== DONE ===\n";
