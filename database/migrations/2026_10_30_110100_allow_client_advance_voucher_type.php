<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Allow the client_advance voucher type on payment voucher lines. The voucher_type_check constraint only
 * lists client_returns, general_voucher and internal_voucher, so the voucher that pays out an advance
 * could not be saved. Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        $constraint = DB::selectOne(
            "SELECT pg_get_constraintdef(c.oid) AS def FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid WHERE t.relname = 'cb_payment_vouchers_lines_new' AND t.relnamespace = current_schema()::regnamespace AND c.conname = 'voucher_type_check'"
        );

        if ($constraint && !str_contains($constraint->def, 'client_advance')) {
            DB::statement('ALTER TABLE cb_payment_vouchers_lines_new DROP CONSTRAINT voucher_type_check');
            DB::statement("ALTER TABLE cb_payment_vouchers_lines_new ADD CONSTRAINT voucher_type_check CHECK (voucher_type IN ('client_returns', 'general_voucher', 'internal_voucher', 'client_advance'))");
        }
    }

    public function down(): void
    {
        // Not reversed: narrowing the list would fail once client_advance vouchers exist.
    }
};
