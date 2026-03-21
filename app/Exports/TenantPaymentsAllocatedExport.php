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

class TenantPaymentsAllocatedExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithCustomStartCell
{
    protected $payments;
    protected $propertyName;
    protected $dateFrom;
    protected $dateTo;

    public function __construct($payments, $propertyName, $dateFrom, $dateTo)
    {
        $this->payments = $payments;
        $this->propertyName = $propertyName;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function collection()
    {
        return $this->payments->map(function ($p) {
            return [
                'date_time' => $p->date_time ? $p->date_time->format('d-m-Y H:i') : '-',
                'tenant_name' => $p->tenant->names ?? '-',
                'narration' => $p->narration ?? '-',
                'amount' => $p->amount,
                'trans_amount' => $p->trans_amount,
                'payment_mode' => $p->payment_mode ?? '-',
                'payment_channel' => $p->payment_channel ?? '-',
                'security_code' => $p->security_code ?? '-',
                'trans_remarks' => $p->trans_remarks ?? '-',
                'trans_type' => $p->trans_type ?? '-',
                'payment_year' => $p->payment_year ?? '-',
                'payment_month' => $p->payment_month ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Date',
            'Tenant',
            'Narration',
            'Allocated Amt',
            'Trans Amount',
            'Mode',
            'Channel',
            'Security Code',
            'Trans Remarks',
            'Trans Type',
            'Year',
            'Month',
        ];
    }

    public function title(): string
    {
        return 'Allocated Payments';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setCellValue('A1', 'ALLOCATED PAYMENTS — ' . $this->propertyName);
        $sheet->mergeCells('A1:L1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Period: ' . date('d M Y', strtotime($this->dateFrom)) . ' to ' . date('d M Y', strtotime($this->dateTo)));
        $sheet->mergeCells('A2:L2');
        $sheet->getStyle('A2')->getFont()->setBold(true);

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(14);
        $sheet->getColumnDimension('I')->setWidth(30);
        $sheet->getColumnDimension('J')->setWidth(12);
        $sheet->getColumnDimension('K')->setWidth(8);
        $sheet->getColumnDimension('L')->setWidth(12);

        $headerRow = 4;
        $sheet->getStyle("A{$headerRow}:L{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '3598DB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $lastRow = $headerRow + $this->payments->count();
        $sheet->getStyle("A{$headerRow}:L{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        $sheet->getStyle("D5:E{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        return [];
    }
}
