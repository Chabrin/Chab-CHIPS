<?php

namespace App\Services\BankStatement;

use App\Exceptions\BankStatementException;
use App\Jobs\SendPaymentReceiptJob;
use Illuminate\Support\Facades\DB;

/**
 * Matches a statement line by hand when the unit code could not do it (no code, an unknown code, no Active lease on the
 * unit, or two). Someone picks the client, the property and then one of its Active leases (a unit with its tenant), and confirming does
 * two things in one database transaction: the line is matched to that lease, and it is paid in through the same payment
 * and allocation the automatic run uses. If anything fails nothing is kept and the line stays as it was. The receipt is
 * queued once the transaction has committed. Only a confirming role may match lines, never the person who captured the
 * statement, and only once the statement has finished its run.
 */
class BankStatementManualMatchService
{
    /** The line results a person can still decide. */
    public const MATCHABLE = ['Unmatched', 'Ambiguous'];

    /** The statement results in which no run is working on the lines. */
    public const RUN_FINISHED = ['Completed', 'Failed'];

    protected const LIMIT = 50;
    protected const LIST_LIMIT = 300;

    protected BankStatementConfirmationService $confirmation;
    protected BankStatementPaymentService $payments;

    /**
     * @param BankStatementConfirmationService $confirmation
     * @param BankStatementPaymentService $payments
     */
    public function __construct(BankStatementConfirmationService $confirmation, BankStatementPaymentService $payments) {
        $this->confirmation = $confirmation;
        $this->payments = $payments;
    }

    /**
     * Why this user may not match the lines of a statement, or null when they may.
     *
     * @param string $uploadStatus the statement's status
     * @param int|null $uploadedBy who captured the statement
     * @param int $userId who wants to match
     */
    public function refusal(string $uploadStatus, ?int $uploadedBy, int $userId): ?string {
        if (!$this->confirmation->canConfirm($userId)) {
            return 'Only ' . $this->confirmation->label() . ' can match statement lines by hand.';
        }
        if ($uploadedBy !== null && $uploadedBy === $userId) {
            return 'You captured this statement, so someone else has to match its lines.';
        }
        if (!in_array($uploadStatus, self::RUN_FINISHED, true)) {
            return 'Lines can be matched by hand once the statement has finished its run. It is ' . strtolower($uploadStatus) . ' now.';
        }

        return null;
    }

    /**
     * Why a line with this result cannot be matched by hand, or null when it can.
     *
     * @param string $matchStatus the line's result
     */
    public function lineRefusal(string $matchStatus): ?string {
        if (in_array($matchStatus, self::MATCHABLE, true)) {
            return null;
        }

        return 'This line is ' . strtolower($matchStatus) . '. Only Unmatched or Ambiguous lines can be matched by hand.';
    }

    /**
     * Everything the matching page shows about one line: what the bank said, why it did not match, whether this user may
     * match it, and the unit the file named when that unit can be found. Null when the line does not exist.
     *
     * @param string $bank equity or coop
     * @param int $lineId
     * @param int $userId who opened the page
     * @return array<string, mixed>|null
     */
    public function page(string $bank, int $lineId, int $userId): ?array {
        $definition = BankStatementBanks::get($bank);
        $line = DB::table($definition['table'] . ' as l')
            ->join('cb_bank_statement_uploads as s', 's.id', '=', 'l.upload_id')
            ->where('l.id', $lineId)
            ->first([
                'l.id', 'l.upload_id', 'l.row_no', 'l.unit_code', 'l.payer_name', 'l.match_status', 'l.match_note',
                'l.' . $definition['date'] . ' as date', 'l.' . $definition['ref'] . ' as reference', 'l.' . $definition['amount'] . ' as amount',
                'l.' . $definition['text'] . ' as description', 's.file_name', 's.status as upload_status', 's.uploaded_by',
            ]);

        if (!$line) {
            return null;
        }

        $refusal = $this->refusal($line->upload_status, $line->uploaded_by !== null ? (int) $line->uploaded_by : null, $userId) ?? $this->lineRefusal($line->match_status);

        return [
            'id' => (int) $line->id,
            'bank' => $bank,
            'bank_label' => $definition['label'],
            'upload_id' => (int) $line->upload_id,
            'file_name' => $line->file_name,
            'row_no' => (int) $line->row_no,
            'date' => $line->date ? substr((string) $line->date, 0, 10) : null,
            'reference' => $line->reference,
            'amount' => number_format((float) $line->amount, 2),
            'description' => $line->description,
            'unit_code' => $line->unit_code,
            'payer_name' => $line->payer_name,
            'status' => $line->match_status,
            'note' => $line->match_note,
            'refusal' => $refusal,
            'suggestion' => $refusal ? null : $this->suggestion($line->unit_code),
        ] + $this->mobileHints($refusal, $line->unit_code, $line->description);
    }

