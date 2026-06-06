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

class LedgerAccountsExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithCustomStartCell
{
    protected array $accounts;

    public function __construct(array $accounts)
    {
        $this->accounts = $accounts;
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function collection()
    {
        return collect($this->accounts)->map(fn ($a) => [
            $a['account_code'],
            $a['account_name'],
            $a['class_name'],
            $a['group_name'],
            $a['category_name'],
            $a['normally'],
            $a['status'],
        ]);
    }

    public function headings(): array
    {
        return ['Account Code', 'Account Name', 'Class', 'Group', 'Category', 'Normally', 'Status'];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setCellValue('A1', 'LEDGER ACCOUNTS');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Generated: ' . now()->format('d/m/Y H:i'));
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('888888');

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);

        // Header row style
        $sheet->getStyle('A4:G4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '3598DB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2277B8']]],
        ]);

        // Data rows
        $lastRow = 4 + count($this->accounts);
        if ($lastRow > 4) {
            $sheet->getStyle('A4:G' . $lastRow)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
            ]);
        }

        return [];
    }

    public function title(): string
    {
        return 'Ledger Accounts';
    }
}
