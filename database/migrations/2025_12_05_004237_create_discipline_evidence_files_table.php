<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_evidence_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evidence_id');
            
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('file_type', 100)->nullable();
            $table->integer('file_size')->nullable();
            
            $table->timestamp('uploaded_at')->useCurrent();
            
            $table->foreign('evidence_id')
                  ->references('id')
                  ->on('discipline_evidence')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_evidence_files');
    }
};