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
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->json('recipients');
            $table->string('type'); // email or sms
            $table->integer('status')->default(5); 
            $table->string('recipient_type'); // staff or position
            $table->string('attachment')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('send_status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('send_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
