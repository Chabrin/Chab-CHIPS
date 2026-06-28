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
        Schema::create('cb_deposit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_id')->constrained('cb_tenant_exits')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->enum('type', ['RENT', 'ARREARS', 'REPAIRS']);
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('approved_by_landlord')->default(false);
            $table->foreignId('approved_by_agent')->nullable()->constrained('josya_users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['exit_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_deposit_applications');
    }
};
