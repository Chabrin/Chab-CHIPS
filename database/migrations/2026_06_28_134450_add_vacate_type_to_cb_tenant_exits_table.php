<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cb_tenant_exits', function (Blueprint $table) {
            $table->enum('vacate_type', ['Eviction', 'Without Notice', 'With Notice'])
                  ->default('With Notice')
                  ->after('unit_id');

            $table->timestamp('process_1_completed_at')->nullable()->after('finalised_at');

            $table->boolean('deposit_as_rent')->default(false)->after('process_1_completed_at');
            $table->boolean('deposit_as_rent_approved')->default(false)->after('deposit_as_rent');
            $table->foreignId('deposit_as_rent_approved_by')
                  ->nullable()
                  ->after('deposit_as_rent_approved')
                  ->constrained('josya_users')
                  ->nullOnDelete();
            $table->decimal('deposit_as_rent_amount', 12, 2)->nullable()->after('deposit_as_rent_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('cb_tenant_exits', function (Blueprint $table) {
            $table->dropForeign(['deposit_as_rent_approved_by']);
            $table->dropColumn([
                'vacate_type',
                'process_1_completed_at',
                'deposit_as_rent',
                'deposit_as_rent_approved',
                'deposit_as_rent_approved_by',
                'deposit_as_rent_amount',
            ]);
        });
    }
};
