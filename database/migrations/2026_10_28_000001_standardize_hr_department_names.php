<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Intentionally disabled (2026-11-04): staging and prod have both FIELD and a separate
     * PROPERTY MANAGEMENT department, so the rename stops. The department names are being
     * edited by hand in HR Settings > Departments instead. This now records as run and does nothing.
     * The original logic is kept below for reference; it will not run again once this is recorded.
     */
    public function up(): void
    {
        // DB::transaction(function () {
        //     $renames = [
        //         'ADMINISTRATION' => 'Administration & General',
        //         'ACCOUNTS' => 'Accounting & Finance',
        //         'FIELD' => 'Property Management',
        //     ];
        //     $updates = [];
        //
        //     // Check every mapping before changing anything. Never merge department records.
        //     foreach ($renames as $oldName => $newName) {
        //         $existing = DB::table('sys_set_departments')
        //             ->whereRaw('LOWER(TRIM(department_name)) = ?', [strtolower($oldName)])
        //             ->get(['id']);
        //
        //         if ($existing->isEmpty()) {
        //             continue; // Already renamed, absent, or given a different custom name.
        //         }
        //
        //         $targetExists = DB::table('sys_set_departments')
        //             ->whereRaw('LOWER(TRIM(department_name)) = ?', [strtolower($newName)])
        //             ->exists();
        //
        //         if ($existing->count() > 1 || $targetExists) {
        //             throw new RuntimeException(
        //                 "Cannot rename department {$oldName} to {$newName}: duplicate departments exist. " .
        //                 'Review the records and their assignments before retrying; no departments were renamed.'
        //             );
        //         }
        //
        //         $updates[$existing->first()->id] = $newName;
        //     }
        //
        //     foreach ($updates as $id => $name) {
        //         // Only the label changes; IDs and all existing assignments stay the same.
        //         DB::table('sys_set_departments')->where('id', $id)->update(['department_name' => $name]);
        //     }
        // });
    }

    public function down(): void
    {
        // Keep the names on rollback: a canonical name may predate this migration.
        // Reverting all matching labels would also rename departments we did not change.
    }
};
