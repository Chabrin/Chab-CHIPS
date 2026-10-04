<?php

namespace App\Services\UnitTenantUpload;

use App\Models\Client;
use App\Models\TenantRecord;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class UnitTenantUploadValidator
{
    /** Plain ASCII only, so case-insensitive matching behaves the same in PHP and the database. */
    private const UNIT_NUMBER_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9 \-\/.]*$/';
    private const IDENTIFIER_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9 \-\/.+]*$/';

    private UnitTenantUploadLookups $lookups;

    /**
     * @param UnitTenantUploadLookups $lookups
     */
    public function __construct(UnitTenantUploadLookups $lookups)
    {
        $this->lookups = $lookups;
    }

    /**
     * Validate staged unit and tenant rows; returns an error string (or null) per row id.
     *
     * @param array $unitRows rows keyed by id
     * @param array $tenantRows rows keyed by id, each with a 'sheet' of individual or corporate
     * @param array $existing output of loadExisting()
     * @return array ['units' => [id => ?string], 'tenants' => [id => ?string]]
     */
    public function validate(array $unitRows, array $tenantRows, array $existing): array
    {
        $context = $this->buildContext($unitRows, $tenantRows);
        $result = ['units' => [], 'tenants' => []];

        foreach ($unitRows as $id => $row) {
            $result['units'][$id] = $this->join($this->unitErrors($row, $context, $existing));
        }

        foreach ($tenantRows as $id => $row) {
            $result['tenants'][$id] = $this->join($this->tenantErrors($row, $context, $existing));
        }

        return $result;
    }

    /**
     * Look up what already exists in the database for the values in these rows.
     *
     * @param int $propertyId
     * @param array $unitRows
     * @param array $tenantRows
     * @return array
     */
    public function loadExisting(int $propertyId, array $unitRows, array $tenantRows): array
    {
        $values = fn(string $field) => array_values(array_unique(array_map('mb_strtolower', array_filter(array_column($tenantRows, $field)))));
        $found = function ($query, string $column, array $lowered) {
            if (!$lowered) {
                return [];
            }

            return $query->whereRaw('LOWER(' . $column . ') IN (' . implode(',', array_fill(0, count($lowered), '?')) . ')', $lowered)->pluck($column)->all();
        };

        return [
            'unit_numbers' => $this->set(Unit::where('property_id', $propertyId)->pluck('unit_number')->all()),
            'national_ids' => $this->set($found(TenantRecord::query(), 'national_id', $values('national_id'))),
            'passports' => $this->set($found(TenantRecord::query(), 'passport_number', $values('passport_no'))),
            'registrations' => $this->set($found(Client::withAll()->where('type_id', 2), 'company_registration_number', $values('registration_number'))),
        ];
    }

    /**
     * Units and tenant identifiers already waiting in another staged upload (value => batch id).
     * Only used when a new file is uploaded; submitting or editing a batch ignores other batches.
     *
     * @param int $propertyId
     * @param array $tenantRows
     * @return array
     */
    public function loadStaged(int $propertyId, array $tenantRows): array
    {
        $staged = fn(string $table) => DB::table($table . ' as r')
            ->join('temp_upload_batches as b', 'b.id', '=', 'r.batch_id')
            ->whereNull('r.deleted_at')->whereNull('b.deleted_at')->whereNull('r.submitted_at')
            ->whereIn('b.status', ['staged', 'submitting']);
        $byValue = function (array $rows, string $column) {
            $map = [];
            foreach ($rows as $row) {
                $map[$this->norm($row->value)] ??= $row->batch_id;
            }
            return $map;
        };
        $values = fn(string $field) => array_values(array_unique(array_map('mb_strtolower', array_filter(array_column($tenantRows, $field)))));
        $tenantMap = function (string $column, array $lowered) use ($staged, $byValue) {
            if (!$lowered) {
                return [];
            }

            return $byValue($staged('temp_batch_tenants')->whereRaw('LOWER(r.' . $column . ') IN (' . implode(',', array_fill(0, count($lowered), '?')) . ')', $lowered)->get(['r.' . $column . ' as value', 'b.id as batch_id'])->all(), $column);
        };

        return [
            'staged_units' => $byValue($staged('temp_batch_units')->where('b.property_id', $propertyId)->get(['r.unit_number as value', 'b.id as batch_id'])->all(), 'unit_number'),
            'staged_national_ids' => $tenantMap('national_id', $values('national_id')),
            'staged_passports' => $tenantMap('passport_no', $values('passport_no')),
            'staged_registrations' => $tenantMap('registration_number', $values('registration_number')),
        ];
    }

    /**
     * Counts of repeated values inside the file itself.
     *
     * @param array $unitRows
     * @param array $tenantRows
     * @return array
     */
    private function buildContext(array $unitRows, array $tenantRows): array
    {
        $count = function (array $rows, string $field) {
            $counts = [];
            foreach ($rows as $row) {
                $value = $this->norm($row[$field] ?? null);
                if ($value !== '') {
                    $counts[$value] = ($counts[$value] ?? 0) + 1;
                }
            }
            return $counts;
        };

        $unitRowNo = [];
        foreach ($unitRows as $row) {
            $unitRowNo[$this->norm($row['unit_number'] ?? null)] ??= (int) ($row['row_no'] ?? 0);
        }

        return [
            'unit_numbers' => $count($unitRows, 'unit_number'),
            'unit_row_no' => $unitRowNo,
            'tenant_units' => $count($tenantRows, 'unit_number'),
            'national_ids' => $count($tenantRows, 'national_id'),
            'passports' => $count($tenantRows, 'passport_no'),
            'registrations' => $count($tenantRows, 'registration_number'),
        ];
    }

    /**
     * Errors for one unit row.
     *
     * @param array $row
     * @param array $context
     * @param array $existing
     * @return array
     */
    private function unitErrors(array $row, array $context, array $existing): array
    {
        $errors = [];
        $missing = $this->missing($row, ['unit_number', 'unit_name', 'topology', 'rent_amount', 'initial_water_reading']);
        if ($missing) {
            $errors[] = implode(', ', $missing) . ' required';
        }

        $number = $this->norm($row['unit_number'] ?? null);
        if ($number !== '' && !preg_match(self::UNIT_NUMBER_PATTERN, trim((string) $row['unit_number']))) {
            $errors[] = "Unit Number '{$row['unit_number']}' may only contain letters A-Z, digits, spaces and - / .";
        }
        if ($number !== '' && ($context['unit_numbers'][$number] ?? 0) > 1) {
            $errors[] = "Unit Number '{$row['unit_number']}' is used by more than one row";
        }
        if ($number !== '' && isset($existing['unit_numbers'][$number])) {
            $errors[] = "Unit Number '{$row['unit_number']}' already exists under this property";
        }
        if ($number !== '' && isset($existing['staged_units'][$number])) {
            $errors[] = "Unit Number '{$row['unit_number']}' is already in staged upload #{$existing['staged_units'][$number]}; submit or cancel that upload first";
        }

        foreach ([['unit_type', 'unit_types', 'Unit Type'], ['usage_type', 'usage_types', 'Usage Type'], ['topology', 'topologies', 'Topology'], ['unit_category', 'categories', 'Unit Category']] as [$field, $list, $label]) {
            if ($this->filled($row[$field] ?? null) && !$this->lookups->has($list, $row[$field])) {
                $errors[] = "{$label} '{$row[$field]}' not recognised";
            }
        }

        if ($this->filled($row['rent_amount'] ?? null)) {
            if (!$this->isAmount($row['rent_amount'])) {
                $errors[] = "Rent Amount '{$row['rent_amount']}' is not a valid amount (digits, optional thousands commas, up to 2 decimals)";
            } elseif ((float) str_replace(',', '', $row['rent_amount']) > 99999999999.99) {
                $errors[] = 'Rent Amount is too large';
            }
        }

        if ($this->filled($row['initial_water_reading'] ?? null) && !preg_match('/^\d{1,9}$/', trim((string) $row['initial_water_reading']))) {
            $errors[] = "Initial Water Reading '{$row['initial_water_reading']}' must be a whole number";
        }

        $errors = array_merge($errors, $this->lengthErrors($row, array_diff(array_keys(UnitTenantUploadColumns::UNITS), ['unit_description'])));

        if ($this->filled($row['vat_able'] ?? null) && !in_array(trim((string) $row['vat_able']), ['0', '1'], true)) {
            $errors[] = "vat_able must be 0 or 1 (got '{$row['vat_able']}')";
        }

        return $errors;
    }

    /**
     * Errors for one tenant row.
     *
     * @param array $row
     * @param array $context
     * @param array $existing
     * @return array
     */
    private function tenantErrors(array $row, array $context, array $existing): array
    {
        $errors = [];
        $isCompany = ($row['sheet'] ?? '') === 'corporate';

        $required = ['unit_number', 'lease_start_date', 'mobile_no'];
        $required = array_merge($required, $isCompany
            ? ['company_name', 'registration_number', 'contact_phone', 'contact_name']
            : ['names', 'other_contact_phone', 'other_contact_name']);
        $missing = $this->missing($row, $required);
        if ($missing) {
            $errors[] = implode(', ', $missing) . ' required';
        }

        $number = $this->norm($row['unit_number'] ?? null);
        if ($number !== '') {
            if (!isset($context['unit_row_no'][$number])) {
                $errors[] = "Unit Number '{$row['unit_number']}' is not on the units sheet";
            } elseif ($context['unit_row_no'][$number] !== (int) ($row['row_no'] ?? 0)) {
                $errors[] = "Unit Number '{$row['unit_number']}' does not match its row on the units sheet";
            }
            if (($context['tenant_units'][$number] ?? 0) > 1) {
                $errors[] = "Unit '{$row['unit_number']}' has more than one tenant";
            }
        }

        if ($this->filled($row['tenant_type'] ?? null)) {
            if (!$this->lookups->has('tenant_types', $row['tenant_type'])) {
                $errors[] = "Tenant Type '{$row['tenant_type']}' not recognised";
            } else {
                $type = strtoupper($this->lookups->name('tenant_types', $row['tenant_type']));
                if ($isCompany && $type === 'INDIVIDUAL') {
                    $errors[] = 'Tenant Type INDIVIDUAL belongs on the individual_tenants sheet';
                } elseif (!$isCompany && $type === 'COMPANY') {
                    $errors[] = 'Tenant Type COMPANY belongs on the corporate_tenants sheet';
                }
            }
        }

        if ($this->filled($row['lease_start_date'] ?? null) && !$this->isDate($row['lease_start_date'])) {
            $errors[] = "Lease Start Date '{$row['lease_start_date']}' must be a real date written YYYY-MM-DD";
        }

        if (!$this->filled($row['nok_name'] ?? null) && ($this->filled($row['nok_phone'] ?? null) || $this->filled($row['nok_email'] ?? null) || $this->filled($row['nok_id'] ?? null) || $this->filled($row['nok_relationship'] ?? null))) {
            $errors[] = 'nok_name required when other next of kin details are given';
        }

        $errors = array_merge($errors, $this->lengthErrors($row, array_keys(UnitTenantUploadColumns::forSheet($isCompany ? UnitTenantUploadColumns::SHEET_CORPORATE : UnitTenantUploadColumns::SHEET_INDIVIDUAL))));

        if ($this->filled($row['nationality'] ?? null) && !$this->lookups->has('countries', $row['nationality'])) {
            $errors[] = "Nationality '{$row['nationality']}' not recognised";
        }

        foreach (['email', 'contact_email', 'other_contact_email', 'nok_email'] as $field) {
            if ($this->filled($row[$field] ?? null) && !filter_var($row[$field], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "{$field} '{$row[$field]}' is not a valid email";
            }
        }

        $errors = array_merge($errors, $isCompany ? $this->companyErrors($row, $context, $existing) : $this->individualErrors($row, $context, $existing));

        return $errors;
    }

    /**
     * Individual-only tenant rules.
     *
     * @param array $row
     * @param array $context
     * @param array $existing
     * @return array
     */
    private function individualErrors(array $row, array $context, array $existing): array
    {
        $errors = [];

        if (!$this->filled($row['national_id'] ?? null) && !$this->filled($row['passport_no'] ?? null)) {
            $errors[] = 'National ID or Passport No required';
        }

        if ($this->filled($row['gender'] ?? null) && !$this->lookups->has('genders', $row['gender'])) {
            $errors[] = "Gender '{$row['gender']}' must be Male, Female or Other";
        }

        foreach ([['national_id', 'national_ids', 'National ID'], ['passport_no', 'passports', 'Passport No']] as [$field, $key, $label]) {
            $value = $this->norm($row[$field] ?? null);
            if ($value === '') {
                continue;
            }
            if (!preg_match(self::IDENTIFIER_PATTERN, trim((string) $row[$field]))) {
                $errors[] = "{$label} '{$row[$field]}' may only contain letters A-Z, digits, spaces and - / . +";
                continue;
            }
            if (($context[$key][$value] ?? 0) > 1) {
                $errors[] = "{$label} '{$row[$field]}' is used by more than one row";
            }
            if (isset($existing[$key][$value])) {
                $errors[] = "{$label} '{$row[$field]}' already belongs to an existing tenant";
            }
            if (isset($existing['staged_' . $key][$value])) {
                $errors[] = "{$label} '{$row[$field]}' is already in staged upload #{$existing['staged_' . $key][$value]}; submit or cancel that upload first";
            }
        }

        return $errors;
    }

    /**
     * Company-only tenant rules.
     *
     * @param array $row
     * @param array $context
     * @param array $existing
     * @return array
     */
    private function companyErrors(array $row, array $context, array $existing): array
    {
        $errors = [];
        $value = $this->norm($row['registration_number'] ?? null);

        if ($value !== '' && !preg_match(self::IDENTIFIER_PATTERN, trim((string) $row['registration_number']))) {
            return ["Registration Number '{$row['registration_number']}' may only contain letters A-Z, digits, spaces and - / . +"];
        }

        if ($value !== '' && ($context['registrations'][$value] ?? 0) > 1) {
            $errors[] = "Registration Number '{$row['registration_number']}' is used by more than one row";
        }
        if ($value !== '' && isset($existing['registrations'][$value])) {
            $errors[] = "Registration Number '{$row['registration_number']}' already belongs to an existing tenant";
        }
        if ($value !== '' && isset($existing['staged_registrations'][$value])) {
            $errors[] = "Registration Number '{$row['registration_number']}' is already in staged upload #{$existing['staged_registrations'][$value]}; submit or cancel that upload first";
        }

        return $errors;
    }

    /**
     * Labels of the required fields that are blank.
     *
     * @param array $row
     * @param array $fields
     * @return array
     */
    private function missing(array $row, array $fields): array
    {
        return array_values(array_filter($fields, fn($field) => !$this->filled($row[$field] ?? null)));
    }

    /**
     * Whether a value has content.
     *
     * @param mixed $value
     * @return bool
     */
    private function filled($value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }

    /**
     * Whether a value is a non-negative amount: digits with optional thousands commas and at most 2 decimals.
     *
     * @param mixed $value
     * @return bool
     */
    private function isAmount($value): bool
    {
        $value = trim((string) $value);

        return (bool) preg_match('/^(\d{1,3}(,\d{3})+|\d+)(\.\d{1,2})?$/', $value);
    }

    /**
     * Whether a value is a real date written YYYY-MM-DD (1990 to 2100).
     *
     * @param string $value
     * @return bool
     */
    private function isDate(string $value): bool
    {
        $value = trim($value);
        $date = \DateTime::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value && (int) $date->format('Y') >= 1990 && (int) $date->format('Y') <= 2100;
    }

    /**
     * Errors for fields longer than the 255 characters the staging tables hold.
     *
     * @param array $row
     * @param array $fields
     * @return array
     */
    private function lengthErrors(array $row, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (mb_strlen((string) ($row[$field] ?? '')) > 255) {
                $errors[] = "{$field} is longer than 255 characters";
            }
        }

        return $errors;
    }

    /**
     * Comparison key: trimmed and lowercase.
     *
     * @param mixed $value
     * @return string
     */
    private function norm($value): string
    {
        return strtolower(trim((string) $value));
    }

    /**
     * Turn a list of values into a lookup set.
     *
     * @param array $values
     * @return array
     */
    private function set(array $values): array
    {
        $set = [];
        foreach ($values as $value) {
            $set[$this->norm($value)] = true;
        }

        return $set;
    }

    /**
     * Join error messages into one string, or null when there are none.
     *
     * @param array $errors
     * @return string|null
     */
    private function join(array $errors): ?string
    {
        return $errors ? implode('; ', $errors) : null;
    }
}
