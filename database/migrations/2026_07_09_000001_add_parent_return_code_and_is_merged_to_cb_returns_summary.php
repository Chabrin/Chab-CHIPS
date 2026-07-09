<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cb_returns_summary', function (Blueprint $table) {
            if (! Schema::hasColumn('cb_returns_summary', 'parent_return_code')) {
                $table->string('parent_return_code', 100)->nullable()->after('transaction_ref')
                    ->comment('Return code of the MASTERRETURN this row was merged into');
            }

            if (! Schema::hasColumn('cb_returns_summary', 'is_merged')) {
                $table->boolean('is_merged')->default(false)->after('parent_return_code')
                    ->comment('True once this MAINRETURN has been merged into a MASTERRETURN');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cb_returns_summary', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('cb_returns_summary', 'parent_return_code') ? 'parent_return_code' : null,
                Schema::hasColumn('cb_returns_summary', 'is_merged') ? 'is_merged' : null,
            ]));
        });
    }
};
