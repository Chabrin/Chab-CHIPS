<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_suspensions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            $table->unsignedBigInteger('suspended_by');
            $table->timestamp('suspended_at');
            $table->text('reason');
            $table->string('pay_status')->default('Full Pay');
            $table->unsignedBigInteger('lifted_by')->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->text('notes')->nullable();

            $table->index('case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_suspensions');
    }
};
