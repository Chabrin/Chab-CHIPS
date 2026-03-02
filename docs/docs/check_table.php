<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get sample data
$sample = DB::table('sys_reports_returns_properties')
    ->where('current_status_id', 5)
    ->orWhere('current_status_id', 17)
    ->first();

if ($sample) {
    echo "Sample row from sys_reports_returns_properties:\n";
    echo json_encode($sample, JSON_PRETTY_PRINT);
} else {
    echo "No records found with status 5 or 17\n";

    // Try any record
    $any = DB::table('sys_reports_returns_properties')->first();
    if ($any) {
        echo "\nAny record from table:\n";
        echo json_encode($any, JSON_PRETTY_PRINT);
    }
}
