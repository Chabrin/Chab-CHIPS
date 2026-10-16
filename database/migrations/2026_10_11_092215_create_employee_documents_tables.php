<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Document categories
        if (!Schema::hasTable('document_types')) {
            Schema::create('document_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->boolean('is_mandatory')->default(false);
                $table->timestamps();
            });
        }

        // 2. Uploaded documents attached to sys_staff
        if (!Schema::hasTable('employee_documents')) {
            Schema::create('employee_documents', function (Blueprint $table) {
                $table->id();
                $table->integer('employee_id');          // Matches sys_staff.id type (integer)
                $table->unsignedBigInteger('document_type_id');
                $table->string('title');
                $table->string('file_path');
                $table->string('file_name');
                $table->unsignedInteger('file_size');
                $table->string('mime_type', 50);
                $table->unsignedBigInteger('uploaded_by')->nullable(); // nullable in case user ID varies
                $table->timestamps();

                // Foreign key referencing sys_staff
                $table->foreign('employee_id')
                      ->references('id')
                      ->on('sys_staff')
                      ->onDelete('cascade');

                $table->foreign('document_type_id')
                      ->references('id')
                      ->on('document_types')
                      ->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('document_types');
    }
};