    /**
     * The client, property and unit a unit code points to, when exactly one unit has that code, so the page can start at
     * the tenant. Null when the code is empty, unknown or shared by several units.
     *
     * @param string|null $unitCode
     * @return array<string, array<string, mixed>>|null
     */
    public function suggestion(?string $unitCode): ?array {
        if (!$unitCode) {
            return null;
        }

        $units = DB::table('sys_pm_units as u')
            ->join('sys_pm_properties as p', 'p.id', '=', 'u.property_id')
            ->join('sys_pm_clients as c', 'c.id', '=', 'p.client_id')
            ->whereRaw('UPPER(REPLACE(' . $this->column('u.unit_code') . ", ' ', '')) = ?", [BankStatementMatchService::codeKey($unitCode)])
            ->limit(2)
            ->get(['u.id as unit_id', 'u.unit_code', 'u.unit_name', 'u.unit_number', 'p.id as property_id', 'p.property_name', 'p.reference_number as property_reference', 'c.id as client_id', 'c.names as client_names', 'c.reference_number as client_reference']);

        if ($units->count() !== 1) {
            return null;
        }

        $row = $units->first();

        return [
            'client' => ['id' => (int) $row->client_id, 'names' => $row->client_names, 'reference' => $row->client_reference],
            'property' => ['id' => (int) $row->property_id, 'name' => $row->property_name, 'reference' => $row->property_reference],
            'unit' => ['id' => (int) $row->unit_id, 'code' => $row->unit_code, 'name' => $row->unit_name, 'number' => $row->unit_number],
        ];
    }

    /**
     * The tenants who probably paid, found from the mobile number in the line's description, for a line the unit code could
     * not place. Nothing is looked for when this user may not match the line or the unit code already points to one unit.
     * Returns the number found (shown as 0712 345 678) and the candidates, each a lease with its client, property, unit and
     * tenant, so the page can fill the three steps in one click.
     *
     * @param string|null $refusal why this user may not match, or null
     * @param string|null $unitCode what the parser read as the unit code
     * @param string|null $description the line's details
     * @return array{mobile: string|null, candidates: array<int, array<string, mixed>>}
     */
    private function mobileHints(?string $refusal, ?string $unitCode, ?string $description): array {
        $none = ['mobile' => null, 'candidates' => []];

        if ($refusal || $this->suggestion($unitCode)) {
            return $none;
        }

        $mobile = $this->mobileIn($description);

        return $mobile ? ['mobile' => '0' . substr($mobile, 0, 3) . ' ' . substr($mobile, 3, 3) . ' ' . substr($mobile, 6), 'candidates' => $this->candidatesByMobile($mobile)] : $none;
    }

    /**
     * The one Kenyan mobile number in a text, as its last nine digits (712345678), whether it is written 0712345678,
     * +254712345678 or 254712345678. Null when there is none or when the text holds two different numbers, because the
     * payer cannot be told. Longer digit runs (account numbers) are not read as a mobile.
     *
     * @param string|null $text
     */
    public function mobileIn(?string $text): ?string {
        if (!$text || !preg_match_all('/(?<!\d)(?:\+?254|0)([17]\d{8})(?!\d)/', $text, $found)) {
            return null;
        }

        $numbers = array_unique($found[1]);

        return count($numbers) === 1 ? $numbers[0] : null;
    }

