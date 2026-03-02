<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_incident_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incident_report_id');
            
            $table->string('document_name', 255);
            $table->string('document_path', 500);
            $table->string('document_type', 100)->nullable()->comment('Photo, Video, Email, etc');
            $table->integer('file_size')->nullable();
            
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at')->useCurrent();
            
            $table->foreign('incident_report_id')
                  ->references('id')
                  ->on('discipline_incident_reports')
                  ->onDelete('cascade');
            // $table->foreign('uploaded_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_incident_documents');
    }
};
