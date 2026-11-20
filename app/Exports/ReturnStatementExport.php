<?php

namespace App\Exports;

use App\Models\ReturnSummary;
use App\Services\ReturnStatementService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ReturnStatementExport
{
    /**
     * Build the return statement workbook from the statement data (rows, totals, summary, blocks I to O, arrears).
     *
     * @param ReturnSummary $return
     * @param array $data the statement data from ReturnStatementService::statement()
     * @param array $header title, landlord, property and period labels
     * @param array $signoff prepared, checked and approved names and dates
     * @return Spreadsheet
     */
    public function build(ReturnSummary $return, array $data, array $header, array $signoff): Spreadsheet {
        $book = new Spreadsheet();
        $book->getProperties()->setTitle($header['title']);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Return Statement');
        $text = function ($cell, $value) use ($sheet) {
            $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
        };
        $text('A1', 'CHABRIN AGENCIES LIMITED - RETURN STATEMENT');
        $sheet->mergeCells('A1:M1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $text('A2', $header['title']);
        $sheet->mergeCells('A2:M2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $row = 2;
        foreach (['Landlord' => $header['landlord_label'], 'Property' => $header['property_label'],
            'Period' => $header['period'], 'Return ID' => $return->return_code] as $label => $value) {
            $row++;
            $text('A' . $row, $label);
            $sheet->mergeCells('B' . $row . ':M' . $row);
            $text('B' . $row, $value);
        }
        $row = 8;
        $col = 'A';
        foreach (ReturnStatementService::COLUMNS as $label) {
            $text($col++ . $row, $label);
        }
        $sheet->getStyle('A8:M8')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getRowDimension(8)->setRowHeight(32);
        foreach ($data['tenantRows'] as $tenant) {
            $row++;
            $col = 'A';
            foreach (ReturnStatementService::COLUMNS as $key => $label) {
                $cell = $col++ . $row;
                if (in_array($key, ['unit_code', 'tenant_name', 'remarks'])) {
                    $text($cell, $tenant[$key]);
                } else {
                    $sheet->setCellValue($cell, $tenant[$key]);
                }
            }
            // Wrapped notes need enough height in Excel and when printed.
            $lines = max(1, (int) ceil(mb_strlen($tenant['remarks']) / 28), substr_count($tenant['remarks'], "\n") + 1);
            $sheet->getRowDimension($row)->setRowHeight(max(30, $lines * 15));
        }
        $row++;
        $text('B' . $row, 'TOTALS');
        $col = 'A';
        foreach (ReturnStatementService::COLUMNS as $key => $label) {
            if (isset($data['totals'][$key])) {
                $sheet->setCellValue($col . $row, $data['totals'][$key]);
            }
            $col++;
        }
        $sheet->getStyle('A' . $row . ':M' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A8:M' . $row)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A8:M8')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF0B1B63');
        $sheet->getStyle('A8:M' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF666666');
        $sheet->getStyle('C9:L' . $row)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00)');
        $row += 3;
        $summaryStart = $row;
        $text('I' . $row, 'FINANCIAL SUMMARY');
        $sheet->mergeCells('I' . $row . ':M' . $row);
        foreach ($data['summaryRows'] as [$key, $label, $kind]) {
            $row++;
            $sheet->mergeCells('I' . $row . ':L' . $row);
            $text('I' . $row, $label);
            $sheet->setCellValue('M' . $row, $data['summary'][$key]);
            if (in_array($kind, ['strong', 'closing'], true)) {
                $sheet->getStyle('I' . $row . ':M' . $row)->getFont()->setBold(true);
            }
        }
        $sheet->getStyle('M' . $summaryStart . ':M' . $row)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00)');
        $sheet->getStyle('I' . $row . ':M' . $row)->getFont()->setBold(true);
        $sheet->getStyle('I' . $summaryStart . ':M' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF666666');

        // Recoveries, opening balance, landlord-account deductions, amount payable and nett balance.
        $row += 2;
        $settlementStart = $row;
        $text('A' . $row, 'DEDUCTIONS, OPENING BALANCE AND AMOUNT PAYABLE');
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $text('J' . $row, 'Reference');
        $sheet->mergeCells('J' . $row . ':K' . $row);
        $text('L' . $row, 'Date');
        $text('M' . $row, 'Amount');
        $sheet->getStyle('A' . $row . ':M' . $row)->getFont()->setBold(true);
        foreach ($data['settlementRows'] as $item) {
            $row++;
            $text('A' . $row, $item['label']);
            $sheet->mergeCells('A' . $row . ':I' . $row);
            $text('J' . $row, $item['ref']);
            $sheet->mergeCells('J' . $row . ':K' . $row);
            $text('L' . $row, $item['date']);
            $sheet->setCellValue('M' . $row, $item['amount']);
            if (in_array($item['kind'], ['strong', 'closing'], true)) {
                $sheet->getStyle('A' . $row . ':M' . $row)->getFont()->setBold(true);
            }
        }
        $sheet->getStyle('M' . ($settlementStart + 1) . ':M' . $row)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00)');
        $sheet->getStyle('A' . $settlementStart . ':M' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF666666');

        // Arrears paid on the return.
        if (!empty($data['arrears'])) {
            $row += 2;
            $arrearsStart = $row;
            $text('A' . $row, 'ARREARS DETAILS: DATE');
            $text('B' . $row, 'Details');
            $sheet->mergeCells('B' . $row . ':H' . $row);
            $text('I' . $row, 'Room');
            $text('M' . $row, 'Amount');
            $sheet->getStyle('A' . $row . ':M' . $row)->getFont()->setBold(true);
            foreach ($data['arrears'] as $item) {
                $row++;
                $text('A' . $row, $item['date']);
                $text('B' . $row, $item['details']);
                $sheet->mergeCells('B' . $row . ':H' . $row);
                $text('I' . $row, $item['room']);
                $sheet->setCellValue('M' . $row, $item['amount']);
            }
            $row++;
            $text('A' . $row, 'TOTAL ARREARS');
            $sheet->setCellValue('M' . $row, array_sum(array_column($data['arrears'], 'amount')));
            $sheet->getStyle('A' . $row . ':M' . $row)->getFont()->setBold(true);
            $sheet->getStyle('M' . ($arrearsStart + 1) . ':M' . $row)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00)');
            $sheet->getStyle('A' . $arrearsStart . ':M' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF666666');
        }
        $row += 3;
        $signoffRow = $row;
        foreach (['A' => ['Prepared by', 'prepared'], 'D' => ['Checked by', 'checked'],
            'G' => ['Approved by', 'approved'], 'J' => ['Paid by', null], 'L' => ['Client name', null]] as $col => [$label, $key]) {
            $text($col . $row, $label);
            $text($col . ($row + 1), 'Name: ' . ($key ? ($signoff[$key]['name'] ?? '') : ''));
            $text($col . ($row + 2), 'Date: ' . ($key ? ($signoff[$key]['date'] ?? '') : ''));
            $text($col . ($row + 3), 'Sign: __________________');
        }
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setWidth(match ($col) { 'A' => 9, 'B' => 24, 'D' => 17, 'M' => 32, default => 13 });
        }
        $sheet->getStyle('A1:M' . ($row + 3))->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle('C9:L' . ($summaryStart - 3))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->freezePane('C9');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(8, 8)
            ->setPrintArea('A1:M' . ($signoffRow + 3));
        // Signatures are worksheet content once at the end, never a repeating print footer.
        $sheet->getHeaderFooter()->setOddFooter('&RPage &P of &N');
        return $book;
    }
}
