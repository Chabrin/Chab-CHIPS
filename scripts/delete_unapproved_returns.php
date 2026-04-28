<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$returnPeriod = '202604';

echo "=== Deleting unapproved April 2026 returns ===\n\n";

// Get unapproved return codes
$unapproved = DB::select("
    SELECT return_code 
    FROM cb_returns_summary 
    WHERE return_period = ? 
      AND approved_status != 1
", [$returnPeriod]);

$returnCodes = array_map(fn($r) => $r->return_code, $unapproved);

if (empty($returnCodes)) {
    echo "No unapproved returns found for period {$returnPeriod}\n";
    exit;
}

echo "Found " . count($returnCodes) . " unapproved returns to delete\n";

// Delete cb_returns_clients lines
$placeholders = implode(',', array_fill(0, count($returnCodes), '?'));
$clientsDeleted = DB::delete("
    DELETE FROM cb_returns_clients 
    WHERE return_period = ? 
      AND return_code IN ({$placeholders})
", array_merge([$returnPeriod], $returnCodes));

echo "Deleted {$clientsDeleted} lines from cb_returns_clients\n";

// Delete cb_returns_summary entries
$summariesDeleted = DB::delete("
    DELETE FROM cb_returns_summary 
    WHERE return_period = ? 
      AND approved_status != 1
", [$returnPeriod]);

echo "Deleted {$summariesDeleted} summaries from cb_returns_summary\n";

// Reset is_recovered flag on landlord account for these entries
$resetCount = DB::affectingStatement("
    UPDATE cb_landlord_account 
    SET is_recovered = 0, 
        recovery_year = NULL, 
        recovery_month = NULL, 
        return_code = NULL,
        recovery_server_time = NULL
    WHERE return_code IN ({$placeholders})
", $returnCodes);

echo "Reset {$resetCount} landlord account entries\n";

echo "\nDone! You can now regenerate April 2026 returns.\n";
