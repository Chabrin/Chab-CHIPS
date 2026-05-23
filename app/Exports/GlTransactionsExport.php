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

class GlTransactionsExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithCustomStartCell
{
    protected array $rows;
    protected string $start;
    protected string $end;

    public function __construct(array $rows, string $start, string $end)
    {
        $this->rows  = $rows;
        $this->start = $start;
        $this->end   = $end;
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function collection()
    {
        return collect($this->rows)->map(fn ($r) => [
            $r['parent_id'],
            $r['date'],
            $r['reference'],
            $r['account'],
            $r['debit'],
            $r['credit'],
            $r['narration'],
        ]);
    }

    public function headings(): array
    {
        return ['Parent ID', 'Date', 'Reference', 'Account', 'Debit', 'Credit', 'Description'];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setCellValue('A1', 'GL TRANSACTIONS');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Period: ' . $this->start . ' – ' . $this->end);
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getFont()->setBold(true);

        $sheet->setCellValue('A3', 'Generated: ' . now()->format('d/m/Y H:i'));
        $sheet->mergeCells('A3:G3');
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle('A3')->getFont()->getColor()->setRGB('888888');

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(13);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(16);
        $sheet->getColumnDimension('G')->setWidth(40);

        // Header row style
        $sheet->getStyle('A5:G5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '3598DB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2277B8']]],
        ]);

        // Data rows
        $lastRow = 5 + count($this->rows);
        if ($lastRow > 5) {
            $sheet->getStyle('A5:G' . $lastRow)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
            ]);
            // Right-align debit/credit columns
            $sheet->getStyle('E6:F' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        return [];
    }

    public function title(): string
    {
        return 'GL Transactions';
    }
}
