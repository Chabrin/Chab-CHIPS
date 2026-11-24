<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ReturnSummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How a client wants utilities (service charge, water and electricity bills) on the return, and which items count as
 * utilities.
 *
 * Three settings, kept on the client (sys_pm_clients.utility_returns) and set on the Returns Config tab:
 * - with_rent: one return and one report, utilities inside it (the default, how every return was made until now);
 * - separate_report: one return and one payment, shown as a rent report and a utility report (money never changes);
 * - separate_return: a rent return and a utility return, each approved and paid on its own.
 *
 * The rule for the two sides: rent is rent, rent arrears and every deposit (house, water, electricity); everything else
 * is a utility. Commission, VAT and deductions stay on the rent side.
 */
class UtilityReturnsService
{
    public const WITH_RENT = 'with_rent';
    public const SEPARATE_REPORT = 'separate_report';
    public const SEPARATE_RETURN = 'separate_return';
    public const MODES = [self::WITH_RENT, self::SEPARATE_REPORT, self::SEPARATE_RETURN];

    /** Abbreviations the old system used for deposits; they stay on the rent side. */
    private const DEPOSIT_ALIASES = ['HODE', 'ELDE', 'WADE'];

    /**
     * A setting value as one of the three known modes; anything else is the default.
     *
     * @param string|null $mode
     */
    public static function normalise(?string $mode): string {
        return in_array($mode, self::MODES, true) ? $mode : self::WITH_RENT;
    }

    /**
     * The ids of a client and of every client record with the same name. A landlord can exist as more than one record
     * (the same person registered twice), and their properties point at either, so a setting is kept on all of them.
     *
     * @param Client $client
     * @return Collection of int
     */
    public function relatedClientIds(Client $client): Collection {
        $name = preg_replace('/\s+/', ' ', trim((string) $client->names));

        if ($name === '') {
            return collect([(int) $client->id]);
        }

        return Client::clients()
            ->whereRaw("REGEXP_REPLACE(TRIM(COALESCE(names, '')), '\\s+', ' ', 'g') = ?", [$name])
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->push((int) $client->id)
            ->unique()
            ->values();
    }

    /**
     * The setting of a client.
     *
     * @param int $clientId
     */
    public function modeForClient(int $clientId): string {
        return self::normalise(DB::table('sys_pm_clients')->where('id', $clientId)->value('utility_returns'));
    }

    /**
     * The setting that applies to a property: the one of the client that owns it.
     *
     * @param int $propertyId
     */
    public function modeForProperty(int $propertyId): string {
        return self::normalise(DB::table('sys_pm_properties as p')
            ->join('sys_pm_clients as c', 'c.id', '=', 'p.client_id')
            ->where('p.id', $propertyId)
            ->value('c.utility_returns'));
    }

    /**
     * The setting that applies to a return: the one of the client that owns its property. A merged return has no
     * property of its own and so reads as the default.
     *
     * @param ReturnSummary $return
     */
    public function modeForReturn(ReturnSummary $return): string {
        if (trim((string) $return->property_code) === '') {
            return self::WITH_RENT;
        }

        return self::normalise(DB::table('sys_pm_properties as p')
            ->join('sys_pm_clients as c', 'c.id', '=', 'p.client_id')
            ->where('p.reference_number', $return->property_code)
            ->value('c.utility_returns'));
    }

    /**
     * Save a client's setting on the client and on every record with the same name.
     *
     * @param Client $client
     * @param string $mode one of MODES
     * @return int the number of client records changed
     */
    public function saveForClient(Client $client, string $mode): int {
        return DB::table('sys_pm_clients')
            ->whereIn('id', $this->relatedClientIds($client)->all())
            ->update(['utility_returns' => self::normalise($mode)]);
    }

    /**
     * Whether an invoice item is a utility: anything that is not rent, rent arrears or a deposit.
     *
     * @param string $name the item name as printed on the invoice or the return line
     */
    public static function isUtilityItemName(string $name): bool {
        $name = strtoupper(trim($name));

        if ($name === '' || str_starts_with($name, 'RENT') || str_contains($name, 'DEPOSIT') || str_contains($name, 'PREPAY')) {
            return false;
        }

        return !in_array($name, self::DEPOSIT_ALIASES, true);
    }

    /**
     * The same rule as isUtilityItemName, as an SQL condition on an item name expression (generation works in SQL).
     *
     * @param string $nameExpression e.g. "LEFT(COALESCE(it.item_name, ...), 80)"
     */
    public static function utilityItemSql(string $nameExpression): string {
        $aliases = "'" . implode("', '", self::DEPOSIT_ALIASES) . "'";
        $name = "UPPER(TRIM({$nameExpression}))";

        return "({$name} <> '' AND {$name} NOT LIKE 'RENT%' AND {$name} NOT LIKE '%DEPOSIT%' AND {$name} NOT LIKE '%PREPAY%' AND {$name} NOT IN ({$aliases}))";
    }
}
