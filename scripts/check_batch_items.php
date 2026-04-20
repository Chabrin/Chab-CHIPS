<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Check cb_returns_merge_batch_items columns
echo "Columns in cb_returns_merge_batch_items:\n";
$cols = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'cb_returns_merge_batch_items' ORDER BY ordinal_position");
foreach ($cols as $c) {
    echo "  - " . $c->column_name . "\n";
}

// Get a sample row
echo "\nSample data from cb_returns_merge_batch_items:\n";
$sample = DB::selectOne("SELECT * FROM cb_returns_merge_batch_items LIMIT 1");
if ($sample) {
    print_r($sample);
} else {
    echo "No data\n";
}

// Get a sample row from merge_batches
echo "\nSample data from cb_returns_merge_batches:\n";
$sample = DB::selectOne("SELECT * FROM cb_returns_merge_batches LIMIT 1");
if ($sample) {
    print_r($sample);
} else {
    echo "No data\n";
}
