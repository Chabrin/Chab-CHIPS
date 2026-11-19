<?php

namespace App\Services\UnitTenantUpload;

use App\Models\Property;
use App\Models\ServiceAgreement;
use App\Models\TempBatchTenant;
use App\Models\TempBatchUnit;
use App\Models\TempUploadBatch;
use App\Models\Unit;
use DomainException;
use Illuminate\Support\Facades\DB;

class UnitTenantUploadBatchService
{
    private UnitTenantUploadValidator $validator;
    private UnitTenantUploadLookups $lookups;

    /**
     * @param UnitTenantUploadValidator $validator
     * @param UnitTenantUploadLookups $lookups
     */
    public function __construct(UnitTenantUploadValidator $validator, UnitTenantUploadLookups $lookups)
    {
        $this->validator = $validator;
        $this->lookups = $lookups;
    }

    /**
     * Stage a parsed file as a new batch, but only when every row is clean.
     *
     * @param Property $property
     * @param array $parsed output of the parser
     * @param string $fileName
     * @param int|null $userId
     * @return TempUploadBatch
     */
    public function stage(Property $property, array $parsed, string $fileName, ?int $userId): TempUploadBatch
    {
        $batch = DB::transaction(function () use ($property, $parsed, $fileName, $userId) {
            // One upload at a time: the duplicate checks below must see every other upload's committed rows.
            DB::getDriverName() === 'pgsql' ? DB::select('SELECT pg_advisory_xact_lock(?)', [7240042]) : Property::whereKey($property->id)->lockForUpdate()->first();

            $errors = $this->validateUpload($property, $parsed);
            if ($errors) {
                throw new UnitTenantUploadRejected($errors);
            }

            $batch = TempUploadBatch::create([
                'property_id' => $property->id,
                'client_id' => $property->client_id,
                'file_name' => mb_substr($fileName, 0, 200),
                'status' => TempUploadBatch::STATUS_STAGED,
                'created_by' => $userId,
            ]);

            foreach ($parsed['units'] as $row) {
                TempBatchUnit::create(['batch_id' => $batch->id] + $this->canonical($row));
            }
            foreach (['individual', 'corporate'] as $sheet) {
                foreach ($parsed[$sheet] as $row) {
                    TempBatchTenant::create(['batch_id' => $batch->id, 'sheet' => $sheet] + $this->canonical($row));
                }
            }

            return $batch;
        });

        $this->refreshTotals($batch);

        return $batch->fresh();
    }

    /**
     * Every validation error in a parsed file, as a flat list; empty when the file is clean.
     *
     * @param Property $property
     * @param array $parsed
     * @return array
     */
    public function validateUpload(Property $property, array $parsed): array
    {
        $unitRows = $parsed['units'];
        $tenantRows = array_merge(
            array_map(fn($row) => $row + ['sheet' => 'individual'], $parsed['individual']),
            array_map(fn($row) => $row + ['sheet' => 'corporate'], $parsed['corporate'])
        );

        $existing = $this->validator->loadExisting($property->id, $unitRows, $tenantRows) + $this->validator->loadStaged($property->id, $tenantRows);
        $result = $this->validator->validate($unitRows, $tenantRows, $existing);
        $errors = [];

        if ($tenantRows && !ServiceAgreement::where('property_id', $property->id)->exists()) {
            $errors[] = ['sheet' => null, 'row' => null, 'unit_number' => null, 'message' => 'Property has no service agreement (SLA), so a lease cannot be created'];
        }

        foreach ($result['units'] as $i => $message) {
            if ($message) {
                $errors[] = ['sheet' => UnitTenantUploadColumns::SHEET_UNITS, 'row' => $unitRows[$i]['row_no'], 'unit_number' => $unitRows[$i]['unit_number'], 'message' => $message];
            }
        }
        foreach ($result['tenants'] as $i => $message) {
            if ($message) {
                $sheet = $tenantRows[$i]['sheet'] === 'corporate' ? UnitTenantUploadColumns::SHEET_CORPORATE : UnitTenantUploadColumns::SHEET_INDIVIDUAL;
                $errors[] = ['sheet' => $sheet, 'row' => $tenantRows[$i]['row_no'], 'unit_number' => $tenantRows[$i]['unit_number'], 'message' => $message];
            }
        }

        return $errors;
    }

    /**
     * Find a batch the current user may see (the property scope hides other users' properties).
     *
     * @param int $id
     * @return TempUploadBatch
     */
    public function find(int $id): TempUploadBatch
    {
        $batch = TempUploadBatch::findOrFail($id);
        Property::findOrFail($batch->property_id);

        return $batch;
    }

