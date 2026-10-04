<?php

namespace Tests\Unit;

use App\Services\UnitTenantUpload\UnitTenantUploadColumns;
use App\Services\UnitTenantUpload\UnitTenantUploadParserService;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class UnitTenantUploadParserTest extends TestCase
{
    private function workbook(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheets = [UnitTenantUploadColumns::SHEET_UNITS, UnitTenantUploadColumns::SHEET_INDIVIDUAL, UnitTenantUploadColumns::SHEET_CORPORATE];

        foreach ($sheets as $i => $name) {
            $sheet = $i === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($name);
            $sheet->fromArray(array_keys(UnitTenantUploadColumns::forSheet($name)), null, 'A1');
        }

        return $spreadsheet;
    }

    private function cell(Spreadsheet $spreadsheet, string $sheet, string $header, int $row): string
    {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(array_search($header, array_keys(UnitTenantUploadColumns::forSheet($sheet))) + 1);

        return $col . $row;
    }

    private function save(Spreadsheet $spreadsheet): string
    {
        $path = sys_get_temp_dir() . '/parser_test_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_rows_are_read_trimmed_with_blanks_skipped_and_formulas_resolved(): void
    {
        $spreadsheet = $this->workbook();
        $units = $spreadsheet->getSheetByName('units');
        $units->setCellValue('A2', ' A1 ');
        $units->setCellValue($this->cell($spreadsheet, 'units', 'unit_name', 2), 'Unit A1');
        $units->setCellValue($this->cell($spreadsheet, 'units', 'rent_amount', 2), 25000);
        $units->setCellValue('A4', 'A3');

        $tenants = $spreadsheet->getSheetByName('individual_tenants');
        $tenants->setCellValue('A2', '=IF(units!A2="","",units!A2)');
        $tenants->setCellValue('A3', '=IF(units!A3="","",units!A3)');
        $tenants->setCellValue($this->cell($spreadsheet, 'individual_tenants', 'names', 2), 'Jane');
        $tenants->setCellValueExplicit($this->cell($spreadsheet, 'individual_tenants', 'mobile_no', 2), '0712345678', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $dateCell = $this->cell($spreadsheet, 'individual_tenants', 'lease_start_date', 2);
        $tenants->setCellValue($dateCell, Date::PHPToExcel(new \DateTime('2026-11-01')));
        $tenants->getStyle($dateCell)->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        $parsed = (new UnitTenantUploadParserService())->parse($this->save($spreadsheet));

        $this->assertCount(2, $parsed['units']);
        $this->assertSame('A1', $parsed['units'][0]['unit_number']);
        $this->assertSame('25000', $parsed['units'][0]['rent_amount']);
        $this->assertSame(4, $parsed['units'][1]['row_no']);
        $this->assertCount(1, $parsed['individual']);
        $this->assertSame('A1', $parsed['individual'][0]['unit_number']);
        $this->assertSame('0712345678', $parsed['individual'][0]['mobile_no']);
        $this->assertSame('2026-11-01', $parsed['individual'][0]['lease_start_date']);
        $this->assertSame([], $parsed['corporate']);
    }

    public function test_a_missing_sheet_or_column_is_rejected(): void
    {
        $spreadsheet = $this->workbook();
        $spreadsheet->removeSheetByIndex(2);

        $this->expectException(InvalidArgumentException::class);
        (new UnitTenantUploadParserService())->parse($this->save($spreadsheet));
    }

    public function test_an_empty_units_sheet_is_rejected(): void
    {
        $this->expectExceptionMessage('no data rows');
        (new UnitTenantUploadParserService())->parse($this->save($this->workbook()));
    }

    public function test_too_many_units_are_rejected(): void
    {
        $spreadsheet = $this->workbook();
        $units = $spreadsheet->getSheetByName('units');
        for ($r = 2; $r <= UnitTenantUploadColumns::MAX_ROWS + 2; $r++) {
            $units->setCellValue('A' . $r, 'U' . $r);
        }

        $this->expectExceptionMessage('limit is ' . UnitTenantUploadColumns::MAX_ROWS);
        (new UnitTenantUploadParserService())->parse($this->save($spreadsheet));
    }
}
