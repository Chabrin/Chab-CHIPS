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

class TenantStatementExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithCustomStartCell
{
    public function __construct(private array $data) {}

    public function startCell(): string
    {
        return 'A6';
    }

    public function collection()
    {
        return collect($this->data['statement'])->map(function ($entry) {
            return [
                'date' => $entry['date']?->format('jS M Y') ?? '-',
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
        return [
            'Date',
            'Reference',
            'Narration',
            'Dr',
            'Cr',
            'Balance'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $tenantInfo = $this->data['tenantInfo'];
        $summary = $this->data['summary'];

        // Set tenant info header
        $sheet->setCellValue('A1', 'TENANT STATEMENT');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Tenant: ' . $tenantInfo['tenant_name']);
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setBold(true);

        $sheet->setCellValue('A3', 'Property: ' . $tenantInfo['property_name'] . ' | Unit: ' . $tenantInfo['unit_name']);
        $sheet->mergeCells('A3:F3');

        $sheet->setCellValue('A4', 'Period: ' . $this->data['dateFrom'] . ' to ' . $this->data['dateTo']);
        $sheet->mergeCells('A4:F4');

        if ($summary['opening_balance'] !== null) {
            $sheet->mergeCells('A5:E5');
            $sheet->setCellValue('A5', 'Opening Balance');
            $sheet->setCellValue('F5', number_format($summary['opening_balance'], 2));
            $sheet->getStyle('F5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);

        // Header row styling
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

        // Data rows borders and alignment
        $lastRow = 6 + count($this->data['statement']);
        $totalsRow = $lastRow + 1;

        // Totals row values
        $totalDebit = $summary['total_debit'];
        $totalCredit = $summary['total_credit'];
        $closingBalance = $summary['closing_balance'];

        $sheet->mergeCells('A' . $totalsRow . ':C' . $totalsRow);
        $sheet->setCellValue('A' . $totalsRow, 'Closing Balance');
        $sheet->setCellValue('D' . $totalsRow, $totalDebit > 0 ? number_format($totalDebit, 2) : '');
        $sheet->setCellValue('E' . $totalsRow, $totalCredit > 0 ? number_format($totalCredit, 2) : '');
        $sheet->setCellValue('F' . $totalsRow, number_format($closingBalance, 2));

        $sheet->getStyle('A' . $totalsRow . ':F' . $totalsRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '3598DB']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '2a7ab5']
                ]
            ]
        ]);
        $sheet->getStyle('D' . $totalsRow . ':F' . $totalsRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getStyle('A6:F' . $totalsRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ]);

        if ($lastRow >= 7) {
            $sheet->getStyle('D7:F' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('A' . $lastRow . ':F' . $lastRow)->getFont()->setBold(true);
        }

        return [];
    }

    public function title(): string
    {
        return 'Tenant Statement';
    }

}
