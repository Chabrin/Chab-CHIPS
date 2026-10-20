<?php

namespace App\Services;

use App\Models\LandlordEntryType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The landlord entry type registry (cb_landlord_entry_types): which kinds of landlord ledger entry
 * count toward a landlord's balance.
 *
 * A ledger row is matched to a registry row by its entry_type_code. When a code has rows with a
 * description pattern (an SQL ILIKE pattern such as '%Opening Balance B/F%'), the first pattern that
 * matches the row's entry_description wins, otherwise the code's plain row applies. A code that is
 * not in the registry counts, so no money is ever silently left out.
 *
 * The same matching is done in PHP (counts()) and in SQL (lateralJoin()) and the two must agree.
 */
class LandlordEntryTypeService
{
    const KINDS = [
        'transaction' => 'Transaction',
        'charge' => 'Charge',
        'payout' => 'Payout',
        'carried_forward' => 'Carried forward',
        'snapshot' => 'Balance snapshot',
        'other' => 'Other',
    ];

    /** The registry grouped by code, loaded once per request. */
    protected ?array $byCode = null;

    /**
     * Whether a ledger row with this entry code and description counts toward the balance.
     *
     * @param string|null $code entry_type_code
     * @param string|null $description entry_description
     */
    public function counts(?string $code, ?string $description): bool {
        $type = $this->match($code, $description);

        return $type ? (bool) $type->counts_toward_balance : true;
    }

    /**
     * The registry row a ledger row falls under, or null when its code is not registered.
     *
     * @param string|null $code
     * @param string|null $description
     */
    public function match(?string $code, ?string $description): ?object {
        $types = $this->byCode()[(string) $code] ?? [];
        $plain = null;

        foreach ($types as $type) {
            if ($type->description_pattern === null || $type->description_pattern === '') {
                $plain = $plain ?? $type;
                continue;
            }
            if ($this->likeMatches($type->description_pattern, (string) $description)) {
                return $type;
            }
        }

        return $plain;
    }

    /**
     * SQL that joins each ledger row (alias $alias) to its registry row, exposing et.type_id and
     * et.counts_toward_balance. Use COALESCE(et.counts_toward_balance, TRUE) so an unregistered
     * code counts, as in counts().
     *
     * @param string $alias the cb_landlord_account alias in the outer query
     */
    public function lateralJoin(string $alias = 'l'): string {
        return "LEFT JOIN LATERAL (
                SELECT t.id AS type_id, t.counts_toward_balance
                FROM cb_landlord_entry_types t
                WHERE t.code = {$alias}.entry_type_code
                    AND (t.description_pattern IS NULL OR t.description_pattern = '' OR {$alias}.entry_description ILIKE t.description_pattern)
                ORDER BY (t.description_pattern IS NULL OR t.description_pattern = ''), t.id
                LIMIT 1
            ) et ON TRUE";
    }

    /**
     * Every registry row with how many ledger rows fall under it, for the settings page.
     */
    public function listWithUsage(): Collection {
        $usage = collect(DB::select('SELECT et.type_id, COUNT(*) AS n FROM cb_landlord_account l ' . $this->lateralJoin('l') . ' GROUP BY et.type_id'))
            ->keyBy('type_id');

        return LandlordEntryType::orderBy('code')->orderByRaw("COALESCE(description_pattern, '')")->get()
            ->map(function (LandlordEntryType $type) use ($usage) {
                $type->ledger_rows = (int) ($usage[$type->id]->n ?? 0);

                return $type;
            });
    }

    /**
     * How many ledger rows have a code that is not in the registry (they count by default).
     */
    public function unregisteredRows(): int {
        return (int) DB::selectOne('SELECT COUNT(*) AS n FROM cb_landlord_account l ' . $this->lateralJoin('l') . ' WHERE et.type_id IS NULL')->n;
    }

    /**
     * Add a registry row.
     *
     * @param array $data code, description_pattern, name, kind, counts_toward_balance, notes
     */
    public function create(array $data): LandlordEntryType {
        $type = LandlordEntryType::create($this->clean($data));
        $this->byCode = null;

        return $type;
    }

    /**
     * Change a registry row. The code and pattern stay as they are, because ledger rows are matched on them.
     *
     * @param LandlordEntryType $type
     * @param array $data name, kind, counts_toward_balance, notes
     */
    public function update(LandlordEntryType $type, array $data): LandlordEntryType {
        $clean = $this->clean($data);
        $type->update(['name' => $clean['name'], 'kind' => $clean['kind'], 'counts_toward_balance' => $clean['counts_toward_balance'], 'notes' => $clean['notes']]);
        $this->byCode = null;

        return $type;
    }

    /**
     * Whether a registry row with this code and pattern already exists.
     *
     * @param string $code
     * @param string|null $pattern
     */
    public function exists(string $code, ?string $pattern): bool {
        return LandlordEntryType::where('code', $code)
            ->whereRaw("COALESCE(description_pattern, '') = ?", [trim((string) $pattern)])
            ->exists();
    }

    /**
     * Tidy submitted values: trimmed text, an empty pattern stored as null.
     *
     * @param array $data
     */
    protected function clean(array $data): array {
        $pattern = trim((string) ($data['description_pattern'] ?? ''));

        return [
            'code' => trim((string) ($data['code'] ?? '')),
            'description_pattern' => $pattern === '' ? null : $pattern,
            'name' => trim((string) ($data['name'] ?? '')),
            'kind' => array_key_exists($data['kind'] ?? '', self::KINDS) ? $data['kind'] : 'other',
            'counts_toward_balance' => (bool) ($data['counts_toward_balance'] ?? false),
            'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
        ];
    }

    /**
     * The registry grouped by code, pattern rows in id order.
     */
    protected function byCode(): array {
        if ($this->byCode === null) {
            $this->byCode = [];
            foreach (DB::table('cb_landlord_entry_types')->orderBy('id')->get() as $type) {
                $this->byCode[(string) $type->code][] = $type;
            }
        }

        return $this->byCode;
    }

    /**
     * SQL ILIKE matching in PHP: % is any run of characters, _ is one character, case-insensitive.
     *
     * @param string $pattern
     * @param string $value
     */
    protected function likeMatches(string $pattern, string $value): bool {
        $regex = '/^' . str_replace(['%', '_'], ['.*', '.'], preg_quote($pattern, '/')) . '$/isu';

        return preg_match($regex, $value) === 1;
    }
}
