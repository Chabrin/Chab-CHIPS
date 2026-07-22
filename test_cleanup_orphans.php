<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Unit;
use App\Models\Client;

Client::withoutGlobalScopes()->where('id', 70292)->delete();
Unit::whereIn('id', [50084, 50085, 50086, 50087, 50109, 50110])->delete();

echo "Remaining test units: " . Unit::whereIn('id', [50084, 50085, 50086, 50087, 50109, 50110])->count() . " (expect 0)\n";
echo "Remaining test tenant: " . Client::withoutGlobalScopes()->where('id', 70292)->count() . " (expect 0)\n";
