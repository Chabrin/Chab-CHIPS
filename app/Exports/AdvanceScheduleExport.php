<?php

namespace App\Exports;

use App\Models\ClientAdvance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// WithStrictNullComparison keeps a zero as a zero; without it a zero balance or a zero repaid amount is left as an empty cell.
class AdvanceScheduleExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithCustomStartCell, WithStrictNullComparison
{
    protected ClientAdvance $advance;
    protected array $schedule;

    protected array $typeNames = [
        2 => 'Client Payment Advance',
        3 => 'Overpayment Advance',
    ];

    protected array $statusNames = [
        1 => 'Pending',
        2 => 'Cleared',
        3 => 'Cancelled',
        4 => 'Suspended',
        5 => 'Approved',
        6 => 'Posted',
        8 => 'Rescheduled',
    ];

    public function __construct(ClientAdvance $advance, array $schedule)
    {
        $this->advance = $advance;
        $this->schedule = $schedule;
    }

    public function startCell(): string
    {
        return 'A9';
    }

    public function title(): string
    {
        return 'Repayment Schedule';
    }

    public function collection()
    {
        return collect($this->schedule)->map(function ($entry, $index) {
            return [
                'no' => $index + 1,
                'due_date' => $entry['payment_date'] . (($entry['restructured'] ?? false) ? ' (restructured)' : ''),
                // Real numbers, formatted in styles(), so the columns can be summed and sorted in Excel.
                'principal' => round((float) $entry['principal_amount'], 2),
                'interest' => round((float) $entry['interest'], 2),
                'total_payment' => round((float) $entry['total_amount'], 2),
                'repaid' => round((float) ($entry['repaid_amount'] ?? 0), 2),
                'balance' => round((float) $entry['loan_balance'], 2),
                'status' => $this->statusNames[$entry['status_id'] ?? 1] ?? '—',
            ];
        });
    }

    public function headings(): array
    {
        return ['#', 'Due Date', 'Principal (KSh)', 'Interest (KSh)', 'Payment (KSh)', 'Repaid (KSh)', 'Balance (KSh)', 'Status'];
    }

    public function styles(Worksheet $sheet)
    {
        $advance = $this->advance;
        $schedule = $this->schedule;

        // Title
        $sheet->setCellValue('A1', 'CLIENT ADVANCE — REPAYMENT SCHEDULE');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Subtitle
        $sheet->setCellValue('A2', 'Generated: ' . now()->format('d M Y, H:i'));
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('888888');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Reference & client
        $sheet->setCellValue('A3', 'Reference:');
        $sheet->setCellValue('B3', $advance->reference_number ?? '—');
        $sheet->getStyle('A3')->getFont()->setBold(true);
        $sheet->setCellValue('D3', 'Landlord:');
        $sheet->setCellValue('E3', ($advance->client->names ?? '—') . ' (' . ($advance->client->reference_number ?? '') . ')');
        $sheet->getStyle('D3')->getFont()->setBold(true);

        // Property & type
        $sheet->setCellValue('A4', 'Property:');
        $sheet->setCellValue('B4', $advance->property->property_name ?? '—');
        $sheet->getStyle('A4')->getFont()->setBold(true);
        $sheet->setCellValue('D4', 'Advance Type:');
        $sheet->setCellValue('E4', $this->typeNames[$advance->advance_type_id] ?? '—');
        $sheet->getStyle('D4')->getFont()->setBold(true);

        // Financial summary
        $sheet->setCellValue('A5', 'Principal:');
        $sheet->setCellValue('B5', round((float) $advance->amount, 2));
        $sheet->getStyle('A5')->getFont()->setBold(true);
        $sheet->setCellValue('D5', 'Interest Rate:');
        $sheet->setCellValue('E5', ($advance->interest_rate ?? 0) . '%');
        $sheet->getStyle('D5')->getFont()->setBold(true);

        $sheet->setCellValue('A6', 'Interest Amount:');
        $sheet->setCellValue('B6', round((float) ($advance->interest_amount ?? 0), 2));
        $sheet->getStyle('A6')->getFont()->setBold(true);
        $sheet->setCellValue('D6', 'Processing Charge:');
        $sheet->setCellValue('E6', round((float) ($advance->charge_amount ?? 0), 2));
        $sheet->getStyle('D6')->getFont()->setBold(true);

        $sheet->setCellValue('A7', 'Total Amount:');
        $sheet->setCellValue('B7', round((float) $advance->total_amount, 2));

        // The summary amounts are numbers shown with a KSh prefix.
        foreach (['B5', 'B6', 'E6', 'B7'] as $cell) {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('"KSh "#,##0.00');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('B7')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('B7')->getFont()->getColor()->setRGB('1a7a4a');

        // Spacer
        $sheet->setCellValue('A8', '');

        // Header row style (A9)
        $lastRow = 9 + count($this->schedule);
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2c3e50']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A9:H9')->applyFromArray($headerStyle);

        // Data rows alignment
        $sheet->getStyle('A10:H' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Totals row
        $totalsRow = $lastRow + 1;
        $totalPrincipal = array_sum(array_column($this->schedule, 'principal_amount'));
        $totalInterest  = array_sum(array_column($this->schedule, 'interest'));
        $totalPayment   = array_sum(array_column($this->schedule, 'total_amount'));
        $totalRepaid    = array_sum(array_column($this->schedule, 'repaid_amount'));

        $sheet->setCellValue('A' . $totalsRow, 'Totals');
        $sheet->mergeCells('A' . $totalsRow . ':B' . $totalsRow);
        $sheet->setCellValue('C' . $totalsRow, round((float) $totalPrincipal, 2));
        $sheet->setCellValue('D' . $totalsRow, round((float) $totalInterest, 2));
        $sheet->setCellValue('E' . $totalsRow, round((float) $totalPayment, 2));
        $sheet->setCellValue('F' . $totalsRow, round((float) $totalRepaid, 2));
        $sheet->setCellValue('G' . $totalsRow, '—');
        $sheet->setCellValue('H' . $totalsRow, '—');

        $sheet->getStyle('A' . $totalsRow . ':H' . $totalsRow)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF0FF']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Money columns: principal, interest, payment, repaid, balance. A repaid amount of zero shows as a dash, as before.
        if (count($this->schedule) > 0) {
            $sheet->getStyle('C10:E' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('F10:F' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;"—"');
            $sheet->getStyle('G10:G' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $sheet->getStyle('C' . $totalsRow . ':F' . $totalsRow)->getNumberFormat()->setFormatCode('#,##0.00');

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(14);

        // Border around data
        if (count($this->schedule) > 0) {
            $sheet->getStyle('A9:H' . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']],
                ],
            ]);
        }
    }
}