    /**
     * The tenants whose mobile number (on their client record or their tenant record) ends in these nine digits, each with
     * the Active leases they hold, or their latest lease when none is Active. A number shared by more than three tenants
     * is not a recommendation and gives nothing. At most five leases come back, Active ones first.
     *
     * @param string $mobile the last nine digits
     * @return array<int, array<string, mixed>>
     */
    public function candidatesByMobile(string $mobile): array {
        $like = '%' . $mobile;

        $tenants = DB::table('sys_pm_clients as t')
            ->leftJoin('cb_tenant_records as r', 'r.id', '=', 't.id')
            ->where('t.type_id', 2)
            ->where(fn ($q) => $q
                ->whereRaw($this->plainNumber('t.mobile_number') . ' LIKE ?', [$like])
                ->orWhereRaw($this->plainNumber('r.phone') . ' LIKE ?', [$like]))
            ->limit(50)
            ->get(['t.id', 't.mobile_number', 'r.phone'])
            ->filter(fn ($row) => $this->endsWithMobile($row->mobile_number, $mobile) || $this->endsWithMobile($row->phone, $mobile))
            ->pluck('id');

        if ($tenants->isEmpty() || $tenants->count() > 3) {
            return [];
        }

        $leases = DB::table('sys_pm_lease_agreements as l')
            ->join('sys_pm_units as u', 'u.id', '=', 'l.unit_id')
            ->join('sys_pm_properties as p', 'p.id', '=', 'u.property_id')
            ->join('sys_pm_clients as c', 'c.id', '=', 'p.client_id')
            ->join('sys_pm_clients as t', 't.id', '=', 'l.tenant_id')
            ->whereIn('l.tenant_id', $tenants->all())
            ->orderByRaw('CASE WHEN ' . $this->column('l.lease_status') . " = 'Active' THEN 0 ELSE 1 END")
            ->orderByDesc('l.date_start')
            ->orderByDesc('l.id')
            ->limit(50)
            ->get(['l.id as lease_id', 'l.tenant_id', 'l.lease_status', 'u.id as unit_id', 'u.unit_code', 'u.unit_name', 'u.unit_number', 'p.id as property_id', 'p.property_name', 'p.reference_number as property_reference', 'c.id as client_id', 'c.names as client_names', 'c.reference_number as client_reference', 't.names as tenant', 't.reference_number as tenant_reference']);

        // A tenant with an Active lease is shown by those; one with none by the latest lease they had
        $hasActive = $leases->where('lease_status', 'Active')->pluck('tenant_id')->unique()->flip();
        $latestOnly = [];
        $picked = $leases->filter(function ($row) use ($hasActive, &$latestOnly) {
            if (isset($hasActive[$row->tenant_id])) {
                return $row->lease_status === 'Active';
            }
            if (isset($latestOnly[$row->tenant_id])) {
                return false;
            }

            return $latestOnly[$row->tenant_id] = true;
        });

        return $picked->take(5)->map(fn ($row) => [
            'lease_id' => (int) $row->lease_id,
            'status' => $row->lease_status,
            'tenant' => $row->tenant,
            'tenant_reference' => $row->tenant_reference,
            'client' => ['id' => (int) $row->client_id, 'names' => $row->client_names, 'reference' => $row->client_reference],
            'property' => ['id' => (int) $row->property_id, 'name' => $row->property_name, 'reference' => $row->property_reference],
            'unit' => ['id' => (int) $row->unit_id, 'code' => $row->unit_code, 'name' => $row->unit_name, 'number' => $row->unit_number],
        ])->values()->all();
    }

    /**
     * SQL that reads a phone column without spaces and dashes, so 0712 345 678 and 0712-345-678 are found by their digits.
     *
     * @param string $name for example t.mobile_number
     */
    private function plainNumber(string $name): string {
        return "REPLACE(REPLACE(" . $this->column($name) . ", ' ', ''), '-', '')";
    }

