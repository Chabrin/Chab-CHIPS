<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The landlord entry type registry: one row per kind of landlord ledger entry (cb_landlord_account
 * entry_type_code), saying whether that kind counts toward the landlord's balance. A code can have
 * extra rows with a description pattern when one code holds two kinds of entry (code 0008 holds
 * both the 3% advance charges and the returns-process balance snapshots). Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cb_landlord_entry_types')) {
            Schema::create('cb_landlord_entry_types', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20);
                $table->string('description_pattern', 100)->nullable();
                $table->string('name', 100);
                $table->string('kind', 30)->default('transaction');
                $table->boolean('counts_toward_balance')->default(true);
                $table->string('notes', 255)->nullable();
                $table->timestamps();
            });
        }

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS cb_landlord_entry_types_code_pattern_unique ON cb_landlord_entry_types (code, COALESCE(description_pattern, ''))");
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_landlord_entry_types');
    }
};
