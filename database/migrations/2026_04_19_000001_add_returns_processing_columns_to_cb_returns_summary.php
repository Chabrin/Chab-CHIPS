<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add columns required for returns processing.
     * These columns support the new returns generation logic in ReturnsSummaryTrait.
     */
    public function up(): void
    {
        Schema::table('cb_returns_summary', function (Blueprint $table) {
            // Financial breakdown columns
            $table->decimal('total_returns', 18, 2)->default(0)->after('return_type')
                ->comment('Sum of all CR lines');
            $table->decimal('rent_amount', 18, 2)->default(0)->after('total_returns')
                ->comment('Sum of commissionable RENT lines');
            $table->decimal('amount_withheld', 18, 2)->default(0)->after('rent_amount')
                ->comment('Amount withheld (below 85% threshold)');
            $table->decimal('gross_commission', 18, 2)->default(0)->after('amount_withheld')
                ->comment('Total commission on rent');
            $table->decimal('vat_commission', 18, 2)->default(0)->after('gross_commission')
                ->comment('VAT on commission (16%)');
            $table->decimal('net_commission_paid', 18, 2)->default(0)->after('vat_commission')
                ->comment('Gross commission + VAT');
            $table->decimal('deductions', 18, 2)->default(0)->after('net_commission_paid')
                ->comment('Sum of all DR lines');
            $table->decimal('other_collections', 18, 2)->default(0)->after('deductions')
                ->comment('Non-rent CR lines');
            $table->decimal('net_amount_payable', 18, 2)->default(0)->after('other_collections')
                ->comment('Total CR - commissions - deductions');

            // Property/client reference columns
            $table->string('property_name', 255)->nullable()->after('property_code');
            $table->string('landlord_name', 255)->nullable()->after('landlord_code');
            $table->string('return_date', 30)->nullable()->after('return_period')
                ->comment('Display date for the return');
            $table->unsignedBigInteger('property_id')->nullable()->after('return_id')
                ->comment('FK to sys_pm_properties');
            $table->unsignedBigInteger('client_id')->nullable()->after('property_id')
                ->comment('FK to cb_clients (landlord)');

            // Zone/staff assignment columns
            $table->unsignedBigInteger('zone_id')->nullable()->after('client_id');
            $table->unsignedBigInteger('field_officer_id')->nullable()->after('zone_id');
            $table->unsignedBigInteger('zone_supervisor_id')->nullable()->after('field_officer_id');
            $table->unsignedBigInteger('zone_manager_id')->nullable()->after('zone_supervisor_id');

            // Audit columns
            $table->unsignedBigInteger('user_id')->nullable()->after('zone_manager_id')
                ->comment('User who generated the return');
            $table->timestamp('server_time')->nullable()->after('user_id');

            // MASTERRETURN tracking
            $table->string('transaction_ref', 500)->nullable()->after('server_time')
                ->comment('Child return codes for MASTERRETURN');

            // Indexes
            $table->index('property_id', 'idx_cb_rs_property_id');
            $table->index('client_id', 'idx_cb_rs_client_id');
            $table->index(['return_period', 'return_type'], 'idx_cb_rs_period_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cb_returns_summary', function (Blueprint $table) {
            $table->dropIndex('idx_cb_rs_property_id');
            $table->dropIndex('idx_cb_rs_client_id');
            $table->dropIndex('idx_cb_rs_period_type');

            $table->dropColumn([
                'total_returns',
                'rent_amount',
                'amount_withheld',
                'gross_commission',
                'vat_commission',
                'net_commission_paid',
                'deductions',
                'other_collections',
                'net_amount_payable',
                'property_name',
                'landlord_name',
                'return_date',
                'property_id',
                'client_id',
                'zone_id',
                'field_officer_id',
                'zone_supervisor_id',
                'zone_manager_id',
                'user_id',
                'server_time',
                'transaction_ref',
            ]);
        });
    }
};
