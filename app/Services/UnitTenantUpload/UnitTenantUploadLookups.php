<?php

namespace App\Services\UnitTenantUpload;

use App\Models\Gender;
use App\Models\SystemCountry;
use App\Models\TenantType;
use App\Models\UnitCategory;
use App\Models\UnitTopology;
use App\Models\UnitType;
use App\Models\UsageType;

/**
 * Name-to-id lookups for the upload dropdowns. Values match case-insensitively with
 * whitespace ignored, so "3 BR" and "3BR" are the same.
 */
class UnitTenantUploadLookups
{
    private ?array $maps = null;

    /**
     * Build from ready-made maps (list => [[value => ['id' => x, 'name' => y]]]); used by tests.
     *
     * @param array $maps
     * @return self
     */
    public static function fromArray(array $maps): self
    {
        $lookups = new self();
        $lookups->maps = [];

        foreach ($maps as $list => $entries) {
            foreach ($entries as $value => $entry) {
                $lookups->maps[$list][self::key((string) $value)] = $entry;
            }
        }

        return $lookups;
    }

    /**
     * Id of the matching row in a list, or null when there is no match.
     *
     * @param string $list
     * @param string|null $value
     * @return int|string|null
     */
    public function id(string $list, ?string $value)
    {
        return $this->entry($list, $value)['id'] ?? null;
    }

    /**
     * Canonical stored name of the matching row, or null when there is no match.
     *
     * @param string $list
     * @param string|null $value
     * @return string|null
     */
    public function name(string $list, ?string $value): ?string
    {
        return $this->entry($list, $value)['name'] ?? null;
    }

    /**
     * Whether the value exists in the list.
     *
     * @param string $list
     * @param string|null $value
     * @return bool
     */
    public function has(string $list, ?string $value): bool
    {
        return $this->entry($list, $value) !== null;
    }

    /**
     * Display names in a list, for dropdowns.
     *
     * @param string $list
     * @return array
     */
    public function names(string $list): array
    {
        return array_values(array_unique(array_column($this->maps()[$list] ?? [], 'name')));
    }

    /**
     * Lookup entry for a value.
     *
     * @param string $list
     * @param string|null $value
     * @return array|null
     */
    private function entry(string $list, ?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $this->maps()[$list][self::key($value)] ?? null;
    }

    /**
     * Lowercase with all whitespace removed.
     *
     * @param string $value
     * @return string
     */
    public static function key(string $value): string
    {
        return strtolower(preg_replace('/\s+/', '', $value));
    }

    /**
     * Lazily load every list from the database.
     *
     * @return array
     */
    private function maps(): array
    {
        if ($this->maps !== null) {
            return $this->maps;
        }

        $maps = ['unit_types' => [], 'usage_types' => [], 'topologies' => [], 'tenant_types' => [], 'categories' => [], 'countries' => [], 'genders' => []];

        foreach (UnitType::orderBy('id')->get(['id', 'type_name']) as $r) {
            $maps['unit_types'][self::key($r->type_name)] = ['id' => $r->id, 'name' => $r->type_name];
        }
        foreach (UsageType::orderBy('id')->get(['id', 'type_name']) as $r) {
            $maps['usage_types'][self::key($r->type_name)] = ['id' => $r->id, 'name' => $r->type_name];
        }
        foreach (UnitTopology::orderBy('topology_name')->get(['id', 'topology_name', 'slug']) as $r) {
            $entry = ['id' => $r->id, 'name' => $r->topology_name];
            $maps['topologies'][self::key($r->topology_name)] = $entry;
            if ($r->slug) {
                $maps['topologies'][self::key($r->slug)] ??= $entry;
            }
        }
        foreach (TenantType::orderBy('id')->get(['id', 'name']) as $r) {
            $maps['tenant_types'][self::key($r->name)] = ['id' => $r->id, 'name' => $r->name];
        }
        foreach (UnitCategory::all(['id', 'category_name']) as $r) {
            $maps['categories'][self::key($r->category_name)] = ['id' => $r->id, 'name' => $r->category_name];
        }
        foreach (SystemCountry::all(['id', 'country_name']) as $r) {
            $maps['countries'][self::key($r->country_name)] = ['id' => $r->id, 'name' => $r->country_name];
        }
        foreach (Gender::all(['id', 'name']) as $r) {
            $maps['genders'][self::key($r->name)] = ['id' => $r->id, 'name' => $r->name];
        }

        return $this->maps = $maps;
    }
}
