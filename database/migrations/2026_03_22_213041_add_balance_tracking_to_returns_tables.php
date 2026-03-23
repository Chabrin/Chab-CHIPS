<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add balance/threshold tracking columns to returns tables.
     *
     * cb_returns_clients: track tenant balance & threshold per line item
     * cb_returns_summary: track payable vs non-payable amounts (85% rule)
     */
    public function up(): void
    {
        // ── cb_returns_clients: tenant balance tracking ──
        Schema::table('cb_returns_clients', function (Blueprint $table) {
            $table->decimal('current_balance', 18, 2)->nullable()->after('pay_percentage')
                ->comment('Tenant current invoice balance at return generation');
            $table->decimal('total_amount', 18, 2)->nullable()->after('current_balance')
                ->comment('Invoice total amount');
            $table->decimal('threshold_percentage', 8, 2)->nullable()->after('total_amount')
                ->comment('Calculated payment threshold % (0-100)');
            $table->smallInteger('payable_status')->nullable()->after('threshold_percentage')
                ->comment('0=unpayable, 1=payable(>=85%), 2=no past invoice, 3=past threshold, 17=processed');
        });

        // ── cb_returns_summary: payable breakdown (85% rule) ──
        Schema::table('cb_returns_summary', function (Blueprint $table) {
            $table->decimal('payable_amount', 18, 2)->default(0)->after('net_balance')
                ->comment('Total credit from tenants meeting >=85% threshold');
            $table->decimal('non_payable_amount', 18, 2)->default(0)->after('payable_amount')
                ->comment('Total credit from tenants below 85% threshold');
            $table->integer('payable_units')->default(0)->after('non_payable_amount')
                ->comment('Count of units meeting payment threshold');
            $table->integer('total_units')->default(0)->after('payable_units')
                ->comment('Total units in the return');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cb_returns_clients', function (Blueprint $table) {
            $table->dropColumn(['current_balance', 'total_amount', 'threshold_percentage', 'payable_status']);
        });

        Schema::table('cb_returns_summary', function (Blueprint $table) {
            $table->dropColumn(['payable_amount', 'non_payable_amount', 'payable_units', 'total_units']);
        });
    }
};
