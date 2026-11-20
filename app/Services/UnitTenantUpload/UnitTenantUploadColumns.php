<?php

namespace App\Services\UnitTenantUpload;

/**
 * Template column layout (header => optional, yellow in the file); required first, optional last,
 * unit_number always first. Shared by the template builder, parser and validator.
 */
final class UnitTenantUploadColumns
{
    public const MAX_ROWS = 100;

    /** Read-only column in the units sheet (property code + unit number); filled by formula, never read back. */
    public const UNIT_CODE = 'unit_code';

    public const SHEET_UNITS = 'units';
    public const SHEET_INDIVIDUAL = 'individual_tenants';
    public const SHEET_CORPORATE = 'corporate_tenants';

    public const UNITS = [
        'unit_number' => false,
        'topology' => false,
        'rent_amount' => false,
        'initial_water_reading' => false,
        'unit_type' => true,
        'usage_type' => true,
        'vat_able' => true,
        'unit_condition' => true,
        'unit_category' => true,
        'unit_description' => true,
    ];

    public const INDIVIDUAL = [
        'unit_number' => false,
        'lease_start_date' => false,
        'mobile_no' => false,
        'names' => false,
        'other_contact_phone' => true,
        'other_contact_name' => true,
        'lease_end_date' => true,
        'tenant_type' => true,
        'national_id' => true,
        'passport_no' => true,
        'gender' => true,
        'title' => true,
        'email' => true,
        'kra_pin' => true,
        'nationality' => true,
        'other_contact_email' => true,
        'nok_name' => true,
        'nok_phone' => true,
        'nok_email' => true,
        'nok_id' => true,
        'nok_relationship' => true,
    ];

    public const CORPORATE = [
        'unit_number' => false,
        'lease_start_date' => false,
        'mobile_no' => false,
        'company_name' => false,
        'registration_number' => false,
        'contact_phone' => false,
        'contact_name' => false,
        'lease_end_date' => true,
        'tenant_type' => true,
        'email' => true,
        'kra_pin' => true,
        'nationality' => true,
        'contact_email' => true,
        'nok_name' => true,
        'nok_phone' => true,
        'nok_email' => true,
        'nok_id' => true,
        'nok_relationship' => true,
    ];

    /** Header => lookup list key, for columns that get a dropdown. */
    public const DROPDOWNS = [
        'tenant_type' => 'tenant_types',
        'unit_type' => 'unit_types',
        'usage_type' => 'usage_types',
        'topology' => 'topologies',
        'vat_able' => 'vat',
        'unit_category' => 'categories',
        'gender' => 'genders',
        'title' => 'titles',
        'nationality' => 'countries',
    ];

    /** Accept readable choices and legacy numeric values without hiding invalid input. */
    public static function normalizeVat(?string $value): ?string
    {
        return match (strtolower(trim((string) $value))) {
            '' => null,
            'yes', '1' => '1',
            'no', '0' => '0',
            default => trim($value),
        };
    }

    /**
     * Column definition for a sheet name.
     *
     * @param string $sheet
     * @return array
     */
    public static function forSheet(string $sheet): array
    {
        return match ($sheet) {
            self::SHEET_UNITS => self::UNITS,
            self::SHEET_INDIVIDUAL => self::INDIVIDUAL,
            self::SHEET_CORPORATE => self::CORPORATE,
        };
    }
}
