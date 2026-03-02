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
        Schema::create('cb_error_logs', function (Blueprint $table) {
            $table->id();
            $table->text('error');
            $table->timestamp('dispatch_time', 0);
            $table->string('route', 100);
            $table->boolean('resolved')->nullable();
            $table->string('user_affected')->nullable();
            $table->text('error_message')->nullable();
            $table->text('stack_trace')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_error_logs');
    }
};
