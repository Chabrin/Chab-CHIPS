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

class ClientInvoicesExport implements FromArray, WithStyles, WithColumnWidths, WithHeadings
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
            'Client Name',
            'Property',
            'Invoice Date',
            'Due Date',
            'Total Amount',
            'Commission %',
            'Commission Amount',
            'Payable Amount',
            'Current Balance',
            'Status',
            'Narration'
        ];
    }

    public function array(): array
    {
        $data = [];

        // Add title row
        $data[] = ['CLIENT INVOICES REPORT'];
        $data[] = ['Generated on: ' . date('d-m-Y H:i:s')];
        $data[] = []; // Empty row

        // Add headings
        $data[] = $this->headings();

        // Add invoice data
        foreach ($this->invoices as $invoice) {
            $data[] = [
                $invoice->reference_number ?? '-',
                $invoice->client->names ?? '-',
                $invoice->property->property_name ?? '-',
                $invoice->date_time ? $invoice->date_time->format('d-m-Y') : '-',
                $invoice->date_due ? $invoice->date_due->format('d-m-Y') : '-',
                number_format($invoice->total_amount, 2),
                number_format($invoice->comm_per ?? 0, 2),
                number_format($invoice->comm_ammount ?? 0, 2),
                number_format($invoice->payable_amount ?? 0, 2),
                number_format($invoice->current_balance, 2),
                $invoice->status_name,
                $invoice->narration ?? '-'
            ];
        }

        // Add summary row
        $data[] = [];
        $totalAmount = $this->invoices->sum('total_amount');
        $totalCommission = $this->invoices->sum('comm_ammount');
        $totalPayable = $this->invoices->sum('payable_amount');
        $totalBalance = $this->invoices->sum('current_balance');

        $data[] = [
            'TOTALS',
            '',
            '',
            '',
            '',
            number_format($totalAmount, 2),
            '',
            number_format($totalCommission, 2),
            number_format($totalPayable, 2),
            number_format($totalBalance, 2),
            '',
            ''
        ];

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        // Style title
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
        $sheet->mergeCells('A1:L1');

        // Style timestamp
        $sheet->getStyle('A2:L2')->applyFromArray([
            'font' => [
                'size' => 11,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
        $sheet->mergeCells('A2:L2');

        // Style table header (row 4)
        $sheet->getStyle('A4:L4')->applyFromArray([
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
        $sheet->getStyle("A5:L{$lastRow}")->applyFromArray([
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
        $sheet->getStyle("A{$totalsRow}:L{$totalsRow}")->applyFromArray([
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
        $sheet->getStyle("F5:J{$lastRow}")->getNumberFormat()
            ->setFormatCode('#,##0.00');
        $sheet->getStyle("F{$totalsRow}:J{$totalsRow}")->getNumberFormat()
            ->setFormatCode('#,##0.00');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // Reference Number
            'B' => 30, // Client Name
            'C' => 25, // Property
            'D' => 15, // Invoice Date
            'E' => 15, // Due Date
            'F' => 15, // Total Amount
            'G' => 12, // Commission %
            'H' => 15, // Commission Amount
            'I' => 15, // Payable Amount
            'J' => 15, // Current Balance
            'K' => 15, // Status
            'L' => 40, // Narration
        ];
    }
}