    /**
     * Whether a stored phone number, however it was typed, ends in these nine digits.
     *
     * @param string|null $stored
     * @param string $mobile the last nine digits
     */
    private function endsWithMobile(?string $stored, string $mobile): bool {
        $digits = preg_replace('/\D+/', '', (string) $stored);

        return strlen($digits) >= 9 && substr($digits, -9) === $mobile;
    }

    /**
     * Step 1: the clients (landlords), found by name or reference, with how many properties each has.
     *
     * @param string $search part of a name or reference; empty gives the first clients by name
     * @return array{total: int, data: array<int, array<string, mixed>>}
     */
    public function clients(string $search = ''): array {
        $query = DB::table('sys_pm_clients as c')->where('c.type_id', '1');
        $this->search($query, $search, ['c.names', 'c.reference_number']);
        $total = (clone $query)->count();

        $rows = $query->select('c.id', 'c.names', 'c.reference_number')
            ->selectSub(DB::table('sys_pm_properties as p')->whereColumn('p.client_id', 'c.id')->selectRaw('COUNT(*)'), 'properties_count')
            ->orderBy('c.names')
            ->limit(self::LIMIT)
            ->get();

        return [
            'total' => $total,
            'data' => $rows->map(fn ($row) => ['id' => (int) $row->id, 'names' => $row->names, 'reference' => $row->reference_number, 'properties' => (int) $row->properties_count])->all(),
        ];
    }

