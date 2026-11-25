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
     * Build the utility report workbook of a return: one row per room with the electricity bill, water bill and service
     * charge, their totals and the sign-off. Commission, deductions and the amount payable are on the rent report.
     *
     * @param ReturnSummary $return
     * @param array $data the utility statement from ReturnStatementService::statement() with the utility part
     * @param array $header title, landlord, property and period labels
     * @param array $signoff prepared, checked and approved names and dates
     * @return Spreadsheet
     */
    public function buildUtility(ReturnSummary $return, array $data, array $header, array $signoff): Spreadsheet {
        $book = new Spreadsheet();
        $book->getProperties()->setTitle($header['title']);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Utility Report');
        $text = function ($cell, $value) use ($sheet) {
            $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
        };
        $text('A1', 'CHABRIN AGENCIES LIMITED - UTILITY REPORT');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $text('A2', $header['title']);
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $row = 2;
        foreach (['Landlord' => $header['landlord_label'], 'Property' => $header['property_label'],
            'Period' => $header['period'], 'Return ID' => $return->return_code] as $label => $value) {
            $row++;
            $text('A' . $row, $label);
            $sheet->mergeCells('B' . $row . ':F' . $row);
            $text('B' . $row, $value);
        }
        $row = 8;
        $columns = array_keys($data['columns']);
        $col = 'A';
        foreach ($data['columns'] as $label) {
            $text($col++ . $row, $label);
        }
        $sheet->getStyle('A8:F8')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        foreach ($data['tenantRows'] as $tenant) {
            $row++;
            $col = 'A';
            foreach ($columns as $key) {
                $cell = $col++ . $row;
                if (in_array($key, ['unit_code', 'tenant_name', 'remarks'])) {
                    $text($cell, $tenant[$key] ?? '');
                } else {
                    $sheet->setCellValue($cell, $tenant[$key]);
                }
            }
        }
        $row++;
        $text('B' . $row, 'TOTALS');
        $col = 'A';
        foreach ($columns as $key) {
            if (isset($data['totals'][$key]) && !in_array($key, ['unit_code', 'tenant_name', 'remarks'])) {
                $sheet->setCellValue($col . $row, $data['totals'][$key]);
            }
            $col++;
        }
        $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A8:F8')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF0B1B63');
        $sheet->getStyle('A8:F' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF666666');
        $sheet->getStyle('C9:E' . $row)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00)');

        $row += 3;
        $summaryStart = $row;
        $text('A' . $row, 'UTILITY SUMMARY');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        foreach ($data['summaryRows'] as [$key, $label, $kind]) {
            $row++;
            $sheet->mergeCells('A' . $row . ':D' . $row);
            $text('A' . $row, $label);
            $sheet->setCellValue('E' . $row, $data['summary'][$key]);
            if ($kind === 'closing') {
                $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
            }
        }
        $sheet->getStyle('E' . $summaryStart . ':E' . $row)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00)');
        $sheet->getStyle('A' . $summaryStart . ':E' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF666666');

        $row += 2;
        $text('A' . $row, 'Commission, VAT, deductions and the amount payable are on the rent report. The two reports add up to the return.');
        $sheet->mergeCells('A' . $row . ':F' . $row);

        $row += 3;
        foreach (['A' => ['Prepared by', 'prepared'], 'C' => ['Checked by', 'checked'], 'E' => ['Approved by', 'approved']] as $col => [$label, $key]) {
            $text($col . $row, $label);
            $text($col . ($row + 1), 'Name: ' . ($signoff[$key]['name'] ?? ''));
            $text($col . ($row + 2), 'Date: ' . ($signoff[$key]['date'] ?? ''));
            $text($col . ($row + 3), 'Sign: __________________');
        }
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setWidth(match ($col) { 'A' => 10, 'B' => 28, 'F' => 30, default => 16 });
        }
        $sheet->getStyle('A1:F' . ($row + 3))->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT)->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0)->setPrintArea('A1:F' . ($row + 3));
        $sheet->getHeaderFooter()->setOddFooter('&RPage &P of &N');

        return $book;
    }

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
        // The columns this statement shows (a rent report or a utility return leaves some out) and where the table ends
        $columns = $data['columns'] ?? ReturnStatementService::COLUMNS;
        $last = chr(ord('A') + count($columns) - 1);
        $firstAmount = 'C';
        $lastAmount = chr(ord('A') + count($columns) - 1 - (isset($columns['remarks']) ? 1 : 0));
        $kind = ($data['part'] ?? 'all') === 'rent' ? 'RENT REPORT' : (str_starts_with((string) $return->return_code, 'RU') ? 'UTILITY RETURN STATEMENT' : 'RETURN STATEMENT');
        $text('A1', 'CHABRIN AGENCIES LIMITED - ' . $kind);
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
        foreach ($columns as $label) {
            $text($col++ . $row, $label);
        }
        $sheet->getStyle('A8:' . $last . '8')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getRowDimension(8)->setRowHeight(32);
        foreach ($data['tenantRows'] as $tenant) {
            $row++;
            $col = 'A';
            foreach ($columns as $key => $label) {
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
        foreach ($columns as $key => $label) {
            if (isset($data['totals'][$key])) {
                $sheet->setCellValue($col . $row, $data['totals'][$key]);
            }
            $col++;
        }
        $sheet->getStyle('A' . $row . ':' . $last . $row)->getFont()->setBold(true);
        $sheet->getStyle('A8:' . $last . $row)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A8:' . $last . '8')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF0B1B63');
        $sheet->getStyle('A8:' . $last . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF666666');
        $sheet->getStyle($firstAmount . '9:' . $lastAmount . $row)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00)');
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
        $remarksColumn = isset($columns['remarks']) ? $last : 'M';
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setWidth($col === $remarksColumn ? 32 : match ($col) { 'A' => 9, 'B' => 24, 'D' => 17, default => 13 });
        }
        $sheet->getStyle('A1:M' . ($row + 3))->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle($firstAmount . '9:' . $lastAmount . ($summaryStart - 3))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->freezePane('C9');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(8, 8)
            ->setPrintArea('A1:M' . ($signoffRow + 3));
        // Signatures are worksheet content once at the end, never a repeating print footer.
        $sheet->getHeaderFooter()->setOddFooter('&RPage &P of &N');
        return $book;
    }
}
