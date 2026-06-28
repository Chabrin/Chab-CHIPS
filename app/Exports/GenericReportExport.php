<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Generic Excel export used by all report controllers.
 *
 * The caller builds a flat $rows array (including title / meta rows) and
 * tells the export which row number holds the column headings via $headingRow.
 * Everything from $headingRow onward is treated as the data table.
 *
 * Typical structure:
 *   Row 1  : Report title (merged)
 *   Row 2  : Meta line 1  (e.g. "Period: Jan 2025")
 *   Row 3  : Meta line 2  (e.g. "Generated: 23/06/2026")
 *   Row 4  : (blank spacer)
 *   Row 5  : Column headings  ← pass headingRow = 5
 *   Row 6+ : Data rows
 *   Last   : Totals row (optional)
 */
class GenericReportExport implements FromArray, WithStyles, WithColumnWidths
{
    public function __construct(
        private array  $rows,
        private int    $headingRow   = 5,
        private int    $numCols      = 0,
        private array  $colWidths    = [],
        private bool   $hasTotals    = false,
    ) {
        if ($this->numCols === 0 && isset($this->rows[$this->headingRow - 1])) {
            $this->numCols = count($this->rows[$this->headingRow - 1]);
        }
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol  = $this->colLetter($this->numCols ?: 1);
        $lastRow  = count($this->rows);
        $headRow  = $this->headingRow;

        // Title row — merged, large bold
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1a1a2e']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // Meta rows (2 … headingRow-2, skip blank row just before heading)
        for ($r = 2; $r < $headRow - 1; $r++) {
            $sheet->getStyle("A{$r}")->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '888888']],
            ]);
        }

        // Heading row — dark background, white bold text
        $sheet->getStyle("A{$headRow}:{$lastCol}{$headRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a1a2e']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                             'color'       => ['rgb' => '000000']]],
        ]);

        // Data rows
        $dataStart = $headRow + 1;
        if ($dataStart <= $lastRow) {
            $dataEnd = $this->hasTotals ? $lastRow - 1 : $lastRow;

            $sheet->getStyle("A{$dataStart}:{$lastCol}{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                               'color'       => ['rgb' => 'DDDDDD']]],
                'font'    => ['size' => 9],
            ]);

            // Alternating row shading
            for ($r = $dataStart; $r <= $dataEnd; $r++) {
                if (($r - $dataStart) % 2 === 1) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F5F7FF');
                }
            }

            // Totals row styling
            if ($this->hasTotals && $lastRow >= $dataStart) {
                $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EAF6']],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM,
                                            'color'       => ['rgb' => '1a1a2e']]],
                ]);
            }
        }

        return [];
    }

    public function columnWidths(): array
    {
        if (!empty($this->colWidths)) {
            return $this->colWidths;
        }
        $widths = [];
        for ($i = 1; $i <= ($this->numCols ?: 1); $i++) {
            $widths[$this->colLetter($i)] = 18;
        }
        return $widths;
    }

    private function colLetter(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $n--;
            $s = chr(65 + ($n % 26)) . $s;
            $n = (int)($n / 26);
        }
        return $s;
    }

    // ── Builder helper ─────────────────────────────────────────────────────────

    /**
     * Convenience builder: wraps data rows with standard meta header.
     *
     * @param string   $title      e.g. "Staff Directory"
     * @param array    $headings   Column headings array
     * @param array    $dataRows   Flat array of row arrays
     * @param array    $meta       Extra meta lines (max 1 extra line in addition to generated-at)
     * @param array    $colWidths  ['A' => 10, 'B' => 20, ...]
     * @param bool     $hasTotals  Whether last data row is a totals row
     */
    public static function build(
        string $title,
        array  $headings,
        array  $dataRows,
        array  $meta      = [],
        array  $colWidths = [],
        bool   $hasTotals = false,
    ): self {
        $rows   = [];
        $rows[] = [$title];
        foreach (array_slice($meta, 0, 2) as $line) {
            $rows[] = [$line];
        }
        // Pad to row 3 with generated timestamp
        while (count($rows) < 3) {
            $rows[] = ['Generated: ' . now()->format('d/m/Y H:i:s')];
        }
        $rows[] = []; // Row 4 — blank spacer
        $rows[] = $headings; // Row 5 — headings

        foreach ($dataRows as $row) {
            $rows[] = $row;
        }

        return new self(
            rows:       $rows,
            headingRow: 5,
            numCols:    count($headings),
            colWidths:  $colWidths,
            hasTotals:  $hasTotals,
        );
    }
}
