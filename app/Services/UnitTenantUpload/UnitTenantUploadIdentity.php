<?php

namespace App\Services\UnitTenantUpload;

use App\Models\Client;
use App\Models\TenantRecord;

/**
 * An identifier may appear on several unit accounts, provided the identity details agree.
 * These checks do not combine tenancy records, leases, invoices or payments.
 */
class UnitTenantUploadIdentity
{
    public static function loadExisting(array $rows): array
    {
        $individuals = array_filter($rows, fn ($row) => ($row['sheet'] ?? '') !== 'corporate');
        $companies = array_filter($rows, fn ($row) => ($row['sheet'] ?? '') === 'corporate');

        $records = self::matchingQuery(TenantRecord::query(), [
            'national_id' => 'national_id', 'passport_number' => 'passport_no',
        ], $individuals)->get(['names', 'phone', 'national_id', 'passport_number']);

        $identities = $records->map(fn ($record) => [
            'sheet' => 'individual', 'names' => $record->names, 'mobile_no' => $record->phone,
            'national_id' => $record->national_id, 'passport_no' => $record->passport_number,
        ])->all();

        $records = self::matchingQuery(Client::withAll()->where('type_id', 2), [
            'company_registration_number' => 'registration_number',
        ], $companies)->with('tenantRecord')->get();

        foreach ($records as $record) {
            $identities[] = ['sheet' => 'corporate', 'company_name' => $record->company_name,
                'mobile_no' => $record->mobile_number, 'registration_number' => $record->company_registration_number];
        }

        return $identities;
    }

    /** Restrict a query to identifiers present in these upload rows. Column names are application constants. */
    public static function matchingQuery($query, array $columns, array $rows)
    {
        return $query->where(function ($query) use ($columns, $rows) {
            // An empty identifier list must match nothing, rather than every tenant.
            $query->whereRaw('1 = 0');
            foreach ($columns as $column => $field) {
                $values = array_values(array_unique(array_filter(array_map(
                    fn ($row) => self::key($row[$field] ?? null), $rows
                ), fn ($value) => $value !== '')));
                if ($values) {
                    $query->orWhereRaw('LOWER(TRIM('.$column.')) IN ('.implode(',', array_fill(0, count($values), '?')).')', $values);
                }
            }
        });
    }

    /** Conflicting names, phones or other supplied identifiers still require correction. */
    public static function errors(array $row, array $candidates): array
    {
        $company = ($row['sheet'] ?? '') === 'corporate';
        $identifiers = $company ? ['registration_number' => 'Registration Number']
            : ['national_id' => 'National ID', 'passport_no' => 'Passport No'];
        $nameField = $company ? 'company_name' : 'names';
        $errors = [];

        foreach ($candidates as $candidate) {
            if ((($candidate['sheet'] ?? '') === 'corporate') !== $company) continue;
            $shared = null;
            foreach ($identifiers as $field => $label) {
                if (self::key($row[$field] ?? null) !== ''
                    && self::key($row[$field]) === self::key($candidate[$field] ?? null)) {
                    $shared = $label." '{$row[$field]}'";
                    break;
                }
            }
            if ($shared === null) continue;

            $different = [];
            if (self::name($row[$nameField] ?? '', $company) !== self::name($candidate[$nameField] ?? '', $company)) {
                $different[] = 'name';
            }
            // Missing optional details do not conflict with a supplied value; neither account is overwritten.
            $phone = self::phone($row['mobile_no'] ?? null);
            $otherPhone = self::phone($candidate['mobile_no'] ?? null);
            if ($phone !== '' && $otherPhone !== '' && $phone !== $otherPhone) $different[] = 'mobile number';
            foreach ($identifiers as $field => $label) {
                $value = self::key($row[$field] ?? null);
                $other = self::key($candidate[$field] ?? null);
                if ($value !== '' && $other !== '' && $value !== $other) $different[] = $label;
            }
            if ($different) {
                $errors[] = $shared.' matches another tenant, but '.implode(', ', $different).' differs. Confirm the tenant details before uploading.';
            }
        }

        return array_values(array_unique($errors));
    }

    private static function key($value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private static function name(string $value, bool $company): string
    {
        $value = self::key(preg_replace('/[\s\p{Z}\p{Cf}]+/u', ' ', $value) ?? $value);
        // Existing individual profiles include the display salutation in their names.
        return $company ? $value : preg_replace('/^(?:mr|mrs|ms|miss|dr|prof|mx)\.?\s+/u', '', $value);
    }

    private static function phone($value): string
    {
        $value = preg_replace('/[\s().+\-]+/u', '', (string) $value);
        if (preg_match('/^0([17][0-9]{8})$/D', $value, $match)) return '254'.$match[1];
        if (str_starts_with($value, '00')) return substr($value, 2);
        return $value;
    }
}