    /**
     * Re-run validation over every unsubmitted row and store the errors.
     *
     * @param TempUploadBatch $batch
     */
    public function revalidate(TempUploadBatch $batch): void
    {
        $units = $batch->units()->whereNull('submitted_at')->get()->keyBy('id');
        $tenants = $batch->tenants()->whereNull('submitted_at')->get()->keyBy('id');
        $unitRows = $units->map->toArray()->all();
        $tenantRows = $tenants->map->toArray()->all();

        $existing = $this->validator->loadExisting($batch->property_id, $unitRows, $tenantRows);
        $result = $this->validator->validate($unitRows, $tenantRows, $existing);

        foreach ($result['units'] as $id => $error) {
            if ($units[$id]->error !== $error) {
                $units[$id]->update(['error' => $error]);
            }
        }
        foreach ($result['tenants'] as $id => $error) {
            if ($tenants[$id]->error !== $error) {
                $tenants[$id]->update(['error' => $error]);
            }
        }

        $this->refreshTotals($batch);
    }

    /**
     * Edit a staged unit row. Renaming the unit number carries over to its tenant row.
     *
     * @param TempBatchUnit $row
     * @param array $data
     * @return TempBatchUnit
     */
    public function updateUnit(TempBatchUnit $row, array $data): TempBatchUnit
    {
        $batch = $this->editableBatch($row);
        $values = $this->canonical($this->clean($data, array_keys(UnitTenantUploadColumns::UNITS)));
        $oldNumber = $row->unit_number;

        return DB::transaction(function () use ($row, $values, $oldNumber, $batch) {
            $this->lockStaged($batch, $row);
            $row->update($values);

            if (array_key_exists('unit_number', $values) && $oldNumber !== null) {
                $batch->tenants()->whereNull('submitted_at')->whereRaw('LOWER(unit_number) = ?', [strtolower($oldNumber)])->update(['unit_number' => $values['unit_number']]);
            }

            return $this->keepIfClean($batch, $row);
        });
    }

    /**
     * Edit a staged tenant row (the unit number is locked to the units sheet); refused if it leaves errors.
     *
     * @param TempBatchTenant $row
     * @param array $data
     * @return TempBatchTenant
     */
    public function updateTenant(TempBatchTenant $row, array $data): TempBatchTenant
    {
        $batch = $this->editableBatch($row);
        $fields = array_diff(array_keys(UnitTenantUploadColumns::forSheet($row->sheet === 'corporate' ? UnitTenantUploadColumns::SHEET_CORPORATE : UnitTenantUploadColumns::SHEET_INDIVIDUAL)), ['unit_number']);

        return DB::transaction(function () use ($row, $fields, $data, $batch) {
            $this->lockStaged($batch, $row);
            $row->update($this->canonical($this->clean($data, $fields)));

            return $this->keepIfClean($batch, $row);
        });
    }

    /**
     * Re-validate the batch after an edit; throws (rolling the edit back) when the edited row has errors.
     *
     * @param TempUploadBatch $batch
     * @param TempBatchUnit|TempBatchTenant $row
     * @return TempBatchUnit|TempBatchTenant
     */
    private function keepIfClean(TempUploadBatch $batch, $row)
    {
        $this->revalidate($batch);
        $row->refresh();

        if ($row->error) {
            throw new DomainException($row->error);
        }

        return $row;
    }

    /**
     * Permanently delete a staged unit row and its tenant row.
     *
     * @param TempBatchUnit $row
     */
    public function deleteUnit(TempBatchUnit $row): void
    {
        $batch = $this->editableBatch($row);

        DB::transaction(function () use ($row, $batch) {
            $this->lockStaged($batch, $row);

            if ($row->unit_number !== null) {
                $batch->tenants()->whereNull('submitted_at')->whereRaw('LOWER(unit_number) = ?', [strtolower($row->unit_number)])->forceDelete();
            }
            $row->forceDelete();
            $this->revalidate($batch);
        });
    }

    /**
     * Permanently delete a staged tenant row (its unit stays and is created vacant).
     *
     * @param TempBatchTenant $row
     */
    public function deleteTenant(TempBatchTenant $row): void
    {
        $batch = $this->editableBatch($row);

        DB::transaction(function () use ($row, $batch) {
            $this->lockStaged($batch, $row);
            $row->forceDelete();
            $this->revalidate($batch);
        });
    }

    /**
     * Permanently delete a batch and its rows; refused once any row was submitted.
     *
     * @param TempUploadBatch $batch
     */
    public function deleteBatch(TempUploadBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $locked = TempUploadBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === TempUploadBatch::STATUS_SUBMITTING) {
                throw new DomainException('This batch is being submitted.');
            }
            if ($locked->status === TempUploadBatch::STATUS_SUBMITTED || $batch->units()->whereNotNull('submitted_at')->exists() || $batch->tenants()->whereNotNull('submitted_at')->exists()) {
                throw new DomainException('This batch has submitted rows and is kept as a record.');
            }

