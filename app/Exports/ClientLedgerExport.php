<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

/**
 * The Client Ledgers sheets. Same layout as the other report exports, except that a zero stays
 * a zero: without WithStrictNullComparison, Maatwebsite writes 0 as an empty cell, which would
 * blank every account balance that is exactly nil.
 */
class ClientLedgerExport extends GenericReportExport implements WithStrictNullComparison
{
}
