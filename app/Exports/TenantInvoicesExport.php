<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class TenantInvoicesExport implements FromArray, WithStyles, WithColumnWidths, WithHeadings
{
    protected $invoices;

    public function __construct($invoices)
    {
        $this->invoices = $invoices;
    }

    public function headings(): array
    {
        return [
            'Reference Number',
            'Tenant Name',
            'Property',
            'Unit',
            'Invoice Date',
            'Due Date',
            'Total Amount',
            'Current Balance',
            'Status',
            'Narration'
        ];
    }

    public function array(): array
    {
        $data = [];

        // Add title row
        $data[] = ['TENANT INVOICES REPORT'];
        $data[] = ['Generated on: ' . date('d-m-Y H:i:s')];
        $data[] = []; // Empty row

        // Add headings
        $data[] = $this->headings();

        // Add invoice data
        foreach ($this->invoices as $invoice) {
            $data[] = [
                $invoice->reference_number ?? '-',
                $invoice->tenant->names ?? '-',
                $invoice->property->property_name ?? '-',
                $invoice->unit->unit_name ?? '-',
                $invoice->date_time ? $invoice->date_time->format('d-m-Y') : '-',
                $invoice->date_due ? $invoice->date_due->format('d-m-Y') : '-',
                number_format($invoice->total_amount, 2),
                number_format($invoice->current_balance, 2),
                $invoice->status_name,
                $invoice->narration ?? '-'
            ];
        }

        // Add summary row
        $data[] = [];
        $totalAmount = $this->invoices->sum('total_amount');
        $totalBalance = $this->invoices->sum('current_balance');
        $data[] = [
            'TOTALS',
            '',
            '',
            '',
            '',
            '',
            number_format($totalAmount, 2),
            number_format($totalBalance, 2),
            '',
            ''
        ];

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        // Style title
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
        $sheet->mergeCells('A1:J1');

        // Style timestamp
        $sheet->getStyle('A2:J2')->applyFromArray([
            'font' => [
                'size' => 11,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
        $sheet->mergeCells('A2:J2');

        // Style table header (row 4)
        $sheet->getStyle('A4:J4')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ]
            ]
        ]);

        // Style data rows
        $lastRow = 5 + count($this->invoices);
        $sheet->getStyle("A5:J{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ]);

        // Style totals row
        $totalsRow = $lastRow + 2;
        $sheet->getStyle("A{$totalsRow}:J{$totalsRow}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E7E6E6']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ]
            ]
        ]);

        // Number format for amount columns
        $sheet->getStyle("G5:H{$lastRow}")->getNumberFormat()
            ->setFormatCode('#,##0.00');
        $sheet->getStyle("G{$totalsRow}:H{$totalsRow}")->getNumberFormat()
            ->setFormatCode('#,##0.00');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // Reference Number
            'B' => 30, // Tenant Name
            'C' => 25, // Property
            'D' => 20, // Unit
            'E' => 15, // Invoice Date
            'F' => 15, // Due Date
            'G' => 15, // Total Amount
            'H' => 15, // Current Balance
            'I' => 15, // Status
            'J' => 40, // Narration
        ];
    }
}
