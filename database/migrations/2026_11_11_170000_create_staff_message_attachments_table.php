<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cb_staff_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('cb_staff_messages')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('storage_path')->unique();
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum', 64);
            $table->timestamps();
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_staff_message_attachments');
    }
};
