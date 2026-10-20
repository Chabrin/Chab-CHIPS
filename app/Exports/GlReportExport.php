<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

/**
 * The GL balance and account ledger sheets: title, a note, the generated stamp, then the
 * headings on row 4 and the data below.
 *
 * GenericReportExport::build() assumes the headings are on row 5, but the blank spacer row it
 * adds is dropped when the file is written, which leaves the real headings unstyled. The rows are
 * laid out here instead. WithStrictNullComparison keeps a zero as a zero; without it a nil
 * balance is written as an empty cell.
 */
class GlReportExport extends GenericReportExport implements WithStrictNullComparison
{
    /**
     * Build the sheet.
     *
     * @param string $title
     * @param string $note
     * @param array $headings
     * @param array $rows
     * @param array $widths column letter => width
     * @param bool $hasTotals style the last row as a totals row
     */
    public static function make(string $title, string $note, array $headings, array $rows, array $widths = [], bool $hasTotals = false): self {
        $all = array_merge([
            [$title],
            [$note],
            ['Generated: ' . now()->format('d/m/Y H:i:s')],
            $headings,
        ], $rows);

        return new static($all, 4, count($headings), $widths, $hasTotals);
    }
}
