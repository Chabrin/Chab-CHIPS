<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_hearing_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hearing_id');
            
            $table->string('document_name', 255);
            $table->string('document_path', 500);
            $table->string('document_type', 100)->nullable()->comment('Minutes, Recording, Presentation, etc');
            
            $table->timestamp('uploaded_at')->useCurrent();
            
            $table->foreign('hearing_id')
                  ->references('id')
                  ->on('discipline_hearings')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_hearing_documents');
    }
};
