<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An advance can be given as an exception to the advance limit (amount over the limit, or the client already has two
 * active advances). These columns record who gave the exception, when, why, and the limit that was available at the
 * time, so the person approving the advance can see it. Safe to run more than once.
 */
return new class extends Migration
{
    private const TABLE = 'sys_pm_clients_advances_requisitions';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (!Schema::hasColumn(self::TABLE, 'limit_exception_reason')) {
                $table->text('limit_exception_reason')->nullable();
            }
            if (!Schema::hasColumn(self::TABLE, 'limit_exception_by')) {
                $table->integer('limit_exception_by')->nullable();
            }
            if (!Schema::hasColumn(self::TABLE, 'limit_exception_at')) {
                $table->timestamp('limit_exception_at')->nullable();
            }
            if (!Schema::hasColumn(self::TABLE, 'limit_exception_kind')) {
                $table->string('limit_exception_kind', 30)->nullable();
            }
            if (!Schema::hasColumn(self::TABLE, 'limit_at_request')) {
                $table->decimal('limit_at_request', 18, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            foreach (['limit_exception_reason', 'limit_exception_by', 'limit_exception_at', 'limit_exception_kind', 'limit_at_request'] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
