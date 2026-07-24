<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_appeals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            $table->unsignedBigInteger('outcome_id');
            $table->unsignedBigInteger('filed_by');
            $table->timestamp('filed_at');
            $table->text('grounds');
            $table->string('status')->default('Pending'); // Pending, Upheld, Rejected, Withdrawn
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('decision_action')->nullable(); // Uphold Original Decision, Overturn Decision, Modify Decision

            $table->index('case_id');
            $table->index('outcome_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_appeals');
    }
};
