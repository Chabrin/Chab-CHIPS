<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$cols = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'sys_pm_zones_assignments' ORDER BY ordinal_position");
echo "Schema for sys_pm_zones_assignments:\n";
foreach ($cols as $c) {
    echo sprintf("  %-20s => %s\n", $c->column_name, $c->data_type);
}
