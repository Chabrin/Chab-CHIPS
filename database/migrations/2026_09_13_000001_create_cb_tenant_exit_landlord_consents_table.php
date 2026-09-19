<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cb_tenant_exit_landlord_consents')) {
            Schema::create('cb_tenant_exit_landlord_consents', function (Blueprint $table) {
                $table->id();

                $table->foreignId('exit_id')->constrained('cb_tenant_exits')->cascadeOnDelete();
                $table->foreignId('settlement_id')->constrained('cb_tenant_exit_settlements')->cascadeOnDelete();
                $table->unsignedBigInteger('landlord_id');
                $table->unsignedBigInteger('tenant_id');

                $table->decimal('refund_amount', 12, 2);
                $table->unsignedTinyInteger('current_status_id')->default(1); // 1=Pending, 5=Approved, 41=Rejected

                $table->foreignId('requested_by')->nullable()->constrained('josya_users')->nullOnDelete();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->text('rejection_reason')->nullable();

                $table->timestamps();

                $table->index('exit_id');
                $table->index('landlord_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_tenant_exit_landlord_consents');
    }
};
