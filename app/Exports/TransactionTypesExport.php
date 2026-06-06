<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class TransactionTypesExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithCustomStartCell
{
    protected array $transactionTypes;

    public function __construct(array $transactionTypes)
    {
        $this->transactionTypes = $transactionTypes;
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function collection()
    {
        return collect($this->transactionTypes)->map(fn ($t) => [
            $t['item_code'],
            $t['name'],
            $t['category_name'] ?? '-',
            $t['debit_account_code'] ?? '-',
            $t['debit_account_name'] ?? '-',
            $t['credit_account_code'] ?? '-',
            $t['credit_account_name'] ?? '-',
            $t['current_status_id'] == 1 ? 'Active' : 'Inactive',
        ]);
    }

    public function headings(): array
    {
        return ['Item Code', 'Type Name', 'Category', 'Debit Account Code', 'Debit Account Name', 'Credit Account Code', 'Credit Account Name', 'Status'];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setCellValue('A1', 'TRANSACTION TYPES');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Generated: ' . now()->format('d/m/Y H:i'));
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('888888');

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(30);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(30);
        $sheet->getColumnDimension('H')->setWidth(12);

        // Header row style
        $sheet->getStyle('A4:H4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '3598DB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2277B8']]],
        ]);

        // Data rows
        $lastRow = 4 + count($this->transactionTypes);
        if ($lastRow > 4) {
            $sheet->getStyle('A4:H' . $lastRow)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
            ]);
        }

        return [];
    }

    public function title(): string
    {
        return 'Transaction Types';
    }
}
