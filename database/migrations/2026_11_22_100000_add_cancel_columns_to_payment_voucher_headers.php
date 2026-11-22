<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cancelled payment voucher keeps its row and its number. These columns record who cancelled it, when and why
 * (the cheque it used is cancelled with it, see VoucherCancellationService). Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cb_payment_vouchers_headers_new')) {
            return;
        }

        Schema::table('cb_payment_vouchers_headers_new', function (Blueprint $table) {
            if (!Schema::hasColumn('cb_payment_vouchers_headers_new', 'cancelled_by')) {
                $table->string('cancelled_by', 255)->nullable();
            }
            if (!Schema::hasColumn('cb_payment_vouchers_headers_new', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            if (!Schema::hasColumn('cb_payment_vouchers_headers_new', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cb_payment_vouchers_headers_new')) {
            return;
        }

        Schema::table('cb_payment_vouchers_headers_new', function (Blueprint $table) {
            foreach (['cancelled_by', 'cancelled_at', 'cancel_reason'] as $column) {
                if (Schema::hasColumn('cb_payment_vouchers_headers_new', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
