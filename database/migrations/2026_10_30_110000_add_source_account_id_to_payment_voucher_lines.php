<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record which company bank account a voucher line is paid from. The create-voucher forms send the
 * chosen account, but it was dropped because the column did not exist, so approving the voucher
 * could not tell which bank paid and could not post the bank side of the payment to the GL.
 * Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cb_payment_vouchers_lines_new') && !Schema::hasColumn('cb_payment_vouchers_lines_new', 'source_account_id')) {
            DB::statement('ALTER TABLE cb_payment_vouchers_lines_new ADD COLUMN source_account_id INTEGER NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cb_payment_vouchers_lines_new', 'source_account_id')) {
            DB::statement('ALTER TABLE cb_payment_vouchers_lines_new DROP COLUMN source_account_id');
        }
    }
};
