<?php

namespace App\Console\Commands;

use App\Models\Gender;
use App\Models\Salutation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateTenantRecords extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenants:migrate-records
        {--dry-run : Report what would happen without writing anything}
        {--chunk=500 : Number of sys_pm_clients rows to process per batch}';

    /**
     * The console command description.
     */
    protected $description = 'Copy tenant rows from sys_pm_clients (type_id=2) into cb_tenant_records, using the same id (shared primary key). Safe to re-run — rows already copied are skipped.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        $genderMap = Gender::pluck('id', 'name'); // name already uppercase, e.g. 'MALE' => 1
        $salutationMap = Salutation::pluck('id', 'name'); // e.g. 'Mr' => 1

        if ($genderMap->isEmpty() || $salutationMap->isEmpty()) {
            $this->error('Gender/Salutation lookup tables are empty — run `php artisan db:seed --class=TenantLookupsSeeder` first.');
            return Command::FAILURE;
        }

        $alreadyMigrated = DB::table('cb_tenant_records')
            ->pluck('id')
            ->flip(); // id => true, for O(1) lookup — id mirrors sys_pm_clients.id

        $totalTenants = DB::table('sys_pm_clients')->where('type_id', 2)->count();
        $this->info("Found {$totalTenants} tenant rows in sys_pm_clients (type_id=2). Already migrated: {$alreadyMigrated->count()}.");

        if ($dryRun) {
            $this->info('--dry-run: no rows will be written.');
        }

        $inserted = 0;
        $skipped = 0;
        $errors = 0;

        DB::table('sys_pm_clients')
            ->where('type_id', 2)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($clients) use (
                $genderMap,
                $salutationMap,
                $alreadyMigrated,
                $dryRun,
                &$inserted,
                &$skipped,
                &$errors
            ) {
                $rows = [];
                $now = now();

                foreach ($clients as $client) {
                    if ($alreadyMigrated->has($client->id)) {
                        $skipped++;
                        continue;
                    }

                    try {
                        $rows[] = $this->mapClientToTenantRecord($client, $genderMap, $salutationMap, $now);
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->error("Failed mapping sys_pm_clients.id={$client->id}: {$e->getMessage()}");
                    }
                }

                if (!empty($rows) && !$dryRun) {
                    DB::transaction(function () use ($rows) {
                        DB::table('cb_tenant_records')->insert($rows);
                    });
                }

                $inserted += count($rows);
                $this->info("Processed batch ending at sys_pm_clients.id={$clients->last()->id} — inserted so far: {$inserted}, skipped: {$skipped}, errors: {$errors}");
            });

        $this->info("Done. Inserted: {$inserted}, Skipped (already migrated): {$skipped}, Errors: {$errors}.");

        return Command::SUCCESS;
    }

    /**
     * Maps one sys_pm_clients row to a cb_tenant_records insert array.
     *
     * company_name is empty for every tenant row in the live data (checked
     * before writing this), so tenant_type only distinguishes INDIVIDUAL vs
     * COMPANY here — CARETAKER/OWNERS have no reliable source field to derive
     * from and are left for manual/future classification.
     */
    private function mapClientToTenantRecord(object $client, $genderMap, $salutationMap, $now): array
    {
        $addressParts = array_filter([
            trim((string) $client->address),
            trim((string) $client->address_2),
            trim((string) $client->address_3),
        ], fn($part) => $part !== '');
        $physicalAddress = implode(', ', $addressParts);
        $physicalAddress = $physicalAddress !== '' ? mb_substr($physicalAddress, 0, 500) : null;

        $genderName = $client->gender ? strtoupper(trim($client->gender)) : null;
        $genderId = $genderName ? ($genderMap[$genderName] ?? null) : null;

        $title = $client->title ? trim($client->title) : null;
        $salutationId = $title ? ($salutationMap[$title] ?? null) : null;

        $nationalId = $client->national_id ? trim($client->national_id) : null;
        $passportNumber = $client->passport_number ? trim($client->passport_number) : null;
        $idDocumentType = $nationalId !== null && $nationalId !== ''
            ? 'NATIONAL_ID'
            : ($passportNumber !== null && $passportNumber !== '' ? 'PASSPORT' : null);

        $companyName = $client->company_name ? trim($client->company_name) : null;
        $tenantType = ($companyName !== null && $companyName !== '') ? 'COMPANY' : 'INDIVIDUAL';

        return [
            'id' => $client->id,
            'tenant_type' => $tenantType,
            'salutation_id' => $salutationId,
            'names' => $client->names ?: null,
            'gender_id' => $genderId,
            'phone' => $client->mobile_number ?: null,
            'email' => $client->email_address ?: null,
            'physical_address' => $physicalAddress,
            'nationality' => null, // source nationality_id has no resolvable lookup table (checked: only 6/74,838 rows even set it)
            'id_document_type' => $idDocumentType,
            'national_id' => $nationalId ?: null,
            'passport_number' => $passportNumber ?: null,
            'kra_pin' => $client->pin_number ?: null,
            'registered_date' => $client->registered_date ? substr($client->registered_date, 0, 10) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
