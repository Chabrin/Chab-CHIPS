<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who matched a statement line by hand, and when. Both stay empty for a line the system matched by its unit code. Added as
 * its own migration because the statement tables already exist on the machines that ran the first one. Safe to run more
 * than once.
 */
return new class extends Migration
{
    private array $tables = ['cb_equity_statement_lines', 'cb_coop_statement_lines'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (!Schema::hasTable($name)) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) use ($name) {
                if (!Schema::hasColumn($name, 'matched_by')) {
                    $table->unsignedBigInteger('matched_by')->nullable();
                }
                if (!Schema::hasColumn($name, 'matched_at')) {
                    $table->timestamp('matched_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            if (!Schema::hasTable($name)) {
                continue;
            }

            foreach (['matched_by', 'matched_at'] as $column) {
                if (Schema::hasColumn($name, $column)) {
                    Schema::table($name, fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
