<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class StatutoryDeductionsExport implements FromArray, WithStyles, WithColumnWidths
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }

    /**
     * Letter of the last column, from the width of the table header row (row 5).
     */
    private function lastColumn(): string {
        return Coordinate::stringFromColumnIndex(max(count($this->data[4] ?? []), 5));
    }

    public function styles(Worksheet $sheet)
    {
        $last = $this->lastColumn();
        // Style the header rows (first 3 rows)
        $sheet->getStyle('A1:' . $last . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        $sheet->mergeCells('A1:' . $last . '1');

        $sheet->getStyle('A2:' . $last . '3')->applyFromArray([
            'font' => [
                'size' => 11,
            ],
        ]);

        // Style the table header (row 5)
        $sheet->getStyle('A5:' . $last . '5')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // Style data rows with borders
        $lastRow = count($this->data);
        $sheet->getStyle('A5:' . $last . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        // Style footer rows (last 2 rows)
        $sheet->getStyle('A' . ($lastRow - 1) . ':' . $last . $lastRow)->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E7E6E6'],
            ],
        ]);

        // Right align amount column
        $sheet->getStyle($last . '6:' . $last . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 20,
            'C' => 35,
            'D' => 20,
            'E' => 18,
            'F' => 18,
            'G' => 18,
        ];
    }
}
