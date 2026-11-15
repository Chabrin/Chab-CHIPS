<?php

namespace App\Services\BankStatement;

use Illuminate\Support\Facades\DB;

/**
 * Matches every line of a confirmed statement to a lease. The payer quotes the unit code; the unit's one Active lease
 * gives the tenant, the property and the client. Each line ends as Matched (ready to be paid in), Unmatched (no unit
 * code, unknown code or no Active lease), Ambiguous (two Active leases on the code) or Duplicate (the same transaction
 * is already in a matched line or already a payment). Lines the parser marked Invalid or Skipped are left as they are.
 * This only decides; the payment is made by BankStatementPaymentService.
 */
class BankStatementMatchService
{
    /**
     * Match all Pending lines of one confirmed upload and record the totals on the upload. Runs for an upload that is
     * Confirmed, or Failed when it is started again; one already Matching is left alone so two runs never overlap. Only
     * lines still Pending are looked at, so a run that was cut short picks up where it stopped.
     *
     * @param int $uploadId
     */
    public function matchUpload(int $uploadId): void {
        $upload = DB::table('cb_bank_statement_uploads')->where('id', $uploadId)->first();

        if (!$upload || !in_array($upload->status, ['Confirmed', 'Failed'], true)) {
            return;
        }

        $definition = BankStatementBanks::get($upload->bank);

        DB::table('cb_bank_statement_uploads')->where('id', $uploadId)->update(['status' => 'Matching', 'error' => null, 'updated_at' => now()]);

        DB::table($definition['table'])
            ->where('upload_id', $uploadId)
            ->where('match_status', 'Pending')
            ->orderBy('id')
            ->chunkById(500, fn ($chunk) => $this->matchChunk($chunk, $definition));

        $counts = DB::table($definition['table'])->where('upload_id', $uploadId)->selectRaw('match_status, COUNT(*) as total')->groupBy('match_status')->pluck('total', 'match_status');

        DB::table('cb_bank_statement_uploads')->where('id', $uploadId)->update([
            'status' => 'Matched',
            'matched_count' => (int) (($counts['Matched'] ?? 0) + ($counts['Paid'] ?? 0)),
            'unmatched_count' => (int) (($counts['Unmatched'] ?? 0) + ($counts['Ambiguous'] ?? 0) + ($counts['Duplicate'] ?? 0)),
            'matched_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Decide a chunk of lines. The units, leases and earlier transactions the chunk needs are loaded once for the whole
     * chunk, not once per line.
     *
     * @param \Illuminate\Support\Collection $chunk the Pending lines
     * @param array $definition the bank's entry from BankStatementBanks
     */
    private function matchChunk($chunk, array $definition): void {
        $table = $definition['table'];
        $codes = $chunk->pluck('unit_code')->filter()->map(fn ($code) => self::codeKey($code))->unique()->values()->all();
        $keySql = DB::getQueryGrammar()->wrap('u.unit_code');
        $keySql = 'UPPER(REPLACE(' . $keySql . ", ' ', ''))";

        // Units are stored in mixed case and a few with a space, so the file's code and the unit's are compared as capitals without spaces
        $leases = $codes
            ? DB::table('sys_pm_units as u')
                ->join('sys_pm_lease_agreements as l', 'l.unit_id', '=', 'u.id')
                ->where('l.lease_status', 'Active')
                ->whereIn(DB::raw($keySql), $codes)
                ->get([DB::raw($keySql . ' as code_key'), 'u.id as unit_id', 'u.property_id', 'l.id as lease_id', 'l.tenant_id'])
                ->groupBy('code_key')
            : collect();
        $knownCodes = $codes ? DB::table('sys_pm_units as u')->whereIn(DB::raw($keySql), $codes)->select(DB::raw($keySql . ' as code_key'))->get()->pluck('code_key')->flip() : collect();
        $references = $chunk->pluck($definition['ref'])->filter()->unique()->values()->all();

        // A transaction is the reference together with the amount and, in a statement line, the date
        $seen = [];
        $matchedBefore = $references
            ? DB::table($table)->whereIn($definition['ref'], $references)->where('match_status', 'Matched')->get([$definition['ref'] . ' as ref', $definition['amount'] . ' as amount'])
            : collect();
        foreach ($matchedBefore as $row) {
            $seen[$this->transactionKey($row->ref, $row->amount)] = true;
        }
        $paid = $references ? DB::table('sys_pm_tenants_payments')->whereIn('reference', $references)->get(['reference', 'amount']) : collect();
        foreach ($paid as $row) {
            $seen[$this->transactionKey($row->reference, $row->amount)] = true;
        }

        foreach ($chunk as $line) {
            $reference = $line->{$definition['ref']};
            $key = $this->transactionKey($reference, $line->{$definition['amount']});
            $result = ['match_status' => 'Unmatched', 'match_note' => null, 'unit_id' => null, 'lease_id' => null, 'tenant_id' => null, 'property_id' => null];

            if (isset($seen[$key])) {
                $result['match_status'] = 'Duplicate';
                $result['match_note'] = 'This transaction is already in a matched statement line or already recorded as a payment.';
            } elseif (!$line->unit_code) {
                $result['match_note'] = 'No unit code found in the line.';
            } elseif (!isset($knownCodes[self::codeKey($line->unit_code)])) {
                $result['match_note'] = 'Unit code ' . $line->unit_code . ' does not exist.';
            } elseif (!isset($leases[self::codeKey($line->unit_code)])) {
                $result['match_note'] = 'Unit ' . $line->unit_code . ' has no Active lease.';
            } elseif ($leases[self::codeKey($line->unit_code)]->count() > 1) {
                $result['match_status'] = 'Ambiguous';
                $result['match_note'] = 'Unit code ' . $line->unit_code . ' has ' . $leases[self::codeKey($line->unit_code)]->count() . ' Active leases (' . $leases[self::codeKey($line->unit_code)]->pluck('lease_id')->implode(', ') . ').';
            } else {
                $lease = $leases[self::codeKey($line->unit_code)]->first();
                $result = ['match_status' => 'Matched', 'match_note' => null, 'unit_id' => $lease->unit_id, 'lease_id' => $lease->lease_id, 'tenant_id' => $lease->tenant_id, 'property_id' => $lease->property_id];
                $seen[$key] = true;
            }

            DB::table($table)->where('id', $line->id)->update($result + ['updated_at' => now()]);
        }
    }

    /**
     * A unit code as compared: capitals, no spaces.
     *
     * @param string $code
     */
    public static function codeKey(string $code): string {
        return strtoupper(str_replace(' ', '', trim($code)));
    }

    /**
     * The identity of one bank transaction for the duplicate check.
     *
     * @param string|null $reference
     * @param mixed $amount
     */
    private function transactionKey(?string $reference, $amount): string {
        return strtoupper(trim((string) $reference)) . '|' . number_format((float) $amount, 2, '.', '');
    }
}