            $batch->units()->withTrashed()->forceDelete();
            $batch->tenants()->withTrashed()->forceDelete();
            $locked->forceDelete();
        });
    }

    /**
     * Batches of a property for the list table, newest first.
     *
     * @param Property $property
     * @return array
     */
    public function listFor(Property $property): array
    {
        return TempUploadBatch::where('property_id', $property->id)->orderByDesc('id')->get()->map(function ($batch) {
            return [
                'id' => $batch->id,
                'file_name' => $batch->file_name,
                'status' => $batch->status,
                'total_units' => $batch->total_units,
                'total_tenants' => $batch->total_tenants,
                'submitted_units' => $batch->units()->whereNotNull('submitted_at')->count(),
                'error_rows' => $batch->units()->whereNull('submitted_at')->whereNotNull('error')->count() + $batch->tenants()->whereNull('submitted_at')->whereNotNull('error')->count(),
                'created_at' => $batch->created_at->format('Y-m-d H:i'),
            ];
        })->all();
    }

    /**
     * Everything the review screen needs for one batch.
     *
     * @param TempUploadBatch $batch
     * @return array
     */
    public function details(TempUploadBatch $batch): array
    {
        $present = fn($row) => $row->toArray() + ['state' => $row->submitted_at ? 'submitted' : ($row->error ? 'error' : 'ready')];
        $property = $batch->property;
        $withCode = fn($row) => $present($row) + ['unit_code' => $property ? Unit::generateUnitCode($property, $row->unit_number) : $row->unit_number];

        return [
            'batch' => $batch->only(['id', 'file_name', 'status', 'total_units', 'total_tenants']),
            'units' => $batch->units()->get()->map($withCode)->all(),
            'tenants' => $batch->tenants()->get()->map($present)->all(),
            'columns' => [
                'units' => UnitTenantUploadColumns::UNITS,
                'individual' => UnitTenantUploadColumns::INDIVIDUAL,
                'corporate' => UnitTenantUploadColumns::CORPORATE,
            ],
            'options' => [
                'tenant_type' => $this->lookups->names('tenant_types'),
                'unit_type' => $this->lookups->names('unit_types'),
                'usage_type' => $this->lookups->names('usage_types'),
                'topology' => $this->lookups->names('topologies'),
                'unit_category' => $this->lookups->names('categories'),
                'gender' => $this->lookups->names('genders'),
                'title' => $this->lookups->names('titles'),
                'nationality' => $this->lookups->names('countries'),
                'vat_able' => ['0', '1'],
            ],
        ];
    }

    /**
     * Update the batch's row totals from its live rows.
     *
     * @param TempUploadBatch $batch
     */
    public function refreshTotals(TempUploadBatch $batch): void
    {
        $batch->update(['total_units' => $batch->units()->count(), 'total_tenants' => $batch->tenants()->count()]);
    }

    /**
     * Inside a transaction: lock the batch row so a submit can't start halfway through the change,
     * then make sure the batch is still staged and the row is not yet submitted.
     *
     * @param TempUploadBatch $batch
     * @param TempBatchUnit|TempBatchTenant $row
     */
    private function lockStaged(TempUploadBatch $batch, $row): void
    {
        $locked = TempUploadBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== TempUploadBatch::STATUS_STAGED) {
            throw new DomainException('This batch can no longer be edited.');
        }

        $row->refresh();
        if ($row->submitted_at) {
            throw new DomainException('This row has already been submitted.');
        }
    }

    /**
     * The row's batch, if it still allows edits.
     *
     * @param TempBatchUnit|TempBatchTenant $row
     * @return TempUploadBatch
     */
    private function editableBatch($row): TempUploadBatch
    {
        $batch = $this->find($row->batch_id);

        if ($batch->status !== TempUploadBatch::STATUS_STAGED) {
            throw new DomainException('This batch can no longer be edited.');
        }
        if ($row->submitted_at) {
            throw new DomainException('This row has already been submitted.');
        }

        return $batch;
    }

    /**
     * Replace dropdown values with their canonical names ("caretaker" becomes "Caretaker"); unknown values are left for the validator.
     *
     * @param array $row
     * @return array
     */
    private function canonical(array $row): array
    {
        if (array_key_exists('vat_able', $row)) {
            $row['vat_able'] = UnitTenantUploadColumns::normalizeVat($row['vat_able']);
        }
        $lists = ['unit_type' => 'unit_types', 'usage_type' => 'usage_types', 'topology' => 'topologies', 'unit_category' => 'categories', 'tenant_type' => 'tenant_types', 'gender' => 'genders', 'title' => 'titles', 'nationality' => 'countries'];

        foreach ($lists as $field => $list) {
            if (!empty($row[$field]) && ($name = $this->lookups->name($list, $row[$field]))) {
                $row[$field] = $name;
            }
        }

        return $row;
    }

    /**
     * Keep only the allowed fields, with blanks stored as null.
     *
     * @param array $data
     * @param array $allowed
     * @return array
     */
    private function clean(array $data, array $allowed): array
    {
        $clean = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data) && (is_scalar($data[$field]) || $data[$field] === null)) {
                $value = trim((string) $data[$field]);
                $clean[$field] = $value === '' ? null : $value;
            }
        }

        return $clean;
    }
}
