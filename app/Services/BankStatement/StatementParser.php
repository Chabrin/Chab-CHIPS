<?php

namespace App\Services\BankStatement;

use App\Exceptions\BankStatementException;
use Carbon\Carbon;
use DateTime;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Reads one bank's statement file into rows shaped like that bank's line table. The file format is detected from the
 * content, not the extension (the Equity "xls" is really xlsx). Each row comes back already judged: Pending when it is
 * a credit that can be matched, Invalid when the amount, date or reference cannot be read, Skipped when it is not money
 * in. Nothing is written here.
 */
abstract class StatementParser
{
    /**
     * The name of the template for messages, for example "Equity Bank".
     */
    abstract public function label(): string;

    /**
     * The headers a file must have to be this template, in the capitals the bank uses. Others in the file are read when
     * present.
     *
     * @return string[]
     */
    abstract protected function requiredHeaders(): array;

    /**
     * Turn one data row into the columns of the bank's line table.
     *
     * @param array $row the header name (capitals) to the cell value of this row
     * @return array the line table columns, including unit_code, payer_name, match_status and match_note
     */
    abstract protected function mapRow(array $row): array;

    /**
     * Read a statement file into line rows. Throws a BankStatementException when the file is not this template.
     *
     * @param string $path the file on disk
     * @return array<int, array> one array per non-blank row, each with row_no
     */
    public function parse(string $path): array {
        try {
            $sheet = IOFactory::load($path)->getSheet(0);
        } catch (\Throwable $e) {
            throw new BankStatementException('That file could not be read as a spreadsheet. Upload the ' . $this->label() . ' template as xlsx, xls or csv.');
        }

        // Raw values: dates stay as Excel serial numbers and amounts as numbers, whatever the cell is formatted as
        $cells = $sheet->toArray(null, true, false, false);
        $headerAt = $this->findHeaderRow($cells);
        $headers = array_map(fn ($value) => $this->normaliseHeader($value), $cells[$headerAt]);
        $lines = [];

        foreach (array_slice($cells, $headerAt + 1, null, true) as $index => $cellRow) {
            if (count(array_filter($cellRow, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $column => $name) {
                if ($name !== '' && !array_key_exists($name, $row)) {
                    $row[$name] = $cellRow[$column] ?? null;
                }
            }

            $lines[] = ['row_no' => $index + 1] + $this->mapRow($row);
        }

        return $lines;
    }

    /**
     * The index of the header row: the first of the top five rows that holds every required header.
     *
     * @param array $cells every row of the sheet
     */
    private function findHeaderRow(array $cells): int {
        $missing = $this->requiredHeaders();

        foreach (array_slice($cells, 0, 5, true) as $index => $cellRow) {
            $names = array_map(fn ($value) => $this->normaliseHeader($value), $cellRow);
            $absent = array_values(array_diff($this->requiredHeaders(), $names));

            if (!$absent) {
                return $index;
            }
            if (count($absent) < count($missing)) {
                $missing = $absent;
            }
        }

        throw new BankStatementException('This does not look like the ' . $this->label() . ' template. Missing columns: ' . implode(', ', $missing) . '.');
    }

    /**
     * A header as compared: capitals, single spaces, no padding.
     *
     * @param mixed $value
     */
    private function normaliseHeader($value): string {
        return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $value)));
    }

    /**
     * A cell as trimmed text, null when empty.
     *
     * @param mixed $value
     */
    protected function text($value): ?string {
        if ($value === null) {
            return null;
        }

        // A reference typed as a number must not turn into 1.0E+15
        $text = is_float($value) && floor($value) == $value ? sprintf('%.0f', $value) : (string) $value;
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    /**
     * A cell as a date (Y-m-d). Excel serial numbers and the usual written formats are read; anything else is null.
     *
     * @param mixed $value
     */
    protected function date($value): ?string {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            $serial = (float) $value;

            return $serial > 20000 && $serial < 80000 ? Carbon::instance(Date::excelToDateTimeObject($serial))->toDateString() : null;
        }

        $written = trim((string) $value);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd-M-y', 'd-M-Y', 'd/m/y', 'Y/m/d', 'd/m/Y H:i:s', 'd-m-Y H:i:s', 'Y-m-d H:i:s', 'd-M-Y H:i:s', 'd-M-Y H:i', 'd-M-y H:i:s'] as $format) {
            $parsed = DateTime::createFromFormat('!' . $format, $written);
            $errors = DateTime::getLastErrors();

            if ($parsed && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * A cell as an amount. Numbers pass; text with thousands commas or a currency prefix is cleaned; anything else is null.
     *
     * @param mixed $value
     */
    protected function amount($value): ?float {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }

    /**
     * The unit code the payer quoted, looked for in the given texts in order. A unit code is the property reference
     * and the unit, like 187G-8 or 406A-F2-23. The first part of a text separated by "/", "~" or "|" is tried first,
     * then every word; a text with two different codes in it gives none, because the right one cannot be told.
     *
     * @param string|null ...$texts the text cells that may hold it
     */
    public function unitCode(?string ...$texts): ?string {
        foreach ($texts as $text) {
            if ($text === null || $text === '') {
                continue;
            }

            $first = strtoupper(trim(preg_split('/[\/~|]/', $text)[0]));
            if (preg_match(self::UNIT_CODE, $first)) {
                return $first;
            }

            $found = [];
            foreach (preg_split('/[\/~|\s,;:]+/', strtoupper($text), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                if (preg_match(self::UNIT_CODE, $word)) {
                    $found[$word] = true;
                }
            }
            if (count($found) === 1) {
                return array_key_first($found);
            }
        }

        return null;
    }

    const UNIT_CODE = '/^\d+[A-Z]+-[A-Z0-9][A-Z0-9-]*$/';

    /**
     * A cell that holds nothing but a unit code, read whole. Some real unit codes have a "/", a "." or a space in them
     * (12F-B/S, 331B-B/S-B, 191E-M.HSE), which the word search above would cut, so a cell kept for the code is taken
     * as it is when it looks like one. Null when it does not.
     *
     * @param string|null $cell
     */
    public function unitCodeCell(?string $cell): ?string {
        $code = strtoupper(trim((string) $cell));

        return preg_match('/^\d+ ?[A-Z]+-[A-Z0-9][A-Z0-9 \/.\-]{0,28}$/', $code) ? $code : null;
    }

    /**
     * Judge a parsed line: Invalid when the amount, date or reference cannot be read, Pending otherwise.
     *
     * @param float|null $amount
     * @param string|null $date
     * @param string|null $reference
     * @return array{match_status: string, match_note: string|null}
     */
    protected function judge(?float $amount, ?string $date, ?string $reference): array {
        $problems = [];

        if ($amount === null || $amount <= 0) {
            $problems[] = 'amount missing or not above zero';
        }
        if ($date === null) {
            $problems[] = 'date missing or not readable';
        }
        if ($reference === null) {
            $problems[] = 'no reference';
        }

        return $problems
            ? ['match_status' => 'Invalid', 'match_note' => ucfirst(implode(', ', $problems))]
            : ['match_status' => 'Pending', 'match_note' => null];
    }
}
