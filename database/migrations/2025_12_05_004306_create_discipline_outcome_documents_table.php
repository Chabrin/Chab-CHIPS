<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_outcome_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outcome_id');
            
            $table->string('document_name', 255);
            $table->string('document_path', 500);
            
            $table->timestamp('uploaded_at')->useCurrent();
            
            $table->foreign('outcome_id')
                  ->references('id')
                  ->on('discipline_outcomes')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_outcome_documents');
    }
};
