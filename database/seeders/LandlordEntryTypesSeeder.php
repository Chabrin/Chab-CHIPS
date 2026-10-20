<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LandlordEntryTypesSeeder extends Seeder
{
    /**
     * Fill the landlord entry type registry with every entry code already in the landlord ledger,
     * and add the "Landlord Entry Types" page under Accounting Settings for the roles that can open
     * Transaction Types. Safe to re-run: existing registry rows, menu order and permissions are
     * left as they are, so settings finance has changed are never overwritten.
     */
    public function run(): void
    {
        $this->seedMenu();
        $this->seedTypes();
    }

    /**
     * The menu entry, next to Transaction Types, and its permissions.
     */
    protected function seedMenu(): void
    {
        $now = now();
        $transactionTypes = DB::table('cb_sub_modules')->where('sub_module_url', '/finance/transaction-types')->first();
        if (!$transactionTypes) {
            return;
        }

        $existing = DB::table('cb_sub_modules')->where('sub_module_url', '/finance/landlord-entry-types')->first();

        DB::table('cb_sub_modules')->updateOrInsert(
            ['sub_module_url' => '/finance/landlord-entry-types'],
            [
                'sub_module_name' => 'Landlord Entry Types',
                'icon' => 'rule',
                'order' => $existing->order ?? (int) DB::table('cb_sub_modules')->where('module_id', $transactionTypes->module_id)->max('order') + 1,
                'added_by' => 'amos',
                'added_on' => $existing->added_on ?? $now,
                'module_id' => $transactionTypes->module_id,
                'created_at' => $existing->created_at ?? $now,
                'updated_at' => $now,
            ]
        );

        $subModuleId = DB::table('cb_sub_modules')->where('sub_module_url', '/finance/landlord-entry-types')->value('id');
        foreach (DB::table('cb_sub_module_permissions')->where('sub_module_id', $transactionTypes->id)->pluck('role_id') as $roleId) {
            $pair = ['role_id' => $roleId, 'sub_module_id' => $subModuleId];
            if (!DB::table('cb_sub_module_permissions')->where($pair)->exists()) {
                DB::table('cb_sub_module_permissions')->insert($pair + ['added_on' => $now, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    /**
     * One registry row per entry code in the ledger, plus the two returns-process snapshot rows
     * under code 0008. Defaults come from what each kind of entry is: every entry counts, except
     * the snapshots, which restate a balance instead of moving money.
     */
    protected function seedTypes(): void
    {
        $now = now();
        $itemNames = DB::table('sys_set_invoiceable_items')->whereNotNull('billing_code')->pluck('item_name', 'billing_code');

        $special = [
            '00014|' => ['name' => 'Carried forward balance (Opening / Closing Balance C/F)', 'kind' => 'carried_forward', 'counts' => true, 'notes' => 'Closing and opening rows of the same amount cancel each other out; a first opening row is a balance brought in.'],
            '0008|' => ['name' => 'Advance payment charges and interest', 'kind' => 'charge', 'counts' => true, 'notes' => null],
            '0008|%Opening Balance B/F%' => ['name' => 'Returns snapshot: Opening Balance B/F', 'kind' => 'snapshot', 'counts' => false, 'notes' => 'Written by the returns process to restate a balance. It does not move money.'],
            '0008|%Closing Balance C/F%' => ['name' => 'Returns snapshot: Closing Balance C/F', 'kind' => 'snapshot', 'counts' => false, 'notes' => 'Written by the returns process to restate a balance. It does not move money.'],
        ];

        $rows = [];
        foreach (DB::table('cb_landlord_account')->select('entry_type_code')->distinct()->pluck('entry_type_code') as $code) {
            $rows[(string) $code . '|'] = ['code' => (string) $code, 'pattern' => null];
        }
        foreach (array_keys($special) as $key) {
            [$code, $pattern] = explode('|', $key, 2);
            $rows[$key] = ['code' => $code, 'pattern' => $pattern === '' ? null : $pattern];
        }

        foreach ($rows as $key => $row) {
            $exists = DB::table('cb_landlord_entry_types')
                ->where('code', $row['code'])
                ->whereRaw("COALESCE(description_pattern, '') = ?", [(string) $row['pattern']])
                ->exists();
            if ($exists) {
                continue;
            }

            $defaults = $special[$key] ?? ['name' => $this->nameFor($row['code'], $itemNames), 'kind' => 'transaction', 'counts' => true, 'notes' => null];

            DB::table('cb_landlord_entry_types')->insert([
                'code' => $row['code'],
                'description_pattern' => $row['pattern'],
                'name' => mb_substr($defaults['name'], 0, 100),
                'kind' => $defaults['kind'],
                'counts_toward_balance' => $defaults['counts'],
                'notes' => $defaults['notes'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * A readable name for an entry code: the invoiceable item's name, else the commonest description.
     *
     * @param string $code
     * @param \Illuminate\Support\Collection $itemNames
     */
    protected function nameFor(string $code, $itemNames): string
    {
        if ($code === '') {
            return 'No entry code';
        }
        if (!empty($itemNames[$code])) {
            return (string) $itemNames[$code];
        }

        $description = DB::table('cb_landlord_account')
            ->where('entry_type_code', $code)
            ->whereNotNull('entry_description')
            ->groupBy('entry_description')
            ->orderByRaw('COUNT(*) DESC')
            ->value('entry_description');

        return $description ? (string) $description : 'Entry code ' . $code;
    }
}
