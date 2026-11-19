<?php

namespace App\Services\UnitTenantUpload;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Calculation\Exception as CalculationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UnitTenantUploadParserService
{
    private const MAX_SHEET_ROW = 500;

    /**
     * Read an uploaded workbook into plain rows per sheet (blank rows skipped).
     *
     * @param string $path
     * @return array ['units' => [...], 'individual' => [...], 'corporate' => [...]]
     */
    public function parse(string $path, bool $existingUnits = false, ?int $propertyId = null): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (ReaderException $e) {
            throw new InvalidArgumentException('The file could not be read. Please upload the downloaded template (.xlsx).');
        }

        if ($existingUnits && $propertyId !== null) {
            $metadata = $spreadsheet->getSheetByName('_upload');
            if (!$metadata || $metadata->getCell('A1')->getValue() !== 'existing_units'
                || (string) $metadata->getCell('B1')->getValue() !== (string) $propertyId) {
                throw new InvalidArgumentException('Download the Tenants for existing units template for the selected property and fill it in. This file is for a different property or upload mode.');
            }
        }

        $parsed = [
            'units' => $this->readSheet($spreadsheet, UnitTenantUploadColumns::SHEET_UNITS, false, $existingUnits),
            'individual' => $this->readSheet($spreadsheet, UnitTenantUploadColumns::SHEET_INDIVIDUAL, true),
            'corporate' => $this->readSheet($spreadsheet, UnitTenantUploadColumns::SHEET_CORPORATE, true),
        ];

        if ($existingUnits) {
            $numbers = array_map(fn ($row) => mb_strtolower((string) $row['unit_number']), array_merge($parsed['individual'], $parsed['corporate']));
            $parsed['units'] = array_values(array_filter($parsed['units'], fn ($row) => in_array(mb_strtolower((string) $row['unit_number']), $numbers, true)));
            if (!$numbers) throw new InvalidArgumentException('Enter at least one tenant on the individual_tenants or corporate_tenants sheet.');
        }

        if (count($parsed['units']) === 0) {
            throw new InvalidArgumentException('The units sheet has no data rows.');
        }

        if (count($parsed['units']) > UnitTenantUploadColumns::MAX_ROWS) {
            throw new InvalidArgumentException('This file has ' . count($parsed['units']) . ' units — the limit is ' . UnitTenantUploadColumns::MAX_ROWS . ' per upload. Please split it into smaller batches.');
        }

        return $parsed;
    }

    /**
     * Read one sheet into rows keyed by header, plus row_no.
     *
     * @param Spreadsheet $spreadsheet
     * @param string $name
     * @param bool $isTenantSheet
     * @return array
     */
    private function readSheet(Spreadsheet $spreadsheet, string $name, bool $isTenantSheet, bool $existingUnits = false): array
    {
        $sheet = $spreadsheet->getSheetByName($name);
        if (!$sheet) {
            throw new InvalidArgumentException("The sheet '{$name}' is missing. Please use the downloaded template.");
        }

        $columns = $existingUnits ? ['unit_number'] : array_keys(UnitTenantUploadColumns::forSheet($name));
        $positions = $this->headerPositions($sheet, $name, $columns);
        $rows = [];

        if ($sheet->getHighestDataRow() > self::MAX_SHEET_ROW) {
            throw new InvalidArgumentException("The sheet '{$name}' has data as far down as row {$sheet->getHighestDataRow()}. Remove stray cells below the data and upload again.");
        }

        for ($r = 2; $r <= $sheet->getHighestDataRow(); $r++) {
            $row = ['row_no' => $r];
            $hasData = false;

            foreach ($columns as $header) {
                $row[$header] = $this->cellValue($sheet, $positions[$header], $r, in_array($header, ['lease_start_date', 'lease_end_date'], true));

                if ($row[$header] !== null && !($isTenantSheet && $header === 'unit_number')) {
                    $hasData = true;
                }
            }

            if ($hasData) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Column index of every expected header; throws when one is missing.
     *
     * @param Worksheet $sheet
     * @param string $name
     * @param array $columns
     * @return array
     */
    private function headerPositions(Worksheet $sheet, string $name, array $columns): array
    {
        $found = [];
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($c = 1; $c <= $lastCol; $c++) {
            $header = strtolower(trim((string) $sheet->getCell([$c, 1])->getValue()));
            if ($header !== '') {
                $found[$header] = $c;
            }
        }

        $positions = [];
        foreach ($columns as $header) {
            if (!isset($found[$header])) {
                throw new InvalidArgumentException("The sheet '{$name}' is missing the column '{$header}'. Please use the downloaded template.");
            }
            $positions[$header] = $found[$header];
        }

        return $positions;
    }

    /**
     * Trimmed text value of a cell (null when blank); date cells become Y-m-d.
     *
     * @param Worksheet $sheet
     * @param int $col
     * @param int $row
     * @param bool $isDate
     * @return string|null
     */
    private function cellValue(Worksheet $sheet, int $col, int $row, bool $isDate): ?string
    {
        $cell = $sheet->getCell([$col, $row]);

        try {
            $value = $cell->getCalculatedValue();
        } catch (CalculationException $e) {
            throw new InvalidArgumentException("Cell {$cell->getCoordinate()} on sheet '{$sheet->getTitle()}' has a formula that could not be calculated.");
        }

        if (is_string($value) && preg_match('/^#(DIV\/0!|N\/A|NAME\?|NULL!|NUM!|REF!|VALUE!)$/', $value)) {
            throw new InvalidArgumentException("Cell {$cell->getCoordinate()} on sheet '{$sheet->getTitle()}' contains an Excel error ({$value}).");
        }

        if ($value === null || $value === '') {
            return null;
        }

        if ($isDate && is_numeric($value) && $cell->getDataType() === DataType::TYPE_NUMERIC && Date::isDateTime($cell)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        if (is_float($value) || is_int($value)) {
            $value = floor($value) == $value ? number_format($value, 0, '.', '') : rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
