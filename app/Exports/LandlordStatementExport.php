<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class LandlordStatementExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithCustomStartCell
{
    protected $statement;
    protected $landlordInfo;

    public function __construct($statement, $landlordInfo)
    {
        $this->statement = $statement;
        $this->landlordInfo = $landlordInfo;
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function collection()
    {
        return collect($this->statement)->map(function ($entry) {
            return [
                'date' => $this->formatOrdinalDate($entry['date']),
                'reference' => $entry['reference'],
                'narration' => $entry['narration'],
                'debit' => $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '',
                'credit' => $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '',
                'balance' => number_format($entry['balance'], 2),
            ];
        });
    }

    public function headings(): array
    {
        return ['Date', 'Reference', 'Narration', 'Dr', 'Cr', 'Balance'];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setCellValue('A1', 'LANDLORD ACCOUNT STATEMENT');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Landlord: ' . $this->landlordInfo['landlord_name']);
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setBold(true);

        $sheet->setCellValue('A3', 'Property: ' . $this->landlordInfo['property_name']);
        $sheet->mergeCells('A3:F3');

        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);

        $sheet->getStyle('A6:F6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '3598DB']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        $lastRow = 6 + count($this->statement);
        $sheet->getStyle('A6:F' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ]);

        $sheet->getStyle('D7:F' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('A' . $lastRow . ':F' . $lastRow)->getFont()->setBold(true);

        return [];
    }

    public function title(): string
    {
        return 'Landlord Statement';
    }

    private function formatOrdinalDate($date)
    {
        $timestamp = strtotime($date);
        if (!$timestamp) return '-';

        $day = date('j', $timestamp);
        $month = date('M', $timestamp);
        $year = date('Y', $timestamp);

        $suffix = 'th';
        if ($day == 1 || $day == 21 || $day == 31) {
            $suffix = 'st';
        } elseif ($day == 2 || $day == 22) {
            $suffix = 'nd';
        } elseif ($day == 3 || $day == 23) {
            $suffix = 'rd';
        }

        return $day . $suffix . ' ' . $month . ' ' . $year;
    }
}