    /**
     * Step 2: the properties of one client, with how many units each has.
     *
     * @param int $clientId
     * @param string $search part of a property name or reference
     * @return array{total: int, data: array<int, array<string, mixed>>}
     */
    public function properties(int $clientId, string $search = ''): array {
        $query = DB::table('sys_pm_properties as p')->where('p.client_id', $clientId);
        $this->search($query, $search, ['p.property_name', 'p.reference_number']);
        $total = (clone $query)->count();

        $rows = $query->select('p.id', 'p.property_name', 'p.reference_number')
            ->selectSub(DB::table('sys_pm_units as u')->whereColumn('u.property_id', 'p.id')->selectRaw('COUNT(*)'), 'units_count')
            ->orderBy('p.property_name')
            ->limit(self::LIST_LIMIT)
            ->get();

        return [
            'total' => $total,
            'data' => $rows->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->property_name, 'reference' => $row->reference_number, 'units' => (int) $row->units_count])->all(),
        ];
    }

    /**
     * Step 3: the Active leases of one property, each with its unit and tenant, so a row is already the choice of unit and
     * tenant. Vacant units are not listed. With $includeEnded the leases that have ended are listed too (after the Active
     * ones of the same unit), because a tenant who has left can still owe arrears.
     *
     * @param int $propertyId
     * @param string $search part of a unit code, unit name or number, or a tenant's name or reference
     * @param bool $includeEnded also list the leases that are not Active
     * @return array{total: int, data: array<int, array<string, mixed>>}
     */
    public function leases(int $propertyId, string $search = '', bool $includeEnded = false): array {
        $query = DB::table('sys_pm_lease_agreements as l')
            ->join('sys_pm_units as u', 'u.id', '=', 'l.unit_id')
            ->join('sys_pm_clients as t', 't.id', '=', 'l.tenant_id')
            ->where('u.property_id', $propertyId);

        if (!$includeEnded) {
            $query->where('l.lease_status', 'Active');
        }
        $this->search($query, $search, ['u.unit_code', 'u.unit_name', 'u.unit_number', 't.names', 't.reference_number']);
        $total = (clone $query)->count();

        $rows = $query
            ->orderBy('u.unit_code')
            ->orderByRaw('CASE WHEN ' . $this->column('l.lease_status') . " = 'Active' THEN 0 ELSE 1 END")
            ->orderByDesc('l.date_start')
            ->orderByDesc('l.id')
            ->limit(self::LIST_LIMIT)
            ->get(['l.id', 'l.unit_id', 'l.tenant_id', 'l.lease_status', 'l.date_start', 'l.date_end', 'u.unit_code', 'u.unit_name', 'u.unit_number', 't.names', 't.reference_number']);

        return [
            'total' => $total,
            'data' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'unit_id' => (int) $row->unit_id,
                'unit_code' => $row->unit_code,
                'unit_name' => $row->unit_name,
                'unit_number' => $row->unit_number,
                'tenant_id' => (int) $row->tenant_id,
                'tenant' => $row->names,
                'reference' => $row->reference_number,
                'status' => $row->lease_status,
                'start' => $row->date_start ? substr((string) $row->date_start, 0, 10) : null,
                'end' => $row->date_end ? substr((string) $row->date_end, 0, 10) : null,
            ])->all(),
        ];
    }

    /**
     * Match one line to a lease and pay it in, together. The line is locked, so two people cannot match it at once. The
     * unit chosen on the page must be the lease's unit, so what was confirmed is what is saved. The line takes the unit's
     * code, the lease's tenant and property, and who matched it and when; then it goes through the same payment as an
     * automatic line, created by the person who matched it. A payment that fails rolls everything back. A transaction
     * that turns out to be recorded as a payment already becomes Duplicate and is not paid again. Returns what to tell
     * the user; the receipt email is queued after the transaction commits.
     *
     * @param string $bank equity or coop
     * @param int $lineId
     * @param int $unitId the unit chosen on the page
     * @param int $leaseId the lease chosen on the page
     * @param int $userId who matches
     * @return array<string, mixed>
     */
    public function matchByHand(string $bank, int $lineId, int $unitId, int $leaseId, int $userId): array {
        $definition = BankStatementBanks::get($bank);
        $table = $definition['table'];

        $result = DB::transaction(function () use ($bank, $definition, $table, $lineId, $unitId, $leaseId, $userId) {
            $line = DB::table($table)->where('id', $lineId)->lockForUpdate()->first();

            if (!$line) {
                throw new BankStatementException('That line does not exist.');
            }

            $upload = DB::table('cb_bank_statement_uploads')->where('id', $line->upload_id)->first();
            $refusal = $this->refusal($upload->status, $upload->uploaded_by !== null ? (int) $upload->uploaded_by : null, $userId) ?? $this->lineRefusal($line->match_status);

            if ($refusal) {
                throw new BankStatementException($refusal);
            }

            $lease = DB::table('sys_pm_lease_agreements as l')
                ->join('sys_pm_units as u', 'u.id', '=', 'l.unit_id')
                ->join('sys_pm_clients as t', 't.id', '=', 'l.tenant_id')
                ->where('l.id', $leaseId)
                ->first(['l.id', 'l.unit_id', 'l.tenant_id', 'l.property_id', 'u.property_id as unit_property_id', 'u.unit_code', 't.names as tenant']);

            if (!$lease || (int) $lease->unit_id !== $unitId) {
                throw new BankStatementException('That tenant is not on the unit you chose. Choose the tenant again.');
            }

            DB::table($table)->where('id', $lineId)->update([
                'unit_code' => $lease->unit_code,
                'unit_id' => $lease->unit_id,
                'lease_id' => $lease->id,
                'tenant_id' => $lease->tenant_id,
                'property_id' => $lease->property_id ?: $lease->unit_property_id,
                'match_status' => 'Matched',
                'match_note' => null,
                'matched_by' => $userId,
                'matched_at' => now(),
                'updated_at' => now(),
            ]);

            $paymentId = $this->payments->payLine($upload, $definition, $lineId, $userId);
            $this->refreshCounts((int) $upload->id, $definition);
            $next = $this->nextLine($table, (int) $upload->id, (int) $line->row_no);

            if ($paymentId === null) {
                if (DB::table($table)->where('id', $lineId)->value('match_status') === 'Duplicate') {
                    return ['paid' => false, 'message' => 'Not paid: this transaction is already recorded as a payment, so the line is now marked Duplicate.', 'next_line_id' => $next];
                }

                throw new BankStatementException('The line could not be paid in, so it was left as it was.');
            }

            $payment = DB::table('sys_pm_tenants_payments')->where('id', $paymentId)->first(['receipt_number', 'amount']);
            $allocated = round((float) DB::table('sys_pm_tenants_payments_invoices')->where('payment_id', $paymentId)->sum('amount_allocated'), 2);

            return [
                'paid' => true,
                'payment_id' => $paymentId,
                'receipt_number' => $payment->receipt_number,
                'amount' => number_format((float) $payment->amount, 2),
                'allocated' => number_format($allocated, 2),
                'credit' => number_format(max(0, round((float) $payment->amount - $allocated, 2)), 2),
                'tenant' => $lease->tenant,
                'unit_code' => $lease->unit_code,
                'message' => 'Matched to ' . $lease->tenant . ' (' . $lease->unit_code . ') and paid in as ' . $payment->receipt_number . '.',
                'next_line_id' => $next,
            ];
        });

        // The receipt is queued only after the transaction has committed
        if ($result['paid']) {
            SendPaymentReceiptJob::dispatch($bank, $lineId, $result['payment_id'], $userId);
        }

        return $result;
    }

    /**
     * The next line of the same statement still waiting for a decision, after this row, or the first one when there is
     * none after it. Null when no line is waiting.
     *
     * @param string $table the bank's line table
     * @param int $uploadId
     * @param int $afterRow the row just matched
     */
    private function nextLine(string $table, int $uploadId, int $afterRow): ?int {
        $waiting = DB::table($table)->where('upload_id', $uploadId)->whereIn('match_status', self::MATCHABLE);
        $next = (clone $waiting)->where('row_no', '>', $afterRow)->orderBy('row_no')->value('id') ?? (clone $waiting)->orderBy('row_no')->value('id');

        return $next ? (int) $next : null;
    }

    /**
     * Bring the totals on the statement in line with its lines.
     *
     * @param int $uploadId
     * @param array $definition the bank's entry from BankStatementBanks
     */
    private function refreshCounts(int $uploadId, array $definition): void {
        $lines = DB::table($definition['table'])->where('upload_id', $uploadId);
        $counts = (clone $lines)->selectRaw('match_status, COUNT(*) as total')->groupBy('match_status')->pluck('total', 'match_status');

        DB::table('cb_bank_statement_uploads')->where('id', $uploadId)->update([
            'matched_count' => (int) (($counts['Matched'] ?? 0) + ($counts['Paid'] ?? 0)),
            'unmatched_count' => (int) (($counts['Unmatched'] ?? 0) + ($counts['Ambiguous'] ?? 0) + ($counts['Duplicate'] ?? 0)),
            'paid_count' => (int) ($counts['Paid'] ?? 0),
            'paid_amount' => round((float) (clone $lines)->where('match_status', 'Paid')->sum($definition['amount']), 2),
            'updated_at' => now(),
        ]);
    }

    /**
     * Narrow a query to the rows where any of the columns contains the text, ignoring case. The text is matched as typed:
     * the characters LIKE treats as wildcards are escaped.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $term what was typed
     * @param string[] $columns the columns to look in
     */
    private function search($query, string $term, array $columns): void {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $like = '%' . mb_strtolower(str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term)) . '%';

        $query->where(function ($group) use ($columns, $like) {
            foreach ($columns as $column) {
                $group->orWhereRaw('LOWER(' . $this->column($column) . ") LIKE ? ESCAPE '!'", [$like]);
            }
        });
    }

    /**
     * A table.column name quoted for raw SQL, with the connection's table prefix applied to the alias the way the query
     * builder does, so it works whatever prefix the connection has.
     *
     * @param string $name for example c.names
     */
    private function column(string $name): string {
        return DB::getQueryGrammar()->wrap($name);
    }
}
