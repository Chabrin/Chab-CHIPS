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
        Schema::create('cb_tenant_exit_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_id')->constrained('cb_tenant_exits')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->decimal('deposit_on_record', 12, 2)->default(0);
            $table->decimal('arrears_amount', 12, 2)->default(0);
            $table->decimal('inspection_damage', 12, 2)->default(0);
            $table->decimal('cleaning_cost', 12, 2)->default(0);
            $table->decimal('deposit_as_rent_applied', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->text('other_deductions_notes')->nullable();
            $table->decimal('settlement_balance', 12, 2)->default(0);
            $table->enum('settlement_type', ['Refund to Tenant', 'Invoice to Tenant', 'Zero Balance']);
            $table->text('notes')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('josya_users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['exit_id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_tenant_exit_settlements');
    }
};
