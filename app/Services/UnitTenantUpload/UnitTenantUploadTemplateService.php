<?php

namespace App\Services\UnitTenantUpload;

use App\Models\Gender;
use App\Models\Property;
use App\Models\Salutation;
use App\Models\SystemCountry;
use App\Models\TenantType;
use App\Models\UnitCategory;
use App\Models\UnitTopology;
use App\Models\UnitType;
use App\Models\UsageType;
use App\Models\Unit;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UnitTenantUploadTemplateService
{
    private const LISTS_SHEET = 'lists';

    /**
     * Build the units & tenants template workbook for a property.
     *
     * @param Property $property
     * @return Spreadsheet
     */
    public function build(Property $property, bool $existingUnits = false): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $lists = $this->lookupLists();

        if ($existingUnits) {
            $sheet = $spreadsheet->getActiveSheet()->setTitle('units');
            $sheet->fromArray(['unit_number', 'unit_code'], null, 'A1');
            $units = Unit::where('property_id', $property->id)->orderBy('unit_number')->get()
                ->filter(fn ($unit) => !ExistingUnitAvailability::blocked($unit))->take(UnitTenantUploadColumns::MAX_ROWS);
            if ($units->isEmpty()) throw new \DomainException('This property has no available units for tenant onboarding.');
            foreach ($units->values() as $index => $unit) {
                $row = $index + 2;
                $sheet->setCellValueExplicit('A'.$row, $unit->unit_number, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B'.$row, Unit::generateUnitCode($property, $unit->unit_number), DataType::TYPE_STRING);
            }
            $sheet->getColumnDimension('A')->setWidth(24);
            $sheet->getColumnDimension('B')->setWidth(32);
            $sheet->getStyle('A1:B1')->getFont()->setBold(true);
            $sheet->freezePane('A2');
            $sheet->getProtection()->setSheet(true);
        } else {
            $this->addUnitsSheet($spreadsheet->getActiveSheet(), $lists, $property);
        }
        $this->addTenantSheet($spreadsheet->createSheet(), UnitTenantUploadColumns::SHEET_INDIVIDUAL, $lists);
        $this->addTenantSheet($spreadsheet->createSheet(), UnitTenantUploadColumns::SHEET_CORPORATE, $lists);
        $this->addReadmeSheet($spreadsheet->createSheet(), $property);
        if ($existingUnits) {
            $readme = $spreadsheet->getSheetByName('readme');
            $readme->setCellValue('A1', 'Tenants for existing units — '.$property->reference_number);
            $readme->setCellValue('A4', 'The units sheet lists up to 100 available existing units. Fill tenant details on the matching row; leave other tenant rows blank. Download again after submitting for remaining units.');
            $readme->setCellValue('A5', 'Unit numbers and codes are locked. Existing rent and VAT settings are used and are not changed by this upload.');
            $readme->setCellValue('A7', 'Rows without tenant details are ignored. No units are created. Occupied units and pending onboarding are blocked.');
            $readme->setCellValue('A11', 'Rent and VAT come from the existing unit; there is no VAT column to fill in this mode.');
        }
        $this->addListsSheet($spreadsheet->createSheet(), $lists);
        if ($existingUnits) {
            $metadata = $spreadsheet->createSheet()->setTitle('_upload');
            $metadata->setCellValue('A1', 'existing_units');
            $metadata->setCellValue('B1', $property->id);
            $metadata->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Download file name for a property's template.
     *
     * @param Property $property
     * @return string
     */
    public function fileName(Property $property): string
    {
        $slug = fn($value) => trim(preg_replace('/[^A-Za-z0-9]+/', '_', (string) $value), '_');

        return 'units_tenants_template_' . $slug($property->reference_number) . '_' . $slug($property->property_name) . '_' . date('Ymd') . '.xlsx';
    }

    /**
     * Dropdown values from the lookup models, keyed by list name.
     *
     * @return array
     */
    private function lookupLists(): array
    {
        return [
            'tenant_types' => TenantType::orderBy('id')->pluck('name')->all(),
            'unit_types' => UnitType::orderBy('id')->pluck('type_name')->all(),
            'usage_types' => UsageType::orderBy('id')->pluck('type_name')->all(),
            'topologies' => UnitTopology::orderBy('topology_name')->pluck('topology_name')->all(),
            'categories' => UnitCategory::orderBy('category_name')->pluck('category_name')->all(),
            'genders' => Gender::orderBy('id')->pluck('name')->all(),
            'titles' => Salutation::orderBy('id')->pluck('name')->all(),
            'countries' => SystemCountry::orderBy('country_name')->pluck('country_name')->all(),
            'vat' => ['No', 'Yes'],
        ];
    }

    /**
     * Units sheet: headers, dropdowns, date/number formats and the locked unit_code formulas.
     *
     * @param Worksheet $sheet
     * @param array $lists
     * @param Property $property
     */
    private function addUnitsSheet(Worksheet $sheet, array $lists, Property $property): void
    {
        $sheet->setTitle(UnitTenantUploadColumns::SHEET_UNITS);
        $columns = $this->withUnitCode(UnitTenantUploadColumns::UNITS);
        $headers = array_keys($columns);
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $codeCol = Coordinate::stringFromColumnIndex(array_search(UnitTenantUploadColumns::UNIT_CODE, $headers) + 1);

        $this->writeHeaders($sheet, $columns);
        $this->addDropdowns($sheet, $headers, $lists);
        $this->formatColumns($sheet, $headers, ['rent_amount', 'initial_water_reading', UnitTenantUploadColumns::UNIT_CODE]);

        // unit_code = property code + unit number; shown for reference, the importer always rebuilds it.
        $prefix = str_replace('"', '""', $property->reference_number . '-');
        for ($row = 2; $row <= $this->lastRow(); $row++) {
            $sheet->setCellValue($codeCol . $row, '=IF(A' . $row . '="","","' . $prefix . '"&TRIM(A' . $row . '))');
        }

        $sheet->getStyle($codeCol . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');

        // Only the unit_code column stays locked once the sheet is protected.
        $sheet->getStyle('A2:' . $lastCol . $this->lastRow())->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
        $sheet->getStyle($codeCol . '2:' . $codeCol . $this->lastRow())->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
        $sheet->getProtection()->setSheet(true);
    }

    /**
     * Columns of a sheet with the read-only unit_code placed right after unit_number.
     *
     * @param array $columns
     * @return array
     */
    private function withUnitCode(array $columns): array
    {
        $layout = [];

        foreach ($columns as $header => $optional) {
            $layout[$header] = $optional;

            if ($header === 'unit_number') {
                $layout[UnitTenantUploadColumns::UNIT_CODE] = false;
            }
        }

        return $layout;
    }

    /**
     * Tenant sheet: locked unit_number and unit_code formulas, dropdowns and protection.
     *
     * @param Worksheet $sheet
     * @param string $name
     * @param array $lists
     */
    private function addTenantSheet(Worksheet $sheet, string $name, array $lists): void
    {
        $sheet->setTitle($name);
        $columns = $this->withUnitCode(UnitTenantUploadColumns::forSheet($name));
        $headers = array_keys($columns);
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $unitsCodeCol = Coordinate::stringFromColumnIndex(array_search(UnitTenantUploadColumns::UNIT_CODE, array_keys($this->withUnitCode(UnitTenantUploadColumns::UNITS))) + 1);

        $this->writeHeaders($sheet, $columns);
        $this->addDropdowns($sheet, $headers, $lists);
        $sheet->getStyle('B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');

        for ($row = 2; $row <= $this->lastRow(); $row++) {
            $sheet->setCellValue('A' . $row, '=IF(units!A' . $row . '="","",units!A' . $row . ')');
            $sheet->setCellValue('B' . $row, '=IF(units!' . $unitsCodeCol . $row . '="","",units!' . $unitsCodeCol . $row . ')');
        }

        $this->formatColumns($sheet, $headers, ['unit_number', UnitTenantUploadColumns::UNIT_CODE, 'lease_start_date', 'lease_end_date']);
        foreach (['lease_start_date', 'lease_end_date'] as $dateHeader) {
            $dateCol = Coordinate::stringFromColumnIndex(array_search($dateHeader, $headers) + 1);
            $sheet->getStyle($dateCol . '2:' . $dateCol . $this->lastRow())->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        }

        // Everything except columns A (unit_number) and B (unit_code) stays editable once the sheet is protected.
        $sheet->getStyle('C2:' . $lastCol . $this->lastRow())->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
        $sheet->getProtection()->setSheet(true);
    }

    /**
     * Text format on every column except the given ones, so IDs and phone numbers keep leading zeros.
     *
     * @param Worksheet $sheet
     * @param array $headers
     * @param array $except
     */
    private function formatColumns(Worksheet $sheet, array $headers, array $except): void
    {
        foreach ($headers as $i => $header) {
            if (in_array($header, $except, true)) {
                continue;
            }

            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getStyle($col . '2:' . $col . $this->lastRow())->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }
    }

    /**
     * Readme sheet with the upload rules.
     *
     * @param Worksheet $sheet
     * @param Property $property
     */
    private function addReadmeSheet(Worksheet $sheet, Property $property): void
    {
        $sheet->setTitle('readme');
        $lines = [
            'Units & tenants upload — ' . $property->reference_number . ' - ' . $property->property_name,
            '',
            'Yellow headers are optional; all other columns are required.',
            'Fill the units sheet first. unit_number and unit_code on the tenant sheets fill themselves and are locked.',
            'unit_code on the units sheet fills itself and is locked: ' . $property->reference_number . '-<unit_number>. Units have no separate name; the unit code is the unit\'s identifier.',
            'A unit with a tenant goes on individual_tenants OR corporate_tenants, never both. Leave the other row blank.',
            'A unit with no tenant row is created vacant.',
            'Individuals need a National ID or a Passport number (either one).',
            'unit_number, National ID, passport and registration number: letters A-Z, digits, spaces and - / . only (no accents or other symbols).',
            'Pick dropdown values from the list; leave blank where optional. unit_type blank = Normal.',
            'vat_able: select Yes if VAT applies, or No if it does not (blank = No). Older files may use 1 = Yes or 0 = No.',
            'Other contact name and phone are optional on individual_tenants.',
            'lease_start_date and lease_end_date format: YYYY-MM-DD. Leave lease_end_date blank for an open-ended lease (no end date); fill it for a fixed-term lease.',
            'Do not rename sheets, add or delete rows. Maximum ' . UnitTenantUploadColumns::MAX_ROWS . ' units per file.',
        ];

        foreach ($lines as $i => $line) {
            $sheet->setCellValueExplicit('A' . ($i + 1), $line, DataType::TYPE_STRING);
        }

        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(110);
    }

    /**
     * Hidden lists sheet: one column per dropdown, blank first row so a blank is selectable.
     *
     * @param Worksheet $sheet
     * @param array $lists
     */
    private function addListsSheet(Worksheet $sheet, array $lists): void
    {
        $sheet->setTitle(self::LISTS_SHEET);

        foreach (array_keys($lists) as $i => $key) {
            foreach (array_values($lists[$key]) as $j => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1) . ($j + 2), (string) $value, DataType::TYPE_STRING);
            }
        }

        $sheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }

    /**
     * Write the header row; optional headers are yellow.
     *
     * @param Worksheet $sheet
     * @param array $columns
     */
    private function writeHeaders(Worksheet $sheet, array $columns): void
    {
        $col = 1;
        foreach ($columns as $header => $optional) {
            $cell = Coordinate::stringFromColumnIndex($col) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            if ($optional) {
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
            }

            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            $col++;
        }

        $sheet->freezePane('A2');
    }

    /**
     * Attach a list dropdown to every dropdown column that appears in the headers.
     *
     * @param Worksheet $sheet
     * @param array $headers
     * @param array $lists
     */
    private function addDropdowns(Worksheet $sheet, array $headers, array $lists): void
    {
        $listKeys = array_keys($lists);

        foreach (UnitTenantUploadColumns::DROPDOWNS as $header => $listKey) {
            $position = array_search($header, $headers);
            if ($position === false) {
                continue;
            }

            $col = Coordinate::stringFromColumnIndex($position + 1);
            $listCol = Coordinate::stringFromColumnIndex(array_search($listKey, $listKeys) + 1);
            $listEnd = count($lists[$listKey]) + 1;

            $validation = new DataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Invalid value');
            $validation->setError('Pick a value from the list.');
            $validation->setFormula1(self::LISTS_SHEET . '!$' . $listCol . '$1:$' . $listCol . '$' . $listEnd);

            $sheet->setDataValidation($col . '2:' . $col . $this->lastRow(), $validation);
        }
    }

    /**
     * Last data row of the template.
     *
     * @return int
     */
    private function lastRow(): int
    {
        return UnitTenantUploadColumns::MAX_ROWS + 1;
    }
}
