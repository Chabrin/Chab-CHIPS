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
        Schema::create('confirmation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('section'); // e.g. Staff Contracts
            $table->string('model'); // e.g. App\Models\Contract
            $table->string('action'); // approve or reject
            $table->integer('approval_stage')->nullable()->comment('1=First approval, 2=Second approval');
            $table->text('metadata')->nullable()->comment('Additional data like days_taken, etc');
            $table->json('ids'); // array of updated IDs
            $table->unsignedBigInteger('confirmed_by'); // user id
            $table->timestamp('confirmed_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confirmation_logs');
    }
};