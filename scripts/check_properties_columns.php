<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$cols = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'sys_pm_properties' ORDER BY ordinal_position");
echo "Columns in sys_pm_properties:\n";
foreach ($cols as $c) {
    echo "  - " . $c->column_name . "\n";
}
