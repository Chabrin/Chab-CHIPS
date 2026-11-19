<?php

namespace App\Services\UnitTenantUpload;

use App\Models\Client;
use App\Models\Gender;
use App\Models\Property;
use App\Models\Salutation;
use App\Models\TempBatchTenant;
use App\Models\TempBatchUnit;
use App\Models\TempUploadBatch;
use App\Models\TenantNextOfKin;
use App\Models\TenantOtherContact;
use App\Models\TenantRecord;
use App\Models\Unit;
use App\Services\ServiceAgreementApprovalService;
use App\Traits\LeaseCreationTrait;
use App\Traits\TenantRecordHelperTrait;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitTenantSubmitService
{
    use TenantRecordHelperTrait;
    use LeaseCreationTrait;

    private const LOCK_KEY = 7240041;

    private UnitTenantUploadBatchService $batches;
    private UnitTenantUploadLookups $lookups;
    private ServiceAgreementApprovalService $slaApprovals;

    /**
     * @param UnitTenantUploadBatchService $batches
     * @param UnitTenantUploadLookups $lookups
     * @param ServiceAgreementApprovalService $slaApprovals
     */
    public function __construct(UnitTenantUploadBatchService $batches, UnitTenantUploadLookups $lookups, ServiceAgreementApprovalService $slaApprovals)
    {
        $this->batches = $batches;
        $this->lookups = $lookups;
        $this->slaApprovals = $slaApprovals;
    }

    /**
     * Submit every ready row of a batch: each unit and its tenant (with a 1-year lease) is one transaction.
     *
     * @param TempUploadBatch $batch
     * @param int|null $userId
     * @return array ['submitted' => int, 'failed' => int]
     */
    public function submit(TempUploadBatch $batch, ?int $userId): array
    {
        if (!$this->resolveSlaId($batch->property_id) && $batch->tenants()->whereNull('submitted_at')->exists()) {
            throw new DomainException('Property has no service agreement (SLA), so a lease cannot be created');
        }

        if (!$batch->units()->whereNull('submitted_at')->exists() && !$batch->tenants()->whereNull('submitted_at')->exists()) {
            throw new DomainException('There is nothing to submit.');
        }

        // A batch stuck in "submitting" for 15+ minutes (e.g. a timed-out request) can be claimed again.
        $claimed = TempUploadBatch::where('id', $batch->id)
            ->where(fn($q) => $q->where('status', TempUploadBatch::STATUS_STAGED)->orWhere(fn($q2) => $q2->where('status', TempUploadBatch::STATUS_SUBMITTING)->where('updated_at', '<', now()->subMinutes(15))))
            ->update(['status' => TempUploadBatch::STATUS_SUBMITTING]);

        if (!$claimed) {
            throw new DomainException('This batch is not available to submit.');
        }

        $submitted = 0;
        $failed = 0;

        try {
            $property = Property::findOrFail($batch->property_id);
            $this->batches->revalidate($batch);
            $slaId = $this->resolveSlaId($property->id);

            foreach ($batch->units()->whereNull('submitted_at')->get() as $unitRow) {
                $tenantRow = $unitRow->unit_number === null ? null : $batch->tenants()->whereNull('submitted_at')->whereRaw('LOWER(unit_number) = ?', [strtolower($unitRow->unit_number)])->first();

                if ($unitRow->error || ($tenantRow && $tenantRow->error)) {
                    if ($tenantRow && !$tenantRow->error) {
                        $tenantRow->update(['error' => 'The unit on this line has an error.']);
                    }
                    if (!$unitRow->error) {
                        $unitRow->update(['error' => 'The tenant on this line has an error.']);
                    }
                    $failed++;
                    continue;
                }

                try {
                    DB::transaction(fn() => $this->submitPair($unitRow, $tenantRow, $property, $slaId, $userId));
                    $submitted++;
                } catch (\Throwable $e) {
                    $message = $this->failureMessage($e);
                    $unitRow->update(['error' => $message]);
                    $tenantRow?->update(['error' => $message]);
                    $failed++;
                }
            }
        } finally {
            $this->closeBatch($batch, $userId);
        }

        return ['submitted' => $submitted, 'failed' => $failed];
    }

    /**
     * Short, safe message for a failed row; database errors are logged in full instead of shown.
     *
     * @param \Throwable $e
     * @return string
     */
    private function failureMessage(\Throwable $e): string
    {
        if ($e instanceof QueryException) {
            Log::error('Units & tenants upload row failed: ' . $e->getMessage());

            $names = ['sys_pm_units' => 'unit', 'sys_pm_clients' => 'tenant', 'cb_tenant_records' => 'tenant profile', 'tenants_other_contacts' => 'other contact', 'tenants_nextof_kin' => 'next of kin', 'sys_pm_lease_agreements' => 'lease'];
            preg_match('/insert into "?(\w+)"?/i', $e->getSql(), $table);
            $what = $names[$table[1] ?? ''] ?? null;

            return 'The database rejected a value while saving the ' . ($what ?? 'record') . ' (a value is too long or out of range; National ID, phone and KRA PIN allow 20 characters).';
        }

        return $e->getMessage();
    }

    /**
     * Serialise uploads for the rest of this transaction (released on commit/rollback), so the
     * free-checks and the tenant reference number can't race with another submit.
     *
     * @param Property $property
     */
    private function lockUploads(Property $property): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [self::LOCK_KEY]);
        } else {
            Property::whereKey($property->id)->lockForUpdate()->first();
        }
    }

    /**
     * Next tenant reference (TEN-000001 style); only safe inside the upload lock. On PostgreSQL it reads
     * the highest six-digit reference from the index (about 2 ms); otherwise the shared slow generator.
     *
     * @return string
     */
    private function nextTenantReference(): string
    {
        if (DB::getDriverName() !== 'pgsql') {
            return $this->generateTenantReferenceNumber();
        }

        $latest = Client::withAll()->whereRaw("reference_number ~ '^TEN-[0-9]{6}$'")->max('reference_number');

        if ($latest === null || $latest === 'TEN-999999') {
            return $this->generateTenantReferenceNumber();
        }

        return 'TEN-' . str_pad((int) substr($latest, 4) + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Mark the batch submitted when nothing is left, otherwise return it to staged.
     *
     * @param TempUploadBatch $batch
     * @param int|null $userId
     */
    private function closeBatch(TempUploadBatch $batch, ?int $userId): void
    {
        $remaining = $batch->units()->whereNull('submitted_at')->count() + $batch->tenants()->whereNull('submitted_at')->count();

        // The claim was a query-level update, so the in-memory model is stale: update by key.
        if ($remaining === 0) {
            TempUploadBatch::whereKey($batch->id)->update(['status' => TempUploadBatch::STATUS_SUBMITTED, 'submitted_at' => now(), 'submitted_by' => $userId]);
        } else {
            TempUploadBatch::whereKey($batch->id)->update(['status' => TempUploadBatch::STATUS_STAGED]);
        }
        $batch->refresh();
    }

    /**
     * Create a unit and, when there is a tenant row, the tenant and lease; flag the rows submitted.
     *
     * @param TempBatchUnit $unitRow
     * @param TempBatchTenant|null $tenantRow
     * @param Property $property
     * @param int|null $slaId
     * @param int|null $userId
     */
    private function submitPair(TempBatchUnit $unitRow, ?TempBatchTenant $tenantRow, Property $property, ?int $slaId, ?int $userId): void
    {
        $this->assertStillFree($unitRow, $tenantRow, $property);

        $unit = $this->createUnit($unitRow, $property, $userId);

        // Pending unless the property's SLA is already approved.
        $this->slaApprovals->approveNewUnits($property->id, [$unit->id], $userId, 'unit_tenant_upload');

        $unitRow->update(['submitted_at' => now(), 'unit_id' => $unit->id, 'error' => null]);

        if (!$tenantRow) {
            return;
        }

        if (!$slaId) {
            throw new DomainException('Property has no service agreement (SLA), so a lease cannot be created');
        }

        $tenant = $this->createTenant($tenantRow, $property, $unit, $slaId, $userId);
        $lease = $this->createLeaseAgreementRecord([
            'client_id' => $property->client_id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'sla_id' => $slaId,
            'date_start' => (new \DateTime($tenantRow->lease_start_date))->format('Y-m-d'),
            'date_end' => $tenantRow->lease_end_date ? (new \DateTime($tenantRow->lease_end_date))->format('Y-m-d') : null,
            'open_ended' => !$tenantRow->lease_end_date,
            'auto_renewal' => 'No',
            'escalation_rate' => 0,
            'escalation_frequency' => 'Annually',
        ]);

        app(\App\Services\TenantReviewService::class)->register($tenant, $lease, \App\Models\User::findOrFail($userId));
        $tenantRow->update(['submitted_at' => now(), 'tenant_id' => $tenant->id, 'lease_id' => $lease->id, 'error' => null]);
    }

    /**
     * Whole lease years for the tenant record: null for an open-ended lease, otherwise the start-to-end span rounded (minimum 1).
     *
     * @param TempBatchTenant $row
     * @return int|null
     */
    private function leaseYears(TempBatchTenant $row): ?int
    {
        if (!$row->lease_end_date) {
            return null;
        }

        $days = (new \DateTime($row->lease_start_date))->diff(new \DateTime($row->lease_end_date))->days;

        return max(1, (int) round($days / 365.25));
    }

    /**
     * Last check right before creating a pair: the unit number and the tenant's ID, passport and
     * registration number must still be free. Takes the upload lock first so two submits can't race.
     *
     * @param TempBatchUnit $unitRow
     * @param TempBatchTenant|null $tenantRow
     * @param Property $property
     */
    private function assertStillFree(TempBatchUnit $unitRow, ?TempBatchTenant $tenantRow, Property $property): void
    {
        $this->lockUploads($property);

        if (Unit::where('property_id', $property->id)->whereRaw('LOWER(unit_number) = ?', [mb_strtolower($unitRow->unit_number)])->exists()) {
            throw new DomainException("Unit Number '{$unitRow->unit_number}' already exists under this property");
        }

        if (!$tenantRow) {
            return;
        }

        $taken = [
            'National ID' => $tenantRow->national_id && TenantRecord::whereRaw('LOWER(national_id) = ?', [mb_strtolower($tenantRow->national_id)])->exists(),
            'Passport No' => $tenantRow->passport_no && TenantRecord::whereRaw('LOWER(passport_number) = ?', [mb_strtolower($tenantRow->passport_no)])->exists(),
            'Registration Number' => $tenantRow->registration_number && Client::withAll()->where('type_id', 2)->whereRaw('LOWER(company_registration_number) = ?', [mb_strtolower($tenantRow->registration_number)])->exists(),
        ];

        foreach ($taken as $label => $exists) {
            if ($exists) {
                throw new DomainException("{$label} already belongs to an existing tenant");
            }
        }
    }

    /**
     * Create the unit from a staged row.
     *
     * @param TempBatchUnit $row
     * @param Property $property
     * @param int|null $userId
     * @return Unit
     */
    private function createUnit(TempBatchUnit $row, Property $property, ?int $userId): Unit
    {
        $unitTypeId = $this->lookups->id('unit_types', $row->unit_type) ?? $this->lookups->id('unit_types', 'Normal');

        $unitCode = Unit::generateUnitCode($property, $row->unit_number);

        return Unit::create([
            'unit_code' => $unitCode,
            'property_id' => $property->id,
            'client_id' => $property->client_id,
            'zone_id' => $property->zone_id,
            'unit_number' => $row->unit_number,
            'unit_name' => $unitCode,
            'unit_type_id' => (string) $unitTypeId,
            'usage_type_id' => $this->lookups->id('usage_types', $row->usage_type),
            'topology_id' => $this->lookups->id('topologies', $row->topology),
            'category_id' => $this->lookups->id('categories', $row->unit_category),
            'unit_condition_id' => $row->unit_condition,
            'rent_amount' => (float) str_replace(',', '', $row->rent_amount),
            'description' => $row->unit_description,
            'vat_able' => UnitTenantUploadColumns::normalizeVat($row->vat_able) === '1' ? 'Yes' : 'No',
            'initial_water_meter_reading' => (float) str_replace(',', '', $row->initial_water_reading),
            'date_time' => now(),
            'created_by_id' => $userId,
            'occupancy_status_id' => 31,
            'current_status_id' => 1,
        ]);
    }

    /**
     * Create the tenant (Client + TenantRecord + contacts) from a staged row.
     *
     * @param TempBatchTenant $row
     * @param Property $property
     * @param Unit $unit
     * @param int $slaId
     * @param int|null $userId
     * @return Client
     */
    private function createTenant(TempBatchTenant $row, Property $property, Unit $unit, int $slaId, ?int $userId): Client
    {
        $isCompany = $row->sheet === 'corporate';
        $typeName = $this->lookups->name('tenant_types', $row->tenant_type) ?: ($isCompany ? 'COMPANY' : 'INDIVIDUAL');
        $title = $isCompany ? null : $row->title;
        $names = $isCompany ? $row->company_name : trim($title . ' ' . $row->names);
        $genderName = $isCompany ? null : $this->lookups->name('genders', $row->gender);
        $country = $this->lookups->name('countries', $row->nationality);

        $tenant = Client::create([
            'reference_number' => $this->nextTenantReference(),
            'names' => $names,
            'title' => $title,
            'gender' => $genderName ? ucfirst(strtolower($genderName)) : null,
            'mobile_number' => $row->mobile_no,
            'email_address' => $row->email,
            'pin_number' => $row->kra_pin,
            'national_id' => $isCompany ? null : $row->national_id,
            'passport_number' => $isCompany ? null : $row->passport_no,
            'nationality_id' => $this->lookups->id('countries', $row->nationality),
            'registered_date' => now()->toDateString(),
            'lease_start_date' => (new \DateTime($row->lease_start_date))->format('Y-m-d'),
            'lease_years' => $this->leaseYears($row),
            'escalation_rate' => 0,
            'frequency' => 'Annually',
            'prefered_messages_language_id' => 'English',
            'client_type_id' => $isCompany ? 3 : 1,
            'type_id' => 2,
            'company_name' => $isCompany ? $row->company_name : null,
            'company_registration_number' => $isCompany ? $row->registration_number : null,
            'contact_person_name' => $isCompany ? $row->contact_name : null,
            'contact_person_mobile' => $isCompany ? $row->contact_phone : null,
            'contact_person_email' => $isCompany ? $row->contact_email : null,
            'property_id' => $property->id,
            'sla_id' => $slaId,
            'unit_id' => $unit->id,
            'rent_amount' => $this->resolveRentAmount($unit->id),
            'overdraft_penalty' => 5,
            'created_by' => $userId,
            'date_time' => now(),
            'current_status_id' => 1,
        ]);

        TenantRecord::updateOrCreate(['id' => $tenant->id], [
            'tenant_type' => $typeName,
            'company_registration_number' => $isCompany ? $row->registration_number : null,
            'salutation_id' => $title ? Salutation::whereRaw('LOWER(name) = ?', [mb_strtolower($title)])->value('id') : null,
            'names' => $names,
            'gender_id' => $genderName ? Gender::where('name', strtoupper($genderName))->value('id') : null,
            'phone' => $row->mobile_no,
            'email' => $row->email,
            'nationality' => $country,
            'id_document_type' => !$isCompany && $row->national_id ? 'NATIONAL_ID' : (!$isCompany && $row->passport_no ? 'PASSPORT' : null),
            'national_id' => $isCompany ? null : $row->national_id,
            'passport_number' => $isCompany ? null : $row->passport_no,
            'kra_pin' => $row->kra_pin,
            'registered_date' => now()->toDateString(),
        ]);

        if (!$isCompany && $row->other_contact_name) {
            TenantOtherContact::create(['tenant_id' => $tenant->id, 'names' => $row->other_contact_name, 'email' => $row->other_contact_email, 'mobile_number' => $row->other_contact_phone]);
        }

        if ($row->nok_name) {
            TenantNextOfKin::create(['tenant_id' => $tenant->id, 'kin_names' => $row->nok_name, 'mobile_number' => $row->nok_phone, 'email_address' => $row->nok_email, 'relationship' => $row->nok_relationship, 'national_id' => $row->nok_id]);
        }

        return $tenant;
    }
}
