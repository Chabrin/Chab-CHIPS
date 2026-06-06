<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cb_tenant_exit_deposit_refunds', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('exit_id');
            $table->unsignedBigInteger('tenant_id');

            $table->decimal('refund_amount', 12, 2);
            $table->text('refund_notes')->nullable();

            $table->unsignedBigInteger('refunded_by')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            $table->foreign('exit_id')->references('id')->on('cb_tenant_exits')->onDelete('cascade');
            $table->foreign('refunded_by')->references('id')->on('josya_users')->onDelete('set null');

            $table->index('exit_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_tenant_exit_deposit_refunds');
    }
};
