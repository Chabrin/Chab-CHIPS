<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_exit_interviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('termination_id');
            $table->text('reason_confirmation')->nullable();
            $table->text('feedback')->nullable();
            $table->boolean('assets_returned')->default(false);
            $table->text('assets_notes')->nullable();
            $table->tinyInteger('would_recommend_rating')->nullable(); // 1-5
            $table->unsignedBigInteger('conducted_by')->nullable();
            $table->timestamp('conducted_at')->nullable();

            $table->index('termination_id');
        });

        Schema::create('hr_final_dues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('termination_id');
            $table->decimal('unpaid_salary', 15, 2)->default(0);
            $table->decimal('leave_payout', 15, 2)->default(0);
            $table->decimal('other_earnings', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('severance_payment', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('computed_by')->nullable();
            $table->timestamp('computed_at')->nullable();

            $table->index('termination_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_final_dues');
        Schema::dropIfExists('hr_exit_interviews');
    }
};
